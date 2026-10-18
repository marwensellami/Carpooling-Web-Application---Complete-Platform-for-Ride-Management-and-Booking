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

$filter = $_GET['filter'] ?? 'all'; // all, upcoming, past, active

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête selon le filtre
    $sql = "
        SELECT r.*, v.brand, v.model, v.color,
               COUNT(b.id) as confirmed_bookings,
               SUM(CASE WHEN b.status = 'confirmed' THEN b.seats_booked ELSE 0 END) as total_seats_booked
        FROM rides r
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id
        WHERE r.driver_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    switch ($filter) {
        case 'upcoming':
            $sql .= " AND r.departure_date >= CURDATE() AND r.is_active = 1";
            break;
        case 'past':
            $sql .= " AND r.departure_date < CURDATE()";
            break;
        case 'active':
            $sql .= " AND r.is_active = 1 AND r.departure_date >= CURDATE()";
            break;
        case 'inactive':
            $sql .= " AND r.is_active = 0";
            break;
        default: // all
            $sql .= " AND r.departure_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
    
    $sql .= " GROUP BY r.id ORDER BY r.departure_date DESC, r.departure_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rides = $stmt->fetchAll();
    
    // Statistiques pour les filtres
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN departure_date >= CURDATE() AND is_active = 1 THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN departure_date < CURDATE() THEN 1 ELSE 0 END) as past,
            SUM(CASE WHEN is_active = 1 AND departure_date >= CURDATE() THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
        FROM rides 
        WHERE driver_id = ?
    ");
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Erreur mes trajets: " . $e->getMessage());
    $error = "Erreur lors du chargement des trajets: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Trajets - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rides-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: #667eea !important;
            color: white;
        }
        .status-badge {
            font-size: 0.75rem;
        }
        .route-icon {
            width: 40px;
            height: 40px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* Style amélioré pour les dropdowns - CORRECTION IMPORTANTE */
        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }
        
        .dropdown-menu {
            min-width: 200px;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            color: #212529;
            text-decoration: none;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        /* Assurer que les dropdowns s'affichent */
        .dropdown.show .dropdown-menu {
            display: block;
        }
        
        /* Pour les tests */
        #debug-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="rides-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-list me-2"></i>Mes Trajets Publiés
                    </h1>
                    <p class="lead mb-0">Gérez tous vos trajets en un seul endroit</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="create_ride.php" class="btn btn-light">
                        <i class="fas fa-plus me-1"></i>Nouveau trajet
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
                        Tous <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=upcoming'">
                        À venir <span class="badge bg-success"><?php echo $stats['upcoming']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'active' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=active'">
                        Actifs <span class="badge bg-primary"><?php echo $stats['active']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'past' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=past'">
                        Passés <span class="badge bg-secondary"><?php echo $stats['past']; ?></span>
                    </span>
                    <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'inactive' ? 'active' : ''; ?>" 
                          onclick="window.location.href='?filter=inactive'">
                        Inactifs <span class="badge bg-warning"><?php echo $stats['inactive']; ?></span>
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="text-muted small">
                    <?php echo count($rides); ?> trajet(s) trouvé(s)
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
                                    <!-- Icône et route -->
                                    <div class="col-md-1 text-center">
                                        <div class="route-icon">
                                            <i class="fas fa-car"></i>
                                        </div>
                                    </div>
                                    
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
                                                <p class="text-muted mb-0 small">
                                                    <i class="fas fa-car me-1"></i>
                                                    <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?> • 
                                                    <?php echo htmlspecialchars($ride['color']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Statistiques -->
                                    <div class="col-md-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="small text-muted">Places</div>
                                                <div class="fw-bold <?php echo $ride['available_seats'] == 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo $ride['available_seats']; ?>
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
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if (!$ride['is_active']): ?>
                                                    <span class="badge bg-warning status-badge">Inactif</span>
                                                <?php elseif (strtotime($ride['departure_date']) < strtotime('today')): ?>
                                                    <span class="badge bg-secondary status-badge">Terminé</span>
                                                <?php elseif ($ride['available_seats'] == 0): ?>
                                                    <span class="badge bg-danger status-badge">Complet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success status-badge">Actif</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" 
                                                        id="dropdownMenuButton-<?php echo $ride['id']; ?>" 
                                                        data-bs-toggle="dropdown" 
                                                        aria-expanded="false"
                                                        aria-haspopup="true">
                                                    <i class="fas fa-cog me-1"></i>Actions
                                                </button>
                                                <ul class="dropdown-menu" 
                                                    aria-labelledby="dropdownMenuButton-<?php echo $ride['id']; ?>">
                                                    <li>
                                                        <a class="dropdown-item" href="../rides/ride_details.php?id=<?php echo $ride['id']; ?>" target="_blank">
                                                            <i class="fas fa-eye me-2 text-primary"></i>Voir détails
                                                        </a>
                                                    </li>
                                                    <?php if ($ride['is_active'] && strtotime($ride['departure_date']) >= strtotime('today')): ?>
    <li>
        <a class="dropdown-item" href="edit_ride.php?id=<?php echo $ride['id']; ?>">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
    </li>
    <li>
        <a class="dropdown-item text-warning" href="deactivate_ride.php?id=<?php echo $ride['id']; ?>">
            <i class="fas fa-power-off me-2"></i>Désactiver
        </a>
    </li>
    <li>
        <a class="dropdown-item text-danger" href="delete_ride.php?id=<?php echo $ride['id']; ?>" 
           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce trajet ?')">
            <i class="fas fa-trash me-2"></i>Supprimer
        </a>
    </li>
<?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="../booking/driver_bookings.php?ride_id=<?php echo $ride['id']; ?>">
                                                            <i class="fas fa-users me-2"></i>Voir réservations
                                                        </a>
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
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-road fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucun trajet trouvé</h4>
                <p class="text-muted mb-4">
                    <?php if ($filter === 'upcoming'): ?>
                        Vous n'avez pas de trajets à venir.
                    <?php elseif ($filter === 'past'): ?>
                        Vous n'avez pas de trajets passés.
                    <?php elseif ($filter === 'active'): ?>
                        Vous n'avez pas de trajets actifs.
                    <?php elseif ($filter === 'inactive'): ?>
                        Vous n'avez pas de trajets inactifs.
                    <?php else: ?>
                        Vous n'avez pas encore publié de trajets.
                    <?php endif; ?>
                </p>
                <a href="create_ride.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Publier votre premier trajet
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Scripts Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script de debug -->
    <div id="debug-info" style="display: none;"></div>
    
    <script>
        // Fonction de confirmation pour la suppression
        function confirmDelete(rideId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce trajet ? Cette action est irréversible.')) {
                window.location.href = 'delete_ride.php?id=' + rideId;
            }
        }
        
        // Debug et test des dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page chargée - Test des dropdowns');
            
            // Vérifier si Bootstrap est chargé
            if (typeof bootstrap !== 'undefined') {
                console.log('✓ Bootstrap JS est chargé');
            } else {
                console.error('✗ Bootstrap JS n\'est pas chargé');
            }
            
            // Vérifier tous les dropdowns
            var dropdowns = document.querySelectorAll('.dropdown-toggle');
            console.log('Nombre de dropdowns trouvés:', dropdowns.length);
            
            // Ajouter un événement de clic pour test
            dropdowns.forEach(function(dropdown, index) {
                dropdown.addEventListener('click', function(e) {
                    console.log('Dropdown cliqué:', index);
                    e.stopPropagation(); // Empêcher la propagation
                });
            });
            
            // Tester manuellement l'affichage d'un dropdown
            if (dropdowns.length > 0) {
                var testButton = dropdowns[0];
                testButton.addEventListener('click', function() {
                    var menu = this.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        console.log('Menu trouvé, toggle display');
                        menu.classList.toggle('show');
                    }
                });
            }
            
            // Forcer l'affichage de tous les dropdowns au clic (fallback)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-toggle')) {
                    var menu = e.target.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        // Fermer tous les autres menus
                        document.querySelectorAll('.dropdown-menu.show').forEach(function(otherMenu) {
                            if (otherMenu !== menu) {
                                otherMenu.classList.remove('show');
                            }
                        });
                        // Ouvrir le menu actuel
                        menu.classList.toggle('show');
                    }
                } else {
                    // Fermer tous les menus si on clique ailleurs
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                        menu.classList.remove('show');
                    });
                }
            });
        });
        
        // Version alternative plus simple
        document.addEventListener('DOMContentLoaded', function() {
            // Initialisation manuelle des dropdowns si Bootstrap ne fonctionne pas
            var dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            
            dropdownToggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var menu = this.nextElementSibling;
                    if (menu && menu.classList.contains('dropdown-menu')) {
                        // Fermer tous les autres menus
                        document.querySelectorAll('.dropdown-menu').forEach(function(otherMenu) {
                            if (otherMenu !== menu) {
                                otherMenu.style.display = 'none';
                            }
                        });
                        
                        // Basculer l'affichage du menu actuel
                        if (menu.style.display === 'block') {
                            menu.style.display = 'none';
                        } else {
                            menu.style.display = 'block';
                        }
                    }
                });
            });
            
            // Fermer les menus quand on clique ailleurs
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                    menu.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>