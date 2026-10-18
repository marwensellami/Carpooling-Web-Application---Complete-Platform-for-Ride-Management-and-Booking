<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté";
    redirect('../auth/login.php');
}

// DEBUG: Vérifier les informations de session
error_log("DEBUG - User ID: " . ($_SESSION['user_id'] ?? 'non défini'));
error_log("DEBUG - Is Admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'non défini'));
error_log("DEBUG - Session: " . print_r($_SESSION, true));

if (!isAdmin()) {
    $_SESSION['error'] = "Accès non autorisé - Vous n'avez pas les droits administrateur";
    // Redirection temporaire pour debug
    echo "<h2>DEBUG - Information de session</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    echo "<p>isAdmin() retourne: " . (isAdmin() ? 'true' : 'false') . "</p>";
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Statistiques générales
    $stats = getSiteStats($conn);
    
    // Trajets récents (7 derniers jours)
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name, 
               COUNT(b.id) as bookings_count
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_rides = $stmt->fetchAll();
    
    // Réservations récentes
    $stmt = $conn->prepare("
        SELECT b.*, u.first_name, u.last_name, 
               r.departure_city, r.arrival_city
        FROM bookings b
        JOIN users u ON b.passenger_id = u.id
        JOIN rides r ON b.ride_id = r.id
        WHERE b.booking_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY b.booking_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_bookings = $stmt->fetchAll();
    
    // Utilisateurs récents
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, email, created_at, is_active
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Activité récente (logs)
    $stmt = $conn->prepare("
        SELECT al.*, u.first_name, u.last_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
    // Statistiques par ville
    $stmt = $conn->prepare("
        SELECT departure_city, COUNT(*) as ride_count
        FROM rides 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY departure_city 
        ORDER BY ride_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_cities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur dashboard admin: " . $e->getMessage());
    $error = "Erreur lors du chargement du tableau de bord: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #343a40 0%, #6c757d 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
        }
        .stat-card:hover {
            transform: translateY(-2px);
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
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header Admin -->
    <?php include '../includes/header.php'; ?>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-tachometer-alt me-2"></i>Tableau de Bord Administrateur
                    </h1>
                    <p class="lead mb-0">Gestion complète de la plateforme</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-warning text-dark fs-6">
                        <i class="fas fa-shield-alt me-1"></i>Administrateur
                    </span>
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
            <div class="col-md-3">
                <div class="card stat-card text-center border-primary">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h3 class="text-primary mb-1"><?php echo $stats['total_users']; ?></h3>
                        <p class="text-muted mb-0">Utilisateurs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-success">
                    <div class="card-body">
                        <i class="fas fa-route fa-2x text-success mb-2"></i>
                        <h3 class="text-success mb-1"><?php echo $stats['total_rides']; ?></h3>
                        <p class="text-muted mb-0">Trajets Actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-info">
                    <div class="card-body">
                        <i class="fas fa-ticket-alt fa-2x text-info mb-2"></i>
                        <h3 class="text-info mb-1"><?php echo $stats['total_bookings']; ?></h3>
                        <p class="text-muted mb-0">Réservations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-warning">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-warning mb-2"></i>
                        <h3 class="text-warning mb-1"><?php echo formatPrice($stats['total_savings']); ?></h3>
                        <p class="text-muted mb-0">Économie Générée</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Colonne de gauche -->
            <div class="col-lg-8">
                <!-- Actions rapides -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <a href="manage_users.php" class="quick-action" style="background: linear-gradient(45deg, #007bff, #0056b3);">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h5>Gérer les Utilisateurs</h5>
                            <p class="mb-0">Gestion des comptes et permissions</p>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="manage_rides.php" class="quick-action" style="background: linear-gradient(45deg, #28a745, #1e7e34);">
                            <i class="fas fa-route fa-2x mb-2"></i>
                            <h5>Gérer les Trajets</h5>
                            <p class="mb-0">Supervision de tous les trajets</p>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="admin_reports.php" class="quick-action" style="background: linear-gradient(45deg, #6f42c1, #563d7c);">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                            <h5>Rapports & Stats</h5>
                            <p class="mb-0">Analyses et statistiques</p>
                        </a>
                    </div>
                </div>

                <!-- Trajets récents -->
                <div class="card stat-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2"></i>Trajets Récents
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_rides): ?>
                            <?php foreach ($recent_rides as $ride): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($ride['departure_city']); ?> → <?php echo htmlspecialchars($ride['arrival_city']); ?></strong>
                                            <p class="small text-muted mb-1">
                                                Par <?php echo htmlspecialchars($ride['first_name'] . ' ' . $ride['last_name']); ?> • 
                                                <?php echo formatDate($ride['departure_date']); ?>
                                            </p>
                                            <p class="small mb-0">
                                                <span class="badge bg-info"><?php echo $ride['available_seats']; ?> places</span>
                                                <span class="badge bg-success"><?php echo $ride['bookings_count']; ?> réservations</span>
                                                <span class="badge bg-secondary"><?php echo formatPrice($ride['price_per_seat']); ?></span>
                                            </p>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($ride['created_at'], true); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="manage_rides.php" class="btn btn-outline-primary btn-sm">
                                    Voir tous les trajets
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-road fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Aucun trajet récent</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Villes populaires -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>Villes les Plus Populaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($top_cities): ?>
                            <div class="row">
                                <?php foreach ($top_cities as $city): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($city['departure_city']); ?></span>
                                            <span class="badge bg-primary"><?php echo $city['ride_count']; ?> trajets</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p class="text-muted">Aucune donnée disponible</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne de droite -->
            <div class="col-lg-4">
                <!-- Utilisateurs récents -->
                <div class="card stat-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus me-2"></i>Utilisateurs Récents
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_users): ?>
                            <?php foreach ($recent_users as $user): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                            <p class="small text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">Inscrit le <?php echo formatDate($user['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="manage_users.php" class="btn btn-outline-success btn-sm">
                                    Gérer les utilisateurs
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Aucun nouvel utilisateur</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Réservations récentes -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-ticket-alt me-2"></i>Réservations Récentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_bookings): ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($booking['departure_city']); ?> → <?php echo htmlspecialchars($booking['arrival_city']); ?></strong>
                                            <p class="small text-muted mb-1">
                                                Par <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                            </p>
                                            <p class="small mb-0">
                                                <span class="badge bg-info"><?php echo $booking['seats_booked']; ?> places</span>
                                                <span class="badge bg-success"><?php echo formatPrice($booking['total_price']); ?></span>
                                            </p>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($booking['booking_date'], true); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-ticket-alt fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Aucune réservation récente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activité récente -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2"></i>Activité Récente
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_activity): ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="small">
                                        <strong>
                                            <?php echo $activity['user_id'] ? htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) : 'Système'; ?>
                                        </strong>
                                        <div class="text-muted"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <small class="text-muted"><?php echo formatDate($activity['created_at'], true); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
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