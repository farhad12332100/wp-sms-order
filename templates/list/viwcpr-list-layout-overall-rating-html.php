<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!$is_shortcode && !$product_id) {
    return;
}
if ($overall_rating_enable !== 'on' && $rating_count_enable !== 'on') {
    return;
}
$prefix_class = $is_shortcode ? 'shortcode-' : '';
$class = array($prefix_class . 'wcpr-overall-rating-and-rating-count', $prefix_class . 'list-style');
if (!$is_shortcode && !empty($product_id)) {
    $class[] = 'wcpr-overall-rating-and-rating-count-' . $product_id;
}
?>
<div class="<?php echo esc_attr(implode(' ', $class)); ?>" <?php echo wp_kses_post(!$prefix_class ? 'style="display: none;"' : ''); ?>>
    <?php
    if ($overall_rating_enable === 'on') {
        ?>
        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-rating <?php echo esc_attr($prefix_class); ?>list-style">
            <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-rating-main">
                <span class="wcpr-title1"><?php echo esc_html__('Average Rating', 'woocommerce-photo-reviews'); ?></span>
                <div class="wcpr-title2">
                    <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-rating-average">
                        <?php echo wp_kses_post(number_format($average_rating, 2)); ?>
                    </div>
                    <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-rating-right-star">
                        <?php echo wc_get_rating_html($average_rating);// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
                <span class="wcpr-title3"><?php echo esc_html__('Average rating on this product', 'woocommerce-photo-reviews'); ?></span>
            </div>
        </div>
        <?php
    }
    if ($rating_count_enable === 'on') {
        ?>
        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-stars-count <?php echo esc_attr($prefix_class); ?>list-style">
            <?php
            for ($i = 5; $i > 0; $i--) {
                $rate = 0;
                $star_count = '';
                if ($count_reviews) {
                    $star_count = isset($star_counts[$i]) ? $star_counts[$i] : ($product_id ? VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::stars_count($i, $product_id) : 0);
                    $rate = (100 * ($star_count / $count_reviews));
                    if($rate==0){
                        $rate=1;/*MIn width is 1%*/
                    }
                }
                ?>
                <div class="<?php echo esc_attr($prefix_class); ?>wcpr-row">
                    <div class="<?php echo esc_attr($prefix_class); ?>wcpr-col-number"><?php echo esc_html($i); ?></div>
                    <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-col-star"><?php echo wc_get_rating_html( $i );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <div class="<?php echo esc_attr($prefix_class); ?>wcpr-col-process">
                        <div class="rate-percent-bg">
                            <div class="rate-percent" style="width: <?php echo esc_attr($rate); ?>%;"></div>
                            <div class="rate--count"><?php echo esc_html( $i) ?></div>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
    if ($overall_rating_enable === 'on') {
        ?>
        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-total-rating <?php echo esc_attr($prefix_class); ?>list-style">
            <div class="<?php echo esc_attr($prefix_class); ?>wcpr-overall-total-rating-main">
                <span class="wcpr-title1"><?php echo esc_html__('Total reviews', 'woocommerce-photo-reviews'); ?></span>
                <span class="wcpr-title2"><?php
                    /* translators: %s: review count */
                    printf(esc_html__('%s', 'woocommerce-photo-reviews'), esc_html($count_reviews));
                    ?>
                    </span>
                <span class="wcpr-title3"><?php echo esc_html__('Reviews on this product', 'woocommerce-photo-reviews'); ?></span>
            </div>
        </div>
        <?php
    }
    ?>
</div>
