<?php
session_start();
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Vérifier qu'il y a une confirmation en session
if (!isset($_SESSION['booking_confirmation'])) {
    redirect('my_bookings.php');
}

$confirmation = $_SESSION['booking_confirmation'];
$bookings = $confirmation['bookings'];
$total_amount = $confirmation['total_amount'];
$booking_count = $confirmation['booking_count'];

// Nettoyer la confirmation après affichage
unset($_SESSION['booking_confirmation']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Réservation - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .confirmation-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .confirmation-card {
            border-radius: 15px;
            border: 1px solid #e9ecef;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .checkmark-circle {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2.5rem;
        }
        .booking-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .whats-next {
            background: #e7f5ff;
            border-left: 4px solid #0d6efd;
            padding: 1.5rem;
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="confirmation-header">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-check-circle me-2"></i>Confirmation de Réservation
                    </h1>
                    <p class="lead mb-0">Votre réservation a été confirmée avec succès !</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Carte de confirmation -->
                <div class="card confirmation-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="checkmark-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <h2 class="card-title">Réservation Confirmée !</h2>
                            <p class="text-muted">Vous recevrez un email de confirmation sous peu</p>
                        </div>
                        
                        <!-- Résumé -->
                        <div class="booking-details mb-4">
                            <h4 class="mb-3">
                                <i class="fas fa-receipt me-2 text-primary"></i>Résumé de votre réservation
                            </h4>
                            <div class="row">
                                <div class="col-md-4 text-center mb-3">
                                    <div class="display-6 fw-bold text-primary">
                                        <?php echo $booking_count; ?>
                                    </div>
                                    <p class="text-muted mb-0">Trajet(s)</p>
                                </div>
                                <div class="col-md-4 text-center mb-3">
                                    <div class="display-6 fw-bold text-success">
                                        <?php echo number_format($total_amount, 2); ?> DT
                                    </div>
                                    <p class="text-muted mb-0">Total</p>
                                </div>
                                <div class="col-md-4 text-center mb-3">
                                    <div class="display-6 fw-bold text-info">
                                        <?php echo date('H:i'); ?>
                                    </div>
                                    <p class="text-muted mb-0">Heure de confirmation</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Détails des trajets -->
                        <h5 class="mb-3">
                            <i class="fas fa-route me-2 text-success"></i>Détails de vos trajets
                        </h5>
                        <?php foreach ($bookings as $booking): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1">
                                                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                                <?php echo htmlspecialchars($booking['departure']); ?> 
                                                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                <i class="fas fa-map-marker-alt text-success me-2"></i>
                                                <?php echo htmlspecialchars($booking['arrival']); ?>
                                            </h6>
                                            <p class="text-muted mb-1 small">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo formatDate($booking['date']); ?> à 
                                                <?php echo substr($booking['time'], 0, 5); ?>
                                            </p>
                                            <p class="text-muted mb-0 small">
                                                <i class="fas fa-user me-1"></i>
                                                Conducteur : <?php echo htmlspecialchars($booking['driver_name']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="mb-2">
                                                <span class="badge bg-primary">
                                                    <?php echo $booking['seats']; ?> place(s)
                                                </span>
                                                <span class="badge bg-success ms-1">
                                                    <?php echo number_format($booking['total_price'], 2); ?> DT
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                Réf. #RES<?php echo $booking['id']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Prochaines étapes -->
                        <div class="whats-next mt-4">
                            <h5>
                                <i class="fas fa-forward me-2 text-primary"></i>Prochaines étapes
                            </h5>
                            <ul class="mb-0">
                                <li>Contactez le conducteur pour les détails de prise en charge</li>
                                <li>Préparez votre paiement pour le jour du trajet</li>
                                <li>Consultez vos réservations pour suivre vos trajets</li>
                                <li>Annulez au moins 24h à l'avance si nécessaire</li>
                            </ul>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-5">
                            <a href="my_bookings.php" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-list me-2"></i>Voir mes réservations
                            </a>
                            <a href="../rides/browse_rides.php" class="btn btn-outline-secondary btn-lg px-5">
                                <i class="fas fa-search me-2"></i>Rechercher d'autres trajets
                            </a>
                        </div>
                        
                        <!-- Information de contact -->
                        <div class="alert alert-info mt-4">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-question-circle fa-2x"></i>
                                </div>
                                <div>
                                    <h6 class="alert-heading">Besoin d'aide ?</h6>
                                    <p class="mb-0">
                                        Contactez notre équipe support à 
                                        <strong>support@covoiturage.tn</strong> ou appelez le 
                                        <strong>+216 70 000 000</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Imprimer la confirmation
        function printConfirmation() {
            window.print();
        }
        
        // Partage sur les réseaux sociaux
        function shareBooking() {
            if (navigator.share) {
                navigator.share({
                    title: 'J\'ai réservé un trajet sur <?php echo APP_NAME; ?>',
                    text: 'Je viens de réserver <?php echo $booking_count; ?> trajet(s) sur <?php echo APP_NAME; ?> !',
                    url: window.location.href,
                });
            } else {
                alert('Partagez cette page avec vos amis !');
            }
        }
    </script>
</body>
</html>