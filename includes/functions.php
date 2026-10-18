<?php
/**
 * Fonctions utilitaires pour le Marketplace de Covoiturage
 */

/**
 * Valider un email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider un numéro de téléphone tunisien
 */
function isValidTunisianPhone($phone) {
    // Formats acceptés : +216 XX XXX XXX, 216 XX XXX XXX, XX XXX XXX
    $pattern = '/^(\+216|216)?\s?[2-9][0-9]{7}$/';
    $cleaned = preg_replace('/\s+/', '', $phone);
    return preg_match($pattern, $cleaned);
}

/**
 * Valider un mot de passe fort
 */
function isStrongPassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    // Doit contenir au moins une majuscule, une minuscule et un chiffre
    if (!preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || 
        !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Générer un token aléatoire
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Valider une date de trajet (au moins 24h à l'avance)
 */
function isValidRideDate($date) {
    $minDate = new DateTime('tomorrow');
    $rideDate = new DateTime($date);
    return $rideDate >= $minDate;
}

/**
 * Calculer l'âge à partir d'une date de naissance
 */
function calculateAge($birthdate) {
    $today = new DateTime();
    $birth = new DateTime($birthdate);
    $age = $today->diff($birth)->y;
    return $age;
}

/**
 * Formater une durée en minutes vers un format lisible
 */
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . 'h' . ($mins > 0 ? $mins . 'min' : '');
    } else {
        return $mins . 'min';
    }
}

/**
 * Calculer la distance estimée entre deux villes (simulation)
 */
function estimateDistance($fromCity, $toCity) {
    // Distances approximatives entre villes tunisiennes (en km)
    $distances = [
        'tunis-sousse' => 140,
        'tunis-sfax' => 270,
        'tunis-bizerte' => 65,
        'tunis-nabeul' => 70,
        'sousse-sfax' => 130,
        'sousse-monastir' => 25,
        'sfax-gabes' => 100
    ];
    
    $key = strtolower($fromCity) . '-' . strtolower($toCity);
    $reverseKey = strtolower($toCity) . '-' . strtolower($fromCity);
    
    if (isset($distances[$key])) {
        return $distances[$key];
    } elseif (isset($distances[$reverseKey])) {
        return $distances[$reverseKey];
    } else {
        // Distance par défaut basée sur la différence de taille des villes
        return rand(50, 300);
    }
}

/**
 * Calculer le temps de trajet estimé
 */
function estimateTravelTime($distance, $averageSpeed = 80) {
    $hours = $distance / $averageSpeed;
    return round($hours * 60); // Retourne en minutes
}

/**
 * Vérifier si un utilisateur peut réserver un trajet
 */
/**
 * Vérifier si un utilisateur peut réserver un trajet
 */
