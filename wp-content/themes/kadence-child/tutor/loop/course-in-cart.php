<?php
/**
 * Course loop show view cart if in added to.
 *
 * @package Tutor\Templates
 * @subpackage CourseLoopPart
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 1.7.5
 */

?>

<div class="tutor-course-loop-price">
	<?php
	$course_id     = get_the_ID();
	$checkout_url      = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '#';
	$enroll_btn    = '<div  class="tutor-loop-cart-btn-wrap"><a href="' . $checkout_url . '" class="tutor-btn tutor-btn-outline-primary tutor-btn-md"><span class="tutor-icon-cart-line tutor-mr-8"></span><span>' . __( 'এগিয়ে যান', 'tutor' ) . '</span></a></div>';
	$default_price = apply_filters( 'tutor-loop-default-price', __( 'Free', 'tutor' ) );
	$price_html    = '<div class="price"> ' . $default_price . $enroll_btn . '</div>';
	if ( tutor_utils()->is_course_purchasable() ) {

		$product_id = tutor_utils()->get_course_product_id( $course_id );
		$product    = wc_get_product( $product_id );

        if ( $product ) {
            $pr = $product->slug;
            $cs_url = "https://bdian.org/product/";
            $price_html = '<div class="tutor-d-flex tutor-align-center tutor-justify-between"><div class="list-item-price tutor-d-flex tutor-align-center"> <span class="price tutor-fs-6 tutor-fw-bold tutor-color-black"> <a href="' . $cs_url . $pr . '" > কোর্সটি কিনুন </a> </span></div>';
            $cart_html  = '<div class="list-item-button"> ' . apply_filters( 'tutor_course_restrict_new_entry', $enroll_btn ) . ' </div></div>';
        }
	}
	echo wp_kses_post( $price_html );
	echo wp_kses_post( $cart_html );
	?>
</div>
