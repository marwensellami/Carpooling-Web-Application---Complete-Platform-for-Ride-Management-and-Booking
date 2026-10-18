<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Journaliser la déconnexion
        logAction($conn, $_SESSION['user_id'], 'user_logout', 'Déconnexion de l\'utilisateur');
        
    } catch (Exception $e) {
        // Continuer même en cas d'erreur de journalisation
        error_log("Erreur journalisation déconnexion: " . $e->getMessage());
    }
}

// Détruire complètement la session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion avec un message
$_SESSION['logout_message'] = "Vous avez été déconnecté avec succès.";
redirect('login.php');
?>