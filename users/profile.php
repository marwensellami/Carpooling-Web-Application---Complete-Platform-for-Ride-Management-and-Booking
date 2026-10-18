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
    
    // Récupérer les informations de l'utilisateur
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT r.id) as total_rides,
               COUNT(DISTINCT b.id) as total_bookings,
               AVG(rev.rating) as avg_rating
        FROM users u
        LEFT JOIN rides r ON u.id = r.driver_id
        LEFT JOIN bookings b ON u.id = b.passenger_id AND b.status = 'confirmed'
        LEFT JOIN reviews rev ON rev.reviewed_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }
    
    // Récupérer le véhicule de l'utilisateur s'il en a un
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicle = $stmt->fetch();
    
    // Récupérer les avis récents
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name, rev.rating, rev.comment, rev.created_at
        FROM reviews rev
        JOIN users u ON rev.reviewer_id = u.id
        JOIN bookings b ON rev.booking_id = b.id
        JOIN rides r ON b.ride_id = r.id
        WHERE rev.reviewed_id = ?
        ORDER BY rev.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reviews = $stmt->fetchAll();
    
    // Récupérer les statistiques détaillées
    $user_stats = getUserStats($conn, $_SESSION['user_id']);
    
} catch (Exception $e) {
    error_log("Erreur profil: " . $e->getMessage());
    $error = "Erreur lors du chargement du profil: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
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
        .rating-stars {
            color: #ffc107;
        }
        .badge-premium {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="profile-avatar mx-auto">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="h2 mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p class="lead mb-1">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        Membre depuis <?php echo formatDate($user['created_at']); ?>
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($user['avg_rating']): ?>
                            <div class="rating-stars">
                                <?php echo generateStarRating($user['avg_rating']); ?>
                                <span class="ms-2"><?php echo number_format($user['avg_rating'], 1); ?>/5</span>
                            </div>
                        <?php else: ?>
                            <span class="text-light opacity-75">Aucun avis pour le moment</span>
                        <?php endif; ?>
                        
                        <?php if ($user_stats['rides_published'] >= 10): ?>
                            <span class="badge badge-premium px-3 py-2">
                                <i class="fas fa-crown me-1"></i>Conducteur Expérimenté
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <a href="edit_profile.php" class="btn btn-light btn-sm">
                        <i class="fas fa-edit me-1"></i>Modifier
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Colonne de gauche - Informations personnelles -->
            <div class="col-lg-4 mb-4">
                <div class="card stat-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-circle me-2"></i>Informations Personnelles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong><i class="fas fa-envelope me-2 text-muted"></i>Email</strong>
                            <p class="mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-phone me-2 text-muted"></i>Téléphone</strong>
                            <p class="mb-1"><?php echo htmlspecialchars($user['phone']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-calendar me-2 text-muted"></i>Membre depuis</strong>
                            <p class="mb-1"><?php echo formatDate($user['created_at'], true); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-sync-alt me-2 text-muted"></i>Dernière mise à jour</strong>
                            <p class="mb-0"><?php echo formatDate($user['updated_at'], true); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Véhicule -->
                <?php if ($vehicle): ?>
                <div class="card stat-card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car me-2"></i>Mon Véhicule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-car fa-3x text-success"></i>
                        </div>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></strong></p>
                        <p class="mb-1 text-muted small">Année: <?php echo $vehicle['year']; ?></p>
                        <p class="mb-1 text-muted small">Couleur: <?php echo htmlspecialchars($vehicle['color']); ?></p>
                        <p class="mb-1 text-muted small">Plaque: <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                        <p class="mb-0">
                            <?php if ($vehicle['has_air_conditioning']): ?>
                                <span class="badge bg-info"><i class="fas fa-snowflake me-1"></i>Climatisation</span>
                            <?php endif; ?>
                            <span class="badge bg-secondary"><?php echo $vehicle['max_passengers']; ?> places max</span>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <div class="card stat-card mt-4">
                    <div class="card-body text-center">
                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                        <h6>Aucun véhicule enregistré</h6>
                        <p class="text-muted small mb-3">Ajoutez un véhicule pour publier des trajets</p>
                        <a href="edit_profile.php?tab=vehicle" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>Ajouter un véhicule
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonne de droite - Statistiques et activité -->
            <div class="col-lg-8">
                <!-- Cartes de statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center bg-primary text-white">
                            <div class="card-body">
                                <i class="fas fa-route fa-2x mb-2"></i>
                                <h4 class="mb-1"><?php echo $user_stats['rides_published']; ?></h4>
                                <p class="mb-0 small">Trajets publiés</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center bg-success text-white">
                            <div class="card-body">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                <h4 class="mb-1"><?php echo $user_stats['upcoming_rides']; ?></h4>
                                <p class="mb-0 small">Trajets à venir</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center bg-info text-white">
                            <div class="card-body">
                                <i class="fas fa-user-plus fa-2x mb-2"></i>
                                <h4 class="mb-1"><?php echo $user_stats['bookings_received']; ?></h4>
                                <p class="mb-0 small">Réservations reçues</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center bg-warning text-dark">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4 class="mb-1"><?php echo $user_stats['bookings_made']; ?></h4>
                                <p class="mb-0 small">Trajets réservés</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avis récents -->
                <div class="card stat-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-star me-2"></i>Avis Reçus
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($reviews): ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                            <span class="text-muted small ms-2">
                                                pour le trajet <?php echo htmlspecialchars($review['departure_city'] . ' → ' . $review['arrival_city']); ?>
                                            </span>
                                        </div>
                                        <div class="rating-stars">
                                            <?php echo generateStarRating($review['rating']); ?>
                                        </div>
                                    </div>
                                    <?php if ($review['comment']): ?>
                                        <p class="mb-1">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                                    <?php endif; ?>
                                    <small class="text-muted"><?php echo formatDate($review['created_at'], true); ?></small>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-3">
                                <a href="#" class="btn btn-outline-warning btn-sm">
                                    Voir tous les avis
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-star fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun avis pour le moment</p>
                                <p class="small text-muted">Les avis apparaîtront ici après vos premiers trajets</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card stat-card mt-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Actions Rapides
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <a href="../rides/create_ride.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                                    Publier un trajet
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="../rides/browse_rides.php" class="btn btn-outline-success btn-lg w-100 py-3">
                                    <i class="fas fa-search fa-2x mb-2"></i><br>
                                    Chercher un trajet
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="user_dashboard.php" class="btn btn-outline-info btn-lg w-100 py-3">
                                    <i class="fas fa-tachometer-alt fa-2x mb-2"></i><br>
                                    Tableau de bord
                                </a>
                            </div>
                        </div>
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