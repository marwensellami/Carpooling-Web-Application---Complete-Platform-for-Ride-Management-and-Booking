<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('../auth/login.php');
}

// Vérifier l'ID de la réservation
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Réservation non spécifiée";
    redirect('my_bookings.php');
}

$booking_id = (int)$_GET['id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les informations de la réservation
    $stmt = $conn->prepare("
        SELECT b.*, r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.driver_id, r.price_per_seat, r.vehicle_id,
               u.first_name as driver_first_name, u.last_name as driver_last_name, 
               u.email as driver_email, u.phone as driver_phone,
               p.first_name as passenger_first_name, p.last_name as passenger_last_name,
               p.email as passenger_email, p.phone as passenger_phone,
               v.brand, v.model, v.color
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        JOIN users p ON b.passenger_id = p.id
        LEFT JOIN vehicles v ON r.vehicle_id = v.id
        WHERE b.id = ? AND (b.passenger_id = ? OR r.driver_id = ?)
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error'] = "Réservation non trouvée ou vous n'êtes pas autorisé à l'annuler";
        redirect('my_bookings.php');
    }
    
    $is_passenger = ($booking['passenger_id'] == $_SESSION['user_id']);
    $is_driver = ($booking['driver_id'] == $_SESSION['user_id']);
    
    // Vérifier si l'annulation est possible
    $departure_datetime = strtotime($booking['departure_date'] . ' ' . $booking['departure_time']);
    $current_datetime = time();
    $time_diff = $departure_datetime - $current_datetime;
    
    // Politique d'annulation flexible
    $cancellation_allowed = true;
    $cancellation_penalty = 0;
    $refund_amount = $booking['total_price'];
    
    if ($time_diff < 24 * 3600) {
        $cancellation_allowed = false;
        $hours_before = floor($time_diff / 3600);
        $_SESSION['error'] = "Impossible d'annuler une réservation moins de 24 heures avant le départ (départ dans $hours_before heures)";
        redirect('booking_details.php?id=' . $booking_id);
    } elseif ($time_diff < 48 * 3600) {
        // Annulation entre 24h et 48h avant départ : 50% de frais
        $cancellation_penalty = $booking['total_price'] * 0.5;
        $refund_amount = $booking['total_price'] - $cancellation_penalty;
    }
    
    if ($booking['status'] !== 'confirmed') {
        $_SESSION['error'] = "Cette réservation ne peut pas être annulée (statut: {$booking['status']})";
        redirect('booking_details.php?id=' . $booking_id);
    }
    
    // Procéder à l'annulation
    $conn->beginTransaction();
    
    try {
        // Marquer la réservation comme annulée
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                cancelled_by = ?,
                cancellation_reason = 'user_cancelled',
                refund_amount = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $refund_amount, $booking_id]);
        
        // AJOUT DES NOTIFICATIONS AMÉLIORÉES
        require_once '../includes/notification_functions.php';
        
        if ($is_passenger) {
            // Le passager annule - NOTIFICATION AMÉLIORÉE pour le conducteur
            notifyDriverAboutBookingCancellation($conn, $booking_id, $_SESSION['user_id'], $booking['driver_id']);
            
            // Notification standard pour le conducteur
            createNotification(
                $conn,
                $booking['driver_id'],
                "🚫 Réservation annulée",
                "{$_SESSION['first_name']} {$SESSION['last_name']} a annulé sa réservation #{$booking_id}",
                'booking_cancelled',
                $booking_id
            );
            
            // Notification pour le passager
            createNotification(
                $conn,
                $_SESSION['user_id'],
                "✅ Annulation confirmée",
                "Vous avez annulé votre réservation #{$booking_id} pour {$booking['departure_city']} → {$booking['arrival_city']}",
                'booking_cancelled',
                $booking_id
            );
            
            // Email au conducteur
            sendEmailNotification(
                $booking['driver_email'],
                "🚫 Réservation annulée - " . APP_NAME,
                "
                <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                    <div style='background:linear-gradient(135deg,#f56565 0%,#e53e3e 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                        <h2 style='margin:0;'>Réservation annulée</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                        <p style='font-size:16px;'>Bonjour <strong>{$booking['driver_first_name']}</strong>,</p>
                        
                        <div style='background:white; border-left:4px solid #f56565; padding:15px; margin:20px 0; border-radius:5px;'>
                            <p style='margin:0 0 10px 0;'><strong>🚫 Un passager a annulé sa réservation</strong></p>
                            <p style='margin:0; color:#666;'>
                                Référence : <strong>#BOOK{$booking_id}</strong>
                            </p>
                        </div>
                        
                        <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Détails de l'annulation :</h3>
                        
                        <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                            <tr style='background:#f1f3f4;'>
                                <th style='padding:12px; text-align:left; width:40%;'>Information</th>
                                <th style='padding:12px; text-align:left; width:60%;'>Détail</th>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Passager</strong></td>
                                <td style='padding:12px;'>{$_SESSION['first_name']} {$_SESSION['last_name']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Trajet</strong></td>
                                <td style='padding:12px;'>{$booking['departure_city']} → {$booking['arrival_city']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date et heure</strong></td>
                                <td style='padding:12px;'>" . formatDate($booking['departure_date']) . " à " . substr($booking['departure_time'], 0, 5) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Places annulées</strong></td>
                                <td style='padding:12px;'>{$booking['seats_booked']} place(s)</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Montant</strong></td>
                                <td style='padding:12px;'>" . number_format($booking['total_price'], 2) . " DT</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date d'annulation</strong></td>
                                <td style='padding:12px;'>" . date('d/m/Y H:i') . "</td>
                            </tr>
                        </table>
                        
                        <div style='background:#e8f5e9; border:1px solid #c8e6c9; padding:15px; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#2e7d32;'>📋 Bonne nouvelle :</h4>
                            <p style='margin:10px 0 0 0;'>
                                <strong>{$booking['seats_booked']} place(s)</strong> sont maintenant disponibles pour votre trajet.
                                Vous pouvez les proposer à d'autres passagers.
                            </p>
                        </div>
                        
                        <div style='text-align:center; margin-top:30px;'>
                            <a href='" . APP_URL . "/rides/ride_details.php?id={$booking['ride_id']}' 
                               style='background:#667eea; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                               👁️ Voir le trajet
                            </a>
                        </div>
                        
                        <p style='color:#666; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;'>
                            Cordialement,<br>
                            L'équipe <strong>" . APP_NAME . "</strong>
                        </p>
                    </div>
                </div>
                "
            );
            
            // Email au passager
            $refund_message = ($cancellation_penalty > 0) 
                ? "<p><strong>Montant remboursé :</strong> " . number_format($refund_amount, 2) . " DT (frais d'annulation : " . number_format($cancellation_penalty, 2) . " DT)</p>"
                : "<p><strong>Montant remboursé :</strong> " . number_format($refund_amount, 2) . " DT</p>";
            
            sendEmailNotification(
                $_SESSION['email'] ?? $booking['passenger_email'],
                "✅ Annulation confirmée - " . APP_NAME,
                "
                <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                    <div style='background:linear-gradient(135deg,#38a169 0%,#2f855a 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                        <h2 style='margin:0;'>Annulation confirmée</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                        <p style='font-size:16px;'>Bonjour <strong>{$_SESSION['first_name']}</strong>,</p>
                        
                        <div style='background:white; border-left:4px solid #38a169; padding:15px; margin:20px 0; border-radius:5px;'>
                            <p style='margin:0 0 10px 0;'><strong>✅ Votre annulation est confirmée</strong></p>
                            <p style='margin:0; color:#666;'>
                                Référence : <strong>#BOOK{$booking_id}</strong>
                            </p>
                        </div>
                        
                        <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Résumé de l'annulation :</h3>
                        
                        <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                            <tr style='background:#f1f3f4;'>
                                <th style='padding:12px; text-align:left; width:40%;'>Information</th>
                                <th style='padding:12px; text-align:left; width:60%;'>Détail</th>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Conducteur</strong></td>
                                <td style='padding:12px;'>{$booking['driver_first_name']} {$booking['driver_last_name']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Trajet</strong></td>
                                <td style='padding:12px;'>{$booking['departure_city']} → {$booking['arrival_city']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date et heure</strong></td>
                                <td style='padding:12px;'>" . formatDate($booking['departure_date']) . " à " . substr($booking['departure_time'], 0, 5) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Places annulées</strong></td>
                                <td style='padding:12px;'>{$booking['seats_booked']} place(s)</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Prix initial</strong></td>
                                <td style='padding:12px;'>" . number_format($booking['total_price'], 2) . " DT</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date d'annulation</strong></td>
                                <td style='padding:12px;'>" . date('d/m/Y H:i') . "</td>
                            </tr>
                        </table>
                        
                        {$refund_message}
                        
                        <div style='background:#e8f5e9; border:1px solid #c8e6c9; padding:15px; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#2e7d32;'>📋 Prochaines étapes :</h4>
                            <ol style='margin:10px 0 0 0; padding-left:20px;'>
                                <li>Le remboursement sera traité dans les 5-7 jours ouvrables</li>
                                <li>Le conducteur a été notifié de l'annulation</li>
                                <li>Vous pouvez réserver un autre trajet dès maintenant</li>
                            </ol>
                        </div>
                        
                        <div style='text-align:center; margin-top:30px;'>
                            <a href='" . APP_URL . "/rides/browse_rides.php' 
                               style='background:#667eea; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block; margin-right:10px;'>
                               🔍 Trouver un nouveau trajet
                            </a>
                            <a href='" . APP_URL . "/booking/my_bookings.php' 
                               style='background:#718096; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                               📋 Mes réservations
                            </a>
                        </div>
                        
                        <p style='color:#666; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;'>
                            Cordialement,<br>
                            L'équipe <strong>" . APP_NAME . "</strong>
                        </p>
                    </div>
                </div>
                "
            );
            
        } elseif ($is_driver) {
            // Le conducteur annule - NOTIFICATION AMÉLIORÉE pour le passager
            notifyPassengerAboutRideCancellation($conn, $booking_id, $_SESSION['user_id'], $booking['passenger_id']);
            
            // Notification standard pour le passager
            createNotification(
                $conn,
                $booking['passenger_id'],
                "🚫 Trajet annulé par le conducteur",
                "Le conducteur a annulé le trajet {$booking['departure_city']} → {$booking['arrival_city']}",
                'ride_cancelled',
                $booking_id
            );
            
            // Notification pour le conducteur
            createNotification(
                $conn,
                $_SESSION['user_id'],
                "✅ Annulation confirmée",
                "Vous avez annulé la réservation #{$booking_id} de {$booking['passenger_first_name']}",
                'booking_cancelled',
                $booking_id
            );
            
            // Email au passager
            sendEmailNotification(
                $booking['passenger_email'],
                "🚫 Trajet annulé - " . APP_NAME,
                "
                <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                    <div style='background:linear-gradient(135deg,#f56565 0%,#e53e3e 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                        <h2 style='margin:0;'>Trajet annulé</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                        <p style='font-size:16px;'>Bonjour <strong>{$booking['passenger_first_name']}</strong>,</p>
                        
                        <div style='background:white; border-left:4px solid #f56565; padding:15px; margin:20px 0; border-radius:5px;'>
                            <p style='margin:0 0 10px 0;'><strong>🚫 Votre trajet a été annulé</strong></p>
                            <p style='margin:0; color:#666;'>
                                Le conducteur a dû annuler son trajet.
                            </p>
                        </div>
                        
                        <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Détails de l'annulation :</h3>
                        
                        <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                            <tr style='background:#f1f3f4;'>
                                <th style='padding:12px; text-align:left; width:40%;'>Information</th>
                                <th style='padding:12px; text-align:left; width:60%;'>Détail</th>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Conducteur</strong></td>
                                <td style='padding:12px;'>{$booking['driver_first_name']} {$booking['driver_last_name']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Trajet annulé</strong></td>
                                <td style='padding:12px;'>{$booking['departure_city']} → {$booking['arrival_city']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date prévue</strong></td>
                                <td style='padding:12px;'>" . formatDate($booking['departure_date']) . " à " . substr($booking['departure_time'], 0, 5) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Places réservées</strong></td>
                                <td style='padding:12px;'>{$booking['seats_booked']} place(s)</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Montant à rembourser</strong></td>
                                <td style='padding:12px;'>" . number_format($booking['total_price'], 2) . " DT</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date d'annulation</strong></td>
                                <td style='padding:12px;'>" . date('d/m/Y H:i') . "</td>
                            </tr>
                        </table>
                        
                        <div style='background:#e8f5e9; border:1px solid #c8e6c9; padding:15px; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#2e7d32;'>📋 Compensation :</h4>
                            <p style='margin:10px 0 0 0;'>
                                <strong>Remboursement complet :</strong> Vous recevrez un remboursement de 
                                <strong>" . number_format($booking['total_price'], 2) . " DT</strong> dans les 24-48 heures.
                            </p>
                        </div>
                        
                        <div style='background:#fff3cd; border:1px solid #ffeaa7; padding:15px; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#856404;'>💡 Suggestions alternatives :</h4>
                            <p style='margin:10px 0 0 0;'>
                                D'autres conducteurs proposent peut-être des trajets similaires. 
                                Nous vous recommandons de rechercher une alternative rapidement.
                            </p>
                        </div>
                        
                        <div style='text-align:center; margin-top:30px;'>
                            <a href='" . APP_URL . "/rides/browse_rides.php?departure={$booking['departure_city']}&arrival={$booking['arrival_city']}&date={$booking['departure_date']}' 
                               style='background:#667eea; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block; margin-right:10px;'>
                               🔍 Trouver un autre trajet
                            </a>
                            <a href='" . APP_URL . "/booking/my_bookings.php' 
                               style='background:#718096; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                               📋 Mes réservations
                            </a>
                        </div>
                        
                        <p style='color:#666; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;'>
                            <strong>Note :</strong> En cas de problème avec le remboursement, contactez notre support.
                            <br><br>
                            Cordialement,<br>
                            L'équipe <strong>" . APP_NAME . "</strong>
                        </p>
                    </div>
                </div>
                "
            );
            
            // Email au conducteur
            sendEmailNotification(
                $_SESSION['email'] ?? $booking['driver_email'],
                "✅ Annulation confirmée - " . APP_NAME,
                "
                <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                    <div style='background:linear-gradient(135deg,#718096 0%,#4a5568 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                        <h2 style='margin:0;'>Annulation confirmée</h2>
                    </div>
                    <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                        <p style='font-size:16px;'>Bonjour <strong>{$_SESSION['first_name']}</strong>,</p>
                        
                        <div style='background:white; border-left:4px solid #718096; padding:15px; margin:20px 0; border-radius:5px;'>
                            <p style='margin:0 0 10px 0;'><strong>✅ Vous avez annulé un trajet</strong></p>
                            <p style='margin:0; color:#666;'>
                                Le passager a été notifié et sera intégralement remboursé.
                            </p>
                        </div>
                        
                        <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Détails de l'annulation :</h3>
                        
                        <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                            <tr style='background:#f1f3f4;'>
                                <th style='padding:12px; text-align:left; width:40%;'>Information</th>
                                <th style='padding:12px; text-align:left; width:60%;'>Détail</th>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Passager</strong></td>
                                <td style='padding:12px;'>{$booking['passenger_first_name']} {$booking['passenger_last_name']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Trajet annulé</strong></td>
                                <td style='padding:12px;'>{$booking['departure_city']} → {$booking['arrival_city']}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Date prévue</strong></td>
                                <td style='padding:12px;'>" . formatDate($booking['departure_date']) . " à " . substr($booking['departure_time'], 0, 5) . "</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Places annulées</strong></td>
                                <td style='padding:12px;'>{$booking['seats_booked']} place(s)</td>
                            </tr>
                            <tr style='border-bottom:1px solid #eee;'>
                                <td style='padding:12px;'><strong>Montant remboursé</strong></td>
                                <td style='padding:12px;'>" . number_format($booking['total_price'], 2) . " DT</td>
                            </tr>
                        </table>
                        
                        <div style='background:#fed7d7; border:1px solid #fc8181; padding:15px; border-radius:5px; margin:20px 0;'>
                            <h4 style='margin-top:0; color:#c53030;'>⚠️ Impact sur votre fiabilité :</h4>
                            <p style='margin:10px 0 0 0;'>
                                Les annulations de dernière minute peuvent affecter votre note de fiabilité.
                                Veuillez éviter d'annuler à moins de 48h du départ.
                            </p>
                        </div>
                        
                        <div style='text-align:center; margin-top:30px;'>
                            <a href='" . APP_URL . "/rides/my_rides.php' 
                               style='background:#667eea; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block; margin-right:10px;'>
                               🚗 Mes trajets
                            </a>
                            <a href='" . APP_URL . "/users/statistics.php' 
                               style='background:#718096; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                               📊 Mes statistiques
                            </a>
                        </div>
                        
                        <p style='color:#666; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;'>
                            Cordialement,<br>
                            L'équipe <strong>" . APP_NAME . "</strong>
                        </p>
                    </div>
                </div>
                "
            );
        }
        // FIN DES NOTIFICATIONS AMÉLIORÉES
        
        $conn->commit();
        
        // Journaliser l'action
        logAction($conn, $_SESSION['user_id'], 'booking_cancelled', 
            "Réservation #$booking_id annulée " . 
            ($is_passenger ? "par le passager" : "par le conducteur") . 
            ($cancellation_penalty > 0 ? " - Frais: " . number_format($cancellation_penalty, 2) . " DT" : "")
        );
        
        // Message de succès personnalisé
        if ($is_passenger) {
            $_SESSION['success'] = "✅ Réservation annulée avec succès !";
            if ($cancellation_penalty > 0) {
                $_SESSION['success'] .= "<br>Remboursement: " . number_format($refund_amount, 2) . " DT (frais d'annulation: " . number_format($cancellation_penalty, 2) . " DT)";
            } else {
                $_SESSION['success'] .= "<br>Remboursement complet de " . number_format($refund_amount, 2) . " DT en cours.";
            }
        } else {
            $_SESSION['success'] = "✅ Annulation confirmée ! Le passager a été notifié et sera remboursé.";
            $_SESSION['success'] .= "<br><small>Les annulations fréquentes peuvent affecter votre note de fiabilité.</small>";
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Erreur transaction annulation: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de l'annulation : " . $e->getMessage();
        redirect('booking_details.php?id=' . $booking_id);
    }
    
} catch (Exception $e) {
    error_log("Erreur annulation réservation: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de l'annulation: " . $e->getMessage();
}

redirect('my_bookings.php');
?>