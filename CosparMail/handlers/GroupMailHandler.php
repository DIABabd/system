<?php
/**
 * Enhanced GroupMailHandler with Filter-Based Conversation Tracking
 * Compatible with existing EmailSender class
 */

// Load PHPMailer classes
require_once '/home/projects/phpMyIAC/htdocs/include/PHPmailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class GroupMailHandler
{
    /**
     * Send group email with filter criteria tracking
     * 
     * @param int $senderId Sender user ID
     * @param array $recipientIds Array of recipient user IDs  
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string|null $ccAddresses CC addresses
     * @param string|null $bccAddresses BCC addresses
     * @param array $attachments Array of attachment info
     * @param array $filterCriteria Current filter settings
     * @return array Success/error response
     */
    public function sendGroupEmailWithFilters($senderId, $recipientIds, $subject, $body, $ccAddresses = null, $bccAddresses = null, $attachments = [], $filterCriteria = [])
    {
        try {
            global $conn;

            $currentTime = time();

            // Create a unique filter signature for conversation grouping
            $filterSignature = $this->createFilterSignature($filterCriteria);
            $conversationName = $this->createConversationName($filterCriteria);

            error_log("Filter signature: " . $filterSignature);
            error_log("Conversation name: " . $conversationName);

            // Get or create group conversation based on filter signature
            $groupConversationId = $this->getOrCreateFilteredGroupConversation($senderId, $filterSignature, $conversationName, $currentTime);

            // Validate recipient IDs and get valid emails
            $validRecipientIds = [];
            $recipientEmails = [];

            foreach ($recipientIds as $recipientId) {
                $query = "SELECT id, mail FROM user WHERE id = " . intval($recipientId);
                $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    if (!empty($row['mail'])) {
                        // Fix email addresses that use (at) instead of @
                        $cleanEmail = str_replace('(at)', '@', $row['mail']);
                        $cleanEmail = trim($cleanEmail);

                        // Validate the cleaned email
                        if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                            $validRecipientIds[] = $row['id'];
                            $recipientEmails[] = $cleanEmail;
                            error_log("Added valid recipient: " . $cleanEmail);
                        } else {
                            error_log("Invalid email address skipped: " . $row['mail'] . " (cleaned: " . $cleanEmail . ")");
                        }
                    }
                }
            }

            if (empty($validRecipientIds)) {
                return [
                    'success' => false,
                    'message' => 'No valid recipients found'
                ];
            }

            // Send email using PHPMailer directly (since EmailSender doesn't have createMailer)
            $mail = new PHPMailer(true);

            // Configure SMTP settings (same as your EmailSender)
            $mail->isSMTP();
            $mail->Host = '192.168.50.229';
            $mail->Port = 1025;
            $mail->SMTPAuth = false;
            $mail->SMTPAutoTLS = false;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Set sender
            $senderQuery = "SELECT first, last, mail FROM user WHERE id = $senderId";
            $senderResult = mysqli_query($GLOBALS["___mysqli_ston"], $senderQuery);
            $senderData = mysqli_fetch_assoc($senderResult);

            $mail->setFrom($senderData['mail'], $senderData['first'] . ' ' . $senderData['last']);

            // Add sender as main recipient (so they get a copy)
            $mail->addAddress($senderData['mail'], $senderData['first'] . ' ' . $senderData['last']);

            // Add all recipients as BCC to protect privacy
            foreach ($recipientEmails as $email) {
                $mail->addBCC($email);
            }

            // Add CC if provided
            if (!empty($ccAddresses)) {
                $ccEmails = explode(',', $ccAddresses);
                foreach ($ccEmails as $ccEmail) {
                    $ccEmail = trim($ccEmail);
                    // Fix (at) in CC addresses
                    $cleanCcEmail = str_replace('(at)', '@', $ccEmail);
                    if (!empty($cleanCcEmail) && filter_var($cleanCcEmail, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cleanCcEmail);
                        error_log("Added CC: " . $cleanCcEmail);
                    } else {
                        error_log("Invalid CC email skipped: " . $ccEmail);
                    }
                }
            }

            // Add BCC if provided
            if (!empty($bccAddresses)) {
                $bccEmails = explode(',', $bccAddresses);
                foreach ($bccEmails as $bccEmail) {
                    $bccEmail = trim($bccEmail);
                    // Fix (at) in BCC addresses
                    $cleanBccEmail = str_replace('(at)', '@', $bccEmail);
                    if (!empty($cleanBccEmail) && filter_var($cleanBccEmail, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($cleanBccEmail);
                        error_log("Added BCC: " . $cleanBccEmail);
                    } else {
                        error_log("Invalid BCC email skipped: " . $bccEmail);
                    }
                }
            }

            // Handle attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['tmp_name'])) {
                        $mail->addAttachment($attachment['tmp_name'], $attachment['original_name']);
                        error_log("Added attachment: " . $attachment['original_name']);
                    }
                }
            }

            // Set email content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            // Send the email
            $mail->send();
            error_log("Group email sent to " . count($validRecipientIds) . " recipients with filter: " . $filterSignature);

            // Record the email in the database
            $conn->begin_transaction();

            try {
                // Store attachments info and filter criteria
                $attachmentData = $this->storeAttachmentInfo($attachments);
                $filterData = json_encode($filterCriteria);

                // Insert the group email with filter criteria
                $stmt = $conn->prepare("INSERT INTO group_mails (subject, content, sender_id, cc, bcc, group_conversation_id, created_at, attachments, filter_criteria) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssississs", $subject, $body, $senderId, $ccAddresses, $bccAddresses, $groupConversationId, $currentTime, $attachmentData, $filterData);
                $stmt->execute();
                $groupMailId = $stmt->insert_id;
                $stmt->close();

                // Insert recipients for tracking
                foreach ($validRecipientIds as $recipientId) {
                    $stmt = $conn->prepare("INSERT INTO group_mail_recipients (group_mail_id, recipient_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $groupMailId, $recipientId);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();

                return [
                    'success' => true,
                    'message' => 'Group email sent to ' . count($validRecipientIds) . ' recipients!' . (count($attachments) > 0 ? ' (' . count($attachments) . ' attachments)' : ''),
                    'conversation_name' => $conversationName,
                    'filter_signature' => $filterSignature
                ];

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Database error in group email: " . $e->getMessage());
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Group email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error sending group email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a unique signature for filter combination
     * 
     * @param array $filterCriteria Filter settings
     * @return string Unique filter signature
     */
    private function createFilterSignature($filterCriteria)
    {
        // Sort the filter criteria to ensure consistent signatures
        ksort($filterCriteria);

        // Remove 'all' values as they don't actually filter
        $activeFilters = array_filter($filterCriteria, function ($value) {
            return $value !== 'all' && $value !== null && $value !== '';
        });

        if (empty($activeFilters)) {
            return 'all_authors';
        }

        // Create a readable signature
        $parts = [];
        foreach ($activeFilters as $key => $value) {
            $parts[] = $key . '_' . $value;
        }

        return implode('_', $parts);
    }

    /**
     * Create a human-readable conversation name from filter criteria
     * 
     * @param array $filterCriteria Filter settings
     * @return string Human-readable conversation name
     */
    private function createConversationName($filterCriteria)
    {
        $parts = [];

        // Author type mapping
        $authorTypeMap = [
            'presenting' => 'Presenting Authors',
            'co_authors' => 'Co-Authors',
            'all' => 'All Authors'
        ];

        // Presentation type mapping
        $presentationTypeMap = [
            'oral' => 'Oral Presentations',
            'poster' => 'Poster Presentations',
            'all' => 'All Presentations'
        ];

        // Upload status mapping
        $uploadStatusMap = [
            'with' => 'With Uploads',
            'without' => 'Without Uploads',
            'all' => 'All Upload Status'
        ];

        // Build conversation name from active filters
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
     * Get or create a group conversation based on filter signature
     * 
     * @param int $userId Creator user ID
     * @param string $filterSignature Unique filter signature
     * @param string $conversationName Human-readable name
     * @param int $currentTime Current timestamp
     * @return int Group conversation ID
     */
    private function getOrCreateFilteredGroupConversation($userId, $filterSignature, $conversationName, $currentTime)
    {
        global $conn;

        // Look for existing group conversation with same filter signature
        $stmt = $conn->prepare("SELECT id FROM group_conversations WHERE name = ? AND created_by = ?");
        $stmt->bind_param("si", $filterSignature, $userId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($groupId);
            $stmt->fetch();
            $stmt->close();

            // Update last updated timestamp
            $stmt = $conn->prepare("UPDATE group_conversations SET updated_at = ? WHERE id = ?");
            $stmt->bind_param("ii", $currentTime, $groupId);
            $stmt->execute();
            $stmt->close();

            return $groupId;
        }

        $stmt->close();

        // Create new group conversation using filter signature as name
        $stmt = $conn->prepare("INSERT INTO group_conversations (name, created_by, created_at, updated_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siii", $filterSignature, $userId, $currentTime, $currentTime);
        $stmt->execute();
        $groupId = $stmt->insert_id;
        $stmt->close();

        return $groupId;
    }

    /**
     * Get all filter-based conversations for a user
     * 
     * @param int $userId User ID
     * @return array List of conversations with their details
     */
    public function getFilteredConversations($userId)
    {
        global $conn;

        $stmt = $conn->prepare("
            SELECT gc.id, gc.name as filter_signature, gc.created_at, gc.updated_at,
                   COUNT(gm.id) as email_count,
                   MAX(gm.created_at) as last_email_date,
                   (SELECT gm2.filter_criteria FROM group_mails gm2 WHERE gm2.group_conversation_id = gc.id ORDER BY gm2.created_at DESC LIMIT 1) as last_filter_criteria
            FROM group_conversations gc
            LEFT JOIN group_mails gm ON gc.id = gm.group_conversation_id
            WHERE gc.created_by = ?
            GROUP BY gc.id, gc.name, gc.created_at, gc.updated_at
            ORDER BY gc.updated_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            // Decode the last filter criteria to get human-readable name
            $filterCriteria = json_decode($row['last_filter_criteria'], true) ?? [];
            $humanReadableName = $this->createConversationName($filterCriteria);

            $conversations[] = [
                'id' => $row['id'],
                'filter_signature' => $row['filter_signature'],
                'human_readable_name' => $humanReadableName,
                'email_count' => $row['email_count'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'last_email_date' => $row['last_email_date'],
                'filter_criteria' => $filterCriteria
            ];
        }

        $stmt->close();
        return $conversations;
    }

    /**
     * Get email history for a specific filtered conversation
     * 
     * @param int $userId User ID
     * @param string $filterSignature Filter signature
     * @return array Email history
     */
    public function getFilteredEmailHistory($userId, $filterSignature)
    {
        global $conn;

        // Find the conversation
        $stmt = $conn->prepare("SELECT id FROM group_conversations WHERE name = ? AND created_by = ?");
        $stmt->bind_param("si", $filterSignature, $userId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $stmt->close();
            return [];
        }

        $stmt->bind_result($groupId);
        $stmt->fetch();
        $stmt->close();

        // Get all emails for this filtered conversation
        $stmt = $conn->prepare("
            SELECT gm.id, gm.subject, gm.content, gm.sender_id, gm.cc, gm.bcc, gm.created_at, gm.attachments, gm.filter_criteria,
                   u.first as sender_first, u.last as sender_last
            FROM group_mails gm
            JOIN user u ON gm.sender_id = u.id
            WHERE gm.group_conversation_id = ?
            ORDER BY gm.created_at DESC
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $result = $stmt->get_result();

        $emails = [];
        while ($row = $result->fetch_assoc()) {
            // Get recipient count for this email
            $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM group_mail_recipients WHERE group_mail_id = ?");
            $countStmt->bind_param("i", $row['id']);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countData = $countResult->fetch_assoc();
            $countStmt->close();

            // Decode filter criteria
            $filterCriteria = json_decode($row['filter_criteria'], true) ?? [];

            $emails[] = [
                'id' => $row['id'],
                'subject' => $row['subject'],
                'content' => $row['content'],
                'sender_id' => $row['sender_id'],
                'sender_first' => $row['sender_first'],
                'sender_last' => $row['sender_last'],
                'cc' => $row['cc'],
                'bcc' => $row['bcc'],
                'created_at' => $row['created_at'],
                'attachments' => $row['attachments'],
                'filter_criteria' => $filterCriteria,
                'recipient_count' => $countData['count']
            ];
        }

        $stmt->close();
        return $emails;
    }

    /**
     * Store attachment information as JSON
     */
    private function storeAttachmentInfo($attachments)
    {
        if (empty($attachments)) {
            return null;
        }

        $attachmentInfo = array_map(function ($attachment) {
            return [
                'original_name' => $attachment['original_name'],
                'stored_name' => $attachment['stored_name'],
                'file_size' => $attachment['file_size'],
                'mime_type' => $attachment['mime_type']
            ];
        }, $attachments);

        return json_encode($attachmentInfo);
    }
}
?>