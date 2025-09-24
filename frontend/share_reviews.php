<?php
/**
 * Class VI_WOOCOMMERCE_PHOTO_REVIEWS_Share_Reviews
 *
 */
if (!defined('ABSPATH')) {
    exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Share_Reviews {
    protected static $settings;
    protected $comments;
    protected $quick_view;
    protected $frontend_style;
    protected static $products = array(), $product_group = array(), $share_group = array();
    protected static $wpml_all_languages = null;
    protected static $group_review_count = array(), $group_rating_counts = array(), $group_average_rating = array();

    public function __construct() {
        self::$settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        self::$share_group = villatheme_json_decode(self::$settings->get_params('share_reviews'));
        if ('off' !== self::$settings->get_params('enable') && is_array(self::$share_group) && count(self::$share_group)) {
            add_filter('woocommerce_product_get_rating_counts', array($this, 'woocommerce_product_get_rating_counts'), 10, 2);
            add_filter('woocommerce_product_get_review_count', array($this, 'woocommerce_product_get_review_count'), 10, 2);
            add_filter('woocommerce_product_get_average_rating', array($this, 'woocommerce_product_get_average_rating'), 10, 2);
            add_filter('woocommerce_photo_reviews_get_comments_arguments', array($this, 'woocommerce_photo_reviews_get_comments_arguments'), 10, 1);
            add_action('parse_comment_query', array($this, 'parse_comment_query'));
            add_filter('woocommerce_product_review_comment_form_args', array($this, 'woocommerce_product_review_comment_form_args'));
        }
    }

    /**
     * @param $product_id
     *
     * @return array|null
     */
    public static function get_products( $product_id ) {
        global $wpml_post_translations;
        if (isset(self::$products[$product_id])) {
            return self::$products[$product_id];
        }
        $result = array();
        foreach (self::$share_group as $k => $product_ids) {
            $current_ids = array($product_id);
            if (self::wpml_all_languages()) {
                $current_ids = array_unique(array_merge(array_values($wpml_post_translations->get_element_translations($product_id)), $current_ids));
            }
            if (count($product_ids) > 1 && count(array_intersect($current_ids, $product_ids))) {
                if (self::wpml_all_languages()) {
                    $wpml_product_ids = $product_ids;
                    foreach ($product_ids as $id) {
                        $wpml_product_ids = array_merge(array_values($wpml_post_translations->get_element_translations($id)), $wpml_product_ids);
                    }
                    $product_ids = array_values(array_merge($wpml_product_ids, $product_ids));
                }
                $result = array_values(array_unique(array_diff($product_ids, array($product_id))));

                self::$product_group[$product_id] = 'group-' . $k;
                break;
            }
        }

        return self::$products[$product_id] = $result;
    }

    public static function wpml_all_languages() {
        if (self::$wpml_all_languages === null) {
            global $wpml_post_translations;
            self::$wpml_all_languages = $wpml_post_translations && get_option('wcml_reviews_in_all_languages');
        }
        return self::$wpml_all_languages;
    }

    /**
     * @param $comment_form
     *
     * @return mixed
     */
    public function woocommerce_product_review_comment_form_args( $comment_form ) {
        global $product;
        if (is_product() && $product) {
            $product_id = $product->get_id();
            $products = $this->get_products($product_id);
            if (count($products) && !have_comments()) {
                foreach ($products as $key => $value) {
                    $pr = wc_get_product($value);
                    if ($pr) {
                        if ($pr->get_review_count('edit')) {
                            $comment_form['title_reply'] = esc_html__('Add a review', 'woocommerce-photo-reviews');
                            break;
                        }
                    }
                }
            }
        }

        return $comment_form;
    }

    /**
     * @param $vars
     *
     * @return mixed
     */
    public function parse_comment_query( $vars ) {
        if (!empty($vars->query_vars['post_id'])) {
            $product_id = $vars->query_vars['post_id'];
            $products = $this->get_products($product_id);
            if (count($products)) {
                $vars->query_vars['post__in'] = array_values(array_merge($products, array($product_id)));
                $vars->query_vars['post_id'] = '';
            }
            if (self::wpml_all_languages()) {
                add_filter('wpml_is_comment_query_filtered', array('VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Shortcode', 'wpml_is_comment_query_filtered'), PHP_INT_MAX);
            }
        } elseif (!empty($vars->query_vars['post__in'])) {
            $ids = $vars->query_vars['post__in'];
            foreach ($vars->query_vars['post__in'] as $id) {
                $products = $this->get_products($id);
                if (count($products)) {
                    $ids = array_merge($products, $ids);
                }
            }
            $vars->query_vars['post__in'] = array_unique($ids);
        }

        return $vars;
    }

    /**
     * @param $comments
     * @param $args
     *
     * @return array|int
     */
    public function woocommerce_photo_reviews_get_comments_arguments( $args ) {
        if (!empty($args['post_id'])) {
            $product_id = $args['post_id'];
            $products = $this->get_products($product_id);
            if (count($products)) {
                $args['post__in'] = array_merge($products, array($product_id));
                unset($args['post_id']);
            }
        }
        return $args;
    }

    /**
     * @param $value
     * @param $product WC_Product
     *
     * @return float|int
     */
    public function woocommerce_product_get_average_rating( $value, $product ) {
        if ($product) {
            $product_id = $product->get_id();
            if (!empty(self::$product_group[$product_id]) && isset(self::$group_average_rating[self::$product_group[$product_id]])) {
                return self::$group_average_rating[self::$product_group[$product_id]];
            }
            $products = $this->get_products($product_id);
            if (count($products) && !empty($ratings = $product->get_rating_counts())) {
                $count_review = 0;
                $total_rating = 0;
                foreach ($ratings as $k => $v) {
                    $count_review += $v;
                    $total_rating += $k * $v;
                }
                if ($count_review && $total_rating) {
                    $value = number_format($total_rating / $count_review, 2, '.', '');
                }
            }
            if (!empty(self::$product_group[$product_id])) {
                self::$group_average_rating[self::$product_group[$product_id]] = $value;
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @param $product WC_Product
     *
     * @return int
     */
    public function woocommerce_product_get_review_count( $value, $product ) {
        global $wpml_post_translations;
        if ($product) {
            $product_id = $product->get_id();
            if (!empty(self::$product_group[$product_id]) && isset(self::$group_review_count[self::$product_group[$product_id]])) {
                return self::$group_review_count[self::$product_group[$product_id]];
            }
            $products = $this->get_products($product_id);
            if (!empty(self::$product_group[$product_id]) && isset(self::$group_review_count[self::$product_group[$product_id]])) {
                return self::$group_review_count[self::$product_group[$product_id]];
            }
            if (count($products)) {
                $wpml_product_ids = array();
                if (self::wpml_all_languages()) {
                    $wpml_product_ids = array_values(array_diff($wpml_post_translations->get_element_translations($product_id), array($product_id)));
                }
                foreach ($products as $pr_id) {
                    $pr = wc_get_product($pr_id);
                    if ($pr) {
                        if (self::wpml_all_languages()) {
                            $wpml_product_ids = array_merge(array_values(array_diff($wpml_post_translations->get_element_translations($pr_id), array($pr_id))), $wpml_product_ids);
                        }
                        if (!in_array($pr_id, $wpml_product_ids)) {
                            $value += $pr->get_review_count('edit');
                        }
                    }
                }
            }
            if (!empty(self::$product_group[$product_id])) {
                self::$group_review_count[self::$product_group[$product_id]] = $value;
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @param $product WC_Product
     *
     * @return int
     */
    public function woocommerce_product_get_rating_counts( $value, $product ) {
        if (did_action('comment_post')) {
            return $value;
        }
        if ($product) {
            $product_id = $product->get_id();
            if (!empty(self::$product_group[$product_id]) && isset(self::$group_rating_counts[self::$product_group[$product_id]])) {
                return self::$group_rating_counts[self::$product_group[$product_id]];
            }
            $products = $this->get_products($product_id);
            if (!empty(self::$product_group[$product_id]) && isset(self::$group_rating_counts[self::$product_group[$product_id]])) {
                return self::$group_rating_counts[self::$product_group[$product_id]];
            }
            if (count($products)) {
                foreach ($products as $pr_id) {
                    $pr = wc_get_product($pr_id);
                    if ($pr) {
                        $rating_counts = $pr->get_rating_counts('edit');
                        if ($rating_counts) {
                            foreach ($rating_counts as $rating => $rating_count) {
                                if (!isset($value[$rating])) {
                                    $value[$rating] = $rating_count;
                                } else {
                                    $value[$rating] += $rating_count;
                                }
                            }
                        }
                    }
                }
            }
            if (!empty(self::$product_group[$product_id])) {
                self::$group_rating_counts[self::$product_group[$product_id]] = $value;
            }
        }

        return $value;
    }
}
