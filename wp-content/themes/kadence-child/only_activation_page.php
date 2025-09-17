<?php
if(!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Helper function to check if we're in activation context (including AJAX)
function is_in_activation_context() {
    global $is_activation_ajax;
    return is_activation_page() || $is_activation_ajax || 
           (defined('DOING_AJAX') && DOING_AJAX && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'activate') !== false);
}

// Only apply activation customizations if we're in activation context
if (is_in_activation_context()) {

add_filter( 'woocommerce_order_button_html', 'custom_order_button_html' );

function custom_order_button_html( $button_html ) {
    if (!is_user_logged_in()) {
        // User not logged in - show login button that triggers Smart Login popup
        $button_html = '<button type="button" class="button alt slr-login-popup-btn" id="slr-login-required">' . esc_attr__( 'Login to Complete Activation', 'woocommerce' ) . '</button>';
        
        // Also include the Smart Login Registration popup HTML
        if (function_exists('SmartLoginRegistration') || class_exists('SmartLoginRegistration')) {
            // Add the popup shortcode content
            $button_html .= do_shortcode('[smart_login_popup button_text="Login" button_class="hidden" show_register="yes"]');
        }
    } else {
        // User is logged in - show normal activation button
        $button_html = '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr__( 'Your desired button text', 'woocommerce' ) . '">' . esc_attr__( 'Complete Activation', 'woocommerce' ) . '</button>';
    }
    return $button_html;
}

// Add custom styles for activation page login integration
add_action('wp_head', 'activation_page_login_styles');
function activation_page_login_styles() {
    ?>
<style>
/* Hide the default Smart Login button since we're using custom integration */
.hidden {
    display: none !important;
}

/* Style the login required button */
#slr-login-required {
    background: #2196F3;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s;
}

#slr-login-required:hover {
    background: #1976D2;
}

/* Ensure popup appears above everything */
#slr-login-popup-container {
    z-index: 999999 !important;
}
</style>
<?php
}

// Add JavaScript for handling login success and page refresh
add_action('wp_footer', 'activation_page_login_scripts');
function activation_page_login_scripts() {
    ?>
<script>
jQuery(document).ready(function($) {
    // Override the Smart Login success behavior for activation page
    $(document).on('slr_login_success', function(e, response) {
        if (window.location.href.indexOf('activate') !== -1) {
            // We're on activation page - reload to show the activation button
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        }
    });

    // Also listen for successful login completion
    $(document).on('slr_login_complete', function() {
        if (window.location.href.indexOf('activate') !== -1) {
            window.location.reload();
        }
    });

    // Handle popup close and check login status
    $(document).on('click', '.slr-popup-close, .slr-popup-overlay', function() {
        setTimeout(function() {
            // Check if user is now logged in by making a simple AJAX call
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'check_login_status'
                },
                success: function(response) {
                    if (response.success && response.data.logged_in) {
                        window.location.reload();
                    }
                }
            });
        }, 500);
    });
});
</script>
<?php
}

// AJAX handler to check login status
add_action('wp_ajax_check_login_status', 'ajax_check_login_status');
add_action('wp_ajax_nopriv_check_login_status', 'ajax_check_login_status');
function ajax_check_login_status() {
    wp_send_json_success(array(
        'logged_in' => is_user_logged_in(),
        'user_id' => get_current_user_id()
    ));
}

/**
 * Make "I have read and agree to the terms and conditions" checkbox checked by default
 */
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );


// add to cart massage custom
add_filter( 'wc_add_to_cart_message_html', 'custom_add_to_cart_message', 10, 3 );

function custom_add_to_cart_message( $message, $products, $show_qty ) {
    // Extract the relevant parts of the message
    $titles = wc_format_list_of_items( apply_filters( 'woocommerce_add_to_cart_qty_html', array(), $products, $show_qty ), apply_filters( 'woocommerce_add_to_cart_item_name_in_quotes', array(), $products, $show_qty ) );
    $count = array_sum( $products );

    // Create the desired message
    $added_text = sprintf( _n( '%s Course successfully ready to Active.', '%s Course successfully ready to Active.', $count, 'woocommerce' ), $titles );

    // Remove the "Continue shopping" link
    $message = preg_replace( '/<a[^>]+>Continue shopping<\/a>/', '', $message );

    // Combine and return the modified message
    $message = sprintf( '<a href="%s" tabindex="1" class="button wc-forward">%s</a> %s', esc_url( "https://bdian.org/courses/" ), esc_html__( 'More Course', 'woocommerce' ), $added_text );

    return $message;
}

//success massage for activation code and remove code
add_filter('woocommerce_coupon_message', 'woo_coupon_custom_message', 10, 3);
function woo_coupon_custom_message($msg, $msg_code, $WC_Coupon) {
    switch ($msg_code) {
        case $WC_Coupon::WC_COUPON_SUCCESS:
            $msg = __( 'Activation Code applied successfully.<br> <span style="color:red;">Click [terms and conditions box] and Complete Activation</span>', 'woocommerce' ); // Replace with your desired message
            break;
        case $WC_Coupon::WC_COUPON_REMOVED:
            $msg = __( 'Activation Code removed successfully.', 'woocommerce' ); // Replace with your desired message
            break;
    }
    return $msg;
}



