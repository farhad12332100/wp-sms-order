<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! $is_shortcode && ! $product_id ) {
	return;
}
if ( $overall_rating_enable !== 'on' && $rating_count_enable !== 'on' ) {
	return;
}
$prefix_class = $is_shortcode ? 'shortcode-' : '';
$class = array($prefix_class.'wcpr-overall-rating-and-rating-count');
if (!$is_shortcode && !empty($product_id)){
    $class[] = 'wcpr-overall-rating-and-rating-count-'.$product_id;
}
?>
<div class="<?php echo esc_attr( implode(' ', $class) ); ?>" <?php echo wp_kses_post( ! $prefix_class ? 'style="display: none;"' : '' ); ?>>
	<?php
	if ( $overall_rating_enable === 'on' ) {
		?>
        <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating">
            <h2>
				<?php
				if ( $is_shortcode ) {
					echo apply_filters( 'woocommerce_photo_reviews_shortcode_overall_rating_text', esc_html__( '', 'woocommerce-photo-reviews' ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo apply_filters( 'woocommerce_photo_reviews_overall_rating_text', esc_html__( 'امتیاز کاربران', 'woocommerce-photo-reviews' ), wc_get_product( $product_id ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
            </h2>
            <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-main">
                <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-left">
					<span class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-left-average">
						<?php echo wp_kses_post( number_format( $average_rating, 2 ) ); ?>
					</span>
                </div>
                <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-right">
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-right-star">
						<?php echo wc_get_rating_html( $average_rating );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-overall-rating-right-total">
						<?php
                        if($count_reviews > 1){
	                        /* translators: %s: review count */
	                        printf( esc_html__( 'مجموع از %s کاربر', 'woocommerce-photo-reviews' ), esc_html( $count_reviews ) );

                        }else{
	                        /* translators: %s: review count */
	                        printf( esc_html__( 'مجموع از %s کاربر', 'woocommerce-photo-reviews' ), esc_html( $count_reviews ) );

                        }
//						printf( _n( 'Based on %s review', 'Based on %s reviews', $count_reviews, 'woocommerce-photo-reviews' ), $count_reviews, 'woocommerce-photo-reviews' );
						?>
                    </div>
                </div>
            </div>
        </div>
		<?php
	}
	if ( $rating_count_enable === 'on' ) {
		?>
        <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-stars-count">
			<?php
			for ( $i = 5; $i > 0; $i -- ) {
				$rate       = 0;
				$star_count = '';
				if ( $count_reviews ) {
					$star_count = isset( $star_counts[ $i ] ) ? $star_counts[ $i ] : ( $product_id ? VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::stars_count( $i, $product_id ) : 0 );
					$rate       = ( 100 * ( $star_count / $count_reviews ) );
				}
				?>
                <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-row">
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-col-number"><?php echo esc_html( $i ); ?></div>
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-col-star"><?php echo wc_get_rating_html( $i );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-col-process">
                        <div class="rate-percent-bg">
                            <div class="rate-percent" style="width: <?php echo esc_attr( $rate ); ?>%;"></div>
                            <div class="rate-percent-bg-1"><?php echo esc_html( round( $rate ) . '%' ) ?></div>
                        </div>
                    </div>
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-col-rank-count"><?php echo esc_html( $star_count ); ?></div>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}
	?>
</div>
