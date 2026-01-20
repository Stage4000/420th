<?php
// HTML sanitization helper for whitelist agreement
// Allows only safe HTML tags for formatting

class HtmlSanitizer {
    // Allowed tags for the whitelist agreement
    private static $allowedTags = [
        'p', 'strong', 'em', 'b', 'i', 'u', 
        'ul', 'ol', 'li', 'br',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
    ];
    
    /**
     * Sanitize HTML content to allow only safe formatting tags
     * @param string $html The HTML content to sanitize
     * @return string Sanitized HTML
     */
    public static function sanitize($html) {
        if (empty($html)) {
            return '';
        }
        
        // Build allowed tags string for strip_tags
        $allowedTagsStr = '<' . implode('><', self::$allowedTags) . '>';
        
        // Strip all tags except allowed ones
        $sanitized = strip_tags($html, $allowedTagsStr);
        
        // Additional security: remove any javascript: or data: protocols
        $sanitized = preg_replace('/javascript:/i', '', $sanitized);
        $sanitized = preg_replace('/data:/i', '', $sanitized);
        $sanitized = preg_replace('/on\w+\s*=/i', '', $sanitized); // Remove event handlers
        
        return $sanitized;
    }
    
    /**
     * Get list of allowed tags for display
     * @return string Comma-separated list of allowed tags
     */
    public static function getAllowedTagsList() {
        return implode(', ', array_map(function($tag) {
            return '&lt;' . $tag . '&gt;';
        }, self::$allowedTags));
    }
}
