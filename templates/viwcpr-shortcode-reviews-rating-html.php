<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woocommerce-photo-reviews-rating-html-shortcode">
	<?php
	echo wc_get_rating_html( $rating );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( 'on' === $review_count_enable && $review_count ) {
		printf( '<span class="woocommerce-photo-reviews-review-count-container">(<span class="woocommerce-photo-reviews-review-count">%s</span>)</span>', wp_kses_post( $review_count ) );
	}
	?>
</div>
