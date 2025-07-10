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
            wp_send_json_error(array('message' => $user->get_error_message()));
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
            
            // Validate phone number
            if (!empty($temp_data['phone']) && !$this->user_handler->is_valid_phone($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'smart-login-registration')));
            }
            
            // Check if phone already exists
            if (!empty($temp_data['phone']) && $this->user_handler->phone_exists($temp_data['phone'])) {
                wp_send_json_error(array('message' => __('This phone number is already registered.', 'smart-login-registration')));
            }
        }
        
        // Generate and store OTP
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, $otp_type, $temp_data)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
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
            wp_send_json_error(array('message' => $verification['message']));
        }
        
        if ($otp_type === 'login') {
            // Log in the user
            $user = get_user_by('email', $email);
            if (!$user) {
                wp_send_json_error(array('message' => __('User not found.', 'smart-login-registration')));
            }
            
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            
            wp_send_json_success(array('message' => __('Login successful! Welcome back!', 'smart-login-registration')));
            
        } else if ($otp_type === 'register') {
            // Create new user with temp data
            $temp_data = $verification['temp_data'];
            if (!$temp_data) {
                wp_send_json_error(array('message' => __('Registration data not found. Please try again.', 'smart-login-registration')));
            }
            
            // Create user
            $user_id = $this->user_handler->create_user($temp_data);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
            }
            
            // Auto login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
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
        
        // Generate and store new OTP (temp_data will be preserved automatically)
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, $otp_type)) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
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
        
        // Generate and store OTP
        $otp = $this->otp_handler->generate_otp();
        
        if (!$this->otp_handler->store_otp($email, $otp, 'login')) {
            wp_send_json_error(array('message' => __('Failed to generate OTP. Please try again.', 'smart-login-registration')));
        }
        
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
            wp_send_json_error(array('message' => $reset_key->get_error_message()));
        }
        
        // Send reset email
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
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
        if (empty($user_data['name']) || empty($user_data['email']) || empty($user_data['password'])) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'smart-login-registration')));
        }
        
        if (!$this->user_handler->is_valid_email($user_data['email'])) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'smart-login-registration')));
        }
        
        if (!empty($user_data['phone']) && !$this->user_handler->is_valid_phone($user_data['phone'])) {
            wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'smart-login-registration')));
        }
        
        if (!empty($user_data['phone']) && $this->user_handler->phone_exists($user_data['phone'])) {
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
        
        if (!$this->otp_handler->store_otp($user_data['email'], $otp, 'register', $user_data)) {
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
}
