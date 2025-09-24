<?php
/**
 * Class VI_WooCommerce_Photo_Reviews_Frontend_Reviews
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Shortcode {
	protected $settings, $is_ajax;
	protected static     $frontend;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		self::$frontend = 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend';
		if ( 'on' === $this->settings->get_params( 'enable' ) ) {
			add_action( 'viwcpr_shortcode_get_template_basic_html', array(
				$this,
				'viwcpr_shortcode_get_template_basic_html'
			), 10, 1 );
			add_action( 'init', array( $this, 'shortcode_init' ) );
			add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'wp_enqueue_scripts_elementor' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_ajax_woocommerce_photo_reviews_shortcode_ajax_get_reviews', array( $this, 'ajax_get_reviews' ) );
			add_action( 'wp_ajax_nopriv_woocommerce_photo_reviews_shortcode_ajax_get_reviews', array( $this, 'ajax_get_reviews' ) );
		}
	}

	public static function wpml_is_comment_query_filtered( $filter ) {
		return false;
	}

	public function ajax_get_reviews() {
		$this->is_ajax = true;
		/*$reviews_shortcode = isset( $_REQUEST['reviews_shortcode'] ) ? json_decode( sanitize_text_field( wp_unslash( $_REQUEST['reviews_shortcode'] ) ), true ) : '';
		$shortcode_attrs   = array();
		if ( is_array( $reviews_shortcode ) && ! empty( $reviews_shortcode ) ) {
			foreach ( $reviews_shortcode as $key => $value ) {
				$shortcode_attrs[] = "{$key}='{$value}'";
			}
		}*/
		$reviews_shortcode_raw = isset( $_REQUEST['reviews_shortcode'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['reviews_shortcode'] ) ) : '';
		$decoded_data          = json_decode( $reviews_shortcode_raw, true );

		$allowed_attrs = array(
			'comments_per_page',
			'cols',
			'cols_mobile',
			'cols_gap',
			'use_single_product',
			'products',
			'products_status',
			'grid_bg_color',
			'grid_item_bg_color',
			'grid_item_border_color',
			'text_color',
			'star_color',
			'product_cat',
			'order',
			'orderby',
			'show_product',
			'filter',
			'pagination',
			'pagination_ajax',
			'pagination_next',
			'pagination_pre',
			'loadmore_button',
			'filter_default_image',
			'filter_default_verified',
			'filter_default_rating',
			'pagination_position',
			'conditional_tag',
			'custom_css',
			'masonry_popup',
			'image_popup',
			'ratings',
			'mobile',
			'style',
			'is_slide',
			'enable_box_shadow',
			'full_screen_mobile',
			'style_mobile',
			'overall_rating',
			'rating_count',
			'only_images',
			'area_border_color',
			'area_bg_color',
			'button_color',
			'button_bg_color',
			'button_border_color',
			'rating_count_bar_color',
			'verified_color',
			'hide_rating_count_if_empty',
			'hide_filters_if_empty',
			'is_elementor',
			'wpml_all_languages',
			'pll_all_languages',
			'language',
		);

		$shortcode_attrs = array();
		if ( is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
			foreach ( $decoded_data as $key => $value ) {
				if ( in_array( $key, $allowed_attrs, true ) ) {
					$shortcode_attrs[] = esc_attr( $key ) . "='" . esc_attr( $value ) . "'";
				}
			}
		}
		wp_send_json( array( 'html' => do_shortcode( '[wc_photo_reviews_shortcode ' . implode( ' ', $shortcode_attrs ) . ']' ) ) );
	}

	/**
	 * @param $atts
	 *
	 * @return string
	 */
	public function overall_rating_html( $atts ) {
		$arr = shortcode_atts( array(
			'product_id'            => '',
			'overall_rating_enable' => '',
			'rating_count_enable'   => '',
			'is_shortcode'          => true,
			'wpml_all_languages'    => 'on',
			'pll_all_languages'     => 'on',
		), $atts );
		if ( empty( $arr['product_id'] ) ) {
			global $product;
			$product_id = 0;
			if ( $product && is_a( $product, 'WC_Product' ) ) {
				$product_id = $product->get_id();
			}
			if ( ! $product_id ) {
				return '';
			}
			$arr['product_id'] = $product_id;
		}
		if ( $arr['product_id'] ) {
			$wpml_products = array( $arr['product_id'] );
			if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) && $arr['wpml_all_languages'] === 'on' ) {
				$languages = apply_filters( 'wpml_active_languages', null, null );
				if ( count( $languages ) ) {
					foreach ( $languages as $key => $language ) {
						$wpml_products[] = apply_filters(
							'wpml_object_id', $arr['product_id'], 'product', false, $key
						);
					}
				}
			} elseif ( class_exists( 'Polylang' ) && $arr['pll_all_languages'] === 'on' ) {
				/*Polylang*/
				$languages = pll_languages_list();
				foreach ( $languages as $language ) {
					$wpml_products[] = pll_get_post( $arr['product_id'], $language );
				}
			}
			$wpml_products = array_unique( $wpml_products );
			if ( count( $wpml_products ) > 1 ) {
				$arr['product_id'] = implode( ',', $wpml_products );
			}
		}
		if ( $arr['pll_all_languages'] === 'on' ) {
			villatheme_remove_object_filter( 'parse_comment_query', 'PLL_Frontend_Filters', 'parse_comment_query' );
			villatheme_remove_object_filter( 'comments_clauses', 'PLL_Frontend_Filters', 'comments_clauses' );
		}
		if ( $arr['wpml_all_languages'] === 'on' ) {
			add_filter( 'wpml_is_comment_query_filtered', array(
				$this,
				'wpml_is_comment_query_filtered'
			), PHP_INT_MAX );
		}
		if ( strpos( $arr['product_id'], ',' ) > 0 ) {
			$arr['product_id'] = explode( ',', $arr['product_id'] );
			//review count
			$reviews_count_args = array(
				'status'      => 'approve',
				'post_type'   => 'product',
				'post_status' => 'any',
				'number'      => 0,
				'count'       => true,
				'parent'      => 0,
				'post__in'    => $arr['product_id'],
			);
			$default_meta_query = array(
				'relation' => 'and'
			);
			$star_counts        = array();
			$total_rating       = 0;
			$total_rating_num   = 0;
			for ( $i = 1; $i < 6; $i ++ ) {
				$star_counts_args               = $reviews_count_args;
				$meta_query                     = $default_meta_query;
				$meta_query[]                   = array(
					'key'     => 'rating',
					'value'   => $i,
					'compare' => '=',
				);
				$star_counts_args['meta_query'] = $meta_query;
				$star_counts[ $i ]              = self::$frontend::get_comments( $star_counts_args );
				$total_rating                   += ( $star_counts[ $i ] * $i );
				$total_rating_num               += $star_counts[ $i ];
			}
			$average_rating = 0;
			if ( $total_rating_num ) {
				$average_rating = $total_rating / $total_rating_num;
			}
			$arr['count_reviews']  = $total_rating_num;
			$arr['star_counts']    = $star_counts;
			$arr['average_rating'] = $average_rating;
		} else {
			$product               = wc_get_product( $arr['product_id'] );
			$arr['star_counts']    = array();
			$arr['average_rating'] = $product->get_average_rating();
			$agrs                  = array(
				'post_id'  => $arr['product_id'],
				'count'    => true,
				'meta_key' => 'rating',
				'status'   => 'approve'
			);
			remove_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
			remove_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
			$arr['count_reviews'] = self::$frontend::get_comments( $agrs );
			add_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
			add_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
		}
		if ( ! wp_style_is( 'wcpr-shortcode-all-reviews-style' ) ) {
			wp_enqueue_style( 'wcpr-shortcode-all-reviews-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shortcode-style.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		$arr = apply_filters( 'wc_photo_reviews_overall_rating_args', $arr );
		ob_start();
		remove_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
		remove_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
		do_action( 'viwcpr_get_overall_rating_html', $arr );
		add_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
		add_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
		$html = ob_get_clean();

		return $html;
	}

	public function rating_html( $atts ) {
		$arr         = shortcode_atts( array(
			'product_id'   => '',
			'rating'       => '',
			'review_count' => 'on',
		), $atts );
		$rating_html = '';
		$rating      = $arr['rating'];
		if ( function_exists( 'wc_get_rating_html' ) ) {
			if ( ! wp_style_is( 'woocommerce-photo-reviews-rating-html-shortcode' ) ) {
				wp_enqueue_style( 'woocommerce-photo-reviews-rating-html-shortcode', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rating-html-shortcode.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
			}
			$review_count = 0;
			if ( $arr['review_count'] === 'on' || ! $rating ) {
				if ( $arr['product_id'] ) {
					$product_id = $arr['product_id'];
					$product    = wc_get_product( $product_id );
				} else {
					global $product;
				}
				if ( $product ) {
					$review_count = $arr['review_count'] === 'on' ? $product->get_review_count() : 0;
					$rating       = $rating ?: $product->get_average_rating();
				}
			}
			$rating_html = wc_get_template_html( 'viwcpr-shortcode-reviews-rating-html.php',
				array(
					'rating'              => $rating,
					'review_count'        => $review_count,
					'review_count_enable' => $arr['review_count'],
				),
				'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
				WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
		}

		return $rating_html;
	}

	public function viwcpr_shortcode_get_template_basic_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'basic/viwcpr-shortcode-template-basic-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function all_reviews_shortcode( $attrs ) {
		$arr       = shortcode_atts( array(
			'comments_per_page'          => 20,
			'cols'                       => $this->settings->get_params( 'photo', 'col_num' ),
			'cols_mobile'                => $this->settings->get_params( 'photo', 'col_num_mobile' ),
			'cols_gap'                   => '',
			'use_single_product'         => '',
			'products'                   => '',
			'products_status'            => 'publish',
			'grid_bg_color'              => '',
			'grid_item_bg_color'         => '',
			'grid_item_border_color'     => '',
			'text_color'                 => '',
			'star_color'                 => '',
			'product_cat'                => '',
			'order'                      => 'DESC',
			'orderby'                    => 'comment_date_gmt',
			'show_product'               => 'on',
			'filter'                     => 'on',
			'pagination'                 => 'on',
			'pagination_ajax'            => $this->settings->get_params( 'pagination_ajax' ) ? 'on' : 'off',
			'pagination_next'            => '',
			'pagination_pre'             => '',
			'loadmore_button'            => '',
			'filter_default_image'       => $this->settings->get_params( 'filter_default_image' ) ? 'on' : 'off',
			'filter_default_verified'    => $this->settings->get_params( 'filter_default_verified' ) ? 'on' : 'off',
			'filter_default_rating'      => $this->settings->get_params( 'filter_default_rating' ),
			'pagination_position'        => '',
			'conditional_tag'            => '',
			'custom_css'                 => '',
			'masonry_popup'              => 'review',
			'image_popup'                => 'below_thumb',
			'ratings'                    => '',
			'mobile'                     => 'on',
			'style'                      => 'masonry',
			'is_slide'                   => '',
			'enable_box_shadow'          => $this->settings->get_params( 'photo', 'enable_box_shadow' ) ? 'on' : 'off',
			'full_screen_mobile'         => $this->settings->get_params( 'photo', 'full_screen_mobile' ) ? 'on' : 'off',
			'style_mobile'               => '',
			'overall_rating'             => 'off',
			'rating_count'               => 'off',
			'only_images'                => 'off',
			'area_border_color'          => $this->settings->get_params( 'photo', 'filter' )['area_border_color'],
			'area_bg_color'              => $this->settings->get_params( 'photo', 'filter' )['area_bg_color'],
			'button_color'               => $this->settings->get_params( 'photo', 'filter' )['button_color'],
			'button_bg_color'            => $this->settings->get_params( 'photo', 'filter' )['button_bg_color'],
			'button_border_color'        => $this->settings->get_params( 'photo', 'filter' )['button_border_color'],
			'rating_count_bar_color'     => $this->settings->get_params( 'photo', 'rating_count_bar_color' ),
			'verified_color'             => $this->settings->get_params( 'photo', 'verified_color' ),
			'hide_rating_count_if_empty' => $this->settings->get_params( 'photo', 'hide_rating_count_if_empty' ) ? 'on' : 'off',
			'hide_filters_if_empty'      => $this->settings->get_params( 'photo', 'hide_filters_if_empty' ) ? 'on' : 'off',
			'is_elementor'               => 'no',
			'wpml_all_languages'         => 'off',
			'pll_all_languages'          => 'off',
			'language'                   => '',
		), $attrs );
		$is_mobile = wp_is_mobile();
		if ( $is_mobile && ! empty( $arr['mobile'] ) && $arr['mobile'] !== 'on' ) {
			return '';
		}
		if ( ! empty( $arr['conditional_tag'] ) && stristr( $arr['conditional_tag'], "(" ) && ! $this->is_ajax ) {
			$logic_value = $arr['conditional_tag'];
			if ( stristr( $logic_value, "return" ) === false ) {
				$logic_value = "return (" . $logic_value . ");";
			}
			try {
				$logic_show = eval( $logic_value );
			} catch ( \Error $e ) {
				trigger_error( $e->getMessage(), E_USER_WARNING );

				$logic_show = false;
			} catch ( \Exception $e ) {
				trigger_error( $e->getMessage(), E_USER_WARNING );

				$logic_show = false;
			}
			if ( ! $logic_show ) {
				return '';
			}
		}
		global $wcpr_shortcode_id, $wcpr_shortcode_count;
		if ( $wcpr_shortcode_id === null ) {
			$wcpr_shortcode_id = 1;
		} else {
			$wcpr_shortcode_id ++;
		}
		$wcpr_shortcode_count = true;
		$orderby              = $arr['orderby'] ? array_unique( explode( ',', $arr['orderby'] ) ) : array();
		$orderby              = $this->settings->reviews_orderby( $orderby );
		$sort_by_vote         = in_array( 'wcpr_review_vote', $orderby );
		if ( empty( $arr['language'] ) && VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language() ) {
			$arr['language'] = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language();
		}
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			if ( $arr['wpml_all_languages'] === 'on' ) {
				add_filter( 'wpml_is_comment_query_filtered', array( $this, 'wpml_is_comment_query_filtered' ), PHP_INT_MAX );
			} elseif ( ! empty( $arr['language'] ) && $arr['language'] != apply_filters( 'wpml_current_language', null ) ) {
				do_action( 'wpml_switch_language', $arr['language'] );
			}
		} elseif ( $arr['pll_all_languages'] === 'on' ) {
			villatheme_remove_object_filter( 'parse_comment_query', 'PLL_Frontend_Filters', 'parse_comment_query' );
			villatheme_remove_object_filter( 'comments_clauses', 'PLL_Frontend_Filters', 'comments_clauses' );
		}
		$custom_css = wp_unslash( $arr['custom_css'] );
		$arr        = array_map( 'strtolower', $arr );
		if ( empty( $arr['masonry_popup'] ) ) {
			$arr['masonry_popup'] = 'off';
		}
		if ( ! $is_mobile || ! $arr['style_mobile'] ) {
			$frontend_style = $arr['style'];
		} else {
			$frontend_style = $arr['style_mobile'];
		}
		$post_status = 'any';
		if ( $arr['products_status'] ) {
			$post_status = array_filter( explode( ',', ( $arr['products_status'] ) ), 'trim' );
		}
		$comments_per_page = intval( $arr['comments_per_page'] );
		$caption_enable    = $this->settings->get_params( 'image_caption_enable' );
		$paged             = isset( $_REQUEST['wcpr_page'] ) ? intval( sanitize_text_field( $_REQUEST['wcpr_page'] ) ) : 1;
		$query_image       = isset( $_REQUEST['wcpr_image'] ) ? sanitize_text_field( $_REQUEST['wcpr_image'] ) : '';
		$query_verified    = isset( $_REQUEST['wcpr_verified'] ) ? sanitize_text_field( $_REQUEST['wcpr_verified'] ) : '';
		$query_rating      = isset( $_REQUEST['wcpr_rating'] ) ? sanitize_text_field( $_REQUEST['wcpr_rating'] ) : '';
		$page_url          = remove_query_arg( array( 'action', 'reviews_shortcode' ), wp_kses_post( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		if ( $query_image ) {
			$query_image = in_array( $query_image, [ 'off', 'false' ] ) ? '' : 1;
			$page_url    = remove_query_arg( [ 'wcpr_image' ], $page_url );
			if ( $query_image ) {
				$page_url = add_query_arg( 'wcpr_image', 1, $page_url );
			}
		}
		if ( $query_verified ) {
			$query_verified = in_array( $query_image, [ 'off', 'false' ] ) ? '' : 1;
			$page_url       = remove_query_arg( [ 'wcpr_verified' ], $page_url );
			if ( $query_verified ) {
				$page_url = add_query_arg( 'wcpr_verified', 1, $page_url );
			}
		}
		if ( $query_rating ) {
			//			$query_rating = in_array($query_image,['off', 'false']) ?'' : 1;/*18-12-2024: Fixed Filter functionality not working correctly */
			$page_url = remove_query_arg( [ 'wcpr_rating' ], $page_url );
			if ( $query_rating ) {
				$page_url = add_query_arg( 'wcpr_rating', 1, $page_url );
			}
		}
		$shortcode_id         = "woocommerce-photo-reviews-shortcode-{$wcpr_shortcode_id}";
		$comment_args         = array(
			'viwcpr_shortcode' => 1,
			'status'           => 'approve',
			'post_type'        => 'product',
			'post_status'      => $post_status,
			'number'           => $comments_per_page,//comments per page
			'paged'            => $paged,// current page
			'offset'           => $offset = ( $paged - 1 ) * $comments_per_page,//start position=(paged-1)*number
			'parent'           => 0,
			'post__in'         => array(),
		);
		$find_comment_post_id = true;
		if ( ! $this->is_ajax ) {
			$comment_args['viwcpr_shortcode'] = 'non_ajax';
			if ( 'on' === $arr['pagination_ajax'] ) {
				$paged          = 1;
				$query_image    = $arr['filter_default_image'] === 'on' ? 1 : '';
				$query_verified = $arr['filter_default_verified'] === 'on' ? 1 : '';
				$query_rating   = $arr['filter_default_rating'];
			}
			if ( is_product() && 'on' === $arr['use_single_product'] ) {
				$find_comment_post_id = false;
				global $product;
				if ( $product ) {
					$arr['product_cat']       = '';
					$arr['products']          = $product->get_id();
					$comment_args['post_id']  = $arr['products'];
					$comment_args['post__in'] = array( $arr['products'] );
				} elseif ( get_the_ID() ) {
					$arr['products']          = get_the_ID();
					$comment_args['post_id']  = $arr['products'];
					$comment_args['post__in'] = array( $arr['products'] );
				}
			}
			$enqueue_args = [
				'style'  => [
					'wcpr-verified-badge-icon',
					'wcpr-shortcode-all-reviews-style',
					'wcpr-swipebox-css',
					'wcpr-shortcode-masonry-style',
					'wcpr-rotate-font-style',
					'wcpr-default-display-style',
				],
				'script' => [
					'wcpr-swipebox-js',
				],
			];
			if ( $this->settings->get_params( 'photo', 'helpful_button_enable' ) ) {
				$enqueue_args['style'][] = 'woocommerce-photo-reviews-vote-icons';
			}
			if ( $arr['is_slide'] === 'on' ) {
				$enqueue_args['style'][]  = 'woocommerce-photo-reviews-flexslider';
				$enqueue_args['script'][] = 'woocommerce-photo-reviews-flexslider';
			}
			foreach ( $enqueue_args as $k => $items ) {
				if ( $k === 'style' ) {
					foreach ( $items as $item ) {
						if ( ! wp_style_is( $item ) ) {
							wp_enqueue_style( $item );
						}
					}
				} else {
					foreach ( $items as $item ) {
						if ( ! wp_script_is( $item ) ) {
							wp_enqueue_script( $item );
						}
					}
				}
			}
			$shortcode_id_css = "#{$shortcode_id} ";
			$modal_class      = ".{$shortcode_id}-modal.shortcode-wcpr-modal-light-box ";
			if ( $caption_enable ) {
				$custom_css .= VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::add_inline_style(
					array(
						$shortcode_id_css . '.shortcode-reviews-images-wrap-right .shortcode-wcpr-review-image-caption',
						$shortcode_id_css . '#shortcode-reviews-content-left-main .shortcode-wcpr-review-image-container .shortcode-wcpr-review-image-caption',
						$shortcode_id_css . '.kt-reviews-image-container .big-review-images-content-container .wcpr-review-image-caption',
					),
					array( 'background-color', 'color', 'font-size' ),
					array(
						$this->settings->get_params( 'image_caption_bg_color' ),
						$this->settings->get_params( 'image_caption_color' ),
						$this->settings->get_params( 'image_caption_font_size' )
					),
					array( '!important', '!important', 'px !important' )
				);
			}
			if ( $arr['is_elementor'] !== 'yes' ) {
				if ( in_array( $frontend_style, [ 'masonry', 'grid', 'grid_layout_2' ] ) ) {
					if ( $arr['cols'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid.wcpr-grid-loadmore{grid-template-columns: repeat(' . absint( $arr['cols'] ) . ', 1fr) !important;}';
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid{column-count: ' . absint( $arr['cols'] ) . ' !important;}';
					}
					if ( $arr['cols_gap'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid.wcpr-grid-loadmore{grid-gap:' . absint( $arr['cols_gap'] ) . 'px !important;}';
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid{column-gap:' . absint( $arr['cols_gap'] ) . 'px !important;}';
					}
					if ( $arr['grid_bg_color'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid{background-color:' . $arr['grid_bg_color'] . ' !important;}';
					}
					if ( $arr['grid_item_bg_color'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid .shortcode-wcpr-grid-item{background-color:' . $arr['grid_item_bg_color'] . ' !important;}';
						$custom_css .= $modal_class . '.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap{background-color:' . $arr['grid_item_bg_color'] . ' !important;}';
					}
					if ( $arr['grid_item_border_color'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid .shortcode-wcpr-grid-item{border: 1px solid ' . $arr['grid_item_border_color'] . ' !important;}';
					}
					if ( $arr['text_color'] ) {
						$custom_css .= $shortcode_id_css . '.shortcode-wcpr-grid .shortcode-wcpr-grid-item{color:' . $arr['text_color'] . ';}';
						$custom_css .= $modal_class . '.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap>#shortcode-reviews-content-right{color:' . $arr['text_color'] . ' !important;}';
					}
					$custom_css .= '@media (max-width: 600px) {';
					$custom_css .= VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::add_inline_style(
						array(
							$shortcode_id_css . '.shortcode-wcpr-grid',
							$shortcode_id_css . '.shortcode-wcpr-grid.shortcode-wcpr-masonry-2-col',
							$shortcode_id_css . '.shortcode-wcpr-grid.shortcode-wcpr-masonry-3-col',
							$shortcode_id_css . '.shortcode-wcpr-grid.shortcode-wcpr-masonry-4-col',
							$shortcode_id_css . '.shortcode-wcpr-grid.shortcode-wcpr-masonry-5-col',
						),
						array( 'column-count', 'grid-template-columns' ),
						array( $arr['cols_mobile'], 'repeat(' . $arr['cols_mobile'] . ', 1fr)' ),
						array( '!important', '!important' )
					);
					$custom_css .= '}';
				}
				if ( 'on' === $arr['filter'] ) {
					$custom_css .= "{$shortcode_id_css} .shortcode-wcpr-filter-container{";
					if ( $arr['area_border_color'] ) {
						$custom_css .= "border:1px solid " . $arr['area_border_color'] . ' !important;';
					}
					if ( $arr['area_bg_color'] ) {
						$custom_css .= 'background-color:' . $arr['area_bg_color'] . ' !important;';
					}
					$custom_css .= "}";
					$custom_css .= "{$shortcode_id_css} .shortcode-wcpr-filter-container .shortcode-wcpr-filter-button{";
					if ( $arr['button_color'] ) {
						$custom_css .= 'color:' . $arr['button_color'] . ' !important;';
					}
					if ( $arr['button_bg_color'] ) {
						$custom_css .= 'background-color:' . $arr['button_bg_color'] . ' !important;';
					}
					if ( $arr['button_border_color'] ) {
						$custom_css .= 'border:1px solid ' . $arr['button_border_color'] . ' !important;';
					}
					$custom_css .= "}";
				}
				if ( 'on' === $arr['rating_count'] && $arr['rating_count_bar_color'] ) {
					$custom_css .= "{$shortcode_id_css}.rate-percent{background-color: {$arr['rating_count_bar_color']} !important;}";
				}
				if ( $arr['pagination_position'] ) {
					if ( in_array( $arr['pagination_position'], array( 'left', 'right', 'center' ) ) ) {
						$custom_css .= "{$shortcode_id_css}.shortcode-wcpr-pagination{text-align: {$arr['pagination_position']} !important;}";
					}
				}
				if ( ( $arr['verified_color'] ) ) {
					$custom_css .= "$shortcode_id_css.woocommerce-review__verified,$modal_class.woocommerce-review__verified{color: {$arr['verified_color']} !important;}";
				}
				if ( $arr['star_color'] ) {
					$custom_css .= "{$shortcode_id_css}.shortcode-wcpr-reviews .shortcode-wcpr-comments .star-rating::before,
				{$shortcode_id_css}.shortcode-wcpr-reviews .shortcode-wcpr-comments .star-rating span::before,
				{$shortcode_id_css}.shortcode-review-content-container .star-rating span:before,
				{$shortcode_id_css}.shortcode-review-content-container .star-rating:before,
				.woocommerce-photo-reviews-rating-html-shortcode .star-rating::before,
				.woocommerce-photo-reviews-rating-html-shortcode .star-rating span::before,
				{$modal_class}.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap .star-rating span:before,
				{$modal_class}.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap .star-rating:before,
				.woocommerce-photo-reviews-form-container .star-rating span:before,
				.woocommerce-photo-reviews-form-container .star-rating:before,
				.woocommerce-photo-reviews-form-container .stars a:before,
				.woocommerce-photo-reviews-form-container .stars a:hover:after,
				.woocommerce-photo-reviews-form-container .stars a.active:after,
				{$shortcode_id_css}.shortcode-wcpr-overall-rating-right .shortcode-wcpr-overall-rating-right-star .star-rating:before,
				{$shortcode_id_css}.shortcode-wcpr-overall-rating-right .shortcode-wcpr-overall-rating-right-star .star-rating span:before,
				{$shortcode_id_css}.shortcode-wcpr-stars-count .shortcode-wcpr-row .shortcode-wcpr-col-star .star-rating:before,
				{$shortcode_id_css}.shortcode-wcpr-stars-count .shortcode-wcpr-row .shortcode-wcpr-col-star .star-rating span:before{color:{$arr['star_color']} !important;}";
				}
			} else {
				if ( ( $arr['verified_color'] ) ) {
					$custom_css .= "$modal_class.woocommerce-review__verified{color: {$arr['verified_color']} !important;}";
				}
				if ( $arr['star_color'] ) {
					$custom_css .= ".woocommerce-photo-reviews-rating-html-shortcode .star-rating::before,
				.woocommerce-photo-reviews-rating-html-shortcode .star-rating span::before,
				{$modal_class}.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap .star-rating span:before,
				{$modal_class}.shortcode-wcpr-modal-light-box-wrapper .shortcode-wcpr-modal-wrap .star-rating:before,
				.woocommerce-photo-reviews-form-container .star-rating span:before,
				.woocommerce-photo-reviews-form-container .star-rating:before,
				.woocommerce-photo-reviews-form-container .stars a:before,
				.woocommerce-photo-reviews-form-container .stars a:hover:after,
				.woocommerce-photo-reviews-form-container .stars a.active:after{color:{$arr['star_color']} !important;}";
				}
			}
			wp_add_inline_style( 'wcpr-shortcode-all-reviews-style', $custom_css );
		}
		if ( $find_comment_post_id ) {
			$review_form_product = isset( $_GET['product_id'] ) ? sanitize_text_field( $_GET['product_id'] ) : '';
			if ( $review_form_product ) {
				if ( defined( 'POLYLANG_VERSION' ) ) {
					if ( $arr['pll_all_languages'] === 'on' ) {
						$product_ids              = pll_get_post_translations( $review_form_product );
						$arr['products']          = implode( ',', $product_ids );
						$comment_args['post__in'] = $product_ids;
					} else {
						$review_form_product      = pll_get_post( $review_form_product );
						$comment_args['post_id']  = $review_form_product;
						$comment_args['post__in'] = array( $review_form_product );
						$arr['products']          = $review_form_product;
					}
				} else {
					$comment_args['post_id']  = $review_form_product;
					$comment_args['post__in'] = array( $review_form_product );
					$arr['products']          = $review_form_product;
				}
			} else {
				$product_ids = array();
				if ( $arr['products'] ) {
					$product_ids = array_filter( explode( ',', ( $arr['products'] ) ), 'trim' );
				}
				if ( $arr['product_cat'] ) {
					$cats = array_filter( explode( ',', ( $arr['product_cat'] ) ), 'trim' );
					if ( count( $cats ) ) {
						$products_args = [
							'return'         => 'ids',
							'posts_per_page' => - 1,
						];
						if ( is_numeric( $cats[0] ) ) {
							$tmp = [];
							foreach ( $cats as $cat_id ) {
								$term = get_term( $cat_id );
								if ( $term ) {
									$tmp[] = $term->slug;
								}
							}
							$cats = $tmp;
						}
						$products_args['category'] = $cats;
						$products                  = wc_get_products( $products_args );
						$product_ids               = array_merge( $products, $product_ids );
					}
				}
				$comment_args['post__in'] = array_unique( $product_ids );
			}
		}
		$share_reviews = $this->settings->get_params( 'share_reviews' );
		if ( $share_reviews && count( villatheme_json_decode( $share_reviews ) ) && ! empty( $comment_args['post__in'] ) ) {
			$share_review = array();
			if ( ! isset( $arr['share_review_ids'] ) ) {
				foreach ( $comment_args['post__in'] as $pd_id ) {
					$share_review_ids = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Share_Reviews::get_products( $pd_id );
					$share_review     = array_unique( array_merge( $share_review_ids, $share_review ) );
				}
				$arr['share_review_ids'] = implode( ',', $share_review );
				$share_review            = array_unique( array_merge( $comment_args['post__in'], $share_review ) );
			} elseif ( $arr['share_review_ids'] ) {
				$share_review = explode( ',', $arr['share_review_ids'] );
			}
			$comment_args['post__in'] = count( $share_review ) ? array_unique( array_merge( $comment_args['post__in'], $share_review ) ) : $comment_args['post__in'];
		}
		if ( is_array( $comment_args['post__in'] ) && count( $comment_args['post__in'] ) ) {
			$product_ids = array();
			if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) && $arr['wpml_all_languages'] === 'on' ) {
				$languages = apply_filters( 'wpml_active_languages', null, null );
				if ( count( $languages ) ) {
					foreach ( $comment_args['post__in'] as $id ) {
						foreach ( $languages as $key => $language ) {
							$product_ids[] = apply_filters( 'wpml_object_id', $id, 'product', false, $key );
						}
					}
				}
			} elseif ( class_exists( 'Polylang' ) && $arr['pll_all_languages'] === 'on' ) {
				$languages = pll_languages_list();
				foreach ( $comment_args['post__in'] as $id ) {
					foreach ( $languages as $language ) {
						$product_ids[] = pll_get_post( $id, $language );
					}
				}
			}
			$comment_args['post__in'] = array_unique( array_merge( $product_ids, $comment_args['post__in'] ) );
		}
		$default_meta_query = array( 'relation' => 'and' );
		if ( $sort_by_vote ) {
			$default_meta_query[] = array(
				array(
					'key' => 'wcpr_vote_up_count'
				)
			);
		}
		if ( 'on' === $arr['only_images'] ) {
			$default_meta_query []      = array(
				'key'     => 'reviews-images',
				'compare' => 'EXISTS',
			);
			$comment_args['meta_query'] = $default_meta_query;
		}
		if ( $arr['ratings'] ) {
			$include_rating        = array_filter( explode( ',', ( $arr['ratings'] ) ), 'trim' );
			$default_meta_query [] = array(
				'key'     => 'rating',
				'value'   => $include_rating,
				'compare' => 'IN',
			);
		}
		/*Adjust comment count arguments*/
		$comment_count_args = array_merge( $comment_args, array(
			'number' => 0,
			'count'  => true,
		) );
		unset( $comment_count_args['paged'] );
		unset( $comment_count_args['offset'] );
		//review count
		$reviews_count_args = $all_rating_count = $comment_count_args;
		$star_counts        = array();
		$average_rating     = $total_rating = $total_rating_num = 0;
		if ( count( $orderby ) ) {
			$orderby_arr = array();
			$order       = $arr['order'] ?? 'DESC';
			foreach ( $orderby as $item ) {
				if ( $item === 'wcpr_review_vote' ) {
					$orderby_arr['meta_value_num'] = $order;
					if ( ! isset( $comment_args['meta_query'] ) ) {
						$comment_args['meta_query']       = $default_meta_query;
						$comment_count_args['meta_query'] = $default_meta_query;
					}
				} else {
					$orderby_arr[ $item ] = $order;
				}
			}
			$comment_args['orderby'] = $orderby_arr;
		}
		if ( $arr['filter'] === 'on' ) {
			$rating = 0;
			if ( in_array( $query_rating, [ 1, 2, 3, 4, 5 ] ) ) {
				$rating = $query_rating;
			}
			$meta_query = $default_meta_query;
			if ( ! empty( $query_verified ) ) {
				$meta_query[] = array(
					'key'   => 'verified',
					'value' => 1
				);
			}
			if ( ! empty( $query_image ) ) {
				$meta_query[] = array(
					'key'     => 'reviews-images',
					'compare' => 'EXISTS'
				);
			}
			$all_rating_count['meta_query'] = $meta_query;//count reviews of all ratings
			if ( $rating ) {
				$meta_query[] = array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '='
				);
			}
			$comment_args['meta_query']       = $meta_query;
			$comment_count_args['meta_query'] = $meta_query;
		}
		$my_comments     = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( apply_filters( 'woocommerce_photo_reviews_shortcode_comment_args', $comment_args ) );
		$count_reviews   = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( apply_filters( 'woocommerce_photo_reviews_shortcode_comment_count_args', $comment_count_args ) );
		$max_num_pages   = $comments_per_page ? ceil( $count_reviews / $comments_per_page ) : 1;
		$loadmore_button = $arr['loadmore_button'] === 'on' && $arr['pagination_ajax'] === 'on' && $arr['is_slide'] !== 'on';
		ob_start();
		if ( ! $this->is_ajax ) {
			$data_arr = $arr;
			unset( $data_arr['custom_css'] );
			$data_arr = wp_json_encode( $data_arr );
			$data_arr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $data_arr ) : _wp_specialchars( $data_arr, ENT_QUOTES, 'UTF-8', true );
			printf( '<div id="%s" class="%s" data-wcpr_image="%s" data-wcpr_verified="%s" data-wcpr_rating="%s" data-wcpr_slide="%s"  data-reviews_shortcode="%s">',
				$shortcode_id, esc_attr( 'woocommerce-photo-reviews-shortcode woocommerce-photo-reviews-shortcode-popup-' . $arr['masonry_popup'] ),
				$query_image, $query_verified, $query_rating, esc_attr( $arr['is_slide'] === 'on' ? 1 : '' ), esc_attr( $data_arr )
			);
		}
		if ( ( $arr['overall_rating'] === 'on' || $arr['rating_count'] === 'on' ) && ( $count_reviews || $arr['hide_rating_count_if_empty'] !== 'on' ) ) {
			for ( $i = 1; $i < 6; $i ++ ) {
				$star_counts_args               = $reviews_count_args;
				$meta_query                     = $default_meta_query;
				$meta_query[]                   = array(
					'key'     => 'rating',
					'value'   => $i,
					'compare' => '=',
				);
				$star_counts_args['meta_query'] = $meta_query;
				$star_counts[ $i ]              = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( $star_counts_args );
				$total_rating                   += ( $star_counts[ $i ] * $i );
				$total_rating_num               += $star_counts[ $i ];
			}
			if ( $total_rating_num ) {
				$average_rating = $total_rating / $total_rating_num;
			}
			do_action( 'viwcpr_get_overall_rating_html', array(
				'product_id'            => empty( $comment_args['post__in'] ) ? ( $comment_args['post_id'] ?? '' ) : $comment_args['post__in'],
				'average_rating'        => $average_rating,
				'count_reviews'         => $total_rating_num,
				'overall_rating_enable' => $arr['overall_rating'],
				'rating_count_enable'   => $arr['rating_count'],
				'star_counts'           => $star_counts,
				'is_shortcode'          => true,
			) );
		}
		if ( $arr['filter'] === 'on' && ( $count_reviews > 0 || $arr['hide_filters_if_empty'] !== 'on' ) ) {
			//			stars count
			$star_counts = [];
			if ( apply_filters( 'viwcpr-filter-by-rating', true ) ) {
				for ( $k = 5; $k > 0; $k -- ) {
					$comment_count_args_k = $comment_count_args;
					$meta_query_k         = $default_meta_query;
					if ( ! empty( $query_verified ) ) {
						$meta_query_k[] = array(
							'key'   => 'verified',
							'value' => 1
						);
					}
					if ( ! empty( $query_image ) ) {
						$meta_query_k[] = array(
							'key'     => 'reviews-images',
							'compare' => 'EXISTS'
						);
					}
					$meta_query_k[]                     = array(
						'key'     => 'rating',
						'value'   => $k,
						'compare' => '='
					);
					$comment_count_args_k['meta_query'] = $meta_query_k;
					$star_counts[ $k ]                  = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( $comment_count_args_k );
				}
			}
			//image count
			$comment_count_args_image = $comment_count_args;
			$meta_query_image         = $default_meta_query;
			if ( $rating ) {
				$meta_query_image[] = array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '='
				);
			} else {
				$meta_query_image[] = array(
					'key'     => 'rating',
					'compare' => 'EXISTS'
				);
			}
			if ( $query_verified == 1 ) {
				$meta_query_image[] = array(
					'key'   => 'verified',
					'value' => 1
				);
			}
			$meta_query_image[]                     = array(
				'key'     => 'reviews-images',
				'compare' => 'EXISTS'
			);
			$comment_count_args_image['meta_query'] = $meta_query_image;
			$count_images                           = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( $comment_count_args_image );
			//			verified count
			$comment_count_args_verified = $comment_count_args;
			$meta_query_verified         = $default_meta_query;
			if ( ! empty( $query_image ) ) {
				$meta_query_verified[] = array(
					'key'     => 'reviews-images',
					'compare' => 'EXISTS'
				);
			}
			if ( $rating ) {
				$meta_query_verified[] = array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '='
				);
			} else {
				$meta_query_verified[] = array(
					'key'     => 'rating',
					'compare' => 'EXISTS'
				);
			}
			$meta_query_verified[]                     = array(
				'key'   => 'verified',
				'value' => 1
			);
			$comment_count_args_verified['meta_query'] = $meta_query_verified;
			$count_verified                            = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( $comment_count_args_verified );
			do_action( 'viwcpr_get_filters_html', array(
				'settings'       => $this->settings,
				'product_id'     => empty( $comment_args['post__in'] ) ? ( $comment_args['post_id'] ?? '' ) : $comment_args['post__in'],
				'count_reviews'  => VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_comments( $all_rating_count ),
				'star_counts'    => $star_counts,
				'count_images'   => $count_images,
				'count_verified' => $count_verified,
				'query_rating'   => $rating,
				'query_verified' => $query_verified,
				'query_image'    => $query_image,
				'product_link'   => $page_url,
				'is_shortcode'   => true,
			) );
		}
		if ( is_array( $my_comments ) && count( $my_comments ) ) {
			$pagination_html = '';
			if ( 'on' === $arr['pagination'] && $max_num_pages > 1 && $arr['is_slide'] !== 'on' ) {
				if ( $loadmore_button && $max_num_pages > $paged ) {
					ob_start();
					do_action( 'viwcpr_get_pagination_loadmore_html', array(
						'settings'     => $this->settings,
						'only_button'  => true,
						'cpage'        => $paged + 1,
						'is_shortcode' => true,
					) );
					$pagination_html = ob_get_clean();
				} elseif ( ! $loadmore_button ) {
					$pagination_html = wc_get_template_html(
						'basic/viwcpr-pagination-basic-html.php',
						array(
							'max_num_pages' => $max_num_pages,
							'paged'         => $paged,
							'page_url'      => $page_url,
							'pre'           => $arr['pagination_pre'],
							'next'          => $arr['pagination_next'],
							'is_shortcode'  => true,
						),
						'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
						WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES
					);
				}
			}
			if ( $arr['is_slide'] === 'on' ) {
				printf( '<div class="wcpr-hidden wcpr-reviews-total-pages">%s</div>', esc_attr( $max_num_pages ) );
			}
			printf( '%s ', $loadmore_button ? '' : $pagination_html );
			switch ( $frontend_style ) {
				case 'grid':
					do_action( 'viwcpr_get_template_grid_html', array(
						'settings'          => $this->settings,
						'my_comments'       => $my_comments,
						'cols'              => $arr['cols'],
						'masonry_popup'     => $arr['masonry_popup'],
						'enable_box_shadow' => $arr['enable_box_shadow'] === 'on',
						'show_product'      => $arr['show_product'],
						'loadmore_button'   => $loadmore_button,
						'is_shortcode'      => true,
					) );
					break;
				case 'grid_layout_2':
					do_action( 'viwcpr_get_template_grid_layout_2_html', array(
						'settings'          => $this->settings,
						'my_comments'       => $my_comments,
						'cols'              => $arr['cols'],
						'masonry_popup'     => $arr['masonry_popup'],
						'enable_box_shadow' => $arr['enable_box_shadow'] === 'on',
						'show_product'      => $arr['show_product'],
						'loadmore_button'   => $loadmore_button,
						'is_shortcode'      => true,
					) );
					break;
				case 'masonry':
					do_action( 'viwcpr_get_template_masonry_html', array(
						'settings'          => $this->settings,
						'my_comments'       => $my_comments,
						'cols'              => $arr['cols'],
						'masonry_popup'     => $arr['masonry_popup'],
						'enable_box_shadow' => $arr['enable_box_shadow'] === 'on',
						'show_product'      => $arr['show_product'],
						'loadmore_button'   => $loadmore_button,
						'is_shortcode'      => true,
					) );
					break;
				default:
					do_action( 'viwcpr_shortcode_get_template_basic_html', array(
						'settings'         => $this->settings,
						'my_comments'      => $my_comments,
						'image_popup_type' => $arr['image_popup'],
						'caption_enable'   => $caption_enable,
					) );
					break;
			}
			printf( '%s', $pagination_html );
		}
		printf( '<div class="wcpr-shortcode-overlay"></div>' );
		if ( ! $this->is_ajax ) {
			printf( '</div>' );
		}
		$return               = ob_get_clean();
		$wcpr_shortcode_count = false;

		return $return;
	}

	public function shortcode_init() {
		add_shortcode( 'wc_photo_reviews_shortcode', array( $this, 'all_reviews_shortcode' ) );
		add_shortcode( 'wc_photo_reviews_rating_html', array( $this, 'rating_html' ) );
		add_shortcode( 'wc_photo_reviews_overall_rating_html', array( $this, 'overall_rating_html' ) );
	}

	public function wp_enqueue_scripts_elementor() {
		$suffix = WP_DEBUG ? '' : 'min.';
		wp_enqueue_style( 'wcpr-verified-badge-icon', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-badge.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_style( 'wcpr-shortcode-all-reviews-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shortcode-style.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_style( 'woocommerce-photo-reviews-vote-icons', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-vote-icons.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_script( 'wcpr-swipebox-js', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'swipebox.' . $suffix . 'js', array( 'jquery' ) );
		wp_enqueue_style( 'wcpr-swipebox-css', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'swipebox.' . $suffix . 'css' );
		wp_enqueue_style( 'wcpr-shortcode-masonry-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shortcode-masonry.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_style( 'wcpr-rotate-font-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rotate.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_style( 'wcpr-default-display-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'default-display-images.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_script( 'wcpr-default-display-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'default-display-images.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_script( 'woocommerce-photo-reviews-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'script.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		$woocommerce_photo_reviews_params = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Single_Page::get_enqueue_params();
		wp_localize_script( 'woocommerce-photo-reviews-script', 'woocommerce_photo_reviews_params', $woocommerce_photo_reviews_params );
		wp_enqueue_script( 'woocommerce-photo-reviews-shortcode-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'shortcode-script.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_localize_script( 'woocommerce-photo-reviews-shortcode-script', 'woocommerce_photo_reviews_shortcode_params', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
		wp_enqueue_style( 'woocommerce-photo-reviews-rating-html-shortcode', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rating-html-shortcode.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
	}


	public function wp_enqueue_scripts() {
		$suffix = WP_DEBUG ? '' : 'min.';
		if ( ! wp_style_is( 'wcpr-verified-badge-icon', 'registered' ) ) {
			wp_register_style( 'wcpr-verified-badge-icon', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-badge.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_style_is( 'wcpr-shortcode-all-reviews-style', 'registered' ) ) {
			wp_register_style( 'wcpr-shortcode-all-reviews-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shortcode-style.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( $this->settings->get_params( 'photo', 'helpful_button_enable' ) && ! wp_style_is( 'woocommerce-photo-reviews-vote-icons' ) ) {
			wp_register_style( 'woocommerce-photo-reviews-vote-icons', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-vote-icons.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_style_is( 'wcpr-swipebox-js', 'registered' ) ) {
			wp_register_script( 'wcpr-swipebox-js', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'swipebox.' . $suffix . 'js', array( 'jquery' ) );
			wp_register_style( 'wcpr-swipebox-css', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'swipebox.' . $suffix . 'css' );
		}
		if ( ! wp_style_is( 'wcpr-shortcode-masonry-style', 'registered' ) ) {
			wp_register_style( 'wcpr-shortcode-masonry-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shortcode-masonry.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_style_is( 'wcpr-rotate-font-style', 'registered' ) ) {
			wp_register_style( 'wcpr-rotate-font-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rotate.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_style_is( 'wcpr-default-display-style', 'registered' ) ) {
			wp_register_style( 'wcpr-default-display-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'default-display-images.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_script_is( 'wcpr-default-display-script' ) ) {
			wp_enqueue_script( 'wcpr-default-display-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'default-display-images.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_script_is( 'woocommerce-photo-reviews-script' ) ) {
			wp_enqueue_script( 'woocommerce-photo-reviews-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'script.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
			$woocommerce_photo_reviews_params = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Single_Page::get_enqueue_params();
			wp_localize_script( 'woocommerce-photo-reviews-script', 'woocommerce_photo_reviews_params', $woocommerce_photo_reviews_params );
		}
		if ( ! wp_script_is( 'woocommerce-photo-reviews-flexslider' ) ) {
			wp_register_style( 'woocommerce-photo-reviews-flexslider', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'flexslider.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
			wp_register_script( 'woocommerce-photo-reviews-flexslider', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'flexslider.min.js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		if ( ! wp_script_is( 'woocommerce-photo-reviews-shortcode-script' ) ) {
			add_action( 'wp_footer', array( $this, 'quickview' ) );
			wp_enqueue_script( 'woocommerce-photo-reviews-shortcode-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'shortcode-script.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
			wp_localize_script( 'woocommerce-photo-reviews-shortcode-script', 'woocommerce_photo_reviews_shortcode_params', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
				)
			);
		}
		if ( ! wp_style_is( 'woocommerce-photo-reviews-rating-html-shortcode', 'registered' ) ) {
			wp_register_style( 'woocommerce-photo-reviews-rating-html-shortcode', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rating-html-shortcode.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
	}

	public function quickview() {
		wc_get_template( 'viwcpr-quickview-template-html.php',
			array(
				'is_shortcode' => true
			),
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}
}