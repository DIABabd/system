/**
 * COSPAR Mail System - Filter Wizard (Fixed Clear Filters Bug)
 * 
 * Fixed issue where filter indicator count wasn't resetting to 0 after clearing all filters
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
        this.setupClearFiltersButton();
    }

    createModal() {
        // Remove existing modal if present
        const existingModal = document.getElementById('filterWizardModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal HTML
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

        // Insert modal into body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    setupClearFiltersButton() {
        // Set up event listener for clear filters button
        // This will be called when the DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            const clearFiltersBtn = document.querySelector('.clear-filters-btn');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', () => {
                    this.clearAllFilters();
                });
            }
        });
    }

    open() {
        console.log('Opening filter wizard');
        this.isOpen = true;
        document.getElementById('filterWizardModal').style.display = 'flex';
        this.currentStep = 1;
        this.updateStepDisplay();
    }

    close() {
        console.log('Closing filter wizard');
        this.isOpen = false;
        document.getElementById('filterWizardModal').style.display = 'none';
    }

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

    previousStep() {
        console.log('Moving to previous step from:', this.currentStep);
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    }

    updateStepDisplay() {
        console.log('Updating step display to:', this.currentStep);
        
        // Hide all steps
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

        // Update progress bar
        const progress = (this.currentStep / this.totalSteps) * 100;
        const progressBar = document.getElementById('wizardProgress');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }

        // Update step number
        const stepNum = document.getElementById('currentStepNum');
        if (stepNum) {
            stepNum.textContent = this.currentStep;
        }

        // Update button visibility
        const prevBtn = document.getElementById('wizardPrevious');
        const nextBtn = document.getElementById('wizardNext');
        const applyBtn = document.getElementById('wizardApply');

        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'inline-block' : 'none';
        }

        if (this.currentStep === this.totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (applyBtn) applyBtn.style.display = 'inline-block';
        } else {
            if (nextBtn) nextBtn.style.display = 'inline-block';
            if (applyBtn) applyBtn.style.display = 'none';
        }
    }

    updateSummary() {
        console.log('Updating summary');
        
        // Get selected values
        const authorType = document.querySelector('input[name="author_type"]:checked')?.value || 'all';
        const presentationType = document.querySelector('input[name="presentation_type"]:checked')?.value || 'all';
        const hasPresentation = document.querySelector('input[name="has_presentation"]:checked')?.value || 'all';

        console.log('Selected filters:', { authorType, presentationType, hasPresentation });

        // Update summary display
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

        const summaryAuthorType = document.getElementById('summaryAuthorType');
        const summaryPresentationType = document.getElementById('summaryPresentationType');
        const summaryUploadStatus = document.getElementById('summaryUploadStatus');

        if (summaryAuthorType) summaryAuthorType.textContent = summaryMap.author_type[authorType];
        if (summaryPresentationType) summaryPresentationType.textContent = summaryMap.presentation_type[presentationType];
        if (summaryUploadStatus) summaryUploadStatus.textContent = summaryMap.has_presentation[hasPresentation];
    }

    applyFilters() {
        console.log('Applying filters');
        
        // Collect all filter values
        this.filters = {
            author_type: document.querySelector('input[name="author_type"]:checked')?.value || 'all',
            presentation_type: document.querySelector('input[name="presentation_type"]:checked')?.value || 'all',
            has_presentation: document.querySelector('input[name="has_presentation"]:checked')?.value || 'all'
        };

        console.log('Filter values collected:', this.filters);

        // Close wizard
        this.close();

        // Apply filters to the author list
        this.executeFilters();
    }

    executeFilters() {
        console.log('Executing filters:', this.filters);

        // Show loading indicator if showNotification is available
        if (typeof showNotification === 'function') {
            showNotification('info', 'Applying Filters', 'Please wait...');
        }

        // Make AJAX request to reload authors with filters
        const xhr = new XMLHttpRequest();
        const params = new URLSearchParams();
        
        // Add current session if available
        const sessionParam = new URLSearchParams(window.location.search).get('session');
        if (sessionParam) {
            params.append('session', sessionParam);
        }
        
        // Add filter parameters
        params.append('action', 'filter_authors');
        params.append('author_type', this.filters.author_type);
        params.append('presentation_type', this.filters.presentation_type);
        params.append('has_presentation', this.filters.has_presentation);

        xhr.open('GET', `?${params.toString()}`, true);
        
        xhr.onload = function() {
            console.log('Filter response received:', {
                status: xhr.status,
                responseLength: xhr.responseText.length,
                responseStart: xhr.responseText.substring(0, 100)
            });
            
            if (xhr.status === 200) {
                try {
                    // Clean response text to remove any potential BOM or whitespace
                    let cleanResponse = xhr.responseText.trim();
                    
                    // Check if response starts with HTML error (common PHP error)
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
                    
                    const response = JSON.parse(cleanResponse);
                    console.log('Parsed response:', response);
                    
                    if (response.success) {
                        // Update the author cards container
                        const authorCards = document.querySelector('.author-cards');
                        if (authorCards && response.html) {
                            authorCards.innerHTML = response.html;
                            console.log('Updated author cards container with', response.html.length, 'characters');
                        } else {
                            console.error('Author cards container not found or no HTML in response');
                        }
                        
                        // Update the active filters display
                        updateActiveFiltersDisplay(filterWizard.filters);
                        
                        // Update filtered IDs
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
        
        xhr.onerror = function() {
            console.error('Filter request error');
            if (typeof showNotification === 'function') {
                showNotification('error', 'Network Error', 
                    'Network error occurred. Please try again.');
            }
        };
        
        xhr.ontimeout = function() {
            console.error('Filter request timeout');
            if (typeof showNotification === 'function') {
                showNotification('error', 'Timeout Error', 
                    'Request timed out. Please try again.');
            }
        };
        
        // Set timeout to 30 seconds
        xhr.timeout = 30000;
        
        xhr.send();
    }

    // FIXED: Reset method that properly updates the UI
    reset() {
        console.log('Resetting filter wizard');
        this.currentStep = 1;
        this.filters = {
            author_type: 'all',
            presentation_type: 'all',
            has_presentation: 'all'
        };
        
        // Reset form selections
        const authorTypeRadios = document.querySelectorAll('input[name="author_type"]');
        const presentationTypeRadios = document.querySelectorAll('input[name="presentation_type"]');
        const uploadStatusRadios = document.querySelectorAll('input[name="has_presentation"]');
        
        authorTypeRadios.forEach(radio => radio.checked = radio.value === 'all');
        presentationTypeRadios.forEach(radio => radio.checked = radio.value === 'all');
        uploadStatusRadios.forEach(radio => radio.checked = radio.value === 'all');
        
        this.updateStepDisplay();
        
        // FIXED: Update the active filters display after reset
        updateActiveFiltersDisplay(this.filters);
    }

    // NEW: Method to handle clearing all filters via the clear button
    clearAllFilters() {
        console.log('Clearing all filters via clear button');
        
        // Reset the wizard state
        this.reset();
        
        // Execute the filters to reload the page with no filters
        this.executeFilters();
        
        // Show success notification
        if (typeof showNotification === 'function') {
            showNotification('success', 'Filters Cleared', 'All filters have been removed');
        }
    }
}

// Initialize filter wizard when DOM is loaded
let filterWizard;
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing filter wizard');
    filterWizard = new FilterWizard();
});

/**
 * FIXED: Update the active filters display to target correct elements
 */
