<?php
/**
 * Connexion à la base de données MySQL
 * Marketplace de Covoiturage Tunisie
 */

class Database {
    private $host = "localhost:4306";
    private $db_name = "covoiturage_tunisie";
    private $username = "root";
    private $password = "";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Définir le fuseau horaire de la Tunisie
            $this->conn->exec("SET time_zone = '+01:00'");
            
        } catch(PDOException $exception) {
            error_log("Erreur de connexion: " . $exception->getMessage());
            throw new Exception("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
        
        return $this->conn;
    }
    
    /**
     * Exécute une requête SQL sécurisée
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Erreur requête SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de l'exécution de la requête.");
        }
    }
    
    /**
     * Récupère une seule ligne
     */
    public function fetchSingle($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Récupère plusieurs lignes
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupère la dernière ID insérée
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}

// Fonction utilitaire pour obtenir une instance de connexion
function getDBConnection() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
        $db->getConnection();
    }
    return $db->conn;
}

// Test de connexion (à désactiver en production)
function testConnection() {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        echo "<div style='background: #d4edda; color: #155724; padding: 10px; margin: 10px; border-radius: 5px;'>
                ✅ Connexion à la base de données réussie!
              </div>";
        return true;
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border-radius: 5px;'>
                ❌ Erreur de connexion: " . $e->getMessage() . "
              </div>";
        return false;
    }
}

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>