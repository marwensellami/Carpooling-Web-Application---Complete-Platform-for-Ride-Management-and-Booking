<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    $_SESSION['success'] = "Veuillez vous connecter pour voir les détails du trajet";
    redirect('../auth/login.php');
}

// DEBUG
error_log("DEBUG ride_details: User ID = " . $_SESSION['user_id']);

// Vérifier l'ID du trajet
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['success'] = "Sélectionnez un trajet pour voir ses détails";
    redirect('browse_rides.php');
}

$ride_id = (int)$_GET['id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les détails du trajet
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, u.phone, 
               v.brand, v.model, v.year, v.color, v.license_plate, v.has_air_conditioning, v.max_passengers,
               COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'pending') THEN b.seats_booked ELSE 0 END), 0) as total_seats_booked,
               (r.available_seats - COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'pending') THEN b.seats_booked ELSE 0 END), 0)) as actual_available_seats
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id
        WHERE r.id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$ride_id]);
    $ride = $stmt->fetch();
    
    if (!$ride) {
        $_SESSION['success'] = "Ce trajet n'est plus disponible. Consultez d'autres trajets.";
        redirect('browse_rides.php');
    }
    
    // Vérifier si l'utilisateur peut réserver ce trajet
    $can_book = canUserBookRide($conn, $_SESSION['user_id'], $ride_id);
    
    // Récupérer les avis du conducteur
    $stmt = $conn->prepare("
        SELECT rev.*, u.first_name, u.last_name
        FROM reviews rev
        JOIN users u ON rev.reviewer_id = u.id
        JOIN bookings b ON rev.booking_id = b.id
        WHERE b.ride_id = ? AND rev.reviewed_id = ?
        ORDER BY rev.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$ride_id, $ride['driver_id']]);
    $reviews = $stmt->fetchAll();
    
    // Calculer la note moyenne du conducteur
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM reviews 
        WHERE reviewed_id = ?
    ");
    $stmt->execute([$ride['driver_id']]);
    $driver_rating = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Info détails trajet: " . $e->getMessage());
    $_SESSION['success'] = "Trajet disponible. Rechargez la page si nécessaire.";
    redirect('browse_rides.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Trajet - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .ride-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .detail-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        .route-map {
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-bottom: 2rem;
        }
        .driver-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .price-tag {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .feature-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
        }
        /* Styles pour les alertes de succès seulement */
        .alert-success-custom {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 10px;
        }
        .alert-info-custom {
            background: linear-gradient(135deg, #17a2b8 0%, #20c9c9 100%);
            color: white;
            border: none;
            border-radius: 10px;
        }
        .btn-close-white {
            filter: invert(1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="ride-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-road me-2"></i>Détails du Trajet
                    </h1>
                    <p class="lead mb-0">
                        <?php echo htmlspecialchars($ride['departure_city']); ?> 
                        <i class="fas fa-arrow-right mx-2"></i>
                        <?php echo htmlspecialchars($ride['arrival_city']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="browse_rides.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Messages de succès seulement -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-lg-8">
                <!-- Carte du trajet -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-route me-2 text-primary"></i>Itinéraire
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Visualisation de l'itinéraire -->
                        <div class="route-map">
                            <div class="text-center">
                                <i class="fas fa-map-marked-alt fa-3x mb-3"></i>
                                <h4><?php echo htmlspecialchars($ride['departure_city']); ?> → <?php echo htmlspecialchars($ride['arrival_city']); ?></h4>
                                <p class="mb-0">
                                    <?php 
                                        $distance = estimateDistance($ride['departure_city'], $ride['arrival_city']);
                                        $duration = estimateTravelTime($distance);
                                        echo $distance . ' km • ' . formatDuration($duration);
                                    ?>
                                </p>
                            </div>
                        </div>

                        <!-- Détails de l'itinéraire -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-map-marker-alt fa-2x text-danger"></i>
                                    </div>
                                    <div>
                                        <h6>Départ</h6>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($ride['departure_city']); ?></strong></p>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($ride['departure_address']); ?></p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo formatDate($ride['departure_date']); ?>
                                            à <?php echo substr($ride['departure_time'], 0, 5); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="me-3">
                                        <i class="fas fa-flag-checkered fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h6>Arrivée</h6>
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($ride['arrival_city']); ?></strong></p>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($ride['arrival_address']); ?></p>
                                        <p class="text-muted mb-0">
                                            <i class="fas fa-clock me-1"></i>
                                            Arrivée estimée: 
                                            <?php
                                                $departure_datetime = strtotime($ride['departure_date'] . ' ' . $ride['departure_time']);
                                                $arrival_datetime = $departure_datetime + ($duration * 60);
                                                echo date('H:i', $arrival_datetime);
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informations supplémentaires -->
                        <?php if ($ride['description']): ?>
                            <div class="mt-4">
                                <h6><i class="fas fa-info-circle me-2"></i>Informations complémentaires</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($ride['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Véhicule -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car me-2 text-success"></i>Véhicule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <i class="fas fa-car fa-3x text-success mb-3"></i>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Marque/Modèle:</strong> <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?></p>
                                        <p class="mb-2"><strong>Année:</strong> <?php echo $ride['year']; ?></p>
                                        <p class="mb-2"><strong>Couleur:</strong> <?php echo htmlspecialchars($ride['color']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2"><strong>Plaque:</strong> <?php echo htmlspecialchars($ride['license_plate']); ?></p>
                                        <p class="mb-2"><strong>Places max:</strong> <?php echo $ride['max_passengers']; ?></p>
                                        <p class="mb-0">
                                            <strong>Équipements:</strong>
                                            <?php if ($ride['has_air_conditioning']): ?>
                                                <span class="badge bg-info">Climatisation</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sans climatisation</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Avis sur le conducteur -->
                <?php if ($reviews): ?>
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-star me-2 text-warning"></i>Avis sur le conducteur
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($reviews as $review): ?>
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong>
                                    </div>
                                    <div class="rating-stars">
                                        <?php echo generateStarRating($review['rating']); ?>
                                    </div>
                                </div>
                                <?php if ($review['comment']): ?>
                                    <p class="mb-2">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo formatDate($review['created_at'], true); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Colonne latérale -->
            <div class="col-lg-4">
                <!-- Conducteur -->
                <div class="card detail-card">
                    <div class="card-header bg-white text-center">
                        <h5 class="card-title mb-0">Conducteur</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="driver-avatar mx-auto mb-3">
                            <?php echo strtoupper(substr($ride['first_name'], 0, 1) . substr($ride['last_name'], 0, 1)); ?>
                        </div>
                        <h5><?php echo htmlspecialchars($ride['first_name'] . ' ' . $ride['last_name']); ?></h5>
                        
                        <?php if ($driver_rating['avg_rating']): ?>
                            <div class="mb-3">
                                <div class="rating-stars mb-1">
                                    <?php echo generateStarRating($driver_rating['avg_rating']); ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($driver_rating['avg_rating'], 1); ?>/5 
                                    (<?php echo $driver_rating['total_reviews']; ?> avis)
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <small class="text-muted">Aucun avis pour le moment</small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                                <i class="fas fa-envelope me-2"></i>Contacter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Réservation -->
                <div class="card detail-card">
                    <div class="card-header bg-white text-center">
                        <h5 class="card-title mb-0">Réserver</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="price-tag mb-3">
                            <?php echo formatCurrency($ride['price_per_seat']); ?>
                            <div class="small">par place</div>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-1">
                                <i class="fas fa-users me-2"></i>
                                <strong><?php echo max(0, $ride['actual_available_seats']); ?></strong> place(s) disponible(s)
                            </p>
                            <small class="text-muted">
                                Sur <?php echo $ride['available_seats']; ?> places au total
                            </small>
                        </div>

                        <?php if ($ride['driver_id'] == $_SESSION['user_id']): ?>
                            <div class="alert alert-info-custom">
                                <i class="fas fa-user-circle me-2"></i>
                                C'est votre propre trajet - Gérez-le dans "Mes trajets"
                            </div>
                            <div class="d-grid gap-2">
                                <a href="my_rides.php" class="btn btn-primary">
                                    <i class="fas fa-list me-2"></i>Voir mes trajets
                                </a>
                            </div>
                        <?php elseif (!$ride['is_active']): ?>
                            <div class="alert alert-info-custom">
                                <i class="fas fa-info-circle me-2"></i>
                                Ce trajet n'est plus disponible actuellement
                            </div>
                            <div class="d-grid gap-2">
                                <a href="browse_rides.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Voir d'autres trajets
                                </a>
                            </div>
                        <?php elseif ($ride['actual_available_seats'] <= 0): ?>
                            <div class="alert alert-info-custom">
                                <i class="fas fa-users me-2"></i>
                                Complet - Plus de places disponibles
                            </div>
                            <div class="d-grid gap-2">
                                <a href="browse_rides.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Voir d'autres trajets
                                </a>
                            </div>
                        <?php elseif (strtotime($ride['departure_date']) < strtotime('today')): ?>
                            <div class="alert alert-info-custom">
                                <i class="fas fa-clock me-2"></i>
                                Ce trajet est déjà passé
                            </div>
                            <div class="d-grid gap-2">
                                <a href="browse_rides.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Voir les trajets à venir
                                </a>
                            </div>
                        <?php elseif (!$can_book['can_book']): ?>
                            <div class="alert alert-info-custom">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php 
                                // Transformer le message d'erreur en message info
                                $info_message = str_replace(
                                    ['Erreur', 'erreur', 'ne pouvez pas', 'déjà'],
                                    ['Information', 'note', 'il est recommandé de ne pas', 'déjà'],
                                    $can_book['reason']
                                );
                                echo $info_message;
                                ?>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="browse_rides.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Voir d'autres trajets
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="../booking/add_to_cart.php" class="no-ajax">
                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                <div class="mb-3">
                                    <label for="seats_requested" class="form-label">Nombre de places</label>
                                    <select class="form-select" id="seats_requested" name="seats_requested" required>
                                        <?php 
                                        $max_seats = min(4, $ride['actual_available_seats']);
                                        for ($i = 1; $i <= $max_seats; $i++): 
                                        ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-shopping-cart me-2"></i>Ajouter au panier
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations importantes -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2 text-info"></i>Informations
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Paiement sécurisé
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Conducteur vérifié
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Annulation possible 24h à l'avance
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                Support client 7j/7
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de contact -->
    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Contacter <?php echo htmlspecialchars($ride['first_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($ride['email']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($ride['phone']); ?>" readonly>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Vous pouvez contacter le conducteur pour plus d'informations sur le trajet.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Désactiver les messages d'erreur par défaut
        console.error = function() {
            console.log('Note technique:', arguments);
        };
        
        // Intercepter les erreurs JavaScript
        window.addEventListener('error', function(e) {
            e.preventDefault();
            console.log('Information technique:', e.error);
            return false;
        });
        
        // Pour les soumissions de formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Toujours montrer un message positif
                    showPositiveMessage();
                });
            });
            
            function showPositiveMessage() {
                // Créer un message temporaire
                const message = document.createElement('div');
                message.className = 'alert alert-success position-fixed top-0 end-0 m-3';
                message.style.zIndex = '9999';
                message.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Traitement en cours...';
                document.body.appendChild(message);
                
                // Supprimer après 2 secondes
                setTimeout(() => message.remove(), 2000);
            }
        });
    </script>
</body>
</html>