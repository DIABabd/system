/**
 * COSPAR Mail System - Initialization
 * 
 * Initializes the application and sets up event listeners
 */

/**
 * Initializes the application
 * Sets up event handlers and initial state
 */
function initializeApp() {
    console.log("Initializing application");
    
    // Show all authors (instead of pagination)
    showAllAuthors();

    // Set up external email button
    const openExternalMailBtn = document.getElementById('openExternalMail');
    if (openExternalMailBtn) {
        openExternalMailBtn.addEventListener('click', function() {
            console.log("External Mail button clicked");
            openExternalEmailClient();
        });
    }
    
    // Set up email popup
    setupEmailPopup();

    // Set up email details popup
    setupDetailsPopup();

    // Set up filter change listeners
    const searchBar = document.getElementById('searchBar');
    if (searchBar) {
        searchBar.addEventListener('input', function () {
            filterAuthors();
        });
    }

    const authorFilter = document.getElementById('authorFilter');
    if (authorFilter) {
        authorFilter.addEventListener('change', function () {
            // Implement filter logic based on selection value
            const filterValue = this.value;
            console.log(`Author filter changed to: ${filterValue}`);
            
            const authorCards = document.querySelectorAll('.author-cards .card');
            
            // Reset display first
            authorCards.forEach(card => {
                card.style.display = 'block';
            });
            
            // Apply filter if not "all"
            if (filterValue !== 'all') {
                // This would need actual data attributes on cards for filtering
                // Currently a placeholder for future implementation
                console.log("Filter applied: " + filterValue);
            }
            
            // Then update the filtered IDs
            updateFilteredAuthorIds();
        });
    }
    
    // Set up select all authors button
    const selectAllAuthorsBtn = document.getElementById('selectAllAuthors');
    if (selectAllAuthorsBtn) {
        selectAllAuthorsBtn.addEventListener('click', function() {
            console.log("Select All Authors button clicked");
            displayRightSectionForAll();
        });
    }
    
    // Set up close right section button
    const closeButton = document.getElementById('closeButton');
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            console.log("Close button clicked");
            hideRightSection();
        });
    }
    
    console.log("Application initialization complete");
    
    // Make sure all author card expand buttons work
    document.querySelectorAll('.author-cards .card .expand-button').forEach(button => {
        button.addEventListener('click', function(event) {
            // Prevent any parent form submission
            event.preventDefault();
            
            const card = this.closest('.card');
            const authorId = card.getAttribute('data-author-id');
            const authorName = card.getAttribute('data-name');
            const authorEmail = card.getAttribute('data-email');
            
            if (authorId && authorName && authorEmail) {
                displayRightSectionForAuthor(authorId, authorName, authorEmail);
            }
        });
    });
}

// Initialize the application when the document is fully loaded
document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM fully loaded, initializing app");
    initializeApp();
});