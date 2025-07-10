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
        
        // Handle AJAX requests for phone migration
        add_action('wp_ajax_slr_migrate_phone_numbers', array($this, 'migrate_phone_numbers_to_woocommerce'));

        
        // Add admin notices
        add_action('admin_notices', array($this, 'phone_migration_notice'));
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
            'phone_required',
            __('Phone Number Required', 'smart-login-registration'),
            array($this, 'phone_required_callback'),
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
                    
                    <?php $this->display_migration_tool(); ?>
                    
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
    
    public function phone_required_callback() {
        $options = get_option('slr_settings');
        $value = isset($options['phone_required']) ? $options['phone_required'] : true;
        echo '<input type="checkbox" name="slr_settings[phone_required]" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label>' . __('Make phone number required during registration', 'smart-login-registration') . '</label>';
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
    
    /**
     * Migrate existing phone numbers to WooCommerce fields
     */
    public function migrate_phone_numbers_to_woocommerce() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        check_ajax_referer('slr_migrate_phones', 'nonce');
        
        // Get all users with phone meta
        $users_with_phones = get_users(array(
            'meta_key' => 'phone',
            'meta_compare' => 'EXISTS'
        ));
        
        $updated_count = 0;
        $user_handler = new SLR_User_Handler();
        
        foreach ($users_with_phones as $user) {
            $phone = get_user_meta($user->ID, 'phone', true);
            
            if (!empty($phone)) {
                // Check if fields already exist
                $existing_billing_phone = get_user_meta($user->ID, 'billing_phone', true);
                $existing_tutor_phone = get_user_meta($user->ID, 'phone_number', true);
                
                $needs_update = false;
                
                // Update WooCommerce billing phone if empty
                if (empty($existing_billing_phone)) {
                    update_user_meta($user->ID, 'billing_phone', $phone);
                    $needs_update = true;
                    
                    if (class_exists('WooCommerce')) {
                        update_user_meta($user->ID, 'shipping_phone', $phone);
                    }
                }
                
                // Update Tutor phone_number if empty
                if (empty($existing_tutor_phone)) {
                    update_user_meta($user->ID, 'phone_number', $phone);
                    $needs_update = true;
                }
                
                if ($needs_update) {
                    $updated_count++;
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d user phone numbers migrated successfully to WooCommerce and Tutor LMS.', 'smart-login-registration'), $updated_count),
            'updated_count' => $updated_count
        ));
    }
    
    /**
     * Show admin notice for phone migration if needed
     */
    public function phone_migration_notice() {
        // Only show on users page and plugin settings page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('users', 'settings_page_smart-login-registration'))) {
            return;
        }
        
        // Check if there are users with phone numbers but missing WooCommerce or Tutor fields
        global $wpdb;
        $users_needing_migration = $wpdb->get_var("
            SELECT COUNT(DISTINCT um1.user_id) 
            FROM {$wpdb->usermeta} um1 
            LEFT JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id AND um2.meta_key = 'billing_phone'
            LEFT JOIN {$wpdb->usermeta} um3 ON um1.user_id = um3.user_id AND um3.meta_key = 'phone_number'
            WHERE um1.meta_key = 'phone' 
            AND um1.meta_value != '' 
            AND ((um2.meta_value IS NULL OR um2.meta_value = '') OR (um3.meta_value IS NULL OR um3.meta_value = ''))
        ");
        
        if ($users_needing_migration > 0 && class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php _e('Smart Login & Registration:', 'smart-login-registration'); ?></strong>
                    <?php 
                    printf(
                        __('Found %d users with phone numbers that can be migrated to WooCommerce and Tutor LMS format. <a href="%s">Go to plugin settings</a> to migrate them.', 'smart-login-registration'),
                        $users_needing_migration,
                        admin_url('options-general.php?page=smart-login-registration')
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Display migration tool widget
     */
    private function display_migration_tool() {
        // Check if there are users needing migration
        global $wpdb;
        $users_needing_migration = $wpdb->get_var("
            SELECT COUNT(DISTINCT um1.user_id) 
            FROM {$wpdb->usermeta} um1 
            LEFT JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id AND um2.meta_key = 'billing_phone'
            LEFT JOIN {$wpdb->usermeta} um3 ON um1.user_id = um3.user_id AND um3.meta_key = 'phone_number'
            WHERE um1.meta_key = 'phone' 
            AND um1.meta_value != '' 
            AND ((um2.meta_value IS NULL OR um2.meta_value = '') OR (um3.meta_value IS NULL OR um3.meta_value = ''))
        ");

        if ($users_needing_migration > 0 && class_exists('WooCommerce')) {
            ?>
            <div class="slr-admin-widget">
                <h3><?php _e('Phone Number Migration', 'smart-login-registration'); ?></h3>
                <p><?php printf(__('Found %d users with phone numbers that can be migrated to WooCommerce and Tutor LMS format.', 'smart-login-registration'), $users_needing_migration); ?></p>
                
                <button type="button" id="slr-migrate-phones" class="button button-primary">
                    <?php _e('Migrate Phone Numbers', 'smart-login-registration'); ?>
                </button>
                
                <div id="slr-migration-status" style="margin-top: 10px;"></div>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#slr-migrate-phones').on('click', function() {
                        var button = $(this);
                        var status = $('#slr-migration-status');
                        
                        button.prop('disabled', true).text('<?php _e('Migrating...', 'smart-login-registration'); ?>');
                        status.html('<p style="color: #0073aa;"><?php _e('Migration in progress...', 'smart-login-registration'); ?></p>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'slr_migrate_phones',
                                nonce: '<?php echo wp_create_nonce('slr_migrate_phones'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    status.html('<p style="color: #00a32a;">' + response.data.message + '</p>');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    status.html('<p style="color: #d63638;"><?php _e('Migration failed: ', 'smart-login-registration'); ?>' + response.data.message + '</p>');
                                    button.prop('disabled', false).text('<?php _e('Migrate Phone Numbers', 'smart-login-registration'); ?>');
                                }
                            },
                            error: function() {
                                status.html('<p style="color: #d63638;"><?php _e('Migration failed: Network error', 'smart-login-registration'); ?></p>');
                                button.prop('disabled', false).text('<?php _e('Migrate Phone Numbers', 'smart-login-registration'); ?>');
                            }
                        });
                    });
                });
                </script>
            </div>
            <?php
        }
    }
}
