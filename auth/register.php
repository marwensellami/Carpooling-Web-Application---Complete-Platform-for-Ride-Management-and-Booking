<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('../users/user_dashboard.php');
}

$errors = [];
$success = false;
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyer les données
    $formData = sanitize($_POST);
    
    // Validation des champs
    if (empty($formData['first_name'])) {
        $errors['first_name'] = "Le prénom est obligatoire";
    } elseif (strlen($formData['first_name']) < 2) {
        $errors['first_name'] = "Le prénom doit contenir au moins 2 caractères";
    }
    
    if (empty($formData['last_name'])) {
        $errors['last_name'] = "Le nom est obligatoire";
    } elseif (strlen($formData['last_name']) < 2) {
        $errors['last_name'] = "Le nom doit contenir au moins 2 caractères";
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = "L'email est obligatoire";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format d'email invalide";
    }
    
    if (empty($formData['phone'])) {
        $errors['phone'] = "Le numéro de téléphone est obligatoire";
    } elseif (!preg_match('/^[+0-9\s\-\(\)]{8,20}$/', $formData['phone'])) {
        $errors['phone'] = "Format de téléphone invalide";
    }
    
    // RÈGLES DE MOT DE PASSE SIMPLIFIÉES
    if (empty($formData['password'])) {
        $errors['password'] = "Le mot de passe est obligatoire";
    } elseif (strlen($formData['password']) < 6) { // Réduit à 6 caractères minimum
        $errors['password'] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    // Supprimé les règles complexes (majuscule, minuscule, chiffre obligatoires)
    
    if ($formData['password'] !== $formData['confirm_password']) {
        $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
    }
    
    if (empty($formData['terms'])) {
        $errors['terms'] = "Vous devez accepter les conditions d'utilisation";
    }
    
    // Vérifier si l'email existe déjà
    if (empty($errors['email'])) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$formData['email']]);
            
            if ($stmt->fetch()) {
                $errors['email'] = "Cet email est déjà utilisé";
            }
        } catch (Exception $e) {
            $errors['general'] = "Erreur lors de la vérification: " . $e->getMessage();
        }
    }
    
    // Créer l'utilisateur si aucune erreur
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (first_name, last_name, email, password, phone) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $formData['first_name'],
                $formData['last_name'],
                $formData['email'],
                $hashedPassword,
                $formData['phone']
            ]);
            
            $success = true;
            
            // Journaliser l'action
            logAction($conn, $conn->lastInsertId(), 'user_registration', 'Nouvel utilisateur inscrit');
            
            // Réinitialiser le formulaire
            $formData = [
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => ''
            ];
            
        } catch (Exception $e) {
            $errors['general'] = "Erreur lors de l'inscription: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .register-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .logo-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 0 0 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-section {
            padding: 3rem;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-medium { background: #ffc107; width: 50%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card register-card">
                        <div class="row g-0">
                            <!-- Section Logo et Présentation -->
                            <div class="col-lg-5 d-none d-lg-block logo-section">
                                <div class="text-center p-5">
                                    <i class="fas fa-car-side fa-5x mb-4"></i>
                                    <h2 class="mb-3"><?php echo APP_NAME; ?></h2>
                                    <p class="lead">Rejoignez notre communauté de covoiturage</p>
                                    <p class="small">Déjà membre ? <a href="login.php" class="text-white fw-bold">Connectez-vous ici</a></p>
                                </div>
                            </div>
                            
                            <!-- Section Formulaire -->
                            <div class="col-lg-7 form-section">
                                <div class="text-center mb-4 d-lg-none">
                                    <i class="fas fa-car-side fa-3x text-primary mb-3"></i>
                                    <h3><?php echo APP_NAME; ?></h3>
                                    <p class="text-muted">Créez votre compte</p>
                                </div>
                                
                                <?php if ($success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Inscription réussie !</strong> Vous pouvez maintenant vous connecter.
                                        <a href="login.php" class="alert-link">Se connecter</a>
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
                                
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="first_name" class="form-label">Prénom *</label>
                                                <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                                       id="first_name" name="first_name" value="<?php echo $formData['first_name']; ?>" required>
                                                <?php if (isset($errors['first_name'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="last_name" class="form-label">Nom *</label>
                                                <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                                       id="last_name" name="last_name" value="<?php echo $formData['last_name']; ?>" required>
                                                <?php if (isset($errors['last_name'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                               id="email" name="email" value="<?php echo $formData['email']; ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Téléphone *</label>
                                        <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                               id="phone" name="phone" value="<?php echo $formData['phone']; ?>" 
                                               placeholder="+216 XX XXX XXX" required>
                                        <?php if (isset($errors['phone'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Mot de passe *</label>
                                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                               id="password" name="password" required>
                                        <div class="password-strength mt-1 d-none" id="passwordStrength"></div>
                                        <small class="form-text text-muted">
                                            Minimum 6 caractères (simple à retenir)
                                        </small>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="confirm_password" name="confirm_password" required>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input <?php echo isset($errors['terms']) ? 'is-invalid' : ''; ?>" 
                                                   type="checkbox" id="terms" name="terms" value="1">
                                            <label class="form-check-label" for="terms">
                                                J'accepte les <a href="#" class="text-decoration-none">conditions d'utilisation</a> 
                                                et la <a href="#" class="text-decoration-none">politique de confidentialité</a> *
                                            </label>
                                            <?php if (isset($errors['terms'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['terms']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-user-plus me-2"></i>Créer mon compte
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <p class="mb-0">Déjà membre ? 
                                        <a href="login.php" class="text-decoration-none fw-bold">Se connecter</a>
                                    </p>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="../index.php" class="text-muted text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>Retour à l'accueil
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation du mot de passe en temps réel SIMPLIFIÉE
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength mt-1 d-none';
                return;
            }
            
            strengthBar.className = 'password-strength mt-1';
            
            // Logique simplifiée : juste basée sur la longueur
            if (password.length < 6) {
                strengthBar.className = 'password-strength mt-1 strength-weak';
            } else if (password.length < 10) {
                strengthBar.className = 'password-strength mt-1 strength-medium';
            } else {
                strengthBar.className = 'password-strength mt-1 strength-strong';
            }
        });
        
        // Confirmation du mot de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>