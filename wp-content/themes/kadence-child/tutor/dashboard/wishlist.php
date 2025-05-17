<?php
/**
 * Frontend Wishlist Page
 *
 * @package Tutor\Templates
 * @subpackage Dashboard
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @version 1.4.3
 */

use TUTOR\Input;

?>

<div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-24"><?php esc_html_e( 'Mock Test Report', 'tutor' ); ?></div>
<div class="tutor-dashboard-content-inner my-wishlist">
	<?php echo do_shortcode( '[ays_user_page id="Your_Quiz_Category_ID"]' );?>
</div>
