<?php
/**
 * Configuration de l'application
 * Marketplace de Covoiturage Tunisie
 */

// Charger la configuration de la base de données
require_once 'db_connection.php';

// Constantes de l'application
define('APP_NAME', 'Tunisia Carpool');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/marketplace-covoiturage');
define('APP_TIMEZONE', 'Africa/Tunis');

// Paramètres de sécurité
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes
define('PASSWORD_MIN_LENGTH', 8);

// Paramètres des trajets
define('MAX_SEATS_PER_RIDE', 4);
define('MIN_PRICE_PER_SEAT', 1.00);
define('MAX_PRICE_PER_SEAT', 100.00);

// Paramètres de l'upload
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Configuration des emails
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@covoiturage.tn');
define('SMTP_PASSWORD', 'your_password_here');
define('FROM_EMAIL', 'noreply@covoiturage.tn');
define('FROM_NAME', 'Tunisia Carpool');

define('APP_ENV', 'development');

// Villes tunisiennes populaires pour les selects
$tunisian_cities = [
    'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba',
    'Kairouan', 'Kasserine', 'Kébili', 'Le Kef', 'Mahdia', 'La Manouba',
    'Médenine', 'Monastir', 'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana',
    'Sousse', 'Tataouine', 'Tozeur', 'Tunis', 'Zaghouan'
];

// Configuration du site
$site_config = [
    'name' => 'Tunisia Carpool',
    'slogan' => 'Voyagez malin, économisez ensemble',
    'description' => 'Plateforme de covoiturage sécurisée pour la Tunisie',
    'keywords' => 'covoiturage, tunisie, voyage, économie, transport, écologie',
    'author' => 'Tunisia Carpool Team',
    'language' => 'fr',
    'charset' => 'UTF-8'
];

// Configuration des prix
$pricing_config = [
    'currency' => 'DT',
    'currency_symbol' => '₺',
    'default_commission_rate' => 0.10, // 10%
    'min_ride_price' => 1.00,
    'max_ride_price' => 100.00
];

// Fonctions utilitaires globales

/**
 * Rediriger vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Vérifier si l'utilisateur est connecté
 */


/**
 * Vérifier si l'utilisateur est administrateur
 */


/**
 * Générer un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valider un token CSRF
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Nettoyer les données utilisateur
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 Formater un prix en dinars tunisiens
 */
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' DT';
}

/**
 * Formater une date en français
 */
function formatDate($date, $include_time = false) {
    $timestamp = strtotime($date);
    $format = $include_time ? 'd/m/Y à H:i' : 'd/m/Y';
    return date($format, $timestamp);
}

/**
 * Calculer l'âge minimum pour un trajet (24 heures à l'avance)
 */
function getMinRideDate() {
    return date('Y-m-d', strtotime('+1 day'));
}

/**
 * Obtenir les statistiques du site
 */
function getSiteStats($conn) {
    try {
        $stats = [];
        
        // Nombre total d'utilisateurs
        $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total_users'];
        
        // Nombre total de trajets actifs
        $stmt = $conn->prepare("SELECT COUNT(*) as total_rides FROM rides WHERE is_active = 1 AND departure_date >= CURDATE()");
        $stmt->execute();
        $stats['total_rides'] = $stmt->fetch()['total_rides'];
        
        // Nombre total de réservations confirmées
        $stmt = $conn->prepare("SELECT COUNT(*) as total_bookings FROM bookings WHERE status = 'confirmed'");
        $stmt->execute();
        $stats['total_bookings'] = $stmt->fetch()['total_bookings'];
        
        // Économie totale générée (estimation)
        $stmt = $conn->prepare("
            SELECT SUM(total_price) as total_savings 
            FROM bookings 
            WHERE status = 'confirmed'
        ");
        $stmt->execute();
        $stats['total_savings'] = $stmt->fetch()['total_savings'] ?? 0;
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Erreur statistiques: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_rides' => 0,
            'total_bookings' => 0,
            'total_savings' => 0
        ];
    }
}




/**
 * Journaliser les actions importantes
 */
function logAction($conn, $user_id, $action, $details = '') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Erreur journalisation: " . $e->getMessage());
    }
}

// Définir le fuseau horaire
date_default_timezone_set(APP_TIMEZONE);

// Démarrer la bufferisation de sortie
ob_start();

// Test de configuration
if (isset($_GET['test_config']) && $_GET['test_config'] === '1') {
    echo "<div style='background: #e2f0fb; color: #0c5460; padding: 15px; margin: 10px; border-radius: 5px; border-left: 4px solid #17a2b8;'>";
    echo "<h3>🔧 Test de Configuration</h3>";
    echo "<p><strong>Nom de l'application:</strong> " . APP_NAME . "</p>";
    echo "<p><strong>Version:</strong> " . APP_VERSION . "</p>";
    echo "<p><strong>URL:</strong> " . APP_URL . "</p>";
    echo "<p><strong>Fuseau horaire:</strong> " . APP_TIMEZONE . "</p>";
    echo "<p><strong>Sièges max par trajet:</strong> " . MAX_SEATS_PER_RIDE . "</p>";
    echo "</div>";
    
    // Tester la connexion BDD
    testConnection();
}
?>