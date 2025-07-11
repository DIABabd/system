<?php
/**
 * User Helper Functions - Updated with Corrected Session-Specific Filtering
 * 
 * Functions for retrieving user information and session-specific authors
 */

// Permission system constants
const FUNCTION_ID_MSO = 5;
const FUNCTION_ID_DO = 4;
const ORGANIZER_ACCESS_LEVELS = [FUNCTION_ID_MSO, FUNCTION_ID_DO];

/**
 * Get user's full name
 * 
 * @param int $userId The user ID
 * @return string The user's full name
 */
function getUserName($userId)
{
    $query = "SELECT CONCAT(first, ' ', last) as name FROM user WHERE id = $userId";
    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['name'];
    }
    return "";
}

/**
 * Get user sessions with permission
 * 
 * @param int $userId The user ID
 * @return array List of session IDs
 */
function getUserSessions($userId)
{
    // Query to fetch sessions user has permission to access
    // Both MSO (5) and DO (4) get identical access
    $query = "SELECT DISTINCT s.id AS session_id, CONCAT(sym.short, s.session) AS session_name, 
                             sym.id AS symposium_id, sym.short AS symposium_short, sym.title AS symposium_title
                             FROM permissions p
                             JOIN session s ON p.oid = s.id
                             JOIN symposium sym ON s.symposium = sym.id
                             WHERE p.uid = $userId AND p.functionid IN (" . implode(',', ORGANIZER_ACCESS_LEVELS) . ")
                             ORDER BY sym.short, s.session";

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);
    $sessionIds = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $sessionIds[] = $row['session_id'];
        }
    }

    return $sessionIds;
}

/**
 * Get authors for specified sessions (legacy function - keep for backward compatibility)
 * 
 * @param array $sessionIds Array of session IDs
 * @return array Author data
 */
function getSessionAuthors($sessionIds)
{
    if (empty($sessionIds)) {
        return [];
    }

    $sessionIdsString = implode(',', array_map('intval', $sessionIds));
    $authors = [];

    // Query to fetch authors of papers in permitted sessions
    $query = "SELECT DISTINCT p.user FROM paper p, con_papersession conps 
              WHERE conps.paper = p.id AND conps.session IN ({$sessionIdsString})";
    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $authorId = $row['user'];
            $authorQuery = "SELECT id, first, last, mail FROM `user` WHERE id = $authorId";
            $authorResult = mysqli_query($GLOBALS["___mysqli_ston"], $authorQuery);

            if ($authorResult && mysqli_num_rows($authorResult) > 0) {
                $authorData = mysqli_fetch_assoc($authorResult);
                $authors[] = [
                    'id' => $authorData['id'],
                    'name' => $authorData['first'] . ' ' . $authorData['last'],
                    'email' => $authorData['mail']
                ];
            }
        }
    }

    return $authors;
}

/**
 * Get all authors for a specific session with detailed information - CORRECTED VERSION
 * 
 * @param int $sessionId The session ID
 * @param array $filters Optional filters array
 * @return array Detailed author data with type and paper information
 */
