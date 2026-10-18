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

$filter = $_GET['filter'] ?? 'all'; // all, upcoming, past, cancelled
$ride_id = $_GET['ride_id'] ?? null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête selon le filtre
    $sql = "
        SELECT b.*, r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.departure_address, r.arrival_address, r.price_per_seat,
               u.first_name as driver_first_name, u.last_name as driver_last_name,
               u.phone as driver_phone, u.email as driver_email,
               v.brand, v.model, v.color
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE b.passenger_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    if ($ride_id) {
        $sql .= " AND b.ride_id = ?";
        $params[] = $ride_id;
    }
    
    switch ($filter) {
        case 'upcoming':
            $sql .= " AND r.departure_date >= CURDATE() AND b.status = 'confirmed'";
            break;
        case 'past':
            $sql .= " AND r.departure_date < CURDATE() AND b.status = 'confirmed'";
            break;
        case 'cancelled':
            $sql .= " AND b.status = 'cancelled'";
            break;
        case 'pending':
            $sql .= " AND b.status = 'pending'";
            break;
        default: // all
            $sql .= " AND r.departure_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
    
    $sql .= " ORDER BY r.departure_date DESC, r.departure_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
    // Statistiques pour les filtres
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.departure_date >= CURDATE() AND b.status = 'confirmed' THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN r.departure_date < CURDATE() AND b.status = 'confirmed' THEN 1 ELSE 0 END) as past,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        WHERE b.passenger_id = ?
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Erreur mes réservations: " . $e->getMessage());
    $error = "Erreur lors du chargement des réservations: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Réservations - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bookings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .booking-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .booking-card:hover {
            transform: translateY(-2px);
        }
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-badge.active {
            background: #667eea !important;
            color: white;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .booking-confirmed { border-left: 4px solid #28a745; }
        .booking-cancelled { border-left: 4px solid #dc3545; }
        .booking-pending { border-left: 4px solid #ffc107; }
        .booking-completed { border-left: 4px solid #6c757d; }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="bookings-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-ticket-alt me-2"></i>Mes Réservations
                    </h1>
                    <p class="lead mb-0">Suivez l'état de toutes vos réservations</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../rides/browse_rides.php" class="btn btn-light">
                        <i class="fas fa-search me-1"></i>Nouvelle réservation
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filtres et statistiques -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-bold me-2">Filtrer :</span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=all'">
                        Toutes <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=upcoming'">
                        À venir <span class="badge bg-success"><?php echo $stats['upcoming']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'past' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=past'">
                        Passées <span class="badge bg-secondary"><?php echo $stats['past']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=cancelled'">
                        Annulées <span class="badge bg-danger"><?php echo $stats['cancelled']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'pending' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=pending'">
                        En attente <span class="badge bg-warning"><?php echo $stats['pending']; ?></span>
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-muted small">
                    <?php echo count($bookings); ?> réservation(s) trouvée(s)
                </div>
            </div>
        </div>

        <!-- Liste des réservations -->
        <?php if ($bookings): ?>
            <div class="row">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    // Déterminer la classe CSS selon le statut
                    $status_class = '';
                    if ($booking['status'] === 'confirmed') {
                        $status_class = strtotime($booking['departure_date']) >= strtotime('today') ? 'booking-confirmed' : 'booking-completed';
                    } elseif ($booking['status'] === 'cancelled') {
                        $status_class = 'booking-cancelled';
                    } elseif ($booking['status'] === 'pending') {
                        $status_class = 'booking-pending';
                    }
                    ?>
                    
                    <div class="col-12 mb-3">
                        <div class="card booking-card <?php echo $status_class; ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Informations du trajet -->
                                    <div class="col-md-5">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($booking['departure_city']); ?> 
                                                    <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                    <?php echo htmlspecialchars($booking['arrival_city']); ?>
                                                </h5>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo formatDate($booking['departure_date']); ?> à 
                                                    <?php echo substr($booking['departure_time'], 0, 5); ?>
                                                </p>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-user me-1"></i>
                                                    Conducteur: <?php echo htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?>
                                                </p>
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-car me-1"></i>
                                                    <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?> • 
                                                    <?php echo htmlspecialchars($booking['color']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Détails de la réservation -->
                                    <div class="col-md-3">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="small text-muted">Places</div>
                                                <div class="fw-bold text-primary">
                                                    <?php echo $booking['seats_booked']; ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">Prix total</div>
                                                <div class="fw-bold text-success">
                                                    <?php echo formatCurrency($booking['total_price']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-center mt-2">
                                            <small class="text-muted">
                                                Réservé le <?php echo formatDate($booking['booking_date'], true); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Statut et actions -->
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center">
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
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="booking_details.php?id=<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>Voir détails
                                                        </a>
                                                    </li>
                                                    <?php if ($booking['status'] === 'confirmed' && strtotime($booking['departure_date']) >= strtotime('+1 day')): ?>
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="#" 
                                                               onclick="confirmCancellation(<?php echo $booking['id']; ?>)">
                                                                <i class="fas fa-times me-2"></i>Annuler
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="contactDriver('<?php echo htmlspecialchars($booking['driver_first_name']); ?>', '<?php echo htmlspecialchars($booking['driver_phone']); ?>', '<?php echo htmlspecialchars($booking['driver_email']); ?>')">
                                                            <i class="fas fa-envelope me-2"></i>Contacter
                                                        </a>
                                                    </li>
                                                    <?php if (strtotime($booking['departure_date']) < strtotime('today') && $booking['status'] === 'confirmed'): ?>
                                                        <li>
                                                            <a class="dropdown-item text-info" href="#" 
                                                               onclick="leaveReview(<?php echo $booking['id']; ?>, <?php echo $booking['driver_id']; ?>)">
                                                                <i class="fas fa-star me-2"></i>Noter
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Adresses -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            Départ: <?php echo htmlspecialchars($booking['departure_address']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-success me-1"></i>
                                            Arrivée: <?php echo htmlspecialchars($booking['arrival_address']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-ticket-alt fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucune réservation trouvée</h4>
                <p class="text-muted mb-4">
                    <?php if ($filter === 'upcoming'): ?>
                        Vous n'avez pas de réservations à venir.
                    <?php elseif ($filter === 'past'): ?>
                        Vous n'avez pas de réservations passées.
                    <?php elseif ($filter === 'cancelled'): ?>
                        Vous n'avez pas de réservations annulées.
                    <?php elseif ($filter === 'pending'): ?>
                        Vous n'avez pas de réservations en attente.
                    <?php else: ?>
                        Vous n'avez pas encore fait de réservations.
                    <?php endif; ?>
                </p>
                <a href="../rides/browse_rides.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Rechercher des trajets
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancellation(bookingId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
                window.location.href = 'cancel_booking.php?id=' + bookingId;
            }
        }
        
        function contactDriver(driverName, phone, email) {
            const message = `Contacter ${driverName}:\n\nTéléphone: ${phone}\nEmail: ${email}\n\nVous pouvez utiliser ces coordonnées pour échanger avec le conducteur.`;
            alert(message);
        }
        
        function leaveReview(bookingId, driverId) {
            window.location.href = '../reviews/create_review.php?booking_id=' + bookingId + '&driver_id=' + driverId;
        }
    </script>
</body>
</html>