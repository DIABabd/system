<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Form Submission Handler with File Attachment Support and Filter Integration
 */
class FormHandler
{
    private $uploadDir;
    private $maxFileSize = 10485760; // 10MB
    private $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];

    public function __construct()
    {
        // Set upload directory relative to the current script location
        $this->uploadDir = __DIR__ . '/../uploads/attachments/';

        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        error_log("FormHandler initialized with upload dir: " . $this->uploadDir);
    }

    /**
     * Process form submission with filter criteria integration
     */
    public function processSubmission($postData)
    {
        try {
            // Get current user ID
            $userId = getUserID();

            // Debug logging to see what's in the form data
            error_log("Form submission data: " . json_encode($postData));
            error_log("FILES array in processSubmission: " . json_encode($_FILES));

            // Process file attachments FIRST
            $attachments = $this->processAttachments();
            error_log("FORM HANDLER: Processed " . count($attachments) . " attachments");

            // Extract current filter criteria from session or form data
            $filterCriteria = $this->extractFilterCriteria($postData);
            error_log("Filter criteria extracted: " . json_encode($filterCriteria));

            // Check if this is a group email (hotline)
            if (isset($postData['group_email']) && $postData['group_email'] === 'true') {
                error_log("Processing as GROUP EMAIL");

                // Make sure we have author IDs for the group email
                if (empty($postData['author_ids'])) {
                    return [
                        'success' => false,
                        'message' => 'No authors selected for group email.'
                    ];
                }

                return $this->processGroupEmail($postData, $userId, $attachments, $filterCriteria);
            }
            // Check if this is a bulk email (individual emails to multiple recipients using BCC)
            else if (isset($postData['bulk_email']) && $postData['bulk_email'] === 'true') {
                error_log("Processing as BULK EMAIL");

                // Make sure we have author IDs for bulk email
                if (empty($postData['author_ids'])) {
                    return [
                        'success' => false,
                        'message' => 'No authors selected for bulk email.'
                    ];
                }

                return $this->processBulkEmail($postData, $userId, $attachments, $filterCriteria);
            }
            // This is a single email to one author
            else {
                error_log("Processing as SINGLE EMAIL");

                // Make sure we have a receiver ID
                if (empty($postData['receiverId'])) {
                    return [
                        'success' => false,
                        'message' => 'Recipient not specified.'
                    ];
                }

                return $this->processSingleEmail($postData, $userId, $attachments);
            }

        } catch (Exception $e) {
            error_log("Form submission error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract filter criteria from form data or session
     */
    private function extractFilterCriteria($postData)
    {
        // First, try to get from form data (hidden fields)
        $filterCriteria = [
            'author_type' => $postData['filter_author_type'] ?? 'all',
            'presentation_type' => $postData['filter_presentation_type'] ?? 'all',
            'has_presentation' => $postData['filter_has_presentation'] ?? 'all'
        ];

        // If all are 'all', try to get from session
        if (
            $filterCriteria['author_type'] === 'all' &&
            $filterCriteria['presentation_type'] === 'all' &&
            $filterCriteria['has_presentation'] === 'all'
        ) {

            // Try to get from session if available
            if (isset($_SESSION['current_filters'])) {
                $sessionFilters = $_SESSION['current_filters'];
                $filterCriteria = [
                    'author_type' => $sessionFilters['author_type'] ?? 'all',
                    'presentation_type' => $sessionFilters['presentation_type'] ?? 'all',
                    'has_presentation' => $sessionFilters['has_presentation'] ?? 'all'
                ];
            }
        }

        return $filterCriteria;
    }

    /**
     * Process file attachments - FIXED VERSION
     */
    private function processAttachments()
    {
        $attachments = [];

        error_log("=== PROCESSING ATTACHMENTS ===");
        error_log("Raw FILES array: " . print_r($_FILES, true));
        error_log("Upload directory: " . $this->uploadDir);
        error_log("Upload directory exists: " . (file_exists($this->uploadDir) ? 'YES' : 'NO'));
        error_log("Upload directory writable: " . (is_writable($this->uploadDir) ? 'YES' : 'NO'));

        // Check if attachments field exists
        if (!isset($_FILES['attachments'])) {
            error_log("No 'attachments' field in FILES array");
            return $attachments;
        }

        $files = $_FILES['attachments'];
        error_log("Attachments field structure: " . print_r($files, true));

        // Handle the case where no files were selected
        if (empty($files['name']) || (is_array($files['name']) && empty($files['name'][0]))) {
            error_log("No files selected or empty file name");
            return $attachments;
        }

        // Handle single file vs multiple files
        if (is_array($files['name'])) {
            // Multiple files
            $fileCount = count($files['name']);
            error_log("Processing $fileCount files");

            for ($i = 0; $i < $fileCount; $i++) {
                // Skip empty file slots
                if (empty($files['name'][$i])) {
                    error_log("Skipping empty file slot $i");
                    continue;
                }

                $fileData = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'error' => $files['error'][$i]
                ];

                error_log("Processing file $i: " . print_r($fileData, true));

                $result = $this->processSingleFile($fileData, $i);
                if ($result) {
                    $attachments[] = $result;
                }
            }
        } else {
            // Single file
            error_log("Processing single file: " . print_r($files, true));
            $result = $this->processSingleFile($files, 0);
            if ($result) {
                $attachments[] = $result;
            }
        }

        error_log("Final attachments array: " . print_r($attachments, true));
        return $attachments;
    }

    /**
     * Process a single file attachment
     */
    private function processSingleFile($fileData, $index)
    {
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            error_log("Upload error for file $index: " . $fileData['error']);
            return null;
        }

        // Check file size
        if ($fileData['size'] > $this->maxFileSize) {
            error_log("File $index too large: " . $fileData['size'] . " bytes");
            return null;
        }

        // Check file extension
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            error_log("File $index has invalid extension: $extension");
            return null;
        }

        // Generate unique filename
        $timestamp = time();
        $uniqueFilename = $timestamp . '_' . $index . '_' . basename($fileData['name']);
        $uploadPath = $this->uploadDir . $uniqueFilename;

        error_log("Attempting to move file from " . $fileData['tmp_name'] . " to " . $uploadPath);

        // Move uploaded file
        if (move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
            error_log("File successfully moved to: " . $uploadPath);

            return [
                'original_name' => $fileData['name'],
                'stored_name' => $uniqueFilename,
                'file_size' => $fileData['size'],
                'mime_type' => $fileData['type'],
                'tmp_name' => $uploadPath // This is now the permanent path
            ];
        } else {
            error_log("Failed to move uploaded file for file $index");
            return null;
        }
    }

    /**
     * Process a single email to one specific author
     */
    private function processSingleEmail($postData, $userId, $attachments)
    {
        try {
            // Get recipient info
            $recipientId = $postData['receiverId'];
            $recipientQuery = "SELECT first, last, mail FROM user WHERE id = $recipientId";
            $recipientResult = mysqli_query($GLOBALS["___mysqli_ston"], $recipientQuery);
            $recipientData = mysqli_fetch_assoc($recipientResult);

            if (!$recipientData || empty($recipientData['mail'])) {
                throw new Exception('Invalid recipient or missing email address.');
            }

            // Get sender info
            $senderQuery = "SELECT first, last, mail FROM user WHERE id = $userId";
            $senderResult = mysqli_query($GLOBALS["___mysqli_ston"], $senderQuery);
            $senderData = mysqli_fetch_assoc($senderResult);

            // Create PHPMailer instance
            require_once "classes/EmailSender.php";
            $emailSender = new EmailSender();
            $mail = $emailSender->createMailer();

            // Set sender and recipient
            $mail->setFrom($senderData['mail'], $senderData['first'] . ' ' . $senderData['last']);
            $mail->addAddress($recipientData['mail'], $recipientData['first'] . ' ' . $recipientData['last']);

            // Add CC if provided
            if (!empty($postData['cc'])) {
                $ccEmails = explode(',', $postData['cc']);
                foreach ($ccEmails as $ccEmail) {
                    $ccEmail = trim($ccEmail);
                    if (!empty($ccEmail)) {
                        $mail->addCC($ccEmail);
                    }
                }
            }

            // Add BCC if provided
            if (!empty($postData['bcc'])) {
                $bccEmails = explode(',', $postData['bcc']);
                foreach ($bccEmails as $bccEmail) {
                    $bccEmail = trim($bccEmail);
                    if (!empty($bccEmail)) {
                        $mail->addBCC($bccEmail);
                    }
                }
            }

            // Add attachments
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment['tmp_name'], $attachment['original_name']);
                error_log("Added attachment: " . $attachment['original_name']);
            }

            // Set email content
            $mail->isHTML(true);
            $mail->Subject = $postData['subject'];
            $mail->Body = $postData['body'];
            $mail->AltBody = strip_tags($postData['body']);

            // Send email
            $mail->send();

            // Store in database
            $this->storeIndividualEmail($userId, $recipientId, $postData, $attachments);

            error_log("Single email sent successfully to " . $recipientData['mail']);

            return [
                'success' => true,
                'message' => 'Email sent successfully!' . (count($attachments) > 0 ? ' (' . count($attachments) . ' attachments)' : '')
            ];

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a bulk email to multiple authors using BCC with filter criteria
     */
    private function processBulkEmail($postData, $userId, $attachments, $filterCriteria)
    {
        try {
            // Get the list of author IDs
            $authorIds = explode(',', $postData['author_ids']);

            if (empty($authorIds)) {
                throw new Exception('No authors selected for bulk email.');
            }

            error_log("Sending bulk email with " . count($attachments) . " attachments to " . count($authorIds) . " authors");

            // Use the GroupMailHandler to send the email with filter criteria
            require_once "handlers/GroupMailHandler.php";
            $groupMailHandler = new GroupMailHandler();

            return $groupMailHandler->sendGroupEmailWithFilters(
                $userId,
                $authorIds,
                $postData['subject'],
                $postData['body'],
                isset($postData['cc']) ? $postData['cc'] : null,
                isset($postData['bcc']) ? $postData['bcc'] : null,
                $attachments,
                $filterCriteria
            );

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a group email to all authors using BCC with filter criteria
     */
    private function processGroupEmail($postData, $userId, $attachments, $filterCriteria)
    {
        try {
            // Get the list of author IDs
            $authorIds = explode(',', $postData['author_ids']);

            if (empty($authorIds)) {
                throw new Exception('No authors selected for group email.');
            }

            error_log("Sending group email with " . count($attachments) . " attachments to " . count($authorIds) . " authors");

            // Use the GroupMailHandler to send the email with filter criteria
            require_once "handlers/GroupMailHandler.php";
            $groupMailHandler = new GroupMailHandler();

            return $groupMailHandler->sendGroupEmailWithFilters(
                $userId,
                $authorIds,
                $postData['subject'],
                $postData['body'],
                isset($postData['cc']) ? $postData['cc'] : null,
                isset($postData['bcc']) ? $postData['bcc'] : null,
                $attachments,
                $filterCriteria
            );

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Store individual email in database
     */
    private function storeIndividualEmail($senderId, $recipientId, $postData, $attachments)
    {
        global $conn;

        try {
            // Get or create conversation
            $conversationId = $this->getOrCreateConversation($senderId, $recipientId);

            // Store attachment information
            $attachmentData = null;
            if (!empty($attachments)) {
                $attachmentInfo = array_map(function ($attachment) {
                    return [
                        'original_name' => $attachment['original_name'],
                        'stored_name' => $attachment['stored_name'],
                        'file_size' => $attachment['file_size'],
                        'mime_type' => $attachment['mime_type']
                    ];
                }, $attachments);
                $attachmentData = json_encode($attachmentInfo);
            }

            // Insert email record
            $stmt = $conn->prepare("INSERT INTO mails (subject, content, sender_id, recipient_id, cc, bcc, conversation_id, created_at, attachments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $currentTime = time();
            $cc = isset($postData['cc']) ? $postData['cc'] : null;
            $bcc = isset($postData['bcc']) ? $postData['bcc'] : null;

            $stmt->bind_param("ssiisssis", $postData['subject'], $postData['body'], $senderId, $recipientId, $cc, $bcc, $conversationId, $currentTime, $attachmentData);
            $stmt->execute();
            $stmt->close();

            error_log("Individual email stored in database successfully");

        } catch (Exception $e) {
            error_log("Error storing individual email: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get or create conversation between two users
     */
    private function getOrCreateConversation($senderId, $recipientId)
    {
        global $conn;

        // Check if conversation exists (either direction)
        $stmt = $conn->prepare("SELECT id FROM conversations WHERE (mso_do_id = ? AND author_id = ?) OR (mso_do_id = ? AND author_id = ?)");
        $stmt->bind_param("iiii", $senderId, $recipientId, $recipientId, $senderId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($conversationId);
            $stmt->fetch();
            $stmt->close();
            return $conversationId;
        }

        $stmt->close();

        // Create new conversation
        $stmt = $conn->prepare("INSERT INTO conversations (mso_do_id, author_id, created_at, updated_at) VALUES (?, ?, ?, ?)");
        $currentTime = time();
        $stmt->bind_param("iiii", $senderId, $recipientId, $currentTime, $currentTime);
        $stmt->execute();
        $conversationId = $stmt->insert_id;
        $stmt->close();

        return $conversationId;
    }
}
?>