function getSessionAuthorsDetailed($sessionId, $filters = [])
{
    $sessionId = intval($sessionId);
    $authors = [];

    // Base condition - only accepted papers unless specified
    $statusCondition = "AND p.statusAbstract = 'ACCEPTED'";
    if (isset($filters['include_withdrawn']) && $filters['include_withdrawn']) {
        $statusCondition = "AND p.statusAbstract IN ('ACCEPTED', 'WITHDRAWN')";
    }

    // Main query with proper joins and filtering - OPTIMIZED VERSION
    $query = "
        SELECT 
            u.id,
            CONCAT(u.first, ' ', u.last) AS name,
            u.mail AS email,
            author_type,
            p.id AS paper_id,
            p.title AS paper_title,
            CASE
                WHEN cps.duration <= 3 THEN 'poster'
                ELSE 'oral'
            END AS presentation_type,
            CASE
                WHEN p.ulpresentation > 0 THEN 1
                ELSE 0
            END AS has_presentation
        FROM (
            -- First authors
            SELECT p.id AS paper_id, p.user AS user_id, 'first_author' AS author_type
            FROM paper p
            JOIN con_papersession cps ON cps.paper = p.id
            WHERE cps.session = $sessionId $statusCondition
            
            UNION
            
            -- Presenting authors (when different from first author)
            SELECT p.id AS paper_id, p.presenting_author AS user_id, 'presenting_author' AS author_type
            FROM paper p
            JOIN con_papersession cps ON cps.paper = p.id
            WHERE cps.session = $sessionId $statusCondition
              AND p.presenting_author IS NOT NULL
              AND p.presenting_author != p.user
            
            UNION
            
            -- Co-authors
            SELECT cp.pid AS paper_id, cp.uid AS user_id, 'co_author' AS author_type
            FROM con_papercoauthors cp
            JOIN con_papersession cps ON cps.paper = cp.pid
            JOIN paper p ON p.id = cp.pid
            WHERE cps.session = $sessionId $statusCondition
        ) AS author_roles
        JOIN user u ON u.id = author_roles.user_id
        JOIN paper p ON p.id = author_roles.paper_id
        JOIN con_papersession cps ON cps.paper = p.id AND cps.session = $sessionId
        ORDER BY name";

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $authorId = $row['id'];

            if (!isset($authors[$authorId])) {
                $authors[$authorId] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'author_types' => [],
                    'papers' => [],
                    'presentation_types' => [],
                    'has_presentation' => false
                ];
            }

            // Add author type if not present
            if (!in_array($row['author_type'], $authors[$authorId]['author_types'])) {
                $authors[$authorId]['author_types'][] = $row['author_type'];
            }

            // Add paper info
            $paperKey = $row['paper_id'];
            if (!isset($authors[$authorId]['papers'][$paperKey])) {
                $authors[$authorId]['papers'][$paperKey] = [
                    'id' => $row['paper_id'],
                    'title' => $row['paper_title'],
                    'presentation_type' => $row['presentation_type']
                ];

                // Add presentation type if new
                if (!in_array($row['presentation_type'], $authors[$authorId]['presentation_types'])) {
                    $authors[$authorId]['presentation_types'][] = $row['presentation_type'];
                }
            }

            // Update upload status
            if ($row['has_presentation']) {
                $authors[$authorId]['has_presentation'] = true;
            }
        }
    }

    // Apply filters - CORRECTED FILTER LOGIC
    return array_filter($authors, function ($author) use ($filters) {
        // Author type filter
        if (isset($filters['author_type']) && $filters['author_type'] !== 'all') {
            if (!in_array($filters['author_type'], $author['author_types'])) {
                return false;
            }
        }

        // Presentation type filter - FIXED: Check per paper instead of author level
        if (isset($filters['presentation_type']) && $filters['presentation_type'] !== 'all') {
            $hasMatchingPresentation = false;
            foreach ($author['papers'] as $paper) {
                if ($paper['presentation_type'] === $filters['presentation_type']) {
                    $hasMatchingPresentation = true;
                    break;
                }
            }
            if (!$hasMatchingPresentation)
                return false;
        }

        // Upload status filter
        if (isset($filters['has_presentation'])) {
            if ($filters['has_presentation'] === 'with' && !$author['has_presentation']) {
                return false;
            }
            if ($filters['has_presentation'] === 'without' && $author['has_presentation']) {
                return false;
            }
        }

        return true;
    });
}

/**
 * Get session information by ID
 * 
 * @param int $sessionId The session ID
 * @return array|null Session information
 */
function getSessionInfo($sessionId)
{
    $sessionId = intval($sessionId);

    $query = "SELECT s.id, s.session, s.name as session_name, 
                     CONCAT(sym.short, s.session) as full_session_name,
                     sym.id as symposium_id, sym.short as symposium_short, sym.title as symposium_title
              FROM session s
              JOIN symposium sym ON s.symposium = sym.id
              WHERE s.id = $sessionId";

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }

    return null;
}

/**
 * Check if user has access to a specific session - UPDATED FOR EQUAL MSO/DO ACCESS
 * 
 * @param int $userId The user ID
 * @param int $sessionId The session ID
 * @return bool True if user has access
 */
function userHasSessionAccess($userId, $sessionId)
{
    $userId = intval($userId);
    $sessionId = intval($sessionId);

    $query = "SELECT COUNT(*) as count 
              FROM permissions 
              WHERE uid = $userId 
                AND oid = $sessionId 
                AND functionid IN (" . implode(',', ORGANIZER_ACCESS_LEVELS) . ")";

    $result = mysqli_query($GLOBALS["___mysqli_ston"], $query);

    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }

    return false;
}

/**
 * Apply filters to authors array (legacy function - kept for compatibility)
 * 
 * @param array $authors Array of authors
 * @param array $filters Filters to apply
 * @return array Filtered authors
 */
function applyAuthorFilters($authors, $filters)
{
    $filteredAuthors = [];

    foreach ($authors as $author) {
        $includeAuthor = true;

        // Author type filter
        if (isset($filters['author_type']) && $filters['author_type'] !== 'all') {
            if (!in_array($filters['author_type'], $author['author_types'])) {
                $includeAuthor = false;
            }
        }

        // Presentation type filter
        if (isset($filters['presentation_type']) && $filters['presentation_type'] !== 'all') {
            if (!in_array($filters['presentation_type'], $author['presentation_types'])) {
                $includeAuthor = false;
            }
        }

        // Upload status filter
        if (isset($filters['has_presentation'])) {
            if ($filters['has_presentation'] === 'with' && !$author['has_presentation']) {
                $includeAuthor = false;
            } elseif ($filters['has_presentation'] === 'without' && $author['has_presentation']) {
                $includeAuthor = false;
            }
        }

        if ($includeAuthor) {
            $filteredAuthors[] = $author;
        }
    }

    return $filteredAuthors;
}
?>