/**
 * COSPAR Mail System - Email Operations
 */

/**
 * Loads email history for a specific author or group
 */
function loadEmailHistory(id, type = 'author') {
    console.log(`Loading email history for ${type} with ID: ${id}`);
    const historyContent = document.getElementById('emailHistoryContent');
    
    if (!historyContent) {
        console.error("Email history content element not found!");
        return;
    }

    // Show loading message while fetching data
    historyContent.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Loading email history...</p>';

    // Determine the correct endpoint and parameters
    let url;
    if (type === 'group') {
        url = 'index.php?action=get_group_history&group_name=' + encodeURIComponent(id);
    } else {
        url = 'index.php?action=get_history&author_id=' + id;
    }

    // Create an AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);

    // Handle successful response
    xhr.onload = function () {
        if (xhr.status === 200) {
            // Display the returned HTML
            historyContent.innerHTML = xhr.responseText;
            console.log("Email history loaded successfully, type: " + type);

            // Use direct DOM event attachment for better reliability
            setupViewDetailsButtons(type);
            
            // If this is a group email and we have the count element, update it
            if (type === 'group' && document.getElementById('bulk_email') && 
                document.getElementById('bulk_email').value === "true") {
                try {
                    const visibleAuthors = getVisibleAuthorIds();
                    const authorCount = visibleAuthors.length;
                    
                    // Add a count to the UI if it doesn't exist
                    if (!document.querySelector('.author-count')) {
                        historyContent.innerHTML +=
                            '<div class="author-count">' +
                            '<p><strong>' + authorCount + '</strong> authors will receive this message</p>' +
                            '</div>';
                    }
                } catch (e) {
                    console.error("Error updating author count:", e);
                }
            }
        } else {
            // Show error message if request failed
            historyContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Error loading email history</p></div>';
            console.error("Failed to load email history:", xhr.status, xhr.statusText);
        }
    };

    // Handle network errors
    xhr.onerror = function () {
        historyContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Network error occurred</p></div>';
        console.error("Network error loading email history");
    };

    // Send the request
    xhr.send();
}

/**
 * Set up view details buttons with enhanced debugging
 */
function setupViewDetailsButtons(currentType) {
    console.log("Setting up view details buttons, current type: " + currentType);
    
    // Enhanced console logging for debugging
    console.log("Current DOM context:", document.body.innerHTML.substring(0, 200) + "...");
    
    const detailButtons = document.querySelectorAll('.view-details-btn');
    console.log(`Found ${detailButtons.length} detail buttons to set up`);
    
    // Log details of each button
    detailButtons.forEach((button, index) => {
        console.log(`Button ${index} details:`, {
            emailId: button.getAttribute('data-email-id'),
            dataType: button.getAttribute('data-type'),
            html: button.outerHTML
        });
    });
    
    // Remove existing event listeners and set up new ones
    detailButtons.forEach(button => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        const dataType = newButton.getAttribute('data-type');
        const emailId = newButton.getAttribute('data-email-id');
        console.log(`Setting up button for email ID ${emailId} with type ${dataType || 'not set'}`);
        
        // Use a more direct approach
        newButton.onclick = function(event) {
            event.preventDefault();
            console.log(`CLICK DETECTED: View details for email ID: ${emailId}, data-type: ${dataType || 'not set'}`);
            
            try {
                // Use data-type if available, otherwise infer from current context
                const emailType = dataType || (currentType === 'group' ? 'group' : 'regular');
                console.log(`Final email type used: ${emailType}`);
                
                // Call viewEmailDetails with explicit parameters
                window.viewEmailDetails(emailId, emailType);
            } catch (error) {
                console.error("Error in view details click handler:", error);
            }
            
            return false;
        };
    });
    
    console.log("All view details buttons are now set up");
}

/**
 * View detailed content of a specific email with enhanced debugging
 */
function viewEmailDetails(emailId, type = 'regular') {
    console.log(`*** VIEWING EMAIL DETAILS - ID: ${emailId}, TYPE: ${type} ***`);
    const detailsModal = document.getElementById('emailDetailsModal');
    const detailsContent = document.getElementById('emailDetailsContent');
    
    if (!detailsModal || !detailsContent) {
        console.error("Email details modal elements not found!");
        return;
    }

    // Show loading message and make modal visible
    detailsContent.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Loading email details...</p>';
    detailsModal.style.display = 'flex';

    // Create an AJAX request with the appropriate action
    const xhr = new XMLHttpRequest();
    const action = type === 'group' ? 'get_group_details' : 'get_details';
    const url = `index.php?action=${action}&email_id=${emailId}`;
    
    console.log(`Requesting email details from URL: ${url}`);
    xhr.open('GET', url, true);

    // Add detailed logging for the XHR process
    xhr.onreadystatechange = function() {
        console.log(`XHR state changed: readyState=${xhr.readyState}, status=${xhr.status}`);
    };

    // Handle successful response
    xhr.onload = function () {
        console.log(`XHR completed: Response status=${xhr.status}, responseText length=${xhr.responseText.length}`);
        console.log(`Response text preview: ${xhr.responseText.substring(0, 200)}...`);
        
        if (xhr.status === 200) {
            // Display the returned HTML
            detailsContent.innerHTML = xhr.responseText;
            console.log("Email details loaded successfully");
            
            // No need to set up reply button event handlers since there's no reply button
            
        } else {
            // Show error message if request failed
            detailsContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Error loading email details: Status ' + xhr.status + '</p></div>';
            console.error("Failed to load email details:", xhr.status, xhr.statusText);
        }
    };

    // Handle network errors
    xhr.onerror = function (error) {
        console.error("XHR error:", error);
        detailsContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Network error occurred</p></div>';
    };

    // Send the request
    xhr.send();
    console.log("XHR request sent");
}

// Make sure these functions are globally available
window.loadEmailHistory = loadEmailHistory;
window.setupViewDetailsButtons = setupViewDetailsButtons;
window.viewEmailDetails = viewEmailDetails;