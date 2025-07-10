<?php
/**
 * Tutor Login Popup - User Handler
 * 
 * @package Kadence Child
 * @author Kazi Sadib Reza
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorUserHandler {
    
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
        
        return !empty($users) ? $users[0] : null;
    }
    
    /**
     * Check if phone number already exists
     */
    public function phone_exists($phone) {
        $user = $this->get_user_by_phone($phone);
        return $user !== null;
    }
}
