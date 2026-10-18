<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';
require_once '../includes/notification_functions.php';

// Vérifier la connexion
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('../auth/login.php');
}

// Vérifier l'ID du trajet
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Trajet non spécifié";
    redirect('my_rides.php');
}

$ride_id = (int)$_GET['id'];
$errors = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les informations du trajet
    $stmt = $conn->prepare("
        SELECT r.*, 
               COUNT(b.id) as confirmed_bookings,
               u.first_name, u.last_name, u.email
        FROM rides r
        LEFT JOIN bookings b ON r.id = b.ride_id AND b.status = 'confirmed'
        WHERE r.id = ? AND r.driver_id = ?
        GROUP BY r.id
    ");
    $stmt->execute([$ride_id, $_SESSION['user_id']]);
    $ride = $stmt->fetch();
    
    if (!$ride) {
        $_SESSION['error'] = "Trajet non trouvé ou vous n'êtes pas autorisé";
        redirect('my_rides.php');
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reason = sanitize($_POST['reason'] ?? '');
        $notify_passengers = isset($_POST['notify_passengers']);
        
        if (empty($reason)) {
            $errors['reason'] = "Veuillez indiquer une raison";
        }
        
        if (empty($errors)) {
            // Désactiver le trajet avec notification
            $result = deactivateRideWithNotification(
                $conn, 
                $ride_id, 
                $_SESSION['user_id'], 
                $notify_passengers ? $reason : ''
            );
            
            if ($result['success']) {
                $_SESSION['success'] = sprintf(
                    "Trajet désactivé avec succès. %d passager(s) notifié(s).",
                    $result['notified_count']
                );
                redirect('my_rides.php');
            } else {
                $errors['general'] = "Erreur lors de la désactivation: " . $result['error'];
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur désactivation trajet: " . $e->getMessage());
    $errors['general'] = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désactiver le Trajet - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .deactivate-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .deactivate-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <!-- Header -->
    <div class="deactivate-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>Désactiver le Trajet
                    </h1>
                    <p class="lead mb-0">
                        <?php echo htmlspecialchars($ride['departure_city']); ?> 
                        <i class="fas fa-arrow-right mx-2"></i>
                        <?php echo htmlspecialchars($ride['arrival_city']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="my_rides.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                <?php endif; ?>

                <div class="card deactivate-card">
                    <div class="card-body p-4">
                        <!-- Informations du trajet -->
                        <div class="alert alert-info mb-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Trajet à désactiver :</h6>
                            <p class="mb-1"><strong>Itinéraire :</strong> <?php echo htmlspecialchars($ride['departure_city']); ?> → <?php echo htmlspecialchars($ride['arrival_city']); ?></p>
                            <p class="mb-1"><strong>Date :</strong> <?php echo formatDate($ride['departure_date']); ?> à <?php echo substr($ride['departure_time'], 0, 5); ?></p>
                            <p class="mb-1"><strong>Places :</strong> <?php echo $ride['available_seats']; ?> place(s) disponible(s)</p>
                            <p class="mb-0"><strong>Réservations confirmées :</strong> <?php echo $ride['confirmed_bookings']; ?></p>
                        </div>

                        <!-- Avertissement -->
                        <div class="alert alert-warning mb-4">
                            <h6><i class="fas fa-exclamation-circle me-2"></i>Attention !</h6>
                            <p class="mb-2">En désactivant ce trajet :</p>
                            <ul class="mb-2">
                                <li>Le trajet ne sera plus visible pour les autres utilisateurs</li>
                                <li>Les réservations en attente seront automatiquement annulées</li>
                                <?php if ($ride['confirmed_bookings'] > 0): ?>
                                    <li><strong>Les <?php echo $ride['confirmed_bookings']; ?> passager(s) confirmé(s) seront notifié(s)</strong></li>
                                <?php endif; ?>
                            </ul>
                            <p class="mb-0">Cette action est réversible (vous pourrez réactiver le trajet plus tard).</p>
                        </div>

                        <!-- Formulaire -->
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="reason" class="form-label fw-bold">
                                    Raison de la désactivation *
                                </label>
                                <textarea class="form-control <?php echo isset($errors['reason']) ? 'is-invalid' : ''; ?>" 
                                          id="reason" name="reason" rows="4" 
                                          placeholder="Pourquoi désactivez-vous ce trajet ? (ex: Véhicule en panne, changement de programme, etc.)"
                                          required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['reason']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">
                                    Cette information aidera les passagers à comprendre la situation.
                                </div>
                            </div>

                            <?php if ($ride['confirmed_bookings'] > 0): ?>
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="notify_passengers" name="notify_passengers" value="1" checked>
                                        <label class="form-check-label" for="notify_passengers">
                                            <i class="fas fa-bell me-2 text-warning"></i>
                                            Notifier les <?php echo $ride['confirmed_bookings']; ?> passager(s) confirmé(s)
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Si coché, les passagers recevront la raison de l'annulation.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my_rides.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-power-off me-2"></i>Désactiver le trajet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>