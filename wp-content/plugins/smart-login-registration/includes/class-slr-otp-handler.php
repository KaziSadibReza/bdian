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
     * Create OTP table for storing verification codes with high-performance indexes
     */
    public function create_otp_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            otp varchar(4) NOT NULL,
            otp_type varchar(20) NOT NULL,
            temp_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            attempts tinyint(3) DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_email_type (email, otp_type),
            INDEX idx_expires_email (expires_at, email),
            INDEX idx_otp_expires (otp, expires_at),
            INDEX idx_created_email (created_at, email),
            INDEX idx_type_expires (otp_type, expires_at)
        ) $charset_collate ENGINE=InnoDB;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add additional performance optimizations
        $wpdb->query("ALTER TABLE {$this->table_name} 
            ADD INDEX idx_email_type_expires (email, otp_type, expires_at),
            ADD INDEX idx_active_otps (expires_at, email, otp_type) USING BTREE");
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
     * Generate a 4-digit OTP
     */
    public function generate_otp() {
        return str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Store OTP in database with high-performance concurrency protection
     */
    public function store_otp($email, $otp = null, $type, $temp_data = null) {
        global $wpdb;
        
        $otp_expiry = isset($this->settings['otp_expiry']) ? (int) $this->settings['otp_expiry'] : 10;
        $expires_at = current_time('mysql', true); // Use GMT time
        $expires_at = date('Y-m-d H:i:s', strtotime($expires_at . " +{$otp_expiry} minutes"));
        
        // Use cache key for this operation
        $cache_key = "slr_otp_store_{$email}_{$type}";
        
        // Check if we're already processing this request (prevents duplicate requests)
        if (wp_cache_get($cache_key)) {
            if ($this->is_debug_enabled()) {
                error_log("SLR: Duplicate OTP request detected for $email, $type - returning cached result");
            }
            return false; // Prevent spam requests
        }
        
        // Set processing flag for 30 seconds
        wp_cache_set($cache_key, true, '', 30);
        
        try {
            // Get existing temp_data if this is a resend and temp_data is not provided
            if (!$temp_data && $type === 'register') {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT temp_data FROM {$this->table_name} 
                     WHERE email = %s AND otp_type = %s 
                     ORDER BY created_at DESC LIMIT 1",
                    $email, $type
                ));
                if ($existing && $existing->temp_data) {
                    $temp_data = json_decode($existing->temp_data, true);
                }
            }
            
            // Generate unique OTP with minimal collision checking for performance
            if (!$otp) {
                $max_attempts = 3; // Reduced for performance
                $attempt = 0;
                
                do {
                    $otp = $this->generate_otp();
                    $attempt++;
                    
                    // Quick collision check with optimized query
                    $existing_otp = $wpdb->get_var($wpdb->prepare(
                        "SELECT 1 FROM {$this->table_name} 
                         WHERE otp = %s AND expires_at > %s LIMIT 1",
                        $otp, current_time('mysql', true)
                    ));
                    
                } while ($existing_otp && $attempt < $max_attempts);
                
                // If still collision after 3 attempts, add timestamp for uniqueness
                if ($existing_otp && $attempt >= $max_attempts) {
                    $otp = substr(str_pad(time() % 10000, 4, '0', STR_PAD_LEFT), -4);
                }
            }
            
            // Use REPLACE for atomic upsert operation (much faster than DELETE + INSERT)
            $result = $wpdb->query($wpdb->prepare(
                "REPLACE INTO {$this->table_name} 
                 (email, otp, otp_type, temp_data, expires_at, attempts) 
                 VALUES (%s, %s, %s, %s, %s, 0)",
                $email, $otp, $type, 
                $temp_data ? json_encode($temp_data) : null, 
                $expires_at
            ));
            
            if ($result === false) {
                throw new Exception('Failed to store OTP: ' . $wpdb->last_error);
            }
            
            // Cache the OTP for quick verification (5 minutes)
            $otp_cache_key = "slr_otp_verify_{$email}_{$type}";
            wp_cache_set($otp_cache_key, array(
                'otp' => $otp,
                'expires_at' => $expires_at,
                'attempts' => 0,
                'temp_data' => $temp_data
            ), '', 300);
            
            if ($this->is_debug_enabled()) {
                error_log("SLR: High-performance OTP stored - Email: $email, Type: $type, OTP: $otp");
            }
            
            return $otp;
            
        } catch (Exception $e) {
            error_log("SLR: High-performance OTP storage failed: " . $e->getMessage());
            return false;
        } finally {
            // Clear processing flag
            wp_cache_delete($cache_key);
        }
    }
    
    /**
     * Verify OTP with high-performance caching and minimal database hits
     */
    public function verify_otp($email, $otp, $type) {
        global $wpdb;
        
        // Input validation
        if (empty($email) || empty($otp) || empty($type)) {
            return array(
                'success' => false, 
                'message' => __('Missing required fields for OTP verification.', 'smart-login-registration'),
                'error_code' => 'INVALID_INPUT'
            );
        }
        
        // Validate email format
        if (!is_email($email)) {
            return array(
                'success' => false, 
                'message' => __('Invalid email address format.', 'smart-login-registration'),
                'error_code' => 'INVALID_EMAIL'
            );
        }
        
        // Validate OTP format (4 digits)
        if (!preg_match('/^\d{4}$/', $otp)) {
            return array(
                'success' => false, 
                'message' => __('OTP must be exactly 4 digits.', 'smart-login-registration'),
                'error_code' => 'INVALID_OTP_FORMAT'
            );
        }
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: High-performance OTP Verification - Email: $email, OTP: $otp, Type: $type");
        }
        
        // Try cache first for ultra-fast verification
        $otp_cache_key = "slr_otp_verify_{$email}_{$type}";
        $cached_otp = wp_cache_get($otp_cache_key);
        
        if ($cached_otp && is_array($cached_otp)) {
            // Check expiration from cache
            if (strtotime($cached_otp['expires_at']) <= time()) {
                wp_cache_delete($otp_cache_key);
                // Clean up database record asynchronously
                wp_schedule_single_event(time(), 'slr_cleanup_expired_otp', array($email, $type));
                
                return array(
                    'success' => false, 
                    'message' => __('OTP has expired. Please request a new one.', 'smart-login-registration'),
                    'error_code' => 'OTP_EXPIRED'
                );
            }
            
            // Check attempts from cache
            $max_attempts = isset($this->settings['max_attempts']) ? (int) $this->settings['max_attempts'] : 5;
            if ($cached_otp['attempts'] >= $max_attempts) {
                wp_cache_delete($otp_cache_key);
                return array(
                    'success' => false, 
                    'message' => __('Too many failed attempts. Please request a new OTP.', 'smart-login-registration'),
                    'error_code' => 'MAX_ATTEMPTS_EXCEEDED'
                );
            }
            
            // Verify OTP from cache
            if ($cached_otp['otp'] === $otp) {
                // Success! Clear cache and database record
                wp_cache_delete($otp_cache_key);
                
                // Async database cleanup
                wp_schedule_single_event(time(), 'slr_cleanup_used_otp', array($email, $type, $otp));
                
                if ($this->is_debug_enabled()) {
                    error_log("SLR: High-performance OTP verification successful (cached) - Email: $email");
                }
                
                return array(
                    'success' => true, 
                    'temp_data' => $cached_otp['temp_data'] ?? null,
                    'message' => __('OTP verified successfully.', 'smart-login-registration')
                );
            } else {
                // Wrong OTP, increment attempts in cache
                $cached_otp['attempts']++;
                wp_cache_set($otp_cache_key, $cached_otp, '', 300);
                
                // Async database update
                wp_schedule_single_event(time(), 'slr_update_otp_attempts', array($email, $type, $cached_otp['attempts']));
                
                $remaining_attempts = $max_attempts - $cached_otp['attempts'];
                $message = $remaining_attempts > 0 
                    ? sprintf(__('Invalid OTP. %d attempts remaining.', 'smart-login-registration'), $remaining_attempts)
                    : __('Invalid OTP. This was your last attempt.', 'smart-login-registration');
                    
                return array(
                    'success' => false, 
                    'message' => $message,
                    'error_code' => 'INVALID_OTP',
                    'attempts_remaining' => $remaining_attempts
                );
            }
        }
        
        // Fallback to database if not in cache (shouldn't happen often)
        try {
            // Use optimized query with composite index
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE email = %s AND otp_type = %s AND expires_at > %s 
                 ORDER BY created_at DESC LIMIT 1",
                $email, $type, current_time('mysql', true)
            ));
            
            if (!$record) {
                return array(
                    'success' => false, 
                    'message' => __('OTP not found or expired. Please request a new one.', 'smart-login-registration'),
                    'error_code' => 'OTP_NOT_FOUND'
                );
            }
            
            // Re-cache for future requests
            wp_cache_set($otp_cache_key, array(
                'otp' => $record->otp,
                'expires_at' => $record->expires_at,
                'attempts' => $record->attempts,
                'temp_data' => $record->temp_data ? json_decode($record->temp_data, true) : null
            ), '', 300);
            
            // Recursively call with cache now populated
            return $this->verify_otp($email, $otp, $type);
            
        } catch (Exception $e) {
            error_log("SLR: High-performance OTP verification database fallback failed: " . $e->getMessage());
            return array(
                'success' => false, 
                'message' => __('An error occurred during verification. Please try again.', 'smart-login-registration'),
                'error_code' => 'EXCEPTION_ERROR'
            );
        }
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
     * Check if email is rate limited with high-performance caching
     */
    public function is_rate_limited($email) {
        global $wpdb;
        
        $rate_limit = isset($this->settings['rate_limit']) ? (int) $this->settings['rate_limit'] : 10; // Increased from 5 to 10
        
        // Use cache for rate limiting to avoid database hits
        $rate_cache_key = "slr_rate_limit_{$email}";
        $cached_count = wp_cache_get($rate_cache_key);
        
        if ($cached_count !== false) {
            return $cached_count >= $rate_limit;
        }
        
        // Only hit database if not in cache - check last 30 minutes instead of 1 hour
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
            $email
        ));
        
        // Cache for 5 minutes
        wp_cache_set($rate_cache_key, $count, '', 300);
        
        return $count >= $rate_limit;
    }
    
    /**
     * Increment rate limit counter efficiently
     */
    public function increment_rate_limit($email) {
        $rate_cache_key = "slr_rate_limit_{$email}";
        $current_count = wp_cache_get($rate_cache_key);
        
        if ($current_count !== false) {
            wp_cache_set($rate_cache_key, $current_count + 1, '', 300);
        } else {
            // Re-calculate and cache
            $this->is_rate_limited($email);
        }
    }
    
    /**
     * Clean up expired OTPs with batch processing for performance
     */
    public function cleanup_expired_otps() {
        global $wpdb;
        
        // Use batch processing to avoid long-running queries
        $batch_size = 100;
        $total_deleted = 0;
        
        do {
            $deleted = $wpdb->query("
                DELETE FROM {$this->table_name} 
                WHERE expires_at < NOW() 
                LIMIT {$batch_size}
            ");
            $total_deleted += $deleted;
        } while ($deleted == $batch_size);
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: Batch cleaned up $total_deleted expired OTPs");
        }
        
        return $total_deleted;
    }
    
    /**
     * Async cleanup functions for WordPress cron
     */
    public function register_async_cleanup_hooks() {
        add_action('slr_cleanup_expired_otp', array($this, 'async_cleanup_expired_otp'), 10, 2);
        add_action('slr_cleanup_used_otp', array($this, 'async_cleanup_used_otp'), 10, 3);
        add_action('slr_update_otp_attempts', array($this, 'async_update_otp_attempts'), 10, 3);
    }
    
    public function async_cleanup_expired_otp($email, $type) {
        global $wpdb;
        $wpdb->delete($this->table_name, array(
            'email' => $email,
            'otp_type' => $type
        ));
    }
    
    public function async_cleanup_used_otp($email, $type, $otp) {
        global $wpdb;
        $wpdb->delete($this->table_name, array(
            'email' => $email,
            'otp_type' => $type,
            'otp' => $otp
        ));
    }
    
    public function async_update_otp_attempts($email, $type, $attempts) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            array('attempts' => $attempts),
            array('email' => $email, 'otp_type' => $type),
            array('%d'),
            array('%s', '%s')
        );
    }
    
    /**
     * Optimize database tables for high performance
     */
    public function optimize_performance() {
        global $wpdb;
        
        // Optimize table
        $wpdb->query("OPTIMIZE TABLE {$this->table_name}");
        
        // Update table statistics
        $wpdb->query("ANALYZE TABLE {$this->table_name}");
        
        if ($this->is_debug_enabled()) {
            error_log("SLR: Database optimization completed");
        }
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled() {
        return isset($this->settings['enable_debug']) && $this->settings['enable_debug'];
    }
    
    /**
     * Handle concurrent login attempts with session protection
     */
    public function handle_concurrent_login($user_id, $email) {
        // Check if user is already logged in from another session
        if (is_user_logged_in() && get_current_user_id() == $user_id) {
            if ($this->is_debug_enabled()) {
                error_log("SLR: User $user_id ($email) already logged in, skipping duplicate login");
            }
            return array(
                'success' => true,
                'message' => __('You are already logged in.', 'smart-login-registration'),
                'already_logged_in' => true
            );
        }
        
        // Check for active sessions (WordPress 4.0+)
        if (function_exists('wp_get_all_sessions')) {
            $sessions = wp_get_all_sessions($user_id);
            $active_sessions = count($sessions);
            
            // Limit concurrent sessions if configured
            $max_sessions = isset($this->settings['max_concurrent_sessions']) ? (int) $this->settings['max_concurrent_sessions'] : 0;
            
            if ($max_sessions > 0 && $active_sessions >= $max_sessions) {
                if ($this->is_debug_enabled()) {
                    error_log("SLR: User $user_id has $active_sessions active sessions (max: $max_sessions)");
                }
                
                // Optionally destroy oldest session
                if (isset($this->settings['destroy_old_sessions']) && $this->settings['destroy_old_sessions']) {
                    wp_destroy_other_sessions($user_id);
                    if ($this->is_debug_enabled()) {
                        error_log("SLR: Destroyed old sessions for user $user_id");
                    }
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Maximum number of concurrent sessions reached. Please log out from other devices.', 'smart-login-registration'),
                        'error_code' => 'MAX_SESSIONS_EXCEEDED'
                    );
                }
            }
        }
        
        return array('success' => true);
    }
    
    /**
     * Generate secure session token for additional verification
     */
    public function generate_session_token($user_id, $email) {
        $token_data = array(
            'user_id' => $user_id,
            'email' => $email,
            'timestamp' => time(),
            'ip' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        );
        
        $token = wp_hash(serialize($token_data) . wp_salt());
        
        // Store token temporarily for verification
        set_transient('slr_session_token_' . $user_id, $token, 300); // 5 minutes
        
        return $token;
    }
    
    /**
     * Get client IP address safely
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}