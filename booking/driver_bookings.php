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
    
    // Construire la requête selon le filtre - ICI ON EST LE CONDUCTEUR
    $sql = "
        SELECT b.*, r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.departure_address, r.arrival_address, r.price_per_seat,
               u.first_name as passenger_first_name, u.last_name as passenger_last_name,
               u.phone as passenger_phone, u.email as passenger_email,
               v.brand, v.model, v.color,
               r.available_seats
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON b.passenger_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.driver_id = ?  -- ICI on filtre par conducteur
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
        WHERE r.driver_id = ?  -- Ici aussi, on filtre par conducteur
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch();
    
    // Si un ride_id est spécifié, récupérer les infos du trajet
    $ride_info = null;
    if ($ride_id) {
        $ride_stmt = $conn->prepare("
            SELECT r.*, v.brand, v.model, v.color
            FROM rides r
            JOIN vehicles v ON r.vehicle_id = v.id
            WHERE r.id = ? AND r.driver_id = ?
        ");
        $ride_stmt->execute([$ride_id, $_SESSION['user_id']]);
        $ride_info = $ride_stmt->fetch();
    }
    
} catch (Exception $e) {
    error_log("Erreur réservations conducteur: " . $e->getMessage());
    $error = "Erreur lors du chargement des réservations: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservations de mes trajets - <?php echo APP_NAME; ?></title>
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
        .ride-info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
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
                        <i class="fas fa-users me-2"></i>Réservations de mes trajets
                    </h1>
                    <p class="lead mb-0">
                        <?php if ($ride_info): ?>
                            <i class="fas fa-route me-1"></i>
                            <?php echo htmlspecialchars($ride_info['departure_city']); ?> → 
                            <?php echo htmlspecialchars($ride_info['arrival_city']); ?>
                        <?php else: ?>
                            Gérez toutes les réservations de vos trajets
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../rides/my_rides.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i>Mes trajets
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Information sur le trajet si spécifié -->
        <?php if ($ride_info): ?>
            <div class="ride-info-card">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-route me-2 text-primary"></i>Informations du trajet</h5>
                        <p class="mb-1">
                            <strong>Itinéraire:</strong> 
                            <?php echo htmlspecialchars($ride_info['departure_city']); ?> → 
                            <?php echo htmlspecialchars($ride_info['arrival_city']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Date:</strong> 
                            <?php echo formatDate($ride_info['departure_date']); ?> à 
                            <?php echo substr($ride_info['departure_time'], 0, 5); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Véhicule:</strong> 
                            <?php echo htmlspecialchars($ride_info['brand'] . ' ' . $ride_info['model'] . ' (' . $ride_info['color'] . ')'); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="small text-muted">Places totales</div>
                                <div class="fw-bold text-primary"><?php echo $ride_info['available_seats']; ?></div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Prix/place</div>
                                <div class="fw-bold text-success"><?php echo formatCurrency($ride_info['price_per_seat']); ?></div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Réservations</div>
                                <div class="fw-bold text-info"><?php echo count($bookings); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filtres et statistiques -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-bold me-2">Filtrer :</span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=all<?php echo $ride_id ? '&ride_id=' . $ride_id : ''; ?>'">
                        Toutes <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=upcoming<?php echo $ride_id ? '&ride_id=' . $ride_id : ''; ?>'">
                        À venir <span class="badge bg-success"><?php echo $stats['upcoming']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'past' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=past<?php echo $ride_id ? '&ride_id=' . $ride_id : ''; ?>'">
                        Passées <span class="badge bg-secondary"><?php echo $stats['past']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=cancelled<?php echo $ride_id ? '&ride_id=' . $ride_id : ''; ?>'">
                        Annulées <span class="badge bg-danger"><?php echo $stats['cancelled']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'pending' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=pending<?php echo $ride_id ? '&ride_id=' . $ride_id : ''; ?>'">
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
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <i class="fas fa-user me-2 text-primary"></i>
                                                    <?php echo htmlspecialchars($booking['passenger_first_name'] . ' ' . $booking['passenger_last_name']); ?>
                                                </h5>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-route me-1"></i>
                                                    <?php echo htmlspecialchars($booking['departure_city']); ?> → 
                                                    <?php echo htmlspecialchars($booking['arrival_city']); ?>
                                                </p>
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo formatDate($booking['departure_date']); ?> à 
                                                    <?php echo substr($booking['departure_time'], 0, 5); ?>
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
                                    
                                    <!-- Coordonnées passager -->
                                    <div class="col-md-3">
                                        <div class="small text-muted mb-1">Coordonnées du passager</div>
                                        <p class="mb-1">
                                            <i class="fas fa-envelope me-1"></i>
                                            <small><?php echo htmlspecialchars($booking['passenger_email']); ?></small>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-phone me-1"></i>
                                            <small><?php echo htmlspecialchars($booking['passenger_phone']); ?></small>
                                        </p>
                                    </div>
                                    
                                    <!-- Statut et actions -->
                                    <div class="col-md-2">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="mb-2">
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
                                                    
                                                    <?php if ($booking['status'] === 'pending'): ?>
                                                        <li>
                                                            <a class="dropdown-item text-success" href="confirm_booking.php?id=<?php echo $booking['id']; ?>">
                                                                <i class="fas fa-check me-2"></i>Confirmer
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="reject_booking.php?id=<?php echo $booking['id']; ?>">
                                                                <i class="fas fa-times me-2"></i>Refuser
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($booking['status'] === 'confirmed' && strtotime($booking['departure_date']) >= strtotime('+1 day')): ?>
                                                        <li>
                                                            <a class="dropdown-item text-warning" href="#" 
                                                               onclick="confirmCancellation(<?php echo $booking['id']; ?>, 'driver')">
                                                                <i class="fas fa-ban me-2"></i>Annuler
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="contactPassenger('<?php echo htmlspecialchars($booking['passenger_first_name']); ?>', '<?php echo htmlspecialchars($booking['passenger_phone']); ?>', '<?php echo htmlspecialchars($booking['passenger_email']); ?>')">
                                                            <i class="fas fa-envelope me-2"></i>Contacter
                                                        </a>
                                                    </li>
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
                <i class="fas fa-users fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucune réservation trouvée</h4>
                <p class="text-muted mb-4">
                    <?php if ($filter === 'upcoming'): ?>
                        Vous n'avez pas de réservations à venir pour vos trajets.
                    <?php elseif ($filter === 'past'): ?>
                        Vous n'avez pas de réservations passées.
                    <?php elseif ($filter === 'cancelled'): ?>
                        Vous n'avez pas de réservations annulées.
                    <?php elseif ($filter === 'pending'): ?>
                        Vous n'avez pas de réservations en attente de confirmation.
                    <?php else: ?>
                        Personne n'a encore réservé vos trajets.
                    <?php endif; ?>
                </p>
                <?php if (!$ride_id): ?>
                    <a href="../rides/my_rides.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>Voir mes trajets
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancellation(bookingId, type) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ? Cette action peut être remboursée.')) {
                window.location.href = 'cancel_booking.php?id=' + bookingId + '&type=' + type;
            }
        }
        
        function contactPassenger(passengerName, phone, email) {
            const message = `Contacter ${passengerName}:\n\nTéléphone: ${phone}\nEmail: ${email}\n\nVous pouvez utiliser ces coordonnées pour échanger avec le passager.`;
            alert(message);
        }
    </script>
</body>
</html>