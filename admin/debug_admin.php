<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Admin</title>
    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
</head>
<body class=\"container mt-5\">
    <h1>Debug Admin Access</h1>";

if (!isLoggedIn()) {
    echo "<div class='alert alert-danger'>Non connecté</div>";
    echo "<a href='../auth/login.php' class='btn btn-primary'>Se connecter</a>";
    exit();
}

echo "<div class='card mb-3'>
        <div class='card-header'>Informations de Session</div>
        <div class='card-body'>
            <pre>" . print_r($_SESSION, true) . "</pre>
        </div>
      </div>";

echo "<div class='card mb-3'>
        <div class='card-header'>Test isAdmin()</div>
        <div class='card-body'>
            <p>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</p>
            <p>isAdmin(): " . (isAdmin() ? 'true' : 'false') . "</p>
            <p>Session is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'non défini') . "</p>
        </div>
      </div>";

// Vérifier directement dans la base de données
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, email, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_db = $stmt->fetch();
    
    echo "<div class='card mb-3'>
            <div class='card-header'>Informations Base de Données</div>
            <div class='card-body'>
                <pre>" . print_r($user_db, true) . "</pre>
            </div>
          </div>";
    
    if ($user_db && $user_db['is_admin']) {
        echo "<div class='alert alert-success'>✓ Compte administrateur confirmé dans la base de données</div>";
        
        // Forcer la session admin
        $_SESSION['is_admin'] = true;
        echo "<div class='alert alert-info'>✓ Session is_admin forcée à true</div>";
        
        // Tester l'accès aux pages admin
        echo "<div class='mt-4'>
                <h4>Test des pages admin :</h4>
                <a href='admin_dashboard.php' class='btn btn-primary me-2'>Admin Dashboard</a>
                <a href='manage_users.php' class='btn btn-primary me-2'>Gérer utilisateurs</a>
                <a href='manage_rides.php' class='btn btn-primary me-2'>Gérer trajets</a>
                <a href='admin_reports.php' class='btn btn-primary'>Rapports</a>
              </div>";
    } else {
        echo "<div class='alert alert-warning'>⚠ Ce compte n'est pas administrateur dans la base de données</div>";
        
        // Option pour devenir admin (débug seulement)
        if ($user_db['email'] === 'admin@covoiturage.tn') {
            echo "<form method='POST' class='mt-3'>
                    <input type='hidden' name='make_admin' value='1'>
                    <button type='submit' class='btn btn-warning'>Forcer les droits admin pour ce compte</button>
                  </form>";
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_admin'])) {
                $stmt = $conn->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['is_admin'] = true;
                echo "<div class='alert alert-success mt-3'>✓ Droits admin accordés. Rechargez la page.</div>";
                echo "<meta http-equiv='refresh' content='2'>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Erreur base de données: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>