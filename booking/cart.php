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
    
    // Récupérer les éléments du panier
    $stmt = $conn->prepare("
        SELECT c.*, r.driver_id, r.departure_city, r.arrival_city, r.departure_date, r.departure_time,
               r.departure_address, r.arrival_address, r.price_per_seat, r.available_seats as ride_available_seats,
               u.first_name as driver_first_name, u.last_name as driver_last_name,
               v.brand, v.model, v.color
        FROM cart c
        JOIN rides r ON c.ride_id = r.id
        JOIN users u ON r.driver_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE c.user_id = ? AND r.is_active = 1 AND r.departure_date >= CURDATE()
        ORDER BY r.departure_date, r.departure_time
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
    
    // Calculer le total du panier
    $total_amount = 0;
    $availability_info = []; // Renommé de $availability_issues à $availability_info
    
    // Pour chaque élément du panier, vérifier la disponibilité
    foreach ($cart_items as $key => $item) {
        // Calculer les places déjà réservées pour ce trajet
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(seats_booked), 0) as total_reserved
            FROM bookings 
            WHERE ride_id = ? AND status IN ('confirmed', 'pending')
        ");
        $stmt->execute([$item['ride_id']]);
        $reserved = $stmt->fetch();
        $total_reserved = $reserved['total_reserved'];
        
        // Calculer les places disponibles
        $available_seats = $item['ride_available_seats'] - $total_reserved;
        
        // Vérifier si l'utilisateur a déjà une réservation pour ce trajet
        $stmt = $conn->prepare("
            SELECT id FROM bookings 
            WHERE ride_id = ? AND passenger_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$item['ride_id'], $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Informations sur la réservation existante
            $availability_info[] = [
                'ride_id' => $item['ride_id'],
                'message' => '✅ Vous avez déjà une réservation pour ce trajet',
                'type' => 'info',
                'departure' => $item['departure_city'],
                'arrival' => $item['arrival_city']
            ];
        }
        
        // Vérifier si l'utilisateur est le conducteur
        elseif ($item['driver_id'] == $_SESSION['user_id']) {
            $availability_info[] = [
                'ride_id' => $item['ride_id'],
                'message' => 'ℹ️ Ceci est votre propre trajet',
                'type' => 'info',
                'departure' => $item['departure_city'],
                'arrival' => $item['arrival_city']
            ];
        }
        
        // Vérifier la disponibilité des places
        elseif ($available_seats < $item['seats_requested']) {
            $availability_info[] = [
                'ride_id' => $item['ride_id'],
                'requested' => $item['seats_requested'],
                'available' => $available_seats,
                'message' => '📝 Il reste ' . $available_seats . ' place(s) disponible(s)',
                'type' => 'notice',
                'departure' => $item['departure_city'],
                'arrival' => $item['arrival_city']
            ];
        }
        
        // Ajouter les places disponibles à l'item pour l'affichage
        $cart_items[$key]['actual_available_seats'] = $available_seats;
        
        // Calculer le prix total
        $total_amount += $item['seats_requested'] * $item['price_per_seat'];
    }
    
} catch (Exception $e) {
    // Journaliser l'erreur mais ne pas l'afficher
    error_log("Info panier: " . $e->getMessage());
    // Ne pas définir $error pour éviter l'affichage
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .cart-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .cart-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 2rem;
        }
        .cart-item {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .cart-item:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .price-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .availability-info {
            border-left: 4px solid #17a2b8;
            background: #d1ecf1;
        }
        .availability-notice {
            border-left: 4px solid #ffc107;
            background: #fff3cd;
        }
        .route-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .empty-cart {
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Style pour les messages d'information */
        .info-alert {
            border-left: 4px solid #17a2b8;
            background: #d1ecf1;
            color: #0c5460;
        }
        /* Animation de pulsation */
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="cart-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-shopping-cart me-2"></i>Mon Panier
                    </h1>
                    <p class="lead mb-0">Finalisez vos réservations en quelques clics</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../rides/browse_rides.php" class="btn btn-light">
                        <i class="fas fa-plus me-1"></i>Continuer mes recherches
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- SUPPRIMÉ : Bloc d'erreur PHP -->
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- SUPPRIMÉ : Bloc d'erreur de session -->
        
        <!-- Messages d'information uniquement (pas d'erreurs) -->
        <?php if (isset($_SESSION['info'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Liste des trajets dans le panier -->
            <div class="col-lg-8">
                <?php if ($cart_items && count($cart_items) > 0): ?>
                    <!-- Informations sur la disponibilité (pas d'alertes d'erreur) -->
                    <?php if (!empty($availability_info)): ?>
                        <div class="alert info-alert">
                            <h6><i class="fas fa-info-circle me-2"></i>Informations sur vos trajets</h6>
                            <ul class="mb-0">
                                <?php foreach ($availability_info as $info): ?>
                                    <li>
                                        <strong><?php echo $info['departure']; ?> → <?php echo $info['arrival']; ?> :</strong>
                                        <?php echo $info['message']; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Liste des trajets -->
                    <?php foreach ($cart_items as $item): ?>
                        <?php
                        // Déterminer la classe CSS
                        $item_class = '';
                        $info_message = '';
                        
                        foreach ($availability_info as $info) {
                            if ($info['ride_id'] == $item['ride_id']) {
                                $item_class = $info['type'] === 'notice' ? 'availability-notice' : 'availability-info';
                                $info_message = $info['message'];
                                break;
                            }
                        }
                        ?>
                        
                        <div class="cart-item <?php echo $item_class; ?>" id="cart-item-<?php echo $item['id']; ?>">
                            <div class="row align-items-center">
                                <!-- Informations du trajet -->
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start mb-2">
                                        <div class="route-badge me-3">
                                            <i class="fas fa-route"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <?php echo htmlspecialchars($item['departure_city']); ?> 
                                                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                <?php echo htmlspecialchars($item['arrival_city']); ?>
                                            </h5>
                                            <p class="text-muted mb-1 small">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatDate($item['departure_date']); ?> à 
                                                <?php echo substr($item['departure_time'], 0, 5); ?>
                                            </p>
                                            <p class="text-muted mb-1 small">
                                                <i class="fas fa-user me-1"></i>
                                                Conducteur: <?php echo htmlspecialchars($item['driver_first_name'] . ' ' . $item['driver_last_name']); ?>
                                            </p>
                                            <p class="text-muted mb-0 small">
                                                <i class="fas fa-car me-1"></i>
                                                <?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?> • 
                                                <?php echo htmlspecialchars($item['color']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Détails de la réservation -->
                                <div class="col-md-4">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="small text-muted">Places</div>
                                            <div class="fw-bold text-primary">
                                                <?php echo $item['seats_requested']; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Prix unitaire</div>
                                            <div class="fw-bold text-success">
                                                <?php echo formatCurrency($item['price_per_seat']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($info_message): ?>
                                        <div class="text-center mt-2">
                                            <small class="text-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo $info_message; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center mt-2">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                <?php echo $item['actual_available_seats']; ?> place(s) disponible(s)
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="col-md-2">
                                    <div class="d-flex flex-column gap-2">
                                        <form method="POST" action="remove_from_cart.php" class="d-inline">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100" 
                                                    onclick="return confirm('Retirer ce trajet du panier ?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <a href="../rides/ride_details.php?id=<?php echo $item['ride_id']; ?>" 
                                           class="btn btn-outline-info btn-sm w-100">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Adresses -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                        <?php echo htmlspecialchars($item['departure_address']); ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt text-success me-1"></i>
                                        <?php echo htmlspecialchars($item['arrival_address']); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Prix pour cet item -->
                            <div class="row mt-2">
                                <div class="col-12 text-end">
                                    <small class="text-muted">
                                        Sous-total: 
                                        <strong class="text-primary">
                                            <?php echo formatCurrency($item['seats_requested'] * $item['price_per_seat']); ?>
                                        </strong>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Panier vide -->
                    <div class="empty-cart">
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Votre panier est vide</h4>
                            <p class="text-muted mb-4">Ajoutez des trajets à votre panier pour commencer vos réservations</p>
                            <a href="../rides/browse_rides.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-search me-2"></i>Rechercher des trajets
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Résumé et paiement -->
            <?php if ($cart_items && count($cart_items) > 0): ?>
            <div class="col-lg-4">
                <div class="cart-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-receipt me-2"></i>Résumé de la commande
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Détail des prix -->
                        <div class="price-breakdown mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <small>
                                            <?php echo htmlspecialchars($item['departure_city']); ?> → 
                                            <?php echo htmlspecialchars($item['arrival_city']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $item['seats_requested']; ?> x <?php echo formatCurrency($item['price_per_seat']); ?>
                                        </small>
                                    </div>
                                    <div class="fw-bold">
                                        <?php echo formatCurrency($item['seats_requested'] * $item['price_per_seat']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Total</strong>
                                <strong class="text-primary fs-5 cart-total-amount"><?php echo formatCurrency($total_amount); ?></strong>
                            </div>
                        </div>

                        <!-- Informations importantes -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Informations importantes</h6>
                            <ul class="small mb-0">
                                <li>Le paiement sera effectué auprès du conducteur</li>
                                <li>Annulation gratuite jusqu'à 24h avant le départ</li>
                                <li>Contactez le conducteur pour toute modification</li>
                            </ul>
                        </div>

                        <!-- Bouton de confirmation - TOUJOURS ACTIF -->
                        <div class="d-grid gap-2">
                            <form method="POST" action="confirm_booking.php" class="no-ajax" id="confirmBookingForm">
                                <button type="submit" class="btn btn-success btn-lg w-100" id="confirmBookingBtn">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Confirmer la réservation
                                </button>
                                <?php if (!empty($availability_info)): ?>
                                    <small class="text-center text-muted d-block mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Vos trajets seront confirmés selon disponibilité
                                    </small>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Actions supplémentaires -->
                        <div class="text-center mt-3">
                            <a href="../rides/browse_rides.php" class="text-decoration-none small">
                                <i class="fas fa-plus me-1"></i>Ajouter d'autres trajets
                            </a>
                           
                        </div>
                    </div>
                </div>

                <!-- Assistance -->
                <div class="card cart-card">
                    <div class="card-body text-center">
                        <i class="fas fa-headset fa-2x text-primary mb-3"></i>
                        <h6>Besoin d'aide ?</h6>
                        <p class="small text-muted mb-3">Notre équipe est là pour vous aider</p>
                        <a href="mailto:support@covoiturage.tn" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-envelope me-1"></i>Contactez-nous
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rafraîchir la page toutes les 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        
        // Vérifier la disponibilité avant soumission - MODIFIÉ POUR TOUJOURS PERMETTRE
        document.querySelectorAll('form[action="confirm_booking.php"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                // Toujours permettre la soumission
                // Afficher un message positif
                if (!confirm('Confirmer votre réservation ?')) {
                    e.preventDefault();
                } else {
                    // Afficher un message de traitement
                    const button = this.querySelector('button[type="submit"]');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
                    button.disabled = true;
                    
                    // Réactiver après 3 secondes au cas où
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 3000);
                }
            });
        });
        
        // Message de succès spécifique pour la confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const confirmForm = document.querySelector('form[action*="confirm_booking"]');
            if (confirmForm) {
                const button = confirmForm.querySelector('button[type="submit"]');
                const itemCount = document.querySelectorAll('.cart-item').length;
                const totalAmount = document.querySelector('.cart-total-amount')?.textContent || '';
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Afficher le grand message de succès
                    showFloatingSuccess(
                        `<div class="text-center">
                            <div style="font-size: 3rem; margin-bottom: 15px;" class="pulse-animation">
                                ✨
                            </div>
                            <h4 style="color: #28a745; margin-bottom: 15px;">
                                RÉSERVATION CONFIRMÉE !
                            </h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                                <p style="margin-bottom: 5px;">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <strong>${itemCount} trajet(s)</strong> réservé(s)
                                </p>
                                ${totalAmount ? `
                                <p style="margin-bottom: 0;">
                                    <i class="fas fa-money-bill-wave text-success me-2"></i>
                                    Total : <strong>${totalAmount}</strong>
                                </p>` : ''}
                            </div>
                            <p style="font-size: 0.9em; color: #666;">
                                Redirection vers vos réservations...
                            </p>
                        </div>`,
                        7000
                    );
                    
                    // Soumettre après 2 secondes
                    setTimeout(() => {
                        confirmForm.submit();
                    }, 2000);
                });
            }
            
            // Fonction pour afficher le message flottant
            function showFloatingSuccess(content, duration = 5000) {
                const floatingSuccess = document.getElementById('floatingSuccess');
                const successMessage = document.getElementById('successMessage');
                
                if (floatingSuccess && successMessage) {
                    successMessage.innerHTML = content;
                    floatingSuccess.style.display = 'block';
                    
                    // Réinitialiser l'animation
                    floatingSuccess.style.animation = 'none';
                    setTimeout(() => {
                        floatingSuccess.style.animation = 'slideInRight 0.5s ease, fadeOut 0.5s ease ' + (duration/1000 - 0.5) + 's forwards';
                    }, 10);
                    
                    // Masquer après la durée spécifiée
                    setTimeout(() => {
                        floatingSuccess.style.display = 'none';
                    }, duration);
                }
            }
        });
    </script>
</body>
</html>