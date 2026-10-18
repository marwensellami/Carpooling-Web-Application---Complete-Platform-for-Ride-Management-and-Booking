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
$formData = ['email' => ''];

// Gérer la tentative de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = sanitize($_POST);
    
    // Validation
    if (empty($formData['email'])) {
        $errors['email'] = "L'email est obligatoire";
    }
    
    if (empty($formData['password'])) {
        $errors['password'] = "Le mot de passe est obligatoire";
    }
    
    // Vérifier les tentatives de connexion
    $login_attempts = $_SESSION['login_attempts'] ?? 0;
    $last_attempt = $_SESSION['last_attempt'] ?? 0;
    
    // Réinitialiser les tentatives après 1 minute
    if (time() - $last_attempt > 60) {
        $login_attempts = 0;
    }
    
    if ($login_attempts >= MAX_LOGIN_ATTEMPTS) {
        $errors['general'] = "Trop de tentatives de connexion. Veuillez réessayer dans 1 minute.";
    }
    
    if (empty($errors)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Rechercher l'utilisateur
            $stmt = $conn->prepare("
                SELECT id, first_name, last_name, email, password, phone, is_active, is_admin 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$formData['email']]);
            $user = $stmt->fetch();
            
            if ($user && $user['is_active']) {
    if (password_verify($formData['password'], $user['password'])) {
        // Connexion réussie
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['is_admin'] = $user['is_admin']; // Assurez-vous que cette ligne existe
        
        // DEBUG
        error_log("Login successful - User: {$user['email']}, is_admin: {$user['is_admin']}");
                    
                    // Réinitialiser les tentatives
                    $_SESSION['login_attempts'] = 0;
                    
                    // Journaliser la connexion
                    logAction($conn, $user['id'], 'user_login', 'Connexion réussie');
                    
                    // Redirection
                    if ($user['is_admin']) {
                        redirect('../admin/admin_dashboard.php');
                    } else {
                        redirect('../users/user_dashboard.php');
                    }
                    
                } else {
                    $errors['general'] = "Email ou mot de passe incorrect";
                    $_SESSION['login_attempts'] = $login_attempts + 1;
                    $_SESSION['last_attempt'] = time();
                }
            } else {
                if ($user && !$user['is_active']) {
                    $errors['general'] = "Votre compte a été désactivé. Contactez l'administrateur.";
                } else {
                    $errors['general'] = "Email ou mot de passe incorrect";
                    $_SESSION['login_attempts'] = $login_attempts + 1;
                    $_SESSION['last_attempt'] = time();
                }
            }
            
        } catch (Exception $e) {
            $errors['general'] = "Erreur lors de la connexion: " . $e->getMessage();
        }
    }
}

// Afficher l'avertissement si trop de tentatives
if (($_SESSION['login_attempts'] ?? 0) >= MAX_LOGIN_ATTEMPTS) {
    $remaining_time = 1800 - (time() - ($_SESSION['last_attempt'] ?? 0));
    $remaining_minutes = ceil($remaining_time / 60);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .logo-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card login-card">
                        <!-- En-tête -->
                        <div class="logo-section text-center">
                            <i class="fas fa-car-side fa-3x mb-3"></i>
                            <h4><?php echo APP_NAME; ?></h4>
                            <p class="mb-0">Connectez-vous à votre compte</p>
                        </div>
                        
                        <!-- Formulaire -->
                        <div class="card-body p-4">
                            <?php if (isset($errors['general'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $errors['general']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($remaining_minutes) && $remaining_minutes > 0): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock me-2"></i>
                                    Trop de tentatives. Réessayez dans <?php echo $remaining_minutes; ?> minutes.
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                               id="email" name="email" value="<?php echo $formData['email']; ?>" 
                                               <?php echo isset($remaining_minutes) && $remaining_minutes > 0 ? 'disabled' : 'required'; ?>>
                                    </div>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                               id="password" name="password" 
                                               <?php echo isset($remaining_minutes) && $remaining_minutes > 0 ? 'disabled' : 'required'; ?>>
                                    </div>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                                </div>
                                
                                <div class="d-grid gap-2 mb-3">
                                    <button type="submit" class="btn btn-primary" 
                                        <?php echo isset($remaining_minutes) && $remaining_minutes > 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                    </button>
                                </div>
                                
                                <div class="text-center mb-3">
                                    <a href="password_reset.php" class="text-decoration-none">Mot de passe oublié ?</a>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <div class="text-center">
                                <p class="mb-0">Nouveau sur <?php echo APP_NAME; ?> ?</p>
                                <a href="register.php" class="btn btn-outline-primary btn-sm mt-2">
                                    <i class="fas fa-user-plus me-1"></i>Créer un compte
                                </a>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="../index.php" class="text-muted text-decoration-none small">
                                    <i class="fas fa-arrow-left me-1"></i>Retour à l'accueil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>