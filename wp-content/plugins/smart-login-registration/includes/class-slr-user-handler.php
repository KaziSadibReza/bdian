<?php
/**
 * Smart Login and Registration - User Handler
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_User_Handler {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('slr_settings', array());
    }
    
    /**
     * Check if string is a phone number
     */
    public function is_phone_number($string) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $string);
        
        // Check if it's a valid phone number format
        // Adjust this pattern based on your country's phone number format
        return preg_match('/^[0-9]{10,15}$/', $cleaned) || 
               preg_match('/^\+[0-9]{10,15}$/', $string) ||
               preg_match('/^01[0-9]{9}$/', $cleaned); // Bangladesh format
    }
    
    /**
     * Validate phone number format
     */
    public function is_valid_phone($phone) {
        if (!isset($this->settings['phone_validation']) || !$this->settings['phone_validation']) {
            return true; // Skip validation if disabled
        }
        
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Check various phone formats
        return preg_match('/^[0-9]{10,15}$/', $cleaned) || 
               preg_match('/^\+[0-9]{10,15}$/', $phone) ||
               preg_match('/^01[0-9]{9}$/', $cleaned); // Bangladesh format
    }
    
    /**
     * Get user by phone number
     */
    public function get_user_by_phone($phone) {
        $users = get_users(array(
            'meta_key' => 'phone',
            'meta_value' => $phone,
            'number' => 1
        ));
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // Also check billing_phone for WooCommerce compatibility
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'number' => 1
        ));
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // Check user_login field for phone numbers
        $users = get_users(array(
            'search' => $phone,
            'search_columns' => array('user_login'),
            'number' => 1
        ));
        
        return !empty($users) ? $users[0] : null;
    }
    
    /**
     * Check if phone number already exists
     */
    public function phone_exists($phone) {
        $user = $this->get_user_by_phone($phone);
        return $user !== null;
    }
    
    /**
     * Validate email format
     */
    public function is_valid_email($email) {
        return is_email($email);
    }
    
    /**
     * Create user with phone number
     */
    public function create_user($user_data) {
        // Create user
        $user_id = wp_create_user($user_data['email'], $user_data['password'], $user_data['email']);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $user_data['name'],
            'first_name' => $user_data['name']
        ));
        
        // Store phone number
        if (!empty($user_data['phone'])) {
            update_user_meta($user_id, 'phone', $user_data['phone']);
        }
        
        return $user_id;
    }
    
    /**
     * Sanitize user input
     */
    public function sanitize_user_data($data) {
        $sanitized = array();
        
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }
        
        if (isset($data['phone'])) {
            $sanitized['phone'] = sanitize_text_field($data['phone']);
        }
        
        if (isset($data['password'])) {
            $sanitized['password'] = $data['password']; // Don't sanitize password
        }
        
        return $sanitized;
    }
    
    /**
     * Format phone number for display
     */
    public function format_phone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Format Bangladesh phone numbers
        if (preg_match('/^01[0-9]{9}$/', $cleaned)) {
            return '+880' . substr($cleaned, 1);
        }
        
        return $phone;
    }
}
