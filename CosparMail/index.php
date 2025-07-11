<?php
/**
 * COSPAR Mail System - Main Controller (Updated)
 * 
 * Main entry point for the COSPAR email communication interface
 * Now with clean session-specific filtering and unified author card generation
 */

// Include necessary configuration files
require_once '../../config.inc.php';
require_once "../../include/functions.inc.php";

// Load CosparMail components
require_once "classes/EmailSender.php";
require_once "helpers/EmailHistory.php";
require_once "helpers/EmailDetails.php";
require_once "helpers/UserHelper.php";
require_once "helpers/AuthorCardHelper.php";  // ADDED: Unified author card functions

// Handle attachment downloads
if (isset($_GET['action']) && $_GET['action'] === 'download_attachment') {
    require_once "handlers/AttachmentDownloadHandler.php";
    $downloadHandler = new AttachmentDownloadHandler();
    $downloadHandler->processDownload();
    exit;
}

// Handle AJAX filter request - FIXED VERSION
if (isset($_GET['action']) && $_GET['action'] === 'filter_authors') {
    // Start output buffering to catch any unexpected output
    ob_start();

    // Set proper JSON headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');

    // Add error logging for debugging
    error_log("Filter authors request received");
    error_log("GET parameters: " . print_r($_GET, true));

    $userId = getUserID();
    $sessionId = isset($_GET['session']) ? intval($_GET['session']) : null;

    // Validate session access
    if ($sessionId && !userHasSessionAccess($userId, $sessionId)) {
        error_log("Session access denied for user $userId, session $sessionId");

        // Clear any output buffer
        ob_clean();

        echo json_encode([
            'success' => false,
            'message' => 'Access denied to this session'
        ]);
        exit;
    }

    // Build filters array
    $filters = [];
    if (isset($_GET['author_type']) && $_GET['author_type'] !== 'all') {
        $filters['author_type'] = $_GET['author_type'];
    }
    if (isset($_GET['presentation_type']) && $_GET['presentation_type'] !== 'all') {
        $filters['presentation_type'] = $_GET['presentation_type'];
    }
    if (isset($_GET['has_presentation']) && $_GET['has_presentation'] !== 'all') {
        $filters['has_presentation'] = $_GET['has_presentation'];
    }

    error_log("Filters applied: " . print_r($filters, true));

    try {
        if ($sessionId) {
            // Get session-specific authors
            error_log("Getting authors for session: $sessionId");
            $authors = getSessionAuthorsDetailed($sessionId, $filters);
        } else {
            // Fallback to all user sessions
            error_log("Getting authors for all user sessions");
            $userSessions = getUserSessions($userId);
            $authors = [];
            if (!empty($userSessions)) {
                foreach ($userSessions as $sid) {
                    $sessionAuthors = getSessionAuthorsDetailed($sid, $filters);
                    $authors = array_merge($authors, $sessionAuthors);
                }

                // Remove duplicates by author ID
                $uniqueAuthors = [];
                foreach ($authors as $author) {
                    $uniqueAuthors[$author['id']] = $author;
                }
                $authors = array_values($uniqueAuthors);
            }
        }

        error_log("Found " . count($authors) . " authors after filtering");

        // FIXED: Use unified author card generation function
        $html = generateAllAuthorCardsHTML($authors);

        // Build response
        $response = [
            'success' => true,
            'html' => $html,
            'count' => count($authors)
        ];

        // Clear any unexpected output
        ob_clean();

        error_log("Sending successful response with " . count($authors) . " authors");
        echo json_encode($response);

    } catch (Exception $e) {
        error_log("Filter authors error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        // Clear any output buffer
        ob_clean();

        echo json_encode([
            'success' => false,
            'message' => 'Filter error: ' . $e->getMessage()
        ]);
    }

    exit;
}

// Process other AJAX requests through the AjaxHandler
if (isset($_GET['action'])) {
    require_once "handlers/AjaxHandler.php";
    $ajaxHandler = new AjaxHandler();
    $ajaxHandler->processRequest();
    exit;
}

// Get current user's ID
$userId = getUserID();
$userName = getUserName($userId);

// Process form submission through the FormHandler
$formResponse = null;
if (isset($_POST['submit'])) {
    require_once "handlers/FormHandler.php";
    $formHandler = new FormHandler();
    $formResponse = $formHandler->processSubmission($_POST);

    // PREVENT FORM RESUBMISSION: Redirect after processing
    $alertType = $formResponse['success'] ? 'success' : 'error';
    $message = urlencode($formResponse['message']);

    // FIXED: Preserve session and filter parameters in redirect
    $redirectParams = ['alert' => $alertType, 'message' => $message];

    // Preserve session parameter
    if (isset($_GET['session'])) {
        $redirectParams['session'] = $_GET['session'];
    }

    // Preserve filter parameters if they exist
    $filterParams = ['author_type', 'presentation_type', 'has_presentation'];
    foreach ($filterParams as $param) {
        if (isset($_GET[$param]) && $_GET[$param] !== 'all') {
            $redirectParams[$param] = $_GET[$param];
        }
    }

    $redirectUrl = 'index.php?' . http_build_query($redirectParams);
    header("Location: $redirectUrl");
    exit;
}

// Handle session-specific view
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : null;
$sessionInfo = null;
$authors = [];

if ($sessionId) {
    // Validate session access
    if (!userHasSessionAccess($userId, $sessionId)) {
        // Redirect with error
        header("Location: index.php?alert=error&message=" . urlencode("Access denied to this session"));
        exit;
    }

    // Get session info and authors
    $sessionInfo = getSessionInfo($sessionId);
    $authors = getSessionAuthorsDetailed($sessionId);

} else {
    // FIXED: Fallback to all user sessions with DETAILED format (no more legacy compatibility)
    $userSessions = getUserSessions($userId);
    $authors = [];
    if (!empty($userSessions)) {
        foreach ($userSessions as $sid) {
            $sessionAuthors = getSessionAuthorsDetailed($sid); // Always use detailed
            $authors = array_merge($authors, $sessionAuthors);
        }

        // Remove duplicates by author ID
        $uniqueAuthors = [];
        foreach ($authors as $author) {
            $uniqueAuthors[$author['id']] = $author;
        }
        $authors = array_values($uniqueAuthors);
    }
}

// FIXED: Validate all author data to ensure consistent structure
if (!empty($authors)) {
    $authors = array_map('validateAuthorData', $authors);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSPAR
        Communications<?php echo $sessionInfo ? ' - ' . htmlspecialchars($sessionInfo['full_session_name']) : ''; ?>
    </title>

    <!-- External stylesheets -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/filter-wizard.css">

    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Include JavaScript files directly -->
    <script src="assets/js/author-management.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/email-ui.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/email-operations.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/popup-handlers.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/notifications.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/navigation.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/filter-wizard.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/filter-conversations.js?v=<?php echo time(); ?>"></script>

    <!-- Initialize when document is ready -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM fully loaded, initializing app');

            // Show notification if there's an alert message from redirect
            <?php if (isset($_GET['alert']) && isset($_GET['message'])): ?>
                const alertType = '<?php echo $_GET['alert']; ?>';
                const message = '<?php echo htmlspecialchars(urldecode($_GET['message']), ENT_QUOTES); ?>';
                const title = alertType === 'success' ? 'Success' : 'Error';

                if (typeof showNotification === 'function') {
                    showNotification(alertType, title, message);
                } else {
                    console.error('showNotification function not found!');
                    alert(title + ': ' + message);
                }
            <?php endif; ?>

            // Initialize app components
            if (typeof initializeApp === 'function') {
                initializeApp();
            } else {
                console.error('initializeApp function not found!');
            }
        });
    </script>

    <style>
        /* Additional author card styling for new metadata */
        .author-meta small {
            color: #666;
            font-size: 0.85em;
            line-height: 1.4;
        }

        .session-header {
            background: linear-gradient(135deg, #3c78d8, #4CAF50);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .session-header h1 {
            margin: 0 0 5px 0;
            font-size: 1.5em;
        }

        .session-header p {
            margin: 0;
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <!-- Notification Container -->
    <div id="notificationContainer" class="notification-container"></div>

    <!-- Header Section -->
    <header>
        <div class="header-container">
            <div class="header-left">
                <button id="backButton" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            </div>
            <div class="header-center">
                <a href="/" class="brand-link">COSPAR</a>
            </div>
            <div class="header-right">
                <!-- Reserved for future header elements -->
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main>
        <!-- Left Navigation Section -->
        <nav>
            <?php if ($sessionInfo): ?>
                <!-- Session-specific header -->
                <div class="session-header">
                    <h1><?php echo htmlspecialchars($sessionInfo['full_session_name']); ?></h1>
                    <p><?php echo htmlspecialchars($sessionInfo['session_name']); ?></p>
                    <p><?php echo htmlspecialchars($sessionInfo['symposium_title']); ?></p>
                </div>
            <?php endif; ?>

            <!-- Search Form: Prevents default form submission with onsubmit="return false" -->
            <form method="POST" onsubmit="return false;">
                <!-- Real-time search input that filters authors as you type -->
                <input type="search" placeholder="Search Authors..." class="search-bar" id="searchBar"
                    oninput="quickFilterAuthors()">
            </form>

            <br>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <div class="filter-header">
                    <label class="filter-label">Filter Authors:</label>
                    <button class="set-filters-btn" onclick="filterWizard.open()">
                        <i class="fas fa-filter"></i>
                        Set Filters
                        <span class="filter-indicator" style="display: none;">0</span>
                    </button>
                </div>

                <!-- Active Filters Display -->
                <div class="active-filters-container" id="activeFiltersContainer">
                    <div class="active-filters-header">
                        <span class="active-filters-title">Active Filters:</span>
                    </div>
                    <div class="active-filters-list" id="activeFiltersList">
                        <!-- Filter tags will be inserted here -->
                    </div>
                </div>
            </div>

            <!-- Author Cards Container -->
            <div class="UserCards" id="authorCards">
                <?php
                // FIXED: Display authors using unified generation function
                if ($sessionId && empty($authors)) {
                    echo "<div class='empty-state-minimal'>";
                    echo "<div class='empty-state-icon'>";
                    echo "<i class='fas fa-search'></i>";
                    echo "</div>";
                    echo "<h3 class='empty-state-title'>No Authors Found</h3>";
                    echo "<p class='empty-state-message'>";
                    echo "No authors found in this session or all papers may have been withdrawn.";
                    echo "</p>";
                    echo "</div>";
                } elseif (!$sessionId && empty($authors)) {
                    echo "<div class='empty-state-minimal'>";
                    echo "<div class='empty-state-icon'>";
                    echo "<i class='fas fa-exclamation-triangle'></i>";
                    echo "</div>";
                    echo "<h3 class='empty-state-title'>Access Denied</h3>";
                    echo "<p class='empty-state-message'>";
                    echo "You don't have permission to access any sessions.";
                    echo "</p>";
                    echo "</div>";
                } else {
                    echo "<div class='author-cards'>";

                    // FIXED: Use the unified author card generation function
                    echo generateAllAuthorCardsHTML($authors);

                    echo "</div>";
                }
                ?>
            </div>
        </nav>

        <!-- Vertical Line Separator -->
        <div class="vertical-line"></div>

        <!-- Right Section for Email Communication -->
        <div>
            <div class="rightSection" id="rightSection" style="display: none;">
                <!-- Close Button -->
                <button class="close-button" id="closeButton" onclick="hideRightSection()">×</button>

                <!-- Author information display -->
                <div id="authorInfo">
                    <h2>Communication with <span id="authorName"></span></h2>
                </div>

                <!-- Email Action Buttons Container -->
                <div class="email-actions">
                    <button type="button" class="new-mail" id="openMailPopup">
                        <i class="fas fa-paper-plane"></i>
                        New Mail
                    </button>

                    <button type="button" class="external-mail" id="openExternalMail">
                        <i class="fas fa-external-link-alt"></i>
                        Use Email Client
                    </button>
                </div>

                <!-- Email History Section -->
                <div class="email-history" id="emailHistory">
                    <div id="emailHistoryContent">
                        <p>Select an author to view communication history.</p>
                    </div>
                </div>
            </div>

            <!-- Mail Popup Modal -->
            <?php include "templates/compose-form.php"; ?>

            <!-- Email Details Popup Modal -->
            <div id="emailDetailsModal" class="popup">
                <div class="popup-content">
                    <span class="close-btn" id="closeDetailsBtn">&times;</span>
                    <div id="emailDetailsContent">
                        <p><i class="fas fa-spinner fa-spin"></i> Loading email details...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer Section -->
    <footer>
        <span>&copy; COSPAR 2024 - Developed by Abdullah Diab</span>
    </footer>

    <!-- Add quick filter function -->
    <script>
        function quickFilterAuthors() {
            const searchBar = document.getElementById('searchBar');
            if (!searchBar) return;

            const searchInput = searchBar.value.toLowerCase();
            const authorCards = document.querySelectorAll('.author-cards .card:not(:first-child)'); // Skip "Select All" card

            authorCards.forEach(card => {
                const authorName = card.getAttribute('data-name')?.toLowerCase() || '';
                const authorEmail = card.getAttribute('data-email')?.toLowerCase() || '';

                if (authorName.includes(searchInput) || authorEmail.includes(searchInput)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Clean initialization function (removed old filtering code)
        function initializeApp() {
            console.log("Initializing application with clean filtering system");

            // Set up external email button
            const openExternalMailBtn = document.getElementById('openExternalMail');
            if (openExternalMailBtn) {
                openExternalMailBtn.addEventListener('click', function () {
                    console.log("External Mail button clicked");
                    openExternalEmailClient();
                });
            }

            // Set up email popup
            setupEmailPopup();

            // Set up email details popup
            setupDetailsPopup();

            // Set up select all authors button
            const selectAllAuthorsBtn = document.getElementById('selectAllAuthors');
            if (selectAllAuthorsBtn) {
                selectAllAuthorsBtn.addEventListener('click', function () {
                    console.log("Select All Authors button clicked");
                    displayRightSectionForAll();
                });
            }

            // Set up close right section button
            const closeButton = document.getElementById('closeButton');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    hideRightSection();
                });
            }

            // Initialize active filters display (hidden by default)
            updateActiveFiltersDisplay({});
        }
    </script>

    <!-- Include main.js for remaining functionality -->
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>

</html>