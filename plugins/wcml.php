<?php
if (!defined('ABSPATH')) {
    exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Plugins_Wcml {
    public static $settings, $is_pro, $enable, $cache = array();
    private static $review;

    public function __construct() {
        if (!is_plugin_active('sitepress-multilingual-cms/sitepress.php') ||
            !is_plugin_active('woocommerce-multilingual/wpml-woocommerce.php')) {
            return;
        }
        self::$settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
        if (!self::$settings->get_params('multi_language')) {
            return;
        }
        add_filter('woocommerce_photo_reviews_reminder_product_price', array(__CLASS__, 'get_price_html'), 10, 3);
        /*Show translated review content for reviews shortcode - masonry*/
        add_action('woocommerce_photo_reviews_shortcode_masonry_item_before_main_content', array($this, 'before_main_content'), 10, 2);
        add_action('woocommerce_photo_reviews_shortcode_masonry_item_after_main_content', array($this, 'after_main_content'), 10, 2);
        /*Show translated review content for reviews shortcode - grid*/
        add_action('woocommerce_photo_reviews_shortcode_grid_item_before_main_content', array($this, 'before_main_content'), 10, 2);
        add_action('woocommerce_photo_reviews_shortcode_grid_item_after_main_content', array($this, 'after_main_content'), 10, 2);
    }

    /**
     * Add hook
     *
     * @param $comment
     * @param $product
     */
    public function before_main_content( $comment, $product ) {
        self::$review = $comment;
        add_filter('wpml_translate_single_string', array(__CLASS__, 'wpml_translate_single_string'), 10, 3);
    }

    /**
     * Remove hook
     *
     * @param $comment
     * @param $product
     */
    public function after_main_content( $comment, $product ) {
        self::$review = null;
        remove_filter('wpml_translate_single_string', array(__CLASS__, 'wpml_translate_single_string'), 10);
    }

    /**
     * The problem is that comment_post_ID of review is changed to current product ID so use get_comment() to bypass this
     *
     * @param $reviewTranslation
     * @param $context
     * @param $string_name
     *
     * @return mixed|void
     */
    public static function wpml_translate_single_string( $reviewTranslation, $context, $string_name ) {
        if ($context === 'wcml-reviews') {
            $review = get_comment(self::$review->comment_ID);
            remove_filter('wpml_translate_single_string', array(__CLASS__, 'wpml_translate_single_string'), 10);
            $reviewTranslation = apply_filters(
                'wpml_translate_single_string',
                $review->comment_content,
                $context,
                'product-' . $review->comment_post_ID . '-review-' . $review->comment_ID
            );
            add_filter('wpml_translate_single_string', array(__CLASS__, 'wpml_translate_single_string'), 10, 3);
        }

        return $reviewTranslation;
    }

    public static function get_price_html( $price_html, $product, $order ) {
        if (!is_a($order, 'WC_Order') || !is_a($product, 'WC_Product')) {
            return $price_html;
        }
        global $woocommerce_wpml;
        $multi_currency_enabled = $woocommerce_wpml->settings['enable_multi_currency'] ?? '';
        if (!$multi_currency_enabled) {
            return $price_html;
        }
        $currency = $order->get_currency();
        $current_currency = get_woocommerce_currency();
        if ($currency !== $current_currency) {
            $currency_options = $woocommerce_wpml->get_setting('currency_options');
            if (!isset($currency_options[$currency])) {
                return $price_html;
            }
            $price = (float)$product->get_price();
            $price_t = apply_filters('wcml_formatted_price', $price, $currency);
            $language = $order->get_meta('wpml_language');
            if (!$language && function_exists('pll_get_post_language')) {
                $language = pll_get_post_language($order->get_id());
            }
            $suffix = apply_filters('wpml_translate_single_string', $product->get_price_suffix(), 'admin_texts_woocommerce_price_display_suffix', 'woocommerce_price_display_suffix', $language);
            if ($product->is_on_sale()) {
                $regular_price = floatval($product->get_regular_price());
                if ($regular_price > $price) {
                    $regular_price_t = apply_filters('wcml_formatted_price', floatval($product->get_regular_price()), $currency);
                    $price_html = wc_format_sale_price($regular_price_t, $price_t) . $suffix;
                } else {
                    $price_html = $price_t . $suffix;
                }
            } else {
                $price_html = $price_t . $suffix;
            }
        }

        return $price_html;
    }
}