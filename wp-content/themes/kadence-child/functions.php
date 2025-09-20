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

/**
 * Make "I have read and agree to the terms and conditions" checkbox checked by default
 */
add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );

/****************************** CUSTOM FUNCTIONS ******************************/

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
		"title" =>	__('All Course', 'tutor'),
		"url" => "https://bdian.org/course",
		"icon" => "tutor-icon-mortarboard-o ",

	];
    $links['custom_link'] = [
		"title" =>	__('Live Class', 'tutor'),
		"url" => "https://bdian.org/live-class",
		"icon" => "tutor-icon-brand-google-meet",

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
    // Check URL from various sources (works for AJAX too)
    $url = '';
    if (isset($_SERVER['REQUEST_URI'])) {
        $url = $_SERVER['REQUEST_URI'];
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
    } elseif (isset($_POST['_wp_http_referer'])) {
        $url = $_POST['_wp_http_referer'];
    }
    
    // Check if URL contains 'activate'
    if (strpos($url, 'activate') !== false) {
        return true;
    }
    
    // Additional check for AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Check if the referer contains activate
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'activate') !== false) {
            return true;
        }
    }
    
    return false;
}

// Always load activation page customizations if it's an activation page or AJAX from activation page
if (is_activation_page() || (defined('DOING_AJAX') && DOING_AJAX && is_activation_page())) {
    require_once get_stylesheet_directory() . '/only_activation_page.php';
}

// Ensure activation page customizations work during AJAX requests
add_action('init', 'setup_activation_ajax_hooks');

function setup_activation_ajax_hooks() {
    if (defined('DOING_AJAX') && DOING_AJAX && is_activation_page()) {
        // Force early loading for AJAX requests from activation page
        if (!function_exists('custom_order_button_html')) {
            require_once get_stylesheet_directory() . '/only_activation_page.php';
        }
    }
}

// Hook into WooCommerce AJAX actions specifically
add_action('wp_ajax_woocommerce_apply_coupon', 'force_activation_context', 1);
add_action('wp_ajax_nopriv_woocommerce_apply_coupon', 'force_activation_context', 1);
add_action('wp_ajax_woocommerce_remove_coupon', 'force_activation_context', 1);
add_action('wp_ajax_nopriv_woocommerce_remove_coupon', 'force_activation_context', 1);
add_action('wp_ajax_woocommerce_update_order_review', 'force_activation_context', 1);
add_action('wp_ajax_nopriv_woocommerce_update_order_review', 'force_activation_context', 1);

function force_activation_context() {
    // Check if this AJAX request is from activation page
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'activate') !== false) {
        // Force load activation page customizations immediately
        if (!function_exists('custom_order_button_html')) {
            require_once get_stylesheet_directory() . '/only_activation_page.php';
        }
        
        // Set a global flag to indicate we're in activation context
        global $is_activation_ajax;
        $is_activation_ajax = true;
        
        // Debug log (remove after testing)
        error_log('Activation AJAX context detected: ' . $_SERVER['HTTP_REFERER']);
    }
}

// Additional hook to ensure activation context is maintained during checkout updates
add_action('woocommerce_checkout_update_order_review', 'maintain_activation_context_on_update', 1);
function maintain_activation_context_on_update($posted_data) {
    // Check if we're in activation context
    if (defined('DOING_AJAX') && DOING_AJAX && is_activation_page()) {
        global $is_activation_ajax;
        $is_activation_ajax = true;
    }
}