// error massage for wrong activation code
add_filter('woocommerce_coupon_error', 'woo_custom_coupon_error', 10, 3);
function woo_custom_coupon_error($err, $err_code, $WC_Coupon) {
    switch ($err_code) {
        case $WC_Coupon::E_WC_COUPON_NOT_EXIST:
            $err = __( 'Activation Code is not valid', 'woocommerce' ); // Replace with your desired message
            break;
        case $WC_Coupon::E_WC_COUPON_PLEASE_ENTER:
            $err = __( 'Please enter your Activation Code', 'woocommerce' ); // Replace with your desired message
            break;
        case $WC_Coupon::E_WC_COUPON_ALREADY_APPLIED:
            $err = __( 'Activation Code is already applied!', 'woocommerce' );
            break;
		case $WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED:
            $err = __( 'Activation Code usage limit has been reached.', 'woocommerce' );
    }
    return $err;
}




// activation code massage for wrong course
add_filter('woocommerce_coupon_error', 'woo_custom_coupon_error_message', 10, 3);

function woo_custom_coupon_error_message($err, $err_code, $WC_Coupon) {
    if ($err_code === 109) { // Target the specific error code for this message
        $err = __( 'Sorry, this Activation Code is not applicable to selected course.', 'woocommerce' );
    }
    return $err;
}

//custom coupon in after order button
add_action( 'woocommerce_review_order_before_submit', 'woocommerce_checkout_coupon_form', 10 );
//remove coupon form checkout head
remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
//remove require field
add_filter( 'woocommerce_checkout_fields', 'unrequire_checkout_fields' );
function unrequire_checkout_fields( $fields ) {
    $fields['billing']['billing_company']['required']   = false;
    $fields['billing']['billing_city']['required']      = false;
    $fields['billing']['billing_postcode']['required']  = false;
    $fields['billing']['billing_country']['required']   = false;
    $fields['billing']['billing_state']['required']     = false;
    $fields['billing']['billing_address_1']['required'] = false;
    $fields['billing']['billing_address_2']['required'] = false;
    $fields['billing']['billing_phone']['required']     = false;
    $fields['billing']['billing_email']['required']     = false;
    return $fields;
}
//remove billing field
add_filter('woocommerce_checkout_fields','remove_checkout_fields');
function remove_checkout_fields($fields){
    unset($fields['billing']['billing_first_name']);
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_email']);
    unset($fields['billing']['billing_company']);
    unset($fields['order']);
    return $fields;
}
//hide billing country
add_action('woocommerce_before_checkout_form', 'hide_checkout_billing_country', 5);
function hide_checkout_billing_country() {
    echo '<style>#billing_country_field{display:none;}</style>';
}
// remove billing country error
add_filter('woocommerce_billing_fields', 'customize_billing_fields', 100);
function customize_billing_fields($fields ) {
    if (is_checkout()) {
        // HERE set the required key fields below
        $chosen_fields = array('first_name', 'last_name', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state');

        foreach ($chosen_fields as $key) {
            if (isset($fields['billing_'.$key]) && $key !== 'country') {
                unset($fields['billing_'.$key]); // Remove all define fields except country
            }
        }
    }
    return $fields;
}
//remove order review
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
//remove required payment methods
add_filter( 'woocommerce_cart_needs_payment', '__return_false' );
//disable billing address requirement when payment not needed
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
add_filter( 'woocommerce_checkout_get_value', 'activation_page_checkout_defaults', 10, 2 );
function activation_page_checkout_defaults( $value, $input ) {
    // Set default values for required billing fields to prevent validation errors
    switch ( $input ) {
        case 'billing_country':
            return 'BD'; // Default country
        case 'billing_address_1':
            return 'N/A'; // Default address
        case 'billing_city':
            return 'N/A'; // Default city
        case 'billing_postcode':
            return '1000'; // Default postcode
        case 'billing_state':
            return 'DHK'; // Default state
    }
    return $value;
}

// Disable billing address validation completely for activation page
add_action('woocommerce_after_checkout_validation', 'remove_billing_address_validation', 10, 2);
function remove_billing_address_validation($data, $errors) {
    // Remove any billing address related errors
    $error_codes_to_remove = array(
        'billing_country_required',
        'billing_address_1_required', 
        'billing_city_required',
        'billing_postcode_required',
        'billing_state_required'
    );
    
    foreach ($error_codes_to_remove as $code) {
        $errors->remove($code);
    }
    
    // Also remove generic address errors
    $all_errors = $errors->get_error_messages();
    foreach ($all_errors as $key => $message) {
        if (strpos($message, 'address') !== false || strpos($message, 'required') !== false) {
            $errors->remove($errors->get_error_codes()[$key]);
        }
    }
}
//required coupon in checkout page
add_action( 'woocommerce_checkout_process', 'my_validate_coupon_usage' );
function my_validate_coupon_usage() {
    $applied_coupons = WC()->cart->get_coupons();
    $num_items = WC()->cart->get_cart_contents_count();
    if ( ! WC()->cart->has_discount() ) {
        wc_add_notice( __( 'Please enter your Activation Code to active your course.', 'woocommerce' ), 'error' );
    }
   // Consider product threshold and exceptions (if needed)
    if ( $num_items > 1 && count( $applied_coupons ) === 1 && !is_coupon_applicable_for_multiple_products( $applied_coupons[0] ) ) {
        wc_add_notice( __( 'Please remove the course for which you do not have the activation code', 'woocommerce' ), 'error' );
    }
}

// Function to check if coupon is applicable for multiple products (customize as needed)
function is_coupon_applicable_for_multiple_products( $coupon_code ) {
    // Logic to identify applicable coupons based on your requirements
    // (e.g., check specific coupon codes, product categories, etc.)
    return false; // By default, assume not applicable
}

} // End activation context check