<?php
session_start();
require_once '../config/db_connection.php';
require_once '../config/app_settings.php';
require_once '../includes/functions.php';

// Fonction pour vérifier si c'est une requête AJAX
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Fonction pour retourner une réponse JSON
function sendJsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Vérifier la connexion
if (!isLoggedIn()) {
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'Veuillez vous connecter pour modifier votre panier');
    }
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('../auth/login.php');
}

// Vérifier les données POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'Méthode non autorisée');
    }
    $_SESSION['error'] = "Méthode non autorisée";
    redirect('cart.php');
}

if (!isset($_POST['cart_id']) || !is_numeric($_POST['cart_id'])) {
    if (isAjaxRequest()) {
        sendJsonResponse(false, 'ID du panier invalide');
    }
    $_SESSION['error'] = "ID du panier invalide";
    redirect('cart.php');
}

$cart_id = (int)$_POST['cart_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    $isAjax = isAjaxRequest();
    
    // 1. Récupérer les informations de l'élément avant suppression
    $stmt = $conn->prepare("
        SELECT c.*, r.departure_city, r.arrival_city, r.price_per_seat, r.departure_date
        FROM cart c
        JOIN rides r ON c.ride_id = r.id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$cart_id, $_SESSION['user_id']]);
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        $message = "Élément du panier non trouvé ou vous n'avez pas les droits pour le supprimer";
        if ($isAjax) {
            sendJsonResponse(false, $message);
        } else {
            $_SESSION['error'] = $message;
            redirect('cart.php');
        }
    }
    
    // 2. Supprimer l'élément du panier
    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$cart_id, $_SESSION['user_id']]);
    
    if ($result && $stmt->rowCount() > 0) {
        // 3. Mettre à jour le compteur de panier dans la session
        if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0) {
            $_SESSION['cart_count']--;
        } else {
            // Recalculer le compteur si nécessaire
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $count = $stmt->fetch();
            $_SESSION['cart_count'] = $count['count'] ?? 0;
        }
        
        // 4. Journaliser l'action
        if (function_exists('logAction')) {
            logAction($conn, $_SESSION['user_id'], 'cart_remove', 
                "Élément #$cart_id supprimé du panier (trajet #{$cart_item['ride_id']}: {$cart_item['departure_city']} → {$cart_item['arrival_city']})");
        }
        
        // 5. Préparer le message de succès
        $successMessage = "✅ Trajet retiré du panier avec succès<br>";
        $successMessage .= "<strong>" . htmlspecialchars($cart_item['departure_city']) . " → " . htmlspecialchars($cart_item['arrival_city']) . "</strong><br>";
        $successMessage .= "<small>" . $cart_item['seats_requested'] . " place(s) • " . number_format($cart_item['price_per_seat'], 2) . " DT/place</small>";
        
        // 6. Récupérer les nouvelles statistiques du panier
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as item_count,
                COALESCE(SUM(c.seats_requested * r.price_per_seat), 0) as total_amount
            FROM cart c
            JOIN rides r ON c.ride_id = r.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_stats = $stmt->fetch();
        
        // 7. Retourner la réponse
        if ($isAjax) {
            $responseData = [
                'cart_id' => $cart_id,
                'cart_count' => $_SESSION['cart_count'],
                'cart_stats' => [
                    'item_count' => $cart_stats['item_count'],
                    'total_amount' => $cart_stats['total_amount']
                ]
            ];
            sendJsonResponse(true, $successMessage, $responseData);
        } else {
            $_SESSION['success'] = $successMessage;
            redirect('cart.php');
        }
        
    } else {
        $message = "Échec de la suppression. L'élément n'existe peut-être plus.";
        if ($isAjax) {
            sendJsonResponse(false, $message);
        } else {
            $_SESSION['error'] = $message;
            redirect('cart.php');
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur PDO suppression panier: " . $e->getMessage());
    
    $errorMessage = "Erreur de base de données lors de la suppression";
    if (isAjaxRequest()) {
        sendJsonResponse(false, $errorMessage);
    } else {
        $_SESSION['error'] = $errorMessage;
        redirect('cart.php');
    }
    
} catch (Exception $e) {
    error_log("Erreur suppression panier: " . $e->getMessage());
    
    $errorMessage = "Une erreur inattendue s'est produite lors de la suppression";
    if (isAjaxRequest()) {
        sendJsonResponse(false, $errorMessage);
    } else {
        $_SESSION['error'] = $errorMessage;
        redirect('cart.php');
    }
}
?>