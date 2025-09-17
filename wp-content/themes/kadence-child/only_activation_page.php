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
  $button_html = '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr__( 'Your desired button text', 'woocommerce' ) . '">' . esc_attr__( 'Complete Activation', 'woocommerce' ) . '</button>';
  return $button_html;
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