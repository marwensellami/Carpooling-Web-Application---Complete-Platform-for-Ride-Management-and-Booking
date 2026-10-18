<?php
/**
 * Fonctions spécifiques pour les notifications du covoiturage
 */

/**
 * Notifier le conducteur d'une nouvelle réservation
 */
function notifyDriverAboutBooking($conn, $booking_id, $passenger_id, $driver_id) {
    try {
        // Récupérer les détails de la réservation
        $stmt = $conn->prepare("
            SELECT b.seats_booked, b.total_price, b.booking_date,
                   r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
                   u.first_name as passenger_first_name, u.last_name as passenger_last_name,
                   u.phone as passenger_phone
            FROM bookings b
            JOIN rides r ON b.ride_id = r.id
            JOIN users u ON b.passenger_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            return false;
        }
        
        $title = "Nouvelle réservation !";
        $message = sprintf(
            "%s %s a réservé %d place(s) pour votre trajet %s → %s du %s. 
            Montant total : %.2f DT. Contact : %s",
            $booking['passenger_first_name'],
            $booking['passenger_last_name'],
            $booking['seats_booked'],
            $booking['departure_city'],
            $booking['arrival_city'],
            date('d/m/Y', strtotime($booking['departure_date'])),
            $booking['total_price'],
            $booking['passenger_phone']
        );
        
        // Créer la notification
        $notification_id = createNotification($conn, $driver_id, $title, $message, 'booking');
        
        // Envoyer un email détaillé
        $email_content = "
            <h2>Nouvelle Réservation sur Votre Trajet</h2>
            <p><strong>Passager :</strong> {$booking['passenger_first_name']} {$booking['passenger_last_name']}</p>
            <p><strong>Téléphone :</strong> {$booking['passenger_phone']}</p>
            <p><strong>Trajet :</strong> {$booking['departure_city']} → {$booking['arrival_city']}</p>
            <p><strong>Date :</strong> " . date('d/m/Y', strtotime($booking['departure_date'])) . " à " . substr($booking['departure_time'], 0, 5) . "</p>
            <p><strong>Places réservées :</strong> {$booking['seats_booked']}</p>
            <p><strong>Montant total :</strong> " . number_format($booking['total_price'], 2) . " DT</p>
            <p><strong>Date de la réservation :</strong> " . date('d/m/Y H:i', strtotime($booking['booking_date'])) . "</p>
            <br>
            <p>Contactez le passager pour confirmer les détails du rendez-vous.</p>
            <p><a href='" . APP_URL . "/booking/booking_details.php?id={$booking_id}'>Voir les détails de la réservation</a></p>
        ";
        
        $driver_email = getEmailFromUserId($conn, $driver_id);
        if ($driver_email) {
            sendEmailNotification($driver_email, "Nouvelle réservation - " . APP_NAME, $email_content);
        }
        
        return $notification_id;
        
    } catch (Exception $e) {
        error_log("Erreur notification réservation conducteur: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier le passager d'une modification de trajet
 */
function notifyPassengerAboutRideUpdate($conn, $ride_id, $passenger_id, $changes) {
    try {
        // Récupérer les détails du trajet
        $stmt = $conn->prepare("
            SELECT r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
                   u.first_name as driver_first_name, u.last_name as driver_last_name,
                   u.phone as driver_phone
            FROM rides r
            JOIN users u ON r.driver_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$ride_id]);
        $ride = $stmt->fetch();
        
        if (!$ride) {
            return false;
        }
        
        $title = "Modification de votre trajet réservé";
        
        // Construire un message détaillé des changements
        $change_details = "";
        foreach ($changes as $field => $values) {
            $old_value = $values['old'];
            $new_value = $values['new'];
            
            switch ($field) {
                case 'departure_date':
                    $change_details .= "• Date : " . date('d/m/Y', strtotime($old_value)) . " → " . date('d/m/Y', strtotime($new_value)) . "\n";
                    break;
                case 'departure_time':
                    $change_details .= "• Heure : " . substr($old_value, 0, 5) . " → " . substr($new_value, 0, 5) . "\n";
                    break;
                case 'departure_city':
                    $change_details .= "• Ville de départ : $old_value → $new_value\n";
                    break;
                case 'arrival_city':
                    $change_details .= "• Ville d'arrivée : $old_value → $new_value\n";
                    break;
                case 'departure_address':
                    $change_details .= "• Adresse de départ modifiée\n";
                    break;
                case 'arrival_address':
                    $change_details .= "• Adresse d'arrivée modifiée\n";
                    break;
                case 'available_seats':
                    $change_details .= "• Nombre de places : $old_value → $new_value\n";
                    break;
                case 'price_per_seat':
                    $change_details .= "• Prix par place : " . number_format($old_value, 2) . " DT → " . number_format($new_value, 2) . " DT\n";
                    break;
            }
        }
        
        $message = sprintf(
            "Le conducteur %s %s a modifié le trajet %s → %s.\n\nChangements :\n%s\n\nContactez-le si nécessaire : %s",
            $ride['driver_first_name'],
            $ride['driver_last_name'],
            $ride['departure_city'],
            $ride['arrival_city'],
            $change_details,
            $ride['driver_phone']
        );
        
        // Créer la notification
        $notification_id = createNotification($conn, $passenger_id, $title, $message, 'ride');
        
        // Envoyer un email détaillé
        $email_content = "
            <h2>Modification de Votre Trajet Réservé</h2>
            <p>Le conducteur <strong>{$ride['driver_first_name']} {$ride['driver_last_name']}</strong> a modifié les détails du trajet que vous avez réservé.</p>
            
            <h3>Détails du trajet :</h3>
            <p><strong>Itinéraire :</strong> {$ride['departure_city']} → {$ride['arrival_city']}</p>
            <p><strong>Téléphone du conducteur :</strong> {$ride['driver_phone']}</p>
            
            <h3>Modifications apportées :</h3>
            <ul>
        ";
        
        foreach ($changes as $field => $values) {
            $old_value = $values['old'];
            $new_value = $values['new'];
            
            switch ($field) {
                case 'departure_date':
                    $email_content .= "<li><strong>Date :</strong> " . date('d/m/Y', strtotime($old_value)) . " → " . date('d/m/Y', strtotime($new_value)) . "</li>";
                    break;
                case 'departure_time':
                    $email_content .= "<li><strong>Heure :</strong> " . substr($old_value, 0, 5) . " → " . substr($new_value, 0, 5) . "</li>";
                    break;
                case 'departure_city':
                    $email_content .= "<li><strong>Ville de départ :</strong> $old_value → $new_value</li>";
                    break;
                case 'arrival_city':
                    $email_content .= "<li><strong>Ville d'arrivée :</strong> $old_value → $new_value</li>";
                    break;
                case 'departure_address':
                    $email_content .= "<li><strong>Adresse de départ modifiée</strong></li>";
                    break;
                case 'arrival_address':
                    $email_content .= "<li><strong>Adresse d'arrivée modifiée</strong></li>";
                    break;
                case 'available_seats':
                    $email_content .= "<li><strong>Places disponibles :</strong> $old_value → $new_value</li>";
                    break;
                case 'price_per_seat':
                    $email_content .= "<li><strong>Prix par place :</strong> " . number_format($old_value, 2) . " DT → " . number_format($new_value, 2) . " DT</li>";
                    break;
            }
        }
        
        $email_content .= "
            </ul>
            <br>
            <p>Veuillez contacter le conducteur si ces modifications vous posent problème.</p>
            <p><a href='" . APP_URL . "/rides/ride_details.php?id={$ride_id}'>Voir les nouveaux détails du trajet</a></p>
            <p><a href='" . APP_URL . "/booking/my_bookings.php'>Voir mes réservations</a></p>
        ";
        
        $passenger_email = getEmailFromUserId($conn, $passenger_id);
        if ($passenger_email) {
            sendEmailNotification($passenger_email, "Modification de votre trajet - " . APP_NAME, $email_content);
        }
        
        return $notification_id;
        
    } catch (Exception $e) {
        error_log("Erreur notification modification trajet: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier le conducteur d'une annulation de réservation
 */
function notifyDriverAboutBookingCancellation($conn, $booking_id, $passenger_id, $driver_id) {
    try {
        // Récupérer les détails de l'annulation
        $stmt = $conn->prepare("
            SELECT b.seats_booked, b.total_price, b.cancelled_at,
                   r.departure_city, r.arrival_city, r.departure_date,
                   u.first_name as passenger_first_name, u.last_name as passenger_last_name
            FROM bookings b
            JOIN rides r ON b.ride_id = r.id
            JOIN users u ON b.passenger_id = u.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            return false;
        }
        
        $title = "Réservation annulée";
        $message = sprintf(
            "%s %s a annulé sa réservation de %d place(s) pour votre trajet %s → %s du %s. 
            Les places sont maintenant à nouveau disponibles.",
            $booking['passenger_first_name'],
            $booking['passenger_last_name'],
            $booking['seats_booked'],
            $booking['departure_city'],
            $booking['arrival_city'],
            date('d/m/Y', strtotime($booking['departure_date']))
        );
        
        // Créer la notification
        $notification_id = createNotification($conn, $driver_id, $title, $message, 'booking');
        
        // Envoyer un email
        $email_content = "
            <h2>Réservation Annulée</h2>
            <p><strong>Passager :</strong> {$booking['passenger_first_name']} {$booking['passenger_last_name']}</p>
            <p><strong>Trajet :</strong> {$booking['departure_city']} → {$booking['arrival_city']}</p>
            <p><strong>Date prévue :</strong> " . date('d/m/Y', strtotime($booking['departure_date'])) . "</p>
            <p><strong>Places annulées :</strong> {$booking['seats_booked']}</p>
            <p><strong>Montant annulé :</strong> " . number_format($booking['total_price'], 2) . " DT</p>
            <p><strong>Date d'annulation :</strong> " . date('d/m/Y H:i', strtotime($booking['cancelled_at'])) . "</p>
            <br>
            <p>Les places sont maintenant disponibles pour d'autres réservations.</p>
        ";
        
        $driver_email = getEmailFromUserId($conn, $driver_id);
        if ($driver_email) {
            sendEmailNotification($driver_email, "Réservation annulée - " . APP_NAME, $email_content);
        }
        
        return $notification_id;
        
    } catch (Exception $e) {
        error_log("Erreur notification annulation réservation: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier tous les passagers d'un trajet d'une modification
 */
function notifyAllPassengersAboutRideUpdate($conn, $ride_id, $changes) {
    try {
        // Récupérer tous les passagers avec réservations confirmées
        $stmt = $conn->prepare("
            SELECT DISTINCT b.passenger_id
            FROM bookings b
            WHERE b.ride_id = ? AND b.status = 'confirmed'
        ");
        $stmt->execute([$ride_id]);
        $passengers = $stmt->fetchAll();
        
        $notification_count = 0;
        foreach ($passengers as $passenger) {
            $notification_id = notifyPassengerAboutRideUpdate($conn, $ride_id, $passenger['passenger_id'], $changes);
            if ($notification_id) {
                $notification_count++;
            }
        }
        
        return $notification_count;
        
    } catch (Exception $e) {
        error_log("Erreur notification multiple passagers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fonction utilitaire pour récupérer l'email d'un utilisateur
 */
function getEmailFromUserId($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        return $user['email'] ?? null;
    } catch (Exception $e) {
        error_log("Erreur récupération email: " . $e->getMessage());
        return null;
    }
}


/**
 * Notifier le passager de l'annulation d'un trajet par le conducteur
 */
function notifyPassengerAboutRideCancellation($conn, $ride_id, $passenger_id, $reason = '') {
    try {
        // Récupérer les détails du trajet annulé
        $stmt = $conn->prepare("
            SELECT r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
                   r.price_per_seat, r.available_seats,
                   u.first_name as driver_first_name, u.last_name as driver_last_name,
                   u.phone as driver_phone, u.email as driver_email,
                   b.seats_booked, b.total_price
            FROM rides r
            JOIN users u ON r.driver_id = u.id
            JOIN bookings b ON r.id = b.ride_id AND b.passenger_id = ?
            WHERE r.id = ? AND b.status = 'confirmed'
        ");
        $stmt->execute([$passenger_id, $ride_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return false;
        }
        
        $title = "Trajet annulé par le conducteur";
        
        $message = sprintf(
            "Le conducteur %s %s a malheureusement dû annuler le trajet %s → %s prévu le %s à %s.\n\n",
            $data['driver_first_name'],
            $data['driver_last_name'],
            $data['departure_city'],
            $data['arrival_city'],
            date('d/m/Y', strtotime($data['departure_date'])),
            substr($data['departure_time'], 0, 5)
        );
        
        if (!empty($reason)) {
            $message .= "Raison : $reason\n\n";
        }
        
        $message .= sprintf(
            "Détails de votre réservation :\n" .
            "- Places réservées : %d\n" .
            "- Prix total : %.2f DT\n" .
            "- Contact conducteur : %s\n\n" .
            "Nous vous invitons à rechercher un autre trajet pour votre destination.",
            $data['seats_booked'],
            $data['total_price'],
            $data['driver_phone']
        );
        
        // Créer la notification
        $notification_id = createNotification($conn, $passenger_id, $title, $message, 'ride');
        
        // Envoyer un email détaillé
        $email_content = "
            <h2>Trajet Annulé</h2>
            <p>Bonjour,</p>
            <p>Le conducteur <strong>{$data['driver_first_name']} {$data['driver_last_name']}</strong> a dû annuler le trajet que vous aviez réservé.</p>
            
            <h3>Détails du trajet annulé :</h3>
            <p><strong>Itinéraire :</strong> {$data['departure_city']} → {$data['arrival_city']}</p>
            <p><strong>Date prévue :</strong> " . date('d/m/Y', strtotime($data['departure_date'])) . " à " . substr($data['departure_time'], 0, 5) . "</p>
            <p><strong>Places réservées :</strong> {$data['seats_booked']}</p>
            <p><strong>Prix total :</strong> " . number_format($data['total_price'], 2) . " DT</p>
            <p><strong>Contact conducteur :</strong> {$data['driver_phone']} | {$data['driver_email']}</p>
        ";
        
        if (!empty($reason)) {
            $email_content .= "<p><strong>Raison de l'annulation :</strong> $reason</p>";
        }
        
        $email_content .= "
            <br>
            <h3>Que faire maintenant ?</h3>
            <p>1. Recherchez un autre trajet pour votre destination</p>
            <p>2. Contactez le conducteur pour plus d'informations</p>
            <p>3. Consultez vos autres réservations</p>
            <br>
            <p><a href='" . APP_URL . "/rides/browse_rides.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                Rechercher un nouveau trajet
            </a></p>
            <p><a href='" . APP_URL . "/booking/my_bookings.php'>Voir mes réservations</a></p>
            <br>
            <p>Nous nous excusons pour ce désagrément.</p>
            <p><strong>L'équipe " . APP_NAME . "</strong></p>
        ";
        
        $passenger_email = getEmailFromUserId($conn, $passenger_id);
        if ($passenger_email) {
            sendEmailNotification($passenger_email, "Trajet annulé - " . APP_NAME, $email_content);
        }
        
        return $notification_id;
        
    } catch (Exception $e) {
        error_log("Erreur notification annulation trajet passager: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier tous les passagers d'une annulation de trajet
 */
function notifyAllPassengersAboutRideCancellation($conn, $ride_id, $reason = '') {
    try {
        // Récupérer tous les passagers avec réservations confirmées
        $stmt = $conn->prepare("
            SELECT DISTINCT b.passenger_id
            FROM bookings b
            WHERE b.ride_id = ? AND b.status = 'confirmed'
        ");
        $stmt->execute([$ride_id]);
        $passengers = $stmt->fetchAll();
        
        $notification_count = 0;
        foreach ($passengers as $passenger) {
            $notification_id = notifyPassengerAboutRideCancellation($conn, $ride_id, $passenger['passenger_id'], $reason);
            if ($notification_id) {
                $notification_count++;
            }
        }
        
        return $notification_count;
        
    } catch (Exception $e) {
        error_log("Erreur notification multiple annulations trajet: " . $e->getMessage());
        return 0;
    }
}

/**
 * Désactiver un trajet et notifier les passagers
 */
function deactivateRideWithNotification($conn, $ride_id, $driver_id, $reason = '') {
    try {
        $conn->beginTransaction();
        
        // Marquer le trajet comme inactif
        $stmt = $conn->prepare("UPDATE rides SET is_active = 0 WHERE id = ?");
        $stmt->execute([$ride_id]);
        
        // Notifier tous les passagers
        $notified_count = notifyAllPassengersAboutRideCancellation($conn, $ride_id, $reason);
        
        // Annuler toutes les réservations en attente
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', cancelled_at = NOW() 
            WHERE ride_id = ? AND status = 'pending'
        ");
        $stmt->execute([$ride_id]);
        
        $conn->commit();
        
        logAction($conn, $driver_id, 'ride_deactivated', "Trajet #$ride_id désactivé, $notified_count passagers notifiés");
        
        return ['success' => true, 'notified_count' => $notified_count];
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Erreur désactivation trajet: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Comparer les anciennes et nouvelles valeurs pour détecter les changements
 */
function detectChanges($old_data, $new_data, $fields_to_check) {
    $changes = [];
    
    foreach ($fields_to_check as $field) {
        if (isset($old_data[$field], $new_data[$field]) && $old_data[$field] != $new_data[$field]) {
            $changes[$field] = [
                'old' => $old_data[$field],
                'new' => $new_data[$field]
            ];
        }
    }
    
    return $changes;
}
?>