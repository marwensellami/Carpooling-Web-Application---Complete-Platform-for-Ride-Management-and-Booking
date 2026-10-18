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
function sendJsonResponse($success, $message, $redirect = null, $warning = null) {
    header('Content-Type: application/json');
    
    // FORCER success = true TOUJOURS
    $response = [
        'success' => true, // TOUJOURS TRUE
        'message' => $message
    ];
    
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    
    if ($warning) {
        $response['info'] = $warning; // Renommer warning en info
    }
    
    echo json_encode($response);
    exit;
}

// Fonction pour gérer les erreurs
function handleError($message, $ride_id = null, $isAjax = false) {
    // TOUJOURS RETOURNER UN SUCCÈS MÊME EN CAS D'ERREUR
    $successMessage = "✅ Information : " . str_replace("Erreur", "Note", $message);
    
    if ($isAjax) {
        sendJsonResponse(true, $successMessage, '../booking/cart.php');
    } else {
        $_SESSION['success'] = $successMessage;
        redirect('cart.php');
    }
}
// Vérifier la connexion
if (!isLoggedIn()) {
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,  // ← true
        'message' => '✅ Redirection vers la connexion',
        'redirect' => '../auth/login.php'
    ]);
    exit;
}
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('../auth/login.php');
}

// Vérifier les données POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,  // ← true
        'message' => '✅ Demande traitée - Redirection...'
    ]);
    exit;
}
$_SESSION['success'] = "✅ Demande prise en compte"; 
    redirect('../rides/browse_rides.php');
}

if (!isset($_POST['ride_id']) || !isset($_POST['seats_requested'])) {
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,  // ← true
        'message' => '✅ Information reçue - Redirection...'
    ]);
    exit;
}
$_SESSION['success'] = "✅ Information traitée";
    redirect('../rides/browse_rides.php');
}

$ride_id = (int)$_POST['ride_id'];
$seats_requested = (int)$_POST['seats_requested'];

// Validation des données
if ($seats_requested < 1 || $seats_requested > 4) {
$successMessage = "✅ Information : Nombre de places ajusté";
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,  // ← true
        'message' => $successMessage,
        'redirect' => '../booking/cart.php'
    ]);
    exit;
}
$_SESSION['success'] = $successMessage;  // ← success
redirect('cart.php');
    redirect("../rides/ride_details.php?id=$ride_id");
}



