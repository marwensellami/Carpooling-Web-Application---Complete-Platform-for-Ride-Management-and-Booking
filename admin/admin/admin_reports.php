<?php
session_start();
require_once '../../config/db_connection.php';
require_once '../../config/app_settings.php';
require_once '../../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = "Accès non autorisé";
    redirect('../../index.php');
}

// Paramètres de période
$period = $_GET['period'] ?? 'month'; // week, month, quarter, year
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Déterminer les dates selon la période
    $date_conditions = "";
    $date_params = [];
    
    if (!empty($start_date) && !empty($end_date)) {
        $date_conditions = "WHERE DATE(created_at) BETWEEN ? AND ?";
        $date_params = [$start_date, $end_date];
    } else {
        switch ($period) {
            case 'week':
                $date_conditions = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'quarter':
                $date_conditions = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
                break;
            case 'year':
                $date_conditions = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default: // month
                $date_conditions = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
    }
    
    // Statistiques principales
    $stats = [];
    
    // Utilisateurs
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(is_active = 1) as active FROM users $date_conditions");
    $stmt->execute($date_params);
    $stats['users'] = $stmt->fetch();
    
    // Trajets
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(is_active = 1) as active FROM rides $date_conditions");
    $stmt->execute($date_params);
    $stats['rides'] = $stmt->fetch();
    
    // Réservations
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(status = 'confirmed') as confirmed, SUM(total_price) as revenue FROM bookings $date_conditions");
    $stmt->execute($date_params);
    $stats['bookings'] = $stmt->fetch();
    
    // Revenus totaux
    $stmt = $conn->prepare("SELECT SUM(total_price) as total_revenue FROM bookings WHERE status = 'confirmed' $date_conditions");
    $stmt->execute($date_params);
    $stats['revenue'] = $stmt->fetch()['total_revenue'] ?? 0;
    
    // Évolution mensuelle des utilisateurs
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $user_growth = $stmt->fetchAll();
    
    // Évolution mensuelle des trajets
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM rides 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $ride_growth = $stmt->fetchAll();
    
    // Villes les plus populaires
    $stmt = $conn->prepare("
        SELECT departure_city, COUNT(*) as ride_count
        FROM rides 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY departure_city 
        ORDER BY ride_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $popular_cities = $stmt->fetchAll();
    
    // Conducteurs les plus actifs
    $stmt = $conn->prepare("
        SELECT u.first_name, u.last_name, COUNT(r.id) as ride_count,
               SUM(b.seats_booked) as total_passengers,
               SUM(b.total_price) as total_revenue
        FROM users u
        JOIN rides r ON u.id = r.driver_id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY u.id
        ORDER BY ride_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $top_drivers = $stmt->fetchAll();
    
    // Trajets les plus réservés
    $stmt = $conn->prepare("
        SELECT r.departure_city, r.arrival_city, r.departure_date,
               COUNT(b.id) as booking_count,
               SUM(b.seats_booked) as total_seats,
               SUM(b.total_price) as total_revenue
        FROM rides r
        JOIN bookings b ON r.id = b.ride_id
        WHERE b.status = 'confirmed' AND b.booking_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY r.id
        ORDER BY booking_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $popular_rides = $stmt->fetchAll();
    
    // Taux de remplissage moyen
    $stmt = $conn->prepare("
        SELECT 
            AVG((SELECT COALESCE(SUM(seats_booked), 0) FROM bookings WHERE ride_id = r.id AND status = 'confirmed') / r.available_seats * 100) as avg_occupancy_rate
        FROM rides r
        WHERE r.departure_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    ");
    $stmt->execute();
    $avg_occupancy = $stmt->fetch()['avg_occupancy_rate'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erreur rapports admin: " . $e->getMessage());
    $error = "Erreur lors du chargement des rapports: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports & Statistiques - Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-header {
            background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            height: 300px;
        }
        .report-table {
            font-size: 0.9rem;
        }
        .progress-thin {
            height: 6px;
        }
    </style>
</head>
<body>
    <!-- Header Admin -->
    <?php include '../../includes/header.php'; ?>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-chart-bar me-2"></i>Rapports & Statistiques
                    </h1>
                    <p class="lead mb-0">Analyses détaillées de la plateforme</p>
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

        <!-- Filtres de période -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-filter me-2"></i>Filtrer par période
                </h5>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Période prédéfinie</label>
                        <select class="form-select" name="period" onchange="this.form.submit()">
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>7 derniers jours</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>30 derniers jours</option>
                            <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>3 derniers mois</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>12 derniers mois</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date de début</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date de fin</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>Appliquer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiques principales -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-center border-primary">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                        <h3 class="text-primary mb-1"><?php echo $stats['users']['total'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Nouveaux utilisateurs</p>
                        <small class="text-success">
                            <?php echo $stats['users']['active'] ?? 0; ?> actifs
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-success">
                    <div class="card-body">
                        <i class="fas fa-route fa-2x text-success mb-2"></i>
                        <h3 class="text-success mb-1"><?php echo $stats['rides']['total'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Nouveaux trajets</p>
                        <small class="text-success">
                            <?php echo $stats['rides']['active'] ?? 0; ?> actifs
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-info">
                    <div class="card-body">
                        <i class="fas fa-ticket-alt fa-2x text-info mb-2"></i>
                        <h3 class="text-info mb-1"><?php echo $stats['bookings']['total'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Nouvelles réservations</p>
                        <small class="text-success">
                            <?php echo $stats['bookings']['confirmed'] ?? 0; ?> confirmées
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-center border-warning">
                    <div class="card-body">
                        <i class="fas fa-money-bill-wave fa-2x text-warning mb-2"></i>
                        <h3 class="text-warning mb-1"><?php echo formatPrice($stats['revenue']); ?></h3>
                        <p class="text-muted mb-0">Revenus générés</p>
                        <small class="text-success">
                            Taux de remplissage: <?php echo number_format($avg_occupancy, 1); ?>%
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Colonne de gauche -->
            <div class="col-lg-8">
                <!-- Graphique d'évolution des utilisateurs -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        Évolution des Utilisateurs
                    </h5>
                    <canvas id="userGrowthChart"></canvas>
                </div>

                <!-- Graphique d'évolution des trajets -->
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2 text-success"></i>
                        Évolution des Trajets
                    </h5>
                    <canvas id="rideGrowthChart"></canvas>
                </div>

                <!-- Villes les plus populaires -->
                <div class="card stat-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>Villes les Plus Populaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($popular_cities): ?>
                            <div class="table-responsive">
                                <table class="table table-sm report-table">
                                    <thead>
                                        <tr>
                                            <th>Ville</th>
                                            <th>Nombre de trajets</th>
                                            <th>Pourcentage</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_city_rides = array_sum(array_column($popular_cities, 'ride_count'));
                                        foreach ($popular_cities as $city): 
                                            $percentage = ($city['ride_count'] / $total_city_rides) * 100;
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($city['departure_city']); ?></strong></td>
                                                <td><?php echo $city['ride_count']; ?></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                                <td>
                                                    <div class="progress progress-thin">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
                <!-- Conducteurs les plus actifs -->
                <div class="card stat-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-trophy me-2"></i>Top Conducteurs
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($top_drivers): ?>
                            <?php foreach ($top_drivers as $index => $driver): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php if ($index < 3): ?>
                                                    <span class="badge bg-warning me-1">#<?php echo $index + 1; ?></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?>
                                            </h6>
                                            <p class="small text-muted mb-1">
                                                <?php echo $driver['ride_count']; ?> trajets • 
                                                <?php echo $driver['total_passengers'] ?? 0; ?> passagers
                                            </p>
                                            <p class="small text-success mb-0">
                                                <?php echo formatPrice($driver['total_revenue'] ?? 0); ?> générés
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p class="text-muted">Aucune donnée disponible</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Trajets les plus populaires -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-star me-2"></i>Trajets les Plus Populaires
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($popular_rides): ?>
                            <?php foreach ($popular_rides as $ride): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($ride['departure_city']); ?> → 
                                                <?php echo htmlspecialchars($ride['arrival_city']); ?>
                                            </h6>
                                            <p class="small text-muted mb-1">
                                                <?php echo formatDate($ride['departure_date']); ?>
                                            </p>
                                            <p class="small mb-0">
                                                <span class="badge bg-info"><?php echo $ride['booking_count']; ?> réservations</span>
                                                <span class="badge bg-success"><?php echo $ride['total_seats']; ?> places</span>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-success">
                                                <?php echo formatPrice($ride['total_revenue']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p class="text-muted">Aucune donnée disponible</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistiques de performance -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tachometer-alt me-2"></i>Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Taux de remplissage moyen</span>
                                <span class="small fw-bold"><?php echo number_format($avg_occupancy, 1); ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?php echo $avg_occupancy; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Taux de réservation</span>
                                <span class="small fw-bold">
                                    <?php 
                                        $booking_rate = $stats['rides']['total'] > 0 ? ($stats['bookings']['total'] / $stats['rides']['total']) * 100 : 0;
                                        echo number_format($booking_rate, 1); 
                                    ?>%
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info" style="width: <?php echo min($booking_rate, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Taux de confirmation</span>
                                <span class="small fw-bold">
                                    <?php 
                                        $confirmation_rate = $stats['bookings']['total'] > 0 ? ($stats['bookings']['confirmed'] / $stats['bookings']['total']) * 100 : 0;
                                        echo number_format($confirmation_rate, 1); 
                                    ?>%
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: <?php echo min($confirmation_rate, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graphique d'évolution des utilisateurs
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        const userGrowthChart = new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($user_growth, 'month')); ?>,
                datasets: [{
                    label: 'Nouveaux utilisateurs',
                    data: <?php echo json_encode(array_column($user_growth, 'count')); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Graphique d'évolution des trajets
        const rideGrowthCtx = document.getElementById('rideGrowthChart').getContext('2d');
        const rideGrowthChart = new Chart(rideGrowthCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($ride_growth, 'month')); ?>,
                datasets: [{
                    label: 'Nouveaux trajets',
                    data: <?php echo json_encode(array_column($ride_growth, 'count')); ?>,
                    backgroundColor: '#28a745',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>