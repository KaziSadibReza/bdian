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
add_filter('tutor_student_registration_required_fields', function ($fields) {
    // Remove last name from required fields
    if (isset($fields['last_name'])) {
        unset($fields['last_name']);
    }
    return $fields;
});



// remove and orderlist from dashboard
// add_filter('tutor_dashboard/nav_items', 'remove_some_links_dashboard');
// function remove_some_links_dashboard($links){
//     unset($links['purchase_history']);
//     return $links;
// }

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
    return __( 'Buy Now', 'woocommerce' );
}

// To change add to cart text on product archives(Collection) page
add_filter( 'woocommerce_product_add_to_cart_text', 'woocommerce_custom_product_add_to_cart_text' );
function woocommerce_custom_product_add_to_cart_text() {
    return __( 'Buy Now', 'woocommerce' );
}



function custom_woocommerce_text( $translated, $text, $domain ) {
  if ( $text === 'Your cart is currently empty.' ) {
    $translated = 'No selected course found';
  }
  return $translated;
}

add_filter( 'gettext', 'custom_woocommerce_text', 10, 3 );


// function disable_plugin_updates_idc( $value ) {
//     if ( isset( $value->response['digits/digit.php'] ) ) {
//         unset( $value->response['digits/digit.php'] );
//     }
//     return $value;
// }
// add_filter( 'site_transient_update_plugins', 'disable_plugin_updates_idc' );




function custom_change_enroll_course_text( $translated_text, $text, $domain ) {
    if ( 'Enroll Course' === $text && 'tutor' === $domain ) {
        $translated_text = 'শুরু করুন';
    }
    return $translated_text;
}
add_filter( 'gettext', 'custom_change_enroll_course_text', 20, 3 );

// add_action('template_redirect', 'bryce_clear_cart');
// function bryce_clear_cart() {
//     // Check if WooCommerce is active
//     if (!class_exists('WooCommerce')) {
//         return;
//     }

//     // Avoid clearing the cart during any WooCommerce AJAX requests
//     if (defined('DOING_AJAX') && DOING_AJAX) {
//         return;
//     }

//     // Avoid clearing the cart during any WooCommerce REST API requests
//     if (defined('REST_REQUEST') && REST_REQUEST) {
//         return;
//     }

//     // Avoid clearing the cart on WooCommerce cart, checkout, and account-related pages
//     if (is_cart() || is_checkout() || is_account_page()) {
//         return;
//     }

//     // Additional WooCommerce-specific checks (optional)
//     if (is_woocommerce() && !is_shop()) {
//         return;
//     }

//     // Additional debugging log to track cart clearing
//     if (function_exists('wc_get_logger')) {
//         $logger = wc_get_logger();
//         $logger->info('Cart cleared by bryce_clear_cart function', array('source' => 'bryce_clear_cart'));
//     }

//     // Clear the cart
//     WC()->cart->empty_cart(true);
// }
// 

add_filter('tutor_dashboard/nav_items', 'add_some_links_dashboard');
function add_some_links_dashboard($links){

	$links['custom_link'] = [
		"title" =>	__('Live Class', 'tutor'),
		"url" => "https://course.bdian.org/live-class/",
		"icon" => "tutor-icon-brand-google-meet ",

	];
	return $links;
}


function enqueue_split_js() {
    wp_enqueue_script('split-js', 'https://unpkg.com/split.js/dist/split.min.js', [], null, false);
}
add_action('wp_enqueue_scripts', 'enqueue_split_js');

add_filter('woocommerce_terms_is_checked_default', '__return_true');





// Remove the username field on checkout
add_filter('woocommerce_checkout_fields', function($fields) {
    if (isset($fields['account']['account_username'])) {
        unset($fields['account']['account_username']);
    }
    return $fields;
});

// Automatically generate username from email
add_filter('woocommerce_new_customer_data', function($customer_data) {
    if (empty($customer_data['user_login']) && !empty($customer_data['user_email'])) {
        $customer_data['user_login'] = $customer_data['user_email'];
    }
    return $customer_data;
});

/****************************** INCLUDE CUSTOM MODULES ******************************/

// Include Tutor Login Popup functionality
if (file_exists(get_stylesheet_directory() . '/includes/tutor-login-popup.php')) {
    require_once get_stylesheet_directory() . '/includes/tutor-login-popup.php';
}