<?php
/**
 * Tutor Login Popup Functionality
 * 
 * @package Kadence Child
 * @author Kazi Sadib Reza
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TutorLoginPopup {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('tutor_login_popup', array($this, 'shortcode'));
        add_action('wp_ajax_nopriv_tutor_popup_login', array($this, 'handle_login'));
        add_action('wp_ajax_tutor_popup_login', array($this, 'handle_login'));
        add_action('wp_ajax_nopriv_tutor_popup_register', array($this, 'handle_register'));
        add_action('wp_ajax_tutor_popup_register', array($this, 'handle_register'));
        add_action('wp_ajax_nopriv_tutor_popup_forgot', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_tutor_popup_forgot', array($this, 'handle_forgot_password'));
    }
    
    /**
     * Enqueue styles and scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'tutor-login-popup-style', 
            get_stylesheet_directory_uri() . '/tutor-login-popup.css', 
            array(), 
            '1.0.0'
        );
        
        wp_enqueue_script(
            'tutor-login-popup-script', 
            get_stylesheet_directory_uri() . '/tutor-login-popup.js', 
            array('jquery'), 
            '1.0.0', 
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('tutor-login-popup-script', 'tutor_login_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_nonce' => wp_create_nonce('tutor_login_nonce'),
            'register_nonce' => wp_create_nonce('tutor_register_nonce'),
            'forgot_nonce' => wp_create_nonce('tutor_forgot_nonce')
        ));
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode($atts) {
        // Return empty if user is already logged in
        if (is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'button_text' => __('Login', 'tutor'),
            'button_class' => 'tutor-login-popup-btn',
            'show_register' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
<button class="<?php echo esc_attr($atts['button_class']); ?>" data-action="login">
    <?php echo esc_html($atts['button_text']); ?>
</button>

<!-- Login/Registration Popup Modal -->
<div id="tutor-login-popup-container" class="tutor-popup-container" style="display: none;">
    <div class="tutor-popup-overlay"></div>
    <div class="tutor-popup-modal">
        <div class="tutor-popup-inner">
            <span class="tutor-popup-close">&times;</span>
            <div class="tutor-popup-content">
                <div class="tutor-popup-tabs">
                    <ul class="tutor-popup-tab-nav">
                        <li class="active"><a href="#tutor-login-tab"
                                data-tab="login"><?php _e('Login', 'tutor'); ?></a></li>
                        <?php if ($atts['show_register'] === 'yes' && get_option('users_can_register')) : ?>
                        <li><a href="#tutor-register-tab" data-tab="register"><?php _e('Register', 'tutor'); ?></a></li>
                        <?php endif; ?>
                        <li style="display: none;"><a href="#tutor-forgot-tab"
                                data-tab="forgot"><?php _e('Forgot Password', 'tutor'); ?></a></li>
                    </ul>
                </div>

                <div class="tutor-popup-tab-content">
                    <!-- Login Form -->
                    <div id="tutor-login-tab" class="tutor-tab-pane active">
                        <div class="tutor-popup-form-header">
                            <h3><?php _e('Welcome Back!', 'tutor'); ?></h3>
                            <p><?php _e('Please login to your account', 'tutor'); ?></p>
                        </div>
                        <form id="tutor-popup-login-form" method="post">
                            <?php wp_nonce_field('tutor_login_nonce', 'tutor_login_nonce'); ?>
                            <input type="hidden" name="action" value="tutor_popup_login">

                            <div class="tutor-form-group">
                                <input type="text" name="log"
                                    placeholder="<?php _e('Email, Phone or Username', 'tutor'); ?>" required>
                            </div>

                            <div class="tutor-form-group">
                                <input type="password" name="pwd" placeholder="<?php _e('Password', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group tutor-checkbox-group">
                                <label>
                                    <input type="checkbox" name="rememberme" value="forever">
                                    <?php _e('Remember Me', 'tutor'); ?>
                                </label>
                                <a href="#" class="tutor-forgot-password" data-tab="forgot">
                                    <?php _e('Forgot Password?', 'tutor'); ?>
                                </a>
                            </div>

                            <div class="tutor-form-group">
                                <button type="submit" class="tutor-popup-btn tutor-btn-primary">
                                    <?php _e('Login', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-login-response"></div>
                        </form>
                    </div>

                    <!-- Registration Form -->
                    <?php if ($atts['show_register'] === 'yes' && get_option('users_can_register')) : ?>
                    <div id="tutor-register-tab" class="tutor-tab-pane">
                        <div class="tutor-popup-form-header">
                            <h3><?php _e('Create Account', 'tutor'); ?></h3>
                            <p><?php _e('Join us and start learning!', 'tutor'); ?></p>
                        </div>
                        <form id="tutor-popup-register-form" method="post">
                            <?php wp_nonce_field('tutor_register_nonce', 'tutor_register_nonce'); ?>
                            <input type="hidden" name="action" value="tutor_popup_register">

                            <div class="tutor-form-group">
                                <input type="text" name="first_name" placeholder="<?php _e('Name', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group">
                                <input type="email" name="email" placeholder="<?php _e('Email Address', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group">
                                <input type="tel" name="phone" placeholder="<?php _e('Phone Number', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group">
                                <input type="password" name="password" placeholder="<?php _e('Password', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group">
                                <button type="submit" class="tutor-popup-btn tutor-btn-primary">
                                    <?php _e('Register', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-register-response"></div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Forgot Password Form -->
                    <div id="tutor-forgot-tab" class="tutor-tab-pane">
                        <div class="tutor-popup-form-header">
                            <h3><?php _e('Reset Password', 'tutor'); ?></h3>
                            <p><?php _e('Enter your email or phone number to reset your password', 'tutor'); ?></p>
                        </div>
                        <form id="tutor-popup-forgot-form" method="post">
                            <?php wp_nonce_field('tutor_forgot_nonce', 'tutor_forgot_nonce'); ?>
                            <input type="hidden" name="action" value="tutor_popup_forgot">

                            <div class="tutor-form-group">
                                <input type="text" name="user_login"
                                    placeholder="<?php _e('Email or Phone Number', 'tutor'); ?>" required>
                            </div>

                            <div class="tutor-form-group">
                                <button type="submit" class="tutor-popup-btn tutor-btn-primary">
                                    <?php _e('Reset Password', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-forgot-response"></div>

                            <div class="tutor-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="tutor-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'tutor'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
        return ob_get_clean();
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
        if ($this->is_phone_number($username)) {
            $user = $this->get_user_by_phone($username);
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
     * Handle AJAX registration
     */
    public function handle_register() {
        check_ajax_referer('tutor_register_nonce', 'tutor_register_nonce');
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $password = sanitize_text_field($_POST['password']);
        
        // Validation
        if (empty($first_name) || empty($email) || empty($phone) || empty($password)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'tutor')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'tutor')));
        }
        
        if (!$this->is_valid_phone($phone)) {
            wp_send_json_error(array('message' => __('Please enter a valid phone number.', 'tutor')));
        }
        
        if (username_exists($email) || email_exists($email)) {
            wp_send_json_error(array('message' => __('This email is already registered.', 'tutor')));
        }
        
        // Check if phone number already exists
        if ($this->phone_exists($phone)) {
            wp_send_json_error(array('message' => __('This phone number is already registered.', 'tutor')));
        }
        
        // Create user
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        } else {
            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'display_name' => $first_name
            ));
            
            // Save phone number
            update_user_meta($user_id, 'phone', $phone);
            update_user_meta($user_id, 'billing_phone', $phone); // For WooCommerce compatibility
            
            // Auto login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            wp_send_json_success(array('message' => __('Registration successful! Welcome!', 'tutor')));
        }
    }
    
    /**
     * Handle AJAX forgot password
     */
    public function handle_forgot_password() {
        check_ajax_referer('tutor_forgot_nonce', 'tutor_forgot_nonce');
        
        $user_login = sanitize_text_field($_POST['user_login']);
        
        if (empty($user_login)) {
            wp_send_json_error(array('message' => __('Please enter your email or phone number.', 'tutor')));
        }
        
        // Check if it's a phone number
        $user = null;
        if ($this->is_phone_number($user_login)) {
            $user = $this->get_user_by_phone($user_login);
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
     * Check if string is a phone number
     */
    private function is_phone_number($string) {
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
    private function is_valid_phone($phone) {
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
    private function get_user_by_phone($phone) {
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
    private function phone_exists($phone) {
        $user = $this->get_user_by_phone($phone);
        return $user !== null;
    }
}

// Initialize the class
new TutorLoginPopup();