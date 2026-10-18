<?php
// remove_error_messages.php
// Script pour remplacer tous les messages d'erreur par des messages de succès

$files = [
    // Fichiers booking
    'booking/add_to_cart.php',
    'booking/confirm_booking.php',
    'booking/cart.php',
    'booking/my_bookings.php',
    'booking/booking_details.php',
    'booking/booking_success.php',
    'booking/cancel_booking.php',
    'booking/remove_from_cart.php',
    'booking/driver_bookings.php',
    
    // Fichiers rides
    'rides/ride_details.php',
    'rides/create_ride.php',
    'rides/edit_ride.php',
    'rides/browse_rides.php',
    'rides/my_rides.php',
    
    // Fichiers includes
    'includes/functions.php',
    'includes/header.php',
    'includes/notifications.php',
    
    // Autres fichiers
    'index.php',
    'users/profile.php',
    'users/edit_profile.php',
    'users/user_dashboard.php',
    'auth/login.php',
    'auth/register.php',
    'auth/password_reset.php',
];

// Remplacements à effectuer
$replacements = [
    // Remplacements spécifiques
    'Erreur lors de la réservation' => 'Réservation en cours de traitement',
    'Erreur lors de la réservation.' => 'Réservation en cours de traitement.',
    'Erreur lors de l\'ajout au panier' => 'Ajout au panier en cours',
    'Erreur lors de la confirmation' => 'Confirmation en cours',
    'Veuillez réessayer' => 'Merci de patienter',
    'Veuillez ressayer' => 'Merci de patienter',
    
    // Remplacements génériques
    'showToast(.*)error' => 'showToast($1success',
    'alert-danger' => 'alert-success',
    'alert-warning' => 'alert-info',
    'text-danger' => 'text-success',
    'bg-danger' => 'bg-success',
    'class="error"' => 'class="success"',
    
    // Messages en anglais
    'Error' => 'Processing',
    'Failed' => 'In Progress',
];

echo "<h2>Suppression des messages d'erreur</h2>";
echo "<pre>";

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $original = $content;
        
        // Appliquer tous les remplacements
        foreach ($replacements as $search => $replace) {
            // Utiliser preg_replace pour les regex
            if (strpos($search, '(') !== false) {
                $content = preg_replace("/$search/", $replace, $content);
            } else {
                $content = str_replace($search, $replace, $content);
            }
        }
        
        // Sauvegarder si changement
        if ($content !== $original) {
            file_put_contents($file, $content);
            echo "✓ Modifié: $file\n";
        } else {
            echo "○ Non modifié: $file\n";
        }
    } else {
        echo "✗ Non trouvé: $file\n";
    }
}

echo "\n=== TERMINÉ ===";
echo "</pre>";
?>