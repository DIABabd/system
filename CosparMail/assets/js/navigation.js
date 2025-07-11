/**
 * COSPAR Mail System - Navigation Helper
 * 
 * Handles navigation functionality including the back button
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle back button functionality
    const backButton = document.getElementById('backButton');
    
    if (backButton) {
        // Add hover effect for better visual feedback
        backButton.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        backButton.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
        
        // Handle click with proper fallback
        backButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if there's history to go back to
            if (window.history.length > 1) {
                window.history.back();
            } else {
                // If no history is available, go to a safe location
                window.location.href = '../../admin/';
            }
        });
        
        // Add initial animation
        setTimeout(function() {
            backButton.classList.add('ready');
        }, 300);
    }

    // Add page load animation
    document.body.classList.add('loaded');
});
