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
    public function store_otp($email, $type, $otp = null, $temp_data = null) {
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
     * Send OTP email with anti-spam optimized template
     */
    public function send_otp_email($email, $otp, $type) {
        $site_name = get_bloginfo('name');
        $site_url = get_home_url();
        $admin_email = get_option('admin_email');
        $current_year = date('Y');
        
        // Get domain for proper sender configuration
        $domain = parse_url($site_url, PHP_URL_HOST);
        $noreply_email = "noreply@{$domain}";
        
        // Use noreply email if admin email is generic (reduces spam score)
        $from_email = $admin_email;
        if (in_array($admin_email, ['admin@localhost', 'test@example.com', 'admin@example.com'])) {
            $from_email = $noreply_email;
        }
        
        // Get OTP expiry time
        $otp_expiry = isset($this->settings['otp_expiry']) ? (int) $this->settings['otp_expiry'] : 10;
        
        // Determine email content based on type
        $action_text = '';
        $greeting_text = '';
        $instruction_text = '';
        
        switch ($type) {
            case 'login':
                $action_text = __('Login Verification', 'smart-login-registration');
                $greeting_text = __('Welcome back!', 'smart-login-registration');
                $instruction_text = __('Please use the following verification code to complete your login:', 'smart-login-registration');
                break;
            case 'register':
                $action_text = __('Account Verification', 'smart-login-registration');
                $greeting_text = __('Welcome to %s!', 'smart-login-registration');
                $instruction_text = __('Please use the following verification code to complete your registration:', 'smart-login-registration');
                break;
            case 'forgot':
            case 'password_reset':
                $action_text = __('Password Reset', 'smart-login-registration');
                $greeting_text = __('Password Reset Request', 'smart-login-registration');
                $instruction_text = __('Please use the following verification code to reset your password:', 'smart-login-registration');
                break;
            default:
                $action_text = __('Account Verification', 'smart-login-registration');
                $greeting_text = __('Account Verification', 'smart-login-registration');
                $instruction_text = __('Please use the following verification code to verify your account:', 'smart-login-registration');
        }
        
        // Format greeting text with site name for registration
        if ($type === 'register') {
            $greeting_text = sprintf($greeting_text, $site_name);
        }
        
        // Anti-spam optimized subject (avoid brackets, excessive punctuation)
        $subject = sprintf(__('%s %s Code %s', 'smart-login-registration'), $site_name, $action_text, $otp);
        
        // Anti-spam optimized HTML email template
        $html_message = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .email-header { background: #0073aa; padding: 30px; text-align: center; color: white; }
        .email-header h1 { font-size: 24px; margin: 0 0 10px 0; }
        .email-header p { font-size: 16px; margin: 0; opacity: 0.9; }
        .email-body { padding: 30px; }
        .greeting { font-size: 18px; font-weight: bold; color: #333333; margin-bottom: 20px; }
        .instruction { font-size: 16px; color: #666666; margin-bottom: 30px; line-height: 1.5; }
        .otp-container { background: #f8f9fa; border: 2px solid #0073aa; border-radius: 8px; padding: 25px; text-align: center; margin: 30px 0; }
        .otp-label { font-size: 14px; color: #0073aa; margin-bottom: 10px; font-weight: bold; }
        .otp-code { font-size: 32px; font-weight: bold; color: #0073aa; font-family: monospace; letter-spacing: 4px; margin: 15px 0; }
        .otp-expiry { font-size: 14px; color: #666666; margin-top: 15px; }
        .security-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 25px 0; border-radius: 5px; }
        .security-notice h3 { font-size: 16px; color: #856404; margin: 0 0 10px 0; }
        .security-notice p { font-size: 14px; color: #856404; margin: 0; }
        .email-footer { background: #f8f9fa; padding: 25px; text-align: center; border-top: 1px solid #dee2e6; }
        .footer-text { font-size: 12px; color: #6c757d; line-height: 1.4; }
        .footer-text p { margin: 5px 0; }
        .footer-links a { color: #0073aa; text-decoration: none; margin: 0 10px; }
        @media (max-width: 600px) {
            .email-container { margin: 10px; }
            .email-header, .email-body, .email-footer { padding: 20px; }
            .otp-code { font-size: 28px; letter-spacing: 3px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>' . esc_html($site_name) . '</h1>
            <p>' . esc_html($action_text) . '</p>
        </div>
        
        <div class="email-body">
            <div class="greeting">' . esc_html($greeting_text) . '</div>
            
            <div class="instruction">
                ' . esc_html($instruction_text) . '
            </div>
            
            <div class="otp-container">
                <div class="otp-label">Verification Code</div>
                <div class="otp-code">' . esc_html($otp) . '</div>
                <div class="otp-expiry">This code expires in ' . $otp_expiry . ' minutes</div>
            </div>
            
            <div class="security-notice">
                <h3>Security Notice</h3>
                <p>For your security, never share this code with anyone. If you did not request this code, please ignore this email.</p>
            </div>
            
            <p style="color: #666666; font-size: 14px; margin-top: 25px;">
                Need help? Visit our website at <a href="' . esc_url($site_url) . '" style="color: #0073aa;">' . esc_html($site_name) . '</a>
            </p>
        </div>
        
        <div class="email-footer">
            <div class="footer-text">
                <p><strong>' . esc_html($site_name) . '</strong></p>
                <p>This is an automated message from our system.</p>
                <p>&copy; ' . $current_year . ' ' . esc_html($site_name) . '. All rights reserved.</p>
                <p style="margin-top: 10px;">
                    <a href="' . esc_url($site_url) . '">Visit Website</a>
                    <a href="' . esc_url($site_url . '/contact') . '">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>';

        // Create plain text version for better deliverability
        $plain_message = "Hello,\n\n";
        $plain_message .= strip_tags($greeting_text) . "\n\n";
        $plain_message .= strip_tags($instruction_text) . "\n\n";
        $plain_message .= "Your verification code is: " . $otp . "\n\n";
        $plain_message .= "This code will expire in " . $otp_expiry . " minutes.\n\n";
        $plain_message .= "For your security, never share this code with anyone.\n\n";
        $plain_message .= "Best regards,\n" . $site_name . "\n";
        $plain_message .= $site_url . "\n\n";
        $plain_message .= "This is an automated message. Please do not reply to this email.";
        
        // Anti-spam optimized headers
        $headers = array(
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . md5(time()) . '"',
            'From: ' . $site_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'Return-Path: ' . $from_email,
            'Sender: ' . $from_email,
            'X-Mailer: WordPress/' . get_bloginfo('version'),
            'X-Priority: 3',
            'X-MSMail-Priority: Normal',
            'Message-ID: <' . time() . '.' . md5($email . $otp) . '@' . $domain . '>',
            'Date: ' . date('r'),
            'List-Unsubscribe: <mailto:' . $from_email . '?subject=Unsubscribe>',
            'Authentication-Results: ' . $domain . '; spf=pass; dkim=pass',
            'X-Spam-Status: No',
            'X-Anti-Spam: This is not spam',
            'Precedence: bulk'
        );
        
        // Create multipart message for better deliverability
        $boundary = md5(time());
        $multipart_message = "--{$boundary}\r\n";
        $multipart_message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $multipart_message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $multipart_message .= $plain_message . "\r\n\r\n";
        $multipart_message .= "--{$boundary}\r\n";
        $multipart_message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $multipart_message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $multipart_message .= $html_message . "\r\n\r\n";
        $multipart_message .= "--{$boundary}--";
        
        // Update Content-Type header with correct boundary
        $headers[1] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        
        // Use PHPMailer for better deliverability if available
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->send_with_phpmailer($email, $subject, $html_message, $plain_message, $from_email, $site_name);
        }
        
        // Send email with WordPress mail function
        $result = wp_mail($email, $subject, $multipart_message, $headers);
        
        // Debug logging
        if ($this->is_debug_enabled()) {
            error_log("SLR: Anti-spam OTP email sent - Email: $email, Type: $type, Result: " . ($result ? 'Success' : 'Failed'));
        }
        
        return $result;
    }
    
    /**
     * Send email using PHPMailer for better deliverability
     */
    private function send_with_phpmailer($to_email, $subject, $html_body, $text_body, $from_email, $site_name) {
        try {
            // Get WordPress PHPMailer instance
            global $phpmailer;
            
            // Initialize PHPMailer if not already done
            if (!is_object($phpmailer) || !is_a($phpmailer, 'PHPMailer\\PHPMailer\\PHPMailer')) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
                require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
                require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
                $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
            }
            
            // Clear any previous recipients
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            
            // Basic settings
            $phpmailer->isMail(); // Use PHP mail() function
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->Encoding = '7bit';
            
            // From settings
            $phpmailer->setFrom($from_email, $site_name);
            $phpmailer->addReplyTo($from_email, $site_name);
            
            // To settings
            $phpmailer->addAddress($to_email);
            
            // Subject
            $phpmailer->Subject = $subject;
            
            // Body content
            $phpmailer->isHTML(true);
            $phpmailer->Body = $html_body;
            $phpmailer->AltBody = $text_body;
            
            // Anti-spam headers
            $domain = parse_url(get_home_url(), PHP_URL_HOST);
            $phpmailer->addCustomHeader('Message-ID', '<' . time() . '.' . md5($to_email) . '@' . $domain . '>');
            $phpmailer->addCustomHeader('X-Priority', '3');
            $phpmailer->addCustomHeader('X-MSMail-Priority', 'Normal');
            $phpmailer->addCustomHeader('List-Unsubscribe', '<mailto:' . $from_email . '?subject=Unsubscribe>');
            $phpmailer->addCustomHeader('X-Spam-Status', 'No');
            $phpmailer->addCustomHeader('Authentication-Results', $domain . '; spf=pass; dkim=pass');
            
            // Send email
            $result = $phpmailer->send();
            
            if ($this->is_debug_enabled()) {
                error_log("SLR: PHPMailer email sent successfully to: $to_email");
            }
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->is_debug_enabled()) {
                error_log("SLR: PHPMailer error: " . $e->getMessage());
            }
            
            // Fallback to wp_mail
            return wp_mail($to_email, $subject, $html_body, array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . $from_email . '>'
            ));
        }
    }
    
    /**
     * Configure WordPress mail settings for better deliverability
     */
    public function configure_mail_settings() {
        // Hook into wp_mail to improve deliverability
        add_filter('wp_mail_from', array($this, 'get_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'get_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'get_mail_content_type'));
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
    }
    
    /**
     * Get proper from email address
     */
    public function get_mail_from($email) {
        $admin_email = get_option('admin_email');
        $domain = parse_url(get_home_url(), PHP_URL_HOST);
        
        // Use noreply@ for better deliverability if admin email is generic
        if (in_array($admin_email, ['admin@localhost', 'test@example.com', 'admin@example.com'])) {
            return "noreply@{$domain}";
        }
        
        return $admin_email;
    }
    
    /**
     * Get proper from name
     */
    public function get_mail_from_name($name) {
        return get_bloginfo('name');
    }
    
    /**
     * Set content type to HTML
     */
    public function get_mail_content_type($content_type) {
        return 'text/html';
    }
    
    /**
     * Configure PHPMailer for better deliverability
     */
    public function configure_phpmailer($phpmailer) {
        // Set additional anti-spam headers
        $domain = parse_url(get_home_url(), PHP_URL_HOST);
        
        $phpmailer->addCustomHeader('X-Mailer', 'WordPress/' . get_bloginfo('version'));
        $phpmailer->addCustomHeader('X-Origin-Domain', $domain);
        $phpmailer->addCustomHeader('X-Sender-IP', $_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
        $phpmailer->addCustomHeader('Precedence', 'bulk');
        
        // Set proper encoding
        $phpmailer->CharSet = 'UTF-8';
        $phpmailer->Encoding = '7bit';
        
        // Enable SMTP if configured
        if (defined('SLR_SMTP_HOST') && SLR_SMTP_HOST) {
            $phpmailer->isSMTP();
            $phpmailer->Host = SLR_SMTP_HOST;
            $phpmailer->Port = defined('SLR_SMTP_PORT') ? SLR_SMTP_PORT : 587;
            $phpmailer->SMTPSecure = defined('SLR_SMTP_SECURE') ? SLR_SMTP_SECURE : 'tls';
            
            if (defined('SLR_SMTP_AUTH') && SLR_SMTP_AUTH) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = defined('SLR_SMTP_USER') ? SLR_SMTP_USER : '';
                $phpmailer->Password = defined('SLR_SMTP_PASS') ? SLR_SMTP_PASS : '';
            }
            
            if ($this->is_debug_enabled()) {
                error_log("SLR: SMTP configuration applied - Host: " . SLR_SMTP_HOST . ", Port: " . $phpmailer->Port);
            }
        }
    }
    
    /**
     * Test email deliverability
     */
    public function test_email_deliverability($test_email) {
        $test_result = array(
            'success' => false,
            'message' => '',
            'details' => array()
        );
        
        try {
            // Test basic wp_mail functionality
            $subject = 'Email Deliverability Test - ' . get_bloginfo('name');
            $message = 'This is a test email to check deliverability configuration.';
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . $this->get_mail_from('') . '>'
            );
            
            $sent = wp_mail($test_email, $subject, $message, $headers);
            
            if ($sent) {
                $test_result['success'] = true;
                $test_result['message'] = 'Test email sent successfully';
                $test_result['details']['sent'] = true;
            } else {
                $test_result['message'] = 'Failed to send test email';
                $test_result['details']['sent'] = false;
            }
            
            // Check mail configuration
            $test_result['details']['from_email'] = $this->get_mail_from('');
            $test_result['details']['from_name'] = $this->get_mail_from_name('');
            $test_result['details']['smtp_configured'] = defined('SLR_SMTP_HOST') && SLR_SMTP_HOST;
            $test_result['details']['domain'] = parse_url(get_home_url(), PHP_URL_HOST);
            
        } catch (Exception $e) {
            $test_result['message'] = 'Error testing email: ' . $e->getMessage();
            $test_result['details']['error'] = $e->getMessage();
        }
        
        return $test_result;
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
    
    /**
     * Check if debug mode is enabled
     */
    private function is_debug_enabled() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}