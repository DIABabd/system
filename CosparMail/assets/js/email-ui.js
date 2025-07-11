/**
 * COSPAR Mail System - Email UI
 * 
 * Handles the display and UI interaction for the email interface
 */

/**
 * Displays the right section for a specific author
 * Updates the UI to show communication with a specific author
 */
function displayRightSectionForAuthor(authorId, authorName, authorEmail) {
    console.log(`Displaying right section for author: ${authorName}`);
    // Display the right section
    const rightSection = document.querySelector('.rightSection');
    if (!rightSection) {
        console.error("Right section element not found!");
        return;
    }
    
    rightSection.style.display = 'block';

    // Update author info in the header
    const authorNameElem = document.getElementById('authorName');
    if (authorNameElem) {
        authorNameElem.textContent = authorName;
    }

    // Set up the email form with this author's information
    const receiverIdElem = document.getElementById('receiverId');
    const recipientElem = document.getElementById('recipient');
    const bulkEmailElem = document.getElementById('bulk_email');
    const authorIdsElem = document.getElementById('author_ids');
    const groupEmailElem = document.getElementById('group_email');
    
    // IMPORTANT: Reset ALL flags for individual emails
    if (receiverIdElem) receiverIdElem.value = authorId;
    if (recipientElem) recipientElem.value = authorEmail;
    if (bulkEmailElem) bulkEmailElem.value = "false";  // Make sure this is false
    if (authorIdsElem) authorIdsElem.value = "";
    if (groupEmailElem) groupEmailElem.value = "false"; // Make sure this is false

    // Load email history for this author via AJAX
    if (typeof window.loadEmailHistory === 'function') {
        window.loadEmailHistory(authorId, 'author');
    } else {
        console.error("loadEmailHistory function not found!");
    }
}

/**
 * Displays the right section for sending to all filtered authors
 * This handles the "Select All Authors" button functionality for hotline mail
 */
function displayRightSectionForAll() {
    console.log("Displaying right section for all authors - HOTLINE MODE");
    // Display the right section
    const rightSection = document.querySelector('.rightSection');
    if (!rightSection) {
        console.error("Right section element not found!");
        return;
    }
    
    rightSection.style.display = 'block';

    // Update info to indicate hotline email mode
    const authorNameElem = document.getElementById('authorName');
    if (authorNameElem) {
        authorNameElem.textContent = "All Filtered Authors (Hotline)";
    }

    // Collect all visible author IDs
    let visibleAuthors = [];
    let authorCount = 0;
    
    try {
        if (typeof window.getVisibleAuthorIds === 'function') {
            visibleAuthors = window.getVisibleAuthorIds();
            authorCount = visibleAuthors.length;
        } else {
            console.error("getVisibleAuthorIds function not found!");
        }
    } catch (e) {
        console.error("Error getting visible author IDs:", e);
    }

    // Load group email history
    if (typeof window.loadEmailHistory === 'function') {
        window.loadEmailHistory("All Authors", 'group');
    } else {
        console.error("loadEmailHistory function not found!");
    }

    // Set up the form for hotline email (group email)
    const recipientElem = document.getElementById('recipient');
    const bulkEmailElem = document.getElementById('bulk_email');
    const authorIdsElem = document.getElementById('author_ids');
    const groupEmailElem = document.getElementById('group_email');
    
    if (recipientElem) recipientElem.value = "All Filtered Authors (Hotline)";
    if (bulkEmailElem) bulkEmailElem.value = "true";
    if (authorIdsElem) authorIdsElem.value = visibleAuthors.join(',');
    if (groupEmailElem) groupEmailElem.value = "true"; // This is critical for using processGroupEmail
    
    console.log("Hotline email setup complete - group_email set to true");
}

/**
 * Hides the right section
 * Called when the close button is clicked
 */
function hideRightSection() {
    console.log("Hiding right section");
    const rightSection = document.querySelector('.rightSection');
    if (rightSection) {
        rightSection.style.display = 'none';
    } else {
        console.error("Right section element not found!");
    }
}

/**
 * Opens the user's default email client with recipient's email prefilled
 * Called when "Use My Email Client" button is clicked
 * Fixed to properly handle BCC for bulk emails
 */
function openExternalEmailClient() {
    console.log("Opening external email client");
    const recipientElem = document.getElementById('recipient');
    const subjectElem = document.getElementById('subject');
    
    if (recipientElem) {
        let subject = subjectElem ? subjectElem.value : '';
        
        // Check if this is a bulk email or group email
        const bulkEmailElem = document.getElementById('bulk_email');
        const groupEmailElem = document.getElementById('group_email');
        
        let mailtoUrl = 'mailto:';
        let params = [];
        
        if ((bulkEmailElem && bulkEmailElem.value === "true") || (groupEmailElem && groupEmailElem.value === "true")) {
            console.log("Processing bulk/group email for external client");
            
            // Get all visible author emails for BCC
            const authorCards = document.querySelectorAll('.author-cards .card');
            let authorEmails = [];
            
            authorCards.forEach(card => {
                // Only include cards that are currently visible (not filtered out)
                if (card.style.display !== 'none') {
                    const email = card.getAttribute('data-email');
                    if (email) {
                        authorEmails.push(email);
                    }
                }
            });
            
            console.log(`Found ${authorEmails.length} author emails for BCC`);
            
            if (authorEmails.length > 0) {
                // Use BCC parameter in mailto URL
                params.push('bcc=' + encodeURIComponent(authorEmails.join(',')));
                console.log("BCC emails: " + authorEmails.join(','));
            } else {
                alert('No authors found for bulk email');
                return;
            }
        } else {
            // Single recipient email
            const recipient = recipientElem.value;
            if (recipient) {
                mailtoUrl += encodeURIComponent(recipient);
                console.log("Single recipient: " + recipient);
            } else {
                alert('Please select a recipient first');
                return;
            }
        }
        
        // Add subject if available
        if (subject) {
            params.push('subject=' + encodeURIComponent(subject));
        }
        
        // Construct final mailto URL
        if (params.length > 0) {
            mailtoUrl += '?' + params.join('&');
        }
        
        console.log("Final mailto URL: " + mailtoUrl);
        
        // Open the default email client
        window.location.href = mailtoUrl;
    } else {
        alert('Please select a recipient first');
    }
}

// Make functions globally available
window.displayRightSectionForAuthor = displayRightSectionForAuthor;
window.displayRightSectionForAll = displayRightSectionForAll;
window.hideRightSection = hideRightSection;
window.openExternalEmailClient = openExternalEmailClient;