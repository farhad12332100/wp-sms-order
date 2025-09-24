<?php

/**
 * Class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Orders
 *
 */
if (!defined('ABSPATH')) {
    exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Orders {
    protected $settings;
    public static $enable;

    public function __construct() {
        $this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
        add_filter('woocommerce_account_orders_columns', array($this, 'woocommerce_account_orders_columns'));
        add_action('woocommerce_my_account_my_orders_column_wcpr_reviews', array(
            $this,
            'add_track_button_on_my_account'
        ));
        if ($this->settings->get_params('reviews_order')) {
            //create new endpoint woo
            add_filter('woocommerce_get_query_vars', array($this, 'viwcpr_woocommerce_get_query_vars'), PHP_INT_MAX, 1);
            add_action('woocommerce_account_viwcpr_review_order_endpoint', array($this, 'viwcpr_review_order_endpoint'));
        }
    }

    public function enable() {
        if (self::$enable !== null) {
            return self::$enable;
        }
        if ($this->settings->get_params('enable') !== 'on') {
            return self::$enable = false;
        }
        //mobile detect
        $is_mobile = wp_is_mobile();
        if ($is_mobile && $this->settings->get_params('mobile') !== 'on') {
            return self::$enable = false;
        }

        return self::$enable = true;
    }

    public function wp_enqueue_scripts() {
        if (!$this->enable()) {
            return;
        }
        if (is_account_page()) {
            $suffix = WP_DEBUG ? '' : '.min';
            wp_enqueue_style('woocommerce-photo-reviews-frontend-orders', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'orders' . $suffix . '.css', '', VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION);
        }
    }

    public function woocommerce_account_orders_columns( $columns ) {
        if (!$this->enable()) {
            return $columns;
        }
        $columns['wcpr_reviews'] = esc_html__('Reviews', 'woocommerce-photo-reviews');

        return $columns;
    }

    public function viwcpr_woocommerce_get_query_vars( $query ) {
        if (!$this->enable()) {
            return $query;
        }
        $query['viwcpr_review_order'] = 'review_order';

        return $query;
    }

    public function viwcpr_review_order_endpoint() {
        if (!$this->enable()) {
            return;
        }
        $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : 0;
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $product_reviews = array();
        $line_items = array_values($order->get_items('line_item'));
        foreach ($line_items as $line_item) {
            $product_id = $line_item->get_product_id();
            if (in_array($product_id, $product_reviews) || !wc_get_product($product_id)) {
                continue;
            }
            $product_reviews[] = $product_id;
        }
        if (empty($product_reviews)) {
            return;
        }
        ?>
        <div class="viwcpr-review-order-wrap">
            <div class="viwcpr-review-order-info">
                <span class="viwcpr-review-order-title"><strong><?php printf(esc_html__('Review for order: #%s', 'woocommerce-photo-reviews'), $order->get_order_number()) ?></strong></span>
            </div>
            <div class="viwcpr-review-order-container">
                <?php
                foreach ($product_reviews as $product_id) {
                    echo do_shortcode("[woocommerce_photo_reviews_form product_id='{$product_id}']");
                }
                ?>
            </div>
            <div class="viwcpr-review-order-info">
                <span class="viwcpr-review-order-title"><strong><?php printf(esc_html__('Review for order: #%s', 'woocommerce-photo-reviews'), $order->get_order_number()) ?></strong></span>
                <span class="woocommerce-button button view viwcpr-review-order-submit"><?php esc_html_e('Submit', 'woocommerce-photo-reviews') ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * @param $order WC_Order
     *
     * @throws Exception
     */
    public function add_track_button_on_my_account( $order ) {
        if (!$this->enable()) {
            return;
        }
        if (!is_a($order, 'WC_Order')) {
            return;
        }
        $my_account_order_statuses = $this->settings->get_params('my_account_order_statuses');
        if (!in_array('wc-' . $order->get_status(), $my_account_order_statuses)) {
            return;
        }
        if ($this->settings->get_params('reviews_order')) {
            $count = 0;
            $product_reviews = array();
            $line_items = array_values($order->get_items('line_item'));
            foreach ($line_items as $line_item) {
                $product_id = $line_item->get_product_id();
                if (in_array($product_id, $product_reviews)) {
                    continue;
                }
                $product_reviews[] = $product_id;
                $product = wc_get_product($product_id);
                if ($product) {
                    $count++;
                }
            }
            $review_order_new_atb = $this->settings->get_params('reviews_order_new_tab');
            if ($count > 0) {
                ?>
                <div class="wcpr-rate-buttons-container">
                    <a class="woocommerce-button button view"
                       href="<?php echo esc_url(wc_get_account_endpoint_url("review_order") . "?order_id={$order->get_id()}") ?>"
                       title="<?php esc_attr_e('Rate', 'woocommerce-photo-reviews'); ?>"
                        <?php if (empty($review_order_new_atb)) { ?>
                            target="_blank"
                        <?php } ?>
                       rel="nofollow"><?php esc_html_e('Rate', 'woocommerce-photo-reviews') ?></a>
                </div>
                <?php
            }
            return;
        }
        $review_form_page = $this->settings->get_params('followup_email', 'review_form_page');
        $review_form_page_url = '';
        $language = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language();
        $wpml_active = $language && is_plugin_active('sitepress-multilingual-cms/sitepress.php');
        if ($review_form_page) {
            $review_form_page_url = get_permalink($review_form_page);
            if ($wpml_active) {
                $review_form_page_url = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_permalink_by_language($review_form_page_url, $language);
            }
        }
        $line_items = array_values($order->get_items('line_item'));
        $line_items_count = count($line_items);
        if ($line_items_count > 0) {
            $count = 0;
            $anchor_link = '#' . $this->settings->get_params('reviews_anchor_link');
            $product_reviews = array();
            ob_start();
            foreach ($line_items as $line_item) {
                /**
                 * $line_item WC_Order_item
                 */
                $product_id = $line_item->get_product_id();
                if (in_array($product_id, $product_reviews)) {
                    continue;
                }
                $product_reviews[] = $product_id;
                $product = wc_get_product($product_id);
                if ($product) {
                    $count++;
                    $review_link = $product->get_permalink() . $anchor_link;
                    if ($review_form_page_url) {
                        $review_link = add_query_arg(array(
                            'product_id' => $product_id,
                        ), $review_form_page_url);
                    }
                    $product_title = $product->get_title();
                    ?>
                    <a class="button wcpr-rate-button"
                       href="<?php echo esc_url($review_link) ?>"
                       target="_blank" title="<?php echo esc_attr($product_title); ?>"
                       rel="nofollow"><?php printf(esc_html__('Rate %s', 'woocommerce-photo-reviews'), $product_title) ?></a>
                    <?php
                }
            }
            $review_button = ob_get_clean();
            if ($count > 0) {
                ?>
                <div class="wcpr-rate-buttons-container">
                    <span class="woocommerce-button button view"><?php esc_html_e('Rate', 'woocommerce-photo-reviews') ?></span>
                    <span class="wcpr-rate-buttons">
                                <span class="wcpr-rate-button-container"><?php echo wp_kses_post($review_button); ?></span>
                        </span>
                </div>
                <?php
            }
        }
    }
}
