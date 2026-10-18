<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Vérifier qu'il y a une confirmation de réservation
if (!isset($_SESSION['booking_confirmation'])) {
    redirect('my_bookings.php');
}

$confirmation = $_SESSION['booking_confirmation'];
unset($_SESSION['booking_confirmation']); // Nettoyer la session
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation Confirmée - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .success-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .confirmation-badge {
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Success Header -->
    <div class="success-header">
        <div class="container text-center">
            <i class="fas fa-check-circle fa-5x mb-4"></i>
            <h1 class="h2 mb-3">Réservation Confirmée !</h1>
            <p class="lead mb-0">Votre réservation a été traitée avec succès</p>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card success-card">
                    <div class="card-body p-5">
                        <!-- Résumé de la commande -->
                        <div class="text-center mb-4">
                            <span class="confirmation-badge">
                                <i class="fas fa-check me-2"></i>
                                <?php echo $confirmation['booking_count']; ?> réservation(s) confirmée(s)
                            </span>
                        </div>

                        <!-- Détails des réservations -->
                        <h4 class="text-center mb-4">Détails de votre réservation</h4>
                        
                        <?php foreach ($confirmation['bookings'] as $booking): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-2">Trajet #<?php echo $booking['ride_id']; ?></h6>
                                        <p class="mb-1">
                                            <strong><?php echo htmlspecialchars($booking['departure']); ?> → <?php echo htmlspecialchars($booking['arrival']); ?></strong>
                                        </p>
                                        <p class="mb-1 text-muted small">
                                            <?php echo $booking['seats']; ?> place(s) réservée(s)
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <strong class="text-success"><?php echo formatCurrency($booking['price']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Total -->
                        <div class="border-top pt-3 mt-3">
                            <div class="row">
                                <div class="col-6">
                                    <h5>Total</h5>
                                </div>
                                <div class="col-6 text-end">
                                    <h5 class="text-success"><?php echo formatCurrency($confirmation['total_amount']); ?></h5>
                                </div>
                            </div>
                        </div>

                        <!-- Informations importantes -->
                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Prochaines étapes</h6>
                            <ul class="mb-0">
                                <li>Vous recevrez un email de confirmation</li>
                                <li>Contactez le conducteur pour les détails de rendez-vous</li>
                                <li>Le paiement s'effectue directement avec le conducteur</li>
                                <li>Annulation possible jusqu'à 24h avant le départ</li>
                            </ul>
                        </div>

                        <!-- Actions -->
                        <div class="text-center mt-4">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <a href="my_bookings.php" class="btn btn-primary btn-lg me-md-2">
                                    <i class="fas fa-list me-2"></i>Voir mes réservations
                                </a>
                                <a href="../rides/browse_rides.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Nouvelle réservation
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