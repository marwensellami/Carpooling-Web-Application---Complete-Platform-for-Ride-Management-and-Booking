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
$filter = $_GET['filter'] ?? 'all'; // all, unread, read, booking, ride, system
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Construire la requête
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$_SESSION['user_id']];
    
    if ($filter !== 'all') {
        if ($filter === 'unread') {
            $sql .= " AND is_read = 0";
        } elseif ($filter === 'read') {
            $sql .= " AND is_read = 1";
        } elseif (in_array($filter, ['booking', 'ride', 'system', 'alert'])) {
            $sql .= " AND type = ?";
            $params[] = $filter;
        }
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    // Comptage total
    $count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $count_params = [$_SESSION['user_id']];
    
    if ($filter !== 'all') {
        if ($filter === 'unread') {
            $count_sql .= " AND is_read = 0";
        } elseif ($filter === 'read') {
            $count_sql .= " AND is_read = 1";
        } elseif (in_array($filter, ['booking', 'ride', 'system', 'alert'])) {
            $count_sql .= " AND type = ?";
            $count_params[] = $filter;
        }
    }
    
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($count_params);
    $total_notifications = $stmt->fetch()['total'];
    $total_pages = ceil($total_notifications / $per_page);
    
    // Requête paginée
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'mark_all_read') {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['success'] = "Toutes les notifications marquées comme lues";
            redirect('notifications.php');
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur notifications: " . $e->getMessage());
    $error = "Erreur lors du chargement des notifications: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Notifications - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notifications-header {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: #212529;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .notification-item {
            border-left: 4px solid;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .notification-item.unread {
            border-left-color: #007bff;
            background-color: #f0f8ff;
        }
        .notification-item.booking {
            border-left-color: #28a745;
        }
        .notification-item.ride {
            border-left-color: #17a2b8;
        }
        .notification-item.system {
            border-left-color: #6c757d;
        }
        .notification-item.alert {
            border-left-color: #dc3545;
        }
        .notification-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .badge-notification {
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Header -->
    <div class="notifications-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-bell me-2"></i>Mes Notifications
                    </h1>
                    <p class="lead mb-0">Restez informé de toutes vos activités</p>
                </div>
                <div class="col-md-4 text-end">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-dark">
                            <i class="fas fa-check-double me-1"></i>Tout marquer comme lu
                        </button>
                    </form>
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

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-bold me-2">Filtrer :</span>
                    <a href="?filter=all" class="badge bg-light text-dark text-decoration-none <?php echo $filter === 'all' ? 'border border-primary' : ''; ?>">
                        Toutes <span class="badge bg-secondary"><?php echo $total_notifications; ?></span>
                    </a>
                    <a href="?filter=unread" class="badge bg-light text-dark text-decoration-none <?php echo $filter === 'unread' ? 'border border-primary' : ''; ?>">
                        Non lues <span class="badge bg-primary" id="unreadCount"><?php echo getUnreadNotifications($conn, $_SESSION['user_id'], 0, true); ?></span>
                    </a>
                    <a href="?filter=booking" class="badge bg-light text-dark text-decoration-none <?php echo $filter === 'booking' ? 'border border-primary' : ''; ?>">
                        <i class="fas fa-ticket-alt me-1"></i>Réservations
                    </a>
                    <a href="?filter=ride" class="badge bg-light text-dark text-decoration-none <?php echo $filter === 'ride' ? 'border border-primary' : ''; ?>">
                        <i class="fas fa-route me-1"></i>Trajets
                    </a>
                    <a href="?filter=system" class="badge bg-light text-dark text-decoration-none <?php echo $filter === 'system' ? 'border border-primary' : ''; ?>">
                        <i class="fas fa-cog me-1"></i>Système
                    </a>
                </div>
            </div>
        </div>

        <!-- Liste des notifications -->
        <?php if ($notifications): ?>
            <div class="row">
                <div class="col-12">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['type']; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary badge-notification me-2">Nouveau</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <span class="badge bg-secondary badge-notification">
                                            <?php 
                                            switch ($notification['type']) {
                                                case 'booking': echo 'Réservation'; break;
                                                case 'ride': echo 'Trajet'; break;
                                                case 'system': echo 'Système'; break;
                                                case 'alert': echo 'Alerte'; break;
                                                default: echo $notification['type'];
                                            }
                                            ?>
                                        </span>
                                    </h5>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    <div class="notification-date">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo formatDate($notification['created_at'], true); ?>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" action="../includes/notifications.php" class="d-inline">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">Aucune notification</h4>
                <p class="text-muted">
                    <?php if ($filter === 'unread'): ?>
                        Vous n'avez pas de nouvelles notifications.
                    <?php elseif ($filter !== 'all'): ?>
                        Aucune notification ne correspond à ce filtre.
                    <?php else: ?>
                        Vous n'avez pas encore de notifications.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mettre à jour le nombre de notifications non lues
        function updateUnreadCount() {
            fetch('../includes/notifications.php?action=get_unread_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('unreadCount').textContent = data.count;
                        
                        // Mettre à jour le badge dans le header
                        const headerBadge = document.querySelector('.badge-notification');
                        if (headerBadge) {
                            if (data.count > 0) {
                                headerBadge.textContent = data.count;
                                headerBadge.style.display = 'inline-block';
                            } else {
                                headerBadge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Rafraîchir le compteur toutes les 30 secondes
        setInterval(updateUnreadCount, 30000);

        // Initialiser
        document.addEventListener('DOMContentLoaded', updateUnreadCount);
    </script>
</body>
</html>