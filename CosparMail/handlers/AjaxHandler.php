<?php
/**
 * Enhanced AJAX Handler with Filter-Based Conversation Support
 * 
 * Handles AJAX requests for filter-based email conversations
 */

class AjaxHandler
{
    public function processRequest()
    {
        // Set appropriate headers based on action type
        $action = $_GET['action'] ?? '';

        // Actions that return HTML (not JSON)
        $htmlActions = ['get_group_history', 'get_history', 'get_details', 'get_group_details', 'get_group_email_history'];

        if (!in_array($action, $htmlActions)) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
        }

        try {
            switch ($action) {
                // New filter-based conversation actions
                case 'get_filtered_conversations':
                    $this->getFilteredConversations();
                    break;

                case 'get_filtered_email_history':
                    $this->getFilteredEmailHistory();
                    break;

                case 'send_group_email':
                    $this->sendGroupEmailWithFilters();
                    break;

                // Legacy action names that your JavaScript is using
                case 'get_group_history':
                case 'get_group_email_history':
                    $this->getGroupEmailHistory();
                    break;

                case 'get_history':
                    $this->getIndividualEmailHistory();
                    break;

                case 'get_details':
                    $this->getIndividualEmailDetails();
                    break;

                case 'get_group_details':
                    $this->getGroupEmailDetails();
                    break;

                case 'get_email_details':
                    $this->getEmailDetails();
                    break;

                default:
                    $this->sendError('Unknown action: ' . htmlspecialchars($action));
                    break;
            }
        } catch (Exception $e) {
            error_log("AJAX Handler error: " . $e->getMessage());
            $this->sendError('Server error occurred');
        }
    }

    /**
     * Get all filtered conversations for current user
     */
    private function getFilteredConversations()
    {
        $userId = getUserID();

        require_once __DIR__ . "/GroupMailHandler.php";
        $handler = new GroupMailHandler();

        $conversations = $handler->getFilteredConversations($userId);

        echo json_encode([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

    /**
     * Get email history for a specific filter combination
     */
    private function getFilteredEmailHistory()
    {
        $userId = getUserID();
        $filterSignature = $_GET['filter_signature'] ?? '';

        if (empty($filterSignature)) {
            $this->sendError('Filter signature is required');
            return;
        }

        require_once __DIR__ . "/GroupMailHandler.php";
        $handler = new GroupMailHandler();

        $emails = $handler->getFilteredEmailHistory($userId, $filterSignature);

        // Get conversation name from the first email's filter criteria
        $conversationName = 'All Filtered Authors';
        $filterCriteria = [];

        if (!empty($emails)) {
            $filterCriteria = $emails[0]['filter_criteria'];
            $conversationName = $this->createConversationName($filterCriteria);
        }

        echo json_encode([
            'success' => true,
            'emails' => $emails,
            'conversation_name' => $conversationName,
            'filter_criteria' => $filterCriteria
        ]);
    }

    /**
     * Send group email with filter criteria
     */
    private function sendGroupEmailWithFilters()
    {
        // Get current user ID
        $userId = getUserID();

        // Get form data
        $subject = $_POST['subject'] ?? '';
        $body = $_POST['body'] ?? '';
        $cc = $_POST['cc'] ?? null;
        $bcc = $_POST['bcc'] ?? null;

        // Get recipient IDs and filter criteria
        $authorIds = $_POST['author_ids'] ?? '';
        $recipientIds = !empty($authorIds) ? explode(',', $authorIds) : [];

        // Get current filter criteria from the form or session
        $filterCriteria = [
            'author_type' => $_POST['filter_author_type'] ?? 'all',
            'presentation_type' => $_POST['filter_presentation_type'] ?? 'all',
            'has_presentation' => $_POST['filter_has_presentation'] ?? 'all'
        ];

        // Handle file attachments - REMOVED FileUploadHandler dependency
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            // Simple file handling without external class
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['attachments']['name'][$i];
                    $tmpName = $_FILES['attachments']['tmp_name'][$i];
                    $fileSize = $_FILES['attachments']['size'][$i];
                    $mimeType = $_FILES['attachments']['type'][$i];

                    // Generate unique filename
                    $storedName = time() . '_' . $i . '_' . $originalName;
                    $uploadPath = $uploadDir . $storedName;

                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $attachments[] = [
                            'original_name' => $originalName,
                            'stored_name' => $storedName,
                            'file_size' => $fileSize,
                            'mime_type' => $mimeType,
                            'tmp_name' => $uploadPath
                        ];
                    }
                }
            }
        }

        // Validate input
        if (empty($subject) || empty($body)) {
            $this->sendError('Subject and message are required');
            return;
        }

        if (empty($recipientIds)) {
            $this->sendError('No recipients specified');
            return;
        }

        // Send the email
        require_once __DIR__ . "/GroupMailHandler.php";
        $handler = new GroupMailHandler();

        $result = $handler->sendGroupEmailWithFilters(
            $userId,
            $recipientIds,
            $subject,
            $body,
            $cc,
            $bcc,
            $attachments,
            $filterCriteria
        );

        echo json_encode($result);
    }

    /**
     * Get group email history (legacy support) - Returns HTML
     */
    private function getGroupEmailHistory()
    {
        $groupName = $_GET['group_name'] ?? 'All Authors';
        $userId = getUserID();

        require_once __DIR__ . "/GroupMailHandler.php";
        $groupMailHandler = new GroupMailHandler();

        // Use the new filtered conversation method instead
        $conversations = $groupMailHandler->getFilteredConversations($userId);

        // For legacy support, show all emails from all conversations
        $allEmails = [];
        foreach ($conversations as $conversation) {
            $emails = $groupMailHandler->getFilteredEmailHistory($userId, $conversation['filter_signature']);
            $allEmails = array_merge($allEmails, $emails);
        }

        // Sort by creation time descending
        usort($allEmails, function ($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });

        if (empty($allEmails)) {
            echo '<div class="no-emails-message">
                    <i class="fas fa-envelope-open"></i>
                    <p>No group emails sent yet.</p>
                    <p>Click the "New Mail" button to send a group email.</p>
                  </div>';
            return;
        }

        echo '<div class="email-list">';

        foreach ($allEmails as $email) {
            $formattedDate = date('M j, Y g:i A', $email['created_at']);
            $timeAgo = $this->formatTimeAgo(time() - $email['created_at'], $formattedDate);

            $contentPreview = substr(strip_tags($email['content']), 0, 100);
            if (strlen(strip_tags($email['content'])) > 100) {
                $contentPreview .= '...';
            }

            $senderName = htmlspecialchars($email['sender_first'] . ' ' . $email['sender_last']);

            $ccBccInfo = '';
            if (!empty($email['cc'])) {
                $ccBccInfo .= '<div class="email-cc"><strong>Cc:</strong> ' . htmlspecialchars($email['cc']) . '</div>';
            }
            if (!empty($email['bcc'])) {
                $ccBccInfo .= '<div class="email-bcc"><strong>Bcc:</strong> ' . htmlspecialchars($email['bcc']) . '</div>';
            }

            echo '<div class="email-item outbound">
                    <div class="email-header">
                        <div class="email-direction"><i class="fas fa-paper-plane text-primary"></i></div>
                        <div class="email-subject">' . htmlspecialchars($email['subject']) . '</div>
                        <div class="email-time">' . $timeAgo . '</div>
                    </div>
                    <div class="email-preview">' . htmlspecialchars($contentPreview) . '</div>
                    <div class="email-participants">
                        <span><strong>From:</strong> ' . $senderName . '</span>
                        <span><strong>To:</strong> All Authors (' . $email['recipient_count'] . ' recipients)</span>
                        ' . $ccBccInfo . '
                    </div>
                    <div class="email-footer">
                        <button class="view-details-btn" data-email-id="' . $email['id'] . '" data-type="group" 
                            onclick="window.viewEmailDetails(' . $email['id'] . ', \'group\')">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                  </div>';
        }

        echo '</div>';
    }

    /**
     * Get individual email history - Returns HTML
     */
    private function getIndividualEmailHistory()
    {
        $authorId = intval($_GET['author_id'] ?? 0);

        if ($authorId <= 0) {
            echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid author ID</p></div>';
            return;
        }

        require_once __DIR__ . "/../helpers/EmailHistory.php";
        getEmailHistoryForAuthor($authorId);
    }

    /**
     * Get individual email details - Returns HTML
     */
    private function getIndividualEmailDetails()
    {
        $emailId = intval($_GET['email_id'] ?? 0);

        if ($emailId <= 0) {
            echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid email ID</p></div>';
            return;
        }

        require_once __DIR__ . "/../helpers/EmailDetails.php";
        getEmailDetails($emailId);
    }

    /**
     * Get group email details - Returns HTML
     */
    private function getGroupEmailDetails()
    {
        $emailId = intval($_GET['email_id'] ?? 0);

        if ($emailId <= 0) {
            echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid email ID</p></div>';
            return;
        }

        $this->renderGroupEmailDetails($emailId);
    }

    /**
     * Get email details (generic) - Returns HTML
     */
    private function getEmailDetails()
    {
        $emailId = intval($_GET['email_id'] ?? 0);
        $type = $_GET['type'] ?? 'individual';

        if ($emailId <= 0) {
            echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Invalid email ID</p></div>';
            return;
        }

        if ($type === 'group') {
            $this->renderGroupEmailDetails($emailId);
        } else {
            require_once __DIR__ . "/../helpers/EmailDetails.php";
            getEmailDetails($emailId);
        }
    }

    /**
     * Render group email details - NEW: Added missing function
     */
    private function renderGroupEmailDetails($emailId)
    {
        global $conn;
        $currentUserId = getUserID();

        // Get group email details
        $query = "SELECT gm.id, gm.subject, gm.content, gm.sender_id, gm.cc, gm.bcc, gm.attachments, gm.filter_criteria,
                         FROM_UNIXTIME(gm.created_at) as sent_date,
                         sender.first as sender_first, sender.last as sender_last, sender.mail as sender_email
                  FROM group_mails gm
                  JOIN user sender ON gm.sender_id = sender.id
                  WHERE gm.id = $emailId 
                  AND gm.sender_id = $currentUserId"; // Only allow sender to view

        $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

        if (!$result || mysqli_num_rows($result) === 0) {
            echo '<div class="error-message"><i class="fas fa-exclamation-triangle"></i><p>Email not found or access denied</p></div>';
            return;
        }

        $email = mysqli_fetch_assoc($result);

        // Get recipient count
        $countQuery = "SELECT COUNT(*) as recipient_count FROM group_mail_recipients WHERE group_mail_id = $emailId";
        $countResult = mysqli_query($GLOBALS["___mysqli_ston"], $countQuery);
        $countData = mysqli_fetch_assoc($countResult);
        $email['recipient_count'] = $countData['recipient_count'];

        // Decode filter criteria
        $email['filter_criteria'] = json_decode($email['filter_criteria'], true) ?? [];

        $isGroupEmail = true;

        // Use the same template as individual emails but with group data
        include __DIR__ . '/../templates/email-details.php';
    }

    /**
     * Create human-readable conversation name from filter criteria
     */
    private function createConversationName($filterCriteria)
    {
        $parts = [];

        $authorTypeMap = [
            'presenting' => 'Presenting Authors',
            'co_authors' => 'Co-Authors',
            'all' => 'All Authors'
        ];

        $presentationTypeMap = [
            'oral' => 'Oral Presentations',
            'poster' => 'Poster Presentations',
            'all' => 'All Presentations'
        ];

        $uploadStatusMap = [
            'with' => 'With Uploads',
            'without' => 'Without Uploads',
            'all' => 'All Upload Status'
        ];

        if (isset($filterCriteria['author_type']) && $filterCriteria['author_type'] !== 'all') {
            $parts[] = $authorTypeMap[$filterCriteria['author_type']] ?? $filterCriteria['author_type'];
        }

        if (isset($filterCriteria['presentation_type']) && $filterCriteria['presentation_type'] !== 'all') {
            $parts[] = $presentationTypeMap[$filterCriteria['presentation_type']] ?? $filterCriteria['presentation_type'];
        }

        if (isset($filterCriteria['has_presentation']) && $filterCriteria['has_presentation'] !== 'all') {
            $parts[] = $uploadStatusMap[$filterCriteria['has_presentation']] ?? $filterCriteria['has_presentation'];
        }

        if (empty($parts)) {
            return 'All Filtered Authors';
        }

        return implode(' + ', $parts);
    }

    /**
     * Format time ago helper
     */
    private function formatTimeAgo($secondsAgo, $fullDate)
    {
        if ($secondsAgo < 60) {
            return 'Just now';
        } elseif ($secondsAgo < 3600) {
            return floor($secondsAgo / 60) . ' minutes ago';
        } elseif ($secondsAgo < 86400) {
            return floor($secondsAgo / 3600) . ' hours ago';
        } elseif ($secondsAgo < 604800) {
            return floor($secondsAgo / 86400) . ' days ago';
        } else {
            return $fullDate;
        }
    }

    /**
     * Send error response
     */
    private function sendError($message)
    {
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
}
?>