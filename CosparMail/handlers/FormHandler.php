<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Form Submission Handler with File Attachment Support
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
     * Process form submission
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
            error_log("Attachments data: " . json_encode($attachments));

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

                return $this->processGroupEmail($postData, $userId, $attachments);
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

                return $this->processBulkEmail($postData, $userId, $attachments);
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

                error_log("Processing file $i: " . json_encode($fileData));

                if ($fileData['error'] === UPLOAD_ERR_OK) {
                    $attachment = $this->processSingleAttachment($fileData);
                    if ($attachment) {
                        $attachments[] = $attachment;
                        error_log("Successfully processed attachment: " . $fileData['name']);
                    } else {
                        error_log("Failed to process attachment: " . $fileData['name']);
                    }
                } else {
                    error_log("Upload error for file " . $fileData['name'] . ": " . $this->getUploadErrorMessage($fileData['error']));
                }
            }
        } else {
            // Single file
            error_log("Processing single file: " . $files['name']);

            if ($files['error'] === UPLOAD_ERR_OK) {
                $attachment = $this->processSingleAttachment($files);
                if ($attachment) {
                    $attachments[] = $attachment;
                    error_log("Successfully processed single attachment: " . $files['name']);
                } else {
                    error_log("Failed to process single attachment: " . $files['name']);
                }
            } else {
                error_log("Upload error for single file " . $files['name'] . ": " . $this->getUploadErrorMessage($files['error']));
            }
        }

        error_log("Total attachments processed: " . count($attachments));
        error_log("Final attachments array: " . json_encode($attachments));
        error_log("=== END PROCESSING ATTACHMENTS ===");
        return $attachments;
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_OK:
                return 'No error';
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE';
            case UPLOAD_ERR_PARTIAL:
                return 'File only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Process a single attachment - IMPROVED VERSION
     */
    private function processSingleAttachment($file)
    {
        try {
            error_log("=== PROCESSING SINGLE ATTACHMENT ===");
            error_log("File data: " . json_encode($file));
            error_log("Temp file exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));

            // Validate file first
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                error_log("File validation failed: " . $validation['error']);
                return null;
            }

            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uniqueName = uniqid('attach_', true) . '.' . $extension;
            $filePath = $this->uploadDir . $uniqueName;

            error_log("Moving file from: " . $file['tmp_name']);
            error_log("Moving file to: " . $filePath);

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("File moved successfully");

                // Verify the file was actually moved and has correct size
                if (file_exists($filePath)) {
                    $actualSize = filesize($filePath);
                    error_log("File exists at destination, size: $actualSize bytes");

                    return [
                        'original_name' => $file['name'],
                        'stored_name' => $uniqueName,
                        'file_path' => $filePath,
                        'file_size' => $actualSize,
                        'mime_type' => $file['type']
                    ];
                } else {
                    error_log("File does not exist at destination after move");
                    return null;
                }
            } else {
                error_log("Failed to move uploaded file: " . $file['name']);
                error_log("Source exists: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
                error_log("Destination directory writable: " . (is_writable($this->uploadDir) ? 'YES' : 'NO'));
                return null;
            }

        } catch (Exception $e) {
            error_log("Error processing attachment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate uploaded file - IMPROVED VERSION
     */
    private function validateFile($file)
    {
        error_log("Validating file: " . $file['name']);

        // Check for upload errors first
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Upload error: ' . $this->getUploadErrorMessage($file['error'])];
        }

        // Check if file was actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File was not uploaded via HTTP POST'];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        if ($file['size'] <= 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['valid' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $this->allowedExtensions)];
        }

        error_log("File validation passed for: " . $file['name']);
        return ['valid' => true];
    }

    /**
     * Process a single email with attachments
     */
    private function processSingleEmail($postData, $userId, $attachments)
    {
        try {
            // Get form data
            $receiverId = $postData['receiverId'];
            $name = $postData['name'];
            $subject = $postData['subject'];
            $body = $postData['body'];
            $ccAddresses = isset($postData['cc']) && !empty($postData['cc']) ? $postData['cc'] : null;
            $bccAddresses = isset($postData['bcc']) && !empty($postData['bcc']) ? $postData['bcc'] : null;

            error_log("Sending single email with " . count($attachments) . " attachments");

            // Send the email using real addresses with attachments
            $emailSender = new EmailSender();
            if (
                !$emailSender->sendEmailWithAttachments(
                    $userId,
                    $receiverId,
                    $name,
                    $subject,
                    $body,
                    $ccAddresses,
                    $bccAddresses,
                    $attachments
                )
            ) {
                throw new Exception('Failed to send email.');
            }

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
     * Process a bulk email to multiple authors using BCC with attachments
     */
    private function processBulkEmail($postData, $userId, $attachments)
    {
        try {
            global $conn;

            // Get the list of filtered authors from the submitted form
            $authorIds = explode(',', $postData['author_ids']);

            if (empty($authorIds)) {
                throw new Exception('No authors selected for bulk email.');
            }

            // Get sender information
            $stmt = $conn->prepare("SELECT mail, CONCAT(first, ' ', last) as name FROM user WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($senderEmail, $senderName);
            $stmt->fetch();
            $stmt->close();

            if (!$senderEmail) {
                throw new Exception('Sender email not found.');
            }

            // Get all author emails for BCC
            $authorEmails = [];
            $validAuthorIds = [];

            foreach ($authorIds as $authorId) {
                if (empty($authorId))
                    continue;

                $authorId = intval($authorId);
                if ($authorId <= 0)
                    continue;

                $stmt = $conn->prepare("SELECT mail FROM user WHERE id = ?");
                $stmt->bind_param("i", $authorId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $row = $result->fetch_assoc()) {
                    $authorEmail = $row['mail'];
                    if (!empty($authorEmail)) {
                        $authorEmails[] = $authorEmail;
                        $validAuthorIds[] = $authorId;
                    }
                }
                $stmt->close();
            }

            if (empty($authorEmails)) {
                throw new Exception('No valid author emails found.');
            }

            error_log("Sending bulk email with " . count($attachments) . " attachments");

            // Setup PHPMailer for bulk email with BCC and attachments
            $mail = new PHPMailer(true);
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

            $mail->setFrom($senderEmail, $senderName);

            // Add sender as the main recipient (so they get a copy)
            $mail->addAddress($senderEmail, $senderName);

            // Add all authors as BCC recipients
            foreach ($authorEmails as $authorEmail) {
                $mail->addBCC($authorEmail);
            }

            // Add explicit CC/BCC if provided
            if (!empty($postData['cc'])) {
                $ccList = explode(',', $postData['cc']);
                foreach ($ccList as $cc) {
                    $cc = trim($cc);
                    if (!empty($cc) && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc);
                    }
                }
            }

            if (!empty($postData['bcc'])) {
                $bccList = explode(',', $postData['bcc']);
                foreach ($bccList as $bcc) {
                    $bcc = trim($bcc);
                    if (!empty($bcc) && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($bcc);
                    }
                }
            }

            // Add attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['file_path'])) {
                    $mail->addAttachment($attachment['file_path'], $attachment['original_name']);
                    error_log("Added attachment to bulk email: " . $attachment['original_name']);
                }
            }

            // Set email content
            $mail->isHTML(true);
            $mail->Subject = $postData['subject'];
            $mail->Body = $postData['body'];
            $mail->AltBody = strip_tags($postData['body']);

            // Send the email
            $mail->send();
            error_log("Bulk email sent successfully to " . count($authorEmails) . " authors via BCC with " . count($attachments) . " attachments");

            return [
                'success' => true,
                'message' => 'Bulk email sent successfully to ' . count($authorEmails) . ' authors!' . (count($attachments) > 0 ? ' (' . count($attachments) . ' attachments)' : '')
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
     * Process a group email to all authors using BCC with attachments
     */
    private function processGroupEmail($postData, $userId, $attachments)
    {
        try {
            // Get the list of author IDs
            $authorIds = explode(',', $postData['author_ids']);

            if (empty($authorIds)) {
                throw new Exception('No authors selected for group email.');
            }

            error_log("Sending group email with " . count($attachments) . " attachments");

            // Use the GroupMailHandler to send the email with attachments
            require_once "handlers/GroupMailHandler.php";
            $groupMailHandler = new GroupMailHandler();

            return $groupMailHandler->sendGroupEmailWithAttachments(
                $userId,
                $authorIds,
                $postData['subject'],
                $postData['body'],
                isset($postData['cc']) ? $postData['cc'] : null,
                isset($postData['bcc']) ? $postData['bcc'] : null,
                $attachments
            );

        } catch (Exception $e) {
            error_log($e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}