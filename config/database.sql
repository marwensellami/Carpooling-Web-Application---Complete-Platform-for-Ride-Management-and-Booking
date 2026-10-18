-- Marketplace de Covoiturage Tunisie - Structure de la base de données

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Base de données : `covoiturage_tunisie`
CREATE DATABASE IF NOT EXISTS `covoiturage_tunisie` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `covoiturage_tunisie`;

-- Table des utilisateurs
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `is_admin` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des véhicules
CREATE TABLE `vehicles` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `brand` VARCHAR(50) NOT NULL,
  `model` VARCHAR(50) NOT NULL,
  `year` YEAR NOT NULL,
  `color` VARCHAR(30) NOT NULL,
  `license_plate` VARCHAR(20) NOT NULL,
  `has_air_conditioning` BOOLEAN DEFAULT FALSE,
  `max_passengers` TINYINT DEFAULT 4,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des trajets
CREATE TABLE `rides` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `driver_id` INT NOT NULL,
  `vehicle_id` INT NOT NULL,
  `departure_city` VARCHAR(100) NOT NULL,
  `arrival_city` VARCHAR(100) NOT NULL,
  `departure_address` TEXT NOT NULL,
  `arrival_address` TEXT NOT NULL,
  `departure_date` DATE NOT NULL,
  `departure_time` TIME NOT NULL,
  `available_seats` TINYINT NOT NULL CHECK (`available_seats` BETWEEN 1 AND 4),
  `price_per_seat` DECIMAL(8,2) NOT NULL,
  `description` TEXT,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des réservations
CREATE TABLE `bookings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ride_id` INT NOT NULL,
  `passenger_id` INT NOT NULL,
  `seats_booked` TINYINT NOT NULL CHECK (`seats_booked` BETWEEN 1 AND 4),
  `total_price` DECIMAL(8,2) NOT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
  `booking_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` TIMESTAMP NULL,
  `cancelled_at` TIMESTAMP NULL,
  FOREIGN KEY (`ride_id`) REFERENCES `rides`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`passenger_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_ride_passenger` (`ride_id`, `passenger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table du panier (session de réservation temporaire)
CREATE TABLE `cart` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `ride_id` INT NOT NULL,
  `seats_requested` TINYINT NOT NULL CHECK (`seats_requested` BETWEEN 1 AND 4),
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ride_id`) REFERENCES `rides`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des avis et notations
CREATE TABLE `reviews` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `booking_id` INT NOT NULL,
  `reviewer_id` INT NOT NULL,
  `reviewed_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des notifications planifiées (REMPLACE l'ancienne table notifications)
CREATE TABLE `scheduled_notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('ride', 'booking', 'system', 'alert') NOT NULL,
  `scheduled_for` DATETIME NOT NULL,
  `sent_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_scheduled_notifications_time` (`scheduled_for`, `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour les réinitialisations de mot de passe
CREATE TABLE `password_resets` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_password_resets_token` (`token`),
  INDEX `idx_password_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour les journaux d'activité
CREATE TABLE `activity_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_activity_logs_user` (`user_id`),
  INDEX `idx_activity_logs_action` (`action`),
  INDEX `idx_activity_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table pour les préférences utilisateur
CREATE TABLE `user_preferences` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `departure_city` VARCHAR(100),
  `arrival_city` VARCHAR(100),
  `max_price` DECIMAL(8,2),
  `preferred_time` TIME,
  `notify_new_rides` BOOLEAN DEFAULT TRUE,
  `notify_booking_updates` BOOLEAN DEFAULT TRUE,
  `notify_promotions` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_preferences` (`user_id`),
  INDEX `idx_user_preferences_cities` (`departure_city`, `arrival_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des données initiales

-- Compte administrateur (mot de passe : Admin123!)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `phone`, `is_admin`) VALUES
('Admin', 'System', 'admin@covoiturage.tn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 20 000 000', TRUE);

-- Villes tunisiennes populaires pour les tests
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `phone`) VALUES
('Mohamed', 'Ben Ali', 'mohamed.benali@email.tn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 20 123 456'),
('Fatma', 'Chouchen', 'fatma.chouchen@email.tn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 98 765 432'),
('Karim', 'Trabelsi', 'karim.trabelsi@email.tn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+216 55 444 333');

-- Véhicules d'exemple
INSERT INTO `vehicles` (`user_id`, `brand`, `model`, `year`, `color`, `license_plate`, `has_air_conditioning`, `max_passengers`) VALUES
(2, 'Renault', 'Clio', 2020, 'Blanc', '1234 TU 1234', TRUE, 4),
(3, 'Peugeot', '208', 2019, 'Noir', '5678 TU 5678', TRUE, 4),
(4, 'Hyundai', 'i10', 2021, 'Bleu', '9012 TU 9012', FALSE, 3);

-- Trajets d'exemple
INSERT INTO `rides` (`driver_id`, `vehicle_id`, `departure_city`, `arrival_city`, `departure_address`, `arrival_address`, `departure_date`, `departure_time`, `available_seats`, `price_per_seat`, `description`) VALUES
(2, 1, 'Tunis', 'Sousse', 'Avenue Habib Bourguiba, Tunis', 'Place Farhat Hached, Sousse', '2024-01-15', '08:00:00', 3, 15.00, 'Trajet direct Tunis-Sousse, voiture confortable avec climatisation'),
(3, 2, 'Sfax', 'Gabès', 'Route de l''Aéroport, Sfax', 'Centre ville Gabès', '2024-01-16', '07:30:00', 2, 12.50, 'Départ tôt le matin, arrivée prévue vers 9h30'),
(4, 3, 'Tunis', 'Nabeul', 'Menzah 6, Tunis', 'Centre ville Nabeul', '2024-01-14', '16:00:00', 3, 8.00, 'Trajet après-midi, idéal pour le weekend');

-- Index pour optimiser les performances
CREATE INDEX idx_rides_departure ON `rides`(`departure_city`, `departure_date`);
CREATE INDEX idx_rides_arrival ON `rides`(`arrival_city`, `departure_date`);
CREATE INDEX idx_bookings_status ON `bookings`(`status`);
CREATE INDEX idx_users_email ON `users`(`email`);
CREATE INDEX idx_scheduled_notifications_user ON `scheduled_notifications`(`user_id`);

COMMIT;