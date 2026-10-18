<?php
session_start();


// Définir la fonction manuellement si elle n'existe pas
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

echo "<pre>";
echo "=== DEBUG ADMIN ACCESS ===\n";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Session is_admin: " . ($_SESSION['is_admin'] ?? 'NOT SET') . "\n";
echo "isLoggedIn(): " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "\n";
echo "isAdmin(): " . (isAdmin() ? 'TRUE' : 'FALSE') . "\n";

// Vérifier manuellement dans la base de données
if (isset($_SESSION['user_id'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT id, email, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        echo "Database check:\n";
        echo "User ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "is_admin in DB: " . $user['is_admin'] . "\n";
    } catch (Exception $e) {
        echo "DB Error: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";

// Test de redirection
if (isLoggedIn() && isAdmin()) {
    echo "<p style='color: green;'>✓ Accès admin autorisé</p>";
    echo "<a href='admin_dashboard.php'>Accéder au tableau de bord admin</a>";
} else {
    echo "<p style='color: red;'>✗ Accès admin refusé</p>";
}
?>