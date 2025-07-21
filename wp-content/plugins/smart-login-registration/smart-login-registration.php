<?php
/**
 * Plugin Name: Smart Login and Registration
 * Plugin URI: https://github.com/KaziSadibReza/smart-login-registration
 * Description: A beautiful and feature-rich AJAX-powered login/registration popup with OTP email verification. Supports login by email, phone, or username with real-time validation.
 * Version: 1.0.0
 * Author: Kazi Sadib Reza
 * Author URI: https://github.com/KaziSadibReza
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-login-registration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SLR_PLUGIN_FILE', __FILE__);
define('SLR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLR_PLUGIN_VERSION', '1.0.0');
define('SLR_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once SLR_PLUGIN_DIR . 'includes/class-smart-login-registration.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-otp-handler.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-user-handler.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-ajax-handlers.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-admin.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-error-handler.php';

/**
 * Main plugin class initialization
 */
class SmartLoginRegistrationPlugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('smart-login-registration', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize main plugin class
        new SmartLoginRegistration();
        
        // Initialize admin if in admin
        if (is_admin()) {
            new SLR_Admin();
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create OTP table
        $otp_handler = new SLR_OTP_Handler();
        $otp_handler->create_otp_table();
        
        // Set default options
        $default_options = array(
            'otp_expiry' => 10, // minutes
            'rate_limit' => 5,  // requests per hour
            'max_attempts' => 5,
            'email_template' => 'default',
            'phone_validation' => true,
            'phone_required' => true
        );
        
        add_option('slr_settings', $default_options);
        
        // Schedule cleanup cron
        if (!wp_next_scheduled('slr_cleanup_otps')) {
            wp_schedule_event(time(), 'daily', 'slr_cleanup_otps');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('slr_cleanup_otps');
    }
}


// Initialize plugin
SmartLoginRegistrationPlugin::get_instance();