<?php
/**
 * Smart Login and Registration - AJAX Handlers
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_AJAX_Handlers {
    
    private $otp_handler;
    private $user_handler;
    
    public function __construct() {
        $this->otp_handler = new SLR_OTP_Handler();
        $this->user_handler = new SLR_User_Handler();
    }
    
    /**
     * Handle AJAX login
     */
    public function handle_login() {
        check_ajax_referer('slr_login_nonce', 'slr_login_nonce');
        
        $username = sanitize_text_field($_POST['log']);
        $password = sanitize_text_field($_POST['pwd']);
        $remember = isset($_POST['rememberme']) ? true : false;
        
        // Check if login is by phone number
        $user = null;
        if ($this->user_handler->is_phone_number($username)) {
            $user = $this->user_handler->get_user_by_phone($username);
            if ($user) {
                $username = $user->user_login;
            }
        }
        
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($credentials, false);
        
        if (is_wp_error($user)) {
            // Custom error handling - replace WordPress default errors
            $custom_message = $this->get_custom_login_error($user, $username);
            wp_send_json_error(array('message' => $custom_message));
        } else {
            wp_send_json_success(array('message' => __('Login successful! Redirecting...', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle send OTP request
     */
    public function handle_send_otp() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid OTP type.', 'smart-login-registration')));
        }
        
        if (!$this->user_handler->is_valid_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'smart-login-registration')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        $temp_data = null;
        
        if ($otp_type === 'login') {
            // Check if user exists
            $user = get_user_by('email', $email);
            if (!$user) {
                wp_send_json_error(array('message' => __('No user found with that email address.', 'smart-login-registration')));
            }
        } else if ($otp_type === 'register') {
            // Check if user already exists
            if (email_exists($email)) {
                wp_send_json_error(array('message' => __('An account with this email already exists.', 'smart-login-registration')));
            }
            
            // For register, we need to collect the form data
            $temp_data = $this->user_handler->sanitize_user_data(array(
                'name' => $_POST['name'] ?? '',
                'email' => $email,
                'phone' => $_POST['phone'] ?? '',
                'password' => $_POST['password'] ?? ''
            ));
            
            // Validate required fields
            if (empty($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('Phone number is required for registration.', 'smart-login-registration')));
            }
            
            // Validate phone number
            if (!$this->user_handler->is_valid_phone($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'smart-login-registration')));
            }
            
            // Check if phone already exists
            if ($this->user_handler->phone_exists($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('This phone number is already registered.', 'smart-login-registration')));
            }
        }
        
        // Check rate limiting with high-performance caching
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        // Generate and store OTP with high-performance method
        $otp_result = $this->otp_handler->store_otp($email, $otp_type, null, $temp_data);
        
        if (!$otp_result) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
        // Increment rate limit counter efficiently
        $this->otp_handler->increment_rate_limit($email);
        
        // Use the actual OTP generated
        $otp = $otp_result;
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, $otp_type)) {
            wp_send_json_success(array(
                'message' => __('OTP sent successfully. Please check your email.', 'smart-login-registration'),
                'email' => $email,
                'otp_type' => $otp_type
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle OTP verification
     */
    public function handle_verify_otp() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp = sanitize_text_field($_POST['otp']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!$this->user_handler->is_valid_email($email) || empty($otp) || !in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid verification data.', 'smart-login-registration')));
        }
        
        // Verify OTP
        $verification = $this->otp_handler->verify_otp($email, $otp, $otp_type);
        
        if (!$verification['success']) {
            $error_data = array('message' => $verification['message']);
            
            // Include additional error information if available
            if (isset($verification['error_code'])) {
                $error_data['error_code'] = $verification['error_code'];
            }
            if (isset($verification['attempts_remaining'])) {
                $error_data['attempts_remaining'] = $verification['attempts_remaining'];
            }
            
            wp_send_json_error($error_data);
        }
        
        if ($otp_type === 'login') {
            // Log in the user with concurrency protection
            $user = get_user_by('email', $email);
            if (!$user) {
                wp_send_json_error(array('message' => __('User not found.', 'smart-login-registration')));
            }
            
            // Check for concurrent login attempts
            $concurrent_check = $this->otp_handler->handle_concurrent_login($user->ID, $email);
            if (!$concurrent_check['success']) {
                wp_send_json_error(array('message' => $concurrent_check['message'], 'error_code' => $concurrent_check['error_code'] ?? 'CONCURRENT_LOGIN_ERROR'));
            }
            
            // If already logged in, just return success
            if (isset($concurrent_check['already_logged_in']) && $concurrent_check['already_logged_in']) {
                wp_send_json_success(array('message' => $concurrent_check['message']));
            }
            
            // Set authentication
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            
            // Clear rate limit cache after successful login
            wp_cache_delete("slr_rate_limit_{$email}");
            
            // Generate session token for additional security
            $session_token = $this->otp_handler->generate_session_token($user->ID, $email);
            
            wp_send_json_success(array(
                'message' => __('Login successful! Welcome back!', 'smart-login-registration'),
                'session_token' => $session_token
            ));
            
        } else if ($otp_type === 'register') {
            // Create new user with temp data
            $temp_data = $verification['temp_data'];
            if (!$temp_data) {
                wp_send_json_error(array('message' => __('Registration data not found. Please try again.', 'smart-login-registration')));
            }
            
            // Create user
            $user_id = $this->user_handler->create_user($temp_data);
            
            if (is_wp_error($user_id)) {
                // Custom registration error handling
                $custom_message = $this->get_custom_registration_error($user_id);
                wp_send_json_error(array('message' => $custom_message));
            }
            
            // Auto login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            // Clear rate limit cache after successful registration
            wp_cache_delete("slr_rate_limit_{$temp_data['email']}");
            
            wp_send_json_success(array('message' => __('Registration successful! Welcome!', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle resend OTP
     */
    public function handle_resend_otp() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!$this->user_handler->is_valid_email($email) || !in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid resend request.', 'smart-login-registration')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        // Generate and store new OTP with concurrency protection (temp_data will be preserved automatically)
        $otp_result = $this->otp_handler->store_otp($email, $otp_type);
        
        if (!$otp_result) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
        // Use the actual OTP generated (could be different due to collision handling)
        $otp = is_string($otp_result) ? $otp_result : $this->otp_handler->generate_otp();
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, $otp_type)) {
            wp_send_json_success(array('message' => __('New OTP sent successfully. Please check your email.', 'smart-login-registration')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle OTP login (send OTP for login)
     */
    public function handle_otp_login() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!$this->user_handler->is_valid_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'smart-login-registration')));
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('No user found with that email address.', 'smart-login-registration')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        // Generate and store OTP with concurrency protection
        $otp_result = $this->otp_handler->store_otp($email, 'login');
        
        if (!$otp_result) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
        // Use the actual OTP generated (could be different due to collision handling)
        $otp = is_string($otp_result) ? $otp_result : $this->otp_handler->generate_otp();
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, 'login')) {
            wp_send_json_success(array(
                'message' => __('OTP sent successfully. Please check your email.', 'smart-login-registration'),
                'email' => $email,
                'otp_type' => 'login'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle forgot password
     */
    public function handle_forgot_password() {
        check_ajax_referer('slr_forgot_nonce', 'slr_forgot_nonce');
        
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Please enter your email or phone number.', 'smart-login-registration')));
        }
        
        // Check if it's a phone number
        $user = null;
        if ($this->user_handler->is_phone_number($user_login)) {
            $user = $this->user_handler->get_user_by_phone($user_login);
        } else {
            // Check if it's an email or username
            $user = get_user_by('email', $user_login);
            if (!$user) {
                $user = get_user_by('login', $user_login);
            }
        }
        
        if (!$user) {
            wp_send_json_error(array('message' => __('No user found with that email or phone number.', 'smart-login-registration')));
        }
        
        // Generate reset key
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            wp_send_json_error(array('message' => __('Unable to generate password reset key. Please try again.', 'smart-login-registration')));
        }
        
        // Send reset email
        $reset_url = $this->get_custom_reset_url($reset_key, $user->user_login);
        
        $subject = __('Password Reset Request', 'smart-login-registration');
        $message = sprintf(
            __('Someone has requested a password reset for the following account:\n\nSite Name: %s\nUsername: %s\n\nIf this was a mistake, just ignore this email and nothing will happen.\n\nTo reset your password, visit the following address:\n\n%s\n\nThis link will expire in 24 hours.', 'smart-login-registration'),
            get_bloginfo('name'),
            $user->user_login,
            $reset_url
        );
        
        $sent = wp_mail($user->user_email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(array('message' => __('Password reset email sent successfully. Please check your email.', 'smart-login-registration')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send password reset email. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle registration with OTP
     */
    public function handle_register() {
        check_ajax_referer('slr_register_nonce', 'slr_register_nonce');
        
        $user_data = $this->user_handler->sanitize_user_data(array(
            'name' => $_POST['first_name'] ?? $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'password' => $_POST['password'] ?? ''
        ));
        
        // Validate inputs
        if (empty($user_data['name']) || empty($user_data['email']) || empty($user_data['password']) || empty($user_data['phone'])) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'smart-login-registration')));
        }
        
        if (!$this->user_handler->is_valid_email($user_data['email'])) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'smart-login-registration')));
        }
        
        if (!$this->user_handler->is_valid_phone($user_data['phone'])) {
            wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'smart-login-registration')));
        }
        
        if ($this->user_handler->phone_exists($user_data['phone'])) {
            wp_send_json_error(array('message' => __('This phone number is already registered.', 'smart-login-registration')));
        }
        
        if (email_exists($user_data['email'])) {
            wp_send_json_error(array('message' => __('An account with this email already exists.', 'smart-login-registration')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($user_data['email'])) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        // Generate and store OTP with temp data
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($user_data['email'], 'register', $otp, $user_data)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($user_data['email'], $otp, 'register')) {
            wp_send_json_success(array(
                'message' => __('Registration OTP sent successfully. Please check your email to verify your account.', 'smart-login-registration'),
                'email' => $user_data['email'],
                'otp_type' => 'register',
                'step' => 'verify'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Get custom reset URL for password reset
     */
    private function get_custom_reset_url($reset_key, $user_login) {
        // First, try to find a page with [slr_reset_password] shortcode
        $reset_page = get_page_by_path('reset-password');
        
        if ($reset_page) {
            return add_query_arg(array(
                'key' => $reset_key,
                'login' => rawurlencode($user_login)
            ), get_permalink($reset_page->ID));
        }
        
        // If no custom page exists, use a query parameter approach
        return add_query_arg(array(
            'action' => 'slr_reset',
            'key' => $reset_key,
            'login' => rawurlencode($user_login)
        ), home_url());
    }
    
    /**
     * Handle send reset OTP request
     */
    public function handle_send_reset_otp() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Please enter your email or phone number.', 'smart-login-registration')));
        }
        
        // Check if it's a phone number
        $user = null;
        $email_to_send = '';
        
        if ($this->user_handler->is_phone_number($user_login)) {
            $user = $this->user_handler->get_user_by_phone($user_login);
            if ($user) {
                $email_to_send = $user->user_email;
            }
        } else {
            // Check if it's an email or username
            $user = get_user_by('email', $user_login);
            if ($user) {
                $email_to_send = $user_login;
            } else {
                $user = get_user_by('login', $user_login);
                if ($user) {
                    $email_to_send = $user->user_email;
                }
            }
        }
        
        if (!$user) {
            wp_send_json_error(array('message' => __('No user found with that email or phone number.', 'smart-login-registration')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email_to_send)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'smart-login-registration')));
        }
        
        // Generate and store OTP for password reset
        $otp_result = $this->otp_handler->store_otp($email_to_send, 'password_reset', null, array(
            'user_id' => $user->ID,
            'user_login' => $user_login
        ));
        
        if (!$otp_result) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
        // Use the actual OTP generated
        $otp = $otp_result;
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email_to_send, $otp, 'password_reset')) {
            wp_send_json_success(array(
                'message' => __('Reset OTP sent successfully. Please check your email.', 'smart-login-registration'),
                'email' => $email_to_send,
                'user_login' => $user_login,
                'step' => 'verify_otp'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'smart-login-registration')));
        }
    }
    
    /**
     * Handle verify reset OTP
     */
    public function handle_verify_reset_otp() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp = sanitize_text_field($_POST['otp']);
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (!$this->user_handler->is_valid_email($email) || empty($otp) || empty($user_login)) {
            wp_send_json_error(array('message' => __('Invalid verification data.', 'smart-login-registration')));
        }
        
        // Verify OTP
        $verification = $this->otp_handler->verify_otp($email, $otp, 'password_reset');
        
        if (!$verification['success']) {
            $error_data = array('message' => $verification['message']);
            
            // Include additional error information if available
            if (isset($verification['error_code'])) {
                $error_data['error_code'] = $verification['error_code'];
            }
            if (isset($verification['attempts_remaining'])) {
                $error_data['attempts_remaining'] = $verification['attempts_remaining'];
            }
            
            wp_send_json_error($error_data);
        }
        
        // Get user data from temp data
        $temp_data = $verification['temp_data'];
        if (!$temp_data || !isset($temp_data['user_id'])) {
            wp_send_json_error(array('message' => __('Reset data not found. Please try again.', 'smart-login-registration')));
        }
        
        $user = get_user_by('ID', $temp_data['user_id']);
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found.', 'smart-login-registration')));
        }
        
        // Generate a temporary reset token for security
        $reset_token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'slr_temp_reset_token', array(
            'token' => $reset_token,
            'expires' => time() + 600 // 10 minutes
        ));
        
        wp_send_json_success(array(
            'message' => __('OTP verified successfully. You can now set your new password.', 'smart-login-registration'),
            'user_id' => $user->ID,
            'reset_token' => $reset_token,
            'step' => 'new_password'
        ));
    }
    
    /**
     * Handle reset password with new password
     */
    public function handle_reset_password() {
        check_ajax_referer('slr_otp_nonce', 'slr_otp_nonce');
        
        $user_id = intval($_POST['user_id']);
        $reset_token = sanitize_text_field($_POST['reset_token']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($user_id) || empty($reset_token) || empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => __('Please fill in all fields.', 'smart-login-registration')));
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => __('Passwords do not match.', 'smart-login-registration')));
        }
        
        if (strlen($new_password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters long.', 'smart-login-registration')));
        }
        
        // Get user
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('Invalid user.', 'smart-login-registration')));
        }
        
        // Verify reset token
        $stored_token_data = get_user_meta($user_id, 'slr_temp_reset_token', true);
        if (!$stored_token_data || 
            !isset($stored_token_data['token']) || 
            !isset($stored_token_data['expires']) ||
            $stored_token_data['token'] !== $reset_token ||
            $stored_token_data['expires'] < time()) {
            
            wp_send_json_error(array('message' => __('Invalid or expired reset token. Please start over.', 'smart-login-registration')));
        }
        
        // Update password
        wp_set_password($new_password, $user_id);
        
        // Clean up reset token
        delete_user_meta($user_id, 'slr_temp_reset_token');
        
        // Clear any active sessions to force re-login
        $sessions = WP_Session_Tokens::get_instance($user_id);
        $sessions->destroy_all();
        
        // Auto-login the user after successful password reset
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Generate session token for additional security
        $session_token = $this->otp_handler->generate_session_token($user_id, $user->user_email);
        
        // Clear rate limit cache after successful password reset
        wp_cache_delete("slr_rate_limit_{$user->user_email}");
        
        wp_send_json_success(array(
            'message' => __('Password has been reset successfully! You are now logged in.', 'smart-login-registration'),
            'step' => 'complete',
            'auto_logged_in' => true,
            'session_token' => $session_token,
            'user_display_name' => $user->display_name
        ));
    }
    
    /**
     * Get custom login error message instead of WordPress defaults
     */
    private function get_custom_login_error($wp_error, $username) {
        $error_codes = $wp_error->get_error_codes();
        $error_message = $wp_error->get_error_message();
        
        // Handle phone number not registered
        if ($this->user_handler->is_phone_number($username)) {
            // Check if user exists with this phone
            $found_user = $this->user_handler->get_user_by_phone($username);
            
            if (!$found_user) {
                return __('Phone number not found. Please check your phone number or register a new account.', 'smart-login-registration');
            } else {
                return __('The password you entered for this phone number is incorrect.', 'smart-login-registration');
            }
        }
        
        // Handle specific error codes
        if (in_array('invalid_username', $error_codes)) {
            return __('Invalid username. Please check your username or try your email address.', 'smart-login-registration');
        }
        
        if (in_array('incorrect_password', $error_codes)) {
            return __('The password you entered is incorrect.', 'smart-login-registration');
        }
        
        if (in_array('empty_username', $error_codes)) {
            return __('Please enter your username or phone number.', 'smart-login-registration');
        }
        
        if (in_array('empty_password', $error_codes)) {
            return __('Please enter your password.', 'smart-login-registration');
        }
        
        // Check if error message contains phone number pattern
        if (preg_match('/\b(\d{7,15})\b/', $error_message) && strpos($error_message, 'is not registered') !== false) {
            return __('Phone number not found. Please check your phone number or register a new account.', 'smart-login-registration');
        }
        
        // Default fallback for any other error
        return __('Login failed. Please check your credentials and try again.', 'smart-login-registration');
    }
    
    /**
     * Get custom registration error message instead of WordPress defaults
     */
    private function get_custom_registration_error($wp_error) {
        $error_codes = $wp_error->get_error_codes();
        $error_message = $wp_error->get_error_message();
        
        // Handle specific registration error codes
        if (in_array('existing_user_login', $error_codes)) {
            return __('This username is already taken. Please choose a different username.', 'smart-login-registration');
        }
        
        if (in_array('existing_user_email', $error_codes)) {
            return __('An account with this email address already exists. Please use a different email or try logging in.', 'smart-login-registration');
        }
        
        if (in_array('invalid_email', $error_codes)) {
            return __('Please enter a valid email address.', 'smart-login-registration');
        }
        
        if (in_array('empty_user_login', $error_codes)) {
            return __('Please enter a username.', 'smart-login-registration');
        }
        
        if (in_array('empty_email', $error_codes)) {
            return __('Please enter an email address.', 'smart-login-registration');
        }
        
        // Check for phone number related errors
        if (strpos($error_message, 'phone') !== false) {
            return __('There was an issue with the phone number. Please check and try again.', 'smart-login-registration');
        }
        
        // Default fallback for any other registration error
        return __('Registration failed. Please check your information and try again.', 'smart-login-registration');
    }
}