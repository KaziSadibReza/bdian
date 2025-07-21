<?php
/**
 * Smart Login Registration - Error Handler
 * 
 * Simple error handler to override WordPress default login errors
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_Error_Handler {
    
    public function __construct() {
        $this->add_hooks();
    }
    
    /**
     * Add error handling hooks - SUPER SIMPLE APPROACH
     */
    private function add_hooks() {
        // Single filter to override ALL WordPress login errors
        add_filter('login_errors', array($this, 'no_wordpress_errors'), PHP_INT_MAX);
        
        // Debug: Log when hooks are added
        error_log('SLR_Error_Handler: Hooks added - login_errors filter registered with PHP_INT_MAX priority');
    }
    
    /**
     * Custom login errors - Based on user's example
     * Handles specific error codes with custom messages
     */
    public function no_wordpress_errors($error) {
        global $errors;
        
        // Debug: Log when this method is called
        error_log('SLR_Error_Handler: no_wordpress_errors called with: ' . print_r($error, true));
        
        // Check if we have error content to work with
        if (empty($error)) {
            error_log('SLR_Error_Handler: Empty error, returning default message');
            return 'Login failed. Please check your credentials and try again.';
        }
        
        // Handle different error types by checking error content
        $error_text = is_string($error) ? $error : '';
        error_log('SLR_Error_Handler: Processing error text: ' . $error_text);
        
        // Check for "username is not registered" pattern
        if (strpos($error_text, 'is not registered') !== false) {
            error_log('SLR_Error_Handler: Found "is not registered" pattern');
            // Check if it's a phone number (like 044895415610)
            if (preg_match('/\b(\d{7,15})\b/', $error_text)) {
                error_log('SLR_Error_Handler: Detected phone number, returning phone-specific message');
                return '<strong>ERROR</strong>: Phone number not found. Please check your phone number.';
            } else {
                error_log('SLR_Error_Handler: Not a phone number, returning invalid username message');
                return '<strong>ERROR</strong>: Invalid username.';
            }
        }
        
        // Check for incorrect password pattern
        if (strpos($error_text, 'password you entered') !== false || strpos($error_text, 'incorrect') !== false) {
            error_log('SLR_Error_Handler: Found password error pattern');
            return '<strong>ERROR</strong>: The password you entered is incorrect.';
        }
        
        // Check for empty fields
        if (strpos($error_text, 'empty') !== false) {
            if (strpos($error_text, 'username') !== false) {
                error_log('SLR_Error_Handler: Empty username detected');
                return '<strong>ERROR</strong>: Please enter your username.';
            }
            if (strpos($error_text, 'password') !== false) {
                error_log('SLR_Error_Handler: Empty password detected');
                return '<strong>ERROR</strong>: Please enter your password.';
            }
        }
        
        // Fallback: If we can't identify the specific error, try using error codes
        if (is_wp_error($errors)) {
            $err_codes = $errors->get_error_codes();
            error_log('SLR_Error_Handler: Using error codes fallback: ' . print_r($err_codes, true));
            
            if (in_array('invalid_username', $err_codes)) {
                return '<strong>ERROR</strong>: Invalid username.';
            }
            if (in_array('incorrect_password', $err_codes)) {
                return '<strong>ERROR</strong>: The password you entered is incorrect.';
            }
            if (in_array('invalid_email', $err_codes)) {
                return '<strong>ERROR</strong>: This email is not registered.';
            }
            if (in_array('empty_username', $err_codes)) {
                return '<strong>ERROR</strong>: Please enter your username.';
            }
            if (in_array('empty_password', $err_codes)) {
                return '<strong>ERROR</strong>: Please enter your password.';
            }
        }
        
        // Final fallback - return generic message
        error_log('SLR_Error_Handler: Using final fallback message');
        return '<strong>ERROR</strong>: Login failed. Please check your credentials and try again.';
    }
}