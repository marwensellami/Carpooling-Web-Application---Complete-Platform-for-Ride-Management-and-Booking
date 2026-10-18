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

// Vérifier l'ID du trajet
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Trajet non spécifié";
    redirect('my_rides.php');
}
// Si le trajet a des réservations confirmées, rediriger vers la désactivation
if ($ride['confirmed_bookings'] > 0) {
    $_SESSION['info'] = "Ce trajet a des réservations confirmées. Vous devez d'abord le désactiver.";
    redirect("deactivate_ride.php?id=$ride_id");
}
$ride_id = (int)$_GET['id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les informations du trajet
    $stmt = $conn->prepare("
        SELECT r.*, 
               COUNT(b.id) as confirmed_bookings,
               u.first_name, u.last_name, u.email
        FROM rides r
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.id = ? AND r.driver_id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$ride_id, $_SESSION['user_id']]);
    $ride = $stmt->fetch();
    
    if (!$ride) {
        $_SESSION['error'] = "Trajet non trouvé ou vous n'êtes pas autorisé à le supprimer";
        redirect('my_rides.php');
    }
    
    // Vérifier si le trajet peut être supprimé (au moins 24h avant le départ)
    $departure_datetime = strtotime($ride['departure_date'] . ' ' . $ride['departure_time']);
    $current_datetime = time();
    $time_diff = $departure_datetime - $current_datetime;
    
    if ($time_diff < 24 * 3600) {
        $_SESSION['error'] = "Impossible de supprimer un trajet moins de 24 heures avant le départ";
        redirect('my_rides.php');
    }
    
    // Vérifier s'il y a des réservations confirmées
    if ($ride['confirmed_bookings'] > 0) {
        $_SESSION['error'] = "Impossible de supprimer un trajet avec des réservations confirmées";
        redirect('my_rides.php');
    }
    
    // Confirmation de suppression (vérifier le paramètre de confirmation)
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
        // Afficher la page de confirmation
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmer la Suppression - <?php echo APP_NAME; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                .confirmation-header {
                    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                    color: white;
                    padding: 2rem 0;
                    margin-bottom: 2rem;
                }
                .confirmation-card {
                    border-radius: 15px;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    border: none;
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <?php include '../includes/header.php'; ?>

            <!-- Confirmation Header -->
            <div class="confirmation-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h2 mb-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>Confirmer la Suppression
                            </h1>
                            <p class="lead mb-0">
                                <?php echo htmlspecialchars($ride['departure_city']); ?> 
                                <i class="fas fa-arrow-right mx-2"></i>
                                <?php echo htmlspecialchars($ride['arrival_city']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="my_rides.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left me-1"></i>Retour
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container mb-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card confirmation-card">
                            <div class="card-body p-5 text-center">
                                <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                                <h3 class="mb-3">Êtes-vous sûr de vouloir supprimer ce trajet ?</h3>
                                
                                <div class="alert alert-warning mb-4">
                                    <h6><i class="fas fa-info-circle me-2"></i>Détails du trajet à supprimer :</h6>
                                    <p class="mb-1"><strong>Itinéraire :</strong> <?php echo htmlspecialchars($ride['departure_city']); ?> → <?php echo htmlspecialchars($ride['arrival_city']); ?></p>
                                    <p class="mb-1"><strong>Date :</strong> <?php echo formatDate($ride['departure_date']); ?> à <?php echo substr($ride['departure_time'], 0, 5); ?></p>
                                    <p class="mb-1"><strong>Places :</strong> <?php echo $ride['available_seats']; ?> place(s) disponible(s)</p>
                                    <p class="mb-0"><strong>Prix :</strong> <?php echo formatCurrency($ride['price_per_seat']); ?> par place</p>
                                </div>

                                <div class="alert alert-danger">
                                    <h6><i class="fas fa-exclamation-circle me-2"></i>Cette action est irréversible !</h6>
                                    <p class="mb-0">Une fois supprimé, ce trajet ne pourra pas être récupéré.</p>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                    <a href="my_rides.php" class="btn btn-outline-secondary btn-lg me-md-3">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </a>
                                    <a href="delete_ride.php?id=<?php echo $ride_id; ?>&confirm=yes" class="btn btn-danger btn-lg">
                                        <i class="fas fa-trash me-2"></i>Oui, supprimer le trajet
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit();
    }
    
    // Procéder à la suppression
    $conn->beginTransaction();
    
    try {
        // Notifier les passagers avec des réservations en attente
        $stmt = $conn->prepare("
            SELECT u.id, u.first_name, u.email, b.id as booking_id
            FROM bookings b
            JOIN users u ON b.passenger_id = u.id
            WHERE b.ride_id = ? AND b.status = 'pending'
        ");
        $stmt->execute([$ride_id]);
        $pending_passengers = $stmt->fetchAll();
        
        // NOTIFICATION AMÉLIORÉE : Notifier tous les passagers avec des détails
require_once '../includes/notification_functions.php';

foreach ($pending_passengers as $passenger) {
    // Annuler la réservation en attente
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'cancelled', cancelled_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$passenger['booking_id']]);
    
    // Envoyer une notification détaillée
    notifyPassengerAboutRideCancellation(
        $conn, 
        $ride_id, 
        $passenger['id'], 
        "Le conducteur a dû annuler ce trajet"
    );
}
        
        // Supprimer les éléments du panier liés à ce trajet
        $stmt = $conn->prepare("DELETE FROM cart WHERE ride_id = ?");
        $stmt->execute([$ride_id]);
        
        // Supprimer le trajet
        $stmt = $conn->prepare("DELETE FROM rides WHERE id = ?");
        $stmt->execute([$ride_id]);
        
        $conn->commit();
        
        // Journaliser l'action
        logAction($conn, $_SESSION['user_id'], 'ride_deleted', "Trajet #$ride_id supprimé");
        
        $_SESSION['success'] = "Trajet supprimé avec succès";
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erreur suppression trajet: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
}

redirect('my_rides.php');
?>