<?php
/**
 * Smart Login and Registration - User Handler
 * 
 * @package SmartLoginRegistration
 * @author Kazi Sadib Reza
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SLR_User_Handler {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('slr_settings', array());
        
        // Migrate existing phone data to Tutor field if needed
        add_action('admin_init', array($this, 'migrate_phone_to_tutor_field'));
        
        // Add test utility hook
        add_action('admin_init', array($this, 'add_test_phone_utility'));
        
        // Add hooks for user profile phone field display
        if (is_admin()) {
            $this->add_phone_field_to_user_profile();
            $this->add_phone_column_to_users_table();
        }
        
        // Add Tutor LMS integration hooks
        if (function_exists('tutor') || class_exists('TUTOR\Tutor')) {
            $this->add_phone_field_to_tutor_profile();
        }
    }
    
    /**
     * Check if string is a phone number
     */
    public function is_phone_number($string) {
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
    public function is_valid_phone($phone) {
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
    public function get_user_by_phone($phone) {
        // First check WooCommerce billing_phone (prioritize WooCommerce integration)
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone,
            'number' => 1
        ));
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // Then check plugin's custom phone field
        $users = get_users(array(
            'meta_key' => 'phone',
            'meta_value' => $phone,
            'number' => 1
        ));
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // Check user_login field for phone numbers
        $users = get_users(array(
            'search' => $phone,
            'search_columns' => array('user_login'),
            'number' => 1
        ));

        return !empty($users) ? $users[0] : null;
    }
    
    /**
     * Check if phone number already exists
     */
    public function phone_exists($phone) {
        $user = $this->get_user_by_phone($phone);
        return $user !== null;
    }
    
    /**
     * Validate email format
     */
    public function is_valid_email($email) {
        return is_email($email);
    }
    
    /**
     * Create user with phone number
     */
    public function create_user($user_data) {
        // Create user
        $user_id = wp_create_user($user_data['email'], $user_data['password'], $user_data['email']);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $user_data['name'],
            'first_name' => $user_data['name']
        ));
        
        // Store phone number in all relevant fields
        if (!empty($user_data['phone'])) {
            // Store in plugin's custom meta field
            update_user_meta($user_id, 'phone', $user_data['phone']);
            
            // Store in WooCommerce billing phone field
            update_user_meta($user_id, 'billing_phone', $user_data['phone']);
            
            // Also store in shipping phone if WooCommerce is active
            if (class_exists('WooCommerce')) {
                update_user_meta($user_id, 'shipping_phone', $user_data['phone']);
            }
        }
        
        // Update WooCommerce customer profile if WooCommerce is active
        $this->update_woocommerce_customer($user_id, $user_data);
        
        return $user_id;
    }
    
    /**
     * Sanitize user input
     */
    public function sanitize_user_data($data) {
        $sanitized = array();
        
        if (isset($data['name'])) {
            $sanitized['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }
        
        if (isset($data['phone'])) {
            $sanitized['phone'] = sanitize_text_field($data['phone']);
        }
        
        if (isset($data['password'])) {
            $sanitized['password'] = $data['password']; // Don't sanitize password
        }
        
        return $sanitized;
    }
    
    /**
     * Format phone number for display
     */
    public function format_phone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Format Bangladesh phone numbers
        if (preg_match('/^01[0-9]{9}$/', $cleaned)) {
            return '+880' . substr($cleaned, 1);
        }
        
        return $phone;
    }
    
    /**
     * Update WooCommerce customer data
     */
    public function update_woocommerce_customer($user_id, $user_data) {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Update WooCommerce billing information
        if (!empty($user_data['name'])) {
            $name_parts = explode(' ', $user_data['name'], 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            update_user_meta($user_id, 'billing_first_name', $first_name);
            update_user_meta($user_id, 'billing_last_name', $last_name);
            update_user_meta($user_id, 'shipping_first_name', $first_name);
            update_user_meta($user_id, 'shipping_last_name', $last_name);
        }
        
        if (!empty($user_data['email'])) {
            update_user_meta($user_id, 'billing_email', $user_data['email']);
        }
        
        if (!empty($user_data['phone'])) {
            update_user_meta($user_id, 'billing_phone', $user_data['phone']);
            update_user_meta($user_id, 'shipping_phone', $user_data['phone']);
        }
    }
    
    /**
     * Get user's phone number (checks both WooCommerce and plugin fields)
     */
    public function get_user_phone($user_id) {
        // First try WooCommerce billing phone
        $phone = get_user_meta($user_id, 'billing_phone', true);
        
        if (!empty($phone)) {
            return $phone;
        }
        
        // Try Tutor's official phone field (following their docs)
        $phone = get_user_meta($user_id, 'phone_number', true);
        
        if (!empty($phone)) {
            return $phone;
        }
        
        // Try Tutor's own phone field (our custom)
        $phone = get_user_meta($user_id, '_tutor_profile_phone', true);
        
        if (!empty($phone)) {
            return $phone;
        }
        
        // Fallback to plugin's phone field
        $phone = get_user_meta($user_id, 'phone', true);
        
        return $phone;
    }
    
    /**
     * Add phone number field to user profile pages
     */
    public function add_phone_field_to_user_profile() {
        // Use only one hook to avoid duplication
        // edit_user_profile is used for editing other users (when admin edits a user)
        // show_user_profile is used when users edit their own profile
        // But since edit_user_profile also covers the admin editing scenario, let's use a simpler approach
        add_action('edit_user_profile', array($this, 'show_phone_field_in_profile'), 10);
        add_action('show_user_profile', array($this, 'show_phone_field_in_profile'), 10);
        add_action('personal_options_update', array($this, 'save_phone_field_in_profile'));
        add_action('edit_user_profile_update', array($this, 'save_phone_field_in_profile'));
    }
    
    /**
     * Display phone number field in user profile
     */
    public function show_phone_field_in_profile($user) {
        // Simple deduplication using a global flag
        if (isset($GLOBALS['slr_phone_field_displayed'])) {
            return;
        }
        $GLOBALS['slr_phone_field_displayed'] = true;
        
        $phone = $this->get_user_phone($user->ID);
        $plugin_phone = get_user_meta($user->ID, 'phone', true);
        $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
        $shipping_phone = get_user_meta($user->ID, 'shipping_phone', true);
        ?>
<h3><?php _e('Contact Information', 'smart-login-registration'); ?></h3>
<table class="form-table">
    <tr>
        <th><label for="phone"><?php _e('Phone Number', 'smart-login-registration'); ?></label></th>
        <td>
            <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" class="regular-text" />
            <p class="description">
                <?php _e('Enter your phone number for contact purposes.', 'smart-login-registration'); ?></p>
        </td>
    </tr>

</table>
<?php
    }
    
    /**
     * Save phone number field from user profile
     */
    public function save_phone_field_in_profile($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['phone'])) {
            $phone = sanitize_text_field($_POST['phone']);
            
            // Update all phone fields
            update_user_meta($user_id, 'phone', $phone);
            update_user_meta($user_id, 'billing_phone', $phone);
            
            if (class_exists('WooCommerce')) {
                update_user_meta($user_id, 'shipping_phone', $phone);
            }
        }
    }
    
    /**
     * Add phone number column to users list table
     */
    public function add_phone_column_to_users_table() {
        add_filter('manage_users_columns', array($this, 'add_phone_column'));
        add_filter('manage_users_custom_column', array($this, 'show_phone_column_content'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this, 'make_phone_column_sortable'));
    }
    
    /**
     * Add phone column to users table
     */
    public function add_phone_column($columns) {
        $columns['phone'] = __('Phone Number', 'smart-login-registration');
        return $columns;
    }
    
    /**
     * Show phone number in users table column
     */
    public function show_phone_column_content($value, $column_name, $user_id) {
        if ($column_name == 'phone') {
            $phone = $this->get_user_phone($user_id);
            return $phone ? esc_html($phone) : '—';
        }
        return $value;
    }
    
    /**
     * Make phone column sortable
     */
    public function make_phone_column_sortable($columns) {
        $columns['phone'] = 'phone';
        return $columns;
    }
    
    /**
     * Add phone number field to Tutor LMS profile pages
     */
    public function add_phone_field_to_tutor_profile() {
        // Hook into Tutor LMS profile save action
        add_action('tutor_profile_update_after', array($this, 'sync_tutor_phone_to_other_fields'), 10, 1);
        
        // Hook into user registration and profile updates
        add_action('user_register', array($this, 'tutor_save_phone_after_register'), 10);
        add_action('profile_update', array($this, 'tutor_save_phone_after_register'), 10);
        
        // Add Tutor LMS registration field requirements
        add_filter('tutor_student_registration_required_fields', array($this, 'tutor_required_phone_fields'));
        add_filter('tutor_instructor_registration_required_fields', array($this, 'tutor_required_phone_fields'));
        
        // Add phone field to registration forms
        add_action('tutor_student_registration_form_fields', array($this, 'add_phone_to_tutor_registration'));
        add_action('tutor_instructor_registration_form_fields', array($this, 'add_phone_to_tutor_registration'));
        
        // Add custom CSS for phone field styling
        add_action('wp_head', array($this, 'add_tutor_phone_field_styles'));
    }
    
    /**
     * Sync phone number from Tutor LMS to other platform fields
     */
    public function sync_tutor_phone_to_other_fields($user_id) {
        // Get the phone number from Tutor's field
        $phone_number = get_user_meta($user_id, 'phone_number', true);
        
        if (!empty($phone_number) && $this->is_valid_phone($phone_number)) {
            // Sync to other platform fields
            update_user_meta($user_id, 'phone', $phone_number);
            update_user_meta($user_id, 'billing_phone', $phone_number);
            update_user_meta($user_id, '_tutor_profile_phone', $phone_number);
            
            if (class_exists('WooCommerce')) {
                update_user_meta($user_id, 'shipping_phone', $phone_number);
            }
            
            // Update WooCommerce customer data
            $user = get_userdata($user_id);
            if ($user) {
                $user_data = array(
                    'phone' => $phone_number,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->user_email
                );
                $this->update_woocommerce_customer($user_id, $user_data);
            }
        }
    }
    
    /**
     * Add phone field to Tutor LMS registration forms (following official docs)
     */
    public function add_phone_to_tutor_registration() {
        ?>
<div class="tutor-form-group">
    <label for="phone_number"><?php _e('Phone Number', 'smart-login-registration'); ?> <span
            class="required">*</span></label>
    <input type="tel" name="phone_number" id="phone_number"
        placeholder="<?php _e('Enter your phone number', 'smart-login-registration'); ?>" required />
    <p class="tutor-form-feedback">
        <?php _e('Phone number is required for registration and will be used for contact purposes.', 'smart-login-registration'); ?>
    </p>
</div>
<?php
    }
    
    /**
     * Add phone number as required field for Tutor LMS registration (following official docs)
     */
    public function tutor_required_phone_fields($atts) {
        $atts['phone_number'] = __('Phone Number field is required', 'smart-login-registration');
        return $atts;
    }
    
    /**
     * Add custom CSS for Tutor LMS phone field styling
     */
    public function add_tutor_phone_field_styles() {
        if (!function_exists('tutor') && !class_exists('TUTOR\Tutor')) {
            return;
        }
        ?> <style type="text/css">
.tutor-form-group input[name="phone_number"],
.tutor-form-control[name="phone_number"] {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.tutor-form-group input[name="phone_number"]:focus,
.tutor-form-control[name="phone_number"]:focus {
    border-color: #008036;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 128, 54, 0.1);
}

.tutor-form-group .required {
    color: #e74c3c;
}

.tutor-form-feedback {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
</style>
<?php
    }
    
    /**
     * Manually set phone number for a user (for testing/debugging)
     */
    public function set_user_phone($user_id, $phone) {
        if (!$user_id || !$phone) {
            return false;
        }
        
        // Validate phone number
        if (!$this->is_valid_phone($phone)) {
            return false;
        }
        
        // Update all phone fields
        update_user_meta($user_id, 'phone', $phone);
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'phone_number', $phone); // Tutor's official field
        update_user_meta($user_id, '_tutor_profile_phone', $phone);
        
        if (class_exists('WooCommerce')) {
            update_user_meta($user_id, 'shipping_phone', $phone);
        }
        
        return true;
    }
    
    /**
     * Save phone number after Tutor LMS registration/profile update (following official docs)
     */
    public function tutor_save_phone_after_register($user_id) {
        // Check for phone_number from Tutor's profile form
        if (!empty($_POST['phone_number'])) {
            $phone_number = sanitize_text_field($_POST['phone_number']);
            
            // Validate phone number
            if ($this->is_valid_phone($phone_number)) {
                // Save in Tutor's expected format (primary)
                update_user_meta($user_id, 'phone_number', $phone_number);
                
                // Also save in our standard fields for cross-platform compatibility
                update_user_meta($user_id, 'phone', $phone_number);
                update_user_meta($user_id, 'billing_phone', $phone_number);
                update_user_meta($user_id, '_tutor_profile_phone', $phone_number);
                
                if (class_exists('WooCommerce')) {
                    update_user_meta($user_id, 'shipping_phone', $phone_number);
                }
                
                // Update WooCommerce customer data
                $user = get_userdata($user_id);
                if ($user) {
                    $user_data = array(
                        'phone' => $phone_number,
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->user_email
                    );
                    $this->update_woocommerce_customer($user_id, $user_data);
                }
            }
        }
    }
    
    /**
     * Migrate existing phone data to Tutor's phone_number field
     */
    public function migrate_phone_to_tutor_field() {
        // Only run once
        if (get_option('slr_tutor_phone_migrated')) {
            return;
        }
        
        $users = get_users(array(
            'meta_key' => 'phone',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $phone = get_user_meta($user->ID, 'phone', true);
            $tutor_phone = get_user_meta($user->ID, 'phone_number', true);
            
            // Only migrate if Tutor field is empty and we have a phone number
            if (!empty($phone) && empty($tutor_phone)) {
                update_user_meta($user->ID, 'phone_number', $phone);
            }
        }
        
        // Mark migration as complete
        update_option('slr_tutor_phone_migrated', true);
    }
    
    /**
     * Add admin utility to set phone numbers for testing
     */
    public function add_test_phone_utility() {
        if (isset($_GET['slr_set_test_phone']) && current_user_can('manage_options')) {
            $user_id = get_current_user_id();
            $test_phone = '01234567890';
            
            // Set phone in all fields
            update_user_meta($user_id, 'phone_number', $test_phone);
            update_user_meta($user_id, 'phone', $test_phone);
            update_user_meta($user_id, 'billing_phone', $test_phone);
            update_user_meta($user_id, '_tutor_profile_phone', $test_phone);
            
            wp_redirect(remove_query_arg('slr_set_test_phone'));
            exit;
        }
    }
}