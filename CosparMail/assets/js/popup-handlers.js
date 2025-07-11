/**
 * Fixed File Attachment JavaScript - Prevents Duplicates with Improved UI
 */

let selectedFiles = [];

function setupEmailPopup() {
    const openPopupBtn = document.getElementById("openMailPopup");
    const mailPopup = document.getElementById("mailPopup");
    const closePopupBtn = document.querySelector(".close-btn");
    const cancelPopupBtn = document.getElementById("cancelMailPopup");

    // Show popup when clicking "New Mail"
    if (openPopupBtn) {
        openPopupBtn.addEventListener("click", function () {
            console.log("New Mail button clicked");
            mailPopup.style.display = "flex";
            
            // Always reset CC and BCC fields
            document.getElementById("ccField").setAttribute("style", "display: none !important");
            document.getElementById("bccField").setAttribute("style", "display: none !important");
            document.getElementById("cc").value = "";
            document.getElementById("bcc").value = "";
            
            // Clear selected files
            clearSelectedFiles();
        });
    }
    
    // Close popup handlers
    if (closePopupBtn) closePopupBtn.addEventListener("click", function() { 
        mailPopup.style.display = "none"; 
        clearSelectedFiles();
    });
    if (cancelPopupBtn) cancelPopupBtn.addEventListener("click", function() { 
        mailPopup.style.display = "none"; 
        clearSelectedFiles();
    });
    window.addEventListener("click", function(event) { 
        if (event.target === mailPopup) {
            mailPopup.style.display = "none"; 
            clearSelectedFiles();
        }
    });

    // Direct DOM manipulation for CC button
    document.getElementById("toggleCc").onclick = function(e) {
        e.preventDefault();
        const ccField = document.getElementById("ccField");
        console.log("CC button clicked - DIRECT MANIPULATION");
        
        if (ccField.style.display === "none" || ccField.style.display === "") {
            ccField.setAttribute("style", "display: flex !important");
            this.classList.add("active");
        } else {
            ccField.setAttribute("style", "display: none !important");
            this.classList.remove("active");
        }
        return false;
    };

    // Direct DOM manipulation for BCC button
    document.getElementById("toggleBcc").onclick = function(e) {
        e.preventDefault();
        const bccField = document.getElementById("bccField");
        console.log("BCC button clicked - DIRECT MANIPULATION");
        
        if (bccField.style.display === "none" || bccField.style.display === "") {
            bccField.setAttribute("style", "display: flex !important");
            this.classList.add("active");
        } else {
            bccField.setAttribute("style", "display: none !important");
            this.classList.remove("active");
        }
        return false;
    };
    
    // Setup file attachment functionality
    setupFileAttachments();
}

function setupFileAttachments() {
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('attachments');
    const selectedFilesDiv = document.getElementById('selectedFiles');
    const filesList = document.getElementById('filesList');
    
    if (!fileUploadArea || !fileInput) return;
    
    // Click to browse files
    fileUploadArea.addEventListener('click', function(e) {
        if (e.target.classList.contains('file-upload-link') || e.target.closest('.file-upload-content')) {
            fileInput.click();
        }
    });
    
    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('dragover');
    });
    
    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
    });
    
    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        handleFileSelection(files);
    });
    
    // File input change
    fileInput.addEventListener('change', function(e) {
        handleFileSelection(this.files);
    });
}

function handleFileSelection(files) {
    console.log(`Selected ${files.length} files`);
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // Check if file is already selected (prevent duplicates)
        const existingFile = selectedFiles.find(f => 
            f.name === file.name && f.size === file.size && f.type === file.type
        );
        
        if (existingFile) {
            console.log(`File ${file.name} already selected, skipping...`);
            continue;
        }
        
        // Validate file
        const validation = validateFile(file);
        if (!validation.valid) {
            showFileError(file.name, validation.error);
            continue;
        }
        
        // Add to selected files
        selectedFiles.push({
            file: file,
            id: generateFileId(),
            name: file.name,
            size: file.size,
            type: file.type,
            extension: getFileExtension(file.name)
        });
    }
    
    updateSelectedFilesDisplay();
    updateFileInput();
}

