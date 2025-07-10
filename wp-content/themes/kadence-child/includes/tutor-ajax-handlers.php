<?php
/**
 * Tutor Login Popup - AJAX Handlers
 * 
 * @package Kadence Child
 * @author Kazi Sadib Reza
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorAjaxHandlers {
    
    private $otp_handler;
    private $user_handler;
    
    public function __construct() {
        $this->otp_handler = new TutorOtpHandler();
        $this->user_handler = new TutorUserHandler();
    }
    
    /**
     * Handle AJAX login
     */
    public function handle_login() {
        check_ajax_referer('tutor_login_nonce', 'tutor_login_nonce');
        
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
            wp_send_json_error(array('message' => $user->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Login successful! Redirecting...', 'tutor')));
        }
    }
    
    /**
     * Handle send OTP request
     */
    public function handle_send_otp() {
        check_ajax_referer('tutor_otp_nonce', 'tutor_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid OTP type.', 'tutor')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tutor')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'tutor')));
        }
        
        $temp_data = null;
        
        if ($otp_type === 'login') {
            // Check if user exists
            $user = get_user_by('email', $email);
            if (!$user) {
                wp_send_json_error(array('message' => __('No user found with that email address.', 'tutor')));
            }
        } else if ($otp_type === 'register') {
            // Check if user already exists
            if (email_exists($email)) {
                wp_send_json_error(array('message' => __('An account with this email already exists.', 'tutor')));
            }
            
            // For register, we need to collect the form data
            $temp_data = array(
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'email' => $email,
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'password' => $_POST['password'] ?? ''
            );
            
            // Validate phone number
            if (!$this->user_handler->is_valid_phone($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'tutor')));
            }
            
            // Check if phone already exists
            if ($this->user_handler->phone_exists($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('This phone number is already registered.', 'tutor')));
            }
        }
        
        // Generate and store OTP
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, $otp_type, $temp_data)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'tutor')));
        }
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, $otp_type)) {
            wp_send_json_success(array(
                'message' => __('OTP sent successfully. Please check your email.', 'tutor'),
                'email' => $email,
                'otp_type' => $otp_type
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'tutor')));
        }
    }
    
    /**
     * Handle OTP verification
     */
    public function handle_verify_otp() {
        check_ajax_referer('tutor_otp_nonce', 'tutor_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp = sanitize_text_field($_POST['otp']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!is_email($email) || empty($otp) || !in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid verification data.', 'tutor')));
        }
        
        // Verify OTP
        $verification = $this->otp_handler->verify_otp($email, $otp, $otp_type);
        
        if (!$verification['success']) {
            wp_send_json_error(array('message' => $verification['message']));
        }
        
        if ($otp_type === 'login') {
            // Log in the user
            $user = get_user_by('email', $email);
            if (!$user) {
                wp_send_json_error(array('message' => __('User not found.', 'tutor')));
            }
            
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            
            wp_send_json_success(array('message' => __('Login successful! Welcome back!', 'tutor')));
            
        } else if ($otp_type === 'register') {
            // Create new user with temp data
            $temp_data = $verification['temp_data'];
            if (!$temp_data) {
                wp_send_json_error(array('message' => __('Registration data not found. Please try again.', 'tutor')));
            }
            
            // Create user
            $user_id = wp_create_user($temp_data['email'], $temp_data['password'], $temp_data['email']);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }
            
            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $temp_data['name'],
                'first_name' => $temp_data['name']
            ));
            
            // Store phone number
            update_user_meta($user_id, 'phone', $temp_data['phone']);
            
            // Auto login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            wp_send_json_success(array('message' => __('Registration successful! Welcome!', 'tutor')));
        }
    }
    
    /**
     * Handle resend OTP
     */
    public function handle_resend_otp() {
        check_ajax_referer('tutor_otp_nonce', 'tutor_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        $otp_type = sanitize_text_field($_POST['otp_type']);
        
        if (!is_email($email) || !in_array($otp_type, array('login', 'register'))) {
            wp_send_json_error(array('message' => __('Invalid resend request.', 'tutor')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'tutor')));
        }
        
        // Generate and store new OTP (temp_data will be preserved automatically)
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, $otp_type)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'tutor')));
        }
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, $otp_type)) {
            wp_send_json_success(array('message' => __('New OTP sent successfully. Please check your email.', 'tutor')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'tutor')));
        }
    }
    
    /**
     * Handle OTP login (send OTP for login)
     */
    public function handle_otp_login() {
        check_ajax_referer('tutor_otp_nonce', 'tutor_otp_nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tutor')));
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(array('message' => __('No user found with that email address.', 'tutor')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'tutor')));
        }
        
        // Generate and store OTP
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, 'login')) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'tutor')));
        }
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, 'login')) {
            wp_send_json_success(array(
                'message' => __('OTP sent successfully. Please check your email.', 'tutor'),
                'email' => $email,
                'otp_type' => 'login'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'tutor')));
        }
    }
    
    /**
     * Handle forgot password
     */
    public function handle_forgot_password() {
        check_ajax_referer('tutor_forgot_nonce', 'tutor_forgot_nonce');
        
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Please enter your email or phone number.', 'tutor')));
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
            wp_send_json_error(array('message' => __('No user found with that email or phone number.', 'tutor')));
        }
        
        // Generate reset key
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            wp_send_json_error(array('message' => $reset_key->get_error_message()));
        }
        
        // Send reset email
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
        $subject = __('Password Reset Request', 'tutor');
        $message = sprintf(
            __('Someone has requested a password reset for the following account:\n\nSite Name: %s\nUsername: %s\n\nIf this was a mistake, just ignore this email and nothing will happen.\n\nTo reset your password, visit the following address:\n\n%s\n\nThis link will expire in 24 hours.', 'tutor'),
            get_bloginfo('name'),
            $user->user_login,
            $reset_url
        );
        
        $sent = wp_mail($user->user_email, $subject, $message);
        
        if ($sent) {
            wp_send_json_success(array('message' => __('Password reset email sent successfully. Please check your email.', 'tutor')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send password reset email. Please try again.', 'tutor')));
        }
    }
    
    /**
     * Handle registration with OTP
     */
    public function handle_register() {
        check_ajax_referer('tutor_register_nonce', 'tutor_register_nonce');
        
        $name = sanitize_text_field($_POST['first_name']); // Note: form uses first_name
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $password = $_POST['password'];
        
        // Validate inputs
        if (empty($name) || empty($email) || empty($phone) || empty($password)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'tutor')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tutor')));
        }
        
        if ($this->user_handler->phone_exists($phone)) {
            wp_send_json_error(array('message' => __('This phone number is already registered.', 'tutor')));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('An account with this email already exists.', 'tutor')));
        }
        
        if (!$this->user_handler->is_valid_phone($phone)) {
            wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'tutor')));
        }
        
        // Check rate limiting
        if ($this->otp_handler->is_rate_limited($email)) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait before requesting another OTP.', 'tutor')));
        }
        
        // Store registration data temporarily and send OTP
        $temp_data = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password
        );
        
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, 'register', $temp_data)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'tutor')));
        }
        
        // Send OTP email
        if ($this->otp_handler->send_otp_email($email, $otp, 'register')) {
            wp_send_json_success(array(
                'message' => __('Registration OTP sent successfully. Please check your email to verify your account.', 'tutor'),
                'email' => $email,
                'otp_type' => 'register',
                'step' => 'verify'
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to send OTP. Please try again.', 'tutor')));
        }
    }
}
