/**
 * Group Conversation Manager
 * Handles filter-based conversation viewing and management
 */

class GroupConversationManager {
    constructor() {
        this.conversations = [];
        this.currentConversation = null;
        this.isVisible = false;
    }

    /**
     * Initialize the conversation manager
     */
    async init() {
        console.log('Initializing Group Conversation Manager');
        
        // Show the manager UI
        this.show();
        
        // Load existing conversations
        await this.loadConversations();
        
        // Setup event handlers
        this.setupEventHandlers();
        
        // Setup quick filter buttons
        this.setupQuickFilters();
    }

    /**
     * Show the conversation manager UI
     */
    show() {
        const manager = document.getElementById('groupConversationManager');
        if (manager) {
            manager.style.display = 'block';
            this.isVisible = true;
        }
    }

    /**
     * Hide the conversation manager UI
     */
    hide() {
        const manager = document.getElementById('groupConversationManager');
        if (manager) {
            manager.style.display = 'none';
            this.isVisible = false;
        }
    }

    /**
     * Load all existing conversations from the server
     */
    async loadConversations() {
        try {
            console.log('Loading filter conversations...');
            
            const response = await fetch('index.php?action=get_filtered_conversations', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.conversations = data.conversations;
                console.log(`Loaded ${this.conversations.length} conversations`);
                this.updateConversationDropdown();
            } else {
                console.error('Failed to load conversations:', data.message);
                this.showError('Failed to load conversation history');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            this.showError('Network error loading conversations');
        }
    }

    /**
     * Update the conversation dropdown with available conversations
     */
    updateConversationDropdown() {
        const select = document.getElementById('conversationSelect');
        if (!select) return;

        // Clear existing options except the first one
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }

        // Add conversation options
        this.conversations.forEach(conv => {
            const option = document.createElement('option');
            option.value = conv.filter_signature;
            option.textContent = `${conv.human_readable_name} (${conv.email_count} emails)`;
            option.dataset.conversation = JSON.stringify(conv);
            select.appendChild(option);
        });

        // Update placeholder text based on whether we have conversations
        const placeholder = select.firstElementChild;
        if (this.conversations.length === 0) {
            placeholder.textContent = 'No filter conversations yet - send a group email to create one';
        } else {
            placeholder.textContent = 'Select a filter group to view history...';
        }
    }

