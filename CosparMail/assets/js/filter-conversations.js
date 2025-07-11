/**
 * Filter Conversation Manager
 * Handles displaying and managing filter-based conversation histories
 */

class FilterConversationManager {
    constructor() {
        this.currentFilterSignature = null;
        this.conversations = [];
    }

    /**
     * Initialize the conversation manager
     */
    init() {
        this.loadFilteredConversations();
        this.setupConversationSwitcher();
    }

    /**
     * Load all filtered conversations for the current user
     */
    async loadFilteredConversations() {
        try {
            const response = await fetch(`index.php?action=get_filtered_conversations`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load conversations');
            }

            const data = await response.json();
            if (data.success) {
                this.conversations = data.conversations;
                this.displayConversationSwitcher();
            } else {
                console.error('Error loading conversations:', data.message);
            }
        } catch (error) {
            console.error('Error loading filtered conversations:', error);
        }
    }

    /**
     * Display the conversation switcher in the UI
     */
    displayConversationSwitcher() {
        const rightSection = document.getElementById('rightSection');
        if (!rightSection) return;

        // Create conversation switcher header
        const existingHeader = rightSection.querySelector('.conversation-switcher');
        if (existingHeader) {
            existingHeader.remove();
        }

        if (this.conversations.length === 0) {
            return; // No conversations to show switcher for
        }

        const switcherHTML = `
            <div class="conversation-switcher">
                <div class="switcher-header">
                    <h3>Filter Conversations</h3>
                    <select id="conversationSelect" class="conversation-select">
                        <option value="">Select a filter group...</option>
                        ${this.conversations.map(conv => `
                            <option value="${conv.filter_signature}" 
                                    data-count="${conv.email_count}">
                                ${conv.human_readable_name} (${conv.email_count} emails)
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="current-filters" id="currentFiltersDisplay" style="display: none;">
                    <small>Current filter:</small>
                    <span id="currentFilterText"></span>
                </div>
            </div>
        `;

        // Insert at the top of right section
        rightSection.insertAdjacentHTML('afterbegin', switcherHTML);
    }

    /**
     * Setup event handlers for conversation switcher
     */
    setupConversationSwitcher() {
        document.addEventListener('change', (e) => {
            if (e.target.id === 'conversationSelect') {
                const filterSignature = e.target.value;
                if (filterSignature) {
                    this.loadFilteredEmailHistory(filterSignature);
                } else {
                    this.clearEmailHistory();
                }
            }
        });
    }

    /**
     * Load email history for a specific filter combination
     */
    async loadFilteredEmailHistory(filterSignature) {
        try {
            const response = await fetch(`index.php?action=get_filtered_email_history&filter_signature=${encodeURIComponent(filterSignature)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load email history');
            }

            const data = await response.json();
            if (data.success) {
                this.currentFilterSignature = filterSignature;
                this.displayFilteredEmails(data.emails, data.conversation_name);
                this.updateCurrentFilterDisplay(data.filter_criteria);
            } else {
                console.error('Error loading email history:', data.message);
            }
        } catch (error) {
            console.error('Error loading filtered email history:', error);
        }
    }

    /**
     * Display filtered emails in the email history section
     */
    displayFilteredEmails(emails, conversationName) {
        const emailHistorySection = document.querySelector('.email-history-section');
        if (!emailHistorySection) return;

        // Update author name to show filter group
        const authorNameSpan = document.getElementById('authorName');
        if (authorNameSpan) {
            authorNameSpan.textContent = conversationName;
        }

        // Clear existing emails
        emailHistorySection.innerHTML = '';

        if (emails.length === 0) {
            emailHistorySection.innerHTML = `
                <div class="no-emails-message">
                    <i class="fas fa-envelope-open"></i>
                    <p>No emails sent to this filter group yet.</p>
                    <p>Apply the same filters and send an email to create history.</p>
                </div>
            `;
            return;
        }

        // Create email list
        const emailListHTML = `
            <div class="email-list">
                ${emails.map(email => this.createEmailItemHTML(email)).join('')}
            </div>
        `;

        emailHistorySection.innerHTML = emailListHTML;

        // Add event listeners for view details buttons
        emailHistorySection.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const emailId = e.target.getAttribute('data-email-id');
                this.viewEmailDetails(emailId);
            });
        });
    }

    /**
     * Create HTML for a single email item
     */
    createEmailItemHTML(email) {
        const formattedDate = new Date(email.created_at * 1000).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });

        const timeAgo = this.formatTimeAgo(Date.now() / 1000 - email.created_at);
        const contentPreview = email.content.replace(/<[^>]*>/g, '').substring(0, 100) + '...';
        const senderName = `${email.sender_first} ${email.sender_last}`;

        // Build CC/BCC info
        let ccBccInfo = '';
        if (email.cc) {
            ccBccInfo += `<div class="email-cc"><strong>Cc:</strong> ${email.cc}</div>`;
        }
        if (email.bcc) {
            ccBccInfo += `<div class="email-bcc"><strong>Bcc:</strong> ${email.bcc}</div>`;
        }

        // Show filter criteria used
        const filterInfo = this.formatFilterCriteria(email.filter_criteria);

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
     * Format filter criteria for display
     */
    formatFilterCriteria(filterCriteria) {
        if (!filterCriteria || Object.keys(filterCriteria).length === 0) {
            return 'All Authors';
        }

        const parts = [];
        
        if (filterCriteria.author_type && filterCriteria.author_type !== 'all') {
            const typeMap = {
                'presenting': 'Presenting Authors',
                'co_authors': 'Co-Authors'
            };
            parts.push(typeMap[filterCriteria.author_type] || filterCriteria.author_type);
        }

        if (filterCriteria.presentation_type && filterCriteria.presentation_type !== 'all') {
            const typeMap = {
                'oral': 'Oral',
                'poster': 'Poster'
            };
            parts.push(typeMap[filterCriteria.presentation_type] || filterCriteria.presentation_type);
        }

        if (filterCriteria.has_presentation && filterCriteria.has_presentation !== 'all') {
            const statusMap = {
                'with': 'With Uploads',
                'without': 'Without Uploads'
            };
            parts.push(statusMap[filterCriteria.has_presentation] || filterCriteria.has_presentation);
        }

        return parts.length > 0 ? parts.join(' + ') : 'All Authors';
    }

    /**
     * Format time ago helper
     */
    formatTimeAgo(secondsAgo) {
        if (secondsAgo < 60) return 'Just now';
        if (secondsAgo < 3600) return Math.floor(secondsAgo / 60) + ' minutes ago';
        if (secondsAgo < 86400) return Math.floor(secondsAgo / 3600) + ' hours ago';
        if (secondsAgo < 604800) return Math.floor(secondsAgo / 86400) + ' days ago';
        return Math.floor(secondsAgo / 604800) + ' weeks ago';
    }

    /**
     * Update current filter display
     */
    updateCurrentFilterDisplay(filterCriteria) {
        const currentFiltersDiv = document.getElementById('currentFiltersDisplay');
        const currentFilterText = document.getElementById('currentFilterText');
        
        if (currentFiltersDiv && currentFilterText) {
            const filterText = this.formatFilterCriteria(filterCriteria);
            currentFilterText.textContent = filterText;
            currentFiltersDiv.style.display = 'block';
        }
    }

    /**
     * Clear email history display
     */
    clearEmailHistory() {
        const emailHistorySection = document.querySelector('.email-history-section');
        if (emailHistorySection) {
            emailHistorySection.innerHTML = `
                <div class="no-emails-message">
                    <i class="fas fa-filter"></i>
                    <p>Select a filter group to view email history.</p>
                </div>
            `;
        }

        const currentFiltersDiv = document.getElementById('currentFiltersDisplay');
        if (currentFiltersDiv) {
            currentFiltersDiv.style.display = 'none';
        }
    }

    /**
     * View email details
     */
    viewEmailDetails(emailId) {
        // Use existing email details functionality
        if (typeof window.viewEmailDetails === 'function') {
            window.viewEmailDetails(emailId, 'group');
        } else {
            console.error('viewEmailDetails function not found');
        }
    }

    /**
     * Get current filter signature from form or wizard
     */
    getCurrentFilterSignature() {
        // This should be called when sending a new email to determine which conversation to add it to
        const filterWizard = window.filterWizard;
        if (filterWizard && filterWizard.filters) {
            return this.createFilterSignature(filterWizard.filters);
        }
        return 'all_authors';
    }

    /**
     * Create filter signature (matching PHP version)
     */
    createFilterSignature(filterCriteria) {
        // Remove 'all' values
        const activeFilters = {};
        for (const [key, value] of Object.entries(filterCriteria)) {
            if (value !== 'all' && value !== null && value !== '') {
                activeFilters[key] = value;
            }
        }

        if (Object.keys(activeFilters).length === 0) {
            return 'all_authors';
        }

        // Sort and create signature
        const sortedKeys = Object.keys(activeFilters).sort();
        const parts = sortedKeys.map(key => `${key}_${activeFilters[key]}`);
        
        return parts.join('_');
    }

    /**
     * Handle bulk email mode activation
     */
    onBulkEmailActivated() {
        // When user clicks "All Authors", we should show the conversation switcher
        this.init();
    }
}

// Initialize the filter conversation manager
const filterConversationManager = new FilterConversationManager();

// Export for global access
window.filterConversationManager = filterConversationManager;

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize when "All Authors" mode is activated
    document.addEventListener('click', function(e) {
        if (e.target.id === 'allAuthorsBtn' || e.target.closest('#allAuthorsBtn')) {
            setTimeout(() => {
                filterConversationManager.onBulkEmailActivated();
            }, 100);
        }
    });
});