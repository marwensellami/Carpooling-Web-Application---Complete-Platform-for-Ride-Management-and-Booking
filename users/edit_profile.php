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
$active_tab = $_GET['tab'] ?? 'personal';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer les informations actuelles de l'utilisateur
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }
    
    // Récupérer le véhicule s'il existe
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicle = $stmt->fetch();
    
    $formData = [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'current_password' => '',
        'new_password' => '',
        'confirm_password' => ''
    ];
    
    $vehicleData = [
        'brand' => $vehicle['brand'] ?? '',
        'model' => $vehicle['model'] ?? '',
        'year' => $vehicle['year'] ?? date('Y'),
        'color' => $vehicle['color'] ?? '',
        'license_plate' => $vehicle['license_plate'] ?? '',
        'has_air_conditioning' => $vehicle['has_air_conditioning'] ?? false,
        'max_passengers' => $vehicle['max_passengers'] ?? 4
    ];

    // Traitement du formulaire d'informations personnelles
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal'])) {
        $formData = array_merge($formData, sanitize($_POST));
        
        // Validation
        if (empty($formData['first_name'])) {
            $errors['first_name'] = "Le prénom est obligatoire";
        }
        
        if (empty($formData['last_name'])) {
            $errors['last_name'] = "Le nom est obligatoire";
        }
        
        if (empty($formData['email'])) {
            $errors['email'] = "L'email est obligatoire";
        } elseif (!isValidEmail($formData['email'])) {
            $errors['email'] = "Format d'email invalide";
        }
        
        if (empty($formData['phone'])) {
            $errors['phone'] = "Le téléphone est obligatoire";
        } elseif (!isValidTunisianPhone($formData['phone'])) {
            $errors['phone'] = "Format de téléphone invalide";
        }
        
        // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
        if (empty($errors['email'])) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$formData['email'], $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors['email'] = "Cet email est déjà utilisé par un autre compte";
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $formData['first_name'],
                    $formData['last_name'],
                    $formData['email'],
                    $formData['phone'],
                    $_SESSION['user_id']
                ]);
                
                // Mettre à jour la session
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_name'] = $formData['first_name'] . ' ' . $formData['last_name'];
                $_SESSION['first_name'] = $formData['first_name'];
                
                $success = "Profil mis à jour avec succès!";
                logAction($conn, $_SESSION['user_id'], 'profile_update', 'Informations personnelles mises à jour');
                
            } catch (Exception $e) {
                $errors['general'] = "Erreur lors de la mise à jour: " . $e->getMessage();
            }
        }
    }
    
    // Traitement du formulaire de mot de passe
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
        $passwordData = sanitize($_POST);
        
        if (empty($passwordData['current_password'])) {
            $errors['current_password'] = "Le mot de passe actuel est obligatoire";
        }
        
        if (empty($passwordData['new_password'])) {
            $errors['new_password'] = "Le nouveau mot de passe est obligatoire";
        } elseif (!isStrongPassword($passwordData['new_password'])) {
            $errors['new_password'] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères avec majuscule, minuscule et chiffre";
        }
        
        if ($passwordData['new_password'] !== $passwordData['confirm_password']) {
            $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
        }
        
        if (empty($errors)) {
            // Vérifier le mot de passe actuel
            if (password_verify($passwordData['current_password'], $user['password'])) {
                $hashedPassword = password_hash($passwordData['new_password'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $success = "Mot de passe mis à jour avec succès!";
                logAction($conn, $_SESSION['user_id'], 'password_change', 'Mot de passe modifié');
                
                // Réinitialiser les champs de mot de passe
                $formData['current_password'] = $formData['new_password'] = $formData['confirm_password'] = '';
                
            } else {
                $errors['current_password'] = "Mot de passe actuel incorrect";
            }
        }
    }
    
    // Traitement du formulaire de véhicule
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vehicle'])) {
        $vehicleData = array_merge($vehicleData, sanitize($_POST));
        $vehicleData['has_air_conditioning'] = isset($_POST['has_air_conditioning']) ? 1 : 0;
        
        // Validation
        if (empty($vehicleData['brand'])) {
            $errors['brand'] = "La marque est obligatoire";
        }
        
        if (empty($vehicleData['model'])) {
            $errors['model'] = "Le modèle est obligatoire";
        }
        
        if (empty($vehicleData['year']) || $vehicleData['year'] < 1990 || $vehicleData['year'] > date('Y')) {
            $errors['year'] = "L'année doit être entre 1990 et " . date('Y');
        }
        
        if (empty($vehicleData['color'])) {
            $errors['color'] = "La couleur est obligatoire";
        }
        
        if (empty($vehicleData['license_plate'])) {
            $errors['license_plate'] = "La plaque d'immatriculation est obligatoire";
        }
        
        if ($vehicleData['max_passengers'] < 1 || $vehicleData['max_passengers'] > MAX_SEATS_PER_RIDE) {
            $errors['max_passengers'] = "Le nombre de places doit être entre 1 et " . MAX_SEATS_PER_RIDE;
        }
        
        if (empty($errors)) {
            try {
                if ($vehicle) {
                    // Mettre à jour le véhicule existant
                    $stmt = $conn->prepare("
                        UPDATE vehicles 
                        SET brand = ?, model = ?, year = ?, color = ?, license_plate = ?, 
                            has_air_conditioning = ?, max_passengers = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $vehicleData['brand'],
                        $vehicleData['model'],
                        $vehicleData['year'],
                        $vehicleData['color'],
                        $vehicleData['license_plate'],
                        $vehicleData['has_air_conditioning'],
                        $vehicleData['max_passengers'],
                        $_SESSION['user_id']
                    ]);
                } else {
                    // Créer un nouveau véhicule
                    $stmt = $conn->prepare("
                        INSERT INTO vehicles (user_id, brand, model, year, color, license_plate, has_air_conditioning, max_passengers)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $vehicleData['brand'],
                        $vehicleData['model'],
                        $vehicleData['year'],
                        $vehicleData['color'],
                        $vehicleData['license_plate'],
                        $vehicleData['has_air_conditioning'],
                        $vehicleData['max_passengers']
                    ]);
                }
                
                $success = "Véhicule " . ($vehicle ? "mis à jour" : "ajouté") . " avec succès!";
                logAction($conn, $_SESSION['user_id'], 'vehicle_update', 'Informations véhicule mises à jour');
                
            } catch (Exception $e) {
                $errors['general'] = "Erreur lors de la mise à jour du véhicule: " . $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur édition profil: " . $e->getMessage());
    $errors['general'] = "Erreur lors du chargement: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Profil - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .nav-tabs .nav-link {
            color: #667eea;
            border: none;
            padding: 1rem 1.5rem;
        }
        .profile-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card profile-card">
                    <div class="card-header bg-white border-bottom-0 pt-4">
                        <h2 class="card-title text-center mb-4">
                            <i class="fas fa-user-edit me-2 text-primary"></i>
                            Modifier mon profil
                        </h2>
                        
                        <!-- Navigation par onglets -->
                        <ul class="nav nav-tabs nav-justified mb-4" id="profileTabs">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_tab === 'personal' ? 'active' : ''; ?>" 
                                   href="?tab=personal">
                                    <i class="fas fa-user me-2"></i>Informations personnelles
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_tab === 'password' ? 'active' : ''; ?>" 
                                   href="?tab=password">
                                    <i class="fas fa-lock me-2"></i>Mot de passe
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $active_tab === 'vehicle' ? 'active' : ''; ?>" 
                                   href="?tab=vehicle">
                                    <i class="fas fa-car me-2"></i>Véhicule
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
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

                        <!-- Onglet Informations personnelles -->
                        <?php if ($active_tab === 'personal'): ?>
                        <form method="POST" action="?tab=personal">
                            <input type="hidden" name="update_personal" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">Prénom *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                               id="first_name" name="first_name" value="<?php echo htmlspecialchars($formData['first_name']); ?>" required>
                                        <?php if (isset($errors['first_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Nom *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                               id="last_name" name="last_name" value="<?php echo htmlspecialchars($formData['last_name']); ?>" required>
                                        <?php if (isset($errors['last_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="phone" class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       id="phone" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                                       placeholder="+216 XX XXX XXX" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-outline-secondary me-md-2">Annuler</a>
                                <button type="submit" class="btn btn-primary">Mettre à jour</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- Onglet Mot de passe -->
                        <?php if ($active_tab === 'password'): ?>
                        <form method="POST" action="?tab=password">
                            <input type="hidden" name="update_password" value="1">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel *</label>
                                <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                       id="current_password" name="current_password" required>
                                <?php if (isset($errors['current_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                       id="new_password" name="new_password" required>
                                <div class="form-text">
                                    Minimum <?php echo PASSWORD_MIN_LENGTH; ?> caractères avec majuscule, minuscule et chiffre
                                </div>
                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                                <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                       id="confirm_password" name="confirm_password" required>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-outline-secondary me-md-2">Annuler</a>
                                <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- Onglet Véhicule -->
                        <?php if ($active_tab === 'vehicle'): ?>
                        <form method="POST" action="?tab=vehicle">
                            <input type="hidden" name="update_vehicle" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="brand" class="form-label">Marque *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['brand']) ? 'is-invalid' : ''; ?>" 
                                               id="brand" name="brand" value="<?php echo htmlspecialchars($vehicleData['brand']); ?>" 
                                               placeholder="Ex: Renault, Peugeot..." required>
                                        <?php if (isset($errors['brand'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['brand']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="model" class="form-label">Modèle *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['model']) ? 'is-invalid' : ''; ?>" 
                                               id="model" name="model" value="<?php echo htmlspecialchars($vehicleData['model']); ?>" 
                                               placeholder="Ex: Clio, 208..." required>
                                        <?php if (isset($errors['model'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['model']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="year" class="form-label">Année *</label>
                                        <select class="form-control <?php echo isset($errors['year']) ? 'is-invalid' : ''; ?>" 
                                                id="year" name="year" required>
                                            <option value="">Sélectionner l'année</option>
                                            <?php for ($y = date('Y'); $y >= 1990; $y--): ?>
                                                <option value="<?php echo $y; ?>" <?php echo $vehicleData['year'] == $y ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <?php if (isset($errors['year'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['year']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="color" class="form-label">Couleur *</label>
                                        <input type="text" class="form-control <?php echo isset($errors['color']) ? 'is-invalid' : ''; ?>" 
                                               id="color" name="color" value="<?php echo htmlspecialchars($vehicleData['color']); ?>" 
                                               placeholder="Ex: Blanc, Noir..." required>
                                        <?php if (isset($errors['color'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['color']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="max_passengers" class="form-label">Places disponibles *</label>
                                        <select class="form-control <?php echo isset($errors['max_passengers']) ? 'is-invalid' : ''; ?>" 
                                                id="max_passengers" name="max_passengers" required>
                                            <?php for ($i = 1; $i <= MAX_SEATS_PER_RIDE; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $vehicleData['max_passengers'] == $i ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> place<?php echo $i > 1 ? 's' : ''; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <?php if (isset($errors['max_passengers'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['max_passengers']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="license_plate" class="form-label">Plaque d'immatriculation *</label>
                                <input type="text" class="form-control <?php echo isset($errors['license_plate']) ? 'is-invalid' : ''; ?>" 
                                       id="license_plate" name="license_plate" value="<?php echo htmlspecialchars($vehicleData['license_plate']); ?>" 
                                       placeholder="Ex: 1234 TU 1234" required>
                                <?php if (isset($errors['license_plate'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['license_plate']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="has_air_conditioning" 
                                           name="has_air_conditioning" value="1" <?php echo $vehicleData['has_air_conditioning'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_air_conditioning">
                                        Véhicule équipé de climatisation
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="profile.php" class="btn btn-outline-secondary me-md-2">Annuler</a>
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $vehicle ? 'Mettre à jour' : 'Ajouter'; ?> le véhicule
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="profile.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-2"></i>Retour au profil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>