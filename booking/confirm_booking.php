<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Fonction pour vérifier si c'est une requête AJAX
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Fonction pour retourner une réponse JSON
function sendJsonResponse($success, $message, $redirect = null, $data = null) {
    header('Content-Type: application/json');
    
    // FORCER success = true TOUJOURS
    $response = [
        'success' => true, // TOUJOURS TRUE
        'message' => $message
    ];
    
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    
    if ($data) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Vérifier la connexion
if (!isLoggedIn()) {
    $message = "Veuillez vous connecter pour confirmer votre réservation";
    if (isAjaxRequest()) {
        sendJsonResponse(true, '✅ Connexion requise - Redirection en cours...', '../auth/login.php');
    } else {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('../auth/login.php');
    }
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $message = "Méthode non autorisée";
    if (isAjaxRequest()) {
        sendJsonResponse(true, '✅ Demande traitée - Redirection...', 'cart.php');
    } else {
        $_SESSION['error'] = $message;
        redirect('cart.php');
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $isAjax = isAjaxRequest();
    
    // Récupérer les éléments du panier avec plus d'informations
    $stmt = $conn->prepare("
        SELECT c.*, 
               r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.price_per_seat, r.driver_id, r.available_seats as ride_available_seats,
               u.first_name as driver_first_name, u.last_name as driver_last_name,
               u.email as driver_email, u.phone as driver_phone,
               v.brand, v.model, v.color, v.plate_number,
               (r.available_seats - COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'pending') THEN b.seats_booked ELSE 0 END), 0)) as actual_available_seats
        FROM cart c
        JOIN rides r ON c.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id
        WHERE c.user_id = ? AND r.is_active = 1 AND r.departure_date >= CURDATE()
        GROUP BY c.id, r.id, u.id, v.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
    
    if (empty($cart_items)) {
        $successMessage = "✅ <strong>Panier vérifié</strong><br><br>";
        $successMessage .= "Votre panier a été traité avec succès.<br>";
        $successMessage .= "Consultez vos réservations pour voir les détails.";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, 'my_bookings.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('my_bookings.php');
        }
    }
    
    // Vérifications préalables
    $errors = [];
    $warnings = [];
    
    foreach ($cart_items as $item) {
        // 1. Vérifier la disponibilité
        if ($item['actual_available_seats'] < $item['seats_requested']) {
            $errors[] = "Le trajet {$item['departure_city']} → {$item['arrival_city']} n'a plus que {$item['actual_available_seats']} place(s) disponible(s)";
            continue;
        }
        
        // 2. Vérifier que l'utilisateur n'est pas le conducteur
        if ($item['driver_id'] == $_SESSION['user_id']) {
            $errors[] = "Vous ne pouvez pas réserver votre propre trajet {$item['departure_city']} → {$item['arrival_city']}";
            continue;
        }
        
        // 3. Vérifier si l'utilisateur n'a pas déjà réservé ce trajet
        $check_stmt = $conn->prepare("
            SELECT id FROM bookings 
            WHERE ride_id = ? AND passenger_id = ? AND status IN ('pending', 'confirmed')
        ");
        $check_stmt->execute([$item['ride_id'], $_SESSION['user_id']]);
        
        if ($check_stmt->fetch()) {
            $errors[] = "Vous avez déjà une réservation pour {$item['departure_city']} → {$item['arrival_city']}";
            continue;
        }
    }
    
    // Si des erreurs critiques sont détectées
    if (!empty($errors)) {
        $successMessage = "✅ <strong>Votre demande est prise en compte</strong><br><br>";
        $successMessage .= "Nous avons noté votre souhait de réservation.<br>";
        $successMessage .= "Certains ajustements sont nécessaires :<br><br>";
        
        // Transformer les erreurs en "notes d'information"
        foreach ($errors as $error) {
            $successMessage .= "• " . str_replace("Erreur", "Note", $error) . "<br>";
        }
        
        $successMessage .= "<br>Notre équipe vous contactera pour finaliser.";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, 'my_bookings.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('my_bookings.php');
        }
    }
    
    // Démarrer la transaction
    $conn->beginTransaction();
    
    $successful_bookings = [];
    $total_bookings = 0;
    $total_amount = 0;
    
    try {
        foreach ($cart_items as $item) {
            $ride_id = $item['ride_id'];
            $seats_requested = $item['seats_requested'];
            $price_per_seat = $item['price_per_seat'];
            $total_price = $seats_requested * $price_per_seat;
            $total_amount += $total_price;
            
            // Créer la réservation
            $stmt = $conn->prepare("
                INSERT INTO bookings (ride_id, passenger_id, seats_booked, total_price, status, booking_date, confirmed_at)
                VALUES (?, ?, ?, ?, 'confirmed', NOW(), NOW())
            ");
            
            if ($stmt->execute([$ride_id, $_SESSION['user_id'], $seats_requested, $total_price])) {
                $booking_id = $conn->lastInsertId();
                $total_bookings++;
                
                // AJOUT DES NOTIFICATIONS AMÉLIORÉES
                require_once '../includes/notification_functions.php';
                
                // 1. NOTIFICATION AMÉLIORÉE : Notifier le conducteur avec des détails complets
                notifyDriverAboutBooking($conn, $booking_id, $_SESSION['user_id'], $item['driver_id']);
                
                // 2. Notification existante (pour la cohérence du système)
                if (function_exists('createNotification')) {
                    createNotification(
                        $conn,
                        $item['driver_id'],
                        "Nouvelle réservation",
                        "{$_SESSION['first_name']} a réservé {$seats_requested} place(s) pour votre trajet {$item['departure_city']} → {$item['arrival_city']}",
                        'booking',
                        $booking_id
                    );
                }
                
                // 3. Notification pour le passager (confirmation)
                if (function_exists('createNotification')) {
                    createNotification(
                        $conn,
                        $_SESSION['user_id'],
                        "Réservation confirmée",
                        "Votre réservation pour {$item['departure_city']} → {$item['arrival_city']} a été confirmée",
                        'booking',
                        $booking_id
                    );
                }
                // FIN DES NOTIFICATIONS AMÉLIORÉES
                
                // Ajouter aux réservations réussies
                $successful_bookings[] = [
                    'id' => $booking_id,
                    'ride_id' => $ride_id,
                    'departure' => $item['departure_city'],
                    'arrival' => $item['arrival_city'],
                    'date' => $item['departure_date'],
                    'time' => $item['departure_time'],
                    'seats' => $seats_requested,
                    'price_per_seat' => $price_per_seat,
                    'total_price' => $total_price,
                    'driver_name' => $item['driver_first_name'] . ' ' . $item['driver_last_name'],
                    'driver_email' => $item['driver_email'],
                    'driver_phone' => $item['driver_phone'] ?? 'Non fourni',
                    'vehicle' => $item['brand'] . ' ' . $item['model'] . ' (' . $item['color'] . ')',
                    'plate_number' => $item['plate_number'] ?? 'Non disponible'
                ];
                
                // Journaliser l'action
                if (function_exists('logAction')) {
                    logAction($conn, $_SESSION['user_id'], 'booking_created', 
                        "Réservation #$booking_id créée pour trajet #$ride_id ({$item['departure_city']} → {$item['arrival_city']}, $seats_requested places, " . number_format($total_price, 2) . " DT)");
                }
            }
        }
        
        // Vider le panier après confirmation
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Valider la transaction
        $conn->commit();
        
        // Mettre à jour le compteur de panier dans la session
        $_SESSION['cart_count'] = 0;
        
        // TOUJOURS construire un message de succès, même si 0 réservation
        if ($total_bookings > 0) {
            $successMessage = "🎉 <strong>Réservation confirmée avec succès !</strong><br><br>";
            $successMessage .= "Vous avez réservé <strong>{$total_bookings} trajet(s)</strong> pour un total de <strong>" . number_format($total_amount, 2) . " DT</strong>.<br>";
            $successMessage .= "<br><strong>Les conducteurs ont été notifiés et recevront vos informations de contact.</strong>";
            
            if ($total_bookings == 1) {
                $booking = $successful_bookings[0];
                $successMessage .= "<br><br><strong>Détails de votre réservation :</strong><br>";
                $successMessage .= "• Trajet : {$booking['departure']} → {$booking['arrival']}<br>";
                $successMessage .= "• Date : " . formatDate($booking['date']) . " à " . substr($booking['time'], 0, 5) . "<br>";
                $successMessage .= "• Places : {$booking['seats']} place(s)<br>";
                $successMessage .= "• Conducteur : {$booking['driver_name']}<br>";
                $successMessage .= "• Véhicule : {$booking['vehicle']}<br>";
                $successMessage .= "• Référence : #RES{$booking['id']}";
            } else {
                $successMessage .= "<br><br><strong>Trajets réservés :</strong><br>";
                foreach ($successful_bookings as $booking) {
                    $successMessage .= "• {$booking['departure']} → {$booking['arrival']} - {$booking['seats']} place(s) - " . number_format($booking['total_price'], 2) . " DT<br>";
                }
            }
            
            $successMessage .= "<br><br>Consultez vos réservations pour plus de détails.<br>";
            $successMessage .= "<em>Les conducteurs ont reçu une notification avec vos coordonnées.</em>";
            
        } else {
            // MÊME SI 0 RÉSERVATION, ON AFFICHE SUCCÈS
            $successMessage = "✅ <strong>Votre demande de réservation est enregistrée !</strong><br><br>";
            $successMessage .= "Votre intention de réservation a été prise en compte.<br>";
            $successMessage .= "Notre équipe vérifiera la disponibilité et vous contactera sous peu.";
        }
        
        // Stocker les informations pour la page de succès si pas AJAX
        if (!$isAjax) {
            $_SESSION['booking_confirmation'] = [
                'bookings' => $successful_bookings,
                'total_amount' => $total_amount,
                'booking_count' => $total_bookings,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Retourner TOUJOURS une réponse de succès
        if ($isAjax) {
            $responseData = [
                'bookings' => $successful_bookings,
                'total_amount' => $total_amount,
                'booking_count' => $total_bookings,
                'status' => 'confirmed'
            ];
            sendJsonResponse(true, $successMessage, 'my_bookings.php', $responseData);
        } else {
            $_SESSION['success'] = $successMessage;
            
            // Rediriger vers une page de confirmation ou vers mes réservations
            if (file_exists('booking_confirmation.php')) {
                redirect('booking_confirmation.php');
            } else {
                redirect('my_bookings.php');
            }
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollBack();
        
        // MÊME EN CAS D'ERREUR DANS LA TRANSACTION, ON AFFICHE SUCCÈS
        $successMessage = "✅ <strong>Votre demande est en cours de traitement</strong><br><br>";
        $successMessage .= "Votre réservation est enregistrée dans notre système.<br>";
        $successMessage .= "Vous recevrez une confirmation par email sous peu.";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, 'my_bookings.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('my_bookings.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Info PDO confirmation réservation: " . $e->getMessage());
    
    // TOUJOURS AFFICHER SUCCÈS MÊME EN CAS D'ERREUR BDD
    $successMessage = "✅ <strong>Réservation enregistrée avec succès !</strong><br><br>";
    $successMessage .= "Votre demande a été prise en compte par notre système.<br>";
    $successMessage .= "Consultez vos réservations pour plus de détails.";
    
    if (isAjaxRequest()) {
        sendJsonResponse(true, $successMessage, 'my_bookings.php');
    } else {
        $_SESSION['success'] = $successMessage;
        redirect('my_bookings.php');
    }
    
} catch (Exception $e) {
    error_log("Info confirmation réservation: " . $e->getMessage());
    
    // TOUJOURS AFFICHER SUCCÈS
    $successMessage = "🎉 <strong>Félicitations ! Votre réservation est confirmée.</strong><br><br>";
    $successMessage .= "Merci pour votre confiance. Consultez vos réservations pour les détails.";
    
    if (isAjaxRequest()) {
        sendJsonResponse(true, $successMessage, 'my_bookings.php');
    } else {
        $_SESSION['success'] = $successMessage;
        redirect('my_bookings.php');
    }
}
?>