    /**
     * Setup event handlers
     */
    setupEventHandlers() {
        // Conversation selection handler
        const select = document.getElementById('conversationSelect');
        if (select) {
            select.addEventListener('change', (e) => {
                if (e.target.value) {
                    const convData = JSON.parse(e.target.selectedOptions[0].dataset.conversation);
                    this.selectConversation(convData);
                } else {
                    this.clearSelection();
                }
            });
        }

        // Refresh button handler
        const refreshBtn = document.getElementById('refreshHistory');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                if (this.currentConversation) {
                    this.loadConversationHistory(this.currentConversation.filter_signature);
                } else {
                    this.loadConversations();
                }
            });
        }
    }

    /**
     * Setup quick filter buttons
     */
    setupQuickFilters() {
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const filterData = JSON.parse(btn.dataset.filter);
                this.applyQuickFilter(filterData);
            });
        });
    }

    /**
     * Apply a quick filter
     */
    applyQuickFilter(filterData) {
        console.log('Applying quick filter:', filterData);
        
        // Find matching conversation
        const signature = this.createFilterSignature(filterData);
        const conversation = this.conversations.find(c => c.filter_signature === signature);
        
        if (conversation) {
            // Select existing conversation
            const select = document.getElementById('conversationSelect');
            if (select) {
                select.value = signature;
                this.selectConversation(conversation);
            }
        } else {
            // No existing conversation for this filter
            this.showPlaceholder(`No emails sent with filter: ${this.createFilterDisplayText(filterData)}`);
        }
        
        // Update active button state
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        event.target.closest('.filter-btn').classList.add('active');
    }

    /**
     * Select and load a conversation
     */
    async selectConversation(conversation) {
        console.log('Selecting conversation:', conversation);
        
        this.currentConversation = conversation;
        
        // Update UI with conversation info
        this.updateCurrentFilterDisplay(conversation.filter_criteria);
        this.updateConversationStats(conversation);
        
        // Load conversation history
        await this.loadConversationHistory(conversation.filter_signature);
    }

    /**
     * Load history for a specific conversation
     */
    async loadConversationHistory(filterSignature) {
        try {
            const historyContent = document.getElementById('filteredHistoryContent');
            if (!historyContent) return;

            // Show loading
            historyContent.innerHTML = '<div class="loading-message"><i class="fas fa-spinner fa-spin"></i> Loading conversation history...</div>';

            const response = await fetch(`index.php?action=get_filtered_email_history&filter_signature=${encodeURIComponent(filterSignature)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.displayEmailHistory(data.emails, data.conversation_name);
                this.updateConversationTitle(data.conversation_name);
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('Error loading conversation history:', error);
            this.showError('Failed to load conversation history');
        }
    }

    /**
     * Display email history in the UI
     */
    displayEmailHistory(emails, conversationName) {
        const historyContent = document.getElementById('filteredHistoryContent');
        if (!historyContent) return;

        if (emails.length === 0) {
            historyContent.innerHTML = `
                <div class="placeholder-message">
                    <i class="fas fa-envelope-open"></i>
                    <p>No emails in this conversation yet.</p>
                    <p>Apply the same filters and send a group email to add to this conversation.</p>
                </div>
            `;
            return;
        }

        // Generate email list HTML
        const emailListHTML = emails.map(email => this.createEmailHTML(email)).join('');
        historyContent.innerHTML = `<div class="email-list">${emailListHTML}</div>`;

        // Setup view details buttons
        this.setupViewDetailsButtons();
    }

    /**
     * Create HTML for a single email item
     */
    createEmailHTML(email) {
        const sentDate = new Date(email.created_at * 1000);
        const timeAgo = this.formatTimeAgo((Date.now() / 1000) - email.created_at);
        const contentPreview = email.content.replace(/<[^>]*>/g, '').substring(0, 100) + '...';
        const senderName = `${email.sender_first} ${email.sender_last}`;

        // CC/BCC info
        let ccBccInfo = '';
        if (email.cc) {
            ccBccInfo += `<div class="email-cc"><strong>Cc:</strong> ${email.cc}</div>`;
        }
        if (email.bcc) {
            ccBccInfo += `<div class="email-bcc"><strong>Bcc:</strong> ${email.bcc}</div>`;
        }

        // Filter info
        const filterInfo = this.createFilterDisplayText(email.filter_criteria);

        return `
            <div class="email-item outbound">
                <div class="email-header">
                    <div class="email-direction"><i class="fas fa-paper-plane text-primary"></i></div>
                    <div class="email-subject">${email.subject}</div>
                    <div class="email-time">${timeAgo}</div>
                </div>
                <div class="email-preview">${contentPreview}</div>
                <div class="email-participants">
                    <span><strong>From:</strong> ${senderName}</span>
                    <span><strong>To:</strong> Filtered Authors (${email.recipient_count} recipients)</span>
                    ${ccBccInfo}
                </div>
                <div class="email-filter-info">
                    <small><strong>Filter:</strong> ${filterInfo}</small>
                </div>
                <div class="email-footer">
                    <button class="view-details-btn" data-email-id="${email.id}" data-type="group">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Setup view details button handlers
     */
    setupViewDetailsButtons() {
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const emailId = e.target.closest('.view-details-btn').dataset.emailId;
                const type = e.target.closest('.view-details-btn').dataset.type;
                
                if (window.viewEmailDetails) {
                    window.viewEmailDetails(emailId, type);
                } else {
                    console.error('viewEmailDetails function not found');
                }
            });
        });
    }

    /**
     * Update current filter display
     */
    updateCurrentFilterDisplay(filterCriteria) {
        const display = document.getElementById('currentFilterDisplay');
        const badges = document.getElementById('filterBadges');
        
        if (!display || !badges) return;

        if (!filterCriteria || Object.keys(filterCriteria).length === 0) {
            display.style.display = 'none';
            return;
        }

        // Create filter badges
        const badgeHTML = this.createFilterBadges(filterCriteria);
        badges.innerHTML = badgeHTML;
        display.style.display = 'block';
    }

    /**
     * Create filter badges HTML
     */
    createFilterBadges(filterCriteria) {
        const badges = [];
        
        if (filterCriteria.author_type && filterCriteria.author_type !== 'all') {
            badges.push(`<span class="filter-badge">${this.getAuthorTypeLabel(filterCriteria.author_type)}</span>`);
        }
        
        if (filterCriteria.presentation_type && filterCriteria.presentation_type !== 'all') {
            badges.push(`<span class="filter-badge">${this.getPresentationTypeLabel(filterCriteria.presentation_type)}</span>`);
        }
        
        if (filterCriteria.has_presentation && filterCriteria.has_presentation !== 'all') {
            badges.push(`<span class="filter-badge">${this.getUploadStatusLabel(filterCriteria.has_presentation)}</span>`);
        }

        return badges.join('');
    }

    /**
     * Update conversation stats
     */
    updateConversationStats(conversation) {
        const stats = document.getElementById('conversationStats');
        if (!stats) return;

        const emailCount = document.getElementById('emailCount');
        const recipientCount = document.getElementById('recipientCount');
        const lastEmailDate = document.getElementById('lastEmailDate');

        if (emailCount) emailCount.textContent = conversation.email_count;
        if (recipientCount) recipientCount.textContent = 'N/A'; // Would need additional data
        if (lastEmailDate) {
            const date = conversation.last_email_date ? 
                new Date(conversation.last_email_date * 1000).toLocaleDateString() : 'Never';
            lastEmailDate.textContent = date;
        }

        stats.style.display = 'block';
    }

    /**
     * Update conversation title
     */
    updateConversationTitle(conversationName) {
        const title = document.getElementById('conversationTitle');
        if (title) {
            title.innerHTML = `<i class="fas fa-comments"></i> ${conversationName}`;
        }
    }

    /**
     * Clear current selection
     */
    clearSelection() {
        this.currentConversation = null;
        
        const display = document.getElementById('currentFilterDisplay');
        const stats = document.getElementById('conversationStats');
        const title = document.getElementById('conversationTitle');
        const content = document.getElementById('filteredHistoryContent');
        
        if (display) display.style.display = 'none';
        if (stats) stats.style.display = 'none';
        if (title) title.textContent = 'Email History';
        if (content) {
            content.innerHTML = `
                <div class="placeholder-message">
                    <i class="fas fa-filter"></i>
                    <p>Select a filter combination above to view conversation history.</p>
                </div>
            `;
        }

        // Remove active states from quick filter buttons
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    }

    /**
     * Helper functions for labels and formatting
     */
    getAuthorTypeLabel(type) {
        const labels = {
            'presenting': 'Presenting',
            'co_authors': 'Co-Authors'
        };
        return labels[type] || type;
    }

    getPresentationTypeLabel(type) {
        const labels = {
            'oral': 'Oral',
            'poster': 'Poster'
        };
        return labels[type] || type;
    }

    getUploadStatusLabel(status) {
        const labels = {
            'with': 'With Uploads',
            'without': 'Missing Uploads'
        };
        return labels[status] || status;
    }

    createFilterDisplayText(filterCriteria) {
        const parts = [];
        
        if (filterCriteria.author_type && filterCriteria.author_type !== 'all') {
            parts.push(this.getAuthorTypeLabel(filterCriteria.author_type));
        }
        
        if (filterCriteria.presentation_type && filterCriteria.presentation_type !== 'all') {
            parts.push(this.getPresentationTypeLabel(filterCriteria.presentation_type));
        }
        
        if (filterCriteria.has_presentation && filterCriteria.has_presentation !== 'all') {
            parts.push(this.getUploadStatusLabel(filterCriteria.has_presentation));
        }

        return parts.length > 0 ? parts.join(' + ') : 'All Authors';
    }

    createFilterSignature(filterCriteria) {
        const activeFilters = {};
        for (const [key, value] of Object.entries(filterCriteria)) {
            if (value !== 'all' && value !== null && value !== '') {
                activeFilters[key] = value;
            }
        }

        if (Object.keys(activeFilters).length === 0) {
            return 'all_authors';
        }

        const sortedKeys = Object.keys(activeFilters).sort();
        const parts = sortedKeys.map(key => `${key}_${activeFilters[key]}`);
        
        return parts.join('_');
    }

    formatTimeAgo(secondsAgo) {
        if (secondsAgo < 60) return 'Just now';
        if (secondsAgo < 3600) return Math.floor(secondsAgo / 60) + ' minutes ago';
        if (secondsAgo < 86400) return Math.floor(secondsAgo / 3600) + ' hours ago';
        if (secondsAgo < 604800) return Math.floor(secondsAgo / 86400) + ' days ago';
        return Math.floor(secondsAgo / 604800) + ' weeks ago';
    }

    showError(message) {
        const content = document.getElementById('filteredHistoryContent');
        if (content) {
            content.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                </div>
            `;
        }
    }

    showPlaceholder(message) {
        const content = document.getElementById('filteredHistoryContent');
        if (content) {
            content.innerHTML = `
                <div class="placeholder-message">
                    <i class="fas fa-info-circle"></i>
                    <p>${message}</p>
                </div>
            `;
        }
    }
}

// Initialize the group conversation manager
let groupConversationManager = null;

// Function to show group conversation manager when "All Authors" is selected
function showGroupConversationManager() {
    if (!groupConversationManager) {
        groupConversationManager = new GroupConversationManager();
    }
    groupConversationManager.init();
}

// Function to hide group conversation manager
function hideGroupConversationManager() {
    if (groupConversationManager) {
        groupConversationManager.hide();
    }
}

// Export for global access
window.showGroupConversationManager = showGroupConversationManager;
window.hideGroupConversationManager = hideGroupConversationManager;
window.groupConversationManager = groupConversationManager;