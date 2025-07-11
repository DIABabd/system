/**
 * COSPAR Mail System - Author Management (Clean Version)
 * 
 * Handles author selection and display functionality
 * OLD FILTERING LOGIC REMOVED - Now uses FilterWizard
 */

/**
 * Display right section for a specific author
 */
function displayRightSectionForAuthor(authorId, authorName, authorEmail) {
    console.log(`Displaying right section for author: ${authorName} (ID: ${authorId})`);
    
    // Show the right section
    const rightSection = document.getElementById('rightSection');
    if (rightSection) {
        rightSection.style.display = 'block';
    }

    // Update author information
    const authorNameSpan = document.getElementById('authorName');
    if (authorNameSpan) {
        authorNameSpan.textContent = authorName;
    }

    // Set hidden form fields for single author email
    const recipientIdField = document.getElementById('recipient_id');
    const recipientNameField = document.getElementById('recipient_name');
    const recipientEmailField = document.getElementById('recipient_email');
    const bulkEmailField = document.getElementById('bulk_email');

    if (recipientIdField) recipientIdField.value = authorId;
    if (recipientNameField) recipientNameField.value = authorName;
    if (recipientEmailField) recipientEmailField.value = authorEmail;
    if (bulkEmailField) bulkEmailField.value = "false";

    // **NEW: Hide the group conversation manager**
    if (typeof hideGroupConversationManager === 'function') {
        hideGroupConversationManager();
    }

    // Show individual email history
    const emailHistorySection = document.querySelector('.email-history-section');
    if (emailHistorySection) {
        emailHistorySection.style.display = 'block';
    }

    // Load email history for this author
    loadEmailHistory(authorId, 'author');

    console.log("Right section displayed for individual author");
}

/**
 * Display right section for all visible authors (bulk email)
 */
function displayRightSectionForAll() {
    console.log("Displaying right section for all visible authors (bulk email)");

    // Show the right section
    const rightSection = document.getElementById('rightSection');
    if (rightSection) {
        rightSection.style.display = 'block';
    }

    // Update author information for bulk mode
    const authorNameSpan = document.getElementById('authorName');
    const visibleCount = getVisibleAuthorIds().length;
    if (authorNameSpan) {
        authorNameSpan.textContent = `All Filtered Authors (${visibleCount} recipients)`;
    }

    // Set form fields for bulk email
    const recipientIdField = document.getElementById('recipient_id');
    const recipientNameField = document.getElementById('recipient_name');
    const recipientEmailField = document.getElementById('recipient_email');
    const bulkEmailField = document.getElementById('bulk_email');

    if (recipientIdField) recipientIdField.value = '';
    if (recipientNameField) recipientNameField.value = 'All Authors';
    if (recipientEmailField) recipientEmailField.value = '';
    if (bulkEmailField) bulkEmailField.value = "true";

    // **NEW: Show the group conversation manager**
    if (typeof showGroupConversationManager === 'function') {
        showGroupConversationManager();
    }

    // Hide individual email history, show group management
    const emailHistorySection = document.querySelector('.email-history-section');
    if (emailHistorySection) {
        emailHistorySection.style.display = 'none';
    }

    console.log("Right section displayed for bulk email with conversation manager");
}

/**
 * Hide the right section
 */
function hideRightSection() {
    const rightSection = document.getElementById('rightSection');
    if (rightSection) {
        rightSection.style.display = 'none';
    }
    console.log("Right section hidden");
}

/**
 * Get IDs of all currently visible authors (for bulk email)
 */
function getVisibleAuthorIds() {
    const visibleCards = document.querySelectorAll('.author-cards .card[data-author-id]:not([style*="display: none"])');
    const authorIds = [];
    
    visibleCards.forEach(card => {
        const authorId = card.getAttribute('data-author-id');
        if (authorId) {
            authorIds.push(authorId);
        }
    });
    
    console.log(`Found ${authorIds.length} visible authors`);
    return authorIds;
}

/**
 * Get email addresses of all currently visible authors
 */
function getVisibleAuthorEmails() {
    const visibleCards = document.querySelectorAll('.author-cards .card[data-email]:not([style*="display: none"])');
    const emails = [];
    
    visibleCards.forEach(card => {
        const email = card.getAttribute('data-email');
        if (email && email.trim()) {
            emails.push(email.trim());
        }
    });
    
    console.log(`Found ${emails.length} visible author emails`);
    return emails;
}

/**
 * Show all authors (remove any display:none styling)
 */
function showAllAuthors() {
    const authorCards = document.querySelectorAll('.author-cards .card');
    authorCards.forEach(card => {
        card.style.display = 'block';
    });
    console.log("All authors are now visible");
}

/**
 * Update filtered author IDs for bulk operations
 * This function is called after filtering to update which authors are included in bulk operations
 */
function updateFilteredAuthorIds() {
    // This function is called by the filter wizard after applying filters
    // It ensures that bulk email operations only include currently visible authors
    console.log("Filtered author IDs updated");
    
    // If we're in bulk mode, update the recipient count
    const bulkEmailField = document.getElementById('bulk_email');
    if (bulkEmailField && bulkEmailField.value === "true") {
        const visibleCount = getVisibleAuthorIds().length;
        const authorNameSpan = document.getElementById('authorName');
        if (authorNameSpan) {
            authorNameSpan.textContent = `All Filtered Authors (${visibleCount} recipients)`;
        }
    }
}

/**
 * LEGACY: Reset all filters and show all authors
 */
function clearAllFiltersLegacy() {
    // Clear search bar
    const searchBar = document.getElementById('searchBar');
    if (searchBar) {
        searchBar.value = '';
    }
    
    // Reset filter wizard
    if (typeof filterWizard !== 'undefined') {
        filterWizard.reset();
    }
    
    // Show all authors
    showAllAuthors();
    
    // Update filtered IDs
    updateFilteredAuthorIds();
    
    console.log("All filters cleared");
}

/**
 * Get author data for the compose form
 */
function getAuthorDataForCompose() {
    const bulkEmailField = document.getElementById('bulk_email');
    const isBulkEmail = bulkEmailField && bulkEmailField.value === "true";
    
    if (isBulkEmail) {
        // Return data for all visible authors
        return {
            isBulk: true,
            authorIds: getVisibleAuthorIds(),
            emails: getVisibleAuthorEmails(),
            count: getVisibleAuthorIds().length
        };
    } else {
        // Return data for single author
        const recipientIdField = document.getElementById('recipient_id');
        const recipientEmailField = document.getElementById('recipient_email');
        
        return {
            isBulk: false,
            authorId: recipientIdField ? recipientIdField.value : '',
            email: recipientEmailField ? recipientEmailField.value : '',
            count: 1
        };
    }
}