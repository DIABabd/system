<?php
/**
 * Email Details Helper Functions with Attachment Support
 * 
 * Functions for retrieving and displaying email details
 */

// Define constant to prevent direct access to templates
if (!defined('COSPAR_MAIL')) {
    define('COSPAR_MAIL', true);
}

/**
 * Retrieves and displays detailed view of a specific email
 * Only shows emails SENT BY the current user
 * 
 * @param int $emailId The ID of the email to display
 */
function getEmailDetails($emailId)
{
    // Input validation
    if ($emailId <= 0) {
        echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid email ID</p></div>';
        return;
    }

    $currentUserId = getUserID();

    // Modified query to only allow sender access (only outbound emails) and include attachments
    // Fixed: Removed the trailing comma after m.attachments
    $query = "SELECT m.id, m.subject, m.content, m.sender_id, m.recipient_id, m.cc, m.bcc, m.attachments, 
              FROM_UNIXTIME(m.created_at) as sent_date,
              sender.first as sender_first, sender.last as sender_last, sender.mail as sender_email,
              recipient.first as recipient_first, recipient.last as recipient_last, recipient.mail as recipient_email
              FROM mails m
              JOIN user sender ON m.sender_id = sender.id
              JOIN user recipient ON m.recipient_id = recipient.id
              WHERE m.id = $emailId 
              AND m.sender_id = $currentUserId"; // Only allow sender to view

    // Log query for debugging
    error_log("Email details query: " . $query);

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    // Log any SQL error
    if (!$result) {
        error_log("SQL Error in getEmailDetails: " . mysqli_error($GLOBALS["___mysqli_ston"]));
    }

    // Check if email exists and user has permission
    if (!$result || mysqli_num_rows($result) === 0) {
        echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Email not found or access denied</p></div>';
        return;
    }

    $email = mysqli_fetch_assoc($result);

    // Debug the email data
    error_log("Email data retrieved: " . json_encode($email));

    $isGroupEmail = false;

    include __DIR__ . '/../templates/email-details.php';
}

/**
 * Helper function to format file size
 */
function formatFileSize($bytes)
{
    if ($bytes == 0)
        return '0 Bytes';

    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB');
    $i = floor(log($bytes) / log($k));

    return round(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
}

/**
 * Helper function to get appropriate icon for file type
 */
function getAttachmentIcon($extension)
{
    $iconMap = array(
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image'
    );

    return isset($iconMap[$extension]) ? $iconMap[$extension] : 'fas fa-file';
}