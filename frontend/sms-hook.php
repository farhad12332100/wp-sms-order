<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Send SMS (and enforce cart-only one-per-order) when coupon email content is generated
add_filter( 'viwcpr_email_coupon_get_content', function( $content, $comment_id ) {
	try {
		if ( ! function_exists( 'PWSMS' ) ) {
			return $content; // Persian WooCommerce SMS plugin not active
		}
		$comment = get_comment( $comment_id );
		if ( ! $comment || intval( $comment->comment_approved ) !== 1 ) {
			return $content;
		}
		$user_email = $comment->comment_author_email;
		$user_id    = $comment->user_id ?: ( ( $u = get_user_by( 'email', $user_email ) ) ? $u->ID : 0 );
		if ( ! $user_email && ! $user_id ) {
			return $content;
		}
		$product_id    = $comment->comment_post_ID;
		$customer_name = $comment->comment_author;

		// Determine which coupon rule applied
		if ( ! class_exists( 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend' ) ) {
			return $content;
		}
		$rule = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_coupon_rule_id( $comment_id, $user_id, $user_email );
		$coupon_rule_id = isset( $rule['id'] ) ? $rule['id'] : '';
		if ( ! $coupon_rule_id ) {
			return $content;
		}
		$settings  = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		$language  = method_exists( 'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend', 'get_language' ) ? VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language() : '';
		$sms_conf  = $settings->get_current_setting( 'coupons', 'sms', $coupon_rule_id, $language );
		$require   = $settings->get_current_setting( 'coupons', 'require', $coupon_rule_id );
		$cart_only = ! empty( $require['cart_only'] );
		if ( empty( $sms_conf['enable'] ) ) {
			return $content; // SMS disabled for this rule
		}

		// Extract coupon code and date from content (best effort)
		$coupon_code  = '';
		$date_expires = '';
		if ( preg_match( '/<span[^>]*>([A-Z0-9_-]{4,})<\/span>/', $content, $m ) ) {
			$coupon_code = $m[1];
		} elseif ( preg_match( '/Coupon(?:\s|&nbsp;)*code\s*:\s*([A-Z0-9_-]{4,})/i', wp_strip_all_tags( $content ), $m ) ) {
			$coupon_code = strtoupper( $m[1] );
		}
		if ( preg_match( '/Date\s*expires\s*:\s*([^<\n]+)/i', wp_strip_all_tags( $content ), $m ) ) {
			$date_expires = trim( $m[1] );
		}

		// Build SMS text
		$msg = $sms_conf['content'] ?? '';
		if ( $msg ) {
			$msg = str_replace( array('{customer_name}','{coupon_code}','{date_expires}','{last_valid_date}'), array( $customer_name, $coupon_code, $date_expires, $date_expires ), $msg );
		}

		// Cart-only: find customer's completed order containing this product and ensure not already sent for that order
		$order_id_for_mark = 0;
		if ( $cart_only ) {
			$orders = array();
			if ( $user_id ) {
				$orders = wc_get_orders( array( 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'customer_id' => $user_id, 'status' => array( 'wc-completed' ), 'return' => 'objects' ) );
			}
			if ( empty( $orders ) && $user_email ) {
				$orders = wc_get_orders( array( 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'billing_email' => $user_email, 'status' => array( 'wc-completed' ), 'return' => 'objects' ) );
			}
			foreach ( (array) $orders as $o ) {
				foreach ( $o->get_items() as $it ) {
					if ( intval( $it->get_product_id() ) === intval( $product_id ) || intval( $it->get_variation_id() ) === intval( $product_id ) ) {
						$order_id_for_mark = $o->get_id();
						break 2;
					}
				}
			}
			if ( $order_id_for_mark ) {
				$o = wc_get_order( $order_id_for_mark );
				if ( $o && $o->get_meta( '_wcpr_cart_only_coupon_sent' ) ) {
					return $content; // already sent, do nothing
				}
			}
		}

		// Phone selection: user profile phone preferred, fallback to order billing phone if we found an order
		$mobile = '';
		if ( $user_id ) {
			$mobile = get_user_meta( $user_id, 'billing_phone', true );
		}
		if ( ! $mobile && ! empty( $order_id_for_mark ) ) {
			$mobile = wc_get_order( $order_id_for_mark )->get_billing_phone();
		}

		if ( $mobile && $msg ) {
			PWSMS()->send_sms( array( 'post_id' => $order_id_for_mark ?: 0, 'type' => 2, 'mobile' => $mobile, 'message' => $msg ) );
			update_comment_meta( $comment_id, 'coupon_sms', 'sent' );
			if ( $cart_only && $order_id_for_mark ) {
				$o = wc_get_order( $order_id_for_mark );
				$o->update_meta_data( '_wcpr_cart_only_coupon_sent', 1 );
				$o->save_meta_data();
			}
		}
	} catch ( \Throwable $e ) {
		// fail silently
	}
	return $content;
}, 10, 2 );
