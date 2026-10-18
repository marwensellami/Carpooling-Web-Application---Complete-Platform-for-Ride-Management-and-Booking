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
    
    // Récupérer les détails de la réservation
    $stmt = $conn->prepare("
        SELECT b.*, 
               r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.departure_address, r.arrival_address, r.price_per_seat, r.description as ride_description,
               r.driver_id, r.is_active as ride_active,
               u.first_name as driver_first_name, u.last_name as driver_last_name,
               u.email as driver_email, u.phone as driver_phone,
               v.brand, v.model, v.year, v.color, v.license_plate, v.has_air_conditioning,
               (SELECT AVG(rating) FROM reviews WHERE reviewed_id = r.driver_id) as driver_rating,
               (SELECT COUNT(*) FROM reviews WHERE reviewed_id = r.driver_id) as driver_review_count
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE b.id = ? AND (b.passenger_id = ? OR r.driver_id = ?)
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error'] = "Réservation non trouvée ou vous n'êtes pas autorisé à la consulter";
        redirect('my_bookings.php');
    }
    
    $is_passenger = ($booking['passenger_id'] == $_SESSION['user_id']);
    $is_driver = ($booking['driver_id'] == $_SESSION['user_id']);
    
    // Récupérer les autres passagers du même trajet (pour le conducteur)
    $other_passengers = [];
    if ($is_driver) {
        $stmt = $conn->prepare("
            SELECT b.seats_booked, u.first_name, u.last_name, u.phone, u.email,
                   b.booking_date, b.status
            FROM bookings b
            JOIN users u ON b.passenger_id = u.id
            WHERE b.ride_id = ? AND b.passenger_id != ?
            ORDER BY b.booking_date DESC
        ");
        $stmt->execute([$booking['ride_id'], $_SESSION['user_id']]);
        $other_passengers = $stmt->fetchAll();
    }
    
    // Vérifier si un avis a déjà été laissé
    $has_review = false;
    if ($is_passenger && strtotime($booking['departure_date']) < strtotime('today')) {
        $stmt = $conn->prepare("
            SELECT id FROM reviews 
            WHERE booking_id = ? AND reviewer_id = ?
        ");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $has_review = (bool)$stmt->fetch();
    }
    
} catch (Exception $e) {
    error_log("Erreur détails réservation: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du chargement de la réservation";
    redirect('my_bookings.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Réservation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .booking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .detail-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        .route-map {
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 2rem;
        }
        .passenger-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #667eea;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="booking-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-ticket-alt me-2"></i>Détails de la Réservation
                    </h1>
                    <p class="lead mb-0">
                        <?php echo htmlspecialchars($booking['departure_city']); ?> 
                        <i class="fas fa-arrow-right mx-2"></i>
                        <?php echo htmlspecialchars($booking['arrival_city']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="my_bookings.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Messages d'erreur/succès -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-lg-8">
                <!-- Statut et informations générales -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2 text-primary"></i>Informations de la réservation
                            </h5>
                            <div>
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <?php if (strtotime($booking['departure_date']) >= strtotime('today')): ?>
                                        <span class="badge bg-success status-badge">Confirmée</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary status-badge">Terminée</span>
                                    <?php endif; ?>
                                <?php elseif ($booking['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger status-badge">Annulée</span>
                                <?php elseif ($booking['status'] === 'pending'): ?>
                                    <span class="badge bg-warning status-badge">En attente</span>
                                <?php endif; ?>
                                
                                <span class="badge bg-info status-badge">
                                    #<?php echo $booking_id; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Timeline de la réservation -->
                        <div class="timeline mb-4">
                            <div class="timeline-item">
                                <h6>Réservation créée</h6>
                                <p class="text-muted mb-0"><?php echo formatDate($booking['booking_date'], true); ?></p>
                            </div>
                            
                            <?php if ($booking['confirmed_at']): ?>
                                <div class="timeline-item">
                                    <h6>Réservation confirmée</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($booking['confirmed_at'], true); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['cancelled_at']): ?>
                                <div class="timeline-item">
                                    <h6>Réservation annulée</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($booking['cancelled_at'], true); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timeline-item">
                                <h6>Date du trajet</h6>
                                <p class="text-muted mb-0">
                                    <?php echo formatDate($booking['departure_date']); ?> à 
                                    <?php echo substr($booking['departure_time'], 0, 5); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Détails financiers -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Détails financiers</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Places réservées:</td>
                                        <td class="text-end"><?php echo $booking['seats_booked']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Prix par place:</td>
                                        <td class="text-end"><?php echo formatCurrency($booking['price_per_seat']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total:</strong></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($booking['total_price']); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Informations de paiement</h6>
                                <div class="alert alert-info">
                                    <small>
                                        <i class="fas fa-info-circle me-2"></i>
                                        Le paiement s'effectue directement avec le conducteur le jour du trajet.
                                        <?php if ($is_passenger): ?>
                                            Prévoyez le montant exact si possible.
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itinéraire -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-route me-2 text-success"></i>Itinéraire
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="route-map">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                                <h4><?php echo htmlspecialchars($booking['departure_city']); ?> → <?php echo htmlspecialchars($booking['arrival_city']); ?></h4>
                                <p class="mb-0">
                                    <?php 
                                        $distance = estimateDistance($booking['departure_city'], $booking['arrival_city']);
                                        $duration = estimateTravelTime($distance);
                                        echo $distance . ' km • ' . formatDuration($duration);
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-map-marker-alt fa-2x text-danger"></i>
                                    </div>
                                    <div>
                                        <h6>Départ</h6>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($booking['departure_city']); ?></strong></p>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($booking['departure_address']); ?></p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo formatDate($booking['departure_date']); ?>
                                            à <?php echo substr($booking['departure_time'], 0, 5); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-flag-checkered fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h6>Arrivée</h6>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($booking['arrival_city']); ?></strong></p>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($booking['arrival_address']); ?></p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-clock me-1"></i>
                                            Arrivée estimée: 
                                            <?php
                                                $departure_datetime = strtotime($booking['departure_date'] . ' ' . $booking['departure_time']);
                                                $arrival_datetime = $departure_datetime + ($duration * 60);
                                                echo date('H:i', $arrival_datetime);
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($booking['ride_description']): ?>
                            <div class="mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Informations complémentaires</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($booking['ride_description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne latérale -->
            <div class="col-lg-4">
                <!-- Conducteur -->
                <div class="card detail-card">
                    <div class="card-header bg-white text-center">
                        <h5 class="card-title mb-0">
                            <?php echo $is_passenger ? 'Conducteur' : 'Vous êtes le conducteur'; ?>
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="driver-avatar mx-auto mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                            <?php echo strtoupper(substr($booking['driver_first_name'], 0, 1) . substr($booking['driver_last_name'], 0, 1)); ?>
                        </div>
                        <h5><?php echo htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?></h5>
                        
                        <?php if ($booking['driver_rating']): ?>
                            <div class="mb-3">
                                <div class="rating-stars mb-1">
                                    <?php echo generateStarRating($booking['driver_rating']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($booking['driver_rating'], 1); ?>/5 
                                    (<?php echo $booking['driver_review_count']; ?> avis)
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <small class="text-muted">Aucun avis pour le moment</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                                <i class="fas fa-envelope me-2"></i>Contacter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Véhicule -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car me-2 text-success"></i>Véhicule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-car fa-3x text-success"></i>
                        </div>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?></strong></p>
                        <p class="mb-1 text-muted small">Année: <?php echo $booking['year']; ?></p>
                        <p class="mb-1 text-muted small">Couleur: <?php echo htmlspecialchars($booking['color']); ?></p>
                        <p class="mb-1 text-muted small">Plaque: <?php echo htmlspecialchars($booking['license_plate']); ?></p>
                        <p class="mb-0">
                            <?php if ($booking['has_air_conditioning']): ?>
                                <span class="badge bg-info"><i class="fas fa-snowflake me-1"></i>Climatisation</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cog me-2 text-warning"></i>Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($is_passenger): ?>
                                <?php if ($booking['status'] === 'confirmed' && strtotime($booking['departure_date']) >= strtotime('+1 day')): ?>
                                    <a href="cancel_booking.php?id=<?php echo $booking_id; ?>" 
                                       class="btn btn-outline-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')">
                                        <i class="fas fa-times me-2"></i>Annuler la réservation
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (strtotime($booking['departure_date']) < strtotime('today') && $booking['status'] === 'confirmed' && !$has_review): ?>
                                    <a href="../reviews/create_review.php?booking_id=<?php echo $booking_id; ?>&driver_id=<?php echo $booking['driver_id']; ?>" 
                                       class="btn btn-outline-info">
                                        <i class="fas fa-star me-2"></i>Noter le conducteur
                                    </a>
                                <?php endif; ?>
                                
                                <a href="../rides/ride_details.php?id=<?php echo $booking['ride_id']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i>Voir le trajet
                                </a>
                                
                            <?php else: ?>
                                <!-- Actions pour le conducteur -->
                                <a href="../rides/ride_details.php?id=<?php echo $booking['ride_id']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i>Voir le trajet
                                </a>
                                
                                <?php if ($booking['ride_active'] && strtotime($booking['departure_date']) >= strtotime('today')): ?>
                                    <a href="../rides/edit_ride.php?id=<?php echo $booking['ride_id']; ?>" 
                                       class="btn btn-outline-warning">
                                        <i class="fas fa-edit me-2"></i>Modifier le trajet
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Autres passagers (pour le conducteur) -->
                <?php if ($is_driver && $other_passengers): ?>
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2 text-info"></i>Autres passagers
                        </h5>
                    </div>
                    <div class="card-body passenger-list">
                        <?php foreach ($other_passengers as $passenger): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($passenger['first_name'] . ' ' . $passenger['last_name']); ?></strong>
                                        <div class="small text-muted">
                                            <?php echo $passenger['seats_booked']; ?> place(s)
                                        </div>
                                    </div>
                                    <span class="badge bg-<?php echo $passenger['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                        <?php echo $passenger['status'] === 'confirmed' ? 'Confirmé' : 'En attente'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de contact -->
    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Contacter <?php echo htmlspecialchars($booking['driver_first_name']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($booking['driver_email']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($booking['driver_phone']); ?>" readonly>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Vous pouvez contacter <?php echo $is_passenger ? 'le conducteur' : 'le passager'; ?> 
                        pour plus d'informations sur le trajet.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>