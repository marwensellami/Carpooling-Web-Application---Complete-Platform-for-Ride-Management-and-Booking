<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

$errors = [];
$success = false;
$step = $_GET['step'] ?? 'request'; // request, verify, reset

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Étape 1 : Demande de réinitialisation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            $errors['email'] = "L'email est obligatoire";
        } elseif (!isValidEmail($email)) {
            $errors['email'] = "Format d'email invalide";
        }
        
        if (empty($errors)) {
            // Vérifier si l'email existe
            $stmt = $conn->prepare("SELECT id, first_name, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Générer un token de réinitialisation
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Supprimer les anciens tokens
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Insérer le nouveau token
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);
                
                // Envoyer l'email de réinitialisation
                $reset_link = APP_URL . "/auth/password_reset.php?step=verify&token=" . $token . "&email=" . urlencode($email);
                
                $email_sent = sendEmailNotification(
                    $email,
                    "Réinitialisation de votre mot de passe - " . APP_NAME,
                    "
                    <h2>Réinitialisation de mot de passe</h2>
                    <p>Bonjour {$user['first_name']},</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe sur " . APP_NAME . ".</p>
                    <p>Cliquez sur le lien ci-dessous pour créer un nouveau mot de passe :</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='$reset_link' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                            Réinitialiser mon mot de passe
                        </a>
                    </p>
                    <p><strong>Ce lien expirera dans 1 heure.</strong></p>
                    <p>Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet email.</p>
                    <br>
                    <p><small>L'équipe " . APP_NAME . "</small></p>
                    "
                );
                
                if ($email_sent) {
                    $success = "Un email de réinitialisation a été envoyé à $email";
                    logAction($conn, $user['id'], 'password_reset_requested', 'Demande de réinitialisation de mot de passe');
                } else {
                    $errors['general'] = "Erreur lors de l'envoi de l'email. Veuillez réessayer.";
                }
            } else {
                // Ne pas révéler si l'email existe ou non
                $success = "Si l'email existe, un lien de réinitialisation a été envoyé.";
            }
        }
    }
    
    // Étape 2 : Vérification du token
    if ($step === 'verify') {
        $token = $_GET['token'] ?? '';
        $email = $_GET['email'] ?? '';
        
        if (empty($token) || empty($email)) {
            $errors['general'] = "Lien de réinitialisation invalide";
            $step = 'request';
        } else {
            // Vérifier le token
            $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
            $stmt->execute([$email, $token]);
            $reset_request = $stmt->fetch();
            
            if (!$reset_request) {
                $errors['general'] = "Lien de réinitialisation invalide ou expiré";
                $step = 'request';
            } else {
                // Stocker les informations de vérification en session
                $_SESSION['reset_token'] = $token;
                $_SESSION['reset_email'] = $email;
                $step = 'reset';
            }
        }
    }
    
    // Étape 3 : Réinitialisation du mot de passe
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $token = $_SESSION['reset_token'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';
        
        // Vérifier la session
        if (empty($token) || empty($email)) {
            $errors['general'] = "Session de réinitialisation invalide";
            $step = 'request';
        } else {
            // Re-vérifier le token
            $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
            $stmt->execute([$email, $token]);
            $reset_request = $stmt->fetch();
            
            if (!$reset_request) {
                $errors['general'] = "Session de réinitialisation expirée";
                $step = 'request';
                unset($_SESSION['reset_token'], $_SESSION['reset_email']);
            } else {
                // Validation du mot de passe
                if (empty($new_password)) {
                    $errors['new_password'] = "Le nouveau mot de passe est obligatoire";
                } elseif (!isStrongPassword($new_password)) {
                    $errors['new_password'] = "Le mot de passe doit contenir au moins " . PASSWORD_MIN_LENGTH . " caractères avec majuscule, minuscule et chiffre";
                }
                
                if ($new_password !== $confirm_password) {
                    $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
                }
                
                if (empty($errors)) {
                    // Mettre à jour le mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
                    $stmt->execute([$hashed_password, $email]);
                    
                    // Supprimer le token utilisé
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    // Nettoyer la session
                    unset($_SESSION['reset_token'], $_SESSION['reset_email']);
                    
                    // Récupérer l'ID utilisateur pour le journal
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        logAction($conn, $user['id'], 'password_reset_success', 'Mot de passe réinitialisé avec succès');
                    }
                    
                    $success = "Votre mot de passe a été réinitialisé avec succès";
                    $step = 'complete';
                }
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur réinitialisation mot de passe: " . $e->getMessage());
    $errors['general'] = "Une erreur est survenue. Veuillez réessayer.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du Mot de Passe - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .password-reset-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 450px;
            width: 100%;
        }
        .logo-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
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
    <div class="password-reset-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card reset-card">
                        <!-- En-tête -->
                        <div class="logo-section text-center">
                            <i class="fas fa-lock fa-3x mb-3"></i>
                            <h4><?php echo APP_NAME; ?></h4>
                            <p class="mb-0">
                                <?php if ($step === 'request'): ?>
                                    Réinitialisation du mot de passe
                                <?php elseif ($step === 'reset'): ?>
                                    Nouveau mot de passe
                                <?php else: ?>
                                    Mot de passe réinitialisé
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Contenu du formulaire -->
                        <div class="card-body p-4">
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                
                                <?php if ($step === 'complete'): ?>
                                    <div class="text-center mt-3">
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors['general'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $errors['general']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Étape 1 : Demande de réinitialisation -->
                            <?php if ($step === 'request'): ?>
                                <form method="POST" action="?step=request">
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Adresse email</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                   id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mb-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Envoyer le lien de réinitialisation
                                        </button>
                                    </div>
                                    
                                    <div class="text-center">
                                        <a href="login.php" class="text-decoration-none">
                                            <i class="fas fa-arrow-left me-1"></i>Retour à la connexion
                                        </a>
                                    </div>
                                </form>
                            
                            <!-- Étape 2 : Réinitialisation du mot de passe -->
                            <?php elseif ($step === 'reset'): ?>
                                <form method="POST" action="?step=reset">
                                    <div class="mb-4">
                                        <p class="text-muted">
                                            Créez un nouveau mot de passe sécurisé pour votre compte.
                                        </p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="new_password" name="new_password" required>
                                        </div>
                                        <div class="password-strength mt-1 d-none" id="passwordStrength"></div>
                                        <small class="form-text text-muted">
                                            Minimum <?php echo PASSWORD_MIN_LENGTH; ?> caractères avec majuscule, minuscule et chiffre
                                        </small>
                                        <?php if (isset($errors['new_password'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['new_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                                   id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback d-block"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mb-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Réinitialiser le mot de passe
                                        </button>
                                    </div>
                                </form>
                            
                            <!-- Étape 3 : Confirmation -->
                            <?php elseif ($step === 'complete'): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                    <h5 class="text-success">Mot de passe réinitialisé !</h5>
                                    <p class="text-muted">
                                        Votre mot de passe a été modifié avec succès.
                                    </p>
                                    <div class="d-grid gap-2 mt-4">
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informations de sécurité -->
                    <div class="text-center mt-3">
                        <small class="text-white">
                            <i class="fas fa-shield-alt me-1"></i>
                            Protégé par <?php echo APP_NAME; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation du mot de passe en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('passwordStrength');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    
                    if (password.length === 0) {
                        strengthBar.className = 'password-strength mt-1 d-none';
                        return;
                    }
                    
                    strengthBar.className = 'password-strength mt-1';
                    
                    let strength = 0;
                    if (password.length >= 8) strength++;
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[a-z]/.test(password)) strength++;
                    if (/[0-9]/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    if (strength <= 2) {
                        strengthBar.className = 'password-strength mt-1 strength-weak';
                    } else if (strength <= 4) {
                        strengthBar.className = 'password-strength mt-1 strength-medium';
                    } else {
                        strengthBar.className = 'password-strength mt-1 strength-strong';
                    }
                });
            }
            
            // Confirmation du mot de passe
            if (confirmInput && passwordInput) {
                confirmInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirm = this.value;
                    
                    if (confirm && password !== confirm) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });
    </script>
</body>
</html>