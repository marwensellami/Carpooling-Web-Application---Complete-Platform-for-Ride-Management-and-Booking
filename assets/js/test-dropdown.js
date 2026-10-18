// Test pour vérifier que Bootstrap est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('Bootstrap dropdown test:');
    
    // Vérifier si Bootstrap est chargé
    if (typeof bootstrap !== 'undefined') {
        console.log('✅ Bootstrap est chargé');
        
        // Vérifier les dropdowns
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        console.log(`✅ ${dropdowns.length} dropdown(s) trouvé(s)`);
        
        dropdowns.forEach((dropdown, index) => {
            console.log(`Dropdown ${index + 1}:`, dropdown);
        });
    } else {
        console.log('❌ Bootstrap non chargé');
    }
    
    // Forcer l'ouverture des dropdowns au clic (fallback)
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            const dropdown = this.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                // Cacher tous les autres menus
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    m.style.display = 'none';
                });
                menu.style.display = 'block';
            }
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    // Fermer les dropdowns en cliquant ailleurs
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    });
});