function updateActiveFiltersDisplay(filters) {
    console.log('Updating active filters display:', filters);
    
    // Look for the correct filter indicator on the "Set Filters" button
    const filterButton = document.querySelector('.set-filters-btn');
    const filterIndicator = filterButton ? filterButton.querySelector('.filter-indicator') : null;
    
    // Also look for the active filters container
    const container = document.getElementById('activeFiltersContainer');
    const list = document.getElementById('activeFiltersList');
    
    if (!container || !list) {
        console.warn('Active filters display elements not found');
        return;
    }
    
    // Clear existing tags
    list.innerHTML = '';
    
    // Count active filters
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
    
    // Add filter tags
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
    
    // FIXED: Update the filter indicator on the "Set Filters" button
    if (filterIndicator) {
        if (activeCount > 0) {
            filterIndicator.textContent = activeCount;
            filterIndicator.style.display = 'inline-block';
        } else {
            // FIXED: Properly hide the indicator when no filters are active
            filterIndicator.style.display = 'none';
            filterIndicator.textContent = '0';
        }
    }
    
    // Show/hide active filters container
    if (activeCount > 0) {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

/**
 * FIXED: Global function to clear all filters - properly resets the count
 */
function clearAllFilters() {
    console.log('Global clearAllFilters called');
    if (typeof filterWizard !== 'undefined' && filterWizard) {
        filterWizard.clearAllFilters();
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FilterWizard, updateActiveFiltersDisplay, clearAllFilters };
}