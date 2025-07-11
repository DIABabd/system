/**
 * COSPAR Mail System - Filter Wizard (Cleaned Version)
 * 
 * Handles the step-by-step filtering process for authors
 */

class FilterWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4;
        this.filters = {
            author_type: 'all',
            presentation_type: 'all',
            has_presentation: 'all'
        };
        this.isOpen = false;
        this.createModal();
    }

    /**
     * Creates the filter wizard modal HTML and inserts it into the DOM
     * This builds the entire 4-step wizard interface dynamically
     */
    createModal() {
        // Remove existing modal if present (prevents duplicates)
        const existingModal = document.getElementById('filterWizardModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create complete modal HTML with all 4 steps
        const modalHTML = `
            <div id="filterWizardModal" class="filter-wizard-modal" style="display: none;">
                <div class="filter-wizard-content">
                    <div class="filter-wizard-header">
                        <h2>Filter Authors</h2>
                        <button class="close-wizard" onclick="filterWizard.close()">&times;</button>
                    </div>
                    
                    <div class="filter-wizard-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="wizardProgress"></div>
                        </div>
                        <div class="step-indicator">
                            Step <span id="currentStepNum">1</span> of ${this.totalSteps}
                        </div>
                    </div>

                    <div class="filter-wizard-body">
                        <!-- Step 1: Author Type -->
                        <div class="wizard-step" id="step1" style="display: block;">
                            <h3>Choose Author Type</h3>
                            <p>Select which types of authors you want to contact:</p>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="author_type" value="all" checked>
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>All Authors</strong>
                                        <span class="option-description">Include all types of authors</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="author_type" value="first_author">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>First Authors Only</strong>
                                        <span class="option-description">Only contact primary authors</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="author_type" value="presenting_author">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>Presenting Authors Only</strong>
                                        <span class="option-description">Only contact authors who will present</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="author_type" value="co_author">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>Co-Authors Only</strong>
                                        <span class="option-description">Only contact co-authors</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Step 2: Presentation Type -->
                        <div class="wizard-step" id="step2" style="display: none;">
                            <h3>Choose Presentation Type</h3>
                            <p>Select the type of presentations you want to target:</p>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="presentation_type" value="all" checked>
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>All Presentations</strong>
                                        <span class="option-description">Include both oral and poster presentations</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="presentation_type" value="oral">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>Oral Presentations Only</strong>
                                        <span class="option-description">Only contact authors with oral presentations</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="presentation_type" value="poster">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>Poster Sessions Only</strong>
                                        <span class="option-description">Only contact authors with poster presentations</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Step 3: Upload Status -->
                        <div class="wizard-step" id="step3" style="display: none;">
                            <h3>Choose Upload Status</h3>
                            <p>Filter based on whether authors have uploaded their presentations:</p>
                            <div class="filter-options">
                                <label class="filter-option">
                                    <input type="radio" name="has_presentation" value="all" checked>
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>All Authors</strong>
                                        <span class="option-description">Include all authors regardless of upload status</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="has_presentation" value="with">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>With Uploaded Presentation</strong>
                                        <span class="option-description">Only contact authors who have uploaded presentations</span>
                                    </div>
                                </label>
                                <label class="filter-option">
                                    <input type="radio" name="has_presentation" value="without">
                                    <span class="checkmark"></span>
                                    <div class="option-content">
                                        <strong>Without Uploaded Presentation</strong>
                                        <span class="option-description">Only contact authors who haven't uploaded presentations</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Step 4: Summary -->
                        <div class="wizard-step" id="step4" style="display: none;">
                            <h3>Review Filter Settings</h3>
                            <p>Review your filter settings before applying:</p>

                            <div class="filter-summary">
                                <h4>Your Filter Settings:</h4>
                                <div class="summary-item">
                                    <strong>Author Type:</strong> <span id="summaryAuthorType">All Authors</span>
                                </div>
                                <div class="summary-item">
                                    <strong>Presentation Type:</strong> <span id="summaryPresentationType">All Presentations</span>
                                </div>
                                <div class="summary-item">
                                    <strong>Upload Status:</strong> <span id="summaryUploadStatus">All Authors</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-wizard-footer">
                        <button class="wizard-btn secondary" id="wizardPrevious" onclick="filterWizard.previousStep()" style="display: none;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button class="wizard-btn primary" id="wizardNext" onclick="filterWizard.nextStep()">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button class="wizard-btn success" id="wizardApply" onclick="filterWizard.applyFilters()" style="display: none;">
                            <i class="fas fa-check"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Insert modal into the page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    /**
     * Opens the filter wizard modal
     * Resets to step 1 and shows the modal
     */
    open() {
        console.log('Opening filter wizard');
        this.isOpen = true;
        document.getElementById('filterWizardModal').style.display = 'flex';
        this.currentStep = 1;
        this.updateStepDisplay();
    }

    /**
     * Closes the filter wizard modal
     * Simply hides the modal without applying any changes
     */
    close() {
        console.log('Closing filter wizard');
        this.isOpen = false;
        document.getElementById('filterWizardModal').style.display = 'none';
    }

    /**
     * Advances to the next step in the wizard
     * Updates the display and triggers summary update on final step
     */
    nextStep() {
        console.log('Moving to next step from:', this.currentStep);
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.updateStepDisplay();
            if (this.currentStep === this.totalSteps) {
                this.updateSummary();
            }
        }
    }

    /**
     * Goes back to the previous step in the wizard
     * Only works if not on the first step
     */
    previousStep() {
        console.log('Moving to previous step from:', this.currentStep);
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    }

    /**
     * Updates the visual display of the current step
     * Handles step visibility, progress bar, step counter, and button states
     */
    updateStepDisplay() {
        console.log('Updating step display to:', this.currentStep);
        
        // Hide all steps first
        for (let i = 1; i <= this.totalSteps; i++) {
            const step = document.getElementById(`step${i}`);
            if (step) {
                step.style.display = 'none';
            }
        }

        // Show current step
        const currentStepElement = document.getElementById(`step${this.currentStep}`);
        if (currentStepElement) {
            currentStepElement.style.display = 'block';
        }

        // Update progress bar (shows percentage completion)
        const progress = (this.currentStep / this.totalSteps) * 100;
        const progressBar = document.getElementById('wizardProgress');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }

        // Update step number display
        const stepNum = document.getElementById('currentStepNum');
        if (stepNum) {
            stepNum.textContent = this.currentStep;
        }

        // Update button visibility based on current step
        const prevBtn = document.getElementById('wizardPrevious');
        const nextBtn = document.getElementById('wizardNext');
        const applyBtn = document.getElementById('wizardApply');

        // Show Previous button only if not on first step
        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'inline-block' : 'none';
        }

        // On final step, show Apply button instead of Next
        if (this.currentStep === this.totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (applyBtn) applyBtn.style.display = 'inline-block';
        } else {
            if (nextBtn) nextBtn.style.display = 'inline-block';
            if (applyBtn) applyBtn.style.display = 'none';
        }
    }

    /**
     * Updates the summary display on the final step
     * Reads current form selections and shows human-readable labels
     */
    updateSummary() {
        console.log('Updating summary');
        
        // Get currently selected values from radio buttons
        const authorType = document.querySelector('input[name="author_type"]:checked')?.value || 'all';
        const presentationType = document.querySelector('input[name="presentation_type"]:checked')?.value || 'all';
        const hasPresentation = document.querySelector('input[name="has_presentation"]:checked')?.value || 'all';

        console.log('Selected filters:', { authorType, presentationType, hasPresentation });

        // Map form values to human-readable labels
        const summaryMap = {
            author_type: {
                'all': 'All Authors',
                'first_author': 'First Authors Only',
                'presenting_author': 'Presenting Authors Only',
                'co_author': 'Co-Authors Only'
            },
            presentation_type: {
                'all': 'All Presentations',
                'oral': 'Oral Presentations Only',
                'poster': 'Poster Sessions Only'
            },
            has_presentation: {
                'all': 'All Authors',
                'with': 'With Uploaded Presentation',
                'without': 'Without Uploaded Presentation'
            }
        };

        // Update the summary display elements
        const summaryAuthorType = document.getElementById('summaryAuthorType');
        const summaryPresentationType = document.getElementById('summaryPresentationType');
        const summaryUploadStatus = document.getElementById('summaryUploadStatus');

        if (summaryAuthorType) summaryAuthorType.textContent = summaryMap.author_type[authorType];
        if (summaryPresentationType) summaryPresentationType.textContent = summaryMap.presentation_type[presentationType];
        if (summaryUploadStatus) summaryUploadStatus.textContent = summaryMap.has_presentation[hasPresentation];
    }

    /**
     * Collects all filter selections and applies them
     * This is called when user clicks "Apply Filters" on the final step
     */
    applyFilters() {
        console.log('Applying filters');
        
        // Collect all selected filter values from the form
        this.filters = {
            author_type: document.querySelector('input[name="author_type"]:checked')?.value || 'all',
            presentation_type: document.querySelector('input[name="presentation_type"]:checked')?.value || 'all',
            has_presentation: document.querySelector('input[name="has_presentation"]:checked')?.value || 'all'
        };

        console.log('Filter values collected:', this.filters);

        // Close the wizard modal
        this.close();

        // Execute the actual filtering
        this.executeFilters();
    }

    /**
     * Executes the actual filtering by making an AJAX request to the server
     * This sends the filter criteria to the backend and updates the author list
     */
    executeFilters() {
        console.log('Executing filters:', this.filters);

        // Show loading notification if available
        if (typeof showNotification === 'function') {
            showNotification('info', 'Applying Filters', 'Please wait...');
        }

        // Prepare AJAX request parameters
        const xhr = new XMLHttpRequest();
        const params = new URLSearchParams();
        
        // Include current session if available (for session-specific filtering)
        const sessionParam = new URLSearchParams(window.location.search).get('session');
        if (sessionParam) {
            params.append('session', sessionParam);
        }
        
        // Add filter parameters to the request
        params.append('action', 'filter_authors');
        params.append('author_type', this.filters.author_type);
        params.append('presentation_type', this.filters.presentation_type);
        params.append('has_presentation', this.filters.has_presentation);

        xhr.open('GET', `?${params.toString()}`, true);
        
        // Handle successful response
        xhr.onload = function() {
            console.log('Filter response received:', {
                status: xhr.status,
                responseLength: xhr.responseText.length,
                responseStart: xhr.responseText.substring(0, 100)
            });
            
            if (xhr.status === 200) {
                try {
                    // Clean up the response to handle any server output issues
                    let cleanResponse = xhr.responseText.trim();
                    
                    // Check if server returned HTML error instead of JSON
                    if (cleanResponse.startsWith('<')) {
                        console.error('Server returned HTML instead of JSON:', cleanResponse.substring(0, 200));
                        throw new Error('Server returned HTML error instead of JSON');
                    }
                    
                    // Find the JSON part if there's any preceding output
                    const jsonStart = cleanResponse.indexOf('{');
                    const jsonEnd = cleanResponse.lastIndexOf('}');
                    
                    if (jsonStart !== -1 && jsonEnd !== -1 && jsonStart <= jsonEnd) {
                        cleanResponse = cleanResponse.substring(jsonStart, jsonEnd + 1);
                    }
                    
                    console.log('Cleaned response:', cleanResponse.substring(0, 200));
                    
                    // Parse the JSON response
                    const response = JSON.parse(cleanResponse);
                    console.log('Parsed response:', response);
                    
                    if (response.success) {
                        // Update the author cards with filtered results
                        const authorCards = document.querySelector('.author-cards');
                        if (authorCards && response.html) {
                            authorCards.innerHTML = response.html;
                            console.log('Updated author cards container with', response.html.length, 'characters');
                        } else {
                            console.error('Author cards container not found or no HTML in response');
                        }
                        
                        // Update the active filters display
                        updateActiveFiltersDisplay(filterWizard.filters);
                        
                        // Update filtered author IDs for bulk operations
                        if (typeof updateFilteredAuthorIds === 'function') {
                            updateFilteredAuthorIds();
                        }
                        
                        // Show success notification
                        if (typeof showNotification === 'function') {
                            showNotification('success', 'Filters Applied', 
                                `Found ${response.count} author(s) matching your criteria`);
                        }
                    } else {
                        console.error('Filter error:', response.message);
                        if (typeof showNotification === 'function') {
                            showNotification('error', 'Filter Error', 
                                response.message || 'Failed to apply filters');
                        }
                    }
                } catch (e) {
                    console.error('Failed to parse filter response:', e);
                    console.error('Raw response:', xhr.responseText);
                    if (typeof showNotification === 'function') {
                        showNotification('error', 'Parse Error', 
                            'Server response could not be processed. Check console for details.');
                    }
                }
            } else {
                console.error('Filter request failed:', xhr.status);
                if (typeof showNotification === 'function') {
                    showNotification('error', 'Request Error', 
                        'Failed to apply filters. Please try again.');
                }
            }
        };
        
        // Handle network errors
        xhr.onerror = function() {
            console.error('Filter request error');
            if (typeof showNotification === 'function') {
                showNotification('error', 'Network Error', 
                    'Network error occurred. Please try again.');
            }
        };
        
        // Handle timeouts
        xhr.ontimeout = function() {
            console.error('Filter request timeout');
            if (typeof showNotification === 'function') {
                showNotification('error', 'Timeout Error', 
                    'Request timed out. Please try again.');
            }
        };
        
        // Set 30 second timeout for the request
        xhr.timeout = 30000;
        
        // Send the request
        xhr.send();
    }

    /**
     * Resets the wizard to initial state
     * Used internally when filters need to be reset
     */
    reset() {
        console.log('Resetting filter wizard');
        this.currentStep = 1;
        this.filters = {
            author_type: 'all',
            presentation_type: 'all',
            has_presentation: 'all'
        };
        
        // Reset all radio button selections to "all"
        const authorTypeRadios = document.querySelectorAll('input[name="author_type"]');
        const presentationTypeRadios = document.querySelectorAll('input[name="presentation_type"]');
        const uploadStatusRadios = document.querySelectorAll('input[name="has_presentation"]');
        
        authorTypeRadios.forEach(radio => radio.checked = radio.value === 'all');
        presentationTypeRadios.forEach(radio => radio.checked = radio.value === 'all');
        uploadStatusRadios.forEach(radio => radio.checked = radio.value === 'all');
        
        this.updateStepDisplay();
        
        // Update the active filters display
        updateActiveFiltersDisplay(this.filters);
    }
}

// Initialize filter wizard when DOM is loaded
let filterWizard;
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing filter wizard');
    filterWizard = new FilterWizard();
});

/**
 * Updates the active filters display on the main page
 * Shows filter badges and count indicator on the "Set Filters" button
 * 
 * @param {Object} filters - The current filter state
 */
function updateActiveFiltersDisplay(filters) {
    console.log('Updating active filters display:', filters);
    
    // Find the filter indicator on the "Set Filters" button
    const filterButton = document.querySelector('.set-filters-btn');
    const filterIndicator = filterButton ? filterButton.querySelector('.filter-indicator') : null;
    
    // Find the active filters container and list
    const container = document.getElementById('activeFiltersContainer');
    const list = document.getElementById('activeFiltersList');
    
    if (!container || !list) {
        console.warn('Active filters display elements not found');
        return;
    }
    
    // Clear existing filter tags
    list.innerHTML = '';
    
    // Count active filters and create labels
    let activeCount = 0;
    const filterLabels = {
        author_type: {
            'first_author': 'First Authors',
            'presenting_author': 'Presenting Authors', 
            'co_author': 'Co-Authors'
        },
        presentation_type: {
            'oral': 'Oral Presentations',
            'poster': 'Poster Sessions'
        },
        has_presentation: {
            'with': 'With Upload',
            'without': 'Without Upload'
        }
    };
    
    // Create filter tag elements for active filters
    Object.keys(filters).forEach(key => {
        const value = filters[key];
        if (value && value !== 'all') {
            activeCount++;
            const label = filterLabels[key] && filterLabels[key][value] 
                ? filterLabels[key][value] 
                : value;
            
            const tag = document.createElement('span');
            tag.className = 'filter-tag';
            tag.textContent = label;
            list.appendChild(tag);
        }
    });
    
    // Update the filter count indicator on the button
    if (filterIndicator) {
        if (activeCount > 0) {
            filterIndicator.textContent = activeCount;
            filterIndicator.style.display = 'inline-block';
        } else {
            filterIndicator.style.display = 'none';
            filterIndicator.textContent = '0';
        }
    }
    
    // Show/hide the active filters container
    if (activeCount > 0) {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FilterWizard, updateActiveFiltersDisplay };
}