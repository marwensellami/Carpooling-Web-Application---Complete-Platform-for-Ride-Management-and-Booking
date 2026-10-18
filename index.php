<?php
session_start();
require_once 'config/db_connection.php';
require_once 'config/app_settings.php';
require_once 'includes/functions.php';

// Définir le titre de la page
$page_title = "Accueil - Covoiturage Tunisie";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les statistiques du site
    $stats = getSiteStats($conn);
    
    // Récupérer les trajets populaires récents (limité à 6)
    $stmt = $conn->prepare("
        SELECT r.*, u.first_name, u.last_name,
               v.brand, v.model, v.has_air_conditioning,
               COUNT(b.id) as booking_count
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.is_active = 1 AND r.departure_date >= CURDATE()
        GROUP BY r.id
        ORDER BY r.created_at DESC, booking_count DESC
        LIMIT 6
    ");
    $stmt->execute();
    $popular_rides = $stmt->fetchAll();
    
    // Récupérer les témoignages (simulés pour l'instant)
    $testimonials = [
        [
            'name' => 'Mohamed Ben Ali',
            'city' => 'Tunis',
            'rating' => 5,
            'comment' => 'Excellent service ! J\'économise 200 DT par mois sur mes trajets travail-maison.',
            'avatar' => 'MA'
        ],
        [
            'name' => 'Fatma Chouchen',
            'city' => 'Sousse',
            'rating' => 4,
            'comment' => 'Très pratique pour mes voyages entre Sousse et Tunis. Conducteurs sympas et ponctuels.',
            'avatar' => 'FC'
        ],
        [
            'name' => 'Karim Trabelsi',
            'city' => 'Sfax',
            'rating' => 5,
            'comment' => 'Plateforme fiable. Je partage régulièrement mes trajets et rencontre des personnes intéressantes.',
            'avatar' => 'KT'
        ]
    ];
    
    // Villes populaires pour la recherche rapide
    global $tunisian_cities;
    $popular_cities = ['Tunis', 'Sousse', 'Sfax', 'Nabeul', 'Bizerte', 'Gabès'];
    
} catch (Exception $e) {
    error_log("Erreur page d'accueil: " . $e->getMessage());
    // Continuer sans les données dynamiques en cas d'erreur
    $stats = [
        'total_users' => '1,000+',
        'total_rides' => '500+',
        'total_bookings' => '2,000+',
        'total_savings' => '50,000+'
    ];
    $popular_rides = [];
    $popular_cities = ['Tunis', 'Sousse', 'Sfax'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Styles spécifiques à la page d'accueil */
        .hero-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%), 
                        url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0 80px;
            position: relative;
        }
        
        .feature-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .ride-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .ride-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .testimonial-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        
        .city-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .city-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .search-box-hero {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .floating-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .gradient-text {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php 
    // Inclure le header sans les messages flash pour la page d'accueil
    $hide_flash_messages = true;
    include 'includes/header.php'; 
    ?>

    <!-- Section Hero -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        Voyagez malin, 
                        <span class="d-block">économisez ensemble</span>
                    </h1>
                    <p class="lead mb-4">
                        La première plateforme de covoiturage en Tunisie. 
                        Rejoignez notre communauté et découvrez une nouvelle façon de voyager 
                        économique, écologique et conviviale.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="auth/register.php" class="btn btn-light btn-lg px-4 py-2 fw-bold">
                            <i class="fas fa-user-plus me-2"></i>Commencer maintenant
                        </a>
                        <a href="#how-it-works" class="btn btn-outline-light btn-lg px-4 py-2">
                            <i class="fas fa-play-circle me-2"></i>Comment ça marche
                        </a>
                    </div>
                    <div class="d-flex flex-wrap gap-4 text-white-50">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-shield-alt me-2 text-success"></i>
                            <span>100% Sécurisé</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-leaf me-2 text-success"></i>
                            <span>Écologique</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-bolt me-2 text-warning"></i>
                            <span>Rapide</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="search-box-hero">
                        <h4 class="mb-4 text-center">
                            <i class="fas fa-search me-2 gradient-text"></i>
                            <p class="text-body">Trouvez votre trajet idéal</p>
                        </h4>
                        <form action="rides/browse_rides.php" method="GET">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><p class="text-body">Départ</p></label>
                                    <select class="form-select" name="departure_city" required>
                                        <option value="">Ville de départ</option>
                                        <?php foreach ($tunisian_cities as $city): ?>
                                            <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><Arrivée class="text-body">Arrivée</p></label>
                                    <select class="form-select" name="arrival_city" required>
                                        <option value="">Ville d'arrivée</option>
                                        <?php foreach ($tunisian_cities as $city): ?>
                                            <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><Date class="text-body">Date</p></label>
                                    <input type="date" class="form-control" name="departure_date" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold"><Places class="text-body">Places</p></label>
                                    <select class="form-select" name="min_seats">
                                        <option value="1">1 place</option>
                                        <option value="2">2 places</option>
                                        <option value="3">3 places</option>
                                        <option value="4">4 places</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                                        <i class="fas fa-search me-2"></i>Rechercher un trajet
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Villes populaires -->
                        <div class="mt-3">
                            <p class="small text-muted mb-2 text-center">Villes populaires :</p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <?php foreach ($popular_cities as $city): ?>
                                    <a href="rides/browse_rides.php?departure_city=<?php echo urlencode($city); ?>" 
                                       class="city-badge text-decoration-none small">
                                        <?php echo $city; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistiques -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center">
                
<div class="row justify-content-center">
    <div class="col-md-3 col-6 mb-4">
        <div class="stat-number text-center"><?php echo $stats['total_users']; ?></div>
        <p class="text-muted mb-0 text-center">Utilisateurs inscrits</p>
    </div>
    
    <div class="col-md-3 col-6 mb-4">
        <div class="stat-number text-center"><?php echo $stats['total_rides']; ?></div>
        <p class="text-muted mb-0 text-center">Trajets publiés</p>
    </div>
    
    <div class="col-md-3 col-6 mb-4">
        <div class="stat-number text-center"><?php echo $stats['total_bookings']; ?></div>
        <p class="text-muted mb-0 text-center">Réservations</p>
    </div>
</div>
                
            </div>
        </div>
    </section>

    <!-- Comment ça marche -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Comment ça marche ?</h2>
                <p class="lead text-muted">Simple, rapide et sécurisé en 3 étapes</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mx-auto mb-3 floating-animation">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-user-plus fa-2x"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-3">1. Inscrivez-vous</h4>
                        <p class="text-muted">
                            Créez votre compte en 2 minutes. C'est gratuit et sans engagement.
                        </p>
                        <a href="auth/register.php" class="btn btn-outline-primary mt-2">
                            S'inscrire maintenant
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mx-auto mb-3 floating-animation" style="animation-delay: 0.5s;">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-route fa-2x"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-3">2. Trouvez ou proposez</h4>
                        <p class="text-muted">
                            Recherchez un trajet ou publiez le vôtre. Fixez votre prix et vos conditions.
                        </p>
                        <div class="d-flex gap-2 justify-content-center mt-2">
                            <a href="rides/browse_rides.php" class="btn btn-outline-success btn-sm">
                                Chercher
                            </a>
                            <a href="rides/create_ride.php" class="btn btn-outline-success btn-sm">
                                Proposer
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card text-center p-4">
                        <div class="feature-icon mx-auto mb-3 floating-animation" style="animation-delay: 1s;">
                            <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-car-side fa-2x"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-3">3. Voyagez</h4>
                        <p class="text-muted">
                            Confirmez votre réservation, rencontrez-vous et voyagez en toute sérénité.
                        </p>
                        <a href="auth/login.php" class="btn btn-outline-warning mt-2">
                            Commencer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trajets populaires -->
    <section id="popular-rides" class="py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-2">Trajets populaires</h2>
                    <p class="text-muted mb-0">Découvrez les trajets les plus demandés</p>
                </div>
                <a href="rides/browse_rides.php" class="btn btn-outline-primary">
                    Voir tous les trajets <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            
            <?php if (!empty($popular_rides)): ?>
                <div class="row g-4">
                    <?php foreach ($popular_rides as $ride): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="ride-card bg-white p-3">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-1">
                                            <?php echo htmlspecialchars($ride['departure_city']); ?> 
                                            <i class="fas fa-arrow-right mx-2 text-muted small"></i>
                                            <?php echo htmlspecialchars($ride['arrival_city']); ?>
                                        </h5>
                                        <p class="text-muted small mb-0">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo formatDate($ride['departure_date']); ?>
                                            à <?php echo substr($ride['departure_time'], 0, 5); ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-success">
                                        <?php echo formatCurrency($ride['price_per_seat']); ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                             style="width: 30px; height: 30px; font-size: 0.8rem;">
                                            <?php echo strtoupper(substr($ride['first_name'], 0, 1) . substr($ride['last_name'], 0, 1)); ?>
                                        </div>
                                        <span class="small"><?php echo htmlspecialchars($ride['first_name']); ?></span>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Places disponibles</div>
                                        <div class="fw-bold text-<?php echo $ride['available_seats'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $ride['available_seats']; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-muted">
                                        <?php if ($ride['has_air_conditioning']): ?>
                                            <i class="fas fa-snowflake text-info me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?>
                                    </div>
                                    <?php if ($ride['booking_count'] > 0): ?>
                                        <div class="small text-success">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $ride['booking_count']; ?> résa
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="rides/ride_details.php?id=<?php echo $ride['id']; ?>" 
                                       class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-eye me-1"></i>Voir le trajet
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-road fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Aucun trajet disponible pour le moment</h4>
                    <p class="text-muted mb-4">Soyez le premier à proposer un trajet !</p>
                    <a href="rides/create_ride.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Proposer un trajet
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Témoignages -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Ils nous font confiance</h2>
                <p class="lead text-muted">Découvrez les expériences de notre communauté</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="testimonial-card p-4 h-100">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                     style="width: 50px; height: 50px; font-weight: bold;">
                                    <?php echo $testimonial['avatar']; ?>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1"><?php echo $testimonial['name']; ?></h6>
                                    <p class="text-muted small mb-0"><?php echo $testimonial['city']; ?></p>
                                </div>
                            </div>
                            
                            <div class="rating-stars mb-3">
                                <?php echo generateStarRating($testimonial['rating']); ?>
                            </div>
                            
                            <p class="text-muted mb-0">
                                "<?php echo $testimonial['comment']; ?>"
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pourquoi nous choisir -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">Pourquoi choisir <?php echo APP_NAME; ?> ?</h2>
                    
                    <div class="d-flex mb-4">
                        <div class="me-4">
                            <i class="fas fa-shield-alt fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Sécurité garantie</h5>
                            <p class="mb-0 text-white-50">
                                Profils vérifiés, système de notation et support client 24h/24.
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="me-4">
                            <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Économique</h5>
                            <p class="mb-0 text-white-50">
                                Jusqu'à 70% d'économies par rapport aux transports classiques.
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="me-4">
                            <i class="fas fa-leaf fa-2x text-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Écologique</h5>
                            <p class="mb-0 text-white-50">
                                Réduisez votre empreinte carbone en partageant vos trajets.
                            </p>
                        </div>
                    </div>
                    
                    <div class="d-flex">
                        <div class="me-4">
                            <i class="fas fa-users fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold">Communautaire</h5>
                            <p class="mb-0 text-white-50">
                                Rencontrez des personnes de votre région et voyagez ensemble.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 text-center">
                    <div class="bg-white text-dark rounded-3 p-5">
                        <h4 class="fw-bold mb-4 gradient-text">Prêt à commencer ?</h4>
                        <p class="text-muted mb-4">
                            Rejoignez des milliers d'utilisateurs qui voyagent malin avec <?php echo APP_NAME; ?>.
                        </p>
                        
                        <div class="d-grid gap-3">
                            <a href="auth/register.php" class="btn btn-primary btn-lg py-3">
                                <i class="fas fa-rocket me-2"></i>Créer mon compte gratuit
                            </a>
                            <a href="auth/login.php" class="btn btn-outline-primary btn-lg py-3">
                                <i class="fas fa-sign-in-alt me-2"></i>J'ai déjà un compte
                            </a>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <div class="fw-bold text-primary">0 DT</div>
                                    <small class="text-muted">Inscription</small>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary">0%</div>
                                    <small class="text-muted">Commission</small>
                                </div>
                                <div>
                                    <div class="fw-bold text-primary">24h/24</div>
                                    <small class="text-muted">Support</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">Questions fréquentes</h2>
                <p class="lead text-muted">Trouvez rapidement des réponses à vos questions</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Comment fonctionne le paiement ?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Le paiement s'effectue directement entre le conducteur et le passager, 
                                    généralement en espèces le jour du trajet. Nous recommandons de prévoir 
                                    le montant exact pour plus de commodité.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Est-ce que le service est sécurisé ?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oui ! Tous nos utilisateurs sont vérifiés et notés. Nous disposons 
                                    d'un système de modération et d'un support client disponible 24h/24 
                                    pour assurer votre sécurité.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Puis-je annuler une réservation ?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Oui, vous pouvez annuler une réservation jusqu'à 24 heures avant 
                                    le départ sans frais. Au-delà, veuillez contacter directement 
                                    le conducteur pour convenir d'un arrangement.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Comment sont calculés les prix ?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Les conducteurs fixent librement leurs prix en fonction de la distance, 
                                    du coût du carburant et des frais d'autoroute. Les prix sont indiqués 
                                    par place et doivent rester raisonnables.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted mb-3">Vous avez d'autres questions ?</p>
                        <a href="mailto:support@covoiturage.tn" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i>Contactez notre support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation pour les éléments au défilement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation du formulaire de recherche
            const searchBox = document.querySelector('.search-box-hero');
            if (searchBox) {
                setTimeout(() => {
                    searchBox.style.opacity = '1';
                    searchBox.style.transform = 'translateY(0)';
                }, 500);
            }
            
            // Empêcher la sélection de la même ville dans le formulaire
            const departureSelect = document.querySelector('select[name="departure_city"]');
            const arrivalSelect = document.querySelector('select[name="arrival_city"]');
            
            if (departureSelect && arrivalSelect) {
                departureSelect.addEventListener('change', function() {
                    const selectedValue = this.value;
                    Array.from(arrivalSelect.options).forEach(option => {
                        option.disabled = option.value === selectedValue;
                    });
                });
                
                arrivalSelect.addEventListener('change', function() {
                    const selectedValue = this.value;
                    Array.from(departureSelect.options).forEach(option => {
                        option.disabled = option.value === selectedValue;
                    });
                });
            }
            
            // Date minimum pour la recherche
            const dateInput = document.querySelector('input[name="departure_date"]');
            if (dateInput) {
                dateInput.min = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>