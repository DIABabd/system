<?php
/**
 * Email History Helper Functions
 * 
 * Functions for retrieving and displaying email history
 */

// Define constant to prevent direct access to templates
define('COSPAR_MAIL', true);

/**
 * Retrieves and displays email history sent by the current user to an author
 * Only shows emails sent BY the current user (outbound only)
 * 
 * @param int $authorId The ID of the author
 */
function getEmailHistory($authorId)
{
    // Log for debugging
    error_log("EmailHistory: Getting history for author ID: $authorId");

    // Input validation
    if ($authorId <= 0) {
        error_log("EmailHistory: Invalid author ID");
        echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid author ID</p></div>';
        return;
    }

    // Get current user's ID
    $currentUserId = getUserID();
    error_log("EmailHistory: Current user ID: $currentUserId");

    // Query to get all emails SENT BY current user TO the specified author
    // IMPORTANT: Include attachments column
    $query = "SELECT m.id, m.subject, m.content, m.sender_id, m.recipient_id, m.cc, m.bcc, m.attachments,
              FROM_UNIXTIME(m.created_at) as sent_date, 
              UNIX_TIMESTAMP(NOW()) - m.created_at as time_ago,
              sender.first as sender_first, sender.last as sender_last,
              recipient.first as recipient_first, recipient.last as recipient_last
              FROM mails m
              JOIN user sender ON m.sender_id = sender.id
              JOIN user recipient ON m.recipient_id = recipient.id
              WHERE m.sender_id = $currentUserId AND m.recipient_id = $authorId
              ORDER BY m.created_at DESC";

    error_log("EmailHistory: Query: $query");

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    // Handle database query errors
    if (!$result) {
        error_log("EmailHistory: Database error: " . mysqli_error($GLOBALS["___mysqli_ston"]));
        echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Database error: ' . mysqli_error($GLOBALS["___mysqli_ston"]) . '</p></div>';
        return;
    }

    // Check if any emails were found
    $numRows = mysqli_num_rows($result);
    error_log("EmailHistory: Found $numRows emails");

    if ($numRows === 0) {
        echo '<div class="no-emails-message">
                <i class="fas fa-envelope-open"></i>
                <p>No emails sent to this author yet.</p>
                <p>Click the "New Mail" button to send your first email.</p>
              </div>';
        return;
    }

    // Start the email list container
    echo '<div class="email-list">';

    // Loop through each email and use the template to display it
    while ($email = mysqli_fetch_assoc($result)) {
        include __DIR__ . '/../templates/email-list-item.php';
    }

    // Close the email list container
    echo '</div>';
}

/**
 * Format a timestamp as a "time ago" string
 * 
 * @param int $seconds Number of seconds ago
 * @param string $formattedDate Full formatted date as fallback
 * @return string Formatted time string
 */
function formatTimeAgo($seconds, $formattedDate)
{
    if ($seconds < 60) {
        return 'just now';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($seconds < 604800) {
        $days = floor($seconds / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return $formattedDate; // Just use the full date for older emails
    }
}