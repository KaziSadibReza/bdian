<?php
/**
 * Smart Login and Registration - Main Class
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SmartLoginRegistration {
    
    private $otp_handler;
    private $ajax_handlers;
    private $user_handler;
    private $error_handler;
    private $settings;
    
    public function __construct() {
        $this->otp_handler = new SLR_OTP_Handler();
        $this->ajax_handlers = new SLR_AJAX_Handlers();
        $this->user_handler = new SLR_User_Handler();
        $this->error_handler = new SLR_Error_Handler();
        $this->settings = get_option('slr_settings', array());
        
        $this->add_core_hooks();
    }
    
    /**
     * Add core plugin hooks (non-login/registration specific)
     */
    private function add_core_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('smart_login_popup', array($this, 'shortcode'));
        add_shortcode('slr_login_popup', array($this, 'shortcode')); // Alternative shortcode
        
        // AJAX handlers for logged out users
        add_action('wp_ajax_nopriv_slr_login', array($this->ajax_handlers, 'handle_login'));
        add_action('wp_ajax_nopriv_slr_register', array($this->ajax_handlers, 'handle_register'));
        add_action('wp_ajax_nopriv_slr_send_otp', array($this->ajax_handlers, 'handle_send_otp'));
        add_action('wp_ajax_nopriv_slr_verify_otp', array($this->ajax_handlers, 'handle_verify_otp'));
        add_action('wp_ajax_nopriv_slr_resend_otp', array($this->ajax_handlers, 'handle_resend_otp'));
        add_action('wp_ajax_nopriv_slr_otp_login', array($this->ajax_handlers, 'handle_otp_login'));
        
        // New OTP-based password reset handlers
        add_action('wp_ajax_nopriv_slr_send_reset_otp', array($this->ajax_handlers, 'handle_send_reset_otp'));
        add_action('wp_ajax_nopriv_slr_verify_reset_otp', array($this->ajax_handlers, 'handle_verify_reset_otp'));
        add_action('wp_ajax_nopriv_slr_reset_password', array($this->ajax_handlers, 'handle_reset_password'));
        
        // Create OTP table on init
        add_action('init', array($this->otp_handler, 'maybe_create_otp_table'));
        
        // Schedule cleanup
        add_action('slr_cleanup_otps', array($this->otp_handler, 'cleanup_expired_otps'));
        
        // Phone number synchronization hooks
        add_action('user_register', array($this, 'sync_new_user_phone'));
        add_action('profile_update', array($this, 'sync_profile_phone'), 10, 2);
        
        // WooCommerce integration hooks
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_customer_save_address', array($this, 'sync_woocommerce_phone'), 10, 2);
            add_filter('woocommerce_checkout_fields', array($this, 'populate_woocommerce_phone_field'));
        }
        
        // Tutor LMS integration hooks
        if (function_exists('tutor') || class_exists('TUTOR\Tutor')) {
            add_action('tutor_profile_update_after', array($this, 'sync_tutor_phone'));
            add_filter('tutor_user_profile_fields', array($this, 'populate_tutor_phone_field'));
        }
        
        // Clean up cron job when plugin is deactivated
        register_deactivation_hook(SLR_PLUGIN_FILE, array($this, 'cleanup_cron_jobs'));
    }
    
    /**
     * Enqueue styles and scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'slr-popup-style', 
            SLR_PLUGIN_URL . 'assets/css/popup-style.css', 
            array(), 
            SLR_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'slr-popup-script', 
            SLR_PLUGIN_URL . 'assets/js/popup-script.js', 
            array('jquery'), 
            SLR_PLUGIN_VERSION, 
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('slr-popup-script', 'slr_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'login_nonce' => wp_create_nonce('slr_login_nonce'),
            'register_nonce' => wp_create_nonce('slr_register_nonce'),
            'forgot_nonce' => wp_create_nonce('slr_forgot_nonce'),
            'otp_nonce' => wp_create_nonce('slr_otp_nonce'),
            'settings' => $this->settings
        ));
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode($atts) {
        // Return empty if user is already logged in and hide_when_logged_in is true
        if (is_user_logged_in() && (!isset($atts['show_when_logged_in']) || $atts['show_when_logged_in'] !== 'true')) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'button_text' => __('Login', 'smart-login-registration'),
            'button_class' => 'slr-login-popup-btn',
            'show_register' => 'yes',
            'show_when_logged_in' => 'false'
        ), $atts);
        
        ob_start();
        include SLR_PLUGIN_DIR . 'templates/popup-template.php';
        return ob_get_clean();
    }
    
    /**
     * Clean up scheduled cron jobs
     */
    public function cleanup_cron_jobs() {
        wp_clear_scheduled_hook('slr_cleanup_otps');
    }
    
    /**
     * Sync phone number for newly registered users
     */
    public function sync_new_user_phone($user_id) {
        $phone = get_user_meta($user_id, 'phone', true);
        if ($phone) {
            $this->user_handler->sync_phone_fields($user_id, $phone);
        }
    }

    /**
     * Sync phone number when profile is updated
     */
    public function sync_profile_phone($user_id, $old_user_data) {
        $phone = get_user_meta($user_id, 'phone', true);
        if ($phone) {
            $this->user_handler->sync_phone_fields($user_id, $phone);
        }
    }

    /**
     * Sync phone number when WooCommerce address is saved
     */
    public function sync_woocommerce_phone($user_id, $load_address) {
        if ($load_address === 'billing') {
            $phone = get_user_meta($user_id, 'billing_phone', true);
            if ($phone) {
                // Update our main phone field
                update_user_meta($user_id, 'phone', $phone);
                $this->user_handler->sync_phone_fields($user_id, $phone);
            }
        }
    }

    /**
     * Sync phone when Tutor profile is updated
     */
    public function sync_tutor_phone($user_id) {
        if (isset($_POST['phone'])) {
            $phone = sanitize_text_field($_POST['phone']);
            if ($phone) {
                $this->user_handler->sync_phone_fields($user_id, $phone);
            }
        }
    }

    /**
     * Populate WooCommerce phone field with our phone data
     */
    public function populate_woocommerce_phone_field($fields) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $phone = get_user_meta($user_id, 'phone', true);
            if ($phone) {
                if (isset($fields['billing']['billing_phone'])) {
                    $fields['billing']['billing_phone']['default'] = $phone;
                }
                if (isset($fields['shipping']['shipping_phone'])) {
                    $fields['shipping']['shipping_phone']['default'] = $phone;
                }
            }
        }
        return $fields;
    }

    /**
     * Populate Tutor LMS phone field with our phone data
     */
    public function populate_tutor_phone_field($fields) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $phone = get_user_meta($user_id, 'phone', true);
            if ($phone && isset($fields['phone'])) {
                $fields['phone']['value'] = $phone;
            }
        }
        return $fields;
    }
}