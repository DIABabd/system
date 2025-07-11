<?php
/**
 * UNIFIED Author Card Generation Functions
 * 
 * This file contains the single, consistent way to generate author cards
 * throughout the entire COSPAR system. Use ONLY these functions.
 */

/**
 * Generate HTML for a single author card with FULL details
 * This is the ONLY way to display author cards in the system
 * 
 * @param array $author Author data with all details
 * @return string HTML for the author card
 */
function generateAuthorCardHTML($author)
{
    $authorId = htmlspecialchars($author['id']);
    $authorName = htmlspecialchars($author['name']);
    $authorEmail = htmlspecialchars($author['email']);

    // Process author types
    $authorTypesStr = 'Author';
    if (isset($author['author_types']) && is_array($author['author_types'])) {
        $authorTypesStr = implode(', ', array_map(function ($type) {
            return ucfirst(str_replace('_', ' ', $type));
        }, $author['author_types']));
    }

    // Process presentation types  
    $presentationTypesStr = 'Unknown';
    if (isset($author['presentation_types']) && is_array($author['presentation_types'])) {
        $presentationTypesStr = implode(', ', array_map('ucfirst', $author['presentation_types']));
    }

    // Process upload status
    $uploadStatus = 'Not uploaded';
    if (isset($author['has_presentation']) && $author['has_presentation']) {
        $uploadStatus = 'Uploaded';
    }

    $html = '<div class="card" data-name="' . $authorName . '" 
                  data-email="' . $authorEmail . '" 
                  data-author-id="' . $authorId . '">';
    $html .= '<div>';
    $html .= '<h2>' . $authorName . '</h2>';
    $html .= '<p><i class="fas fa-envelope"></i> ' . $authorEmail . '</p>';
    $html .= '<p class="author-meta">';
    $html .= '<small><strong>Type:</strong> ' . htmlspecialchars($authorTypesStr) . '</small><br>';
    $html .= '<small><strong>Presentations:</strong> ' . htmlspecialchars($presentationTypesStr) . '</small><br>';
    $html .= '<small><strong>Upload Status:</strong> ' . htmlspecialchars($uploadStatus) . '</small>';
    $html .= '</p>';
    $html .= '</div>';
    $html .= '<button type="button" class="expand-button" 
                  onclick="displayRightSectionForAuthor(\'' . $authorId . '\', 
                            \'' . addslashes($authorName) . '\', 
                            \'' . addslashes($authorEmail) . '\')">';
    $html .= '<i class="fas fa-arrow-right"></i>';
    $html .= '</button>';
    $html .= '</div>';

    return $html;
}

/**
 * Generate HTML for the "Select All Authors" card
 * 
 * @param int $authorCount Number of authors
 * @return string HTML for the select all card
 */
function generateSelectAllAuthorsCardHTML($authorCount)
{
    $html = '<div class="card">';
    $html .= '<div>';
    $html .= '<h2>Select All Authors</h2>';
    $html .= '<p>Send emails to all ' . $authorCount . ' filtered author(s) via BCC</p>';
    $html .= '</div>';
    $html .= '<button type="button" class="expand-button" id="selectAllAuthors" onclick="displayRightSectionForAll()">';
    $html .= '<span class="expand-icon">';
    $html .= '<i class="fas fa-arrow-right"></i>';
    $html .= '</span>';
    $html .= '</button>';
    $html .= '</div>';

    return $html;
}

/**
 * Generate HTML for all author cards (including Select All card)
 * This replaces ALL instances of author card generation in the system
 * i think this is only there to make the select all card appear first in the list 
 * 
 * @param array $authors Array of author data
 * @return string Complete HTML for all author cards
 */
function generateAllAuthorCardsHTML($authors)
{
    if (empty($authors)) {
        return '<div class="empty-state-minimal">
            <div class="empty-state-icon">
                <i class="fas fa-filter"></i>
            </div>
            <h3 class="empty-state-title">No Results Found</h3>
            <p class="empty-state-message">
                No authors match your current filter criteria.
            </p>
         </div>';
    }

    $html = '';

    // Always include "Select All Authors" card first
    $html .= generateSelectAllAuthorsCardHTML(count($authors));

    // Add individual author cards using the unified format
    foreach ($authors as $author) {
        $html .= generateAuthorCardHTML($author);
    }

    return $html;
}

/**
 * Validate author data structure
 * Ensures author has all required fields for detailed display
 * 
 * @param array $author Author data
 * @return array Author data with defaults for missing fields
 */
function validateAuthorData($author)
{
    // Ensure required fields exist
    if (!isset($author['id'])) {
        throw new InvalidArgumentException('Author data missing required field: id');
    }
    if (!isset($author['name'])) {
        throw new InvalidArgumentException('Author data missing required field: name');
    }
    if (!isset($author['email'])) {
        throw new InvalidArgumentException('Author data missing required field: email');
    }

    // Set defaults for optional fields
    if (!isset($author['author_types'])) {
        $author['author_types'] = ['author'];
    }
    if (!isset($author['presentation_types'])) {
        $author['presentation_types'] = ['unknown'];
    }
    if (!isset($author['has_presentation'])) {
        $author['has_presentation'] = false;
    }

    return $author;
}
?>