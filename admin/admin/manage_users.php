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

// Paramètres de filtrage
$filter = $_GET['filter'] ?? 'all'; // all, active, inactive, admin
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête avec filtres
    $sql = "
        SELECT u.*,
               COUNT(DISTINCT r.id) as rides_count,
               COUNT(DISTINCT b.id) as bookings_count,
               (SELECT COUNT(*) FROM reviews WHERE reviewed_id = u.id) as reviews_count,
               (SELECT AVG(rating) FROM reviews WHERE reviewed_id = u.id) as avg_rating
        FROM users u
        LEFT JOIN rides r ON u.id = r.driver_id
        LEFT JOIN bookings b ON u.id = b.passenger_id
    ";
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    switch ($filter) {
        case 'active':
            $where_conditions[] = "u.is_active = 1";
            break;
        case 'inactive':
            $where_conditions[] = "u.is_active = 0";
            break;
        case 'admin':
            $where_conditions[] = "u.is_admin = 1";
            break;
        default: // all
            break;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
    
    // Requête pour le comptage total
    $count_sql = "SELECT COUNT(*) as total FROM users u";
    if (!empty($where_conditions)) {
        $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];
    $total_pages = ceil($total_users / $per_page);
    
    // Requête paginée
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Actions admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id && in_array($action, ['activate', 'deactivate', 'make_admin', 'remove_admin', 'delete'])) {
            try {
                $conn->beginTransaction();
                
                // Vérifier que l'utilisateur existe et n'est pas l'admin actuel
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    throw new Exception("Utilisateur non trouvé");
                }
                
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception("Vous ne pouvez pas modifier votre propre compte");
                }
                
                switch ($action) {
                    case 'activate':
                        $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $_SESSION['success'] = "Utilisateur activé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'user_activated_admin', "Utilisateur #$user_id activé par admin");
                        break;
                        
                    case 'deactivate':
                        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $_SESSION['success'] = "Utilisateur désactivé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'user_deactivated_admin', "Utilisateur #$user_id désactivé par admin");
                        break;
                        
                    case 'make_admin':
                        $stmt = $conn->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $_SESSION['success'] = "Droits administrateur accordés avec succès";
                        logAction($conn, $_SESSION['user_id'], 'user_made_admin', "Utilisateur #$user_id promu admin");
                        break;
                        
                    case 'remove_admin':
                        $stmt = $conn->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $_SESSION['success'] = "Droits administrateur retirés avec succès";
                        logAction($conn, $_SESSION['user_id'], 'user_removed_admin', "Utilisateur #$user_id retiré admin");
                        break;
                        
                    case 'delete':
                        // Vérifier s'il y a des trajets ou réservations actifs
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) as count FROM rides 
                            WHERE driver_id = ? AND departure_date >= CURDATE() AND is_active = 1
                        ");
                        $stmt->execute([$user_id]);
                        $active_rides = $stmt->fetch()['count'];
                        
                        $stmt = $conn->prepare("
                            SELECT COUNT(*) as count FROM bookings b
                            JOIN rides r ON b.ride_id = r.id
                            WHERE b.passenger_id = ? AND r.departure_date >= CURDATE() AND b.status = 'confirmed'
                        ");
                        $stmt->execute([$user_id]);
                        $active_bookings = $stmt->fetch()['count'];
                        
                        if ($active_rides > 0 || $active_bookings > 0) {
                            throw new Exception("Impossible de supprimer un utilisateur avec des trajets ou réservations actifs");
                        }
                        
                        // Désactiver l'utilisateur au lieu de le supprimer (pour garder l'historique)
                        $stmt = $conn->prepare("UPDATE users SET is_active = 0, email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        $_SESSION['success'] = "Utilisateur désactivé avec succès";
                        logAction($conn, $_SESSION['user_id'], 'user_deleted_admin', "Utilisateur #$user_id désactivé par admin");
                        break;
                }
                
                $conn->commit();
                
                // Rediriger pour éviter la resoumission du formulaire
                redirect('manage_users.php?' . $_SERVER['QUERY_STRING']);
                
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = "Erreur: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur gestion utilisateurs admin: " . $e->getMessage());
    $error = "Erreur lors du chargement: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .user-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
        }
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-badge.active {
            background: #007bff !important;
            color: white;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .stats-badge {
            font-size: 0.7rem;
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
                        <i class="fas fa-users me-2"></i>Gestion des Utilisateurs
                    </h1>
                    <p class="lead mb-0">Gestion complète des comptes utilisateurs</p>
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
                            <div class="input-group" style="max-width: 400px;">
                                <input type="text" class="form-control" name="search" placeholder="Rechercher par nom, email ou téléphone..." value="<?php echo htmlspecialchars($search); ?>">
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
                            <span class="badge filter-badge bg-light text-dark <?php echo $filter === 'admin' ? 'active' : ''; ?>" 
                                  onclick="window.location.href='?filter=admin&search=<?php echo urlencode($search); ?>'">
                                Administrateurs
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
                        <h5 class="card-title text-primary"><?php echo $total_users; ?></h5>
                        <p class="card-text text-muted">Utilisateurs total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-success" id="activeUsersCount">-</h5>
                        <p class="card-text text-muted">Utilisateurs actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-warning" id="adminUsersCount">-</h5>
                        <p class="card-text text-muted">Administrateurs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h5 class="card-title text-info" id="newUsersCount">-</h5>
                        <p class="card-text text-muted">Nouveaux (7j)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des utilisateurs -->
        <?php if ($users): ?>
            <div class="row">
                <?php foreach ($users as $user): ?>
                    <div class="col-12 mb-3">
                        <div class="card user-card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Avatar et informations de base -->
                                    <div class="col-md-2 text-center">
                                        <div class="user-avatar mx-auto mb-2">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="badge bg-warning stats-badge">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Informations personnelles -->
                                    <div class="col-md-4">
                                        <h5 class="card-title mb-1">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </h5>
                                        <p class="text-muted mb-1 small">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </p>
                                        <p class="text-muted mb-1 small">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($user['phone']); ?>
                                        </p>
                                        <p class="text-muted mb-0 small">
                                            <i class="fas fa-calendar me-1"></i>
                                            Inscrit le <?php echo formatDate($user['created_at']); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Statistiques -->
                                    <div class="col-md-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="small text-muted">Trajets</div>
                                                <div class="fw-bold text-primary">
                                                    <?php echo $user['rides_count']; ?>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Réservations</div>
                                                <div class="fw-bold text-success">
                                                    <?php echo $user['bookings_count']; ?>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">Avis</div>
                                                <div class="fw-bold text-info">
                                                    <?php echo $user['reviews_count']; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($user['avg_rating']): ?>
                                            <div class="text-center mt-2">
                                                <div class="rating-stars small">
                                                    <?php echo generateStarRating($user['avg_rating']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo number_format($user['avg_rating'], 1); ?>/5</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Statut et actions -->
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactif</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="../../users/profile.php?user_id=<?php echo $user['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>Voir profil
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                            <i class="fas fa-envelope me-2"></i>Contacter
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    
                                                    <?php if ($user['is_active']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <button type="submit" class="dropdown-item text-warning" 
                                                                        onclick="return confirm('Désactiver cet utilisateur ?')">
                                                                    <i class="fas fa-user-slash me-2"></i>Désactiver
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="activate">
                                                                <button type="submit" class="dropdown-item text-success">
                                                                    <i class="fas fa-user-check me-2"></i>Activer
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$user['is_admin']): ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="make_admin">
                                                                <button type="submit" class="dropdown-item text-info" 
                                                                        onclick="return confirm('Accorder les droits administrateur à cet utilisateur ?')">
                                                                    <i class="fas fa-shield-alt me-2"></i>Rendre Admin
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="remove_admin">
                                                                <button type="submit" class="dropdown-item text-warning" 
                                                                        onclick="return confirm('Retirer les droits administrateur à cet utilisateur ?')">
                                                                    <i class="fas fa-user me-2"></i>Retirer Admin
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="dropdown-item text-danger" 
                                                                    onclick="return confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur ?')">
                                                                <i class="fas fa-trash me-2"></i>Supprimer
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
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
                <i class="fas fa-users fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucun utilisateur trouvé</h4>
                <p class="text-muted mb-4">
                    <?php if (!empty($search)): ?>
                        Aucun utilisateur ne correspond à votre recherche "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        Aucun utilisateur ne correspond aux critères sélectionnés
                    <?php endif; ?>
                </p>
                <?php if (!empty($search)): ?>
                    <a href="manage_users.php" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Voir tous les utilisateurs
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calcul des statistiques en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const users = <?php echo json_encode($users); ?>;
            
            let activeUsers = 0;
            let adminUsers = 0;
            let newUsers = 0;
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
            
            users.forEach(user => {
                if (user.is_active) {
                    activeUsers++;
                }
                if (user.is_admin) {
                    adminUsers++;
                }
                if (new Date(user.created_at) >= sevenDaysAgo) {
                    newUsers++;
                }
            });
            
            document.getElementById('activeUsersCount').textContent = activeUsers;
            document.getElementById('adminUsersCount').textContent = adminUsers;
            document.getElementById('newUsersCount').textContent = newUsers;
        });
    </script>
</body>
</html>