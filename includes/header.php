<?php
// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir le chemin absolu vers la racine du projet
define('ROOT_PATH', dirname(dirname(__FILE__)));

// Inclure les fichiers de configuration nécessaires
require_once ROOT_PATH . '/config/app_settings.php';

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$user_name = $_SESSION['user_name'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Déterminer le lien du tableau de bord
$dashboard_link = $is_admin ? 'admin/admin_dashboard.php' : 'users/user_dashboard.php';

// Récupérer les notifications non lues si l'utilisateur est connecté
$unread_notifications = [];
$cart_count = 0;

if ($is_logged_in && $user_id) {
    try {
        require_once ROOT_PATH . '/config/db_connection.php';
        require_once ROOT_PATH . '/includes/functions.php';
        
        $db = new Database();
        $conn = $db->getConnection();
        
        // Récupérer les notifications non lues
        $unread_notifications = getUnreadNotifications($conn, $user_id, 5);
        
        // Récupérer le nombre d'éléments dans le panier
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_result = $stmt->fetch();
        $cart_count = $cart_result['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Erreur chargement header: " . $e->getMessage());
        // Continuer sans notifications/panier en cas d'erreur
    }
}

// Déterminer le titre de la page
$page_title = $page_title ?? APP_NAME;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Personnalisé -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico">
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo $site_config['description'] ?? 'Plateforme de covoiturage sécurisée pour la Tunisie'; ?>">
    <meta name="keywords" content="<?php echo $site_config['keywords'] ?? 'covoiturage, tunisie, voyage, économie, transport, écologie'; ?>">
    <meta name="author" content="<?php echo $site_config['author'] ?? 'Tunisia Carpool Team'; ?>">
    
    <!-- Open Graph Meta Tags pour les réseaux sociaux -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo $site_config['description'] ?? 'Plateforme de covoiturage sécurisée pour la Tunisie'; ?>">
    <meta property="og:image" content="<?php echo APP_URL; ?>/assets/images/og-image.jpg">
    <meta property="og:url" content="<?php echo APP_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    
    <!-- Styles supplémentaires pour le header -->
    <style>
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .badge-notification {
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }
        
        @media (max-width: 991.98px) {
            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .dropdown-menu {
                border-radius: 0;
                box-shadow: none;
                border: 1px solid rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation principale -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo APP_URL; ?>/index.php">
                <i class="fas fa-car-side fa-lg me-2"></i>
                <span class="d-none d-sm-inline"><?php echo APP_NAME; ?></span>
            </a>

            <!-- Bouton menu mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" 
                    aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Menu principal -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/index.php">
                            <i class="fas fa-home me-1"></i>Accueil
                        </a>
                    </li>
                    
                    <?php if ($is_logged_in): ?>
                        <!-- Tableau de bord -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_dashboard.php' || basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL . '/' . $dashboard_link; ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>
                                Tableau de bord
                                <?php if ($is_admin): ?>
                                    <span class="badge bg-warning badge-notification ms-1">Admin</span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                        <!-- Recherche de trajets -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'browse_rides.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/rides/browse_rides.php">
                                <i class="fas fa-search me-1"></i>Chercher un trajet
                            </a>
                        </li>
                        
                        <!-- Proposer un trajet -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_ride.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/rides/create_ride.php">
                                <i class="fas fa-plus-circle me-1"></i>Proposer un trajet
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Pages publiques pour visiteurs -->
                        <li class="nav-item">
                    <a class="nav-link" href="<?php echo APP_URL; ?>/index.php#popular-rides">
                        <i class="fas fa-eye me-1"></i>Voir les trajets
                    </a>
                </li>
                <li class="nav-item">
                 <a class="nav-link" href="<?php echo APP_URL; ?>/index.php#how-it-works">
                <i class="fas fa-question-circle me-1"></i>Comment ça marche ?
               </a>
                </li>
                    <?php endif; ?>
                </ul>

                <!-- Menu utilisateur -->
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_logged_in): ?>
                        <!-- Notifications -->
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationsDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if (!empty($unread_notifications)): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                                          style="font-size: 0.6rem; padding: 0.2rem 0.4rem;">
                                        <?php echo count($unread_notifications); ?>
                                        <span class="visually-hidden">Notifications non lues</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (!empty($unread_notifications)): ?>
                                    <?php foreach ($unread_notifications as $notification): ?>
                                        <li>
                                            <a class="dropdown-item d-flex align-items-start" href="#" 
                                               onclick="markNotificationAsRead(<?php echo $notification['id']; ?>)">
                                                <div class="me-2">
                                                    <?php 
                                                    $icon = '';
                                                    switch($notification['type']) {
                                                        case 'ride': $icon = 'fa-route'; break;
                                                        case 'booking': $icon = 'fa-ticket-alt'; break;
                                                        case 'system': $icon = 'fa-cog'; break;
                                                        default: $icon = 'fa-bell';
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="small">
                                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                                        <p class="mb-0 text-muted small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                        <small class="text-muted"><?php 
                                                            if (function_exists('formatDate')) {
                                                                echo formatDate($notification['created_at'], true);
                                                            } else {
                                                                echo date('d/m/Y H:i', strtotime($notification['created_at']));
                                                            }
                                                        ?></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                    <?php endforeach; ?>
                                    <li>
                                        <a class="dropdown-item text-center text-primary small fw-bold" 
                                           href="<?php echo APP_URL; ?>/users/notifications.php">
                                            Voir toutes les notifications
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <a class="dropdown-item text-center text-muted py-3" href="#">
                                            <i class="fas fa-bell-slash fa-lg mb-2"></i>
                                            <div>Aucune notification</div>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <!-- Panier -->
                        <li class="nav-item">
                            <a class="nav-link position-relative <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/booking/cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <?php if ($cart_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" 
                                          style="font-size: 0.6rem; padding: 0.2rem 0.4rem;">
                                        <?php echo $cart_count; ?>
                                        <span class="visually-hidden">Articles dans le panier</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <!-- Menu utilisateur déroulant -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="me-2">
                                    <i class="fas fa-user-circle fa-lg"></i>
                                </div>
                                <div class="d-none d-lg-block">
                                    <div class="small"><?php echo htmlspecialchars($first_name); ?></div>
                                    <div class="xsmall text-light opacity-75">
                                        <?php echo $is_admin ? 'Administrateur' : 'Membre'; ?>
                                    </div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                <li>
                                    <div class="dropdown-header">
                                        <div class="fw-bold"><?php echo htmlspecialchars($user_name); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL . '/' . $dashboard_link; ?>">
                                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>Tableau de bord
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/profile.php">
                                        <i class="fas fa-user-circle me-2 text-primary"></i>Mon profil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/rides/my_rides.php">
                                        <i class="fas fa-route me-2 text-success"></i>Mes trajets
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/booking/my_bookings.php">
                                        <i class="fas fa-ticket-alt me-2 text-info"></i>Mes réservations
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <?php if ($is_admin): ?>
                                    <li class="dropdown-header text-warning">
                                        <i class="fas fa-shield-alt me-1"></i>Administration
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/admin_dashboard.php">
                                            <i class="fas fa-tachometer-alt me-2"></i>Tableau de bord
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/manage_users.php">
                                            <i class="fas fa-users me-2"></i>Gérer utilisateurs
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/manage_rides.php">
                                            <i class="fas fa-route me-2"></i>Gérer trajets
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/admin_reports.php">
                                            <i class="fas fa-chart-bar me-2"></i>Rapports
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/users/edit_profile.php">
                                        <i class="fas fa-cog me-2 text-secondary"></i>Paramètres
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/auth/logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Menu visiteur -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/auth/login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Connexion
                            </a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-outline-light btn-sm px-3" href="<?php echo APP_URL; ?>/auth/register.php">
                                <i class="fas fa-user-plus me-1"></i>Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Messages flash -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0 rounded-0 border-0 shadow-sm" role="alert">
            <div class="container">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-lg me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['success']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0 rounded-0 border-0 shadow-sm" role="alert">
            <div class="container">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['error']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show m-0 rounded-0 border-0 shadow-sm" role="alert">
            <div class="container">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-lg me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['warning']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show m-0 rounded-0 border-0 shadow-sm" role="alert">
            <div class="container">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-lg me-3"></i>
                    <div class="flex-grow-1">
                        <?php echo $_SESSION['info']; ?>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <!-- Contenu principal -->
    <main>