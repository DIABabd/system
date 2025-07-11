<?php
/**
 * Enhanced AJAX Handler with Filter-Based Conversation Support
 * 
 * Handles AJAX requests for filter-based email conversations
 */

class EnhancedAjaxHandler
{
    public function processRequest()
    {
        // Set JSON response headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');

        $action = $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'get_filtered_conversations':
                    $this->getFilteredConversations();
                    break;

                case 'get_filtered_email_history':
                    $this->getFilteredEmailHistory();
                    break;

                case 'send_group_email':
                    $this->sendGroupEmailWithFilters();
                    break;

                case 'get_group_email_history':
                    $this->getGroupEmailHistory();
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

        require_once __DIR__ . "/EnhancedGroupMailHandler.php";
        $handler = new EnhancedGroupMailHandler();

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

        require_once __DIR__ . "/EnhancedGroupMailHandler.php";
        $handler = new EnhancedGroupMailHandler();

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

        // Handle file attachments
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            require_once __DIR__ . "/FileUploadHandler.php";
            $uploadHandler = new FileUploadHandler();
            $attachments = $uploadHandler->handleMultipleUploads($_FILES['attachments']);
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
        require_once __DIR__ . "/EnhancedGroupMailHandler.php";
        $handler = new EnhancedGroupMailHandler();

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
     * Get group email history (legacy support)
     */
    private function getGroupEmailHistory()
    {
        $groupName = $_GET['group_name'] ?? 'All Authors';
        $userId = getUserID();

        require_once __DIR__ . "/GroupMailHandler.php";
        $groupMailHandler = new GroupMailHandler();

        $emails = $groupMailHandler->getGroupEmailHistory($userId, $groupName);

        if (empty($emails)) {
            echo '<div class="no-emails-message">
                    <i class="fas fa-envelope-open"></i>
                    <p>No group emails sent yet.</p>
                    <p>Click the "New Mail" button to send a group email.</p>
                  </div>';
            return;
        }

        echo '<div class="email-list">';

        foreach ($emails as $email) {
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
     * Get email details
     */
    private function getEmailDetails()
    {
        $emailId = intval($_GET['email_id'] ?? 0);
        $type = $_GET['type'] ?? 'individual';

        if ($emailId <= 0) {
            $this->sendError('Invalid email ID');
            return;
        }

        if ($type === 'group') {
            require_once __DIR__ . "/../helpers/GroupEmailDetails.php";
            getGroupEmailDetails($emailId);
        } else {
            require_once __DIR__ . "/../helpers/EmailDetails.php";
            getEmailDetails($emailId);
        }
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