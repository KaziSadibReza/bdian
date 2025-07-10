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

// Include required files
require_once __DIR__ . '/tutor-otp-handler.php';
require_once __DIR__ . '/tutor-user-handler.php';
require_once __DIR__ . '/tutor-ajax-handlers.php';

class TutorLoginPopup {
    
    private $otp_handler;
    private $ajax_handlers;
    
    public function __construct() {
        $this->otp_handler = new TutorOtpHandler();
        $this->ajax_handlers = new TutorAjaxHandlers();
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('tutor_login_popup', array($this, 'shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_nopriv_tutor_popup_login', array($this->ajax_handlers, 'handle_login'));
        add_action('wp_ajax_tutor_popup_login', array($this->ajax_handlers, 'handle_login'));
        add_action('wp_ajax_nopriv_tutor_popup_register', array($this->ajax_handlers, 'handle_register'));
        add_action('wp_ajax_tutor_popup_register', array($this->ajax_handlers, 'handle_register'));
        add_action('wp_ajax_nopriv_tutor_popup_forgot', array($this->ajax_handlers, 'handle_forgot_password'));
        add_action('wp_ajax_tutor_popup_forgot', array($this->ajax_handlers, 'handle_forgot_password'));
        
        // OTP related AJAX handlers
        add_action('wp_ajax_nopriv_tutor_send_otp', array($this->ajax_handlers, 'handle_send_otp'));
        add_action('wp_ajax_tutor_send_otp', array($this->ajax_handlers, 'handle_send_otp'));
        add_action('wp_ajax_nopriv_tutor_verify_otp', array($this->ajax_handlers, 'handle_verify_otp'));
        add_action('wp_ajax_tutor_verify_otp', array($this->ajax_handlers, 'handle_verify_otp'));
        add_action('wp_ajax_nopriv_tutor_resend_otp', array($this->ajax_handlers, 'handle_resend_otp'));
        add_action('wp_ajax_tutor_resend_otp', array($this->ajax_handlers, 'handle_resend_otp'));
        add_action('wp_ajax_nopriv_tutor_otp_login', array($this->ajax_handlers, 'handle_otp_login'));
        add_action('wp_ajax_tutor_otp_login', array($this->ajax_handlers, 'handle_otp_login'));
        
        // Create OTP table on activation
        add_action('after_switch_theme', array($this->otp_handler, 'create_otp_table'));
        
        // Also create table when class is initialized (backup)
        add_action('init', array($this->otp_handler, 'maybe_create_otp_table'));
        
        // Clean up expired OTPs daily
        if (!wp_next_scheduled('tutor_cleanup_otps')) {
            wp_schedule_event(time(), 'daily', 'tutor_cleanup_otps');
        }
        add_action('tutor_cleanup_otps', array($this->otp_handler, 'cleanup_expired_otps'));
        
        // Clean up cron job when theme is switched
        add_action('switch_theme', array($this, 'cleanup_cron_jobs'));
    }
    
    /**
     * Clean up scheduled cron jobs
     */
    public function cleanup_cron_jobs() {
        wp_clear_scheduled_hook('tutor_cleanup_otps');
    }
    
    /**
     * Enqueue styles and scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'tutor-login-popup-style', 
            get_stylesheet_directory_uri() . '/tutor-login-popup.css', 
            array(), 
            '1.0.1'
        );
        
        wp_enqueue_script(
            'tutor-login-popup-script', 
            get_stylesheet_directory_uri() . '/tutor-login-popup.js', 
            array('jquery'), 
            '1.0.1', 
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('tutor-login-popup-script', 'tutor_login_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_nonce' => wp_create_nonce('tutor_login_nonce'),
            'register_nonce' => wp_create_nonce('tutor_register_nonce'),
            'forgot_nonce' => wp_create_nonce('tutor_forgot_nonce'),
            'otp_nonce' => wp_create_nonce('tutor_otp_nonce'),
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

                            <div class="tutor-form-group tutor-login-divider">
                                <span><?php _e('OR', 'tutor'); ?></span>
                            </div>

                            <div class="tutor-form-group">
                                <button type="button" class="tutor-popup-btn tutor-btn-secondary tutor-otp-login-btn">
                                    <?php _e('Login with OTP', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-login-response"></div>
                        </form>
                    </div>

                    <!-- OTP Login Form -->
                    <div id="tutor-otp-login-tab" class="tutor-tab-pane">
                        <div class="tutor-popup-form-header">
                            <h3><?php _e('Login with OTP', 'tutor'); ?></h3>
                            <p><?php _e('Enter your email to receive a one-time password', 'tutor'); ?></p>
                        </div>
                        <form id="tutor-popup-otp-login-form" method="post">
                            <?php wp_nonce_field('tutor_otp_nonce', 'tutor_otp_nonce'); ?>
                            <input type="hidden" name="action" value="tutor_otp_login">

                            <div class="tutor-form-group">
                                <input type="email" name="email" placeholder="<?php _e('Email Address', 'tutor'); ?>"
                                    required>
                            </div>

                            <div class="tutor-form-group">
                                <button type="submit" class="tutor-popup-btn tutor-btn-primary">
                                    <?php _e('Send OTP', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="tutor-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'tutor'); ?>
                                </a>
                            </div>

                            <div class="tutor-otp-login-response"></div>
                        </form>
                    </div>

                    <!-- OTP Verification Form -->
                    <div id="tutor-otp-verify-tab" class="tutor-tab-pane">
                        <div class="tutor-popup-form-header">
                            <h3><?php _e('Enter OTP Code', 'tutor'); ?></h3>
                            <p><?php _e('We\'ve sent a 6-digit code to your email', 'tutor'); ?></p>
                        </div>
                        <form id="tutor-popup-otp-verify-form" method="post">
                            <?php wp_nonce_field('tutor_otp_nonce', 'tutor_otp_nonce'); ?>
                            <input type="hidden" name="action" value="tutor_verify_otp">
                            <input type="hidden" name="email" value="">
                            <input type="hidden" name="otp_type" value="">

                            <div class="tutor-form-group">
                                <div class="tutor-otp-input-group">
                                    <input type="text" name="otp" placeholder="<?php _e('000000', 'tutor'); ?>"
                                        maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                                </div>
                            </div>

                            <div class="tutor-form-group">
                                <button type="submit" class="tutor-popup-btn tutor-btn-primary">
                                    <?php _e('Verify OTP', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-form-group tutor-otp-resend">
                                <p><?php _e('Didn\'t receive the code?', 'tutor'); ?></p>
                                <button type="button" class="tutor-resend-otp-btn" disabled>
                                    <?php _e('Resend OTP (60s)', 'tutor'); ?>
                                </button>
                            </div>

                            <div class="tutor-form-group" style="text-align: center; margin-top: 20px;">
                                <a href="#" class="tutor-back-to-login" data-tab="login">
                                    <?php _e('← Back to Login', 'tutor'); ?>
                                </a>
                            </div>

                            <div class="tutor-otp-verify-response"></div>
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
                                    <?php _e('Send OTP & Register', 'tutor'); ?>
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
}

// Initialize the class
new TutorLoginPopup();