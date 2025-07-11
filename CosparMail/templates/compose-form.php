<?php
/**
 * Simplified Email Composition Form Template with Professional Attachments
 */
?>
<div id="mailPopup" class="popup">
    <div class="popup-content">
        <span class="close-btn">&times;</span>
        <h2>New Message</h2>
        <form id="mailForm" method="POST" action="" enctype="multipart/form-data">
            <!-- Hidden input for receiver ID -->
            <input type="hidden" id="receiverId" name="receiverId" value="">

            <!-- Hidden fields for bulk email functionality -->
            <input type="hidden" id="author_ids" name="author_ids" value="">
            <input type="hidden" id="bulk_email" name="bulk_email" value="false">
            <input type="hidden" id="group_email" name="group_email" value="false">

            <!-- Main recipient field with CC/BCC toggle buttons -->
            <div class="recipient-row">
                <div class="recipient-label">
                    <label for="recipient">To:</label>
                </div>
                <div class="recipient-input">
                    <input type="email" id="recipient" name="recipient" readonly required>
                </div>
                <div class="recipient-toggles">
                    <button type="button" id="toggleCc" class="toggle-btn">Cc</button>
                    <button type="button" id="toggleBcc" class="toggle-btn">Bcc</button>
                </div>
            </div>

            <!-- CC field (hidden by default) -->
            <div id="ccField" class="recipient-row" style="display: none;">
                <div class="recipient-label">
                    <label for="cc">Cc:</label>
                </div>
                <div class="recipient-input">
                    <input type="email" id="cc" name="cc" multiple>
                </div>
            </div>

            <!-- BCC field (hidden by default) -->
            <div id="bccField" class="recipient-row" style="display: none;">
                <div class="recipient-label">
                    <label for="bcc">Bcc:</label>
                </div>
                <div class="recipient-input">
                    <input type="email" id="bcc" name="bcc" multiple>
                </div>
            </div>

            <label for="name">Your Name:</label>
            <input type="text" id="name" name="name" required value="<?php echo getUserName($userId); ?>" readonly>

            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" required>

            <label for="body">Message:</label>
            <textarea id="body" name="body" required></textarea>

            <!-- Simplified File Attachments Section -->
            <div class="attachments-section">
                <label for="attachments">Attachments:</label>
                <div class="file-upload-container">
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-content">
                            <i class="fas fa-paperclip"></i>
                            <p>Drop files here or <span class="file-upload-link">browse</span></p>
                            <p class="file-upload-hint">Max 10MB • PDF, DOC, XLS, PPT, images, TXT</p>
                        </div>
                        <input type="file" id="attachments" name="attachments[]" multiple
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                    </div>

                    <!-- Selected Files Display -->
                    <div class="selected-files" id="selectedFiles" style="display: none;">
                        <h4>Selected Files:</h4>
                        <div class="files-list" id="filesList"></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="submit" class="send-mail-btn">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
                <button type="button" class="cancel-mail-btn" id="cancelMailPopup">Cancel</button>
            </div>
        </form>
    </div>
</div>