<?php
/**
 * Smart Login and Registration - OTP Handler
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_OTP_Handler {
    
    private $table_name;
    private $settings;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'slr_otp';
        $this->settings = get_option('slr_settings', array());
    }
    
    /**
     * Create OTP table for storing verification codes
     */
    public function create_otp_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            otp varchar(6) NOT NULL,
            otp_type varchar(20) NOT NULL,
            temp_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            attempts tinyint(3) DEFAULT 0,
            PRIMARY KEY (id),
            KEY email (email),
            KEY expires_at (expires_at),
            KEY otp_type (otp_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Check if OTP table exists and create if needed
     */
    public function maybe_create_otp_table() {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) != $this->table_name) {
            $this->create_otp_table();
        }
    }
    
    /**
     * Generate a 6-digit OTP
     */
    public function generate_otp() {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Store OTP in database
     */
    public function store_otp($email, $otp, $type, $temp_data = null) {
        global $wpdb;
        
        $otp_expiry = isset($this->settings['otp_expiry']) ? (int) $this->settings['otp_expiry'] : 10;
        $expires_at = current_time('mysql', true); // Use GMT time
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_at . " +{$otp_expiry} minutes"));
        
        // Get existing temp_data if this is a resend and temp_data is not provided
        if (!$temp_data && $type === 'register') {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT temp_data FROM {$this->table_name} WHERE email = %s AND otp_type = %s ORDER BY created_at DESC LIMIT 1",
                $email, $type
            ));
            if ($existing && $existing->temp_data) {
                $temp_data = json_decode($existing->temp_data, true);
            }
        }
        
        // Clear any existing OTP for this email and type
        $wpdb->delete($this->table_name, array(
            'email' => $email,
            'otp_type' => $type
        ));
        
        // Insert new OTP
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'email' => $email,
                'otp' => $otp,
                'otp_type' => $type,
                'temp_data' => $temp_data ? json_encode($temp_data) : null,
                'expires_at' => $expires_at,
                'attempts' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: OTP stored - Email: $email, Type: $type, OTP: $otp, Expires: $expires_at");
        }
        
        return $result !== false;
    }
    
    /**
     * Verify OTP
     */
    public function verify_otp($email, $otp, $type) {
        global $wpdb;
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: OTP Verification - Email: $email, OTP: $otp, Type: $type");
        }
        
        // Get OTP record - use ORDER BY to get latest
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE email = %s AND otp_type = %s ORDER BY created_at DESC LIMIT 1",
            $email, $type
        ));
        
        if ($this->is_debug_enabled()) {
            if ($record) {
                error_log("SLR: OTP Record found - ID: {$record->id}, OTP: {$record->otp}, Expires: {$record->expires_at}");
            } else {
                error_log("SLR: No OTP record found for email: $email, type: $type");
            }
        }
        
        if (!$record) {
            return array('success' => false, 'message' => __('OTP not found. Please request a new one.', 'smart-login-registration'));
        }
        
        // Check if OTP has expired using GMT time
        $current_time = current_time('mysql', true);
        if ($record->expires_at <= $current_time) {
            // Delete expired OTP
            $wpdb->delete($this->table_name, array('id' => $record->id));
            if ($this->is_debug_enabled()) {
                error_log("SLR: OTP expired - Current: $current_time, Expires: {$record->expires_at}");
            }
            return array('success' => false, 'message' => __('OTP has expired. Please request a new one.', 'smart-login-registration'));
        }
        
        // Check attempt limit
        $max_attempts = isset($this->settings['max_attempts']) ? (int) $this->settings['max_attempts'] : 5;
        if ($record->attempts >= $max_attempts) {
            return array('success' => false, 'message' => __('Too many failed attempts. Please request a new OTP.', 'smart-login-registration'));
        }
        
        // Increment attempts
        $wpdb->update(
            $this->table_name,
            array('attempts' => $record->attempts + 1),
            array('id' => $record->id),
            array('%d'),
            array('%d')
        );
        
        // Check OTP
        if ($record->otp !== $otp) {
            if ($this->is_debug_enabled()) {
                error_log("SLR: OTP mismatch - Expected: {$record->otp}, Got: $otp");
            }
            return array('success' => false, 'message' => __('Invalid OTP. Please try again.', 'smart-login-registration'));
        }
        
        // OTP is valid, delete it
        $wpdb->delete($this->table_name, array('id' => $record->id));
        
        return array('success' => true, 'temp_data' => $record->temp_data ? json_decode($record->temp_data, true) : null);
    }
    
    /**
     * Send OTP email
     */
    public function send_otp_email($email, $otp, $type) {
        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] Your OTP Code', 'smart-login-registration'), $site_name);
        
        $message = '';
        $otp_expiry = isset($this->settings['otp_expiry']) ? (int) $this->settings['otp_expiry'] : 10;
        
        if ($type === 'login') {
            $message = sprintf(
                __('Your OTP code for login is: %s\n\nThis code will expire in %d minutes.\n\nIf you did not request this code, please ignore this email.', 'smart-login-registration'),
                $otp, $otp_expiry
            );
        } else if ($type === 'register') {
            $message = sprintf(
                __('Your OTP code for registration is: %s\n\nThis code will expire in %d minutes.\n\nIf you did not request this code, please ignore this email.', 'smart-login-registration'),
                $otp, $otp_expiry
            );
        }
        
        // Add HTML formatting
        $html_message = sprintf(
            '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2 style="color: #008036; text-align: center;">%s</h2>
                <p style="font-size: 16px; line-height: 1.6;">%s</p>
                <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 4px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Your OTP Code</h3>
                    <div style="font-size: 32px; font-weight: bold; color: #008036; letter-spacing: 4px; font-family: monospace;">%s</div>
                </div>
                <p style="font-size: 14px; color: #666; text-align: center;">This code will expire in %d minutes.</p>
                <p style="font-size: 12px; color: #999; text-align: center;">If you did not request this code, please ignore this email.</p>
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #999; text-align: center;">Powered by Smart Login & Registration Plugin</p>
            </div>',
            $site_name,
            $type === 'login' ? __('Please use the following OTP code to complete your login:', 'smart-login-registration') : __('Please use the following OTP code to complete your registration:', 'smart-login-registration'),
            $otp,
            $otp_expiry
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($email, $subject, $html_message, $headers);
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: OTP email sent - Email: $email, Type: $type, Result: " . ($result ? 'Success' : 'Failed'));
        }
        
        return $result;
    }
    
    /**
     * Check if email is rate limited
     */
    public function is_rate_limited($email) {
        global $wpdb;
        
        $rate_limit = isset($this->settings['rate_limit']) ? (int) $this->settings['rate_limit'] : 5;
        
        // Check if more than rate_limit OTPs were sent in the last hour
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $email
        ));
        
        return $count >= $rate_limit;
    }
    
    /**
     * Clean up expired OTPs
     */
    public function cleanup_expired_otps() {
        global $wpdb;
        
        $deleted = $wpdb->query("DELETE FROM {$this->table_name} WHERE expires_at < NOW()");
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: Cleaned up $deleted expired OTPs");
        }
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled() {
        return isset($this->settings['enable_debug']) && $this->settings['enable_debug'];
    }
}