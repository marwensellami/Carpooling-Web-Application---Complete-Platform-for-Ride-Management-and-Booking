<?php
/**
 * Système de notifications
 * Gère les notifications en temps réel et les emails
 */

session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion pour les actions nécessitant une authentification
if (!isLoggedIn() && !in_array($_GET['action'] ?? '', ['email_test', 'system_broadcast'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            // Marquer une notification comme lue
            $notification_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            
            if ($notification_id) {
                $success = markNotificationAsRead($conn, $notification_id, $_SESSION['user_id']);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors du marquage']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de notification manquant']);
            }
            break;
            
        case 'mark_all_read':
            // Marquer toutes les notifications comme lues
            $stmt = $conn->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $success = $stmt->execute([$_SESSION['user_id']]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du marquage']);
            }
            break;
            
        case 'get_unread_count':
            // Récupérer le nombre de notifications non lues
            $notifications = getUnreadNotifications($conn, $_SESSION['user_id'], 0, true);
            echo json_encode(['success' => true, 'count' => count($notifications)]);
            break;
            
        case 'send_ride_notification':
            // Envoyer une notification pour un nouveau trajet
            $ride_id = (int)($_POST['ride_id'] ?? 0);
            $title = $_POST['title'] ?? 'Nouveau trajet disponible';
            $message = $_POST['message'] ?? '';
            
            if ($ride_id && $message) {
                // Récupérer les utilisateurs intéressés par cet itinéraire
                $stmt = $conn->prepare("
                    SELECT DISTINCT u.id, u.email, u.first_name
                    FROM users u
                    WHERE u.is_active = 1 
                    AND u.id != ?
                    AND EXISTS (
                        SELECT 1 FROM user_preferences up 
                        WHERE up.user_id = u.id 
                        AND (up.departure_city = ? OR up.arrival_city = ?)
                    )
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $_POST['departure_city'] ?? '',
                    $_POST['arrival_city'] ?? ''
                ]);
                
                $interested_users = $stmt->fetchAll();
                
                $sent_count = 0;
                foreach ($interested_users as $user) {
                    $notification_sent = createNotification(
                        $conn,
                        $user['id'],
                        $title,
                        $message,
                        'ride'
                    );
                    
                    if ($notification_sent) {
                        $sent_count++;
                        
                        // Envoyer un email (optionnel)
                        if (isset($_POST['send_email']) && $_POST['send_email']) {
                            sendEmailNotification(
                                $user['email'],
                                $title . ' - ' . APP_NAME,
                                "
                                <h2>$title</h2>
                                <p>Bonjour {$user['first_name']},</p>
                                <p>$message</p>
                                <p><strong>Itinéraire:</strong> {$_POST['departure_city']} → {$_POST['arrival_city']}</p>
                                <p><strong>Date:</strong> {$_POST['departure_date']} à {$_POST['departure_time']}</p>
                                <p><strong>Prix:</strong> {$_POST['price']} DT par place</p>
                                <br>
                                <p><a href='" . APP_URL . "/rides/ride_details.php?id=$ride_id'>Voir les détails du trajet</a></p>
                                <p>Merci d'utiliser " . APP_NAME . " !</p>
                                "
                            );
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Notification envoyée à $sent_count utilisateur(s)",
                    'count' => $sent_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            }
            break;
            
        case 'send_booking_notification':
            // Envoyer une notification pour une réservation
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $target_user_id = (int)($_POST['target_user_id'] ?? 0);
            $title = $_POST['title'] ?? 'Nouvelle réservation';
            $message = $_POST['message'] ?? '';
            
            if ($booking_id && $target_user_id && $message) {
                $notification_sent = createNotification(
                    $conn,
                    $target_user_id,
                    $title,
                    $message,
                    'booking'
                );
                
                if ($notification_sent) {
                    echo json_encode(['success' => true, 'message' => 'Notification envoyée']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            }
            break;
            
        case 'system_broadcast':
            // Diffusion système (admin seulement)
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                break;
            }
            
            $title = $_POST['title'] ?? 'Annonce importante';
            $message = $_POST['message'] ?? '';
            $user_group = $_POST['user_group'] ?? 'all'; // all, drivers, passengers
            
            if ($title && $message) {
                // Construire la requête selon le groupe cible
                $sql = "SELECT id, email, first_name FROM users WHERE is_active = 1";
                $params = [];
                
                if ($user_group === 'drivers') {
                    $sql .= " AND EXISTS (SELECT 1 FROM rides WHERE driver_id = users.id)";
                } elseif ($user_group === 'passengers') {
                    $sql .= " AND EXISTS (SELECT 1 FROM bookings WHERE passenger_id = users.id)";
                }
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll();
                
                $sent_count = 0;
                foreach ($users as $user) {
                    $notification_sent = createNotification(
                        $conn,
                        $user['id'],
                        $title,
                        $message,
                        'system'
                    );
                    
                    if ($notification_sent) {
                        $sent_count++;
                        
                        // Envoyer un email
                        if (isset($_POST['send_email']) && $_POST['send_email']) {
                            sendEmailNotification(
                                $user['email'],
                                $title . ' - ' . APP_NAME,
                                "
                                <h2>$title</h2>
                                <p>Bonjour {$user['first_name']},</p>
                                <p>$message</p>
                                <br>
                                <p><strong>" . APP_NAME . "</strong></p>
                                <p><small>Ceci est un message automatique, merci de ne pas y répondre.</small></p>
                                "
                            );
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Diffusion envoyée à $sent_count utilisateur(s)",
                    'count' => $sent_count
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Titre et message requis']);
            }
            break;
            
        case 'email_test':
            // Test d'envoi d'email (admin seulement)
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                break;
            }
            
            $test_email = $_POST['email'] ?? $_SESSION['user_email'];
            $subject = 'Test d\'email - ' . APP_NAME;
            $message = "
                <h2>Test d'email réussi !</h2>
                <p>Ceci est un email de test envoyé depuis " . APP_NAME . ".</p>
                <p>Si vous recevez cet email, cela signifie que le système d'envoi d'emails fonctionne correctement.</p>
                <br>
                <p><strong>Date d'envoi:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Serveur:</strong> " . $_SERVER['SERVER_NAME'] . "</p>
            ";
            
            $success = sendEmailNotification($test_email, $subject, $message);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Email de test envoyé avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email de test']);
            }
            break;
            
        case 'cleanup_old_notifications':
            // Nettoyer les anciennes notifications (admin seulement)
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
                break;
            }
            
            $days_old = (int)($_POST['days'] ?? 30);
            $stmt = $conn->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND is_read = 1
            ");
            
            $stmt->execute([$days_old]);
            $deleted_count = $stmt->rowCount();
            
            echo json_encode([
                'success' => true, 
                'message' => "$deleted_count notifications supprimées",
                'count' => $deleted_count
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erreur notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>

<?php
/**
 * Fonction pour envoyer une notification push (simulée)
 * En production, utiliser un service comme Firebase Cloud Messaging
 */
function sendPushNotification($user_id, $title, $message, $data = []) {
    // Simulation d'envoi de notification push
    // En production, intégrer avec FCM, OneSignal, etc.
    
    error_log("PUSH NOTIFICATION - User: $user_id, Title: $title, Message: $message");
    
    // Retourner vrai pour simuler le succès
    return true;
}

/**
 * Fonction pour planifier une notification différée
 */
function scheduleNotification($conn, $user_id, $title, $message, $type, $schedule_time) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO scheduled_notifications (user_id, title, message, type, scheduled_for, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$user_id, $title, $message, $type, $schedule_time]);
    } catch (Exception $e) {
        error_log("Erreur planification notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonction pour traiter les notifications planifiées
 */
function processScheduledNotifications($conn) {
    try {
        // Récupérer les notifications planifiées dont l'heure est passée
        $stmt = $conn->prepare("
            SELECT * FROM scheduled_notifications 
            WHERE scheduled_for <= NOW() AND sent_at IS NULL
        ");
        $stmt->execute();
        $scheduled_notifications = $stmt->fetchAll();
        
        $processed_count = 0;
        foreach ($scheduled_notifications as $scheduled) {
            // Créer la notification réelle
            $notification_sent = createNotification(
                $conn,
                $scheduled['user_id'],
                $scheduled['title'],
                $scheduled['message'],
                $scheduled['type']
            );
            
            if ($notification_sent) {
                // Marquer comme envoyée
                $stmt = $conn->prepare("
                    UPDATE scheduled_notifications 
                    SET sent_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$scheduled['id']]);
                $processed_count++;
            }
        }
        
        return $processed_count;
    } catch (Exception $e) {
        error_log("Erreur traitement notifications planifiées: " . $e->getMessage());
        return 0;
    }
}
?>