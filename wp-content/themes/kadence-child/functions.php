<?php

/**
 * Kadence Child Theme functions and definitions
 *
 * @package  Kadence Child
 * @author   Kazi Sadib Reza
 * @link     https://github.com/KaziSadibReza
 */
// Enqueue parent theme styles
function kadence_child_enqueue_styles() {
    wp_enqueue_style( 'kadence-parent-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'kadence_child_enqueue_styles' );


/****************************** CUSTOM FUNCTIONS ******************************/

/* === ISOLATION SYSTEM FOR ACTIVATION PAGE ===
 * This theme uses conditional logic to isolate activation page customizations
 * from CartFlows and other checkout pages.
 * 
 * SETUP: CartFlows checkout is on HOME PAGE (https://bdian.org/)
 *        Activation page is on ACTIVATE URL (https://bdian.org/activate/)
 * 
 * KEY FUNCTIONS:
 * - is_cartflows_checkout() - Detects home page and CartFlows pages
 * - is_activation_page() - Returns true only for /activate/ page (NOT home page)
 * 
 * ISOLATED CUSTOMIZATIONS:
 * 1. PHP Functions (in functions.php):
 *    - Field removal (billing/shipping)
 *    - Field requirement removal  
 *    - CSS hiding of fields
 *    - Terms auto-check
 *    - Coupon position
 *    - Order review removal
 *    - Payment requirement removal
 *    - Place order button text
 *    - Coupon validation
 * 
 * 2. Template Files (in woocommerce/checkout/):
 *    - form-checkout.php - Hides customer details, custom layout
 *    - form-coupon.php - Bengali text, custom styling
 * 
 * RESULT:
 * - Home Page (CartFlows): Standard WooCommerce functionality with all fields
 * - Activation Page (/activate/): Full customization (hidden fields, Bengali text, etc.)
 * - Other Pages: Unaffected
 */

/**
 * Enqueue Split.js library
 *
 * @return void
 */
function enqueue_split_js() {
    wp_enqueue_script('split-js', 'https://unpkg.com/split.js/dist/split.min.js', [], null, false);
}
add_action('wp_enqueue_scripts', 'enqueue_split_js');


/**
 * Disable mobile order summary collapse in CartFlows
 */
add_filter( 'cartflows_show_mobile_order_summary_collapsed', '__return_false' );

//add to cart redirect to check out page
add_filter ('woocommerce_add_to_cart_redirect', 'redirect_to_checkout');
function redirect_to_checkout() {
    return wc_get_checkout_url();
}


//change add to card text
add_filter('gettext', 'woo_custom_change_cart_string', 100, 3);
add_filter('ngettext', 'woo_custom_change_cart_string', 100, 3);
function woo_custom_change_cart_string($translated_text, $text, $domain) {
    $translated_text = str_replace("view cart", "এগিয়ে যান", $translated_text);
    $translated_text = str_replace("View cart", "এগিয়ে যান", $translated_text);
    return $translated_text;
}
//change bye now button text
// To change add to cart text on single product page
add_filter( 'woocommerce_product_single_add_to_cart_text', 'woocommerce_custom_single_add_to_cart_text' );
function woocommerce_custom_single_add_to_cart_text() {
    return __( 'Active Course', 'woocommerce' );
}

// To change add to cart text on product archives(Collection) page
add_filter( 'woocommerce_product_add_to_cart_text', 'woocommerce_custom_product_add_to_cart_text' );
function woocommerce_custom_product_add_to_cart_text() {
    return __( 'Active Course', 'woocommerce' );
}



function custom_woocommerce_text( $translated, $text, $domain ) {
  if ( $text === 'Your cart is currently empty.' ) {
    $translated = 'No selected course found';
  }
  return $translated;
}

add_filter( 'gettext', 'custom_woocommerce_text', 10, 3 );



function custom_change_enroll_course_text( $translated_text, $text, $domain ) {
    if ( 'Enroll Course' === $text && 'tutor' === $domain ) {
        $translated_text = 'শুরু করুন';
    }
    return $translated_text;
}
add_filter( 'gettext', 'custom_change_enroll_course_text', 20, 3 );



/**
 * Add custom link to Tutor dashboard menu
 */
add_filter('tutor_dashboard/nav_items', 'add_some_links_dashboard');
function add_some_links_dashboard($links){

	$links['custom_link'] = [
		"title" =>	__('Live Class', 'tutor'),
		"url" => "https://course.bdian.org/live-class/",
		"icon" => "tutor-icon-brand-google-meet ",

	];
	return $links;
}

// Automatically generate username from email
add_filter('woocommerce_new_customer_data', function($customer_data) {
    if (empty($customer_data['user_login']) && !empty($customer_data['user_email'])) {
        $customer_data['user_login'] = $customer_data['user_email'];
    }
    return $customer_data;
});

// Function to check if the current page is the activation page
function is_activation_page() {
    $url = $_SERVER['REQUEST_URI'];
    // Now checks for 'activate' in URL
    if (strpos($url, 'activate') !== false) {
        return true;
    }
    return false;
}

if (is_activation_page()) {
require_once get_stylesheet_directory() . '/only_activation_page.php';
}