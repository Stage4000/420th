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
        
        // Additional security: remove any dangerous content
        // Remove javascript: protocol (including variations with whitespace/encoding)
        $sanitized = preg_replace('/\bjavascript\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\bdata\s*:/i', '', $sanitized);
        $sanitized = preg_replace('/\bvbscript\s*:/i', '', $sanitized);
        
        // Remove event handlers (on* attributes) more thoroughly
        $sanitized = preg_replace('/\s*on[a-z]+\s*=/i', ' ', $sanitized);
        
        // Remove any remaining script-like content
        $sanitized = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $sanitized);
        $sanitized = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $sanitized);
        $sanitized = preg_replace('/<object[^>]*>.*?<\/object>/is', '', $sanitized);
        $sanitized = preg_replace('/<embed[^>]*>/i', '', $sanitized);
        
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
