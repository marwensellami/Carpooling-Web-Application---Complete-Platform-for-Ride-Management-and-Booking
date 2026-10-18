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

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les statistiques de l'utilisateur
    $user_stats = getUserStats($conn, $_SESSION['user_id']);
    
    // Récupérer les trajets à venir de l'utilisateur (en tant que conducteur)
    $stmt = $conn->prepare("
        SELECT r.*, v.brand, v.model, v.color,
               COUNT(b.id) as bookings_count
        FROM rides r
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.driver_id = ? AND r.departure_date >= CURDATE() AND r.is_active = 1
        GROUP BY r.id
        ORDER BY r.departure_date, r.departure_time
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcoming_rides = $stmt->fetchAll();
    
    // Récupérer les réservations à venir (en tant que passager)
    $stmt = $conn->prepare("
        SELECT b.*, r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               u.first_name as driver_first_name, u.last_name as driver_last_name,
               u.phone as driver_phone
        FROM bookings b
        JOIN rides r ON b.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        WHERE b.passenger_id = ? AND b.status = 'confirmed' AND r.departure_date >= CURDATE()
        ORDER BY r.departure_date, r.departure_time
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcoming_bookings = $stmt->fetchAll();
    
    // Récupérer les notifications non lues
    $notifications = getUnreadNotifications($conn, $_SESSION['user_id'], 5);
    
    // Récupérer l'activité récente
    $stmt = $conn->prepare("
        (SELECT 'ride' as type, id, departure_city, arrival_city, departure_date, created_at
         FROM rides 
         WHERE driver_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY created_at DESC
         LIMIT 3)
        UNION
        (SELECT 'booking' as type, id, '' as departure_city, '' as arrival_city, booking_date as departure_date, booking_date as created_at
         FROM bookings 
         WHERE passenger_id = ? AND booking_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY booking_date DESC
         LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur dashboard: " . $e->getMessage());
    $error = "Erreur lors du chargement du tableau de bord: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .quick-action {
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            text-decoration: none;
            display: block;
            transition: transform 0.2s;
        }
        .quick-action:hover {
            color: white;
            text-decoration: none;
            transform: translateY(-3px);
        }
        .notification-item {
            border-left: 4px solid #667eea;
            padding-left: 1rem;
        }
        .activity-item {
            border-left: 3px solid #28a745;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">Bonjour, <?php echo htmlspecialchars($_SESSION['first_name']); ?> ! 👋</h1>
                    <p class="lead mb-0">Bienvenue sur votre tableau de bord</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="../rides/create_ride.php" class="btn btn-light">
                            <i class="fas fa-plus me-1"></i>Publier un trajet
                        </a>
                        <a href="../rides/browse_rides.php" class="btn btn-outline-light">
                            <i class="fas fa-search me-1"></i>Chercher
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-center border-primary">
                    <div class="card-body">
                        <i class="fas fa-route fa-2x text-primary mb-2"></i>
                        <h3 class="text-primary mb-1"><?php echo $user_stats['rides_published']; ?></h3>
                        <p class="text-muted mb-0">Trajets publiés</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-center border-success">
                    <div class="card-body">
                        <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                        <h3 class="text-success mb-1"><?php echo $user_stats['upcoming_rides']; ?></h3>
                        <p class="text-muted mb-0">Trajets à venir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-center border-info">
                    <div class="card-body">
                        <i class="fas fa-user-plus fa-2x text-info mb-2"></i>
                        <h3 class="text-info mb-1"><?php echo $user_stats['bookings_received']; ?></h3>
                        <p class="text-muted mb-0">Réservations reçues</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card text-center border-warning">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-warning mb-2"></i>
                        <h3 class="text-warning mb-1"><?php echo $user_stats['bookings_made']; ?></h3>
                        <p class="text-muted mb-0">Trajets réservés</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Colonne de gauche - Actions rapides et notifications -->
            <div class="col-lg-4 mb-4">
                <!-- Actions rapides -->
                <div class="card stat-card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Actions Rapides
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="../rides/create_ride.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle text-primary me-2"></i>
                                Publier un nouveau trajet
                            </a>
                            <a href="../rides/browse_rides.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-search text-success me-2"></i>
                                Chercher un trajet
                            </a>
                            <a href="profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user text-info me-2"></i>
                                Voir mon profil
                            </a>
                            <a href="../rides/my_rides.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-list text-warning me-2"></i>
                                Mes trajets publiés
                            </a>
                            <a href="../booking/my_bookings.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-ticket-alt text-secondary me-2"></i>
                                Mes réservations
                            </a>
                        </div>
                    </div>
                </div>

                
            </div>

            <!-- Colonne centrale - Trajets à venir -->
            <div class="col-lg-8">
                <div class="row">
                    <!-- Mes trajets à venir (conducteur) -->
                    <div class="col-md-6 mb-4">
                        <div class="card stat-card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-car me-2"></i>Mes Trajets à Venir
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($upcoming_rides): ?>
                                    <?php foreach ($upcoming_rides as $ride): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($ride['departure_city']); ?> → <?php echo htmlspecialchars($ride['arrival_city']); ?></strong>
                                                    <p class="small text-muted mb-1">
                                                        <?php echo formatDate($ride['departure_date']); ?> à <?php echo substr($ride['departure_time'], 0, 5); ?>
                                                    </p>
                                                    <p class="small mb-0">
                                                        <span class="badge bg-info"><?php echo $ride['available_seats']; ?> place(s)</span>
                                                        <span class="badge bg-success"><?php echo $ride['bookings_count']; ?> réservation(s)</span>
                                                    </p>
                                                </div>
                                                <span class="badge bg-primary"><?php echo formatCurrency($ride['price_per_seat']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center mt-3">
                                        <a href="../rides/my_rides.php" class="btn btn-outline-primary btn-sm">
                                            Voir tous mes trajets
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-road fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucun trajet à venir</p>
                                        <a href="../rides/create_ride.php" class="btn btn-primary btn-sm">
                                            Publier mon premier trajet
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Mes réservations à venir (passager) -->
                    <div class="col-md-6 mb-4">
                        <div class="card stat-card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-ticket-alt me-2"></i>Mes Réservations à Venir
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($upcoming_bookings): ?>
                                    <?php foreach ($upcoming_bookings as $booking): ?>
                                        <div class="border-bottom pb-2 mb-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($booking['departure_city']); ?> → <?php echo htmlspecialchars($booking['arrival_city']); ?></strong>
                                                    <p class="small text-muted mb-1">
                                                        <?php echo formatDate($booking['departure_date']); ?> à <?php echo substr($booking['departure_time'], 0, 5); ?>
                                                    </p>
                                                    <p class="small mb-0">
                                                        Conducteur: <?php echo htmlspecialchars($booking['driver_first_name'] . ' ' . $booking['driver_last_name']); ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-success"><?php echo $booking['seats_booked']; ?> place(s)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-center mt-3">
                                        <a href="../booking/my_bookings.php" class="btn btn-outline-success btn-sm">
                                            Voir mes réservations
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-ticket-alt fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Aucune réservation à venir</p>
                                        <a href="../rides/browse_rides.php" class="btn btn-success btn-sm">
                                            Chercher un trajet
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="card stat-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Activité Récente
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_activity): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <?php if ($activity['type'] === 'ride'): ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-car text-success me-2"></i>
                                                <strong>Trajet publié</strong>
                                                <span class="text-muted ms-2">
                                                    <?php echo htmlspecialchars($activity['departure_city']); ?> → <?php echo htmlspecialchars($activity['arrival_city']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted"><?php echo formatDate($activity['created_at'], true); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-ticket-alt text-primary me-2"></i>
                                                <strong>Réservation effectuée</strong>
                                            </div>
                                            <small class="text-muted"><?php echo formatDate($activity['created_at'], true); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Aucune activité récente</p>
                            </div>
                        <?php endif; ?>
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