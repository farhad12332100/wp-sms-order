<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $product ) || empty( $comment ) || $comment->comment_parent || empty( $settings ) ) {
	return;
}
if (!isset($edit_enable) && $settings->get_params( 'review_edit_enable' )){
	global $current_user;
    $edit_enable = !empty($current_user->ID) && ! empty( $comment->user_id ) && $current_user->ID == $comment->user_id;
}
$product_title = $product->get_title();
if ( $settings->get_params( 'custom_fields_enable' ) ) {
	$custom_fields = apply_filters( 'woocommerce_photo_reviews_custom_fields', get_comment_meta( $comment->comment_ID, 'wcpr_custom_fields', true ), $comment, $product );
	if ( is_array( $custom_fields ) && count( $custom_fields ) ) {
		$number_of_fields = 0;
		ob_start();
		foreach ( $custom_fields as $custom_field ) {
			$custom_field_name  = apply_filters( 'woocommerce_photo_reviews_custom_field_name', $custom_field['name'], $custom_field );
			$custom_field_value = apply_filters( 'woocommerce_photo_reviews_custom_field_value', $custom_field['value'], $custom_field );
			$custom_field_unit  = apply_filters( 'woocommerce_photo_reviews_custom_field_unit', $custom_field['unit'], $custom_field );
			if (  ! $custom_field_value ) {
				continue;
			}
			ob_start();
			?>
            <div class="wcpr-review-custom-field">
                <?php
                if ($custom_field_name){
                    printf('<span class="wcpr-review-custom-field-name">%s</span>:',wp_kses_post( $custom_field_name ));
                }
                ?>
                <span class="wcpr-review-custom-field-value"><?php echo wp_kses_post( $custom_field_unit ? $custom_field_value . ' ' . $custom_field_unit : $custom_field_value ) ?></span>
            </div>
			<?php
			echo apply_filters( 'woocommerce_photo_reviews_custom_field_html', ob_get_clean(), $custom_field );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$number_of_fields ++;
		}
		$custom_fields_html = apply_filters( 'woocommerce_photo_reviews_custom_fields_html', ob_get_clean(), $custom_fields );
		if ( $number_of_fields ) {
			?>
            <div class="wcpr-review-custom-fields <?php echo esc_attr( 'wcpr-review-custom-fields-' . $number_of_fields ) ?>">
				<?php
				echo wp_kses_post( $custom_fields_html );
				?>
            </div>
			<?php
		}
	}
}
printf( '<div class="kt-reviews-image-container kt-reviews-image-container-image-popup-%s">', esc_attr( $image_popup ) );
$image_post_ids = get_comment_meta( $comment->comment_ID, 'reviews-images', true );
if (empty($image_post_ids) && $settings->get_params( 'photo', 'product_gallery_enable' ) ){
	$product_galleries = $product->get_gallery_image_ids();
	if ( $product_image_id = $product->get_image_id()){
		$product_galleries[] = $product_image_id;
	}
	if (!empty($product_galleries)) {
		$show_random  = wp_rand( 0, count( $product_galleries ) - 1 );
		$image_post_ids = [ $product_galleries[ $show_random ] ];
	}
}
if ( is_array( $image_post_ids ) && !empty( $image_post_ids ) ) {
	?>
    <div class="kt-wc-reviews-images-wrap-wrap">
		<?php
		$i = 0;
		foreach ( $image_post_ids as $image_post_id ) {
			if ( ! villatheme_is_url( $image_post_id ) ) {
				$image_post = get_post( $image_post_id );
				if ( ! $image_post ) {
					continue;
				}
				$image_data = wp_get_attachment_metadata( $image_post_id );
				$is_video   = strpos( $image_data['mime_type'] ?? '', 'video/' ) === 0;
				$alt        = get_post_meta( $image_post_id, '_wp_attachment_image_alt', true );
				$image_alt  = $alt ? $alt : $product_title;
				?>
                <div class="reviews-images-item"
                     data-image_src="<?php echo esc_attr( apply_filters( 'woocommerce_photo_reviews_big_review_photo', $is_video ? wp_get_attachment_url( $image_post_id ) : wp_get_attachment_image_url( $image_post_id, 'full' ), $image_post_id, $comment ) ) ?>"
                     data-index="<?php echo esc_attr( $i ); ?>"
                     data-image_caption="<?php echo esc_attr( $image_post->post_excerpt ) ?>">
					<?php
					if ( $is_video ) {
						printf( '<video class="review-images review-videos" src="%s" >%s</video>',
							esc_url( apply_filters( 'woocommerce_photo_reviews_thumbnail_photo', wp_get_attachment_url( $image_post_id ), $image_post_id, $comment ) ),
							esc_attr( apply_filters( 'woocommerce_photo_reviews_image_alt', $image_alt, $image_post_id, $comment ) ) );
					} else {
						?>
                        <img class="review-images" loading="lazy"
                             src="<?php echo esc_url( apply_filters( 'woocommerce_photo_reviews_thumbnail_photo', wp_get_attachment_image_url( $image_post_id ), $image_post_id, $comment ) ); ?>"
                             alt="<?php echo esc_attr( apply_filters( 'woocommerce_photo_reviews_image_alt', $image_alt, $image_post_id, $comment ) ) ?>"/>
						<?php
					}
					?>
                </div>
				<?php
			} else {
				$file_type = explode( '.', $image_post_id );
				$file_type = end( $file_type );
				if ( ! in_array( 'image/' . strtolower( $file_type ), $settings->get_params( 'upload_allow_images' ) ) ) {
					if ( strpos( $image_post_id, '.mp4' ) || strpos( $image_post_id, '.webm' ) ) {
						printf( '<div class="reviews-images-item" data-image_src="%s" data-index="%s"><video class="review-images review-videos" src="%s" >%s</video></div>',
							esc_attr( $image_post_id ), esc_attr( $i ), esc_url( $image_post_id ), esc_attr( $product_title ) );
					} elseif ( strpos( $image_post_id, '.shopee.' ) ) {
						?>
                        <div class="reviews-images-item" data-image_src="<?php echo esc_attr( $image_post_id ) ?>"
                             data-index="<?php echo esc_attr( $i ); ?>">
                            <img class="review-images" loading="lazy" src="<?php echo esc_url( $image_post_id ); ?>"
                                 alt="<?php echo esc_attr( $product_title ) ?>"/>
                        </div>
						<?php
					} else {
						printf( '<div class="reviews-images-item" data-image_src="%s" data-index="%s"><iframe class="review-images review-iframe" src="%s" frameborder="0" allowfullscreen></iframe></div>',
							esc_attr( $image_post_id ), esc_attr( $i ), esc_url( $image_post_id ) );
					}
				} else {
					?>
                    <div class="reviews-images-item" data-image_src="<?php echo esc_attr( $image_post_id ) ?>"
                         data-index="<?php echo esc_attr( $i ); ?>">
                        <img class="review-images" loading="lazy" src="<?php echo esc_url( $image_post_id ); ?>"
                             alt="<?php echo esc_attr( $product_title ) ?>"/>
                    </div>
					<?php
				}
			}
			$i ++;
		}
		?>
    </div>
    <div class="big-review-images">
        <div class="big-review-images-content-container">
            <div class="big-review-images-content"></div>
			<?php
			if ( $caption_enable ) {
				?>
                <div class="wcpr-review-image-caption"></div>
				<?php
			}
			?>
        </div>
        <span class="wcpr-close-normal"></span>
        <div class="wcpr-rotate">
            <input type="hidden" class="wcpr-rotate-value" value="0">
            <span class="wcpr-rotate-left wcpr_rotate-rotate-left-circular-arrow-interface-symbol"
                  title="<?php esc_attr_e( 'Rotate left 90 degrees', 'woocommerce-photo-reviews' ) ?>"></span>
            <span class="wcpr-rotate-right wcpr_rotate-rotating-arrow-to-the-right"
                  title="<?php esc_attr_e( 'Rotate right 90 degrees', 'woocommerce-photo-reviews' ) ?>"></span>
        </div>
		<?php
		if ( count( $image_post_ids ) > 1 ) {
			?>
            <span class="wcpr-prev-normal"></span>
            <span class="wcpr-next-normal"></span>
			<?php
		}
		?>
    </div>
	<?php
}
printf( '</div>' );
if ( ! empty( $edit_enable ) || ( $settings->get_params( 'photo', 'helpful_button_enable' ) && isset( $vote_info ) ) ) {
	$class = 'wcpr-comment-helpful-button-container';
	if (! empty( $edit_enable ) && isset( $vote_info )){
		$class .= ' wcpr-comment-helpful-edit-button-container';
	}
	if ( isset( $vote_info ) ) {
		$up_votes   = get_comment_meta( $comment->comment_ID, 'wcpr_vote_up', false );
		$down_votes = get_comment_meta( $comment->comment_ID, 'wcpr_vote_down', false );
		if ( in_array( $vote_info, $up_votes ) ) {
			$class .= ' wcpr-comment-helpful-button-voted-up';
		} elseif ( in_array( $vote_info, $down_votes ) ) {
			$class .= ' wcpr-comment-helpful-button-voted-down';
		}
	}
	?>
    <div class="<?php echo esc_attr( $class ) ?>" data-comment_id="<?php echo esc_attr( $comment->comment_ID ) ?>">
        <div class="wcpr-comment-helpful-button-voting-overlay"></div>
		<?php
		if ( ! empty( $edit_enable ) ) {
			?>
            <div class="wcpr-comment-helpful-button-edit-container">
                <span class="wcpr-comment-helpful-button-label wcpr-comment-helpful-button-edit"><?php esc_html_e( 'Edit', 'woocommerce-photo-reviews' ); ?></span>
                <span class="woocommerce-review__dash">|</span>
                <span class="wcpr-comment-helpful-button-label wcpr-comment-helpful-button-delete"><?php esc_html_e( 'Delete', 'woocommerce-photo-reviews' ); ?></span>
            </div>
			<?php
		}
		if ( isset( $vote_info ) ) {
			$updated_cmt_meta = get_option( 'wcpr_comment_meta_updated' );

			$helpful_label = $settings->get_params( 'photo', 'helpful_button_title', VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language() );
			?>
            <div class="wcpr-comment-helpful-button-vote-container">
				<?php
				if ( $helpful_label ) {
					?>
                    <span class="wcpr-comment-helpful-button-label"><?php echo wp_kses_post( $helpful_label ) ?></span>
					<?php
				}
				?>
                <span class="wcpr-comment-helpful-button-up-vote-count">
                <?php
                if ( $updated_cmt_meta ) {
	                echo esc_html( absint( get_comment_meta( $comment->comment_ID, 'wcpr_vote_up_count', true ) ) );
                } else {
	                echo esc_html( count( $up_votes ) + absint( get_comment_meta( $comment->comment_ID, 'wcpr_vote_up_count', true ) ) );
                }
                ?>
            </span>
                <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-up-vote woocommerce-photo-reviews-vote-like"></span>
                <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-down-vote woocommerce-photo-reviews-vote-like"></span>
                <span class="wcpr-comment-helpful-button-down-vote-count">
                <?php
                if ( $updated_cmt_meta ) {
	                echo esc_html( absint( get_comment_meta( $comment->comment_ID, 'wcpr_vote_down_count', true ) ) );
                } else {
	                echo esc_html( count( $down_votes ) + absint( get_comment_meta( $comment->comment_ID, 'wcpr_vote_down_count', true ) ) );
                }
                ?>
            </span>
            </div>
		<?php } ?>
    </div>
	<?php
}
?>