function validateFile(file) {
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (file.size > maxSize) {
        return { valid: false, error: 'File size exceeds 10MB limit' };
    }
    
    const extension = getFileExtension(file.name).toLowerCase();
    const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    
    if (!allowedExtensions.includes(extension)) {
        return { valid: false, error: 'File type not allowed' };
    }
    
    return { valid: true };
}

function updateSelectedFilesDisplay() {
    const selectedFilesDiv = document.getElementById('selectedFiles');
    const filesList = document.getElementById('filesList');
    
    if (selectedFiles.length === 0) {
        selectedFilesDiv.style.display = 'none';
        return;
    }
    
    selectedFilesDiv.style.display = 'block';
    filesList.innerHTML = '';
    
    selectedFiles.forEach(fileObj => {
        const fileItem = createFileItem(fileObj);
        filesList.appendChild(fileItem);
    });
}

function createFileItem(fileObj) {
    const fileItem = document.createElement('div');
    fileItem.className = 'file-item';
    fileItem.dataset.fileId = fileObj.id;
    
    const extension = fileObj.extension.toLowerCase();
    const formattedSize = formatFileSize(fileObj.size);
    
    // Create file icon with proper data attribute for styling
    const fileIconText = getFileIconText(extension);
    
    fileItem.innerHTML = `
        <div class="file-info">
            <div class="file-icon" data-type="${extension}">
                ${fileIconText}
            </div>
            <div class="file-details">
                <div class="file-name" title="${fileObj.name}">${fileObj.name}</div>
                <div class="file-size">${formattedSize}</div>
            </div>
        </div>
        <button type="button" class="file-remove" onclick="removeFile('${fileObj.id}')" title="Remove file">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    return fileItem;
}

function removeFile(fileId) {
    selectedFiles = selectedFiles.filter(f => f.id !== fileId);
    updateSelectedFilesDisplay();
    updateFileInput();
}

function clearSelectedFiles() {
    selectedFiles = [];
    updateSelectedFilesDisplay();
    const fileInput = document.getElementById('attachments');
    if (fileInput) fileInput.value = '';
}

function updateFileInput() {
    // Create a new DataTransfer object to update the file input
    const dt = new DataTransfer();
    selectedFiles.forEach(fileObj => {
        dt.items.add(fileObj.file);
    });
    
    const fileInput = document.getElementById('attachments');
    if (fileInput) {
        fileInput.files = dt.files;
    }
}

function getFileExtension(filename) {
    return filename.split('.').pop() || '';
}

function getFileIconText(extension) {
    const iconMap = {
        'pdf': 'PDF',
        'doc': 'DOC',
        'docx': 'DOC',
        'xls': 'XLS',
        'xlsx': 'XLS',
        'ppt': 'PPT',
        'pptx': 'PPT',
        'txt': 'TXT',
        'jpg': 'IMG',
        'jpeg': 'IMG',
        'png': 'IMG',
        'gif': 'IMG'
    };
    
    return iconMap[extension] || 'FILE';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function generateFileId() {
    return 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

function showFileError(filename, error) {
    console.error(`File error for ${filename}: ${error}`);
    // Simple alert for now - you can make this prettier later
    alert(`Error with file "${filename}": ${error}`);
}

function setupDetailsPopup() {
    const closeDetailsBtn = document.getElementById('closeDetailsBtn');
    const emailDetailsModal = document.getElementById('emailDetailsModal');

    if (closeDetailsBtn) {
        closeDetailsBtn.addEventListener('click', function () {
            emailDetailsModal.style.display = 'none';
        });
    }

    window.addEventListener('click', function (event) {
        if (event.target === emailDetailsModal) {
            emailDetailsModal.style.display = 'none';
        }
    });
}

window.setupEmailPopup = setupEmailPopup;
window.setupDetailsPopup = setupDetailsPopup;