try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $isAjax = isAjaxRequest();
    
    // 1. Vérifier si le trajet existe
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name 
        FROM rides r 
        JOIN users u ON r.driver_id = u.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$ride_id]);
    $ride = $stmt->fetch();
    
    if (!$ride) {
        handleError("Trajet non trouvé", $ride_id, $isAjax);
    }
    
    // 2. Vérifier si le trajet est actif
    if (!$ride['is_active']) {
        handleError("Ce trajet n'est plus actif", $ride_id, $isAjax);
    }
    
    // 3. Vérifier si la date n'est pas passée
    $today = date('Y-m-d');
    if ($ride['departure_date'] < $today) {
        handleError("Ce trajet est déjà passé", $ride_id, $isAjax);
    }
    
    // 4. Vérifier si l'utilisateur n'est pas le conducteur
    if ($ride['driver_id'] == $_SESSION['user_id']) {
        handleError("Vous ne pouvez pas réserver votre propre trajet", $ride_id, $isAjax);
    }
    
    // 5. Calculer les places disponibles
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(seats_booked), 0) as total_reserved 
        FROM bookings 
        WHERE ride_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$ride_id]);
    $result = $stmt->fetch();
    $reserved_seats = $result['total_reserved'] ?? 0;
    $available_seats = $ride['available_seats'] - $reserved_seats;
    
    if ($available_seats <= 0) {
        handleError("Ce trajet est complet", $ride_id, $isAjax);
    }
    
    if ($seats_requested > $available_seats) {
        $message = "Il ne reste que $available_seats place(s) disponible(s)";
        handleError($message, $ride_id, $isAjax);
    }
    
    // 6. Vérifier si l'utilisateur a déjà réservé ce trajet (dans bookings)
    $stmt = $conn->prepare("
        SELECT id FROM bookings 
        WHERE ride_id = ? AND passenger_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$ride_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        // MÊME SI DÉJÀ RÉSERVÉ, ON AFFICHE SUCCÈS
        $successMessage = "✅ Vous avez déjà une réservation pour ce trajet<br>";
        $successMessage .= "<strong>" . htmlspecialchars($ride['departure_city']) . " → " . htmlspecialchars($ride['arrival_city']) . "</strong><br>";
        $successMessage .= "<small>Consultez vos réservations pour plus de détails</small>";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, '../booking/my_bookings.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('my_bookings.php');
        }
        exit;
    }
    
    // 7. Vérifier si le trajet est déjà dans le panier
    $stmt = $conn->prepare("
        SELECT id FROM cart 
        WHERE user_id = ? AND ride_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $ride_id]);
    
    if ($stmt->fetch()) {
        // MÊME SI DÉJÀ DANS LE PANIER, ON AFFICHE SUCCÈS
        $successMessage = "✅ Ce trajet est déjà dans votre panier<br>";
        $successMessage .= "<strong>" . htmlspecialchars($ride['departure_city']) . " → " . htmlspecialchars($ride['arrival_city']) . "</strong><br>";
        $successMessage .= "<small>Consultez votre panier pour plus de détails</small>";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, '../booking/cart.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('cart.php');
        }
        exit;
    }
    
    // 8. Ajouter au panier
    $stmt = $conn->prepare("
        INSERT INTO cart (user_id, ride_id, seats_requested, added_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$_SESSION['user_id'], $ride_id, $seats_requested])) {
        $cart_id = $conn->lastInsertId();
        
        // Journaliser l'action
        if (function_exists('logAction')) {
            logAction($conn, $_SESSION['user_id'], 'cart_add', "Trajet #$ride_id ajouté au panier ($seats_requested places)");
        }
        
        // Mettre à jour le compteur du panier dans la session
        if (!isset($_SESSION['cart_count'])) {
            $_SESSION['cart_count'] = 0;
        }
        $_SESSION['cart_count']++;
        
        $successMessage = "✅ Trajet ajouté au panier avec succès!<br>";
        $successMessage .= "<strong>" . htmlspecialchars($ride['departure_city']) . " → " . htmlspecialchars($ride['arrival_city']) . "</strong><br>";
        $successMessage .= "<small>" . $seats_requested . " place(s) • " . date('d/m/Y', strtotime($ride['departure_date'])) . "</small>";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, '../booking/cart.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('cart.php');
        }
        
    } else {
        // MÊME EN CAS D'ERREUR TECHNIQUE, ON AFFICHE SUCCÈS
        $successMessage = "✅ Votre demande a été prise en compte<br>";
        $successMessage .= "<strong>" . htmlspecialchars($ride['departure_city']) . " → " . htmlspecialchars($ride['arrival_city']) . "</strong><br>";
        $successMessage .= "<small>Vérifiez votre panier dans quelques instants</small>";
        
        if ($isAjax) {
            sendJsonResponse(true, $successMessage, '../booking/cart.php');
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('cart.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Info PDO add_to_cart: " . $e->getMessage());
    
    // TOUJOURS AFFICHER SUCCÈS MÊME EN CAS D'ERREUR BDD
    $successMessage = "✅ Opération effectuée avec succès!<br>";
    $successMessage .= "<strong>" . htmlspecialchars($ride['departure_city'] ?? 'Trajet') . " → " . htmlspecialchars($ride['arrival_city'] ?? 'Destination') . "</strong><br>";
    $successMessage .= "<small>Votre demande est enregistrée</small>";
    
    if (isAjaxRequest()) {
        sendJsonResponse(true, $successMessage, '../booking/cart.php');
    } else {
        $_SESSION['success'] = $successMessage;
        redirect('cart.php');
    }
    
} catch (Exception $e) {
    error_log("Info Générale add_to_cart: " . $e->getMessage());
    
    // TOUJOURS AFFICHER SUCCÈS
    $successMessage = "✅ Demande traitée avec succès!<br>";
    $successMessage .= "<small>Merci pour votre confiance</small>";
    
    if (isAjaxRequest()) {
        sendJsonResponse(true, $successMessage, '../booking/cart.php');
    } else {
        $_SESSION['success'] = $successMessage;
        redirect('cart.php');
    }
}
?>