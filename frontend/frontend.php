<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend {
	protected static $settings, $language;
	public static               $is_ajax, $rating, $verified, $image;
	protected                   $is_mobile, $frontend_style;
	protected                   $characters_array;
	protected static            $anchor_link;
	protected                   $enctype_start;
	public static               $product_id, $cache = array();

	public function __construct() {
		$this->enctype_start = false;
		self::$settings      = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		if ( self::$settings->get_params( 'enable' ) !== 'on' ) {
			return;
		}
		$photo_enable = self::$settings->get_params( 'photo', 'enable' );
		add_action( 'edit_comment', array( $this, 'coupon_for_not_logged_in' ), 10, 1 );
		add_action( 'wp_set_comment_status', array( $this, 'coupon_for_not_logged_in' ), 10, 1 );
		/*Keep this hook wpr_schedule_email for emails scheduled previously with free version*/
		add_action( 'wpr_schedule_email', array( $this, 'send_schedule_email' ), 10, 7 );
		add_action( 'wcpr_schedule_email', array( $this, 'send_schedule_email_new' ) );
		add_action( 'wcpr_schedule_resend_email', array( $this, 'wcpr_schedule_resend_email' ) );
		//email reminder
		if ( 'on' === self::$settings->get_params( 'followup_email', 'enable' ) ) {
			$order_statuses = self::$settings->get_params( 'followup_email', 'order_statuses' );
			if ( count( $order_statuses ) ) {
				foreach ( $order_statuses as $status ) {
					$status = substr( $status, 3 );
					add_action( "woocommerce_order_status_{$status}", array( $this, 'follow_up_email' ), 10, 2 );
				}
			}
		}
		//mobile detect
		$this->is_mobile = wp_is_mobile();
		if ( $this->is_mobile && self::$settings->get_params( 'mobile' ) !== 'on' ) {
			return;
		}
		self::$anchor_link = '#' . self::$settings->get_params( 'reviews_anchor_link' );
		$display_mobile    = self::$settings->get_params( 'photo', 'display_mobile' );
		if ( ! $this->is_mobile || ! $display_mobile ) {
			$this->frontend_style = self::$settings->get_params( 'photo', 'display' );
		} else {
			$this->frontend_style = $display_mobile;
		}
		// sort review
		add_filter( 'comments_template_query_args', array( $this, 'sort_reviews' ) );
		if ( 'on' == self::$settings->get_params( 'photo', 'filter' )['enable'] ) {
			add_action( 'parse_comment_query', array( __CLASS__, 'filter_images_and_verified' ) );
			add_action( 'parse_comment_query', array( __CLASS__, 'filter_review_rating' ) );
		}
		add_action( 'viwcpr_get_overall_rating_html', array( $this, 'viwcpr_get_overall_rating_html' ), 10, 1 );
		add_action( 'viwcpr_get_filters_html', array( $this, 'viwcpr_get_filters_html' ), 10, 1 );
		add_action( 'viwcpr_get_pagination_loadmore_html', array(
			$this,
			'viwcpr_get_pagination_loadmore_html'
		), 10, 1 );

		add_action( 'viwcpr_get_template_masonry_html', array( $this, 'viwcpr_get_template_masonry_html' ), 10, 1 );
		add_action( 'viwcpr_get_template_basic_html', array( $this, 'viwcpr_get_template_basic_html' ), 10, 1 );
		add_action( 'viwcpr_get_template_grid_html', array( $this, 'viwcpr_get_template_grid_html' ), 10, 1 );
		add_action( 'viwcpr_get_template_grid_layout_2_html', array( $this, 'viwcpr_get_template_grid_layout_2_html' ), 10, 1 );
		add_action( 'viwcpr_get_template_list_html', array( $this, 'viwcpr_get_template_list_html' ), 10, 1 );
		add_action( 'viwcpr_get_template_list_layout_2_html', array( $this, 'viwcpr_get_template_list_layout_2_html' ), 10, 1 );

		add_action( 'wp_ajax_wcpr_ajax_load_more_reviews', array( $this, 'ajax_load_more_reviews' ) );
		add_action( 'wp_ajax_nopriv_wcpr_ajax_load_more_reviews', array( $this, 'ajax_load_more_reviews' ) );
		/*helpful button handle*/
		add_action( 'wp_ajax_wcpr_helpful_button_handle', array( $this, 'helpful_button_handle' ) );
		add_action( 'wp_ajax_nopriv_wcpr_helpful_button_handle', array( $this, 'helpful_button_handle' ) );
		if ( self::$settings->get_params( 'photo', 'hide_name' ) ) {
			add_filter( 'comment_author', array( $this, 'comment_author' ), PHP_INT_MAX, 2 );
		}
		add_filter( 'get_comment_link', array( $this, 'get_comment_link' ), 99, 4 );
		//form review
		add_filter( 'woocommerce_product_review_comment_form_args', array( $this, 'add_comment_field' ), PHP_INT_MAX, 1 );
		add_filter( 'woocommerce_photo_reviews_custom_fields_input', array( $this, 'custom_fields_from_product_variations' ), 10, 2 );
		add_filter( 'comment_post_redirect', array( $this, 'comment_post_redirect' ), 99, 2 );
		add_action( 'comment_form_before', array( $this, 'thank_you_message_after_review' ) );
		add_action( 'comment_form_top', array( $this, 'add_form_description' ), 20 );
		if ( 'on' == $photo_enable ) {
			//add enctype attribute to form
			add_action( 'comment_form_before', array( $this, 'add_form_enctype_start' ) );
			add_action( 'comment_form_top', array( $this, 'add_form_enctype_end' ) );
			add_action( 'comment_form_must_log_in_after', array( $this, 'comment_form_must_log_in_after' ) );
		}
		//save review
		/*handle review reminder token*/
		add_action( 'init', array( $this, 'login' ) );
		add_filter( 'allow_empty_comment', array( $this, 'allow_empty_comment' ) );
		add_action( 'comment_post', array( $this, 'save_review_title' ) );
		add_action( 'comment_post', array( $this, 'save_custom_fields' ) );
		add_action( 'comment_post', array( $this, 'fix_get_comment_link' ) );
		add_filter( 'woocommerce_photo_reviews_image_file_name', array( $this, 'add_prefix_to_photo_name' ), 10, 4 );
		//display message error
		add_action( 'wp_error_added', array( $this, 'viwcpr_wp_error_added' ), 10, 4 );
		// Validate product reviews if requires verified owners.
		add_action( 'pre_comment_on_post', array( __CLASS__, 'validate_product_review_verified_owners' ) );
		//input#2-handle image field
		add_filter( 'preprocess_comment', array( $this, 'check_review_image' ), 10, 1 );
		add_filter( 'cron_schedules', function ( $schedules ) {
			$schedules['one_minute'] = array(
				'interval' => 60,
				'display'  => esc_html__( 'One minute', 'woocommerce-photo-reviews' ),
			);

			return $schedules;
		} );
		add_filter( 'woocommerce_photo_reviews_get_rating_count_arguments', array(
			$this,
			'wpml_count_rating_from_all_languages'
		), 10, 3 );
		self::add_ajax_events();
	}

	public static function add_ajax_events() {
		$ajax_events = array(
			'viwcpr_add_to_cart'                => true,
			'viwcpr_restrict_number_of_reviews' => true,
		);
		if ( self::$settings->get_params( 'reviews_order' ) ) {
			$ajax_events['viwcpr_review_order'] = true;
		}
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public static function viwcpr_add_to_cart() {
		if ( ! did_action( 'woocommerce_add_to_cart' ) ) {
			WC_Form_Handler::add_to_cart_action();
		}
		$notices = WC()->session->get( 'wc_notices', array() );
		if ( ! empty( $notices['error'] ) ) {
			wp_send_json( array( 'error' => true ) );
		}
		if ( ! empty( $notices ) ) {
			unset( $notices['success'] );
			WC()->session->set( 'wc_notices', $notices );
		}
		WC_AJAX::get_refreshed_fragments();
		die();
	}

	/**
	 * @param $args
	 * @param $product_id
	 * @param $star
	 *
	 * @return mixed
	 */
	public function wpml_count_rating_from_all_languages( $args, $product_id, $star ) {
		global $wpml_post_translations;
		if ( $wpml_post_translations && get_option( 'wcml_reviews_in_all_languages' ) ) {
			$products = array_values( $wpml_post_translations->get_element_translations( $product_id ) );
			if ( count( $products ) > 1 ) {
				$args['post__in'] = ! empty( $args['post__in'] ) && is_array( $args['post__in'] ) ? array_merge( $args['post__in'], $products ) : $products;
				$args['post__in'] = array_unique( $args['post__in'] );
			}
		}

		return $args;
	}

	public function wcpr_schedule_resend_email() {
		if ( 'on' !== self::$settings->get_params( 'followup_email', 'loop' ) ) {
			return;
		}
		$loop_times  = self::$settings->get_params( 'followup_email', 'loop_time' );
		$loop_repeat = self::$settings->get_params( 'followup_email', 'loop_repeat' );
		if ( ! $loop_repeat || ! $loop_times ) {
			return;
		}
		$max_loop_days  = $loop_times * $loop_repeat;
		$current_day    = strtotime( 'today' );
		$start_date     = date( 'Y-m-d', strtotime( "-{$max_loop_days} days" ) );
		$order_statuses = self::$settings->get_params( 'followup_email', 'order_statuses' );
		$order_statuses = empty( $order_statuses ) ? array_keys( wc_get_order_statuses() ) : $order_statuses;
		$args           = array(
			'post_status'    => $order_statuses,
			'posts_per_page' => - 1,
			'return'         => 'ids',
			'date_after'     => $start_date . ' 00:00:00',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_wcpr_review_reminder',
					'compare' => 'EXISTS',
				)
			)
		);
		$order_ids      = wc_get_orders( $args );
		if ( $order_ids ) {
			foreach ( $order_ids as $order_id ) {
				$order           = wc_get_order( $order_id );
				$review_reminder = $order->get_meta( '_wcpr_review_reminder' );
				$status_message  = $review_reminder['status'] ?? '';
				if ( $status_message !== 'sent' ) {
					continue;
				}
				$order_date_create = $order->get_date_created()->date_i18n( "Y-m-d" );
				$order_date_create = strtotime( $order_date_create );
				if ( $order_date_create == $current_day ) {
					continue;
				}
				$days_count    = ( $current_day - $order_date_create ) / 86400;
				$not_send_mail = $days_count ? $days_count % $loop_times : 1;
				if ( $not_send_mail ) {
					continue;
				}
				$order->update_meta_data( '_wcpr_review_reminder_resend', $days_count / $loop_times );
				$order->save_meta_data();
				wp_schedule_single_event( time(), 'wcpr_schedule_email', array( $order_id ) );
			}
		}
	}

	public static function review_reminder_from_address( $from_address ) {
		$email = self::$settings->get_params( 'followup_email', 'from_address' );
		if ( is_email( $email ) ) {
			$from_address = sanitize_email( $email );
		}

		return $from_address;
	}

	public static function send_schedule_email( $user_email, $customer_name, $products, $order_id, $time, $date_create, $date_complete ) {
		global $wcpr_products_to_review;
		if ( 'on' !== self::$settings->get_params( 'followup_email', 'enable' ) && ! isset( $time['admin_send_reminder'] ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete_meta_data( '_wcpr_review_reminder' );
				$order->delete_meta_data( '_wcpr_review_reminder_resend' );
				$order->save();
			}

			return;
		}
		if ( count( $products ) ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$order_meta = $order->get_meta( '_wcpr_review_reminder', true );
			$token      = isset( $order_meta['token'] ) ? $order_meta['token'] : '';
			$language   = '';
			if ( self::$settings->get_params( 'multi_language' ) ) {
				$language = $order->get_meta( 'wpml_language', true );
				if ( ! $language && isset( $order_meta['language'] ) && $order_meta['language'] ) {
					$language = $order_meta['language'];
				}
			}
			$subject        = $content = '';
			$email_template = self::$settings->get_params( 'reminder_email_template', '', $language );
			$use_template   = false;
			if ( $email_template && VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::is_email_template_customizer_active() ) {
				$email_template_obj = get_post( $email_template );
				if ( $email_template_obj && $email_template_obj->post_type === 'viwec_template' ) {
					foreach ( $products as $p ) {
						if ( $language && is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
							$p = apply_filters( 'wpml_object_id', $p, 'product', true, $language );
						}
						$product = wc_get_product( $p );
						if ( $product ) {
							$product_image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wp_get_attachment_thumb_url( $product->get_image_id() );
							$product_url   = $product->get_permalink() . self::$anchor_link;
							if ( $token ) {
								$product_url = add_query_arg( 'wcpr_token', $token, $product_url );
							}
							$product_title = $product->get_title();
							do_action( 'viwcpr_reminder_before_get_product_html', $order, $products );
							$product_price = apply_filters( 'woocommerce_photo_reviews_reminder_product_price', $product->get_price_html(), $product, $order );
							do_action( 'viwcpr_reminder_after_get_product_html', $order, $products );
							$wcpr_products_to_review[] = array(
								'image'      => $product_image,
								'name'       => $product_title,
								'price'      => $product_price,
								'review_url' => $product_url,
							);
						}
					}
					$use_template = true;
					$viwec_email  = new VIWEC_Render_Email_Template( array(
						'template_id' => $email_template,
						'order'       => $order
					) );
					ob_start();
					$viwec_email->get_content();
					$content = ob_get_clean();
					$subject = $viwec_email->get_subject();
					$content = str_replace( array(
						'{wcpr_customer_name}',
						'{wcpr_order_id}',
						'{wcpr_order_date_create}',
						'{wcpr_order_date_complete}'
					), array( $customer_name, $order_id, $date_create, $date_complete ), $content );
					$content = str_replace( array(
						'{site_title}',
						'{wcpr_site_title}'
					), get_bloginfo( 'name' ), $content );
					$subject = str_replace( array(
						'{wcpr_customer_name}',
						'{wcpr_order_id}',
						'{wcpr_order_date_create}',
						'{wcpr_order_date_complete}'
					), array( $customer_name, $order_id, $date_create, $date_complete ), $subject );
					$subject = str_replace( array(
						'{site_title}',
						'{wcpr_site_title}'
					), get_bloginfo( 'name' ), $subject );
				}
			}
			$mailer  = WC()->mailer();
			$email   = new WC_Email();
			$headers = "Content-Type: text/html\r\nReply-to: {$email->get_from_name()} <{$email->get_from_address()}>\r\n";
			add_filter( 'woocommerce_email_from_address', array( __CLASS__, 'review_reminder_from_address' ) );
			if ( ! $use_template ) {
				$product_image_width = self::$settings->get_params( 'followup_email', 'product_image_width' );
				$content             = nl2br( stripslashes( self::$settings->get_params( 'followup_email', 'content', $language ) ) );
				$content             = str_replace( '{customer_name}', $customer_name, $content );
				$content             = str_replace( '{order_id}', $order_id, $content );
				$content             = str_replace( '{date_create}', $date_create, $content );
				$content             = str_replace( '{date_complete}', $date_complete, $content );
				$content             = str_replace( '{site_title}', get_bloginfo( 'name' ), $content );
				$content             .= '<table style="width: 100%;">';
				foreach ( $products as $p ) {
					if ( $language && is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
						$p = apply_filters( 'wpml_object_id', $p, 'product', true, $language );
					}
					$product = wc_get_product( $p );
					if ( $product ) {
						$product_image = wp_get_attachment_thumb_url( $product->get_image_id() );
						$product_url   = $product->get_permalink() . self::$anchor_link;
						if ( $token ) {
							$product_url = add_query_arg( 'wcpr_token', $token, $product_url );
						}
						$product_title = $product->get_title();
						do_action( 'viwcpr_reminder_before_get_product_html', $order, $products );
						$product_price = apply_filters( 'woocommerce_photo_reviews_reminder_product_price', $product->get_price_html(), $product, $order );
						do_action( 'viwcpr_reminder_after_get_product_html', $order, $products );
						ob_start();
						$target_link = apply_filters( 'viwcpr_hook_target_link_product', '_blank' );
						?>
                        <tr>
                            <td style="text-align: center;">
                                <a target="<?php echo esc_attr( $target_link ) ?>" href="<?php echo esc_attr( $product_url ) ?>">
                                    <img style="width: <?php echo esc_attr( $product_image_width ) ?>px;"
                                         src="<?php echo esc_url( $product_image ) ?>"
                                         alt="<?php echo wp_kses_post( $product_title ) ?>">
                                </a>
                            </td>
                            <td>
                                <p>
                                    <a target="<?php echo esc_attr( $target_link ) ?>"
                                       href="<?php echo esc_url( $product_url ) ?>"><?php echo wp_kses_post( $product_title ) ?></a>
                                </p>
                                <p><?php echo wp_kses_post( $product_price ) ?></p>
                                <a target="<?php echo esc_attr( $target_link ) ?>"
                                   style="text-align: center;padding: 10px;text-decoration: none;font-weight: 800;
                                           background-color:<?php echo esc_attr( self::$settings->get_params( 'followup_email', 'review_button_bg_color' ) ); ?>;
                                           color:<?php echo esc_attr( self::$settings->get_params( 'followup_email', 'review_button_color' ) ) ?>;"
                                   href="<?php echo esc_url( $product_url ) ?>"><?php echo wp_kses_post( self::$settings->get_params( 'followup_email', 'review_button', $language ) ) ?>
                                </a>
                            </td>
                        </tr>
						<?php
						$content .= ob_get_clean();
					}
				}
				$content       .= '</table>';
				$subject       = stripslashes( self::$settings->get_params( 'followup_email', 'subject', $language ) );
				$email_heading = self::$settings->get_params( 'followup_email', 'heading', $language );
				$content       = $email->style_inline( $mailer->wrap_message( $email_heading, $content ) );
			}
			$subject = apply_filters( 'viwcpr_email_reminder_get_subject', $subject, $order_id );
			$content = apply_filters( 'viwcpr_email_reminder_get_content', $content, $order_id );
			$email->send( $user_email, $subject, $content, $headers, array() );
			if ( ! empty( $time['admin_send_reminder'] ) ) {
				$order->update_meta_data( '_wcpr_review_reminder', array(
					'status' => 'sent',
					'time'   => $time['admin_send_reminder'],
				) );
			} else {
				$order->update_meta_data( '_wcpr_review_reminder', array(
					'status'   => 'sent',
					'time'     => $time,
					'token'    => $token,
					'language' => $language
				) );
			}
			$order->save_meta_data();
			remove_filter( 'woocommerce_email_from_address', array( __CLASS__, 'review_reminder_from_address' ) );
		}
	}

	/**
	 * @param $user_email
	 * @param $customer_name
	 * @param $products
	 * @param $order WC_Order
	 * @param $date_create
	 * @param $date_complete
	 * @param $language
	 * @param string $token
	 * @param string $user_id
	 */
	public static function send_schedule_email1( $user_email, $customer_name, $products, $order, $date_create, $date_complete, $language, $token = '', $user_id = '' ) {
		global $wcpr_products_to_review;
		$wcpr_products_to_review = array();
		$review_form_page        = self::$settings->get_params( 'followup_email', 'review_form_page' );
		$product_image_width     = self::$settings->get_params( 'followup_email', 'product_image_width' );
		$review_form_page_url    = '';
		$wpml_active             = $language && is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
		if ( $review_form_page ) {
			$review_form_page_url = get_permalink( $review_form_page );
			if ( $wpml_active ) {
				$review_form_page_url = self::get_permalink_by_language( $review_form_page_url, $language );
			}
		}
		$products_html       = '';
		$review_button_title = self::$settings->get_params( 'followup_email', 'review_button', $language );
		$review_button_style = 'text-align: center;text-decoration: none;font-weight: 800;color:' . self::$settings->get_params( 'followup_email', 'review_button_color' ) . ';';
		if ( self::$settings->get_params( 'followup_email', 'review_button_bg_color' ) ) {
			$review_button_style .= 'padding: 10px;background-color:' . self::$settings->get_params( 'followup_email', 'review_button_bg_color' ) . ';';
		}
		$review_button_style = apply_filters( 'viwcpr_reminder_get_review_button_style', $review_button_style );
		$show_star_rating    = self::$settings->get_params( 'followup_email', 'star_rating' );
		foreach ( $products as $p ) {
			if ( $wpml_active ) {
				$p = apply_filters( 'wpml_object_id', $p, 'product', true, $language );
			}
			$product = wc_get_product( $p );
			if ( $product ) {
				$product_image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wp_get_attachment_thumb_url( $product->get_image_id() );
				$product_url   = $product->get_permalink();
				if ( $wpml_active ) {
					$product_url = self::get_permalink_by_language( $product_url, $language );
				}
				$product_url  .= self::$anchor_link;
				$product_url1 = $product_url;
				if ( $review_form_page_url ) {
					$product_url = add_query_arg( array(
						'product_id' => $p,
						'user_name'  => urlencode( base64_encode( $customer_name ) ),
						'user_email' => urlencode( base64_encode( $user_email ) ),
						'user_id'    => urlencode( base64_encode( $user_id ) ),
					), $review_form_page_url );
				}
				if ( $token ) {
					$product_url = add_query_arg( 'wcpr_token', $token, $product_url );
				}
				$product_title = $product->get_title();
				do_action( 'viwcpr_reminder_before_get_product_html', $order, $products );
				$product_price = apply_filters( 'woocommerce_photo_reviews_reminder_product_price', $product->get_price_html(), $product, $order );
				do_action( 'viwcpr_reminder_after_get_product_html', $order, $products );
				$wcpr_products_to_review[] = array(
					'image'      => $product_image,
					'name'       => $product_title,
					'price'      => $product_price,
					'review_url' => $product_url,
				);
				ob_start();
				$target_link = apply_filters( 'viwcpr_hook_target_link_product', '_blank' );
				?>
                <tr>
                    <td style="text-align: center;">
                        <a target="<?php echo esc_attr( $target_link ) ?>" href="<?php echo esc_attr( $product_url ) ?>">
                            <img style="width: <?php echo esc_attr( $product_image_width ) ?>px;"
                                 src="<?php echo esc_url( $product_image ) ?>"
                                 alt="<?php echo esc_attr( $product_title ) ?>">
                        </a>
                    </td>
                    <td>
                        <p>
                            <a target="<?php echo esc_attr( $target_link ) ?>" href="<?php echo esc_attr( $product_url ) ?>"><?php echo esc_attr( $product_title ) ?></a>
                        </p>
                        <p><?php echo wp_kses_post( $product_price ) ?></p>
						<?php
						if ( $show_star_rating ) {
							$product_url1 = add_query_arg( array(
								'wcpr_reminder_review' => 1,
								'product_id'           => $p,
								'user_name'            => urlencode( base64_encode( $customer_name ) ),
								'user_email'           => urlencode( base64_encode( $user_email ) ),
								'user_id'              => urlencode( base64_encode( $user_id ) ),
							), $product_url1 );
							?>
                            <a target="<?php echo esc_attr( $target_link ) ?>" style="display: flex; margin-bottom: 16px;" href="<?php echo esc_url( $product_url1 ) ?>">
                                <img src="<?php echo esc_url( VI_WOOCOMMERCE_PHOTO_REVIEWS_IMAGES . 'star.png' ) ?>" alt="star" style="width: 20px;height: 20px;margin-right: 2px;">
                                <img src="<?php echo esc_url( VI_WOOCOMMERCE_PHOTO_REVIEWS_IMAGES . 'star.png' ) ?>" alt="star" style="width: 20px;height: 20px;margin-right: 2px;">
                                <img src="<?php echo esc_url( VI_WOOCOMMERCE_PHOTO_REVIEWS_IMAGES . 'star.png' ) ?>" alt="star" style="width: 20px;height: 20px;margin-right: 2px;">
                                <img src="<?php echo esc_url( VI_WOOCOMMERCE_PHOTO_REVIEWS_IMAGES . 'star.png' ) ?>" alt="star" style="width: 20px;height: 20px;margin-right: 2px;">
                                <img src="<?php echo esc_url( VI_WOOCOMMERCE_PHOTO_REVIEWS_IMAGES . 'star.png' ) ?>" alt="star" style="width: 20px;height: 20px;">
                            </a>
							<?php
						}
						?>
                        <a target="<?php echo esc_attr( $target_link ) ?>"
                           style="<?php echo esc_attr( $review_button_style ) ?>"
                           href="<?php echo esc_url( $product_url ) ?>"><?php echo wp_kses_post( $review_button_title ) ?>
                        </a>
                    </td>
                </tr>
				<?php
				$products_html .= ob_get_clean();
			}
		}
		if ( $products_html ) {
			$order_id        = $order->get_id();
			$subject         = $content = '';
			$email_template  = self::$settings->get_params( 'reminder_email_template', '', $language );
			$resend_reminder = $order->get_meta( '_wcpr_review_reminder_resend', true );
			if ( $resend_reminder && 'on' === self::$settings->get_params( 'followup_email', 'loop' ) ) {
				$resend_email_template = self::$settings->get_params( 'followup_email', 'loop_email_template' );
				$resend_index          = array_search( $resend_reminder, $resend_email_template['times'] ?? array() );
				if ( $resend_index !== false ) {
					$email_template = ! empty( $resend_email_template[ 'email_template' . ( $language ? '_' . $language : '' ) ][ $resend_index ] ) ? $resend_email_template[ 'email_template' . ( $language ? '_' . $language : '' ) ][ $resend_index ] : $email_template;
				}
			}
			$use_template = false;
			if ( $email_template && VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::is_email_template_customizer_active() ) {
				$email_template_obj = get_post( $email_template );
				if ( $email_template_obj && $email_template_obj->post_type === 'viwec_template' ) {
					$use_template = true;
					$viwec_email  = new VIWEC_Render_Email_Template( array(
						'template_id' => $email_template,
						'order'       => $order
					) );
					ob_start();
					$viwec_email->get_content();
					$content = ob_get_clean();
					$subject = $viwec_email->get_subject();
					$content = str_replace( array(
						'{wcpr_customer_name}',
						'{wcpr_order_id}',
						'{wcpr_order_date_create}',
						'{wcpr_order_date_complete}'
					), array( $customer_name, $order_id, $date_create, $date_complete ), $content );
					$content = str_replace( array(
						'{site_title}',
						'{wcpr_site_title}'
					), get_bloginfo( 'name' ), $content );
					$subject = str_replace( array(
						'{wcpr_customer_name}',
						'{wcpr_order_id}',
						'{wcpr_order_date_create}',
						'{wcpr_order_date_complete}'
					), array( $customer_name, $order_id, $date_create, $date_complete ), $subject );
					$subject = str_replace( array(
						'{site_title}',
						'{wcpr_site_title}'
					), get_bloginfo( 'name' ), $subject );
				}
			}
			$mailer = WC()->mailer();
			$email  = new WC_Email();
			if ( ! $use_template ) {
				$content = nl2br( stripslashes( self::$settings->get_params( 'followup_email', 'content', $language ) ) );
				if ( $resend_reminder ) {
					$resend_email_content = self::$settings->get_params( 'followup_email', 'loop_email_content' );
					$resend_index         = array_search( $resend_reminder, $resend_email_content['times'] ?? array() );
					if ( $resend_index !== false ) {
						$content = ! empty( $resend_email_content[ 'email_content' . ( $language ? '_' . $language : '' ) ][ $resend_index ] ) ? nl2br( stripslashes( $resend_email_content[ 'email_content' . ( $language ? '_' . $language : '' ) ][ $resend_index ] ) ) : $content;
					}
				}
				$content = str_replace( '{customer_name}', $customer_name, $content );
				$content = str_replace( '{order_id}', $order_id, $content );
				$content = str_replace( '{date_create}', $date_create ?: '', $content );
				$content = str_replace( '{date_complete}', $date_complete ?: '', $content );
				$content = str_replace( '{site_title}', get_bloginfo( 'name' ), $content );
				$content .= '<table style="width: 100%;">' . $products_html . '</table>';
				add_filter( 'woocommerce_email_from_address', array( __CLASS__, 'review_reminder_from_address' ) );
				$subject       = stripslashes( self::$settings->get_params( 'followup_email', 'subject', $language ) );
				$email_heading = self::$settings->get_params( 'followup_email', 'heading', $language );
				$content       = $email->style_inline( $mailer->wrap_message( $email_heading, $content ) );
			}
			$headers = "Content-Type: text/html\r\nReply-to: {$email->get_from_name()} <{$email->get_from_address()}>\r\n";
			$subject = apply_filters( 'viwcpr_email_reminder_get_subject', $subject, $order_id );
			$content = apply_filters( 'viwcpr_email_reminder_get_content', $content, $order_id );
			$email->send( $user_email, $subject, $content, $headers, array() );
			$time = current_time( 'timestamp' );
			$order->update_meta_data( '_wcpr_review_reminder', array(
				'status' => 'sent',
				'time'   => $time,
			) );
			$order->delete_meta_data( '_wcpr_review_reminder_resend' );
			$order->save_meta_data();
			remove_filter( 'woocommerce_email_from_address', array( __CLASS__, 'review_reminder_from_address' ) );
		}
	}

	public static function get_permalink_by_language( $url, $lang ) {
		$tran_url = apply_filters( 'wpml_permalink', $url, $lang );
		if ( $url == $tran_url ) {
			$tran_url = apply_filters( 'wpml_permalink', $url, $lang, true );
		}

		return $tran_url;
	}

	public function send_schedule_email_new( $order_id ) {
		$sent = false;
		if ( 'on' === self::$settings->get_params( 'followup_email', 'enable' ) ) {
			$language = '';
			$order    = wc_get_order( $order_id );
			if ( $order ) {
				if ( self::$settings->get_params( 'multi_language' ) ) {
					$language = $order->get_meta( 'wpml_language' );
					if ( ! $language && function_exists( 'pll_get_post_language' ) ) {
						$language = pll_get_post_language( $order_id );
					}
				}
				$date_format = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_date_format();
				$date_create = $order->get_date_created();
				if ( $date_create ) {
					$date_create = $date_create->date_i18n( $date_format );
				}
				$date_complete = $order->get_date_completed();
				if ( $date_complete ) {
					$date_complete = $date_complete->date_i18n( $date_format );
				}
				$items                       = $order->get_items();
				$products                    = array();
				$products_restriction        = self::$settings->get_params( 'followup_email', 'products_restriction' );
				$excluded_categories         = self::$settings->get_params( 'followup_email', 'excluded_categories' );
				$exclude_non_coupon_products = self::$settings->get_params( 'followup_email', 'exclude_non_coupon_products' );
				foreach ( $items as $item ) {
					$product_id = $item->get_product_id();
					if ( in_array( $product_id, $products_restriction ) ) {
						continue;
					}
					if ( count( $excluded_categories ) ) {
						$product = wc_get_product( $product_id );
						if ( $product ) {
							$categories = $product->get_category_ids();
							if ( count( array_intersect( $categories, $excluded_categories ) ) ) {
								continue;
							}
						}
					}
					if ( $exclude_non_coupon_products === 'on' && ! self::has_coupon_rule( $product_id ) ) {
						continue;
					}
					$products[] = $product_id;
				}
				$products = array_unique( $products );
				if ( count( $products ) ) {
					$user_email    = $order->get_billing_email();
					$customer_name = $order->get_billing_first_name();
					$user_id       = $order->get_user_id();
					if ( ! $user_id ) {
						$user = get_user_by( 'email', $user_email );
						if ( $user ) {
							$user_id = $user->ID;
						}
					}
					if ( $user_id ) {
						$user_reviewed_products = get_user_meta( $user_id, 'wcpr_user_reviewed_product', false );
						$auto_login             = self::$settings->get_params( 'followup_email', 'auto_login' );
						$auto_login_exclude     = self::$settings->get_params( 'followup_email', 'auto_login_exclude' );
						$token                  = '';
						if ( ! count( $user_reviewed_products ) ) {
							/*this user did not review any products*/
							if ( count( $products ) ) {
								if ( $auto_login ) {
									$user = new WP_User( $user_id );
									if ( empty( array_intersect( $auto_login_exclude, $user->roles ) ) ) {
										$token = uniqid( md5( microtime() . $order_id ) );
										$token = hash( "sha256", "{$user_id}{$order_id}{$token}" );
										set_transient( $token, $user_id, 2592000 );
										update_user_meta( $user_id, 'wcpr_token', $token );
									}
								}
								$sent = true;
								$this->send_schedule_email1( $user_email, $customer_name, $products, $order, $date_create, $date_complete, $language, $token, $user_id );
							}
						} else {
							/*only send review reminder if there are products in the order that this user has not reviewed*/
							$not_reviewed_products = array_diff( $products, $user_reviewed_products );
							if ( count( $not_reviewed_products ) ) {
								if ( $auto_login ) {
									$user = new WP_User( $user_id );
									if ( empty( array_intersect( $auto_login_exclude, $user->roles ) ) ) {
										$token = uniqid( md5( microtime() . $order_id ) );
										$token = hash( "sha256", "{$user_id}{$order_id}{$token}" );
										set_transient( $token, $user_id, 2592000 );
										update_user_meta( $user_id, 'wcpr_token', $token );
									}
								}
								$sent = true;
								$this->send_schedule_email1( $user_email, $customer_name, $not_reviewed_products, $order, $date_create, $date_complete, $language, $token,
									$user_id );
							}
						}
					} else if ( $user_email ) {
						$sents = array();
						foreach ( $products as $p ) {
							$args     = array(
								'post_type'    => 'product',
								'type'         => 'review',
								'author_email' => $user_email,
								'post_id'      => $p,
								'meta_query'   => array(
									'relation' => 'AND',
									array(
										'key'     => 'id_import_reviews_from_ali',
										'compare' => 'NOT EXISTS'
									),
								)
							);
							$comments = self::get_comments( $args );
							if ( ! count( $comments ) ) {
								$sents[] = $p;
							}
						}
						if ( count( $sents ) ) {
							$sent = true;
							$this->send_schedule_email1( $user_email, $customer_name, $sents, $order, $date_create, $date_complete, $language, '', $user_id );
						}
					}
				}
			}
		}
		if ( ! $sent ) {
			$order = wc_get_order( $order_id );
			$order->delete_meta_data( '_wcpr_review_reminder' );
			$order->delete_meta_data( '_wcpr_review_reminder_resend' );
			$order->save_meta_data();
		}
	}

	public function schedule( $order_id ) {
		$next_schedule = wp_next_scheduled( 'wcpr_schedule_email', array( $order_id ) );
		if ( $next_schedule ) {
			wp_unschedule_event( $next_schedule, 'wcpr_schedule_email', array( $order_id ) );
		}
		$t_amount = (float) self::$settings->get_params( 'followup_email', 'amount' );
		$t_unit   = self::$settings->get_params( 'followup_email', 'unit' );
		switch ( $t_unit ) {
			case 's':
				$t = $t_amount;
				break;
			case 'm':
				$t = $t_amount * 60;
				break;
			case 'h':
				$t = $t_amount * 3600;
				break;
			case 'd':
				$t = $t_amount * 86400;
				break;
			default:
				$t = 0;
		}
		if ( $t ) {
			$time     = time() + $t;
			$schedule = wp_schedule_single_event( $time, 'wcpr_schedule_email', array( $order_id ) );
			if ( $schedule !== false ) {
				$order = wc_get_order( $order_id );
				$order->update_meta_data( '_wcpr_review_reminder', array(
					'status' => 'pending',
					'time'   => $time,
				) );
				$order->save_meta_data();
			}
		} else {
			$this->send_schedule_email_new( $order_id );
		}
	}

	/**
	 * @param $order_id
	 * @param $order WC_Order
	 */
	public function follow_up_email( $order_id, $order ) {
		$user_email = $order->get_billing_email();
		if ( in_array( $user_email, self::$settings->get_params( 'followup_email', 'exclude_addresses' ) ) ) {
			return;
		}
		$items                       = $order->get_items();
		$products                    = array();
		$products_restriction        = self::$settings->get_params( 'followup_email', 'products_restriction' );
		$excluded_categories         = self::$settings->get_params( 'followup_email', 'excluded_categories' );
		$exclude_non_coupon_products = self::$settings->get_params( 'followup_email', 'exclude_non_coupon_products' );
		foreach ( $items as $item ) {
			$product_id = $item->get_product_id();
			if ( in_array( $product_id, $products_restriction ) ) {
				continue;
			}
			if ( count( $excluded_categories ) ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$categories = $product->get_category_ids();
					if ( count( array_intersect( $categories, $excluded_categories ) ) ) {
						continue;
					}
				}
			}
			if ( $exclude_non_coupon_products === 'on' && ! self::has_coupon_rule( $product_id ) ) {
				continue;
			}
			$products[] = $product_id;
		}
		$products = array_unique( $products );
		if ( count( $products ) ) {
			$user_id = $order->get_user_id();
			if ( ! $user_id ) {
				$user = get_user_by( 'email', $user_email );
				if ( $user ) {
					$user_id = $user->ID;
				}
			}
			if ( $user_id ) {
				$user_reviewed_products = get_user_meta( $user_id, 'wcpr_user_reviewed_product', false );
				if ( ! count( $user_reviewed_products ) ) {
					/*this user did not review any products*/
					$this->schedule( $order_id );
				} else {
					/*only send review reminder if there are products in the order that this user did not review*/
					$not_reviewed_products = array_diff( $products, $user_reviewed_products );
					if ( count( $not_reviewed_products ) ) {
						$this->schedule( $order_id );
					}
				}
			} else {
				$sents = array();
				foreach ( $products as $p ) {
					$args     = array(
						'post_type'    => 'product',
						'type'         => 'review',
						'author_email' => $user_email,
						'post_id'      => $p,
						'meta_query'   => array(
							'relation' => 'AND',
							array(
								'key'     => 'id_import_reviews_from_ali',
								'compare' => 'NOT EXISTS'
							),
						)
					);
					$comments = self::get_comments( $args );
					if ( ! count( $comments ) ) {
						$sents[] = $p;
					}
				}
				if ( count( $sents ) ) {
					$this->schedule( $order_id );
				}
			}
		}
	}

	public function send_email( $user_email, $customer_name, $coupon_code, $date_expires, $email_temp = array(), $email_template = '', $comment_id = 0 ) {
		add_filter( 'woocommerce_email_from_address', array( $this, 'review_coupon_from_address' ) );
		$use_template         = false;
		$subject              = $content = '';
		$date_expires         = $date_expires ? (float) $date_expires : '';
		$last_valid_date      = $date_expires ? $date_expires - 86400 : '';
		$show_last_valid_date = empty( $last_valid_date ) ? esc_html__( 'never expires', 'woocommerce-photo-reviews' ) : date_i18n( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_date_format(), $last_valid_date );
		$show_date_expires    = empty( $date_expires ) ? esc_html__( 'never expires', 'woocommerce-photo-reviews' ) : date_i18n( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_date_format(), $date_expires );
		if ( $email_template && VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::is_email_template_customizer_active() ) {
			$email_template_obj = get_post( $email_template );
			if ( $email_template_obj && $email_template_obj->post_type === 'viwec_template' ) {
				$use_template = true;
				$viwec_email  = new VIWEC_Render_Email_Template( array( 'template_id' => $email_template, ) );
				ob_start();
				$viwec_email->get_content();
				$content = ob_get_clean();
				$subject = $viwec_email->get_subject();
				$content = str_replace( array(
					'{wcpr_customer_name}',
					'{wcpr_coupon_code}',
					'{wcpr_date_expires}',
					'{wcpr_last_valid_date}'
				), array(
					$customer_name,
					$coupon_code,
					$show_date_expires,
					$show_last_valid_date
				), $content );
				$subject = str_replace( array(
					'{wcpr_customer_name}',
					'{wcpr_coupon_code}',
					'{wcpr_date_expires}',
					'{wcpr_last_valid_date}'
				), array(
					$customer_name,
					$coupon_code,
					$show_date_expires,
					$show_last_valid_date
				), $subject );
			}
		}
		$mailer  = WC()->mailer();
		$email   = new WC_Email();
		$headers = "Content-Type: text/html\r\nReply-to: {$email->get_from_name()} <{$email->get_from_address()}>\r\n";
		$headers = apply_filters( 'viwcpr_email_coupon_headers', $headers );
		if ( ! $use_template ) {
			$subject       = stripslashes( $email_temp['subject'] );
			$content       = nl2br( stripslashes( $email_temp['content'] ) );
			$content       = str_replace( '{customer_name}', $customer_name, $content );
			$content       = str_replace( '{coupon_code}', '<span style="font-size: x-large;">' . strtoupper( $coupon_code ) . '</span>', $content );
			$content       = str_replace( '{date_expires}', $show_date_expires, $content );
			$content       = str_replace( '{last_valid_date}', $show_last_valid_date, $content );
			$email_heading = isset( $email_temp['heading'] ) ? $email_temp['heading'] : esc_html__( 'Thank You For Your Review!', 'woocommerce-photo-reviews' );
			$content       = $email->style_inline( $mailer->wrap_message( $email_heading, $content ) );
		}
		$subject = apply_filters( 'viwcpr_email_coupon_get_subject', $subject, $comment_id );
		$content = apply_filters( 'viwcpr_email_coupon_get_content', $content, $comment_id );
		$email->send( $user_email, $subject, $content, $headers, array() );
		remove_filter( 'woocommerce_email_from_address', array( $this, 'review_coupon_from_address' ) );
	}

	protected function rand() {
		if ( $this->characters_array === null ) {
			$this->characters_array = array_merge( range( 0, 9 ), range( 'a', 'z' ) );
		}
		$rand = rand( 0, count( $this->characters_array ) - 1 );

		return $this->characters_array[ $rand ];
	}

	protected function create_code( $prefix = '' ) {
		$code = $prefix;
		for ( $i = 0; $i < 6; $i ++ ) {
			$code .= $this->rand();
		}
		$args      = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'title'          => $code
		);
		$the_query = new WP_Query( $args );
		if ( $the_query->have_posts() ) {
			wp_reset_postdata();
			$code = $this->create_code( $prefix );
		}
		wp_reset_postdata();

		return $code;
	}

	public function generate_coupon( $coupon_rule_id = '', $coupon_rule_index = '' ) {
		if ( ! $coupon_rule_id || $coupon_rule_index === '' ) {
			return false;
		}
		if ( ! self::$settings->get_current_setting( 'coupons', 'active', $coupon_rule_index ) ) {
			return false;
		}
		remove_all_filters( 'woocommerce_coupon_get_date_expires' );
		$coupon_select = self::$settings->get_current_setting( 'coupons', 'coupon_select', $coupon_rule_index );
		if ( $coupon_select === 'kt_generate_coupon' ) {
			$coupon_generate = self::$settings->get_current_setting( 'coupons', 'unique_coupon', $coupon_rule_id );
			$code            = $this->create_code( $coupon_generate['coupon_code_prefix'] ?? '' );
			$coupon          = new WC_Coupon( $code );
			$today           = strtotime( date( 'Ymd' ) );
			$date_expires    = ( $coupon_generate['expiry_date'] ) ? ( ( $coupon_generate['expiry_date'] + 1 ) * 86400 + $today ) : '';
			$coupon->set_amount( $coupon_generate['coupon_amount'] );
			$coupon->set_date_expires( $date_expires );
			$coupon->set_discount_type( $coupon_generate['discount_type'] );
			$coupon->set_individual_use( in_array( $coupon_generate['individual_use'] ?? '', [ 'yes', '1' ] ) ? 1 : 0 );
			if ( ! empty( $coupon_generate['product_ids'] ) ) {
				$coupon->set_product_ids( $coupon_generate['product_ids'] );
			}
			if ( ! empty( $coupon_generate['excluded_product_ids'] ) ) {
				$coupon->set_excluded_product_ids( $coupon_generate['excluded_product_ids'] );
			}
			$coupon->set_usage_limit( $coupon_generate['limit_per_coupon'] );
			$coupon->set_usage_limit_per_user( $coupon_generate['limit_per_user'] );
			$coupon->set_limit_usage_to_x_items( $coupon_generate['limit_to_x_items'] );
			$coupon->set_free_shipping( in_array( $coupon_generate['allow_free_shipping'] ?? '', [
				'yes',
				'1'
			] ) ? 1 : 0 );
			$coupon->set_product_categories( $coupon_generate['product_categories'] ?? array() );
			$coupon->set_excluded_product_categories( $coupon_generate['excluded_product_categories'] ?? array() );
			$coupon->set_exclude_sale_items( in_array( $coupon_generate['exclude_sale_items'] ?? '', [
				'yes',
				'1'
			] ) ? 1 : 0 );
			$coupon->set_minimum_amount( $coupon_generate['min_spend'] );
			$coupon->set_maximum_amount( $coupon_generate['max_spend'] );
			$coupon->save();
			$code = $coupon->get_code();
			/*Update date expires this way to prevent Advanced Coupons for WooCommerce Premium from override this value(set empty)*/
			update_post_meta( $coupon->get_id(), 'date_expires', $date_expires );
			update_post_meta( $coupon->get_id(), 'kt_unique_coupon', 'yes' );
		} else {
			$coupon = new WC_Coupon( self::$settings->get_current_setting( 'coupons', 'existing_coupon', $coupon_rule_id ) );
			$code   = $coupon->get_code();
			if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) {
				return false;
			}
			if ( $coupon->get_date_expires() && time() > $coupon->get_date_expires()->getTimestamp() ) {
				return false;
			}
		}

		return $code;
	}

	public function coupon_for_not_logged_in( $comment_id ) {
		if ( get_comment_meta( $comment_id, 'coupon_for_reviews', true ) ) {
			return;
		}
		$comment = get_comment( $comment_id );
		if ( ( $comment->comment_approved ?? '' ) != 1 ) {
			return;
		}
		if ( get_comment_meta( $comment_id, 'coupon_email', true ) ) {
			return;
		}
		$user_email = $comment->comment_author_email;
		$user_id    = $comment->user_id;
		if ( ! $user_id ) {
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}
		if ( ! $user_email && ! $user_id ) {
			return;
		}
		$coupon_rule       = self::get_coupon_rule_id( $comment_id, $user_id, $user_email );
		$coupon_rule_id    = $coupon_rule['id'] ?? '';
		$coupon_rule_index = $coupon_rule['index'] ?? '';
		if ( ! $coupon_rule_id || $coupon_rule_index === '' ) {
			return;
		}
		$language = '';
		if ( self::$settings->get_params( 'multi_language' ) ) {
			$current_language = get_comment_meta( $comment_id, 'wcpr_current_language', true );
			if ( $current_language ) {
				if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
					$default_lang = apply_filters( 'wpml_default_language', null );
					if ( $current_language !== $default_lang ) {
						$language = $current_language;
					}
				} else if ( class_exists( 'Polylang' ) ) {
					$default_lang = pll_default_language( 'slug' );
					if ( $current_language !== $default_lang ) {
						$language = $current_language;
					}
				}
			}
		}
		$product_id    = $comment->comment_post_ID;
		$customer_name = $comment->comment_author;
		if ( $user_id ) {
			$user_coupon = get_user_meta( $user_id, 'wcpr_user_reviewed_product', false );
			if ( ! is_array( $user_coupon ) || ! count( $user_coupon ) || ! in_array( $product_id, $user_coupon ) ) {
				$code = $this->generate_coupon( $coupon_rule_id, $coupon_rule_index );
				if ( $code ) {
					$c  = new WC_Coupon( $code );
					$er = $c->get_email_restrictions();
					if ( self::$settings->get_params( 'set_email_restriction' ) && ! in_array( $user_email, $er ) ) {
						$er[] = $user_email;
						$c->set_email_restrictions( $er );
						$c->save();
					}
					$coupon_code    = $c->get_code();
					$date_expires   = $c->get_date_expires() ? $c->get_date_expires()->getTimestamp() : '';
					$email_temp     = self::$settings->get_current_setting( 'coupons', 'email', $coupon_rule_id, $language );
					$email_template = self::$settings->get_current_setting( 'coupons', 'email_template', $coupon_rule_id, $language );
					$this->send_email( $user_email, $customer_name, $coupon_code, $date_expires, $email_temp, $email_template, $comment_id );
					add_user_meta( $user_id, 'wcpr_user_reviewed_product', $product_id );
					update_comment_meta( $comment_id, 'coupon_email', 'sent' );
					update_comment_meta( $comment_id, 'coupon_for_reviews', 1 );
				}
			}
		} else {
			$args     = array(
				'post_type'    => 'product',
				'type'         => 'review',
				'author_email' => $user_email,
				'post_id'      => $product_id,
				'meta_query'   => array(
					'relation' => 'AND',
					array(
						'key'     => 'id_import_reviews_from_ali',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'     => 'coupon_email',
						'compare' => 'EXISTS'
					),
				)
			);
			$comments = self::get_comments( $args );
			if ( ! count( $comments ) ) {
				$code = $this->generate_coupon( $coupon_rule_id, $coupon_rule_index );
				if ( $code ) {
					$c  = new WC_Coupon( $code );
					$er = $c->get_email_restrictions();
					if ( self::$settings->get_params( 'set_email_restriction' ) && ! in_array( $user_email, $er ) ) {
						$er[] = $user_email;
						$c->set_email_restrictions( $er );
						$c->save();
					}
					$coupon_code    = $c->get_code();
					$date_expires   = $c->get_date_expires() ? $c->get_date_expires()->getTimestamp() : '';
					$email_temp     = self::$settings->get_current_setting( 'coupons', 'email', $coupon_rule_id, $language );
					$email_template = self::$settings->get_current_setting( 'coupons', 'email_template', $coupon_rule_id, $language );
					$this->send_email( $user_email, $customer_name, $coupon_code, $date_expires, $email_temp, $email_template, $comment_id );
					update_comment_meta( $comment_id, 'coupon_email', 'sent' );
					update_comment_meta( $comment_id, 'coupon_for_reviews', 1 );
				}
			}
		}
	}

	public function review_coupon_from_address( $from_address ) {
		$new_address = self::$settings->get_current_setting( 'coupons', 'email', 'from_address', '', '' );
		if ( $new_address && is_email( $new_address ) ) {
			$from_address = sanitize_email( $new_address );
		}

		return $from_address;
	}

	public static function has_coupon_rule( $product_id ) {
		if ( ! $product_id ) {
			return false;
		}
		if ( isset( self::$cache['coupon_rule_per_product'][ $product_id ] ) ) {
			return self::$cache['coupon_rule_per_product'][ $product_id ];
		}
		$ids = self::$settings->get_params( 'coupons', 'ids' );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return self::$cache['coupon_rule_per_product'][ $product_id ] = true;
		}
		$result = false;
		foreach ( $ids as $i => $id ) {
			if ( ! self::$settings->get_current_setting( 'coupons', 'active', $i ) ) {
				continue;
			}
			$products_gen_coupon = self::$settings->get_current_setting( 'coupons', 'product_include', $id, '', array() );
			if ( ! empty( $products_gen_coupon ) && ! in_array( $product_id, $products_gen_coupon ) ) {
				continue;
			}
			$excluded_products_gen_coupon = self::$settings->get_current_setting( 'coupons', 'product_exclude', $id, '', array() );
			if ( in_array( $product_id, $excluded_products_gen_coupon ) ) {
				continue;
			}
			$categories_gen_coupon          = self::$settings->get_current_setting( 'coupons', 'cats_include', $id, '', array() );
			$excluded_categories_gen_coupon = self::$settings->get_current_setting( 'coupons', 'cats_exclude', $id, '', array() );
			$cate_ids                       = $cate_ids ?? wc_get_product_cat_ids( $product_id );
			if ( count( $categories_gen_coupon ) && ! count( array_intersect( $cate_ids, $categories_gen_coupon ) ) ) {
				continue;
			} elseif ( count( array_intersect( $cate_ids, $excluded_categories_gen_coupon ) ) ) {
				continue;
			}
			$result = $id;
			break;
		}

		return self::$cache['coupon_rule_per_product'][ $product_id ] = $result;
	}

	public static function get_coupon_rule_id( $comment_id, $user_id = '', $user_email = '' ) {
		if ( ! $comment_id ) {
			return false;
		}
		if ( isset( self::$cache['coupon_rule_per_comment'][ $comment_id ] ) ) {
			return self::$cache['coupon_rule_per_comment'][ $comment_id ];
		}
		$ids = self::$settings->get_params( 'coupons', 'ids' );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return self::$cache['coupon_rule_per_comment'][ $comment_id ] = false;
		}
		$result     = false;
		$comment    = get_comment( $comment_id );
		$product_id = $comment->comment_post_ID;
		foreach ( $ids as $i => $id ) {
			if ( ! self::$settings->get_current_setting( 'coupons', 'active', $i ) ) {
				continue;
			}
			$coupon_require = self::$settings->get_current_setting( 'coupons', 'require', $id );
			if ( intval( get_comment_meta( $comment_id, 'rating', true ) ) < intval( $coupon_require['min_rating'] ?? 0 ) ) {
				continue;
			}
			if ( 'on' === self::$settings->get_params( 'photo', 'enable' ) && ! empty( $coupon_require['photo'] ) && ! get_comment_meta( $comment_id, 'reviews-images', true ) ) {
				continue;
			}
			$products_gen_coupon = self::$settings->get_current_setting( 'coupons', 'product_include', $id, '', array() );
			if ( ! empty( $products_gen_coupon ) && ! in_array( $product_id, $products_gen_coupon ) ) {
				continue;
			}
			$excluded_products_gen_coupon = self::$settings->get_current_setting( 'coupons', 'product_exclude', $id, '', array() );
			if ( in_array( $product_id, $excluded_products_gen_coupon ) ) {
				continue;
			}
			if ( ! empty( $coupon_require['owner'] ) && 1 != get_comment_meta( $comment_id, 'verified', true ) ) {
				continue;
			}
			if ( ! empty( $coupon_require['owner'] ) ) {
				$verified = false;
				if ( 'product' === get_post_type( $product_id ) ) {
					$verified = wc_customer_bought_product( $user_email, $user_id, $product_id );
				}
				if ( ! $verified ) {
					continue;
				}
			}
			if ( ! $user_id && ! empty( $coupon_require['register'] ) ) {
				continue;
			}
			$categories_gen_coupon          = self::$settings->get_current_setting( 'coupons', 'cats_include', $id, '', array() );
			$excluded_categories_gen_coupon = self::$settings->get_current_setting( 'coupons', 'cats_exclude', $id, '', array() );
			$cate_ids                       = $cate_ids ?? wc_get_product_cat_ids( $product_id );
			if ( count( $categories_gen_coupon ) && ! count( array_intersect( $cate_ids, $categories_gen_coupon ) ) ) {
				continue;
			} elseif ( count( array_intersect( $cate_ids, $excluded_categories_gen_coupon ) ) ) {
				continue;
			}
			$result = array(
				'index' => $i,
				'id'    => $id,
			);
			break;
		}

		return self::$cache['coupon_rule_per_comment'][ $comment_id ] = $result;
	}

	public function send_coupon_after_reviews( $comment_id, $approve, $commentdata ) {
		$comment_type = isset( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '';
		if ( $comment_type !== 'review' ) {
			return;
		}
		$comment  = get_comment( $comment_id );
		$language = self::get_language();
		if ( $comment->comment_approved != 1 ) {
			update_comment_meta( $comment_id, 'coupon_for_reviews', "0" );
			if ( $language ) {
				update_comment_meta( $comment_id, 'wcpr_current_language', $language );
			}

			return;
		}
		$user_email = $comment->comment_author_email;
		$user_id    = $comment->user_id;
		if ( ! $user_id ) {
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}
		if ( ! $user_email && ! $user_id ) {
			return;
		}
		$coupon_rule       = self::get_coupon_rule_id( $comment_id, $user_id, $user_email );
		$coupon_rule_id    = $coupon_rule['id'] ?? '';
		$coupon_rule_index = $coupon_rule['index'] ?? '';
		if ( ! $coupon_rule_id || $coupon_rule_index === '' ) {
			return;
		}
		$product_id    = $comment->comment_post_ID;
		$customer_name = $comment->comment_author;
		if ( $user_id ) {
			$user_coupon = get_user_meta( $user_id, 'wcpr_user_reviewed_product', false );
			if ( ! is_array( $user_coupon ) || ! count( $user_coupon ) || ! in_array( $product_id, $user_coupon ) ) {
				$code = $this->generate_coupon( $coupon_rule_id, $coupon_rule_index );
				if ( $code ) {
					$c  = new WC_Coupon( $code );
					$er = $c->get_email_restrictions();
					if ( self::$settings->get_params( 'set_email_restriction' ) && ! in_array( $user_email, $er ) ) {
						$er[] = $user_email;
						$c->set_email_restrictions( $er );
						$c->save();
					}
					$coupon_code    = $c->get_code();
					$date_expires   = $c->get_date_expires() ? $c->get_date_expires()->getTimestamp() : '';
					$email_temp     = self::$settings->get_current_setting( 'coupons', 'email', $coupon_rule_id, $language );
					$email_template = self::$settings->get_current_setting( 'coupons', 'email_template', $coupon_rule_id, $language );
					$this->send_email( $user_email, $customer_name, $coupon_code, $date_expires, $email_temp, $email_template, $comment_id );
					add_user_meta( $user_id, 'wcpr_user_reviewed_product', $product_id );
					update_comment_meta( $comment_id, 'coupon_email', 'sent' );
				}
			}
		} else {
			$args     = array(
				'post_type'    => 'product',
				'type'         => 'review',
				'author_email' => $user_email,
				'post_id'      => $product_id,
				'meta_query'   => array(
					'relation' => 'AND',
					array(
						'key'     => 'id_import_reviews_from_ali',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'     => 'coupon_email',
						'compare' => 'EXISTS'
					),
				)
			);
			$comments = self::get_comments( $args );
			if ( ! count( $comments ) ) {
				$code = $this->generate_coupon( $coupon_rule_id, $coupon_rule_index );
				if ( $code ) {
					$c  = new WC_Coupon( $code );
					$er = $c->get_email_restrictions();
					if ( self::$settings->get_params( 'set_email_restriction' ) && ! in_array( $user_email, $er ) ) {
						$er[] = $user_email;
						$c->set_email_restrictions( $er );
						$c->save();
					}
					$coupon_code    = $c->get_code();
					$date_expires   = $c->get_date_expires() ? $c->get_date_expires()->getTimestamp() : '';
					$email_temp     = self::$settings->get_current_setting( 'coupons', 'email', $coupon_rule_id, $language );
					$email_template = self::$settings->get_current_setting( 'coupons', 'email_template', $coupon_rule_id, $language );
					$this->send_email( $user_email, $customer_name, $coupon_code, $date_expires, $email_temp, $email_template, $comment_id );
					update_comment_meta( $comment_id, 'coupon_email', 'sent' );
				}
			}
		}
	}

	/**
	 * @param $name
	 * @param $comment_id
	 * @param $post_id
	 * @param $is_sideload
	 *
	 * @return mixed
	 */
	public function add_prefix_to_photo_name( $name, $comment_id, $post_id, $is_sideload ) {
		$current_comment_data  = get_comment( $comment_id, ARRAY_A );
		$author_cmt_id         = $current_comment_data['user_id'] ?? '';
		$customer_display_name = $current_comment_data['comment_author'] ?? '';
		if ( empty( $customer_display_name ) && ! empty( $author_cmt_id ) ) {
			$author_cmt_data       = get_user_by( 'ID', $author_cmt_id );
			$customer_display_name = $author_cmt_data->data->display_name ?? '';
		}

		if ( $is_sideload ) {
			$import_upload_prefix = self::$settings->get_params( 'import_upload_prefix' );
			if ( $import_upload_prefix ) {
				$import_upload_prefix = str_replace( array( '{comment_id}', '{product_id}', '{product_title}', '{customer_display_name}' ), array(
					$comment_id,
					$post_id,
					get_the_title( $post_id ),
					$customer_display_name
				), $import_upload_prefix );
				$name                 = sanitize_file_name( $import_upload_prefix ) . $name;
			}
		} else {
			$user_upload_prefix = self::$settings->get_params( 'user_upload_prefix' );
			if ( $user_upload_prefix ) {
				$user_upload_prefix = str_replace( array( '{comment_id}', '{product_id}', '{product_title}', '{customer_display_name}' ), array(
					$comment_id,
					$post_id,
					get_the_title( $post_id ),
					$customer_display_name
				), $user_upload_prefix );
				$name               = sanitize_file_name( $user_upload_prefix ) . $name;
			}
		}

		return $name;
	}

	/**Custom folder to save images uploaded in users' reviews
	 *
	 * @param $param
	 *
	 * @return mixed
	 */
	public static function user_upload_folder( $param ) {
		$settings           = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		$user_upload_folder = $settings->get_params( 'user_upload_folder' );
		if ( $user_upload_folder ) {
			$subdir = '';
			if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
				// Generate the yearly and monthly dirs
				$time   = current_time( 'mysql' );
				$y      = substr( $time, 0, 4 );
				$m      = substr( $time, 5, 2 );
				$subdir = "/$y/$m";
			}
			if ( ! empty( self::$product_id ) ) {
				$user_upload_folder = str_replace( '{product_id}', self::$product_id, $user_upload_folder );
			}
			$user_upload_folder = '/' . $user_upload_folder;
			$param['path']      = str_replace( $param['basedir'], $param['basedir'] . $user_upload_folder, $param['path'] );
			$param['url']       = str_replace( $param['baseurl'], $param['baseurl'] . $user_upload_folder, $param['url'] );
			if ( $subdir && ! empty( $param['subdir'] ) ) {
				$param['path'] = str_replace( $param['subdir'], $subdir, $param['path'] );
				$param['url']  = str_replace( $param['subdir'], $subdir, $param['url'] );
			}
		}

		return $param;
	}

	public static function reduce_image_sizes( $sizes ) {
		$reduce_array = apply_filters( 'woocommerce_photo_reviews_reduce_array', array(
			'thumbnail',
			'wcpr-photo-reviews',
			'medium'
		) );
		foreach ( $sizes as $k => $size ) {
			if ( in_array( $size, $reduce_array ) ) {
				continue;
			}
			unset( $sizes[ $k ] );
		}

		return $sizes;
	}

	public function viwcpr_wp_error_added( $code, $message, $data, $object ) {
		$errors = array(
			'comment_id_not_found',
			'comment_closed',
			'comment_on_trash',
			'comment_on_draft',
			'comment_on_password_protected',
			'not_logged_in',
			'require_name_email',
			'require_valid_email',
			'require_valid_comment',
			'comment_save_error',
		);
		if ( in_array( $code, $errors ) && ! empty( $_POST['wcpr_image_upload_nonce'] ) && wp_verify_nonce( wc_clean( $_POST['wcpr_image_upload_nonce'] ), 'wcpr_image_upload' ) ) {
			$link = ! empty( $_REQUEST['_wp_http_referer'] ) ? esc_url( villatheme_sanitize_fields( $_REQUEST['_wp_http_referer'] ) ) : ( ! empty( $_POST['comment_post_ID'] ) ? get_permalink( $_POST['comment_post_ID'] ) : home_url() );
			wc_add_notice( $message, 'error' );
			do_action( 'woocommerce_set_cart_cookies', true );
			wp_safe_redirect( $link );
			exit;
		}
	}

	public static function viwcpr_review_order() {
		$result = array( 'error' => '' );
		global $viwcpr_review_order;
		$viwcpr_review_order = 1;
		add_filter( 'wp_safe_redirect_fallback', function ( $url ) {
			global $viwcpr_review_order;
			if ( wp_doing_ajax() && $viwcpr_review_order ) {
				$notice = wc_print_notices( true );
				if ( $notice ) {
					$result['error'] = $notice;
					wp_send_json( $result );
				}
			}

			return $url;
		}, 10, 1 );
		$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
		if ( is_wp_error( $comment ) ) {
			$data = (int) $comment->get_error_data();
			if ( ! empty( $data ) ) {
				$error = $comment->get_error_message();
			} else {
				$error = 'wp_handle_comment_submission failed';
			}
			if ( $error ) {
				$result['error'] = $error;
				wp_send_json( $result );
			}
		}

		$user            = wp_get_current_user();
		$cookies_consent = ( isset( $_POST['wp-comment-cookies-consent'] ) );

		/**
		 * Perform other actions when comment cookies are set.
		 *
		 * @param WP_Comment $comment Comment object.
		 * @param WP_User $user Comment author's user object. The user may not exist.
		 * @param bool $cookies_consent Comment author's consent to store cookies.
		 *
		 * @since 4.9.6 The `$cookies_consent` parameter was added.
		 *
		 * @since 3.4.0
		 */
		do_action( 'set_comment_cookies', $comment, $user, $cookies_consent );
		if ( $comment->comment_approved ) {
			$message_type = 2;
			if ( 'sent' === get_comment_meta( $comment->comment_ID, 'coupon_email', true ) ) {
				update_comment_meta( $comment->comment_ID, 'coupon_email', 'notified' );
				$message_type = 1;
			}
			if ( $message_type === 1 ) {
				$result['html'] = apply_filters( 'viwcpr_thank_you_message_after_review', sprintf( '<div id="wcpr_thank_you_message" class="woocommerce-message"><p>%s</p></div>',
					wp_kses_post( self::$settings->get_params( 'thank_you_message_coupon', '', self::get_language() ) ) ) );
			} elseif ( $message_type === 2 ) {
				$result['html'] = apply_filters( 'viwcpr_thank_you_message_after_review', sprintf( '<div id="wcpr_thank_you_message" class="woocommerce-message"><p>%s</p></div>',
					wp_kses_post( self::$settings->get_params( 'thank_you_message', '', self::get_language() ) ) ) );
			}
		} else {
			$result['html'] = sprintf( '<p class="meta"><em class="woocommerce-review__awaiting-approval">%s</em></p>', esc_html__( 'Your review is awaiting approval', 'woocommerce-photo-reviews' ) );
		}
		$viwcpr_review_order = '';
		wp_send_json( $result );
	}

	public static function viwcpr_restrict_number_of_reviews() {
		$result = array( 'error' => '' );
		$error  = self::check_review( $comment_post_ID, $comment_parent, $user_ID, $comment_author, $comment_author_email, $comment_author_url, $comment_content );
		if ( $error ) {
			$result['error'] = $error;
			wp_send_json( $result );
		}
		if ( self::$settings->get_params( 'ajax_upload_file' ) ) {
			$error = self::check_upload_file();
			if ( $error ) {
				$result['error'] = $error;
				wp_send_json( $result );
			}
			$img_id = self::upload_file( '', $comment_post_ID );
			if ( ! is_array( $img_id ) ) {
				$result['error'] = wc_print_notices( true );
			} elseif ( count( $img_id ) ) {
				$result['img_id'] = implode( ',', $img_id );
			}
			$result['remove_upload_file'] = 1;
		}
		wp_send_json( $result );
	}

	public static function check_review( &$comment_post_ID, &$comment_parent, &$user_ID, &$comment_author, &$comment_author_email, &$comment_author_url, &$comment_content ) {
		$comment_post_ID      = 0;
		$comment_parent       = 0;
		$user_ID              = 0;
		$comment_author       = null;
		$comment_author_email = null;
		$comment_author_url   = null;
		$comment_content      = null;
		if ( ! isset( $_POST['wcpr_image_upload_nonce'] ) || ! wp_verify_nonce( wc_clean( $_POST['wcpr_image_upload_nonce'] ), 'wcpr_image_upload' ) ) {
			return 'wcpr_image_upload_nonce is invalid';
		}
		if ( isset( $_POST['comment_post_ID'] ) ) {
			$comment_post_ID = (int) sanitize_text_field( wp_unslash( $_POST['comment_post_ID'] ) );
		}
		if ( ! $comment_post_ID ) {
			return 'comment_post_ID not found';
		}
		if ( isset( $_POST['author'] ) && is_string( $_POST['author'] ) ) {
			$comment_author = trim( sanitize_text_field( strip_tags( wp_unslash( $_POST['author'] ) ) ) );
		}
		if ( isset( $_POST['email'] ) && is_string( $_POST['email'] ) ) {
			$comment_author_email = trim( sanitize_text_field( wp_unslash( $_POST['email'] ) ) );
		}
		if ( isset( $_POST['url'] ) && is_string( $_POST['url'] ) ) {
			$comment_author_url = trim( wp_kses_post( wp_unslash( $_POST['url'] ) ) );
		}
		if ( isset( $_POST['comment'] ) && is_string( $_POST['comment'] ) ) {
			$comment_content = trim( villatheme_sanitize_kses( $_POST['comment'] ) );
		}
		if ( isset( $_POST['comment_parent'] ) ) {
			$comment_parent = absint( sanitize_text_field( wp_unslash( $_POST['comment_parent'] ) ) );
		}
		$post = get_post( $comment_post_ID );
		if ( empty( $post->comment_status ) ) {
			return 'comment_id_not_found';
		}
		// get_post_status() will get the parent status for attachments.
		$status = get_post_status( $post );
		if ( ( 'private' === $status ) && ! current_user_can( 'read_post', $comment_post_ID ) ) {
			return 'comment_id_not_found';
		}
		$status_obj = get_post_status_object( $status );
		if ( ! comments_open( $comment_post_ID ) ) {
			return esc_html__( 'Sorry, comments are closed for this item.', 'woocommerce-photo-reviews' );
		} elseif ( 'trash' === $status ) {
			return 'comment_on_trash';
		} elseif ( ! $status_obj->public && ! $status_obj->private ) {
			if ( current_user_can( 'read_post', $comment_post_ID ) ) {
				return esc_html__( 'Sorry, comments are allowed for this item.', 'woocommerce-photo-reviews' );
			} else {
				return 'comment_on_draft';
			}
		} elseif ( post_password_required( $comment_post_ID ) ) {
			return 'comment_on_password_protected';
		}
		$user = wp_get_current_user();
		if ( $user->exists() ) {
			if ( empty( $user->display_name ) ) {
				$user->display_name = $user->user_login;
			}
			$comment_author       = $user->display_name;
			$comment_author_email = $user->user_email;
			$comment_author_url   = $user->user_url;
			$user_ID              = $user->ID;
		} else {
			if ( get_option( 'comment_registration' ) ) {
				return esc_html__( 'Sorry, you must be logged in to comment.', 'woocommerce-photo-reviews' );
			}
		}
		if ( get_option( 'require_name_email' ) && ! $user->exists() ) {
			if ( '' == $comment_author_email || '' == $comment_author ) {
				return esc_html__( 'Please fill the required fields.', 'woocommerce-photo-reviews' );
			} elseif ( ! is_email( $comment_author_email ) ) {
				return esc_html__( 'Please enter a valid email address.', 'woocommerce-photo-reviews' );
			}
		}
		if ( '' === $comment_content && ! self::$settings->get_params( 'allow_empty_comment' ) ) {
			return esc_html__( ' Please type your comment text.', 'woocommerce-photo-reviews' );
		}
		$comment_type      = 'review';
		$commentdata       = compact(
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_content',
			'comment_type',
			'comment_parent',
			'user_ID'
		);
		$check_max_lengths = wp_check_comment_data_max_lengths( $commentdata );
		if ( is_wp_error( $check_max_lengths ) ) {
			return $check_max_lengths->get_error_message();
		}
		$arg = array(
			'comment_author_email' => $comment_author_email,
			'comment_post_ID'      => $comment_post_ID,
			'user_ID'              => $user_ID,
		);

		return self::restrict_number_of_reviews( $arg );
	}

	public static function restrict_number_of_reviews( $comment = array() ) {
		$error                      = '';
		$restrict_number_of_reviews = self::$settings->get_params( 'restrict_number_of_reviews' );
		switch ( $restrict_number_of_reviews ) {
			case 'one':
				if ( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::reviews_count_of_customer( $comment['comment_author_email'], $comment['comment_post_ID'], $comment['user_ID'] ?? 0 ) >= 1 ) {
					$error = esc_html__( 'You have reached the maximum number of reviews that a user can leave for this product', 'woocommerce-photo-reviews' );
				}
				break;
			case 'one_verified':
				if ( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::reviews_count_of_customer( $comment['comment_author_email'], $comment['comment_post_ID'], $comment['user_ID'] ?? 0 ) >= 1 ) {
					$error = esc_html__( 'You have reached the maximum number of reviews that a user can leave for this product.', 'woocommerce-photo-reviews' );
				} elseif ( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_orders_count_by_product( $comment['comment_post_ID'], $comment['comment_author_email'], $comment['user_ID'] ) < 1 ) {
					$error = esc_html__( 'Only customers who bought this product can leave a review.', 'woocommerce-photo-reviews' );
				}
				break;
			case 'orders_count':
				$orders_count = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_orders_count_by_product( $comment['comment_post_ID'], $comment['comment_author_email'], $comment['user_ID'] );
				ob_start();
				var_dump( $orders_count );
				error_log( ob_get_clean() );
				if ( $orders_count < 1 ) {
					$error = esc_html__( 'Only customers who bought this product can leave a review.', 'woocommerce-photo-reviews' );
				} elseif ( VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::reviews_count_of_customer( $comment['comment_author_email'], $comment['comment_post_ID'], $comment['user_ID'] ?? 0 ) >= $orders_count ) {
					$error = esc_html__( 'You have reached the maximum number of reviews that you can leave for this product', 'woocommerce-photo-reviews' );
				}
				break;
			case '':
			default:
		}

		return $error;
	}

	public static function check_upload_file() {
		$error    = '';
		$tmp_name = villatheme_array_flatten( villatheme_sanitize_kses( $_FILES['wcpr_image_upload']['tmp_name'] ?? array() ), false );
		if ( ( ! isset( $_FILES['wcpr_image_upload'] ) || empty( $tmp_name ) ) && 'on' === self::$settings->get_params( 'photo', 'required' ) ) {
			$error = esc_html__( 'Photo is required', 'woocommerce-photo-reviews' );

			return $error;
		}
		if ( empty( $tmp_name ) && 'on' !== self::$settings->get_params( 'photo', 'required' ) ) {
			return '';
		}
		$maxsize_allowed = self::$settings->get_params( 'photo', 'maxsize' );
		$max_file_up     = self::$settings->get_params( 'photo', 'maxfiles' );
		$names           = villatheme_array_flatten( villatheme_sanitize_kses( $_FILES['wcpr_image_upload']['name'] ?? array() ) );
		$sizes           = array_map( 'intval', villatheme_array_flatten( villatheme_sanitize_kses( $_FILES['wcpr_image_upload']['size'] ?? array() ) ) );
		$types           = villatheme_array_flatten( villatheme_sanitize_kses( $_FILES['wcpr_image_upload']['type'] ?? array() ) );
		$errors          = array_unique( array_map( 'intval', villatheme_array_flatten( villatheme_sanitize_kses( $_FILES['wcpr_image_upload']['error'] ?? array() ), false ) ) );
		/*need more security checks*/
		if ( ! empty( $errors ) && ! in_array( UPLOAD_ERR_NO_FILE, $errors ) ) {
			return sprintf( esc_html__( 'There was an error uploading files: %s', 'woocommerce-photo-reviews' ), implode( ',', $errors ) );
		}
		if ( empty( $names ) && 'on' === self::$settings->get_params( 'photo', 'required' ) ) {
			return esc_html__( 'Photo is required.', 'woocommerce-photo-reviews' );
		}
		if ( count( $names ) > $max_file_up ) {
			return sprintf( esc_html__( 'Maximum number of files allowed is: %s.', 'woocommerce-photo-reviews' ), $max_file_up );
		}
		$upload_allow = self::$settings->get_params( 'upload_allow' );
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $upload_allow ) ) {
				$error = esc_html__( 'Only JPG, JPEG, BMP, PNG , WEBP, GIF, MP4 and WEBM are allowed.', 'woocommerce-photo-reviews' );
				break;
			}
		}
		if ( $error ) {
			return $error;
		}
		$file_type_pattern = '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|bmp|BMP|webp|WEBP|mp4|MP4|mov|MOV|webm|WEBM)/';
		foreach ( $names as $name ) {
			if ( $name && ! preg_match( $file_type_pattern, $name ) ) {
				$error = esc_html__( 'Only JPG, JPEG, BMP, PNG , WEBP, GIF, MP4, MOV and WEBM are allowed.', 'woocommerce-photo-reviews' );
				break;
			}
		}
		if ( $error ) {
			return $error;
		}
		foreach ( $sizes as $size ) {
			if ( ! $size ) {
				$error = esc_html__( 'File\'s too large!', 'woocommerce-photo-reviews' );
				break;
			}
			if ( $size > ( $maxsize_allowed * 1024 ) ) {
				$error = sprintf( esc_html__( 'Max size allowed: %skB.', 'woocommerce-photo-reviews' ), $maxsize_allowed );
				break;
			}
		}

		return $error;
	}

	public static function upload_file( $comment_id, $post_id ) {
		$img_id = array();
		$error  = false;
		viwcpr_set_time_limit();
		add_filter( 'intermediate_image_sizes', array(
			'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
			'reduce_image_sizes'
		) );
		add_filter( 'upload_dir', array( 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend', 'user_upload_folder' ) );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		$files    = isset( $_FILES['wcpr_image_upload'] ) ? $_FILES['wcpr_image_upload'] : array();// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$captions = isset( $_POST['wcpr_image_caption'] ) ? villatheme_sanitize_kses( $_POST['wcpr_image_caption'] ) : array();

		if ( is_array( $files['name'][0] ?? '' ) ) {
			foreach ( $files['name'] as $key => $value ) {
				viwcpr_set_time_limit();
				if ( $files['name'][ $key ][0] ) {
					$meta = array();
					if ( isset( $captions[ $key ] ) && $captions[ $key ] ) {
						$meta['post_excerpt'] = $captions[ $key ];
					}
					$file                   = array(
						'name'     => apply_filters( 'woocommerce_photo_reviews_image_file_name', $files['name'][ $key ][0], $comment_id, $post_id, false ),
						'type'     => $files['type'][ $key ][0],
						'tmp_name' => $files['tmp_name'][ $key ][0],
						'error'    => $files['error'][ $key ][0],
						'size'     => $files['size'][ $key ][0]
					);
					$_FILES ['upload_file'] = $file;
					$attachment_id          = media_handle_upload( 'upload_file', $post_id, $meta );
					if ( is_wp_error( $attachment_id ) ) {
						wc_add_notice( sprintf( esc_html__( 'Error adding file: %s.', 'woocommerce-photo-reviews' ), $attachment_id->get_error_message() ), 'error' );
						do_action( 'woocommerce_set_cart_cookies', true );
						$error = true;
						break;
					} else {
						$img_id[] = $attachment_id;
					}
				}
			}
		} else {
			foreach ( ( $files['name'] ?? array() ) as $key => $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				viwcpr_set_time_limit();
				if ( $files['name'][ $key ] ) {
					$meta = array();
					if ( isset( $captions[ $key ] ) && $captions[ $key ] ) {
						$meta['post_excerpt'] = $captions[ $key ];
					}
					$file                   = array(
						'name'     => apply_filters( 'woocommerce_photo_reviews_image_file_name', $files['name'][ $key ], $comment_id, $post_id, false ),
						'type'     => $files['type'][ $key ],
						'tmp_name' => $files['tmp_name'][ $key ],
						'error'    => $files['error'][ $key ],
						'size'     => $files['size'][ $key ]
					);
					$_FILES ['upload_file'] = $file;
					$attachment_id          = media_handle_upload( 'upload_file', $post_id, $meta );
					if ( is_wp_error( $attachment_id ) ) {
						wc_add_notice( sprintf( esc_html__( 'Error adding file: %s.', 'woocommerce-photo-reviews' ), $attachment_id->get_error_message() ), 'error' );
						do_action( 'woocommerce_set_cart_cookies', true );
						$error = true;
						break;
					} else {
						$img_id[] = $attachment_id;
					}
				}
			}
		}
		remove_filter( 'intermediate_image_sizes', array(
			'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
			'reduce_image_sizes'
		) );
		remove_filter( 'upload_dir', array( 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend', 'user_upload_folder' ) );

		return $error ? '' : $img_id;
	}

	public function add_review_image( $comment_id ) {

		if ( isset( $_POST['wcpr_image_upload_id'] ) ) {
			$img_id = explode( ',', wc_clean( wp_unslash( $_POST['wcpr_image_upload_id'] ) ) );
		} else {
			$post_id          = get_comment( $comment_id )->comment_post_ID;
			self::$product_id = $post_id;
			$img_id           = self::upload_file( $comment_id, $post_id );
			if ( ! is_array( $img_id ) ) {
				wp_safe_redirect( self::$product_id ? get_permalink( self::$product_id ) : home_url() );
				exit;
			}
		}
		if ( is_array( $img_id ) && count( $img_id ) ) {
			update_comment_meta( $comment_id, 'reviews-images', $img_id );
		}
		update_comment_meta( $comment_id, 'gdpr_agree', 1 );
		update_comment_meta( $comment_id, 'wcpr_vote_up_count', 0 );
		update_comment_meta( $comment_id, 'wcpr_vote_down_count', 0 );
	}

	public static function validate_product_review_verified_owners( $comment_post_id ) {
		// Only validate if option is enabled.
		if ( 'yes' !== get_option( 'woocommerce_review_rating_verification_required' ) ) {
			return;
		}

		// Validate only products.
		if ( 'product' !== get_post_type( $comment_post_id ) ) {
			return;
		}
		$error = self::check_review( $comment_post_ID, $comment_parent, $user_ID, $comment_author, $comment_author_email, $comment_author_url, $comment_content );
		if ( $error ) {
			if ( isset( $_POST['wcpr_image_upload_nonce'] ) && wp_verify_nonce( wc_clean( $_POST['wcpr_image_upload_nonce'] ), 'wcpr_image_upload' ) ) {
				$link = ! empty( $_REQUEST['_wp_http_referer'] ) ? esc_url( villatheme_sanitize_fields( $_REQUEST['_wp_http_referer'] ) ) : ( get_permalink( $comment_post_id ) ?: home_url() );
				wc_add_notice( $error, 'error' );
				do_action( 'woocommerce_set_cart_cookies', true );
				wp_safe_redirect( $link );
				exit;
			} else {
				wp_die( $error, 'wcpr_restrict_number_of_reviews', array( 'code' => 403 ) );
			}
		}
	}

	public function check_review_image( $comment ) {
		if ( ! isset( $_POST['wcpr_image_upload_nonce'] ) ) {
			return $comment;
		}
		if ( ! wp_verify_nonce( wc_clean( $_POST['wcpr_image_upload_nonce'] ), 'wcpr_image_upload' ) ) {
			wp_die( 'wcpr_image_upload_nonce is invalid', 'check_review_image', array( 'code' => 403 ) );
		}
		if ( ! isset( $_POST['wcpr_image_upload_id'] ) ) {
			$comment_type = isset( $comment['comment_type'] ) ? $comment['comment_type'] : '';
			if ( ! is_admin() && isset( $_POST['comment_post_ID'], $comment['comment_type'] ) && 'product' === get_post_type( absint( villatheme_sanitize_fields( $_POST['comment_post_ID'] ) ) ) && ( '' === $comment_type || 'comment' === $comment_type ) ) {
				$comment_type = $comment['comment_type'] = 'review';
			}
			if ( $comment_type !== 'review' ) {
				return $comment;
			}
			$link = ! empty( $_REQUEST['_wp_http_referer'] ) ? esc_url( villatheme_sanitize_fields( $_REQUEST['_wp_http_referer'] ) ) : ( ! empty( $comment['comment_post_ID'] ) ? get_permalink( $comment['comment_post_ID'] ) : home_url() );
			if ( ( 'on' == self::$settings->get_params( 'photo', 'gdpr' ) ) && empty( $_POST['wcpr_gdpr_checkbox'] ) ) {
				wc_add_notice( esc_html__( 'Please agree with the GDPR policy!', 'woocommerce-photo-reviews' ), 'error' );
				do_action( 'woocommerce_set_cart_cookies', true );
				wp_safe_redirect( $link );
				exit;
			}
			$error = self::restrict_number_of_reviews( $comment );
			if ( $error ) {
				wc_add_notice( $error, 'error' );
				do_action( 'woocommerce_set_cart_cookies', true );
				wp_safe_redirect( $link );
				exit;
			}
			$error = self::check_upload_file();
			if ( $error ) {
				wc_add_notice( $error, 'error' );
				do_action( 'woocommerce_set_cart_cookies', true );
				wp_safe_redirect( $link );
				exit;
			}
		}
		add_action( 'comment_post', array( $this, 'add_review_image' ) );
		if ( 'yes' === get_option( 'woocommerce_enable_coupons' ) && self::$settings->get_params( 'coupons', 'enable' ) ) {
			add_action( 'comment_post', array( $this, 'send_coupon_after_reviews' ), 11, 3 );
		}

		return $comment;
	}

	public function save_custom_fields( $comment_id ) {
		if ( ! self::$settings->get_params( 'custom_fields_enable' ) ) {
			return;
		}
		$custom_fields = isset( $_POST['wcpr_custom_fields'] ) ? villatheme_sanitize_kses( stripslashes_deep( $_POST['wcpr_custom_fields'] ) ) : array();
		if ( isset( $custom_fields['name'] ) && is_array( $custom_fields['name'] ) && count( $custom_fields['name'] ) ) {
			$custom_fields_data = array();
			foreach ( $custom_fields['name'] as $custom_field_name_k => $custom_field_name_v ) {
				if ( ! $custom_fields['value'][ $custom_field_name_k ] ) {
					continue;
				}
				$custom_fields_data[] = array(
					'name'  => $custom_field_name_v,
					'value' => $custom_fields['value'][ $custom_field_name_k ],
					'unit'  => $custom_fields['unit'][ $custom_field_name_k ],
				);
			}
			update_comment_meta( $comment_id, 'wcpr_custom_fields', $custom_fields_data );
		}
	}

	public function save_review_title( $comment_id ) {
		if ( ! self::$settings->get_params( 'review_title_enable' ) ) {
			return;
		}
		$title = isset( $_POST['wcpr_review_title'] ) ? wp_kses_post( $_POST['wcpr_review_title'] ) : '';
		if ( $title ) {
			update_comment_meta( $comment_id, 'wcpr_review_title', $title );
			self::wpml_register_single_string(
				'viwcpr-reviews-title',
				'viwcpr-title-product-review-' . $comment_id,
				$title
			);
		}
	}

	public static function wpml_register_single_string( $domain, $name, $content ) {
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) &&
		     is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) &&
		     apply_filters( 'wcml_enable_product_review_translation', true ) &&
		     $domain && $name && $content ) {
			do_action(
				'wpml_register_single_string',
				$domain,
				$name,
				$content,
				false,
				self::get_language() ?: apply_filters( 'wpml_current_language', null )
			);
		}
	}

	public static function wpml_translate_single_string( $domain, $name, $content ) {
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) &&
		     is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) &&
		     apply_filters( 'wcml_enable_product_review_translation', true ) &&
		     $domain && $name ) {
			return apply_filters(
				'wpml_translate_single_string',
				$content,
				$domain,
				$name,
				self::$language
			);
		}

		return $content;
	}

	public function fix_get_comment_link() {
		add_filter( 'get_comment_link', array( $this, 'get_comment_link_1' ), 10, 4 );
	}

	public function allow_empty_comment( $allow ) {
		return boolval( self::$settings->get_params( 'allow_empty_comment' ) );
	}

	public function login() {
		if ( empty( $_GET['wcpr_token'] ) ) {
			return;
		}
		$token = sanitize_text_field( $_GET['wcpr_token'] );
		if ( ! is_user_logged_in() && self::$settings->get_params( 'followup_email', 'auto_login' ) ) {
			$user_id = $token ? get_transient( $token ) : 0;
			if ( $user_id && ( $user = get_user_by( 'id', $user_id ) ) ) {
				$auto_login_exclude = self::$settings->get_params( 'followup_email', 'auto_login_exclude' );
				$wcpr_token         = get_user_meta( $user_id, 'wcpr_token', true );
				if ( $wcpr_token === $token && empty( array_intersect( $auto_login_exclude, $user->roles ) ) ) {
					delete_transient( $token );
					wp_set_auth_cookie( $user_id );
					delete_user_meta( $user_id, 'wcpr_token' );
					wp_safe_redirect( remove_query_arg( 'wcpr_token', wp_kses_post( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
					exit();
				} else {
					delete_transient( $wcpr_token );
				}
			}
		}
		wp_safe_redirect( remove_query_arg( 'wcpr_token', wp_kses_post( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		exit();
	}

	public static function add_inline_style( $element, $style, $value, $suffix = array() ) {
		if ( ! $element || ! is_array( $element ) ) {
			return '';
		}
		$element = implode( ',', $element );
		$return  = $element . '{';
		if ( is_array( $style ) && count( $style ) ) {
			foreach ( $style as $k => $v ) {
				$get_value  = $value[ $k ] ?? '';
				$get_suffix = $suffix[ $k ] ?? '';
				$return     .= $v . ':' . $get_value . $get_suffix . ';';
			}
		}
		$return .= '}';

		return $return;
	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public static function get_comments( $args ) {
		$original_args = $args;
		$args          = (array) apply_filters( 'woocommerce_photo_reviews_get_comments_arguments', $args, $original_args );
		$cache         = self::$cache['comments'] ?? "";
		if ( $cache ) {
			$cache = villatheme_json_decode( $cache );
		}
		if ( ! is_array( $cache ) ) {
			$cache = [];
		}
		$result = null;
		foreach ( $cache as $item ) {
			if ( ! isset( $item['args'], $item['value'] ) || ! is_array( $item['args'] ) ) {
				continue;
			}
			if ( ! villatheme_is_equal( $item['args'], $args ) ) {
				continue;
			}
			$result = $item['value'];
			break;
		}
		if ( $result === null ) {
			$result  = apply_filters( 'woocommerce_photo_reviews_get_comments', get_comments( $args ), $args );
			$cache[] = [
				'args'  => $args,
				'value' => $result,
			];
		} elseif ( is_array( $result ) && ! empty( $result[0]['comment_ID'] ) ) {
			$tmp = [];
			foreach ( $result as $item ) {
				$tmp[] = get_comment( (object) $item );
			}
			$result = $tmp;
		}

		return $result;
	}

	/**
	 * @param $star
	 * @param $post_id
	 *
	 * @return mixed|void
	 */
	public static function stars_count( $star, $post_id ) {
		$args = array(
			'post__in'   => is_array( $post_id ) ? $post_id : array( $post_id ),
			'post_type'  => 'product',
			'count'      => true,
			'status'     => 'approve',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'rating',
					'value'   => $star,
					'compare' => '='
				)
			)
		);
		if ( ! empty( $args['post__in'] ) && is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) &&
		     isset( $_GET['clang'] ) && sanitize_text_field( $_GET['clang'] ) === 'all' ) {
			global $wpml_post_translations;
			$ids = $args['post__in'];
			foreach ( $args['post__in'] as $id ) {
				$ids = array_merge( array_values( $wpml_post_translations->get_element_translations( $id ) ), $ids );
			}
			$args['post__in'] = array_unique( $ids );
		}
		$args   = apply_filters( 'woocommerce_photo_reviews_get_rating_count_arguments', $args, $post_id, $star );
		$return = self::get_comments( $args );

		return $return;
	}

	public static function get_language() {
		if ( self::$language !== null ) {
			return self::$language;
		}
		if ( ! self::$settings->get_params( 'multi_language' ) ) {
			return self::$language = '';
		}
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$default_lang     = apply_filters( 'wpml_default_language', null );
			$current_language = apply_filters( 'wpml_current_language', null );
			if ( $current_language && $current_language !== $default_lang ) {
				self::$language = $current_language;
			}
		} else if ( class_exists( 'Polylang' ) ) {
			$default_lang     = pll_default_language( 'slug' );
			$current_language = pll_current_language( 'slug' );
			if ( $current_language && $current_language !== $default_lang ) {
				self::$language = $current_language;
			}
		}
		self::$language = apply_filters( 'viwcpr_get_current_language', self::$language ?? '' );

		return self::$language;
	}

	/**
	 * Fix redirect_url after a review is submitted
	 *
	 * @param $link
	 * @param $comment
	 * @param $args
	 * @param $cpage
	 *
	 * @return false|string
	 */
	public function get_comment_link_1( $link, $comment, $args, $cpage ) {
		global $wp_rewrite;
		if ( self::$settings->get_params( 'pagination_ajax' ) ) {
			$product = wc_get_product( $comment->comment_post_ID );
			if ( $product ) {
				$link = $product->get_permalink() . self::$anchor_link;
			}
		} else {
			$sort = self::$settings->get_params( 'photo', 'sort' )['time'];
			if ( ( $sort == 1 ) ) {
				$link  = get_permalink( $comment->comment_post_ID );
				$cpage = 1;
				if ( get_option( 'page_comments' ) ) {
					if ( $wp_rewrite->using_permalinks() ) {
						if ( $cpage ) {
							$link = trailingslashit( $link ) . $wp_rewrite->comments_pagination_base . '-' . $cpage;
						}
						$link = user_trailingslashit( $link, 'comment' );
					} elseif ( $cpage ) {
						$link = add_query_arg( 'cpage', $cpage, $link );
					}
				}
				if ( $wp_rewrite->using_permalinks() ) {
					$link = user_trailingslashit( $link, 'comment' );
				}
				$link = $link . '#comment-' . $comment->comment_ID;
			}
		}

		return $link;
	}

	public function get_comment_link( $link, $comment, $args, $cpage ) {
		if ( self::$settings->get_params( 'pagination_ajax' ) && $this->frontend_style == 2 ) {
			$product = wc_get_product( $comment->comment_post_ID );
			if ( $product ) {
				$link = $product->get_permalink() . self::$anchor_link;
			}
		}

		return $link;
	}

	public function comment_form_must_log_in_after() {
		if ( $this->enctype_start ) {
			$this->enctype_start = false;
			echo ob_get_clean();
		}
	}

	public function add_form_enctype_end() {
		global $wcpr_review_form;
		if ( ! is_product() || $wcpr_review_form ) {
			return;
		}
		if ( $this->enctype_start ) {
			$this->enctype_start = false;
			$v                   = ob_get_clean();
			$v                   = str_replace( '<form', '<form enctype="multipart/form-data"', $v );
			print( $v );
		}
	}

	public function add_form_enctype_start() {
		global $wcpr_review_form;
		if ( ! is_product() || $wcpr_review_form ) {
			return;
		}
		$this->enctype_start = true;
		ob_start();
	}

	public function add_form_description() {
		if ( ! is_product() ) {
			return;
		}
		if ( self::$settings->get_params( 'coupons', 'enable' ) ) {
			echo apply_filters( 'viwcpr_get_form_description_html',
				sprintf( '<div class="wcpr-form-description">%s</div>', wp_kses_post( self::$settings->get_params( 'coupons', 'form_title', self::get_language() ) ) ) );
		}
	}

	public function comment_post_redirect( $location, $comment ) {
		if ( self::$settings->get_params( 'pagination_ajax' ) ) {
			$product = wc_get_product( $comment->comment_post_ID );
			if ( $product ) {
				$location = $product->get_permalink() . self::$anchor_link;
			}
		}
		if ( 'sent' === get_comment_meta( $comment->comment_ID, 'coupon_email', true ) ) {
			$location = add_query_arg( array( 'wcpr_thank_you_message' => 1 ), $location );
			update_comment_meta( $comment->comment_ID, 'coupon_email', 'notified' );
		} else {
			$location = add_query_arg( array( 'wcpr_thank_you_message' => 2 ), $location );
		}

		return $location;
	}

	public function thank_you_message_after_review() {
		if ( is_product() ) {
			$message_type = isset( $_GET['wcpr_thank_you_message'] ) ? sanitize_text_field( $_GET['wcpr_thank_you_message'] ) : '';
			if ( $message_type == 1 ) {
				echo apply_filters( 'viwcpr_thank_you_message_after_review', sprintf( '<div id="wcpr_thank_you_message" class="woocommerce-message"><p>%s</p></div>',
					wp_kses_post( self::$settings->get_params( 'thank_you_message_coupon', '', self::get_language() ) ) ) );
			} elseif ( $message_type == 2 ) {
				echo apply_filters( 'viwcpr_thank_you_message_after_review', sprintf( '<div id="wcpr_thank_you_message" class="woocommerce-message"><p>%s</p></div>',
					wp_kses_post( self::$settings->get_params( 'thank_you_message', '', self::get_language() ) ) ) );
			} elseif ( $message_type == 3 ) {
				echo apply_filters( 'viwcpr_thank_you_message_after_review',
					sprintf( '<p class="meta"><em class="woocommerce-review__awaiting-approval">%s</em></p>',
						esc_html__( 'Your review is awaiting approval', 'woocommerce-photo-reviews' ) ) );
			}
		}
	}

	public function custom_fields_from_product_variations( $custom_fields, $product ) {
		if ( self::$settings->get_params( 'custom_fields_from_variations' ) ) {
			if ( $product && $product->is_type( 'variable' ) ) {
				$attributes = $product->get_variation_attributes();
				if ( is_array( $attributes ) && count( $attributes ) ) {
					foreach ( $attributes as $attribute_name => $attribute_value ) {
						if ( is_array( $attribute_value ) && count( $attribute_value ) ) {
							$custom_fields[] = array(
								'name'     => wc_attribute_label( $attribute_name ),
								'language' => self::get_language(),
								'label'    => '',
								'value'    => array_map( 'wc_attribute_label', urldecode_deep( $attribute_value ) ),
								'unit'     => array(),
							);
						}
					}
				}
			}
		}

		return $custom_fields;
	}

	public function add_comment_field( $comment_form ) {
		$comment_field = wc_get_template_html(
			'viwcpr-comment-field-html.php',
			array(
				'comment_form' => $comment_form,
				'settings'     => self::$settings,
			),
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES
		);
		$comment_field = $comment_field ?: ( $comment_form['comment_field'] ?? '' );
		if ( self::$settings->get_params( 'allow_empty_comment' ) ) {
			$comment_field = str_replace(
				array(
					'&nbsp;<span class="required">*</span></label><textarea id="comment"',
					'&nbsp;<span class="required">*</span></label><textarea id="wcpr-comment"',
				),
				array(
					'</label><textarea id="comment"',
					'</label><textarea id="wcpr-comment"',
				),
				$comment_field
			);
		}
		$comment_form['comment_field'] = $comment_field;

		return $comment_form;
	}

	public function helpful_button_handle() {
		global $wpdb;
		$vote       = isset( $_POST['vote'] ) ? sanitize_text_field( $_POST['vote'] ) : '';
		$comment_id = isset( $_POST['comment_id'] ) ? sanitize_text_field( $_POST['comment_id'] ) : '';
		$response   = array(
			'status' => 'error',
			'up'     => '',
			'down'   => '',
		);
		if ( $vote && $comment_id ) {
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
			$up_votes         = get_comment_meta( $comment_id, 'wcpr_vote_up', false );
			$down_votes       = get_comment_meta( $comment_id, 'wcpr_vote_down', false );
			$updated_cmt_meta = get_option( 'wcpr_comment_meta_updated' );
			if ( ! $updated_cmt_meta ) {
				if ( $vote == 'up' ) {
					if ( ! in_array( $vote_info, $up_votes ) ) {
						$response['status'] = 'success';
						if ( in_array( $vote_info, $down_votes ) ) {
							$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}commentmeta set meta_key='wcpr_vote_up' where comment_id='%s' and meta_key='wcpr_vote_down' and meta_value='%s'", $comment_id, $vote_info );
							$wpdb->query( $query );
							if ( count( $down_votes ) > 0 ) {
								$response['down'] = count( $down_votes ) - 1 + absint( get_comment_meta( $comment_id, 'wcpr_vote_down_count', true ) );
							}
						} else {
							$response['down'] = count( $down_votes ) + absint( get_comment_meta( $comment_id, 'wcpr_vote_down_count', true ) );
							add_comment_meta( $comment_id, 'wcpr_vote_' . $vote, $vote_info );
						}
						$response['up'] = count( $up_votes ) + 1 + absint( get_comment_meta( $comment_id, 'wcpr_vote_up_count', true ) );
					}
				} else {
					if ( ! in_array( $vote_info, $down_votes ) ) {
						$response['status'] = 'success';
						if ( in_array( $vote_info, $up_votes ) ) {
							$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}commentmeta set meta_key='wcpr_vote_down' where comment_id='%s' and meta_key='wcpr_vote_up' and meta_value='%s'", $comment_id, $vote_info );
							$wpdb->query( $query );
							if ( count( $up_votes ) > 0 ) {
								$response['up'] = count( $up_votes ) - 1 + absint( get_comment_meta( $comment_id, 'wcpr_vote_up_count', true ) );
							}
						} else {
							add_comment_meta( $comment_id, 'wcpr_vote_' . $vote, $vote_info );
							$response['up'] = count( $up_votes ) + absint( get_comment_meta( $comment_id, 'wcpr_vote_up_count', true ) );
						}
						$response['down'] = count( $down_votes ) + 1 + absint( get_comment_meta( $comment_id, 'wcpr_vote_down_count', true ) );
					}
				}
			} else {
				$vote_down_count = absint( get_comment_meta( $comment_id, 'wcpr_vote_down_count', true ) );
				$vote_up_count   = absint( get_comment_meta( $comment_id, 'wcpr_vote_up_count', true ) );
				if ( $vote == 'up' ) {
					if ( ! in_array( $vote_info, $up_votes ) ) {
						$response['status'] = 'success';
						if ( in_array( $vote_info, $down_votes ) ) {
							$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}commentmeta set meta_key='wcpr_vote_up' where comment_id='%s' and meta_key='wcpr_vote_down' and meta_value='%s'", $comment_id, $vote_info );
							$wpdb->query( $query );
							if ( $vote_down_count > 0 ) {
								update_comment_meta( $comment_id, 'wcpr_vote_down_count', $vote_down_count - 1 );
								$response['down'] = $vote_down_count - 1;
							}
						} else {
							$response['down'] = $vote_down_count;
							add_comment_meta( $comment_id, 'wcpr_vote_' . $vote, $vote_info );
						}
						update_comment_meta( $comment_id, 'wcpr_vote_up_count', $vote_up_count + 1 );
						$response['up'] = $vote_up_count + 1;
					}
				} else {
					if ( ! in_array( $vote_info, $down_votes ) ) {
						$response['status'] = 'success';
						if ( in_array( $vote_info, $up_votes ) ) {
							$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}commentmeta set meta_key='wcpr_vote_down' where comment_id='%s' and meta_key='wcpr_vote_up' and meta_value='%s'", $comment_id, $vote_info );
							$wpdb->query( $query );
							if ( $vote_up_count > 0 ) {
								update_comment_meta( $comment_id, 'wcpr_vote_up_count', $vote_up_count - 1 );
								$response['up'] = $vote_up_count - 1;
							}
						} else {
							add_comment_meta( $comment_id, 'wcpr_vote_' . $vote, $vote_info );
							$response['up'] = $vote_up_count;
						}
						update_comment_meta( $comment_id, 'wcpr_vote_down_count', $vote_down_count + 1 );
						$response['down'] = $vote_down_count + 1;
					}
				}
			}
		}
		wp_send_json( $response );
	}

	public function comment_author( $author, $comment_id ) {
		$hide_name = self::$settings->get_params( 'photo', 'hide_name' );
		if ( $hide_name === 'off' ) {
			return $author;
		}
		global $wcpr_shortcode_count;
		if ( is_admin() && ! $wcpr_shortcode_count && ! wp_doing_ajax() ) {
			return $author;
		}
		if ( wp_doing_ajax() && ! isset( $_REQUEST['action'] ) && ( $_REQUEST['action'] == 'wcpr_ajax_load_more_reviews' ) ) {
			return $author;
		}
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return $author;
		}
		$product = wc_get_product( $comment->comment_post_ID ?? 0 );
		if ( ! $product ) {
			return $author;
		}
		switch ( $hide_name ) {
			case '4':
				if ( $author ) {
					$number_character_show = self::$settings->get_params( 'photo', 'number_character_show' );
					$word                  = $author;
					$word_length           = function_exists( 'mb_strlen' ) ? mb_strlen( $author ) : strlen( $author );
					if ( $word_length > absint( $number_character_show ) ) {
						$word = function_exists( 'mb_substr' ) ? mb_substr( $author, 0, absint( $number_character_show ) ) : substr( $author, 0, absint( $number_character_show ) );
						$word .= str_repeat( '*', $word_length - $number_character_show );
					}

					$author = $word;
				}
				break;
			case '3':
				if ( $author ) {
					$str_arr = explode( ' ', $author );
					if ( count( $str_arr ) < 2 ) {
						break;
					}
					$first       = ucwords( array_shift( $str_arr ) );
					$word_length = function_exists( 'mb_strlen' ) ? mb_strlen( $first ) : strlen( $first );
					if ( $word_length == 1 ) {
						$author = $first;
					} else {
						$author = function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 1 ) : substr( $first, 0, 1 ) . '.';
					}
					$author .= ucwords( array_pop( $str_arr ) );
				}
				break;
			case '2':
				/* $comment    = get_comment( $comment_id );
				 if (! empty( $comment->user_id )){
					 $user = get_userdata( $comment->user_id );
					 $author = $user->first_name.' '.$user->last_name;
				 }elseif (!empty( $comment->comment_author ) ){
					 $author = $comment->comment_author;
				 }else{
					 $author = esc_html__( 'Anonymous','woocommerce-photo-reviews' );
				 }*/
				if ( $author ) {
					$str_arr = explode( ' ', $author );
					if ( count( $str_arr ) < 2 ) {
						break;
					}
					$return_arr = array( ucwords( array_shift( $str_arr ) ) );
					foreach ( $str_arr as $key => $value ) {
						$word_length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
						if ( $word_length == 1 ) {
							$return_arr[] = $value;
							continue;
						}
						$word         = ucwords( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1 ) : substr( $value, 0, 1 ) );
						$return_arr[] = $word;
					}
					if ( count( $return_arr ) ) {
						$author = implode( ' ', $return_arr );
					}
				}
				break;
			default:
				if ( $author ) {
					$str_arr    = explode( ' ', $author );
					$return_arr = array();
					foreach ( $str_arr as $key => $value ) {
						$word_length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
						if ( $word_length == 1 ) {
							$return_arr[] = $value;
							continue;
						}
						$word = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1 ) : substr( $value, 0, 1 );
						for ( $i = 0; $i < $word_length - 1; $i ++ ) {
							$word .= '*';
						}
						$return_arr[] = $word;
					}
					if ( count( $return_arr ) ) {
						$author = implode( ' ', $return_arr );
					}
				}
		}

		return $author;
	}

	public function ajax_load_more_reviews() {
		self::$is_ajax  = true;
		$frontend_style = isset( $_POST['frontend_style'] ) ? wc_clean( $_POST['frontend_style'] ) : '';
		self::$rating   = isset( $_POST['rating'] ) ? wc_clean( $_POST['rating'] ) : '';
		self::$verified = isset( $_POST['verified'] ) ? wc_clean( $_POST['verified'] ) : '';
		self::$image    = isset( $_POST['image'] ) ? wc_clean( $_POST['image'] ) : '';
		$product_id     = isset( $_POST['post_id'] ) ? wc_clean( $_POST['post_id'] ) : 0;
		$response       = array(
			'html'         => '',
			'max_page'     => 1,
			'cpage'        => 1,
			'update_count' => array()
		);
		if ( ! $product_id ) {
			wp_send_json( $response );
		}
		global $product, $post;
		$post              = get_post( $product_id );
		$product           = wc_get_product( $product_id );
		$comments_per_page = get_option( 'comments_per_page' );
		$cpage             = isset( $_POST['cpage'] ) ? sanitize_text_field( $_POST['cpage'] ) : 1;
		$filter_type       = isset( $_POST['filter_type'] ) ? sanitize_text_field( $_POST['filter_type'] ) : '';
		$post_in           = array( $product_id );
		if ( count( villatheme_json_decode( self::$settings->get_params( 'share_reviews' ) ) ) ) {
			$share_review_ids = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Share_Reviews::get_products( $product_id );
			$post_in          = array_merge( $share_review_ids, $post_in );
		}
		$comment_args = array(
			'viwcpr_single_loadmore' => 1,
			'status'                 => 'approve',
			'post_type'              => 'product',
			'post_status'            => 'any',
			'number'                 => $comments_per_page,//comments per page
			'paged'                  => $cpage,// current page
			'parent'                 => 0,
			'post__in'               => $post_in,
		);
		if ( self::$image || self::$verified || self::$rating ) {
			$meta_query = array( 'relation' => 'and' );
			if ( self::$verified ) {
				$meta_query[] = array(
					'key'   => 'verified',
					'value' => 1
				);
			}
			if ( self::$image ) {
				$meta_query[] = array(
					'key'     => 'reviews-images',
					'compare' => 'EXISTS'
				);
			}
			if ( self::$rating ) {
				$meta_query[] = array(
					'key'     => 'rating',
					'value'   => self::$rating,
					'compare' => '='
				);
			}
			$comment_args['meta_query'] = $meta_query;
		}
		$comment_args = $this->sort_reviews( $comment_args );
		if ( $filter_type ) {
			$ratings = $product->get_rating_counts();
			$counts  = 0;
			foreach ( $ratings as $k => $v ) {
				$counts                         += $v;
				$response['update_count'][ $k ] = $v;
			}
			$response['update_count']['all'] = $counts;
			if ( $filter_type !== 'verified' ) {
				$agrs2                                = array(
					'post__in'   => $post_in,
					'post_type'  => 'product',
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
					),
				);
				$response['update_count']['verified'] = self::get_comments( $agrs2 );
			}
			if ( $filter_type !== 'image' ) {
				$agrs1                             = array(
					'post__in' => $post_in,
					'count'    => true,
					'meta_key' => 'reviews-images',
					'status'   => 'approve'
				);
				$response['update_count']['image'] = self::get_comments( $agrs1 );
			}
			$comment_count_args   = array_merge( $comment_args, array(
				'number' => 0,
				'count'  => true,
			) );
			$count_reviews        = self::get_comments( apply_filters( 'woocommerce_photo_reviews_shortcode_comment_count_args', $comment_count_args ) );
			$response['max_page'] = ceil( $count_reviews / $comments_per_page );
			if ( get_option( 'default_comments_page' ) !== 'oldest' ) {
				$cpage = $response['max_page'];
			}
		}
		$cpage                  = absint( $cpage ?: 1 );
		$comment_args['offset'] = ( $cpage - 1 ) * $comments_per_page;
		$my_comments            = self::get_comments( apply_filters( 'woocommerce_photo_reviews_shortcode_comment_args', $comment_args ) );
		if ( $frontend_style === '1' ) {
			$masonry_popup     = self::$settings->get_params( 'photo', 'masonry_popup' );
			$enable_box_shadow = self::$settings->get_params( 'photo', 'enable_box_shadow' );
			do_action( 'viwcpr_get_template_masonry_html', array(
				'settings'          => self::$settings,
				'my_comments'       => $my_comments,
				'masonry_popup'     => $masonry_popup,
				'enable_box_shadow' => $enable_box_shadow,
				'show_product'      => 'off',
				'is_shortcode'      => false,
			) );
		} else {
			wp_list_comments( apply_filters( 'woocommerce_product_review_list_args', array(
				'callback' => 'woocommerce_comments',
				'echo'     => true,
			) ), $my_comments );
		}
		$response['html'] = ob_get_clean();
		wp_send_json( $response );
		if ( $frontend_style === '1' ) {
			$comments_html = wp_list_comments( array(
				'page'              => $cpage, // current comment page
				'per_page'          => $comments_per_page,
				'style'             => 'div', // comments won't wrapped in this tag and it is awesome!
				'short_ping'        => true,
				'reverse_top_level' => self::$settings->get_params( 'photo', 'sort' )['time'] == 1 ? true : false,
				'reply_text'        => esc_html__( 'Reply', 'woocommerce-photo-reviews' ),
				'echo'              => false,
			) );
			preg_match_all( '/<article id=\"(.+?)\"/', $comments_html, $matches );
			$comment_ids = array();
			if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
				foreach ( $matches[1] as $match ) {
					$comment_ids[] = substr( $match, 12 );
				}
			} else {
				preg_match_all( '/id=\"comment\-(.*?)\"/', $comments_html, $matches );
				if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
					foreach ( $matches[1] as $match ) {
						$comment_ids[] = $match;
					}
				}
			}
			/* ob_start();
            var_dump($comment_ids);
            error_log(ob_get_clean());*/
			if ( count( $comment_ids ) ) {
				$masonry_popup     = self::$settings->get_params( 'photo', 'masonry_popup' );
				$enable_box_shadow = self::$settings->get_params( 'photo', 'enable_box_shadow' );
				ob_start();
				foreach ( $comment_ids as $comment_id ) {
					$v = get_comment( $comment_id );
					do_action( 'viwcpr_get_template_masonry_html', array(
						'settings'          => self::$settings,
						'my_comments'       => [ $v ],
						'masonry_popup'     => $masonry_popup,
						'enable_box_shadow' => $enable_box_shadow,
						'show_product'      => 'off',
						'is_shortcode'      => false,
					) );
				}
				$response['html'] = ob_get_clean();
			}
		} else {
			$response['html'] = wp_list_comments( apply_filters( 'woocommerce_product_review_list_args', array(
				'page'              => $cpage, // current comment page
				'per_page'          => $comments_per_page,
				'style'             => 'ul', // comments won't wrapped in this tag and it is awesome!
				'short_ping'        => true,
				'reverse_top_level' => self::$settings->get_params( 'photo', 'sort' )['time'] == 1 ? true : false,
				'reply_text'        => esc_html__( 'Reply', 'woocommerce-photo-reviews' ),
				'callback'          => 'woocommerce_comments',
				'echo'              => false,
			) ) );
		}
		wp_send_json( $response );
	}

	public function viwcpr_get_template_basic_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'basic/viwcpr-template-basic-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_template_masonry_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'masonry/viwcpr-template-masonry-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_template_grid_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'grid/viwcpr-template-grid-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_template_grid_layout_2_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'grid/viwcpr-template-grid-layout-2-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_template_list_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'list/viwcpr-template-list-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_template_list_layout_2_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'list/viwcpr-template-list-layout-2-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_pagination_loadmore_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'viwcpr-pagination-loadmore-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_filters_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		wc_get_template( 'viwcpr-filters-html.php', $arg,
			'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
	}

	public function viwcpr_get_overall_rating_html( $arg ) {
		if ( empty( $arg ) ) {
			return;
		}
		switch ( $this->frontend_style ) {
			case '3':
			case '4':
				wc_get_template( 'grid/viwcpr-grid-layout-overall-rating-html.php', $arg,
					'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
					WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
				break;
			case '5':
			case '6':
				wc_get_template( 'list/viwcpr-list-layout-overall-rating-html.php', $arg,
					'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
					WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
				break;
			default:
				wc_get_template( 'viwcpr-overall-rating-html.php', $arg,
					'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
					WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES );
				break;
		}

	}

	/**Do not apply review filter to apply so that reply will not be lost when customer select a filter
	 *
	 * @param $query_vars
	 */
	public static function remove_meta_query_for_reply( &$query_vars ) {
		if ( isset( $query_vars['meta_query'] ) && is_array( $query_vars['meta_query'] ) && count( $query_vars['meta_query'] ) ) {
			foreach ( $query_vars['meta_query'] as $key => $value ) {
				if ( is_array( $value ) && isset( $value['key'] ) && ( $value['key'] === 'reviews-images' || $value['key'] === 'verified' || $value['key'] === 'rating' ) ) {
					unset( $query_vars['meta_query'][ $key ] );
				}
			}
		}
	}

	/**Filter out reviews with a specific rating
	 *
	 * @param $vars
	 */
	public static function filter_review_rating( $vars ) {
		if ( ! empty( $vars->query_vars['parent__in'] ) || ! empty( $vars->query_vars['parent'] ) ) {
			self::remove_meta_query_for_reply( $vars->query_vars );

			return;
		}
		if ( ! self::$is_ajax && ! is_product() ) {
			return;
		}
		global $wcpr_shortcode_count;
		if ( $wcpr_shortcode_count === true ) {
			return;
		}
		$rating = 0;
		if ( self::$is_ajax ) {
			$rating = self::$rating;
		} else {
			if ( empty( $_GET['wcpr_is_ajax'] ) && self::$settings->get_params( 'pagination_ajax' ) && empty( $_GET['wcpr_thank_you_message'] ) ) {
				$filter_default_rating = self::$settings->get_params( 'filter_default_rating' );
				if ( $filter_default_rating ) {
					$rating = $filter_default_rating;
				} else {
					$rating = 0;
				}
			} else {
				if ( isset( $_GET['rating'] ) ) {
					switch ( sanitize_text_field( $_GET['rating'] ) ) {
						case 1:
						case 2:
						case 3:
						case 4:
						case 5:
							$rating = sanitize_text_field( $_GET['rating'] );
							break;
						default:
							$rating = 0;
					}
				}
			}
		}
		if ( $rating ) {
			if ( $vars->query_vars['meta_query'] ) {
				$vars->query_vars['meta_query']['relation'] = 'AND';
				$vars->query_vars['meta_query'][]           = array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '='
				);
			} else {
				$custom                         = array(
					'relation' => 'AND'
				);
				$custom[]                       = array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '='
				);
				$vars->query_vars['meta_query'] = $custom;
			}
		}
	}

	/**Filter our reviews with images/verified
	 *
	 * @param $vars
	 */
	public static function filter_images_and_verified( $vars ) {
		if ( ! empty( $vars->query_vars['parent__in'] ) || ! empty( $vars->query_vars['parent'] ) ) {
			self::remove_meta_query_for_reply( $vars->query_vars );

			return;
		}
		if ( ! self::$is_ajax && ! is_product() ) {
			return;
		}
		global $wcpr_shortcode_count;
		if ( $wcpr_shortcode_count === true ) {
			return;
		}
		if ( self::$is_ajax ) {
			$image    = self::$image;
			$verified = self::$verified;
		} else {
			if ( empty( $_GET['wcpr_is_ajax'] ) && self::$settings->get_params( 'pagination_ajax' ) && empty( $_GET['wcpr_thank_you_message'] ) ) {
				$image    = self::$settings->get_params( 'filter_default_image' );
				$verified = self::$settings->get_params( 'filter_default_verified' );
			} else {
				$image    = isset( $_GET['image'] ) ? sanitize_text_field( $_GET['image'] ) : "";
				$verified = isset( $_GET['verified'] ) ? sanitize_text_field( $_GET['verified'] ) : "";
			}
		}
		if ( $vars->query_vars['meta_query'] ) {
			$vars->query_vars['meta_query']['relation'] = 'AND';
			if ( $image ) {
				$vars->query_vars['meta_query'][] = array(
					'key'     => 'reviews-images',
					'compare' => 'EXISTS'
				);
			}
			if ( $verified ) {
				$vars->query_vars['meta_query'][] = array(
					'key'     => 'verified',
					'value'   => 1,
					'compare' => '='
				);
			}
		} else {
			$custom = array(
				'relation' => 'AND'
			);
			if ( $image ) {
				$custom[] = array(
					'key'     => 'reviews-images',
					'compare' => 'EXISTS'
				);
			}
			if ( $verified ) {
				$custom[] = array(
					'key'     => 'verified',
					'value'   => 1,
					'compare' => '='
				);
			}
			$vars->query_vars['meta_query'] = $custom;
		}
	}

	public function sort_reviews( $comment_args ) {
		//		if ( self::$is_ajax ) {
		//			die;
		//		}
		/*Only include the single product post type and exclude all other custom post types.*/
		if ( is_singular() ) {
			if ( ! is_product() ) {
				return $comment_args;
			}
		}
		$sort_type = self::$settings->get_params( 'photo', 'sort' )['time'];
		switch ( $sort_type ) {
			case 1:
				$comment_args['orderby'] = 'comment_date_gmt';
				$comment_args['order']   = 'DESC';
				break;
			case 2:
				$comment_args['orderby'] = 'comment_date_gmt';
				$comment_args['order']   = 'ASC';
				break;
			case 3:
				if ( empty( $_GET['moderation-hash'] ) ) {
					$comment_args['meta_key'] = 'wcpr_vote_up_count';
					//					$comment_args['meta_query'][] = array(
					//						'relation' => 'OR',
					//						array(
					//							'key'     => 'wcpr_vote_up_count',
					//							'compare' => 'EXISTS'
					//						),
					//						array(
					//							'key'     => 'wcpr_vote_up_count',
					//							'compare' => 'NOT EXISTS'
					//						)
					//					);
					//					$comment_args['orderby']      = [ 'meta_value_num' => 'DESC', 'comment_date_gmt' => 'DESC' ];
					$comment_args['orderby'] = [ 'meta_value_num' => 'DESC', 'comment_date_gmt' => 'ASC' ];
				} else {
					$comment_args['orderby'] = 'comment_date_gmt';
					$comment_args['order']   = 'DESC';
				}
				break;
		}

		return $comment_args;
	}

	/**Custom folder to save images uploaded in reviews imported from AliExpress or CSV
	 *
	 * @param $param
	 *
	 * @return mixed
	 */
	public static function import_upload_folder( $param ) {
		$settings             = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		$import_upload_folder = $settings->get_params( 'import_upload_folder' );
		if ( $import_upload_folder ) {
			$subdir = '';
			if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
				// Generate the yearly and monthly dirs
				$time   = current_time( 'mysql' );
				$y      = substr( $time, 0, 4 );
				$m      = substr( $time, 5, 2 );
				$subdir = "/$y/$m";
			}
			if ( ! empty( self::$product_id ) ) {
				$import_upload_folder = str_replace( '{product_id}', self::$product_id, $import_upload_folder );
			}
			$import_upload_folder = '/' . $import_upload_folder;
			$param['path']        = str_replace( $param['basedir'], $param['basedir'] . $import_upload_folder, $param['path'] );
			$param['url']         = str_replace( $param['baseurl'], $param['baseurl'] . $import_upload_folder, $param['url'] );
			if ( $subdir && ! empty( $param['subdir'] ) ) {
				$param['path'] = str_replace( $param['subdir'], $subdir, $param['path'] );
				$param['url']  = str_replace( $param['subdir'], $subdir, $param['url'] );
			}
		}

		return $param;
	}

	public static function fix_style( $flag_size ) {
		$margin_width = ( 60 - 60 * $flag_size ) / 2;
		$margin_heigh = ( 40 - 40 * $flag_size ) / 2;
		$style        = "transform: scale({$flag_size}); margin: -{$margin_heigh}px -{$margin_width}px";

		return $style;
	}
}