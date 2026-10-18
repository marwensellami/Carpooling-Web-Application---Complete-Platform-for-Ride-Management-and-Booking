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

// Vérifier l'ID du trajet
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Trajet non spécifié";
    redirect('my_rides.php');
}

$ride_id = (int)$_GET['id'];
$errors = [];
$success = false;



try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les informations du trajet
    $stmt = $conn->prepare("
        SELECT r.*, v.brand, v.model, v.max_passengers
        FROM rides r
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.id = ? AND r.driver_id = ?
    ");
    $stmt->execute([$ride_id, $_SESSION['user_id']]);
    $ride = $stmt->fetch();
    
    if (!$ride) {
        $_SESSION['error'] = "Trajet non trouvé ou vous n'êtes pas autorisé à le modifier";
        redirect('my_rides.php');
    }
    
    // Vérifier si le trajet peut être modifié (au moins 24h avant le départ)
    $departure_datetime = strtotime($ride['departure_date'] . ' ' . $ride['departure_time']);
    $current_datetime = time();
    $time_diff = $departure_datetime - $current_datetime;
    
    if ($time_diff < 24 * 3600) {
        $_SESSION['error'] = "Impossible de modifier un trajet moins de 24 heures avant le départ";
        redirect('my_rides.php');
    }
    
    // Vérifier s'il y a des réservations confirmées
    $stmt = $conn->prepare("
        SELECT COUNT(*) as confirmed_bookings 
        FROM bookings 
        WHERE ride_id = ? AND status = 'confirmed'
    ");
    $stmt->execute([$ride_id]);
    $has_confirmed_bookings = $stmt->fetch()['confirmed_bookings'] > 0;
    
    // Villes tunisiennes
    global $tunisian_cities;
    
    $formData = [
        'departure_city' => $ride['departure_city'],
        'arrival_city' => $ride['arrival_city'],
        'departure_address' => $ride['departure_address'],
        'arrival_address' => $ride['arrival_address'],
        'departure_date' => $ride['departure_date'],
        'departure_time' => $ride['departure_time'],
        'available_seats' => $ride['available_seats'],
        'price_per_seat' => $ride['price_per_seat'],
        'description' => $ride['description']
    ];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = array_merge($formData, sanitize($_POST));
        
        // Validation
        if (empty($formData['departure_city'])) {
            $errors['departure_city'] = "La ville de départ est obligatoire";
        } elseif (!in_array($formData['departure_city'], $tunisian_cities)) {
            $errors['departure_city'] = "Ville de départ invalide";
        }
        
        if (empty($formData['arrival_city'])) {
            $errors['arrival_city'] = "La ville d'arrivée est obligatoire";
        } elseif (!in_array($formData['arrival_city'], $tunisian_cities)) {
            $errors['arrival_city'] = "Ville d'arrivée invalide";
        }
        
        if ($formData['departure_city'] === $formData['arrival_city']) {
            $errors['arrival_city'] = "La ville d'arrivée doit être différente de la ville de départ";
        }
        
        if (empty($formData['departure_address'])) {
            $errors['departure_address'] = "L'adresse de départ est obligatoire";
        }
        
        if (empty($formData['arrival_address'])) {
            $errors['arrival_address'] = "L'adresse d'arrivée est obligatoire";
        }
        
        if (empty($formData['departure_date'])) {
            $errors['departure_date'] = "La date de départ est obligatoire";
        } elseif (!isValidRideDate($formData['departure_date'])) {
            $errors['departure_date'] = "La date doit être au moins 24 heures à l'avance";
        }
        
        if (empty($formData['departure_time'])) {
            $errors['departure_time'] = "L'heure de départ est obligatoire";
        }
        
        // Vérification spéciale pour les places disponibles
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(seats_booked), 0) as total_booked_seats
            FROM bookings 
            WHERE ride_id = ? AND status = 'confirmed'
        ");
        $stmt->execute([$ride_id]);
        $total_booked_seats = $stmt->fetch()['total_booked_seats'];
        
        if (empty($formData['available_seats']) || $formData['available_seats'] < 1 || $formData['available_seats'] > $ride['max_passengers']) {
            $errors['available_seats'] = "Le nombre de places doit être entre 1 et " . $ride['max_passengers'];
        } elseif ($formData['available_seats'] < $total_booked_seats) {
            $errors['available_seats'] = "Vous ne pouvez pas réduire le nombre de places en dessous du nombre de réservations confirmées ($total_booked_seats place(s))";
        }
        
        if (empty($formData['price_per_seat']) || $formData['price_per_seat'] < MIN_PRICE_PER_SEAT || $formData['price_per_seat'] > MAX_PRICE_PER_SEAT) {
            $errors['price_per_seat'] = "Le prix doit être entre " . MIN_PRICE_PER_SEAT . " et " . MAX_PRICE_PER_SEAT . " DT";
        }
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Mettre à jour le trajet
                $stmt = $conn->prepare("
                    UPDATE rides 
                    SET departure_city = ?, arrival_city = ?, departure_address = ?, arrival_address = ?,
                        departure_date = ?, departure_time = ?, available_seats = ?, price_per_seat = ?,
                        description = ?, updated_at = NOW()
                    WHERE id = ? AND driver_id = ?
                ");
                
                $stmt->execute([
                    $formData['departure_city'],
                    $formData['arrival_city'],
                    $formData['departure_address'],
                    $formData['arrival_address'],
                    $formData['departure_date'],
                    $formData['departure_time'],
                    $formData['available_seats'],
                    $formData['price_per_seat'],
                    $formData['description'],
                    $ride_id,
                    $_SESSION['user_id']
                ]);
                
                // AJOUT DES NOTIFICATIONS AMÉLIORÉES POUR LES MODIFICATIONS
                // Détecter les changements
                $fields_to_check = [
                    'departure_city', 'arrival_city', 'departure_address', 'arrival_address',
                    'departure_date', 'departure_time', 'available_seats', 'price_per_seat'
                ];
                
                // Récupérer les anciennes données
                $old_ride = $ride; // Les données avant modification
                
                $changes = detectChanges($old_ride, $formData, $fields_to_check);
                
                if (!empty($changes) && $has_confirmed_bookings) {
                    // NOTIFICATION AMÉLIORÉE : Notifier tous les passagers avec les détails des changements
                    require_once '../includes/notification_functions.php';
                    $notified_count = notifyAllPassengersAboutRideUpdate($conn, $ride_id, $changes);
                    
                    // Notification alternative pour les systèmes existants
                    $stmt = $conn->prepare("
                        SELECT u.id, u.first_name, u.email
                        FROM bookings b
                        JOIN users u ON b.passenger_id = u.id
                        WHERE b.ride_id = ? AND b.status = 'confirmed'
                    ");
                    $stmt->execute([$ride_id]);
                    $passengers = $stmt->fetchAll();
                    
                    $formatted_changes = formatChangesForNotification($changes);
                    
                    foreach ($passengers as $passenger) {
                        // Créer une notification détaillée
                        $notification_message = "Le conducteur a modifié le trajet {$formData['departure_city']} → {$formData['arrival_city']} :\n";
                        
                        foreach ($formatted_changes as $change) {
                            $notification_message .= "• {$change['label']} : {$change['old']} → {$change['new']}\n";
                        }
                        
                        createNotification(
                            $conn,
                            $passenger['id'],
                            "🚗 Modifications du trajet",
                            $notification_message,
                            'ride_update',
                            $ride_id
                        );
                        
                        // Envoyer un email (simulé en développement)
                        sendEmailNotification(
                            $passenger['email'],
                            "🔄 Modifications de votre trajet - " . APP_NAME,
                            "
                            <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                                <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                                    <h2 style='margin:0;'>Modifications du trajet</h2>
                                </div>
                                <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                                    <p style='font-size:16px;'>Bonjour <strong>{$passenger['first_name']}</strong>,</p>
                                    
                                    <div style='background:white; border-left:4px solid #ffc107; padding:15px; margin:20px 0; border-radius:5px;'>
                                        <p style='margin:0 0 10px 0;'><strong>⚠️ Le conducteur a modifié votre trajet</strong></p>
                                        <p style='margin:0; color:#666;'>
                                            Trajet : <strong>{$formData['departure_city']} → {$formData['arrival_city']}</strong>
                                        </p>
                                    </div>
                                    
                                    <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Détails des modifications :</h3>
                                    
                                    <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                                        <tr style='background:#f1f3f4;'>
                                            <th style='padding:12px; text-align:left; width:40%;'>Champ</th>
                                            <th style='padding:12px; text-align:left; width:30%;'>Ancienne valeur</th>
                                            <th style='padding:12px; text-align:left; width:30%;'>Nouvelle valeur</th>
                                        </tr>
                            "
                        );
                        
                        // Ajouter les lignes de changements
                        $email_content = "";
                        foreach ($formatted_changes as $change) {
                            $email_content .= "
                                <tr style='border-bottom:1px solid #eee;'>
                                    <td style='padding:12px;'><strong>{$change['label']}</strong></td>
                                    <td style='padding:12px; color:#d32f2f;'><s>{$change['old']}</s></td>
                                    <td style='padding:12px; color:#388e3c;'><strong>{$change['new']}</strong></td>
                                </tr>
                            ";
                        }
                        
                        // Fermer le tableau et l'email
                        sendEmailNotification(
                            $passenger['email'],
                            "🔄 Modifications de votre trajet - " . APP_NAME,
                            "
                            <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif;'>
                                <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; padding:20px; text-align:center; border-radius:10px 10px 0 0;'>
                                    <h2 style='margin:0;'>Modifications du trajet</h2>
                                </div>
                                <div style='background:#f8f9fa; padding:30px; border-radius:0 0 10px 10px;'>
                                    <p style='font-size:16px;'>Bonjour <strong>{$passenger['first_name']}</strong>,</p>
                                    
                                    <div style='background:white; border-left:4px solid #ffc107; padding:15px; margin:20px 0; border-radius:5px;'>
                                        <p style='margin:0 0 10px 0;'><strong>⚠️ Le conducteur a modifié votre trajet</strong></p>
                                        <p style='margin:0; color:#666;'>
                                            Trajet : <strong>{$formData['departure_city']} → {$formData['arrival_city']}</strong>
                                        </p>
                                    </div>
                                    
                                    <h3 style='color:#333; border-bottom:2px solid #eee; padding-bottom:10px;'>Détails des modifications :</h3>
                                    
                                    <table style='width:100%; border-collapse:collapse; margin:20px 0;'>
                                        <tr style='background:#f1f3f4;'>
                                            <th style='padding:12px; text-align:left; width:40%;'>Champ</th>
                                            <th style='padding:12px; text-align:left; width:30%;'>Ancienne valeur</th>
                                            <th style='padding:12px; text-align:left; width:30%;'>Nouvelle valeur</th>
                                        </tr>
                                        {$email_content}
                                    </table>
                                    
                                    <div style='background:#e8f5e9; border:1px solid #c8e6c9; padding:15px; border-radius:5px; margin:20px 0;'>
                                        <h4 style='margin-top:0; color:#2e7d32;'>📋 Actions recommandées :</h4>
                                        <ol style='margin:10px 0 0 0; padding-left:20px;'>
                                            <li>Vérifiez les nouvelles informations</li>
                                            <li>Contactez le conducteur si vous avez des questions</li>
                                            <li>Confirmez votre participation avec les nouvelles données</li>
                                        </ol>
                                    </div>
                                    
                                    <div style='text-align:center; margin-top:30px;'>
                                        <a href='" . APP_URL . "/booking/ride_details.php?id={$ride_id}' 
                                           style='background:#667eea; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                                           👁️ Voir le trajet mis à jour
                                        </a>
                                    </div>
                                    
                                    <p style='color:#666; font-size:14px; margin-top:30px; border-top:1px solid #eee; padding-top:20px;'>
                                        Cordialement,<br>
                                        L'équipe <strong>" . APP_NAME . "</strong>
                                    </p>
                                </div>
                            </div>
                            "
                        );
                        
                        // Log pour débogage
                        error_log("Trajet #$ride_id modifié : {$passenger['first_name']} ({$passenger['id']}) notifié");
                    }
                    
                    // Message de confirmation avec nombre de passagers notifiés
                    $_SESSION['notification_count'] = count($passengers);
                }
                // FIN DES NOTIFICATIONS AMÉLIORÉES
                
                $conn->commit();
                
                $success = true;
                logAction($conn, $_SESSION['user_id'], 'ride_updated', "Trajet #$ride_id modifié" . (!empty($changes) ? " - " . count($changes) . " changement(s)" : ""));
                
            } catch (Exception $e) {
                $conn->rollBack();
                $errors['general'] = "Erreur lors de la modification: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur modification trajet: " . $e->getMessage());
    $errors['general'] = "Erreur lors du chargement: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Trajet - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .edit-ride-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .edit-form-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        .route-visual {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .city-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem;
        }
        .warning-alert {
            border-left: 4px solid #ffc107;
        }
        .change-badge {
            background: #ffc107;
            color: #856404;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="edit-ride-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-edit me-2"></i>Modifier le Trajet
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
            <div class="col-lg-10">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Trajet modifié avec succès !</strong>
                        
                        <?php if (isset($_SESSION['notification_count']) && $_SESSION['notification_count'] > 0): ?>
                            <div class="mt-2">
                                <i class="fas fa-bell me-1"></i>
                                <strong><?php echo $_SESSION['notification_count']; ?> passager(s)</strong> ont été notifiés des modifications.
                            </div>
                            <?php unset($_SESSION['notification_count']); ?>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="my_rides.php" class="btn btn-success btn-sm me-2">
                                <i class="fas fa-list me-1"></i>Voir mes trajets
                            </a>
                            <a href="ride_details.php?id=<?php echo $ride_id; ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir le trajet
                            </a>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $errors['general']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Alertes importantes -->
                <?php if ($has_confirmed_bookings): ?>
                    <div class="alert alert-warning warning-alert">
                        <div class="d-flex">
                            <i class="fas fa-exclamation-triangle fa-2x me-3 align-self-start mt-1"></i>
                            <div>
                                <h6 class="alert-heading mb-2"><strong>⚠️ Attention - Réservations actives</strong></h6>
                                <p class="mb-2">Ce trajet a des réservations confirmées. Toute modification sera automatiquement notifiée à tous les passagers.</p>
                                <p class="mb-2"><strong>Implications :</strong></p>
                                <ul class="mb-2">
                                    <li>Les passagers recevront une notification détaillée des changements</li>
                                    <li>Vous ne pouvez pas réduire le nombre de places en dessous des réservations existantes</li>
                                    <li>Les modifications importantes peuvent être refusées par les passagers</li>
                                </ul>
                                <p class="mb-0"><i class="fas fa-info-circle me-1"></i> Il est recommandé de contacter les passagers avant les modifications majeures.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card edit-form-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car me-2 text-primary"></i>Modifier les informations du trajet
                            <?php if ($has_confirmed_bookings): ?>
                                <span class="change-badge"><i class="fas fa-users me-1"></i><?php echo $has_confirmed_bookings; ?> réservation(s)</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- Véhicule utilisé -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Véhicule utilisé :</strong>
                                    <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?> 
                                    - Maximum <?php echo $ride['max_passengers']; ?> places
                                </div>
                                <a href="../users/edit_profile.php?tab=vehicle" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-edit me-1"></i>Changer de véhicule
                                </a>
                            </div>
                        </div>

                        <form method="POST" action="" id="editRideForm">
                            <!-- Villes de départ et d'arrivée -->
                            <div class="route-visual">
                                <div class="row align-items-center">
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label for="departure_city" class="form-label fw-bold">
                                                Ville de départ *
                                                <?php if ($has_confirmed_bookings): ?>
                                                    <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                        <i class="fas fa-bell"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </label>
                                            <select class="form-select city-select <?php echo isset($errors['departure_city']) ? 'is-invalid' : ''; ?>" 
                                                    id="departure_city" name="departure_city" required>
                                                <option value="">Choisir une ville...</option>
                                                <?php foreach ($tunisian_cities as $city): ?>
                                                    <option value="<?php echo $city; ?>" <?php echo $formData['departure_city'] === $city ? 'selected' : ''; ?>>
                                                        <?php echo $city; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['departure_city'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['departure_city']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <i class="fas fa-arrow-right fa-2x text-primary"></i>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label for="arrival_city" class="form-label fw-bold">
                                                Ville d'arrivée *
                                                <?php if ($has_confirmed_bookings): ?>
                                                    <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                        <i class="fas fa-bell"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </label>
                                            <select class="form-select city-select <?php echo isset($errors['arrival_city']) ? 'is-invalid' : ''; ?>" 
                                                    id="arrival_city" name="arrival_city" required>
                                                <option value="">Choisir une ville...</option>
                                                <?php foreach ($tunisian_cities as $city): ?>
                                                    <option value="<?php echo $city; ?>" <?php echo $formData['arrival_city'] === $city ? 'selected' : ''; ?>>
                                                        <?php echo $city; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['arrival_city'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['arrival_city']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="departure_address" class="form-label">
                                            Adresse de départ précise *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="text" class="form-control <?php echo isset($errors['departure_address']) ? 'is-invalid' : ''; ?>" 
                                               id="departure_address" name="departure_address" 
                                               value="<?php echo htmlspecialchars($formData['departure_address']); ?>" required>
                                        <?php if (isset($errors['departure_address'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['departure_address']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="arrival_address" class="form-label">
                                            Adresse d'arrivée précise *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="text" class="form-control <?php echo isset($errors['arrival_address']) ? 'is-invalid' : ''; ?>" 
                                               id="arrival_address" name="arrival_address" 
                                               value="<?php echo htmlspecialchars($formData['arrival_address']); ?>" required>
                                        <?php if (isset($errors['arrival_address'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['arrival_address']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Date et heure -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="departure_date" class="form-label">
                                            Date de départ *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="date" class="form-control <?php echo isset($errors['departure_date']) ? 'is-invalid' : ''; ?>" 
                                               id="departure_date" name="departure_date" 
                                               value="<?php echo htmlspecialchars($formData['departure_date']); ?>" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                        <div class="form-text">La date doit être au moins 24h à l'avance</div>
                                        <?php if (isset($errors['departure_date'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['departure_date']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="departure_time" class="form-label">
                                            Heure de départ *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <input type="time" class="form-control <?php echo isset($errors['departure_time']) ? 'is-invalid' : ''; ?>" 
                                               id="departure_time" name="departure_time" 
                                               value="<?php echo htmlspecialchars($formData['departure_time']); ?>" required>
                                        <?php if (isset($errors['departure_time'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['departure_time']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Places et prix -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="available_seats" class="form-label">
                                            Places disponibles *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <select class="form-select <?php echo isset($errors['available_seats']) ? 'is-invalid' : ''; ?>" 
                                                id="available_seats" name="available_seats" required>
                                            <?php for ($i = 1; $i <= $ride['max_passengers']; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $formData['available_seats'] == $i ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <?php if (isset($errors['available_seats'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['available_seats']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price_per_seat" class="form-label">
                                            Prix par place (DT) *
                                            <?php if ($has_confirmed_bookings): ?>
                                                <span class="text-warning" data-bs-toggle="tooltip" title="Les passagers seront notifiés de ce changement">
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" class="form-control <?php echo isset($errors['price_per_seat']) ? 'is-invalid' : ''; ?>" 
                                                   id="price_per_seat" name="price_per_seat" 
                                                   value="<?php echo htmlspecialchars($formData['price_per_seat']); ?>" 
                                                   min="<?php echo MIN_PRICE_PER_SEAT; ?>" 
                                                   max="<?php echo MAX_PRICE_PER_SEAT; ?>" 
                                                   step="0.5" required>
                                            <span class="input-group-text">DT</span>
                                        </div>
                                        <div class="form-text">Entre <?php echo MIN_PRICE_PER_SEAT; ?> et <?php echo MAX_PRICE_PER_SEAT; ?> DT</div>
                                        <?php if (isset($errors['price_per_seat'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['price_per_seat']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Prix total estimé</label>
                                        <div class="form-control bg-light" id="totalPricePreview">
                                            <?php echo number_format($formData['available_seats'] * $formData['price_per_seat'], 2); ?> DT
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label for="description" class="form-label">Description du trajet (optionnel)</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4" placeholder="Informations supplémentaires... (arrêts possibles, préférences, etc.)"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                            </div>

                            <!-- Avertissement final -->
                            <?php if ($has_confirmed_bookings): ?>
                                <div class="alert alert-warning mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmNotification" required>
                                        <label class="form-check-label" for="confirmNotification">
                                            <strong>Je confirme avoir pris connaissance que tous les passagers seront notifiés des modifications apportées à ce trajet.</strong>
                                            <br>
                                            <small class="text-muted">Cette action enverra automatiquement des notifications et emails à tous les passagers confirmés.</small>
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="my_rides.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-times me-1"></i>Annuler
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>
                                    <?php if ($has_confirmed_bookings): ?>
                                        Enregistrer et Notifier
                                    <?php else: ?>
                                        Enregistrer les modifications
                                    <?php endif; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Mise à jour du prix total en temps réel
        function updateTotalPrice() {
            const availableSeats = document.getElementById('available_seats').value;
            const pricePerSeat = document.getElementById('price_per_seat').value;
            
            if (availableSeats && pricePerSeat) {
                const totalPrice = availableSeats * pricePerSeat;
                document.getElementById('totalPricePreview').textContent = totalPrice.toFixed(2) + ' DT';
            }
        }
        
        document.getElementById('available_seats').addEventListener('change', updateTotalPrice);
        document.getElementById('price_per_seat').addEventListener('input', updateTotalPrice);
        
        // Empêcher la sélection de la même ville
        document.getElementById('departure_city').addEventListener('change', function() {
            const arrivalSelect = document.getElementById('arrival_city');
            const selectedDeparture = this.value;
            
            if (selectedDeparture) {
                Array.from(arrivalSelect.options).forEach(option => {
                    if (option.value === selectedDeparture) {
                        option.disabled = true;
                    } else {
                        option.disabled = false;
                    }
                });
            }
        });
        
        document.getElementById('arrival_city').addEventListener('change', function() {
            const departureSelect = document.getElementById('departure_city');
            const selectedArrival = this.value;
            
            if (selectedArrival) {
                Array.from(departureSelect.options).forEach(option => {
                    if (option.value === selectedArrival) {
                        option.disabled = true;
                    } else {
                        option.disabled = false;
                    }
                });
            }
        });
        
        // Confirmation avant envoi si des passagers sont concernés
        const hasConfirmedBookings = <?php echo $has_confirmed_bookings ? 'true' : 'false'; ?>;
        
        if (hasConfirmedBookings) {
            document.getElementById('editRideForm').addEventListener('submit', function(e) {
                const form = this;
                let hasChanges = false;
                
                // Vérifier si des champs ont été modifiés
                const originalValues = {
                    departure_city: '<?php echo $ride['departure_city']; ?>',
                    arrival_city: '<?php echo $ride['arrival_city']; ?>',
                    departure_address: '<?php echo addslashes($ride['departure_address']); ?>',
                    arrival_address: '<?php echo addslashes($ride['arrival_address']); ?>',
                    departure_date: '<?php echo $ride['departure_date']; ?>',
                    departure_time: '<?php echo $ride['departure_time']; ?>',
                    available_seats: '<?php echo $ride['available_seats']; ?>',
                    price_per_seat: '<?php echo $ride['price_per_seat']; ?>'
                };
                
                for (const field in originalValues) {
                    const currentValue = form.querySelector(`[name="${field}"]`).value;
                    if (currentValue != originalValues[field]) {
                        hasChanges = true;
                        break;
                    }
                }
                
                if (hasChanges && !document.getElementById('confirmNotification').checked) {
                    e.preventDefault();
                    alert('Veuillez confirmer que vous avez pris connaissance de la notification des passagers.');
                    document.getElementById('confirmNotification').focus();
                }
            });
        }
    </script>
</body>
</html>