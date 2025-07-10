<?php
/**
 * Smart Login and Registration - Admin Class
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . SLR_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Smart Login & Registration Settings', 'smart-login-registration'),
            __('Smart Login & Registration', 'smart-login-registration'),
            'manage_options',
            'smart-login-registration',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('slr_settings_group', 'slr_settings');
        
        add_settings_section(
            'slr_general_section',
            __('General Settings', 'smart-login-registration'),
            array($this, 'general_section_callback'),
            'slr_settings'
        );
        
        add_settings_section(
            'slr_otp_section',
            __('OTP Settings', 'smart-login-registration'),
            array($this, 'otp_section_callback'),
            'slr_settings'
        );
        
        add_settings_section(
            'slr_security_section',
            __('Security Settings', 'smart-login-registration'),
            array($this, 'security_section_callback'),
            'slr_settings'
        );
        
        // General settings fields
        add_settings_field(
            'phone_validation',
            __('Phone Number Validation', 'smart-login-registration'),
            array($this, 'phone_validation_callback'),
            'slr_settings',
            'slr_general_section'
        );
        
        add_settings_field(
            'enable_debug',
            __('Enable Debug Logging', 'smart-login-registration'),
            array($this, 'enable_debug_callback'),
            'slr_settings',
            'slr_general_section'
        );
        
        // OTP settings fields
        add_settings_field(
            'otp_expiry',
            __('OTP Expiry Time (minutes)', 'smart-login-registration'),
            array($this, 'otp_expiry_callback'),
            'slr_settings',
            'slr_otp_section'
        );
        
        add_settings_field(
            'email_template',
            __('Email Template Style', 'smart-login-registration'),
            array($this, 'email_template_callback'),
            'slr_settings',
            'slr_otp_section'
        );
        
        // Security settings fields
        add_settings_field(
            'rate_limit',
            __('Rate Limit (requests per hour)', 'smart-login-registration'),
            array($this, 'rate_limit_callback'),
            'slr_settings',
            'slr_security_section'
        );
        
        add_settings_field(
            'max_attempts',
            __('Max OTP Attempts', 'smart-login-registration'),
            array($this, 'max_attempts_callback'),
            'slr_settings',
            'slr_security_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_smart-login-registration' !== $hook) {
            return;
        }
        
        wp_enqueue_style('slr-admin-style', SLR_PLUGIN_URL . 'assets/css/admin-style.css', array(), SLR_PLUGIN_VERSION);
        wp_enqueue_script('slr-admin-script', SLR_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), SLR_PLUGIN_VERSION, true);
    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=smart-login-registration') . '">' . __('Settings', 'smart-login-registration') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Admin page template
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="slr-admin-header">
                <h2><?php _e('Smart Login & Registration', 'smart-login-registration'); ?></h2>
                <p><?php _e('Configure your login and registration popup settings.', 'smart-login-registration'); ?></p>
            </div>
            
            <div class="slr-admin-content">
                <div class="slr-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('slr_settings_group');
                        do_settings_sections('slr_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="slr-admin-sidebar">
                    <div class="slr-admin-widget">
                        <h3><?php _e('Quick Start', 'smart-login-registration'); ?></h3>
                        <p><?php _e('Use these shortcodes to display the login popup:', 'smart-login-registration'); ?></p>
                        <code>[smart_login_popup]</code>
                        <br><br>
                        <code>[slr_login_popup button_text="Sign In" show_register="yes"]</code>
                        
                        <h4><?php _e('Shortcode Parameters:', 'smart-login-registration'); ?></h4>
                        <ul>
                            <li><strong>button_text:</strong> <?php _e('Text for the login button', 'smart-login-registration'); ?></li>
                            <li><strong>button_class:</strong> <?php _e('CSS class for the button', 'smart-login-registration'); ?></li>
                            <li><strong>show_register:</strong> <?php _e('Show registration tab (yes/no)', 'smart-login-registration'); ?></li>
                            <li><strong>show_when_logged_in:</strong> <?php _e('Show when user is logged in (true/false)', 'smart-login-registration'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="slr-admin-widget">
                        <h3><?php _e('Statistics', 'smart-login-registration'); ?></h3>
                        <?php $this->display_stats(); ?>
                    </div>
                    
                    <div class="slr-admin-widget">
                        <h3><?php _e('Support', 'smart-login-registration'); ?></h3>
                        <p><?php _e('Need help? Contact the developer:', 'smart-login-registration'); ?></p>
                        <p><a href="https://github.com/KaziSadibReza" target="_blank"><?php _e('GitHub Profile', 'smart-login-registration'); ?></a></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display OTP statistics
     */
    private function display_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'slr_otp';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            echo '<p>' . __('OTP table not found.', 'smart-login-registration') . '</p>';
            return;
        }
        
        $today_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = CURDATE()");
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $expired_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE expires_at < NOW()");
        
        echo '<ul>';
        echo '<li>' . sprintf(__('OTPs sent today: %d', 'smart-login-registration'), $today_count) . '</li>';
        echo '<li>' . sprintf(__('Total OTPs sent: %d', 'smart-login-registration'), $total_count) . '</li>';
        echo '<li>' . sprintf(__('Expired OTPs: %d', 'smart-login-registration'), $expired_count) . '</li>';
        echo '</ul>';
        
        if ($expired_count > 0) {
            echo '<p><a href="' . admin_url('admin.php?page=smart-login-registration&cleanup=1') . '" class="button button-secondary">' . __('Clean Up Expired OTPs', 'smart-login-registration') . '</a></p>';
        }
        
        // Handle cleanup
        if (isset($_GET['cleanup']) && $_GET['cleanup'] == '1') {
            $deleted = $wpdb->query("DELETE FROM {$table_name} WHERE expires_at < NOW()");
            echo '<div class="notice notice-success"><p>' . sprintf(__('Cleaned up %d expired OTPs.', 'smart-login-registration'), $deleted) . '</p></div>';
        }
    }
    
    // Section callbacks
    public function general_section_callback() {
        echo '<p>' . __('General plugin settings.', 'smart-login-registration') . '</p>';
    }
    
    public function otp_section_callback() {
        echo '<p>' . __('Configure OTP (One-Time Password) settings.', 'smart-login-registration') . '</p>';
    }
    
    public function security_section_callback() {
        echo '<p>' . __('Security and rate limiting settings.', 'smart-login-registration') . '</p>';
    }
    
    // Field callbacks
    public function phone_validation_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['phone_validation']) ? $options['phone_validation'] : true;
        echo '<input type="checkbox" name="slr_settings[phone_validation]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label>' . __('Enable phone number validation', 'smart-login-registration') . '</label>';
    }
    
    public function enable_debug_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['enable_debug']) ? $options['enable_debug'] : false;
        echo '<input type="checkbox" name="slr_settings[enable_debug]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label>' . __('Enable debug logging for troubleshooting', 'smart-login-registration') . '</label>';
    }
    
    public function otp_expiry_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['otp_expiry']) ? $options['otp_expiry'] : 10;
        echo '<input type="number" name="slr_settings[otp_expiry]" value="' . esc_attr($value) . '" min="1" max="60" />';
        echo '<p class="description">' . __('How long OTP codes remain valid (1-60 minutes).', 'smart-login-registration') . '</p>';
    }
    
    public function email_template_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['email_template']) ? $options['email_template'] : 'default';
        echo '<select name="slr_settings[email_template]">';
        echo '<option value="default" ' . selected('default', $value, false) . '>' . __('Default', 'smart-login-registration') . '</option>';
        echo '<option value="minimal" ' . selected('minimal', $value, false) . '>' . __('Minimal', 'smart-login-registration') . '</option>';
        echo '<option value="modern" ' . selected('modern', $value, false) . '>' . __('Modern', 'smart-login-registration') . '</option>';
        echo '</select>';
    }
    
    public function rate_limit_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['rate_limit']) ? $options['rate_limit'] : 5;
        echo '<input type="number" name="slr_settings[rate_limit]" value="' . esc_attr($value) . '" min="1" max="20" />';
        echo '<p class="description">' . __('Maximum OTP requests per hour per email address.', 'smart-login-registration') . '</p>';
    }
    
    public function max_attempts_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['max_attempts']) ? $options['max_attempts'] : 5;
        echo '<input type="number" name="slr_settings[max_attempts]" value="' . esc_attr($value) . '" min="3" max="10" />';
        echo '<p class="description">' . __('Maximum failed OTP verification attempts before blocking.', 'smart-login-registration') . '</p>';
    }
}
