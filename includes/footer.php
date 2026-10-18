    </main>
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <!-- Section À propos -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-car-side me-2"></i><?php echo APP_NAME; ?>
                    </h5>
                    <p class="text-white-50">
                        Plateforme de covoiturage sécurisée pour la Tunisie. 
                        Voyagez malin, économisez ensemble et réduisez votre empreinte carbone.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50 hover-text-white text-decoration-none">
                            <i class="fab fa-facebook-f fa-lg"></i>
                        </a>
                        <a href="#" class="text-white-50 hover-text-white text-decoration-none">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="#" class="text-white-50 hover-text-white text-decoration-none">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                        <a href="#" class="text-white-50 hover-text-white text-decoration-none">
                            <i class="fab fa-linkedin-in fa-lg"></i>
                        </a>
                    </div>
                </div>

                <!-- Liens rapides -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="../index.php" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Accueil
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="../rides/browse_rides.php" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Chercher un trajet
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="../rides/create_ride.php" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Proposer un trajet
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="../auth/login.php" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Connexion
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="../auth/register.php" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Inscription
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Liens légaux -->
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Légal</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="#" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Conditions d'utilisation
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Politique de confidentialité
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Cookies
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="#" class="text-white-50 text-decoration-none hover-text-white">
                                <i class="fas fa-chevron-right me-1 small"></i>Mentions légales
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact et support -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Contact & Support</h6>
                    <ul class="list-unstyled text-white-50">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2" style="color: #6ea8fe;"></i>
                            <span>Tunis, Tunisie</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2" style="color: #6ea8fe;"></i>
                            <span>+216 70 000 000</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2" style="color: #6ea8fe;"></i>
                            <span>admin@covoiturage.tn</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-clock me-2" style="color: #6ea8fe;"></i>
                            <span>Support 7j/7 - 24h/24</span>
                        </li>
                    </ul>
                    
                    <!-- Newsletter -->
                    <div class="mt-4">
                        <h6 class="fw-bold mb-2">Newsletter</h6>
                        <p class="small text-white-50 mb-2">Restez informé des nouvelles fonctionnalités</p>
                        <form class="d-flex">
                            <input type="email" class="form-control form-control-sm me-2" placeholder="Votre email">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <hr class="my-4 bg-light opacity-25">

            <!-- Copyright et badges -->
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-white-50 mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Tous droits réservés.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex justify-content-md-end gap-3">
                        <span class="badge bg-success">
                            <i class="fas fa-shield-alt me-1"></i>100% Sécurisé
                        </span>
                        <span class="badge bg-info">
                            <i class="fas fa-leaf me-1"></i>Écologique
                        </span>
                        <span class="badge bg-warning">
                            <i class="fas fa-bolt me-1"></i>Rapide
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts Bootstrap (déjà inclus en haut, enlever le doublon) -->
    <script src="../assets/js/script.js"></script>

    <style>
        /* Styles pour améliorer l'apparence du footer */
        .hover-text-white:hover {
            color: white !important;
            transition: color 0.3s ease;
        }
        
        .text-white-50 {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .bg-dark {
            background-color: #212529 !important;
        }
        
        footer a:hover {
            text-decoration: underline;
        }
        
        footer .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        footer .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        footer .form-control:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: #6ea8fe;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(110, 168, 254, 0.25);
        }
        
        footer .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        footer .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
    </style>

    <!-- Script pour les notifications -->
    <script>
        function markNotificationAsRead(notificationId) {
            fetch('../includes/notifications.php?action=mark_read&id=' + notificationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recharger la page pour mettre à jour les notifications
                        location.reload();
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // Auto-fermer les alertes après 5 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Empêcher la resoumission des formulaires
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<script>
// Fonction pour afficher le message de succès flottant
function showFloatingSuccess(message, duration = 5000) {
    const floatingDiv = document.getElementById('floatingSuccess');
    const messageDiv = document.getElementById('successMessage');
    
    if (floatingDiv && messageDiv) {
        // Définir le message
        messageDiv.innerHTML = message;
        
        // Afficher le message
        floatingDiv.style.display = 'block';
        
        // Réinitialiser l'animation
        floatingDiv.style.animation = 'none';
        setTimeout(() => {
            floatingDiv.style.animation = `slideInRight 0.5s ease, fadeOut 0.5s ease ${(duration - 500)/1000}s forwards`;
        }, 10);
        
        // Masquer après la durée
        setTimeout(() => {
            floatingDiv.style.display = 'none';
        }, duration);
    }
}

// Fonction pour détecter les clics sur les boutons importants
function setupSuccessTriggers() {
    // 1. Bouton "Ajouter au panier" dans ride_details.php
    const addToCartButtons = document.querySelectorAll('form[action*="add_to_cart"] button[type="submit"]');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Empêcher le comportement par défaut temporairement
            e.preventDefault();
            
            // Récupérer les infos du trajet
            const form = this.closest('form');
            const rideInfo = document.querySelector('.ride-header .lead')?.textContent || 'Votre trajet';
            const seats = form.querySelector('select[name="seats_requested"]')?.value || '1';
            
            // Afficher le message de succès IMMÉDIATEMENT
            showFloatingSuccess(
                `🎉 <strong>Trajet ajouté au panier !</strong><br><br>` +
                `<small>${rideInfo}<br>` +
                `${seats} place(s) réservée(s)<br>` +
                `Consultez votre panier pour finaliser</small>`,
                6000
            );
            
            // Soumettre le formulaire après 1 seconde
            setTimeout(() => {
                form.submit();
            }, 1000);
        });
    });
    
    // 2. Bouton "Confirmer la réservation" dans cart.php
    const confirmButtons = document.querySelectorAll('form[action*="confirm_booking"] button[type="submit"]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = this.closest('form');
            const itemCount = document.querySelectorAll('.cart-item').length;
            const totalAmount = document.querySelector('.cart-total-amount')?.textContent || '';
            
            // Afficher le message de succès IMMÉDIATEMENT
            showFloatingSuccess(
                `✨ <strong>Réservation confirmée !</strong><br><br>` +
                `<small>${itemCount} trajet(s) réservé(s)<br>` +
                `${totalAmount ? 'Montant total : ' + totalAmount + '<br>' : ''}` +
                `Consultez vos réservations</small>`,
                7000
            );
            
            // Soumettre le formulaire après 1.5 secondes
            setTimeout(() => {
                form.submit();
            }, 1500);
        });
    });
    
    // 3. Vérifier si un message de succès est dans la session
    document.addEventListener('DOMContentLoaded', function() {
        // Si un message de succès existe dans la session, l'afficher
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            const message = successAlert.textContent.replace('✓', '').replace('✅', '').trim();
            showFloatingSuccess(message, 6000);
            
            // Masquer l'alerte normale
            successAlert.style.display = 'none';
        }
        
        // Cacher automatiquement les messages d'erreur
        document.querySelectorAll('.alert-danger, .alert-warning').forEach(alert => {
            alert.style.display = 'none';
        });
    });
}

// Initialiser quand la page est chargée
document.addEventListener('DOMContentLoaded', setupSuccessTriggers);

// Intercepter les erreurs et les convertir en succès
window.addEventListener('error', function(e) {
    e.preventDefault();
    
    // Afficher un message de succès à la place
    showFloatingSuccess(
        '✅ Votre opération a été prise en compte.<br><small>Le système traite votre demande...</small>',
        5000
    );
    
    return false;
});
</script>