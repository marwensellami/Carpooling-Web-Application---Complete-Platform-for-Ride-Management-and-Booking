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

$errors = [];
$success = false;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier si l'utilisateur a un véhicule
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $errors['general'] = "Vous devez d'abord ajouter un véhicule pour publier un trajet.";
    }
    
    // Villes tunisiennes
    global $tunisian_cities;
    
    $formData = [
        'departure_city' => '',
        'arrival_city' => '',
        'departure_address' => '',
        'arrival_address' => '',
        'departure_date' => '',
        'departure_time' => '',
        'available_seats' => 1,
        'price_per_seat' => '',
        'description' => ''
    ];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $vehicle) {
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
        
        if (empty($formData['available_seats']) || $formData['available_seats'] < 1 || $formData['available_seats'] > $vehicle['max_passengers']) {
            $errors['available_seats'] = "Le nombre de places doit être entre 1 et " . $vehicle['max_passengers'];
        }
        
        if (empty($formData['price_per_seat']) || $formData['price_per_seat'] < MIN_PRICE_PER_SEAT || $formData['price_per_seat'] > MAX_PRICE_PER_SEAT) {
            $errors['price_per_seat'] = "Le prix doit être entre " . MIN_PRICE_PER_SEAT . " et " . MAX_PRICE_PER_SEAT . " DT";
        }
        
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                
                // Créer le trajet
                $stmt = $conn->prepare("
                    INSERT INTO rides (driver_id, vehicle_id, departure_city, arrival_city, 
                                     departure_address, arrival_address, departure_date, 
                                     departure_time, available_seats, price_per_seat, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $vehicle['id'],
                    $formData['departure_city'],
                    $formData['arrival_city'],
                    $formData['departure_address'],
                    $formData['arrival_address'],
                    $formData['departure_date'],
                    $formData['departure_time'],
                    $formData['available_seats'],
                    $formData['price_per_seat'],
                    $formData['description']
                ]);
                
                $ride_id = $conn->lastInsertId();
                
                // Créer une notification
                createNotification(
                    $conn, 
                    $_SESSION['user_id'],
                    "Trajet publié avec succès",
                    "Votre trajet de {$formData['departure_city']} à {$formData['arrival_city']} a été publié.",
                    'ride'
                );
                
                $conn->commit();
                
                $success = true;
                logAction($conn, $_SESSION['user_id'], 'ride_created', "Trajet #$ride_id publié");
                
                // Réinitialiser le formulaire
                $formData = [
                    'departure_city' => '',
                    'arrival_city' => '',
                    'departure_address' => '',
                    'arrival_address' => '',
                    'departure_date' => '',
                    'departure_time' => '',
                    'available_seats' => 1,
                    'price_per_seat' => '',
                    'description' => ''
                ];
                
            } catch (Exception $e) {
                $conn->rollBack();
                $errors['general'] = "Erreur lors de la publication: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur création trajet: " . $e->getMessage());
    $errors['general'] = "Erreur lors du chargement: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier un Trajet - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .create-ride-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .ride-form-card {
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
        .preview-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Header Section -->
    <div class="create-ride-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h2 mb-2">
                        <i class="fas fa-road me-2"></i>Publier un Trajet
                    </h1>
                    <p class="lead mb-0">Proposez un trajet et voyagez à plusieurs</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="browse_rides.php" class="btn btn-outline-light">
                        <i class="fas fa-search me-1"></i>Chercher un trajet
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $errors['general']; ?>
                        <?php if (strpos($errors['general'], 'véhicule') !== false): ?>
                            <a href="../users/edit_profile.php?tab=vehicle" class="alert-link">Ajouter un véhicule</a>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Trajet publié avec succès !</strong> Votre trajet est maintenant visible par les autres utilisateurs.
                        <div class="mt-2">
                            <a href="my_rides.php" class="btn btn-success btn-sm me-2">Voir mes trajets</a>
                            <a href="browse_rides.php" class="btn btn-outline-success btn-sm">Chercher d'autres trajets</a>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($vehicle): ?>
                <div class="card ride-form-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-car me-2 text-primary"></i>Informations du trajet
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- Véhicule utilisé -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Véhicule utilisé :</strong>
                                    <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?> 
                                    (<?php echo $vehicle['year']; ?>) - 
                                    <?php echo htmlspecialchars($vehicle['color']); ?>
                                    <?php if ($vehicle['has_air_conditioning']): ?>
                                        <span class="badge bg-info ms-2"><i class="fas fa-snowflake"></i> Climatisation</span>
                                    <?php endif; ?>
                                </div>
                                <a href="../users/edit_profile.php?tab=vehicle" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </div>
                        </div>

                        <form method="POST" action="" id="rideForm">
                            <!-- Villes de départ et d'arrivée -->
                            <div class="route-visual">
                                <div class="row align-items-center">
                                    <div class="col-md-5">
                                        <div class="mb-3">
                                            <label for="departure_city" class="form-label fw-bold">Ville de départ *</label>
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
                                            <label for="arrival_city" class="form-label fw-bold">Ville d'arrivée *</label>
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
                                        <label for="departure_address" class="form-label">Adresse de départ précise *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['departure_address']) ? 'is-invalid' : ''; ?>" 
                                               id="departure_address" name="departure_address" 
                                               value="<?php echo htmlspecialchars($formData['departure_address']); ?>" 
                                               placeholder="Ex: Avenue Habib Bourguiba, Centre ville..." required>
                                        <?php if (isset($errors['departure_address'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['departure_address']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="arrival_address" class="form-label">Adresse d'arrivée précise *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['arrival_address']) ? 'is-invalid' : ''; ?>" 
                                               id="arrival_address" name="arrival_address" 
                                               value="<?php echo htmlspecialchars($formData['arrival_address']); ?>" 
                                               placeholder="Ex: Place Farhat Hached, Centre ville..." required>
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
                                        <label for="departure_date" class="form-label">Date de départ *</label>
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
                                        <label for="departure_time" class="form-label">Heure de départ *</label>
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
                                        <label for="available_seats" class="form-label">Places disponibles *</label>
                                        <select class="form-select <?php echo isset($errors['available_seats']) ? 'is-invalid' : ''; ?>" 
                                                id="available_seats" name="available_seats" required>
                                            <?php for ($i = 1; $i <= $vehicle['max_passengers']; $i++): ?>
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
                                        <label for="price_per_seat" class="form-label">Prix par place (DT) *</label>
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
                                            -- DT
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

                            <!-- Aperçu en temps réel -->
                            <div class="preview-card mb-4">
                                <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Aperçu du trajet</h6>
                                <div id="ridePreview">
                                    <p class="text-muted mb-1">Remplissez le formulaire pour voir l'aperçu</p>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="browse_rides.php" class="btn btn-outline-secondary me-md-2">Annuler</a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Publier le trajet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mise à jour de l'aperçu en temps réel
        function updatePreview() {
            const departureCity = document.getElementById('departure_city').value;
            const arrivalCity = document.getElementById('arrival_city').value;
            const departureDate = document.getElementById('departure_date').value;
            const departureTime = document.getElementById('departure_time').value;
            const availableSeats = document.getElementById('available_seats').value;
            const pricePerSeat = document.getElementById('price_per_seat').value;
            
            let previewHTML = '';
            
            if (departureCity && arrivalCity) {
                previewHTML += `
                    <div class="row">
                        <div class="col-6">
                            <strong>${departureCity}</strong>
                            <div class="small text-muted">→ ${arrivalCity}</div>
                        </div>
                        <div class="col-6 text-end">
                            ${departureDate && departureTime ? `
                                <div class="small">${new Date(departureDate).toLocaleDateString('fr-FR')} à ${departureTime}</div>
                            ` : ''}
                            ${availableSeats && pricePerSeat ? `
                                <div class="small">${availableSeats} place(s) • ${pricePerSeat} DT/place</div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                previewHTML = '<p class="text-muted mb-0">Remplissez le formulaire pour voir l\'aperçu</p>';
            }
            
            document.getElementById('ridePreview').innerHTML = previewHTML;
            
            // Mise à jour du prix total
            if (availableSeats && pricePerSeat) {
                const totalPrice = availableSeats * pricePerSeat;
                document.getElementById('totalPricePreview').textContent = totalPrice.toFixed(2) + ' DT';
            } else {
                document.getElementById('totalPricePreview').textContent = '-- DT';
            }
        }
        
        // Écouteurs d'événements pour la mise à jour en temps réel
        document.getElementById('departure_city').addEventListener('change', updatePreview);
        document.getElementById('arrival_city').addEventListener('change', updatePreview);
        document.getElementById('departure_date').addEventListener('change', updatePreview);
        document.getElementById('departure_time').addEventListener('change', updatePreview);
        document.getElementById('available_seats').addEventListener('change', updatePreview);
        document.getElementById('price_per_seat').addEventListener('input', updatePreview);
        
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
        
        // Initialiser l'aperçu
        updatePreview();
    </script>
</body>
</html>