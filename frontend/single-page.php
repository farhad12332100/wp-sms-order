<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Single_Page {
	protected static $settings, $frontend, $frontend_style;
	protected                              $is_mobile, $single_product_id;
	protected                              $anchor_link, $quick_view;

	public function __construct() {
		self::$settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		self::$frontend = 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend';
		if ( self::$settings->get_params( 'enable' ) !== 'on' ) {
			return;
		}
		//mobile detect
		$this->is_mobile = wp_is_mobile();
		if ( $this->is_mobile && self::$settings->get_params( 'mobile' ) !== 'on' ) {
			return;
		}
		//review from reminder email
		add_action( 'init', array( $this, 'wcpr_reminder_review' ) );
		$this->anchor_link = '#' . self::$settings->get_params( 'reviews_anchor_link' );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );
		//move tab reviews to first position
		add_filter( 'woocommerce_product_tabs', array( $this, 'show_reviews_tab_first' ), PHP_INT_MAX, 1 );
		// display overall rating, filter and pagination
		add_action( 'wp_footer', array( $this, 'overall_rating_and_filter_html' ) );
		//output#
		$display_mobile = self::$settings->get_params( 'photo', 'display_mobile' );
		if ( ! $this->is_mobile || ! $display_mobile ) {
			self::$frontend_style = self::$settings->get_params( 'photo', 'display' );
		} else {
			self::$frontend_style = $display_mobile;
		}
		switch ( self::$frontend_style ) {
			case '2':
				if ( self::$settings->get_params( 'review_title_enable' ) ) {
					add_action( 'woocommerce_review_before_comment_text', array( $this, 'display_reviews_title' ), 5 );
				}
				if ( self::$settings->get_params( 'show_review_country' ) ) {
					add_action( 'woocommerce_review_before', array( $this, 'display_review_country' ), 11 );
				}
				add_action( 'woocommerce_review_after_comment_text', array( $this, 'wc_reviews' ) );
				if ( self::$settings->get_params( 'photo', 'verified' ) !== 'default' ) {
					add_filter( 'wc_get_template', array( $this, 'comments_template' ), PHP_INT_MAX, 2 );
				}
				break;
			case '5':
			case '6':
			case '3':
			case '4':
			default:
				add_action( 'wp_list_comments_args', array( $this, 'photo_reviews' ), 999 );
				break;
		}
		/*if (1 == self::$frontend_style) {
			add_action('wp_list_comments_args', array($this, 'photo_reviews'), 999);
		} else {

		}*/
		if ( 'on' == self::$settings->get_params( 'photo', 'single_product_summary' ) ) {
			add_action( 'wcpr_woocommerce_single_product_summary', array( $this, 'single_product_summary' ) );
		}
	}

	public function wcpr_reminder_review() {
		if ( empty( $_GET['wcpr_reminder_review'] ) || ! self::$settings->get_params( 'followup_email', 'star_rating' ) ) {
			return;
		}
		$product_id = 0;
		if ( ! empty( $_GET['product_id'] ) ) {
			$product_id = sanitize_text_field( $_GET['product_id'] );
		}
		if ( ! $product_id || ! comments_open( $product_id ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		$link = $product->get_permalink() . $this->anchor_link;
		if ( is_user_logged_in() ) {
			$user       = wp_get_current_user();
			$user_name  = $user->display_name;
			$user_email = $user->user_email;
			$user_id    = $user->ID;
		} else {
			$user_name  = isset( $_GET['user_name'] ) ? base64_decode( urldecode( sanitize_text_field( $_GET['user_name'] ) ) ) : '';
			$user_email = isset( $_GET['user_email'] ) ? base64_decode( urldecode( sanitize_text_field( $_GET['user_email'] ) ) ) : '';
			$user_id    = isset( $_GET['user_id'] ) ? base64_decode( urldecode( sanitize_text_field( $_GET['user_id'] ) ) ) : '';
		}
		$arg   = array(
			'comment_author_email' => $user_email,
			'comment_post_ID'      => $product_id,
			'user_ID'              => $user_id,
		);
		$error = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::restrict_number_of_reviews( $arg );
		if ( $error ) {
			wc_add_notice( $error, 'error' );
			do_action( 'woocommerce_set_cart_cookies', true );
			wp_safe_redirect( $link );
			exit;
		}
		$data       = array(
			'author_name'  => $user_name,
			'author_email' => $user_email,
			'review_title' => '',
			'content'      => '',
			'rating'       => 5,
			'images'       => array(),
			'verified'     => 1,
			'date'         => current_time( 'timestamp' ),
		);
		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $data['author_name'],
			'comment_author_email' => $data['author_email'],
			'comment_author_url'   => '',
			'comment_content'      => $data['content'],
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'user_id'              => '',
			'comment_author_IP'    => '',
			'comment_agent'        => '',
			'comment_date'         => date( 'Y-m-d H:j:s', $data['date'] ),
			'comment_date_gmt'     => date( 'Y-m-d H:j:s', $data['date'] ),
			'comment_approved'     => 0,
		) );
		if ( $comment_id ) {
			if ( $data['rating'] ) {
				update_comment_meta( $comment_id, 'rating', $data['rating'] );
			}
			if ( $data['review_title'] ) {
				update_comment_meta( $comment_id, 'wcpr_review_title', $data['review_title'] );
			}
			if ( $data['verified'] ) {
				update_comment_meta( $comment_id, 'verified', '1' );
			}
			if ( is_array( $data['images'] ) && count( $data['images'] ) ) {
				update_comment_meta( $comment_id, 'reviews-images', $data['images'] );
			}
			update_comment_meta( $comment_id, 'wcpr_vote_up_count', 0 );
			update_comment_meta( $comment_id, 'wcpr_vote_down_count', 0 );
			wp_safe_redirect( add_query_arg( array( 'wcpr_thank_you_message' => 3 ), $link ) );
			exit;
		}
	}

	/**
	 * @param $product WC_Product
	 */
	public function single_product_summary( $product ) {
		wc_get_template( 'viwcpr-quickview-single-product-summary-html.php',
			array(
				'is_shortcode' => false,
				'product'      => $product
			),
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function comments_template( $located, $template_name ) {
		if ( $template_name == 'single-product/review-meta.php' ) {
			$located = WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES . 'review-meta.php';
		}

		return $located;
	}

	public function wc_reviews( $comment ) {
		global $product;
		if ( ! $product || $comment->comment_parent || ! empty( $comment->wcpr_masonry ) ) {
			return;
		}
		$user = wp_get_current_user();
		if ( $user ) {
			if ( ! empty( $user->ID ) ) {
				$vote_info = $user->ID;
			} else {
				$vote_info = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_the_user_ip();
			}
		} else {
			$vote_info = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_the_user_ip();
		}
		do_action( 'viwcpr_get_template_basic_html', array(
			'settings'       => self::$settings,
			'comment'        => $comment,
			'product'        => $product,
			'vote_info'      => $vote_info,
			'image_popup'    => self::$settings->get_params( 'photo', 'image_popup' ),
			'caption_enable' => self::$settings->get_params( 'image_caption_enable' ),
			'is_shortcode'   => false,
		) );
	}

	public function display_review_country( $comment ) {
		global $product;
		if ( ! $product || $comment->comment_parent ) {
			return;
		}
		$countries      = VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Ali::get_countries();
		$review_country = get_comment_meta( $comment->comment_ID, 'wcpr_review_country', true );
		if ( $review_country ) {
			?>
            <div class="wcpr-review-country"
                 title="<?php echo esc_attr( isset( $countries[ $review_country ] ) ? $countries[ $review_country ] : $review_country ); ?>">
                <i class="vi-flag-64 flag-<?php echo esc_attr( strtolower( $review_country ) ) ?> "></i><?php echo esc_html( $review_country ); ?>
            </div>
			<?php
		}
	}

	public function display_reviews_title( $comment ) {
		global $product;
		if ( ! $product || $comment->comment_parent ) {
			return;
		}
		$review_title = get_comment_meta( $comment->comment_ID, 'wcpr_review_title', true );
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) &&
		     is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) &&
		     apply_filters( 'wcml_enable_product_review_translation', true ) ) {
			$review_title = apply_filters(
				'wpml_translate_single_string',
				$review_title,
				'viwcpr-reviews-title',
				'viwcpr-title-product-review-' . $comment->comment_ID
			);
		}
		if ( $review_title ) {
			?>
            <div class="wcpr-review-title"
                 title="<?php echo esc_attr( $review_title ); ?>"><?php echo esc_html( $review_title ); ?></div>
			<?php
		}
	}

	public static function sort_comment_by_date_desc( $comment_1, $comment_2 ) {
		return strtotime( $comment_2->comment_date ) - strtotime( $comment_1->comment_date );
	}

	public static function sort_comment_by_date_asc( $comment_1, $comment_2 ) {
		return strtotime( $comment_1->comment_date ) - strtotime( $comment_2->comment_date );
	}

	public function photo_reviews( $r ) {
		if ( self::$frontend::$is_ajax || ! is_product() ) {
			return $r;
		}
		if ( 'no' === get_option( 'woocommerce_enable_reviews' ) ) {
			return $r;
		}
		if ( empty( $r['echo'] ) ) {
			return $r;
		}
		global $wp_query;
		$my_comments = $wp_query->comments;

		if ( empty( $wp_query->comments ) || ! is_array( $wp_query->comments ) ) {
			return $r;
		}
		if ( get_option( 'page_comments' ) ) {
			/*Uncode theme gets all reviews and sets to $wp_query->comments, more at \themes\uncode\woocommerce\single-product-reviews.php*/
			$per_page = get_option( 'comments_per_page' );
			switch ( self::$settings->get_params( 'photo', 'sort' )['time'] ) {
				case 1:
					uasort( $my_comments, array( __CLASS__, 'sort_comment_by_date_desc' ) );
					break;
				case 2:
					uasort( $my_comments, array( __CLASS__, 'sort_comment_by_date_asc' ) );
					break;
				default:
			}
			if ( count( $my_comments ) > $per_page ) {
				$cpage  = get_query_var( 'cpage' );
				$offset = $cpage ? $per_page * ( $cpage - 1 ) : 0;

				$my_comments = array_slice( $my_comments, $offset, $per_page );
			}

		}
		switch ( self::$frontend_style ) {
			case '3':
				do_action( 'viwcpr_get_template_grid_html', array(
					'settings'          => self::$settings,
					'my_comments'       => $my_comments,
					'parent_tag_html'   => $r['style'] ?? '',
					'cols'              => self::$settings->get_params( 'photo', 'col_num' ),
					'masonry_popup'     => self::$settings->get_params( 'photo', 'masonry_popup' ),
					'enable_box_shadow' => self::$settings->get_params( 'photo', 'enable_box_shadow' ),
					'loadmore_button'   => self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ? 1 : '',
					'show_product'      => 'off',
					'is_shortcode'      => false,
				) );
				break;
			case '4':
				do_action( 'viwcpr_get_template_grid_layout_2_html', array(
					'settings'          => self::$settings,
					'my_comments'       => $my_comments,
					'parent_tag_html'   => $r['style'] ?? '',
					'cols'              => self::$settings->get_params( 'photo', 'col_num' ),
					'masonry_popup'     => self::$settings->get_params( 'photo', 'masonry_popup' ),
					'enable_box_shadow' => self::$settings->get_params( 'photo', 'enable_box_shadow' ),
					'loadmore_button'   => self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ? 1 : '',
					'show_product'      => 'off',
					'is_shortcode'      => false,
				) );
				break;
			case '5':
				do_action( 'viwcpr_get_template_list_html', array(
					'settings'          => self::$settings,
					'my_comments'       => $my_comments,
					'parent_tag_html'   => $r['style'] ?? '',
					'cols'              => self::$settings->get_params( 'photo', 'col_num' ),
					'masonry_popup'     => self::$settings->get_params( 'photo', 'masonry_popup' ),
					'enable_box_shadow' => self::$settings->get_params( 'photo', 'enable_box_shadow' ),
					'loadmore_button'   => self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ? 1 : '',
					'show_product'      => 'off',
					'is_shortcode'      => false,
				) );
				break;
			case '6':
				do_action( 'viwcpr_get_template_list_layout_2_html', array(
					'settings'          => self::$settings,
					'my_comments'       => $my_comments,
					'parent_tag_html'   => $r['style'] ?? '',
					'cols'              => self::$settings->get_params( 'photo', 'col_num' ),
					'masonry_popup'     => self::$settings->get_params( 'photo', 'masonry_popup' ),
					'enable_box_shadow' => self::$settings->get_params( 'photo', 'enable_box_shadow' ),
					'loadmore_button'   => self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ? 1 : '',
					'show_product'      => 'off',
					'is_shortcode'      => false,
				) );
				break;
			default:
				do_action( 'viwcpr_get_template_masonry_html', array(
					'settings'          => self::$settings,
					'my_comments'       => $my_comments,
					'parent_tag_html'   => $r['style'] ?? '',
					'cols'              => self::$settings->get_params( 'photo', 'col_num' ),
					'masonry_popup'     => self::$settings->get_params( 'photo', 'masonry_popup' ),
					'enable_box_shadow' => self::$settings->get_params( 'photo', 'enable_box_shadow' ),
					'loadmore_button'   => self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ? 1 : '',
					'show_product'      => 'off',
					'is_shortcode'      => false,
				) );
				break;
		}

		$r['echo'] = false;

		return $r;
	}

	public function overall_rating_and_filter_html( $post_id = null ) {
		if ( empty( $this->single_product_id ) ) {
			return;
		}
		//        if ($post_id === null) {
		//	        if ( is_array( $this->single_product_id ) ) {
		//		        foreach ( $this->single_product_id as $id ) {
		//			        $this->overall_rating_and_filter_html($id);
		//		        }
		//	        }else{
		//		        $this->overall_rating_and_filter_html($this->single_product_id);
		//	        }
		//            return;
		//        }
		//        if (!$post_id){
		//            return;
		//        }
		$post_id = $this->single_product_id;
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : new WC_Product( $post_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$product_link  = wp_kses_post( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$product_link1 = $product->get_permalink();
		$product_link  = remove_query_arg( array( 'image', 'verified', 'rating' ), $product_link );
		$product_link1 = remove_query_arg( array( 'image', 'verified', 'rating' ), $product_link1 );
		$args          = array(
			'post_id'  => $post_id,
			'count'    => true,
			'meta_key' => 'rating',
			'status'   => 'approve'
		);
		remove_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
		remove_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
		$counts_review = self::$frontend::get_comments( $args );
		if ( ! self::$settings->get_params( 'photo', 'hide_rating_count_if_empty' ) || $counts_review ) {
			do_action( 'viwcpr_get_overall_rating_html', array(
				'product_id'            => $post_id,
				'average_rating'        => $product->get_average_rating(),
				'count_reviews'         => $counts_review,
				'star_counts'           => array(),
				'overall_rating_enable' => self::$settings->get_params( 'photo', 'overall_rating' ),
				'rating_count_enable'   => self::$settings->get_params( 'photo', 'rating_count' ),
				'is_shortcode'          => false,
			) );
		}
		add_action( 'parse_comment_query', array( self::$frontend, 'filter_images_and_verified' ) );
		add_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
		if ( ! self::$settings->get_params( 'photo', 'hide_filters_if_empty' ) || $counts_review ) {
			if ( 'on' === self::$settings->get_params( 'photo', 'filter' )['enable'] ) {
				$args1          = array(
					'post_id'  => $post_id,
					'count'    => true,
					'meta_key' => 'reviews-images',
					'status'   => 'approve'
				);
				$count_images   = self::$frontend::get_comments( $args1 );
				$args2          = array(
					'post_id'    => $post_id,
					'count'      => true,
					'status'     => 'approve',
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key'     => 'rating',
							'compare' => 'EXISTS',
						),
						array(
							'key'     => 'verified',
							'value'   => 1,
							'compare' => '=',
						),
					)
				);
				$count_verified = self::$frontend::get_comments( $args2 );
				remove_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
				$counts_review = self::$frontend::get_comments( $args );
				if ( empty( $_GET['wcpr_is_ajax'] ) && self::$settings->get_params( 'pagination_ajax' ) && empty( $_GET['wcpr_thank_you_message'] ) ) {
					$query_image    = self::$settings->get_params( 'filter_default_image' );
					$query_verified = self::$settings->get_params( 'filter_default_verified' );
					$query_rating   = self::$settings->get_params( 'filter_default_rating' );
				} else {
					$query_image    = isset( $_GET['image'] ) ? sanitize_text_field( $_GET['image'] ) : '';
					$query_verified = isset( $_GET['verified'] ) ? sanitize_text_field( $_GET['verified'] ) : '';
					$query_rating   = isset( $_GET['rating'] ) ? sanitize_text_field( $_GET['rating'] ) : '';
				}
				if ( $query_image ) {
					$product_link  = add_query_arg( array( 'image' => true ), $product_link );
					$product_link1 = add_query_arg( array( 'image' => true ), $product_link1 );
				}
				if ( $query_verified ) {
					$product_link  = add_query_arg( array( 'verified' => true ), $product_link );
					$product_link1 = add_query_arg( array( 'verified' => true ), $product_link1 );
				}
				if ( $query_rating ) {
					$product_link  = add_query_arg( array( 'rating' => $query_rating ), $product_link );
					$product_link1 = add_query_arg( array( 'rating' => $query_rating ), $product_link1 );
				}
				do_action( 'viwcpr_get_filters_html', array(
					'settings'       => self::$settings,
					'product_id'     => $post_id,
					'count_reviews'  => $counts_review,
					'count_images'   => $count_images,
					'count_verified' => $count_verified,
					'query_rating'   => $query_rating,
					'query_verified' => $query_verified,
					'query_image'    => $query_image,
					'product_link'   => $product_link,
					'product_link1'  => $product_link1,
					'anchor_link'    => $this->anchor_link,
					'is_shortcode'   => false,
				) );
				add_action( 'parse_comment_query', array( self::$frontend, 'filter_review_rating' ) );
			}
		}
		/*replace WooCommerce pagination with ajax pagination button*/
		if ( self::$settings->get_params( 'pagination_ajax' ) && self::$settings->get_params( 'loadmore_button' ) ) {
			$cpage             = 1;
			$comments_per_page = get_option( 'comments_per_page' );
			if ( $counts_review <= $comments_per_page ) {
				return;
			}
			//			if ( self::$settings->get_params( 'photo', 'sort' )['time'] == 1 && get_option( 'page_comments' ) && $comments_per_page > 0 ) {
			//			$product_id        = $product->get_id();
			//				$args   = array(
			//					'post_id'  => $product_id,
			//					'count'    => true,
			//					'meta_key' => 'rating',
			//					'status'   => 'approve'
			//				);
			//				$counts = self::$frontend::get_comments( $args );
			//				$cpage  = ceil( $counts / $comments_per_page );
			//			}
			if ( empty( $_GET['wcpr_thank_you_message'] ) ) {
				$image    = self::$settings->get_params( 'filter_default_image' );
				$verified = self::$settings->get_params( 'filter_default_verified' );
				$rating   = self::$settings->get_params( 'filter_default_rating' );
			} else {
				$image    = '';
				$verified = '';
				$rating   = '';
			}
			do_action( 'viwcpr_get_pagination_loadmore_html', array(
				'settings'     => self::$settings,
				'product_id'   => $post_id,
				'max_page'     => $max_page = ceil( $counts_review / $comments_per_page ),
				'cpage'        => get_option( 'default_comments_page' ) === 'oldest' ? $cpage + 1 : $max_page,
				'rating'       => $rating,
				'verified'     => $verified,
				'image'        => $image,
				'is_shortcode' => false,
			) );
		}
	}

	public function show_reviews_tab_first( $tabs ) {
		if ( ! is_array( $tabs ) || sizeof( $tabs ) == 0 ) {
			return $tabs;
		}
		if ( 'on' != self::$settings->get_params( 'photo', 'review_tab_first' ) ) {
			return $tabs;
		}
		foreach ( $tabs as $k => $v ) {
			if ( $k == 'reviews' ) {
				$reviews_tab                   = array( $k => $v );
				$reviews_tab[ $k ]['priority'] = 1;
				unset( $tabs[ $k ] );
				$tabs = $reviews_tab + $tabs;
				break;
			}
		}
		uasort( $tabs, '_sort_priority_callback' );

		return $tabs;
	}

	public function quick_view() {
		if ( ! $this->single_product_id ) {
			return;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $this->single_product_id ) : new WC_Product( $this->single_product_id );
		if ( ! $product ) {
			return;
		}
		if ( $this->quick_view ) {
			return;
		}
		$this->quick_view = true;
		wc_get_template( 'viwcpr-quickview-template-html.php',
			array(
				'is_shortcode' => false,
				'product'      => $product
			),
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function frontend_enqueue() {
		wp_enqueue_style( 'wcpr-country-flags', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'flags-64.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		global $post;
		if ( ! $post ) {
			return;
		}
		if ( is_product() ) {
			$this->single_product_id = $post->ID;
		} elseif ( ! empty( $post->post_content ) && strstr( $post->post_content, '[product_page' ) ) {
			$matches = array();
			preg_match( '/\[product_page(.*?)\]/', strstr( $post->post_content, '[product_page' ), $matches );
			if ( ! empty( $matches[1] ) ) {
				$attr                    = shortcode_parse_atts( $matches[1] );
				$this->single_product_id = $attr['id'] ?? '';
			}
		}
		if ( ! $this->single_product_id ) {
			return;
		}
		if ( ! wc_get_product( $this->single_product_id ) ) {
			$this->single_product_id = '';

			return;
		}
		$suffix = WP_DEBUG ? '' : 'min.';
		if ( self::$settings->get_params( 'photo', 'helpful_button_enable' ) ) {
			wp_enqueue_style( 'woocommerce-photo-reviews-vote-icons', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-vote-icons.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		}
		wp_enqueue_style( 'wcpr-verified-badge-icon', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'woocommerce-photo-reviews-badge.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		wp_enqueue_style( 'woocommerce-photo-reviews-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'style.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		if ( ! wp_script_is( 'woocommerce-photo-reviews-script' ) ) {
			wp_enqueue_script( 'woocommerce-photo-reviews-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'script.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
			$woocommerce_photo_reviews_params = self::get_enqueue_params();
			wp_localize_script( 'woocommerce-photo-reviews-script', 'woocommerce_photo_reviews_params', $woocommerce_photo_reviews_params );
		}
		wp_enqueue_script( 'wcpr-swipebox-js', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'swipebox.' . $suffix . 'js', array( 'jquery' ) );
		wp_enqueue_style( 'wcpr-swipebox-css', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'swipebox.' . $suffix . 'css' );
		switch ( (string) self::$frontend_style ) {
			case '1':
			case '3':
			case '4':
				wp_enqueue_style( 'wcpr-masonry-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'masonry.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				$css_masonry = self::$frontend::add_inline_style(
					array( '.wcpr-grid' ),
					array( 'background-color' ),
					array( self::$settings->get_params( 'photo', 'grid_bg' ) )
				);
				$css_masonry .= self::$frontend::add_inline_style(
					array( '.wcpr-grid>.wcpr-grid-item,#wcpr-modal-wrap' ),
					array( 'background-color' ),
					array( self::$settings->get_params( 'photo', 'grid_item_bg' ) )
				);
				if ( $grid_item_border_color = self::$settings->get_params( 'photo', 'grid_item_border_color' ) ) {
					$css_masonry .= '.wcpr-grid>.wcpr-grid-item{border:1px solid ' . $grid_item_border_color . ';}';
				}
				$css_masonry .= self::$frontend::add_inline_style(
					array(
						'.wcpr-grid>.wcpr-grid-item,#reviews-content-right',
						'#reviews-content-right>.reviews-content-right-meta',
						'#reviews-content-right>.wcpr-single-product-summary>h1.product_title',
					),
					array( 'color' ),
					array( self::$settings->get_params( 'photo', 'comment_text_color' ) )
				);
				if ( 'on' == self::$settings->get_params( 'photo', 'single_product_summary' ) ) {
					$css_masonry .= '#reviews-content-right>.wcpr-single-product-summary{border-top:1px solid;}';
				}
				if ( $this->is_mobile ) {
					$css_masonry .= '@media (max-width: 600px) {';
					if ( self::$settings->get_params( 'photo', 'full_screen_mobile' ) ) {
						$css_masonry .= '.wcpr-modal-light-box .wcpr-modal-light-box-wrapper .wcpr-modal-wrap {border-radius: 0;}
									.wcpr-modal-light-box-wrapper{
									    align-items: baseline !important;
									}
									.wcpr-modal-light-box .wcpr-modal-wrap-container{
									    width: 100% !important;
									    height: calc(100% - 58px) !important;
									    max-height: unset !important;
									}
									
									.wcpr-modal-light-box .wcpr-modal-wrap-container .wcpr-close{
									    position: fixed !important;
									    bottom: 0;
									    left: 50%;
									    transform: translateX(-50%);
									    right: unset !important;
									    top: unset !important;
									    background: black;
									     border-radius: 0;
									    width: 58px !important;
									    height: 58px !important;
									    line-height: 58px !important;
									    display: flex !important;
									    justify-content: center;
									    align-items: center;
									}
									.wcpr-modal-light-box .wcpr-modal-wrap-container .wcpr-prev, .wcpr-modal-light-box .wcpr-modal-wrap-container .wcpr-next{
										height: 58px;
									    background: rgba(255,255,2555,0.6);
									    width: calc(50% - 29px) !important;
									    padding: 0 !important;
									    border-radius: 0 !important;
									    position: fixed !important;
									    display: flex;
									    justify-content: center;
									    align-items: center;
									    bottom: 0;
									    top: unset !important;
									}
									.wcpr-modal-light-box .wcpr-modal-wrap-container .wcpr-prev{
									    left: 0 !important;
									}
									.wcpr-modal-light-box .wcpr-modal-wrap-container .wcpr-next {
									    right: 0 !important;
									}
					';
					}
					$css_masonry .= self::$frontend::add_inline_style(
						array(
							'.wcpr-grid, .wcpr-grid.wcpr-masonry-2-col, .wcpr-grid.wcpr-masonry-3-col',
							'.wcpr-grid.wcpr-masonry-4-col, .wcpr-grid.wcpr-masonry-5-col'
						),
						array( 'column-count', 'grid-template-columns' ),
						array(
							self::$settings->get_params( 'photo', 'col_num_mobile' ),
							'repeat(' . self::$settings->get_params( 'photo', 'col_num_mobile' ) . ', 1fr)'
						),
						array( '!important', '!important' )
					);
					$css_masonry .= '}';
				}
				wp_add_inline_style( 'wcpr-masonry-style', $css_masonry );
				wp_enqueue_script( 'wcpr-masonry-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'masonry.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				add_action( 'wp_footer', array( $this, 'quick_view' ) );
				break;
			case '5':
			case '6':
				wp_enqueue_style( 'wcpr-list-display-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'list_style.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				wp_enqueue_script( 'wcpr-list-display-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'list_style.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				add_action( 'wp_footer', array( $this, 'quick_view' ) );
				break;
			default:
				wp_enqueue_style( 'wcpr-rotate-font-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'rotate.min.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				wp_enqueue_style( 'wcpr-default-display-style', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'default-display-images.' . $suffix . 'css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				wp_enqueue_script( 'wcpr-default-display-script', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'default-display-images.' . $suffix . 'js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
				break;
		}

		$css_inline = self::$settings->get_params( 'photo', 'custom_css' );
		$css_inline .= self::$frontend::add_inline_style(
			array( '.woocommerce-review__verified' ),
			array( 'color' ),
			array( self::$settings->get_params( 'photo', 'verified_color' ) ),
			array( '!important' )
		);
		$css_inline .= self::$frontend::add_inline_style(
			array( '.wcpr-input-file-wrap .wcpr_image_upload_button.button, .wcpr-input-file-wrap .wcpr_image_upload_button.button' ),
			array( 'background-color', 'color' ),
			array(
				self::$settings->get_params( 'photo', 'upload_button_bg_color' ),
				self::$settings->get_params( 'photo', 'upload_button_color' )
			),
			array( '!important', '!important' )
		);
		if ( self::$settings->get_params( 'photo', 'filter' )['enable'] == 'on' ) {
			$css_inline .= ".wcpr-filter-container{";
			if ( self::$settings->get_params( 'photo', 'filter' )['area_border_color'] ) {
				$css_inline .= "border:1px solid " . self::$settings->get_params( 'photo', 'filter' )['area_border_color'] . ";";
			}
			if ( self::$settings->get_params( 'photo', 'filter' )['area_bg_color'] ) {
				$css_inline .= 'background-color:' . self::$settings->get_params( 'photo', 'filter' )['area_bg_color'] . ';';
			}
			$css_inline .= "}";
			$css_inline .= '.wcpr-filter-container .wcpr-filter-button{';
			if ( self::$settings->get_params( 'photo', 'filter' )['button_color'] ) {
				$css_inline .= 'color:' . self::$settings->get_params( 'photo', 'filter' )['button_color'] . ';';
			}
			if ( self::$settings->get_params( 'photo', 'filter' )['button_bg_color'] ) {
				$css_inline .= 'background-color:' . self::$settings->get_params( 'photo', 'filter' )['button_bg_color'] . ';';
			}
			if ( self::$settings->get_params( 'photo', 'filter' )['button_border_color'] ) {
				$css_inline .= 'border:1px solid ' . self::$settings->get_params( 'photo', 'filter' )['button_border_color'] . ';';
			}
			$css_inline .= "}";
		}
		$css_inline .= self::$frontend::add_inline_style(
			array( '.star-rating:before,.star-rating span:before,.stars a:hover:after, .stars a.active:after' ),
			array( 'color' ),
			array( self::$settings->get_params( 'photo', 'star_color' ) ),
			array( '!important' )
		);
		if ( self::$settings->get_params( 'image_caption_enable' ) ) {
			$css_inline .= self::$frontend::add_inline_style(
				array(
					'.reviews-images-wrap-right .wcpr-review-image-caption',
					'#reviews-content-left-main .wcpr-review-image-container .wcpr-review-image-caption',
					'.kt-reviews-image-container .big-review-images-content-container .wcpr-review-image-caption'
				),
				array( 'background-color', 'color', 'font-size' ),
				array(
					self::$settings->get_params( 'image_caption_bg_color' ),
					self::$settings->get_params( 'image_caption_color' ),
					self::$settings->get_params( 'image_caption_font_size' ),
				),
				array( '', '', 'px' )
			);
		}
		if ( 'on' == self::$settings->get_params( 'photo', 'rating_count' ) ) {
			$css_inline .= self::$frontend::add_inline_style(
				array( '.rate-percent' ),
				array( 'background-color' ),
				array( self::$settings->get_params( 'photo', 'rating_count_bar_color' ) ?: '#96588a' ),
				array( '' )
			);
		}
		$upload_button_bg_color = self::$settings->get_params( 'photo', 'upload_button_bg_color' );
		$upload_button_color    = self::$settings->get_params( 'photo', 'upload_button_color' );
		$css_inline             .= ":root{
        --upload_button_bg_color:$upload_button_bg_color;
        --upload_button_color:$upload_button_color;
        }";
		wp_add_inline_style( 'woocommerce-photo-reviews-style', $css_inline );
	}

	public static function get_enqueue_params() {
		$grid_class = array(
			'wcpr-grid wcpr-masonry-' . self::$settings->get_params( 'photo', 'col_num' ) . '-col',
			'wcpr-masonry-popup-' . self::$settings->get_params( 'photo', 'masonry_popup' ),
		);
		if ( self::$settings->get_params( 'photo', 'enable_box_shadow' ) ) {
			$grid_class[] = 'wcpr-enable-box-shadow';
		}
		$container_class = '.commentlist';

		if ( in_array( (string) self::$frontend_style, [ '1', '3', '4' ] ) ) {
			/*1,3,4 is grid template*/
			$container_class = '.wcpr-grid';
		} else {
			if ( self::$settings->get_params( 'reviews_container' ) ) {
				$container_class = self::$settings->get_params( 'reviews_container' );
			}
		}
        $class_wrap_filters = 'wcpr-filter-container';
		switch (self::$settings->get_params( 'photo', 'display' ) ){
			case '1':
				$class_wrap_filters .=' wcpr-filter-style-masonry';
				break;
			case '2':
				$class_wrap_filters .=' wcpr-filter-style-normal';
				break;
			case '3':
				$class_wrap_filters .=' wcpr-filter-style-grid';
				break;
			case '4':
				$class_wrap_filters .=' wcpr-filter-style-grid-layout-2';
				break;
			case '5':
				$class_wrap_filters .=' wcpr-filter-style-list';
				break;
			case '6':
				$class_wrap_filters .=' wcpr-filter-style-list-layout-2';
				break;
			default:
				break;
		}
		$woocommerce_photo_reviews_params = array(
			'ajaxurl'                    => admin_url( 'admin-ajax.php' ),
			'text_load_more'             => esc_html__( 'Load more', 'woocommerce-photo-reviews' ),
			'text_loading'               => esc_html__( 'Loading...', 'woocommerce-photo-reviews' ),
			'i18n_required_rating_text'  => esc_attr__( 'Please select a rating.', 'woocommerce-photo-reviews' ),
			'i18n_required_comment_text' => esc_attr__( 'Please enter your comment.', 'woocommerce-photo-reviews' ),
			'i18n_minimum_comment_text'  => sprintf( esc_attr__( 'Please enter your comment not less than %s character.', 'woocommerce-photo-reviews' ), $minimum_comment_length = self::$settings->get_params( 'minimum_comment_length' ) ?: 0 ),
			'i18n_required_name_text'    => esc_attr__( 'Please enter your name.', 'woocommerce-photo-reviews' ),
			'i18n_required_email_text'   => esc_attr__( 'Please enter your email.', 'woocommerce-photo-reviews' ),
			'warning_gdpr'               => esc_html__( 'Please agree with our term and policy.', 'woocommerce-photo-reviews' ),
			'max_files'                  => self::$settings->get_params( 'photo', 'maxfiles' ),
			'upload_allow'               => self::$settings->get_params( 'upload_allow' ),
			'max_file_size'              => self::$settings->get_params( 'photo', 'maxsize' ),
			'required_image'             => self::$settings->get_params( 'photo', 'required' ),
			'enable_photo'               => self::$settings->get_params( 'photo', 'enable' ),
			'warning_required_image'     => esc_html__( 'Please upload at least one image for your review!', 'woocommerce-photo-reviews' ),
			'warning_max_files'          => sprintf( _n( 'You can only upload maximum of %s file.', 'You can only upload maximum of %s files.', self::$settings->get_params( 'photo', 'maxfiles' ), 'woocommerce-photo-reviews' ), self::$settings->get_params( 'photo', 'maxfiles' ) ),
			'warning_upload_allow'       => sprintf( esc_html__( '\'%s\' is not an allowed file type.', 'woocommerce-photo-reviews' ), '%file_name%' ),
			'warning_max_file_size'      => sprintf( esc_html__( 'The size of \'%s\' is greater than %s kB.', 'woocommerce-photo-reviews' ), '%file_name%', self::$settings->get_params( 'photo', 'maxsize' ) ),
			'default_comments_page'      => get_option( 'default_comments_page' ),
			'comments_per_page'          => get_option( 'comments_per_page' ),
			'sort'                       => self::$settings->get_params( 'photo', 'sort' )['time'],
			'display'                    => self::$frontend_style,
			'masonry_popup'              => self::$settings->get_params( 'photo', 'masonry_popup' ),
			'pagination_ajax'            => self::$settings->get_params( 'pagination_ajax' ),
			'loadmore_button'            => self::$settings->get_params( 'loadmore_button' ) ?: '',
			'allow_empty_comment'        => self::$settings->get_params( 'allow_empty_comment' ),
			'minimum_comment_length'     => $minimum_comment_length,
			'container'                  => $container_class,
			'wrap_filters'                  => $class_wrap_filters,
			'comments_container_id'      => apply_filters( 'woocommerce_photo_reviews_comments_wrap', 'comments' ),
			'nonce'                      => wp_create_nonce( 'woocommerce_photo_reviews_nonce' ),
			'grid_class'                 => esc_attr( trim( implode( ' ', $grid_class ) ) ),
			'i18n_image_caption'         => esc_attr__( 'Caption for this image', 'woocommerce-photo-reviews' ),
			'image_caption_enable'       => self::$settings->get_params( 'image_caption_enable' ),
			'restrict_number_of_reviews' => self::$settings->get_params( 'ajax_check_content_reviews' ),
			'wc_ajax_url'                => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'review_rating_required'     => wc_review_ratings_required() ? 'yes' : 'no',
		);
		if ( self::$settings->get_params( 'photo', 'single_product_summary_ajax_atc' ) ) {
			$woocommerce_photo_reviews_params['ajax_atc']                            = 1;
			$woocommerce_photo_reviews_params['cart_redirect_after_add']             = get_option( 'woocommerce_cart_redirect_after_add' );
			$woocommerce_photo_reviews_params['woocommerce_enable_ajax_add_to_cart'] = 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ? 1 : '';
			$woocommerce_photo_reviews_params['cart_url']                            = apply_filters( 'woocommerce_add_to_cart_redirect', wc_get_cart_url(), null );
		}

		return $woocommerce_photo_reviews_params;
	}
}