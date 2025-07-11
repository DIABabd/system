<?php
/**
 * Email Sender Class with File Attachment Support
 */

// Ensure PHPMailer classes are loaded
require_once '/home/projects/phpMyIAC/htdocs/include/PHPmailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    /**
     * Send an email with file attachments
     */
    function sendEmailWithAttachments($senderId, $receiverId, $name, $subject, $body, $ccAddresses = null, $bccAddresses = null, $attachments = [])
    {
        global $conn;

        // Step 1: Retrieve the sender's real email from the database
        $sql = "SELECT mail FROM user WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $senderId);
        $stmt->execute();
        $stmt->bind_result($senderRealEmail);
        $stmt->fetch();
        $stmt->close();

        if (!$senderRealEmail) {
            error_log("Sender email not found for user ID: $senderId");
            return false;
        }

        // Step 2: Retrieve the receiver's real email from the database
        $sql = "SELECT mail FROM user WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $receiverId);
        $stmt->execute();
        $stmt->bind_result($receiverRealEmail);
        $stmt->fetch();
        $stmt->close();

        if (!$receiverRealEmail) {
            error_log("Receiver email not found for user ID: $receiverId");
            return false;
        }

        // Step 3: Find existing conversation or create a new one
        $mso_do_Id = min($senderId, $receiverId);
        $author_Id = max($senderId, $receiverId);
        $currentTime = time();

        $sql = "SELECT id FROM conversations WHERE mso_do_id = ? AND author_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $mso_do_Id, $author_Id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($conversationId);
            $stmt->fetch();
            $stmt->close();

            $sql = "UPDATE conversations SET updated_at = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $currentTime, $conversationId);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt->close();

            $sql = "INSERT INTO conversations (mso_do_id, author_id, created_at, updated_at) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $mso_do_Id, $author_Id, $currentTime, $currentTime);
            $stmt->execute();
            $conversationId = $stmt->insert_id;
            $stmt->close();
        }

        // Step 4: Initialize PHPMailer
        $mail = new PHPMailer(true);
        try {
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

            // Step 5: Set sender and recipient
            $mail->setFrom($senderRealEmail, $name);
            $mail->addAddress($receiverRealEmail, 'Recipient');

            // Step 6: Add CC recipients
            if (!empty($ccAddresses)) {
                $ccList = explode(',', $ccAddresses);
                foreach ($ccList as $ccEmail) {
                    $ccEmail = trim($ccEmail);
                    if (!empty($ccEmail) && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($ccEmail);
                    }
                }
            }

            // Step 7: Add BCC recipients
            if (!empty($bccAddresses)) {
                $bccList = explode(',', $bccAddresses);
                foreach ($bccList as $bccEmail) {
                    $bccEmail = trim($bccEmail);
                    if (!empty($bccEmail) && filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($bccEmail);
                    }
                }
            }

            // Step 8: Add file attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['file_path'])) {
                    $mail->addAttachment($attachment['file_path'], $attachment['original_name']);
                    error_log("Added attachment: " . $attachment['original_name']);
                }
            }

            // Step 9: Set email content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            // For debugging
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer [$level] : $str");
            };

            // Step 10: Send the email
            $mail->send();
            error_log("Email with " . count($attachments) . " attachments sent successfully to: $receiverRealEmail");

            // Step 11: Store attachments info and record email in database
            $attachmentData = $this->storeAttachmentInfo($attachments);
            error_log("EmailSender storing attachments data: " . ($attachmentData ? $attachmentData : 'NULL'));

            $sql = "INSERT INTO mails (subject, content, sender_id, recipient_id, cc, bcc, conversation_id, created_at, attachments) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiississ", $subject, $body, $senderId, $receiverId, $ccAddresses, $bccAddresses, $conversationId, $currentTime, $attachmentData);

            if ($stmt->execute()) {
                $mailId = $stmt->insert_id;
                $stmt->close();
                error_log('Email with attachments recorded in database with ID: ' . $mailId);
                return true;
            } else {
                error_log("Failed to record email in database: " . $stmt->error);
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    function sendEmail($senderId, $receiverId, $name, $subject, $body, $ccAddresses = null, $bccAddresses = null)
    {
        return $this->sendEmailWithAttachments($senderId, $receiverId, $name, $subject, $body, $ccAddresses, $bccAddresses, []);
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