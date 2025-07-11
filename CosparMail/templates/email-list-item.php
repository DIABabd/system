<?php
/**
 * Email List Item Template with Attachment Indicators
 * 
 * Template for displaying a single email in the conversation list
 * Only displays outbound (sent) emails
 * 
 * @param array $email - Email data
 * @param int $currentUserId - Current user ID
 */

// Prevent direct access
if (!defined('COSPAR_MAIL')) {
    exit('Direct access not permitted');
}

// Format date and time for display
$formattedDate = date('M j, Y g:i A', strtotime($email['sent_date']));

// All emails are outbound (sent) since we only show sent emails
$directionIcon = '<i class="fas fa-paper-plane text-primary"></i>'; // Sent icon
$emailClass = 'email-item outbound';

// Create a preview of content (first 100 characters)
$contentPreview = substr(strip_tags($email['content']), 0, 100);
if (strlen(strip_tags($email['content'])) > 100) {
    $contentPreview .= '...'; // Add ellipsis if content is truncated
}

// Format sender and recipient names with HTML escaping for security
$senderName = htmlspecialchars($email['sender_first'] . ' ' . $email['sender_last']);
$recipientName = htmlspecialchars($email['recipient_first'] . ' ' . $email['recipient_last']);

// Handle CC and BCC if present
$hasCc = !empty($email['cc']);
$hasBcc = !empty($email['bcc']);
$ccBccInfo = '';

if ($hasCc) {
    $ccBccInfo .= '<div class="email-cc"><strong>Cc:</strong> ' . htmlspecialchars($email['cc']) . '</div>';
}

if ($hasBcc) { // Always show BCC since all emails are sent by current user
    $ccBccInfo .= '<div class="email-bcc"><strong>Bcc:</strong> ' . htmlspecialchars($email['bcc']) . '</div>';
}

// Parse attachments if they exist
$attachments = [];
$hasAttachments = false;
if (!empty($email['attachments'])) {
    $attachmentData = json_decode($email['attachments'], true);
    if (is_array($attachmentData) && !empty($attachmentData)) {
        $attachments = $attachmentData;
        $hasAttachments = true;
    }
}

// Create a user-friendly "time ago" string
$timeAgo = formatTimeAgo($email['time_ago'], $formattedDate);
?>

<div class="<?php echo $emailClass; ?>">
    <div class="email-header">
        <div class="email-direction"><?php echo $directionIcon; ?></div>
        <div class="email-subject">
            <?php echo htmlspecialchars($email['subject']); ?>
            <?php if ($hasAttachments): ?>
                <i class="fas fa-paperclip attachment-indicator"
                    title="<?php echo count($attachments); ?> attachment(s)"></i>
            <?php endif; ?>
        </div>
        <div class="email-time"><?php echo $timeAgo; ?></div>
    </div>

    <div class="email-preview"><?php echo htmlspecialchars($contentPreview); ?></div>

    <div class="email-participants">
        <span><strong>From:</strong> <?php echo $senderName; ?></span>
        <span><strong>To:</strong> <?php echo $recipientName; ?></span>
        <?php echo $ccBccInfo; ?>
    </div>

    <?php if ($hasAttachments): ?>
        <div class="email-attachments-preview">
            <div class="attachments-mini-list">
                <?php foreach (array_slice($attachments, 0, 3) as $index => $attachment): ?>
                    <?php
                    $fileName = htmlspecialchars($attachment['original_name']);
                    $fileSize = formatFileSize($attachment['file_size']);
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $iconClass = getAttachmentIcon($extension);
                    ?>
                    <div class="attachment-mini-item" title="<?php echo $fileName; ?> (<?php echo $fileSize; ?>)">
                        <i class="<?php echo $iconClass; ?>"></i>
                        <span><?php echo strlen($fileName) > 15 ? substr($fileName, 0, 12) . '...' : $fileName; ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if (count($attachments) > 3): ?>
                    <div class="attachment-mini-item more-attachments">
                        <i class="fas fa-plus-circle"></i>
                        <span>+<?php echo count($attachments) - 3; ?> more</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="email-footer">
        <button class="view-details-btn" data-email-id="<?php echo $email['id']; ?>">
            <i class="fas fa-eye"></i> View Details
        </button>
    </div>
</div>