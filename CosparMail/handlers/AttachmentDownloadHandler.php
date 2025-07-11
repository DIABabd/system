<?php
/**
 * Attachment Download Handler
 * 
 * Handles secure downloading of email attachments
 */

class AttachmentDownloadHandler
{
    private $uploadDir;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../uploads/attachments/';

        // Log the upload directory for debugging
        error_log("AttachmentDownloadHandler: Upload directory set to " . $this->uploadDir);

        // Make sure the directory exists
        if (!file_exists($this->uploadDir)) {
            error_log("AttachmentDownloadHandler: Creating upload directory");
            mkdir($this->uploadDir, 0755, true);
        }

        // Check if the directory is writable
        if (!is_writable($this->uploadDir)) {
            error_log("AttachmentDownloadHandler: WARNING - Upload directory is not writable!");
        } else {
            error_log("AttachmentDownloadHandler: Upload directory exists and is writable");
        }
    }

    /**
     * Process attachment download request
     */
    public function processDownload()
    {
        // Log the download request for debugging
        error_log("AttachmentDownloadHandler: Processing download request");
        error_log("GET params: " . json_encode($_GET));

        // Check if required parameters are provided
        if (!isset($_GET['email_id']) || !isset($_GET['attachment_id'])) {
            error_log("AttachmentDownloadHandler: Missing required parameters");
            $this->sendError('Missing required parameters');
            return;
        }

        $emailId = intval($_GET['email_id']);
        $attachmentId = intval($_GET['attachment_id']);

        error_log("AttachmentDownloadHandler: EmailID = $emailId, AttachmentID = $attachmentId");

        if ($emailId <= 0 || $attachmentId < 0) {
            error_log("AttachmentDownloadHandler: Invalid parameters");
            $this->sendError('Invalid parameters');
            return;
        }

        // Get current user ID
        $userId = getUserID();
        if (!$userId) {
            error_log("AttachmentDownloadHandler: User not authenticated");
            $this->sendError('User not authenticated');
            return;
        }

        // Get attachment info from database
        $attachmentInfo = $this->getAttachmentInfo($emailId, $attachmentId, $userId);
        if (!$attachmentInfo) {
            $this->sendError('Attachment not found or access denied');
            return;
        }

        // Download the file
        $this->downloadFile($attachmentInfo);
    }

    /**
     * Get attachment information from database
     */
    private function getAttachmentInfo($emailId, $attachmentId, $userId)
    {
        global $conn;

        error_log("AttachmentDownloadHandler: Getting attachment info for EmailID=$emailId, AttachmentID=$attachmentId, UserID=$userId");

        // First, check regular emails
        $sql = "SELECT m.attachments, m.sender_id, m.recipient_id 
                FROM mails m 
                WHERE m.id = ? AND (m.sender_id = ? OR m.recipient_id = ?)";

        error_log("AttachmentDownloadHandler: SQL Query = $sql");

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $emailId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            error_log("AttachmentDownloadHandler: Found email record, attachments JSON: " . $row['attachments']);
            $stmt->close();
            $attachment = $this->parseAttachment($row['attachments'], $attachmentId);
            error_log("AttachmentDownloadHandler: Parsed attachment: " . json_encode($attachment));
            return $attachment;
        }

        error_log("AttachmentDownloadHandler: No regular email found, checking group emails");
        $stmt->close();

        // If not found in regular emails, check group emails
        $sql = "SELECT gm.attachments, gm.sender_id, gc.created_by
                FROM group_mails gm
                JOIN group_conversations gc ON gm.group_conversation_id = gc.id
                WHERE gm.id = ? AND (gm.sender_id = ? OR gc.created_by = ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $emailId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $stmt->close();
            return $this->parseAttachment($row['attachments'], $attachmentId);
        }

        $stmt->close();
        return null;
    }

    /**
     * Parse attachment data from JSON
     */
    private function parseAttachment($attachmentsJson, $attachmentId)
    {
        error_log("AttachmentDownloadHandler: Parsing attachment JSON: " . $attachmentsJson);

        if (empty($attachmentsJson)) {
            error_log("AttachmentDownloadHandler: Empty attachments JSON");
            return null;
        }

        $attachments = json_decode($attachmentsJson, true);
        error_log("AttachmentDownloadHandler: Decoded JSON: " . json_encode($attachments));

        if (!is_array($attachments)) {
            error_log("AttachmentDownloadHandler: JSON did not decode to an array");
            return null;
        }

        if (!isset($attachments[$attachmentId])) {
            error_log("AttachmentDownloadHandler: Attachment ID $attachmentId not found in array with keys: " . implode(', ', array_keys($attachments)));
            return null;
        }

        error_log("AttachmentDownloadHandler: Found attachment: " . json_encode($attachments[$attachmentId]));
        return $attachments[$attachmentId];
    }

    /**
     * Download file securely
     */
    private function downloadFile($attachmentInfo)
    {
        // Try both with and without a file_path property
        if (isset($attachmentInfo['file_path']) && file_exists($attachmentInfo['file_path'])) {
            // If the full path is provided in the attachment info, use it
            $filePath = $attachmentInfo['file_path'];
            error_log("AttachmentDownloadHandler: Using file_path from attachment info: $filePath");
        } else {
            // Otherwise construct the path from the upload dir and stored_name
            $filePath = $this->uploadDir . $attachmentInfo['stored_name'];
            error_log("AttachmentDownloadHandler: Constructed path: $filePath");
        }

        // Check if file exists at the constructed path
        if (!file_exists($filePath)) {
            error_log("AttachmentDownloadHandler: File not found at path: $filePath");

            // Try to scan the uploads directory to find the file
            error_log("AttachmentDownloadHandler: Scanning uploads directory for the file...");
            $found = false;
            if (is_dir($this->uploadDir)) {
                $files = scandir($this->uploadDir);
                foreach ($files as $file) {
                    if ($file === $attachmentInfo['stored_name']) {
                        $found = true;
                        error_log("AttachmentDownloadHandler: Found file in directory scan: $file");
                        break;
                    }
                }

                if (!$found) {
                    error_log("AttachmentDownloadHandler: File not found in directory scan");
                }
            }

            $this->sendError('File not found on server');
            return;
        }

        // Validate file size matches
        $actualSize = filesize($filePath);
        if ($actualSize != $attachmentInfo['file_size']) {
            error_log("AttachmentDownloadHandler: File size mismatch for {$attachmentInfo['stored_name']}: expected {$attachmentInfo['file_size']}, actual $actualSize");
        }

        // Set appropriate headers for download
        $this->setDownloadHeaders($attachmentInfo['original_name'], $attachmentInfo['mime_type'], $actualSize);

        // Output file content
        if (readfile($filePath) === false) {
            $this->sendError('Failed to read file');
            return;
        }

        exit; // Important: stop script execution after file download
    }

    /**
     * Set HTTP headers for file download
     */
    private function setDownloadHeaders($filename, $mimeType, $fileSize)
    {
        // Clean any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        // Prevent any additional output
        header('Connection: close');
    }

    /**
     * Send error response
     */
    private function sendError($message)
    {
        error_log("Attachment download error: $message");

        if (!headers_sent()) {
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain');
        }

        echo "Error: $message";
        exit;
    }
}