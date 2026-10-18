<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion et les droits admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error'] = "Accès non autorisé";
    redirect('../index.php');
}

// Paramètres de filtrage
$filter = $_GET['filter'] ?? 'all'; // all, active, inactive, upcoming, past
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête avec filtres
    $sql = "
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone,
               v.brand, v.model, v.license_plate,
               COUNT(b.id) as confirmed_bookings,
               SUM(b.seats_booked) as total_seats_booked,
               (r.available_seats - COALESCE(SUM(b.seats_booked), 0)) as actual_available_seats
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
    ";
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(r.departure_city LIKE ? OR r.arrival_city LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    switch ($filter) {
        case 'active':
            $where_conditions[] = "r.is_active = 1 AND r.departure_date >= CURDATE()";
            break;
        case 'inactive':
            $where_conditions[] = "r.is_active = 0";
            break;
        case 'upcoming':
            $where_conditions[] = "r.departure_date >= CURDATE()";
            break;
        case 'past':
            $where_conditions[] = "r.departure_date < CURDATE()";
            break;
        default: // all
            break;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " GROUP BY r.id ORDER BY r.created_at DESC";
    
    // Requête pour le comptage total
    $count_sql = "SELECT COUNT(DISTINCT r.id) as total FROM rides r JOIN users u ON r.driver_id = u.id";
    if (!empty($where_conditions)) {
        $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_rides = $stmt->fetch()['total'];
    $total_pages = ceil($total_rides / $per_page);
    
    // Requête paginée
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rides = $stmt->fetchAll();
    
    // Actions admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $ride_id = (int)($_POST['ride_id'] ?? 0);
        
        if ($ride_id && in_array($action, ['activate', 'deactivate', 'delete'])) {
            try {
                $conn->beginTransaction();
                
                // Vérifier que le trajet existe
                $stmt = $conn->prepare("SELECT * FROM rides WHERE id = ?");
                $stmt->execute([$ride_id]);
                $ride = $stmt->fetch();
                
                if (!$ride) {
                    throw new Exception("Trajet non trouvé");
                }
                
                switch ($action) {
                    case 'activate':
                        $stmt = $conn->prepare("UPDATE rides SET is_active = 1 WHERE id = ?");
                        $stmt->execute([$ride_id]);
                        $_SESSION['success'] = "Trajet activé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'ride_activated_admin', "Trajet #$ride_id activé par admin");
                        break;
                        
                    case 'deactivate':
                        $stmt = $conn->prepare("UPDATE rides SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$ride_id]);
                        $_SESSION['success'] = "Trajet désactivé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'ride_deactivated_admin', "Trajet #$ride_id désactivé par admin");
                        break;
                        
                    case 'delete':
                        // Vérifier s'il y a des réservations confirmées
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE ride_id = ? AND status = 'confirmed'");
                        $stmt->execute([$ride_id]);
                        $confirmed_bookings = $stmt->fetch()['count'];
                        
                        if ($confirmed_bookings > 0) {
                            throw new Exception("Impossible de supprimer un trajet avec des réservations confirmées");
                        }
                        
                        // Supprimer les réservations en attente et les éléments du panier
                        $stmt = $conn->prepare("DELETE FROM bookings WHERE ride_id = ?");
                        $stmt->execute([$ride_id]);
                        
                        $stmt = $conn->prepare("DELETE FROM cart WHERE ride_id = ?");
                        $stmt->execute([$ride_id]);
                        
                        // Supprimer le trajet
                        $stmt = $conn->prepare("DELETE FROM rides WHERE id = ?");
                        $stmt->execute([$ride_id]);
                        
                        $_SESSION['success'] = "Trajet supprimé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'ride_deleted_admin', "Trajet #$ride_id supprimé par admin");
                        break;
                }
                
                $conn->commit();
                
                // Rediriger pour éviter la resoumission du formulaire
                redirect('manage_rides.php?' . $_SERVER['QUERY_STRING']);
                
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = "Erreur: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur gestion trajets admin: " . $e->getMessage());
    $error = "Erreur lors du chargement: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Trajets - Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
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
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-badge.active {
            background: #28a745 !important;
            color: white;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .search-box {
            max-width: 400px;
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
                        <i class="fas fa-route me-2"></i>Gestion des Trajets
                    </h1>
                    <p class="lead mb-0">Supervision de tous les trajets de la plateforme</p>
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

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Barre de recherche et filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <div class="input-group search-box">
                                <input type="text" class="form-control" name="search" placeholder="Rechercher par ville ou conducteur..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                            <span class="fw-bold me-2">Filtrer :</span>
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=all&search=<?php echo urlencode($search); ?>'">
                                Tous
                            </span>
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'active' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=active&search=<?php echo urlencode($search); ?>'">
                                Actifs
                            </span>
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'inactive' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=inactive&search=<?php echo urlencode($search); ?>'">
                                Inactifs
                            </span>
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=upcoming&search=<?php echo urlencode($search); ?>'">
                                À venir
                            </span>
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'past' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=past&search=<?php echo urlencode($search); ?>'">
                                Passés
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?php echo $total_rides; ?></h5>
                        <p class="card-text text-muted">Trajets total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-success" id="activeRidesCount">-</h5>
                        <p class="card-text text-muted">Trajets actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-warning" id="upcomingRidesCount">-</h5>
                        <p class="card-text text-muted">Trajets à venir</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-info" id="totalBookingsCount">-</h5>
                        <p class="card-text text-muted">Réservations total</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des trajets -->
        <?php if ($rides): ?>
            <div class="row">
                <?php foreach ($rides as $ride): ?>
                    <div class="col-12 mb-3">
                        <div class="card ride-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Informations du trajet -->
                                    <div class="col-md-5">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($ride['departure_city']); ?> 
                                                    <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                    <?php echo htmlspecialchars($ride['arrival_city']); ?>
                                                </h5>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo formatDate($ride['departure_date']); ?> à 
                                                    <?php echo substr($ride['departure_time'], 0, 5); ?>
                                                </p>
                                                <p class="text-muted mb-1 small">
                                                    <i class="fas fa-user me-1"></i>
                                                    Conducteur: <?php echo htmlspecialchars($ride['first_name'] . ' ' . $ride['last_name']); ?>
                                                </p>
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-car me-1"></i>
                                                    <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?> • 
                                                    <?php echo htmlspecialchars($ride['license_plate']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Statistiques -->
                                    <div class="col-md-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="small text-muted">Places</div>
                                                <div class="fw-bold <?php echo $ride['actual_available_seats'] == 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo $ride['actual_available_seats']; ?>/<?php echo $ride['available_seats']; ?>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Réservés</div>
                                                <div class="fw-bold text-info">
                                                    <?php echo $ride['total_seats_booked'] ?? 0; ?>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Prix</div>
                                                <div class="fw-bold text-primary">
                                                    <?php echo formatCurrency($ride['price_per_seat']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Statut et actions -->
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if (!$ride['is_active']): ?>
                                                    <span class="badge bg-danger status-badge">Inactif</span>
                                                <?php elseif (strtotime($ride['departure_date']) < strtotime('today')): ?>
                                                    <span class="badge bg-secondary status-badge">Terminé</span>
                                                <?php elseif ($ride['actual_available_seats'] == 0): ?>
                                                    <span class="badge bg-warning status-badge">Complet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success status-badge">Actif</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="../rides/ride_details.php?id=<?php echo $ride['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>Voir détails
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($ride['is_active']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <button type="submit" class="dropdown-item text-warning" 
                                                                        onclick="return confirm('Désactiver ce trajet ?')">
                                                                    <i class="fas fa-pause me-2"></i>Désactiver
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <button type="submit" class="dropdown-item text-success">
                                                                    <i class="fas fa-play me-2"></i>Activer
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce trajet ? Cette action est irréversible.')">
                                                                <i class="fas fa-trash me-2"></i>Supprimer
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="mailto:<?php echo htmlspecialchars($ride['email']); ?>">
                                                            <i class="fas fa-envelope me-2"></i>Contacter
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Informations supplémentaires -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            Départ: <?php echo htmlspecialchars($ride['departure_address']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt text-success me-1"></i>
                                            Arrivée: <?php echo htmlspecialchars($ride['arrival_address']); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Dates -->
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Créé le: <?php echo formatDate($ride['created_at'], true); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-sync-alt me-1"></i>
                                            Modifié le: <?php echo formatDate($ride['updated_at'], true); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>">
                                    Précédent
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>">
                                    Suivant
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-road fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucun trajet trouvé</h4>
                <p class="text-muted mb-4">
                    <?php if (!empty($search)): ?>
                        Aucun trajet ne correspond à votre recherche "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        Aucun trajet ne correspond aux critères sélectionnés
                    <?php endif; ?>
                </p>
                <?php if (!empty($search)): ?>
                    <a href="manage_rides.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Voir tous les trajets
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calcul des statistiques en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const rides = <?php echo json_encode($rides); ?>;
            
            let activeRides = 0;
            let upcomingRides = 0;
            let totalBookings = 0;
            
            rides.forEach(ride => {
                if (ride.is_active && new Date(ride.departure_date) >= new Date()) {
                    activeRides++;
                }
                if (new Date(ride.departure_date) >= new Date()) {
                    upcomingRides++;
                }
                totalBookings += parseInt(ride.total_seats_booked) || 0;
            });
            
            document.getElementById('activeRidesCount').textContent = activeRides;
            document.getElementById('upcomingRidesCount').textContent = upcomingRides;
            document.getElementById('totalBookingsCount').textContent = totalBookings;
        });
    </script>
</body>
</html>