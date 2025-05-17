<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() &&
    $checkout->is_registration_required() && ! is_user_logged_in() ) { echo
esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __(
    'You must be logged in to checkout.', 'woocommerce' ) ) ); return; } ?>

<form
        name="checkout"
        method="post"
        class="checkout woocommerce-checkout"
        action="<?php echo esc_url( wc_get_checkout_url() ); ?>"
        enctype="multipart/form-data"
>
    <?php if ( $checkout->get_checkout_fields() ) : ?>

        <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

        <div class="col2-set" id="customer_details" style="display: none" ;>
            <div class="col-1">
                <?php do_action( 'woocommerce_checkout_billing' ); ?>
            </div>

            <div class="col-2">
                <?php do_action( 'woocommerce_checkout_shipping' ); ?>
            </div>
        </div>

        <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

    <?php endif; ?>

    <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

    <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
    <div
            class="custom_container_woo">
        <div class="custom_flid_one">
            <?php
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                    ?>
                    <!-- .cart-contents-wrapper -->
                    <div class="custom_product cart-contents-wrapper mb-3">
                        <!-- .cart-contents -->
                        <?php do_action( 'woocommerce_before_cart_contents' ); ?>
                        <div class="cart-contents">

                            <!-- .cart-product-image -->
                            <div class="cart-product-image">
                                <?php
                                $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'cart-image-thumb' ), $cart_item, $cart_item_key );

                                if ( ! $product_permalink ) {
                                    echo $thumbnail; // PHPCS: XSS ok.
                                } else {
                                    printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // PHPCS: XSS ok.
                                }
                                ?>
                            </div>

                            <!-- .cart-product-title -->
                            <div class="cart-product-title">
                                <?php
                                if ( ! $product_permalink ) {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' );
                                } else {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
                                }

                                do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

                                // Meta data.
                                echo wc_get_formatted_cart_item_data( $cart_item ); // PHPCS: XSS ok.

                                // Backorder notification.
                                if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'tutorstarter' ) . '</p>', $product_id ) );
                                }
                                ?>
                            </div>

                            <!-- .cart-product-actions -->
                            <div class="cart-product-actions">


                                <!-- .cart-product-remove -->
                                <div class="cart-product-remove">
                                    <?php
                                    echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        'woocommerce_cart_item_remove_link',
                                        sprintf(
                                            '<a href="%s" aria-label="%s" data-product_id="%s" data-product_sku="%s">%s</a>',
                                            esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                            esc_html__( 'Remove this item', 'tutorstarter' ),
                                            esc_attr( $product_id ),
                                            esc_attr( $_product->get_sku() ),
                                            esc_html__( 'Remove', 'tutorstarter' )
                                        ),
                                        $cart_item_key
                                    );
                                    ?>
                                </div>
                            </div>
                        </div>
                        <!-- .cart-contents -->
                        <?php do_action( 'woocommerce_cart_contents' ); ?>
                        <?php do_action( 'woocommerce_after_cart_contents' ); ?>
                    </div>
                    <!-- .cart-contents-wrapper -->
                    <?php
                }
            }
            ?>
        </div>
        <div class="custom_flid_tow">
            <div id="order_review" class="woocommerce-checkout-review-order">
                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
            </div>

            <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
        </div>
    </div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
