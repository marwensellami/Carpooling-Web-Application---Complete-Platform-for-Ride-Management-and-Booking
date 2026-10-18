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

// Paramètres de filtrage
$departure_city = $_GET['departure_city'] ?? '';
$arrival_city = $_GET['arrival_city'] ?? '';
$departure_date = $_GET['departure_date'] ?? '';
$min_seats = $_GET['min_seats'] ?? 1;
$max_price = $_GET['max_price'] ?? MAX_PRICE_PER_SEAT;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête avec filtres
    $sql = "
        SELECT r.*, u.first_name, u.last_name, u.phone, v.brand, v.model, v.color, v.has_air_conditioning,
               COUNT(b.id) as confirmed_bookings,
               (r.available_seats - COALESCE(SUM(b.seats_booked), 0)) as actual_available_seats
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.is_active = 1 AND r.departure_date >= CURDATE()
    ";
    
    $params = [];
    
    if (!empty($departure_city)) {
        $sql .= " AND r.departure_city = ?";
        $params[] = $departure_city;
    }
    
    if (!empty($arrival_city)) {
        $sql .= " AND r.arrival_city = ?";
        $params[] = $arrival_city;
    }
    
    if (!empty($departure_date)) {
        $sql .= " AND r.departure_date = ?";
        $params[] = $departure_date;
    }
    
    $sql .= " GROUP BY r.id HAVING actual_available_seats >= ?";
    $params[] = $min_seats;
    
    if (!empty($max_price) && $max_price < MAX_PRICE_PER_SEAT) {
        $sql .= " AND r.price_per_seat <= ?";
        $params[] = $max_price;
    }
    
    $sql .= " ORDER BY r.departure_date, r.departure_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rides = $stmt->fetchAll();
    
    // Villes disponibles pour les filtres
    global $tunisian_cities;
    
    // Dates disponibles
    $dates_stmt = $conn->prepare("
        SELECT DISTINCT departure_date 
        FROM rides 
        WHERE departure_date >= CURDATE() AND is_active = 1
        ORDER BY departure_date
        LIMIT 10
    ");
    $dates_stmt->execute();
    $available_dates = $dates_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Erreur recherche trajets: " . $e->getMessage());
    $error = "Erreur lors de la recherche: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechercher un Trajet - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .search-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        .ride-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .ride-card:hover {
            transform: translateY(-2px);
        }
        .route-visual {
            position: relative;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .driver-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .price-tag {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="search-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-search me-2"></i>Rechercher un Trajet
                    </h1>
                    <p class="lead mb-0">Trouvez le trajet parfait pour votre destination</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="create_ride.php" class="btn btn-light">
                        <i class="fas fa-plus me-1"></i>Proposer un trajet
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulaire de recherche -->
        <div class="card search-card">
            <div class="card-body p-4">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="departure_city" class="form-label">Départ</label>
                            <select class="form-select" id="departure_city" name="departure_city">
                                <option value="">Toutes les villes</option>
                                <?php foreach ($tunisian_cities as $city): ?>
                                    <option value="<?php echo $city; ?>" <?php echo $departure_city === $city ? 'selected' : ''; ?>>
                                        <?php echo $city; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="arrival_city" class="form-label">Arrivée</label>
                            <select class="form-select" id="arrival_city" name="arrival_city">
                                <option value="">Toutes les villes</option>
                                <?php foreach ($tunisian_cities as $city): ?>
                                    <option value="<?php echo $city; ?>" <?php echo $arrival_city === $city ? 'selected' : ''; ?>>
                                        <?php echo $city; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="departure_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="departure_date" name="departure_date" 
                                   value="<?php echo htmlspecialchars($departure_date); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="min_seats" class="form-label">Places min</label>
                            <select class="form-select" id="min_seats" name="min_seats">
                                <?php for ($i = 1; $i <= MAX_SEATS_PER_RIDE; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $min_seats == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="max_price" class="form-label">Prix max (DT)</label>
                            <input type="number" class="form-control" id="max_price" name="max_price" 
                                   value="<?php echo htmlspecialchars($max_price); ?>"
                                   min="<?php echo MIN_PRICE_PER_SEAT; ?>" 
                                   max="<?php echo MAX_PRICE_PER_SEAT; ?>" 
                                   step="0.5">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php echo count($rides); ?> trajet(s) trouvé(s)
                                </small>
                                <div>
                                    <button type="reset" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-redo me-1"></i>Réinitialiser
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Rechercher
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Résultats de recherche -->
        <?php if ($rides): ?>
            <div class="row">
                <?php foreach ($rides as $ride): ?>
                    <div class="col-12 mb-3">
                        <div class="card ride-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Informations conducteur -->
                                    <div class="col-md-2">
                                        <div class="d-flex align-items-center">
                                            <div class="driver-avatar me-3">
                                                <?php echo strtoupper(substr($ride['first_name'], 0, 1) . substr($ride['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($ride['first_name'] . ' ' . $ride['last_name']); ?></strong>
                                                <div class="small text-muted">
                                                    <i class="fas fa-car me-1"></i>
                                                    <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Itinéraire -->
                                    <div class="col-md-4">
                                        <div class="route-visual">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-center">
                                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($ride['departure_city']); ?></div>
                                                    <small class="text-muted"><?php echo substr($ride['departure_time'], 0, 5); ?></small>
                                                </div>
                                                <div class="flex-grow-1 mx-3">
                                                    <div class="progress" style="height: 3px;">
                                                        <div class="progress-bar bg-primary" style="width: 100%"></div>
                                                    </div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="fw-bold text-success"><?php echo htmlspecialchars($ride['arrival_city']); ?></div>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $distance = estimateDistance($ride['departure_city'], $ride['arrival_city']);
                                                            $duration = estimateTravelTime($distance);
                                                            echo formatDuration($duration);
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Date et places -->
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="fw-bold text-info">
                                                <?php echo formatDate($ride['departure_date']); ?>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $ride['actual_available_seats']; ?> place(s) disponible(s)
                                            </div>
                                            <?php if ($ride['has_air_conditioning']): ?>
                                                <span class="badge bg-info mt-1">
                                                    <i class="fas fa-snowflake"></i> Climatisation
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Prix et action -->
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price-tag">
                                                <?php echo formatCurrency($ride['price_per_seat']); ?>
                                            </div>
                                            <div>
                                                <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Voir détails
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Adresses -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            <?php echo htmlspecialchars($ride['departure_address']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-success me-1"></i>
                                            <?php echo htmlspecialchars($ride['arrival_address']); ?>
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
                <i class="fas fa-search fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucun trajet trouvé</h4>
                <p class="text-muted mb-4">
                    Aucun trajet ne correspond à vos critères de recherche.
                    <?php if ($departure_city || $arrival_city || $departure_date): ?>
                        Essayez d'élargir vos critères.
                    <?php endif; ?>
                </p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="browse_rides.php" class="btn btn-outline-primary">
                        <i class="fas fa-redo me-2"></i>Voir tous les trajets
                    </a>
                    <a href="create_ride.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Proposer un trajet
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Date minimum pour la recherche
        document.getElementById('departure_date').min = new Date().toISOString().split('T')[0];
        
        // Mise à jour automatique du formulaire lors du changement des filtres
        const filters = ['departure_city', 'arrival_city', 'departure_date', 'min_seats', 'max_price'];
        filters.forEach(filter => {
            document.getElementById(filter).addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>