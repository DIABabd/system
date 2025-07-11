<?php
/**
 * Email Details Template with Downloadable Attachments
 * 
 * Template for displaying detailed email information in a popup
 * 
 * @param array $email - Email data
 * @param int $currentUserId - Current user ID
 */

// Prevent direct access
if (!defined('COSPAR_MAIL')) {
    exit('Direct access not permitted');
}

// Format data for display
$formattedDate = date('F j, Y g:i A', strtotime($email['sent_date']));

// All emails are outbound (sent) since we only show sent emails
$directionLabel = '<span class="badge outbound">Sent</span>';

// Escape HTML in user data for security
$senderName = htmlspecialchars($email['sender_first'] . ' ' . $email['sender_last']);
$recipientName = htmlspecialchars($email['recipient_first'] . ' ' . $email['recipient_last']);
$senderEmail = htmlspecialchars($email['sender_email']);
$recipientEmail = htmlspecialchars($email['recipient_email']);
$subject = htmlspecialchars($email['subject']);

// Parse attachments if they exist
$attachments = [];
if (!empty($email['attachments'])) {
    // Log for debugging
    error_log("Email details template: Processing attachments JSON: " . $email['attachments']);

    $attachmentData = json_decode($email['attachments'], true);
    if (is_array($attachmentData)) {
        $attachments = $attachmentData;
        error_log("Email details template: Found " . count($attachments) . " attachments");
    } else {
        error_log("Email details template: Failed to parse attachments JSON");
    }
}
?>

<div class="email-full-details">
    <div class="email-detail-header">
        <h2><?php echo $subject; ?> <?php echo $directionLabel; ?></h2>
        <div class="email-meta">
            <div><strong>Date:</strong> <?php echo $formattedDate; ?></div>
            <div><strong>From:</strong> <?php echo $senderName; ?> (<?php echo $senderEmail; ?>)</div>
            <div><strong>To:</strong> <?php echo $recipientName; ?> (<?php echo $recipientEmail; ?>)</div>

            <?php if (!empty($email['cc'])): ?>
                <div><strong>CC:</strong> <?php echo htmlspecialchars($email['cc']); ?></div>
            <?php endif; ?>

            <?php if (!empty($email['bcc'])): ?>
                <div><strong>BCC:</strong> <?php echo htmlspecialchars($email['bcc']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="email-detail-content">
        <?php echo nl2br(htmlspecialchars($email['content'])); ?>
    </div>

    <?php if (!empty($attachments)): ?>
        <div class="email-attachments">
            <h4><i class="fas fa-paperclip"></i> Attachments (<?php echo count($attachments); ?>)</h4>
            <div class="attachment-list">
                <?php foreach ($attachments as $index => $attachment): ?>
                    <?php
                    $fileName = htmlspecialchars($attachment['original_name']);
                    $fileSize = formatFileSize($attachment['file_size']);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $iconClass = getAttachmentIcon($extension);

                    // Create download URL
                    $downloadUrl = 'index.php?action=download_attachment&email_id=' . $email['id'] . '&attachment_id=' . $index;
                    ?>
                    <a href="<?php echo $downloadUrl; ?>" class="attachment-item downloadable"
                        title="Click to download <?php echo $fileName; ?> (<?php echo $fileSize; ?>)" target="_blank">
                        <i class="<?php echo $iconClass; ?>"></i>
                        <span class="attachment-name"><?php echo $fileName; ?></span>
                        <small class="attachment-size">(<?php echo $fileSize; ?>)</small>
                        <i class="fas fa-download download-icon"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer removed - no reply button needed for sent emails -->
</div>