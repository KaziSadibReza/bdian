<?php
/**
 * Checkout coupon form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-coupon.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
	return;
}

?>
<?php
$applied_coupons = WC()->cart->get_applied_coupons();

if ( ! empty( $applied_coupons ) ) {
$valid_coupon = reset( $applied_coupons ); // Assuming only one valid coupon
} else {
$valid_coupon = ''; // Replace with your actual valid coupon code
}
?>

<form class="checkout_coupons woocommerce-form-coupon" method="post" >

	<p class="custom_title"><?php esc_html_e( 'বইয়ে থাকা Activation Code টি প্রদান করুন.', 'woocommerce' ); ?></p>
<div class="custom_flex">
	<p class="form-row form-row-first">
		<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Activation Code:', 'woocommerce' ); ?></label>
		<input type="text" name="coupon_code" class="input-text" placeholder="<?php esc_attr_e( 'Activation Code', 'woocommerce' ); ?>" id="coupon_code" value="<?php echo esc_attr( $valid_coupon ); ?>" />
	</p>

	<p class="form-row form-row-last">
		<button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply code', 'woocommerce' ); ?></button>
	</p>
</div>
	<div class="clear"></div>
    <p class="custom_title">আপনার কাছে Activation Code না থাকলে <a href="https://bdian.org/books/">বইটি কিনুন</a></p>
</form>