function canUserBookRide($conn, $user_id, $ride_id) {
    try {
        // Vérifier si l'utilisateur est le conducteur
        $stmt = $conn->prepare("SELECT driver_id FROM rides WHERE id = ?");
        $stmt->execute([$ride_id]);
        $ride = $stmt->fetch();
        
        if (!$ride) {
            return ['can_book' => false, 'reason' => 'Trajet non trouvé'];
        }
        
        if ($ride['driver_id'] == $user_id) {
            return ['can_book' => false, 'reason' => 'Vous ne pouvez pas réserver votre propre trajet'];
        }
        
        // Vérifier si l'utilisateur a déjà réservé ce trajet
        $stmt = $conn->prepare("
            SELECT id FROM bookings 
            WHERE ride_id = ? AND passenger_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([$ride_id, $user_id]);
        
        if ($stmt->fetch()) {
            return ['can_book' => false, 'reason' => 'Vous avez déjà réservé ce trajet'];
        }
        
        return ['can_book' => true, 'reason' => ''];
        
    } catch (PDOException $e) {
        error_log("Erreur vérification réservation: " . $e->getMessage());
        return ['can_book' => false, 'reason' => 'Erreur de vérification'];
    }
}

/**
 * Obtenir les statistiques d'un utilisateur
 */
function getUserStats($conn, $user_id) {
    try {
        $stats = [];
        
        // Nombre de trajets publiés
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rides WHERE driver_id = ?");
        $stmt->execute([$user_id]);
        $stats['rides_published'] = $stmt->fetch()['count'];
        
        // Nombre de trajets à venir
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM rides 
            WHERE driver_id = ? AND departure_date >= CURDATE() AND is_active = 1
        ");
        $stmt->execute([$user_id]);
        $stats['upcoming_rides'] = $stmt->fetch()['count'];
        
        // Nombre de réservations faites
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM bookings 
            WHERE passenger_id = ? AND status = 'confirmed'
        ");
        $stmt->execute([$user_id]);
        $stats['bookings_made'] = $stmt->fetch()['count'];
        
        // Nombre de réservations reçues
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM bookings b
            JOIN rides r ON b.ride_id = r.id
            WHERE r.driver_id = ? AND b.status = 'confirmed'
        ");
        $stmt->execute([$user_id]);
        $stats['bookings_received'] = $stmt->fetch()['count'];
        
        // Note moyenne reçue
        $stmt = $conn->prepare("
            SELECT AVG(r.rating) as avg_rating FROM reviews r
            JOIN bookings b ON r.booking_id = b.id
            JOIN rides rd ON b.ride_id = rd.id
            WHERE rd.driver_id = ? AND r.reviewed_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
        $stats['avg_rating'] = round($stmt->fetch()['avg_rating'] ?? 0, 1);
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Erreur statistiques utilisateur: " . $e->getMessage());
        return [
            'rides_published' => 0,
            'upcoming_rides' => 0,
            'bookings_made' => 0,
            'bookings_received' => 0,
            'avg_rating' => 0
        ];
    }
}

/**
 * Envoyer une notification par email
 */
function sendEmailNotification($to, $subject, $message) {
    // En production, utiliser une bibliothèque comme PHPMailer
    // Pour le développement, on simule l'envoi
    
    $headers = "From: " . FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Simuler l'envoi (en développement)
    if (APP_ENV === 'development') {
        error_log("EMAIL SIMULÉ - À: $to, Sujet: $subject");
        return true;
    }
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Créer une notification dans la base de données
 */
function createNotification($conn, $user_id, $title, $message, $type = 'system') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les notifications non lues d'un utilisateur
 */
function getUnreadNotifications($conn, $user_id, $limit = 10) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur récupération notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notification_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Erreur marquage notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Valider un fichier uploadé (image)
 */
function validateUploadedFile($file, $max_size = MAX_FILE_SIZE, $allowed_types = ALLOWED_IMAGE_TYPES) {
    $errors = [];
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'upload du fichier";
        return $errors;
    }
    
    // Vérifier la taille
    if ($file['size'] > $max_size) {
        $errors[] = "Le fichier est trop volumineux (max: " . ($max_size / 1024 / 1024) . "MB)";
    }
    
    // Vérifier le type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = "Type de fichier non autorisé. Types acceptés: " . implode(', ', $allowed_types);
    }
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = "Type MIME du fichier non autorisé";
    }
    
    return $errors;
}

/**
 * Générer un nom de fichier unique
 */
function generateUniqueFilename($original_name, $prefix = '') {
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    return $prefix . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Sauvegarder un fichier uploadé
 */
function saveUploadedFile($file, $target_dir) {
    // Créer le dossier s'il n'existe pas
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $filename = generateUniqueFilename($file['name']);
    $target_path = $target_dir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    }
    
    return false;
}

/**
 * Formater un nombre avec étoiles pour les avis
 */
function generateStarRating($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    // Étoiles pleines
    for ($i = 0; $i < $fullStars; $i++) {
        $stars .= '<i class="fas fa-star text-warning"></i>';
    }
    
    // Demi-étoile
    if ($halfStar) {
        $stars .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    // Étoiles vides
    for ($i = 0; $i < $emptyStars; $i++) {
        $stars .= '<i class="far fa-star text-warning"></i>';
    }
    
    return $stars;
}

/**
 * Vérifier si une session est expirée
 */
function isSessionExpired() {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    $elapsed = time() - $_SESSION['login_time'];
    return $elapsed > SESSION_TIMEOUT;
}

/**
 * Régénérer l'ID de session pour prévenir les attaques de fixation
 */
function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['login_time'] = time();
}

/**
 * Vérifier si l'utilisateur est administrateur
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && (bool)$_SESSION['is_admin'];
}


/**
 * Fonctions utilitaires pour le Marketplace de Covoiturage
 */

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}


function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' DT';
}

function displayPrice($price) {
    return number_format($price, 2) . ' DT';
}

?>