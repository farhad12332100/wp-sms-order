<?php
if (!defined('ABSPATH')) {
    exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA {
    private $params;
    private $default;
    private static $prefix;
    private static $date_format;
    private static $time_format;
    protected static $instance = null;

    /**
     * VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA constructor.
     * Init setting
     */
    public function __construct() {
        self::$prefix = 'wcpr-';
        global $woo_photo_reviews_settings;
        if (!$woo_photo_reviews_settings) {
            $woo_photo_reviews_settings = get_option('_wcpr_nkt_setting', array());
            $woo_photo_reviews_settings['coupons'] = get_option('_wcpr_nkt_setting_coupons', array());
            $woo_photo_reviews_settings['share_reviews'] = get_option('_wcpr_nkt_setting_share_reviews', $woo_photo_reviews_settings['share_reviews'] ?? '[]');
        }
        $post_max = absint(ini_get('post_max_size'));
        $upload_max = absint(ini_get('upload_max_filesize'));
        $max_allow = $post_max > $upload_max ? $upload_max : $post_max;
        $maxsize = $max_allow > 2 ? (2000) : ($max_allow * 1000);
        $coupon_id = 'coupon_discount';
        $coupons = array(
            'enable' => 0,
            'ids' => array($coupon_id),
            'active' => array(1),
            'name' => array('Coupon For The Review'),
            'form_title' => 'Review our product to get a chance to receive coupon!',
            'require' => array(
                $coupon_id => array(
                    'photo' => 0,
                    'min_rating' => 0,
                    'owner' => 0,
                    'register' => 0,
                )
            ),
            'product_include' => array($coupon_id => array()),
            'product_exclude' => array($coupon_id => array()),
            'cats_include' => array($coupon_id => array()),
            'cats_exclude' => array($coupon_id => array()),
            'email_template' => array($coupon_id => ''),
            'email' => array(
                'from_address' => '',
                $coupon_id => array(
                    'subject' => 'Discount Coupon For Your Review',
                    'heading' => 'Thank You For Your Review!',
                    'content' => "Dear {customer_name},\nThank you so much for leaving review on my website!\nWe'd like to offer you this discount coupon as our thankfulness to you.\nCoupon code: {coupon_code}.\nDate expires: {date_expires}.\nYours sincerely!"
                )
            ),
            'coupon_select' => array('kt_generate_coupon'),
            'existing_coupon' => array($coupon_id => ''),
            'unique_coupon' => array(
                $coupon_id => array(
                    'discount_type' => 'percent',
                    'coupon_amount' => 11,
                    'allow_free_shipping' => 0,
                    'expiry_date' => null,
                    'min_spend' => '',
                    'max_spend' => '',
                    'individual_use' => 0,
                    'exclude_sale_items' => 0,
                    'limit_per_coupon' => 1,
                    'limit_to_x_items' => null,
                    'limit_per_user' => 0,
                    'product_ids' => array(),
                    'excluded_product_ids' => array(),
                    'product_categories' => array(),
                    'excluded_product_categories' => array(),
                    'coupon_code_prefix' => ''
                )
            ),
        );
        if (empty($woo_photo_reviews_settings['coupons']) && !empty($woo_photo_reviews_settings['coupon'])) {
            $coupon = $woo_photo_reviews_settings['coupon'];
            $coupons['enable'] = ($coupon['enable'] ?? '') === 'on' ? 1 : 0;
            $coupons['form_title'] = $coupon['form_title'] ?? $coupons['form_title'];
            $coupons['require'][$coupon_id] = array(
                'photo' => ($coupon['require']['photo'] ?? '') === 'on' ? 1 : 0,
                'min_rating' => $coupon['require']['min_rating'] ?? 0,
                'owner' => ($coupon['require']['owner'] ?? '') === 'on' ? 1 : 0,
                'register' => ($coupon['require']['register'] ?? '') === 'on' ? 1 : 0,
            );
            $coupons['product_include'][$coupon_id] = $coupon['products_gene'] ?? array();
            $coupons['product_exclude'][$coupon_id] = $coupon['excluded_products_gene'] ?? array();
            $coupons['cats_include'][$coupon_id] = $coupon['categories_gene'] ?? array();
            $coupons['cats_exclude'][$coupon_id] = $coupon['excluded_categories_gene'] ?? array();
            $coupons['email_template'][$coupon_id] = $woo_photo_reviews_settings['email_template'] ?? '';
            $coupons['email']['from_address'] = $coupon['email']['from_address'] ?? '';
            $coupons['email'][$coupon_id] = $coupon['email'] ?? $coupons['email'][$coupon_id];
            $coupons['coupon_select'][0] = $coupon['coupon_select'] ?? 'kt_generate_coupon';
            $coupons['existing_coupon'][$coupon_id] = $coupon['existing_coupon'] ?? '';
            $coupons['unique_coupon'][$coupon_id] = $coupon['unique_coupon'] ?? $coupons['unique_coupon'][$coupon_id];
        }
        $this->default = array(
            'enable' => 'on',
            'mobile' => 'on',
            'key' => '',
            'photo' => array(
                /*At first, this option must be 'on' to use all following features
                Now this is used to turn images field on/off
                */
                'enable' => 'on',
                'maxsize' => $maxsize,
                'maxfiles' => 5,
                'upload_images_requirement' => 'Choose pictures & videos(maxsize: {max_size}, max files: {max_files})',
                'upload_button_text' => 'Choose pictures & videos',
                'upload_button_display_type' => 'button',
                'upload_button_bg_color' => '#F3F3F3',
                'upload_button_color' => '#3E3E3E',
                'required' => 'off',
                'display' => 1,
                'masonry_popup' => 'review',
                'image_popup' => 'below_thumb',
                'full_image_size' => '',
                'crop_image_enable' => '1',
                'product_gallery_enable' => 0,
                'full_screen_mobile' => '',
                'display_mobile' => '',
                'col_num' => 3,
                'col_num_mobile' => 1,
                'grid_bg' => '',
                'grid_item_bg' => '#f3f3f3',
                'grid_item_border_color' => '',
                'comment_text_color' => '#000',
                'star_color' => '#ffb600',
                'max_content_length' => '150',
                'sort' => array(
                    'time' => 1
                ),
                'enable_box_shadow' => '1',
                'rating_count' => 'on',
                'rating_count_bar_color' => '#96588a',
                'filter' => array(
                    'enable' => 'on',
                    'area_border_color' => '#e5e5e5',
                    'area_bg_color' => '',
                    'button_border_color' => '#e5e5e5',
                    'button_color' => '',
                    'button_bg_color' => '',
                    'active_button_color' => '',
                    'active_button_bg_color' => '',
                ),
                'custom_css' => '',
                'review_tab_first' => 'off',
                'gdpr' => 'off',
                'gdpr_message' => 'I agree with the privacy policy',
                'overall_rating' => 'off',
                'single_product_summary' => 'off',
                'single_product_summary_ajax_atc' => '',
                'verified' => 'default',
                'verified_text' => 'Verified owner',
                'verified_badge' => 'woocommerce-photo-reviews-badge-tick',
                'verified_color' => '#29d50b',
                'verified_size' => '',
                'hide_name' => 'off',
                'number_character_show' => 4,
                'show_review_date' => '1',
                'custom_review_date_format' => '',
                'helpful_button_enable' => 1,
                'helpful_button_title' => 'Helpful?',
                'hide_rating_count_if_empty' => '',
                'hide_filters_if_empty' => '',
            ),
            'coupons' => $coupons,
            'followup_email' => array(
                'enable' => 'on',
                'loop' => 'off',
                'loop_time' => 7,
                'loop_repeat' => 2,
                'loop_run' => 9,
                'loop_email_template' => array(
                    'times' => array('1', '2'),
                    'email_template' => array(),
                ),
                'loop_email_content' => array(
                    'times' => array('1', '2'),
                    'email_content' => array(),
                ),
                'from_address' => '',
                'exclude_addresses' => array(),
                'subject' => 'Review our products to get discount coupon',
                'content' => "Dear {customer_name},\nThank you for your recent purchase from our company.\nWe’re excited to count you as a customer. Our goal is always to provide our very best product so that our customers are happy. It\’s also our goal to continue improving. That\’s why we value your feedback.\nThank you so much for taking the time to provide us feedback and review. This feedback is appreciated and very helpful to us.\nBest regards!",
                'heading' => 'Review our product now',
                'amount' => '',
                'unit' => 's',
                'products_restriction' => array(),
                'excluded_categories' => array(),
                'star_rating' => '',
                'review_button' => 'Write a review',
                'review_button_color' => '#ffffff',
                'exclude_non_coupon_products' => 'off',
                'review_button_bg_color' => '#88256f',
                'empty_product_price' => '',
                'auto_login' => '1',
                'auto_login_exclude' => array('administrator'),
                'review_form_page' => '',
                'order_statuses' => array('wc-completed'),
                'product_image_width' => '150',
            ),
            //new options-> checkbox value 1||0
            'pagination_ajax' => '',
            'loadmore_button' => 0,
            'reviews_container' => '',
            'reviews_anchor_link' => 'reviews',
            'set_email_restriction' => 1,
            'multi_language' => 0,
            /*image caption*/
            'image_caption_enable' => 0,
            'image_caption_position' => 'bottom_wide',
            'image_caption_color' => '#ffffff',
            'image_caption_bg_color' => 'rgba(1,1,1,0.4)',
            'image_caption_font_size' => '14',
            'custom_fields_enable' => 0,
            'custom_fields_from_variations' => 0,
            'custom_fields' => array(),
            'import_csv_date_format' => 'Y-m-d H:i:s',
            'import_csv_download_images' => '',
            'import_csv_download_videos' => '',
            'reviews_per_request' => '10',
            'search_id_by_sku' => '',
            'search_id_by_slug' => '',
            'allow_empty_comment' => '',
            'user_upload_folder' => '',
            'user_upload_prefix' => '',
            'import_upload_folder' => '',
            'import_upload_prefix' => '',
            'filter_default_image' => '',
            'filter_default_verified' => '',
            'filter_default_rating' => '',
            'show_review_country' => '',
            'review_title_enable' => '1',
            'review_title_placeholder' => 'Review Title',
            'thank_you_message' => 'Thank you so much for reviewing our product.',
            'thank_you_message_coupon' => 'Thank you for reviewing our product. A coupon code has been sent to your email address. Please check your mailbox for more details.',
            'phrases_filter' => array(
                'from_string' => array(),
                'to_string' => array(),
                'sensitive' => array(),
            ),
            'restrict_number_of_reviews' => '',
            'ajax_check_content_reviews' => '',
            'ajax_upload_file' => '',
            'reviews_order' => '',
            'reviews_order_new_tab' => '',
            'my_account_order_statuses' => array('wc-completed'),
//			'email_template'                 => '',
            'reminder_email_template' => '',
            'secret_key' => md5(time()),
            'search_product_by' => '_sku',
            'import_reviews_to' => array(),
            'import_reviews_status' => 0,
            'import_reviews_verified' => 1,
            'import_reviews_vote' => 0,
            'import_reviews_download_images' => 0,
            'import_reviews_download_videos' => 0,
            'import_reviews_order_info' => 0,
            'share_reviews' => '[[]]',
            'minimum_comment_length' => '',
            'auto_play_video' => 1,
            'upload_allow_images' => array(
                "image/jpg",
                "image/jpeg",
                "image/bmp",
                "image/png",
                "image/webp",
                "image/gif"
            ),
            'upload_allow_videos' => array("video/mp4", "video/webm", "video/quicktime"),
        );
        if (!isset($woo_photo_reviews_settings['secret_key'])) {
            $woo_photo_reviews_settings['secret_key'] = $this->default['secret_key'];
            update_option('_wcpr_nkt_setting', ['secret_key' => $woo_photo_reviews_settings['secret_key']]);
        }
        $this->params = apply_filters('_wcpr_nkt_setting', wp_parse_args($woo_photo_reviews_settings, $this->default));
    }

    public static function get_instance( $new = false ) {
        if ($new || null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function get_params( $name = "", $name_sub1 = "", $language = "" ) {
        if (!$name) {
            return $this->params;
        }
        if ($name === 'upload_allow') {
            return apply_filters('_wcpr_nkt_setting_' . $name, array_merge($this->params['upload_allow_images'] ?? array(), $this->params['upload_allow_videos'] ?? array()));
        }
        if (isset($this->params[$name])) {
            $language = apply_filters('_wcpr_nkt_setting_language', $language, $name, $name_sub1);
            if ($language && strpos($language, '_') !== 0) {
                $language = '_' . $language;
            }
            if ($name_sub1) {
                $name_language = $name_sub1 . $language;

                return apply_filters('_wcpr_nkt_setting_' . $name . '__' . $name_language, $this->params[$name][$name_language] ?? $this->params[$name][$name_sub1] ?? $this->default[$name] [$name_sub1] ?? null);
            } else {
                $name_language = $name . $language;

                return apply_filters('_wcpr_nkt_setting_' . $name_language, $this->params[$name_language] ?? $this->params[$name] ?? null);
            }
        }

        return null;
    }

    public function get_current_setting( $name = "", $name_sub1 = "", $i = 0, $language = "", $default = null ) {
        if (!$name) {
            return false;
        }
        if ($default !== null) {
            $result = $this->get_params($name, $name_sub1, $language)[$i] ?? $default;
        } else {
            $result = $this->get_params($name, $name_sub1, $language)[$i] ?? $this->get_default($name, $name_sub1)[$i] ?? false;
        }

        return $result;
    }

    public function get_default( $name = "", $name_sub1 = '' ) {
        if (!$name) {
            return $this->default;
        } elseif (isset($this->default[$name])) {
            if ($name_sub1) {
                if (isset($this->default[$name][$name_sub1])) {
                    return apply_filters('_wcpr_nkt_setting_default_' . $name . '__' . $name_sub1, $this->default[$name] [$name_sub1]);
                } else {
                    return false;
                }
            } else {
                return apply_filters('_wcpr_nkt_setting_default_' . $name, $this->default[$name]);
            }
        } else {
            return false;
        }
    }

    public static function get_date_format() {
        if (self::$date_format === null) {
            self::$date_format = get_option('date_format', 'F d, Y');
            if (!self::$date_format) {
                self::$date_format = 'F d, Y';
            }
        }

        return self::$date_format;
    }

    public static function get_time_format() {
        if (self::$time_format === null) {
            self::$time_format = get_option('time_format', 'H:i:s');
            if (!self::$time_format) {
                self::$time_format = 'H:i:s';
            }
        }

        return self::$time_format;
    }

    public static function get_datetime_format() {
        return self::get_date_format() . ' ' . self::get_time_format();
    }

    public static function get_the_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
//check ip from share internet
            $ip = wc_clean(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
//to check ip is pass from proxy
            $ip = wc_clean(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } else {
            $ip = wc_clean(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $ip;
    }

    public static function set( $name, $set_name = false ) {
        if (is_array($name)) {
            return implode(' ', array_map(array('VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA', 'set'), $name));
        } else {
            if ($set_name) {
                return esc_attr__(str_replace('-', '_', self::$prefix . $name));
            } else {
                return esc_attr__(self::$prefix . $name);
            }
        }
    }

    public static function search_product_statuses() {
        apply_filters('woocommerce_photo_reviews_search_product_statuses', current_user_can('edit_private_products') ? array(
            'private',
            'publish'
        ) : array('publish'));
    }

    public static function use_wc_order_table() {
        if (isset(self::get_instance()->params['viwcpr_use_wc_order_table'])) {
            return self::get_instance()->params['viwcpr_use_wc_order_table'];
        }
        $hpos = get_option('woocommerce_custom_orders_table_enabled') === 'yes' || get_option('woocommerce_feature_custom_order_tables_enabled') === 'yes';
        self::get_instance()->params['viwcpr_use_wc_order_table'] = $hpos && get_option('woocommerce_custom_orders_table_data_sync_enabled', 'no') === 'no';
        return self::get_instance()->params['viwcpr_use_wc_order_table'];
    }

    /**Count orders of a customer by product
     *
     * @param $product_id
     * @param $customer_email
     * @param $user_id
     *
     * @return int|string|null
     */
    public static function get_orders_count_by_product( $product_id, $customer_email, $user_id ) {
        global $wpdb;
        if (!$product_id || (!$customer_email && !$user_id)) {
            return 0;
        }
        $customer_data = array();
//		if ( is_email( $customer_email ) ) {
//			$customer_data[] = $customer_email;
//		} elseif ( $user_id ) {
//			$user = get_user_by( 'id', $user_id );
//			if ( isset( $user->user_email ) ) {
//				$customer_data[] = $user->user_email;
//			}
//		}
        if ($user_id) {
            $customer_data[] = $user_id;
        } elseif (is_email($customer_email)) {
            $customer_data[] = $customer_email;
        }
        if (empty($customer_data)) {
            return 0;
        }
        $customer_data = array_map('esc_sql', array_filter(array_unique($customer_data)));
        $statuses = array_map('esc_sql', wc_get_is_paid_statuses());
        if (self::use_wc_order_table()) {
            $customer_data = implode("','", $customer_data);
            $sql = "
			SELECT COUNT(im.meta_value) FROM {$wpdb->prefix}wc_orders AS p
			INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
			WHERE p.status IN ( 'wc-" . implode("','wc-", $statuses) . "' )
			AND ((p.billing_email IN ( '{$customer_data}' ))OR(p.customer_id IN ( '{$customer_data}' )) )
			AND im.meta_key IN ( '_product_id', '_variation_id' )
			AND im.meta_value = {$product_id} ";
        } else {
            $sql = "
			SELECT COUNT(im.meta_value) FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
			WHERE p.post_status IN ( 'wc-" . implode("','wc-", $statuses) . "' )
			AND pm.meta_key IN ( '_billing_email', '_customer_user' )
			AND im.meta_key IN ( '_product_id', '_variation_id' )
			AND im.meta_value = {$product_id}
			AND pm.meta_value IN ( '" . implode("','", $customer_data) . "' ) ";
        }
        $result = $wpdb->get_var($sql);
        return $result;
    }

    /**
     * @param $customer_email
     * @param $product_id
     * @param $rating
     *
     * @return array|int
     */
    public static function reviews_count_of_customer( $customer_email, $product_id, $user_id = 0, $rating = '' ) {
        $comment_count_args = array(
            'type' => 'review',
            'count' => true,
        );
        if ($user_id) {
            $comment_count_args['user_id'] = $user_id;
        } else {
            $comment_count_args['author_email'] = $customer_email;
        }
        if ($product_id) {
            $comment_count_args['post_id'] = $product_id;
        }
        if ($rating === '') {
            $comment_count_args['meta_query'] = array(
                'relation' => 'AND',
                array(
                    'key' => 'rating',
                    'compare' => 'EXISTS',
                )
            );
        } elseif ($rating !== false) {
            $comment_count_args['meta_query'] = array(
                'relation' => 'AND',
                array(
                    'key' => 'rating',
                    'value' => $rating,
                    'compare' => '=',
                )
            );
        }

        return get_comments($comment_count_args);
    }

    public static function is_email_template_customizer_active() {
        return (class_exists('WooCommerce_Email_Template_Customizer') || class_exists('Woo_Email_Template_Customizer'));
    }

    public static function search_product_by() {
        $instance = self::get_instance();
        $search_product_by = $instance->get_params('search_product_by');
        if (!$search_product_by) {
            $search_product_by = '_sku';
        }

        return $search_product_by;
    }

    public function get_setting_capability() {
        return apply_filters('viwcpr_setting_capability', 'manage_options');
    }

    public function reviews_orderby( $orderby ) {
        if (!is_array($orderby) || empty($orderby)) {
            $orderby = ['comment_date'];
            return $orderby;
        }
        $tmp = [];
        $default = [
            'comment_date' => 'comment_date',
            'comment_post_id' => 'comment_post_ID',
            'comment_id' => 'comment_ID',
            'comment_author' => 'comment_author',
            'wcpr_review_vote' => 'wcpr_review_vote',
        ];
        foreach ($orderby as $item) {
            if (isset($default[strtolower($item)])) {
                $tmp[] = $default[strtolower($item)];
            }
        }
        $orderby = $tmp;
        return $orderby;
    }
}