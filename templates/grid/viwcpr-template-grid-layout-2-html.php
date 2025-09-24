<?php
if (!defined('ABSPATH')) {
    exit;
}
if (empty($my_comments) || !is_array($my_comments) || empty($settings)) {
    return;
}
$prefix = $is_shortcode ? 'shortcode_' : '';
$prefix_class = $is_shortcode ? 'shortcode-' : '';
global $product;
$return_product = $product;
$grid_class = '';
$grid_tag_html = !empty($parent_tag_html) && in_array($parent_tag_html, ['ul', 'ol']) ? 'li' : 'div';
if (isset($cols)) {
    $grid_class = array(
        $prefix_class . 'wcpr-grid-layout-2-style',
        $prefix_class . 'wcpr-grid',
        $prefix_class . 'wcpr-grid-' . $cols . '-col',
        $prefix_class . 'wcpr-grid-popup-' . $masonry_popup,
    );
    if ($enable_box_shadow) {
        $grid_class[] = $prefix_class . 'wcpr-enable-box-shadow';
    }
    if (!empty($loadmore_button) && in_array($loadmore_button, ['on', '1'])) {
        $grid_class[] = 'wcpr-grid-loadmore';
    }
}
$countries = VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Ali::get_countries();
$show_review_country = $settings->get_params('show_review_country');
$review_title_enable = $settings->get_params('review_title_enable');
$auto_play_video = $settings->get_params('auto_play_video');
$updated_cmt_meta = get_option('wcpr_comment_meta_updated');
$is_wpml_active = is_plugin_active('sitepress-multilingual-cms/sitepress.php');
$user = wp_get_current_user();
if ($user) {
    if (!empty($user->ID)) {
        $vote_info = $user->ID;
        if ($settings->get_params('review_edit_enable')) {
            $user_id = $vote_info;
        }
    } else {
        $vote_info = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_the_user_ip();
    }
} else {
    $vote_info = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_the_user_ip();
}
$full_image_size = $settings->get_params('photo', 'full_image_size');
$caption_enable = $settings->get_params('image_caption_enable');
$product_gallery_enable = $settings->get_params('photo', 'product_gallery_enable');
$image_title = $masonry_popup === 'off' ? '' : esc_attr__('Click to view full screen', 'woocommerce-photo-reviews');
$max_content_length = intval(wp_is_mobile() ? ($settings->get_params('photo', 'max_content_length_mobile') ?? $settings->get_params('photo', 'max_content_length')) : $settings->get_params('photo', 'max_content_length'));
if ($grid_class) {
    printf('<%s class="%s" data-wcpr_columns="%s">', $grid_tag_html, esc_attr(trim(implode(' ', $grid_class))), esc_attr($cols ?? '3'));
}
foreach ($my_comments as $v) {
    if ($v->comment_parent) {
        continue;
    }
    $comment = $v;
    $product = $is_shortcode ? wc_get_product($comment->comment_post_ID) : $product;
    if ($product) {
        $product_title = $product->get_title() . ' photo review';
        $comment_children = $comment->get_children();
        $rating = intval(get_comment_meta($comment->comment_ID, 'rating', true));
        ?>
        <div id="<?php echo esc_attr($prefix_class); ?>comment-<?php echo esc_attr($v->comment_ID); ?>" class="<?php echo esc_attr($prefix_class); ?>wcpr-grid-layout-2-style-item">
            <div class="<?php echo esc_attr($prefix_class); ?>wcpr-content">
                <?php
                do_action('woocommerce_photo_reviews_' . $prefix . 'grid_item_top', $comment, $product);
                $img_post_ids = get_comment_meta($v->comment_ID, 'reviews-images', true);
                if (empty($img_post_ids) && $product_gallery_enable) {
                    $product_galleries = $product->get_gallery_image_ids();
                    if ($product_image_id = $product->get_image_id()) {
                        $product_galleries[] = $product_image_id;
                    }
                    if (!empty($product_galleries)) {
	                    switch ( $product_gallery_enable ) {
		                    case '1':
			                    $show_random  = wp_rand( 0, count( $product_galleries ) - 1 );
			                    $img_post_ids = [ $product_galleries[ $show_random ] ];
			                    break;
		                    case '2':
			                    $img_post_ids = [ $product_galleries[0] ];
			                    break;
		                    default:
			                    break;
	                    }
                    }
                }
                ?>
                <div class="<?php echo esc_attr($prefix_class); ?>review-author-container">
                    <?php
                    if ('0' === $v->comment_approved) {
                        printf('<p class="meta"><em class="woocommerce-review__awaiting-approval">%s</em></p>', esc_html__('Your review is awaiting approval', 'woocommerce-photo-reviews'));
                    } else {
                        ?>

                        <div class="<?php echo esc_attr($prefix_class); ?>review-author-container-top">
                            <?php do_action('woocommerce_photo_reviews_' . $prefix . 'grid_item_author_container_top', $comment, $product); ?>
                        </div>
                        <div class="<?php echo esc_attr($prefix_class); ?>review-author-container-bottom">
                            <div class="review-author-container-bottom-left">
                                <?php
                                $get_comment_author_email = get_comment_author_email($comment);
                                echo get_avatar($get_comment_author_email);
                                ?>
                            </div>
                            <div class="review-author-container-bottom-right">
                                <?php
                                if ($is_wpml_active) {
                                    ?>
                                    <div style="display: none">
                                        <?php
                                        do_action('woocommerce_review_before', $comment);//For WPML review translation functions to run only, contents are not supposed to display here
                                        ?>
                                    </div>
                                    <?php
                                }
                                do_action('woocommerce_photo_reviews_' . $prefix . 'grid_item_author_container_bottom', $comment, $product);
                                $review_country_html = '';
                                $comment_author_class = [$prefix_class . 'wcpr-comment-author'];
                                if ($show_review_country) {
                                    $review_country = get_comment_meta($comment->comment_ID, 'wcpr_review_country', true);
                                    if ($review_country) {
                                        $comment_author_class[] = $prefix_class . 'wcpr-comment-author-with-country';
                                        ob_start();
                                        ?>
                                        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-review-country"
                                             title="<?php echo esc_attr(isset($countries[$review_country]) ? $countries[$review_country] : $review_country); ?>">
                                            <i class="vi-flag-64 flag-<?php echo esc_attr(strtolower($review_country)) ?>"></i>
                                        </div>
                                        <?php
                                        $review_country_html = ob_get_clean();
                                    }
                                }
                                ?>
                                <div class="<?php echo esc_attr(trim(implode(' ', $comment_author_class))) ?>">
                                    <?php
                                    echo wp_kses_post($review_country_html);
                                    comment_author($comment);
                                    if ('yes' === get_option('woocommerce_review_rating_verification_label') && 1 == get_comment_meta($comment->comment_ID, 'verified', true)) {
                                        switch ($settings->get_params('photo', 'verified')) {
                                            case 'badge':
                                                printf('<em class="woocommerce-review__verified verified woocommerce-photo-reviews-verified %s"></em>', $settings->get_params('photo', 'verified_badge') ?: 'woocommerce-photo-reviews-badge-tick');
                                                break;
                                            case 'text':
                                                printf('<em class="woocommerce-review__verified verified woocommerce-photo-reviews-verified">%s</em>', $settings->get_params('photo', 'verified_text', VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language() ?: apply_filters('wpml_current_language', null)));
                                                break;
                                            default:
                                                printf('<em class="woocommerce-review__verified verified woocommerce-photo-reviews-verified %swcpr-icon-badge"></em>', esc_attr($prefix_class));
                                        }
                                    }
                                    ?>
                                </div>
                                <div>
                                    <div class="wcpr-review-rating">
                                        <?php
                                        if ($rating > 0) {
                                            echo wc_get_rating_html($rating);
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    if ($settings->get_params('photo', 'show_review_date')) {
                                        ?>
                                        <div class="wcpr-review-date">
                                            <?php
                                            $review_date_format = $settings->get_params('photo', 'custom_review_date_format');
                                            if (!$review_date_format) {
                                                $review_date_format = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_date_format();
                                            }
                                            comment_date($review_date_format, $comment)
                                            ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php

                    }

                    if (is_array($comment_children) && count($comment_children)) {
                        ?>
                        <div class="wcpr-comment-children">
                            <div class="wcpr-comment-children-content">
                                <?php
                                foreach ($comment_children as $comment_child) {
                                    ?>
                                    <div class="wcpr-comment-child">
                                        <div class="wcpr-comment-child-author">
                                            <?php
                                            ob_start();
                                            esc_html_e('Reply from ', 'woocommerce-photo-reviews');
                                            ?>
                                            <span class="wcpr-comment-child-author-name"><?php comment_author($comment_child); ?></span>:
                                            <?php
                                            $comment_child_author = ob_get_clean();
                                            $comment_child_author = apply_filters('woocommerce_photo_reviews_reply_author_html', $comment_child_author, $comment_child);
                                            echo wp_kses_post($comment_child_author);
                                            ?>
                                        </div>
                                        <?php
                                        $class = array('wcpr-comment-child-content');
                                        $comment_content = $comment_child->comment_content;
                                        $stripped_comment_content = strip_tags($comment_content);
                                        $comment_content_length = function_exists('mb_strlen') ? mb_strlen($stripped_comment_content) : strlen($stripped_comment_content);
                                        if ($comment_content_length > $max_content_length) {
                                            $class[] = "{$prefix_class}wcpr-review-content";
                                            $comment_content_t = function_exists('mb_substr') ? mb_substr($stripped_comment_content, 0, $max_content_length) : substr($stripped_comment_content, 0, $max_content_length);
                                            $comment_content = sprintf('<div class="%swcpr-review-content-short">%s<span class="%swcpr-read-more" title="%s">%s</span></div><div class="%swcpr-review-content-full">%s</div>',
                                                esc_attr($prefix_class), wp_kses_post($comment_content_t), esc_attr($prefix_class), esc_html__('Read more', 'woocommerce-photo-reviews'),
                                                esc_html__('...More', 'woocommerce-photo-reviews'), esc_attr($prefix_class), apply_filters('woocommerce_photo_reviews_grid_review_content', nl2br($comment_content), $comment));
                                        }
                                        ?>
                                        <div class="<?php echo esc_attr(implode(' ', $class)) ?>">
                                            <?php
                                            echo nl2br($comment_content);
                                            ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="<?php echo esc_attr($prefix_class); ?>review-content-container-bottom">
                    <div class="<?php echo esc_attr($prefix_class); ?>review-content-container">
                        <?php
                        if ($review_title_enable && ($review_title = get_comment_meta($comment->comment_ID, 'wcpr_review_title', true))) {
                            $review_title = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::wpml_translate_single_string(
                                'viwcpr-reviews-title',
                                'viwcpr-title-product-review-' . $comment->comment_ID,
                                $review_title
                            );
                            printf('<div class="%swcpr-review-title" title="%s">%s</div>', esc_attr($prefix_class), esc_attr($review_title), esc_html($review_title));
                        }
                        ?>
                        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-wrap-review-custom-fields">
                            <?php
                            if ($settings->get_params('custom_fields_enable')) {
                                $custom_fields = apply_filters('woocommerce_photo_reviews_custom_fields', get_comment_meta($comment->comment_ID, 'wcpr_custom_fields', true), $comment, $product);
                                if (is_array($custom_fields) && count($custom_fields)) {
                                    $number_of_fields = 0;
                                    ob_start();
                                    foreach ($custom_fields as $custom_field) {
                                        $custom_field_name = apply_filters('woocommerce_photo_reviews_custom_field_name', $custom_field['name'], $custom_field);
                                        $custom_field_value = apply_filters('woocommerce_photo_reviews_custom_field_value', $custom_field['value'], $custom_field);
                                        $custom_field_unit = apply_filters('woocommerce_photo_reviews_custom_field_unit', $custom_field['unit'], $custom_field);
                                        if (!$custom_field_value) {
                                            continue;
                                        }
                                        ob_start();
                                        ?>
                                        <div class="wcpr-review-custom-field">
                                            <?php
                                            if ($custom_field_name) {
                                                printf('<span class="wcpr-review-custom-field-name">%s</span>:', wp_kses_post($custom_field_name));
                                            }
                                            ?>

                                            <span class="wcpr-review-custom-field-value"><?php echo wp_kses_post($custom_field_unit ? $custom_field_value . ' ' . $custom_field_unit : $custom_field_value) ?></span>
                                        </div>
                                        <?php
                                        echo apply_filters('woocommerce_photo_reviews_custom_field_html', ob_get_clean(), $custom_field);
                                        $number_of_fields++;
                                    }
                                    $custom_fields_html = apply_filters('woocommerce_photo_reviews_custom_fields_html', ob_get_clean(), $custom_fields);
                                    if ($number_of_fields) {
                                        ?>
                                        <div class="wcpr-review-custom-fields <?php esc_attr_e('wcpr-review-custom-fields-' . $number_of_fields) ?>">
                                            <?php
                                            echo wp_kses_post($custom_fields_html);
                                            ?>
                                        </div>
                                        <?php
                                    }
                                }
                            }
                            ?>
                        </div>
                        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-wrap-review-content">
                            <?php
                            $comment->wcpr_grid = 1;
                            if ($is_wpml_active) {
                                ?>
                                <div>
                                    <?php
                                    do_action('woocommerce_review_before_comment_text', $comment);//For WPML review translation functions to run only, contents are not supposed to display here
                                    ?>
                                </div>
                                <?php
                            }
                            $comment_content = $comment->comment_content;
                            $stripped_comment_content = strip_tags($comment_content);
                            $comment_content_length = function_exists('mb_strlen') ? mb_strlen($stripped_comment_content) : strlen($stripped_comment_content);
                            if ($comment_content_length > $max_content_length) {
                                $comment_content = function_exists('mb_substr') ? mb_substr($stripped_comment_content, 0, $max_content_length) : substr($stripped_comment_content, 0, $max_content_length);
                                $comment_content = sprintf('<div class="%swcpr-review-content-short">%s<span class="%swcpr-read-more" title="%s">%s</span></div><div class="%swcpr-review-content-full">%s</div>',
                                    esc_attr($prefix_class), $comment_content, esc_attr($prefix_class), esc_html__('Read more', 'woocommerce-photo-reviews'), esc_html__('...More', 'woocommerce-photo-reviews'),
                                    esc_attr($prefix_class), apply_filters('woocommerce_photo_reviews_grid_review_content', nl2br($comment->comment_content), $comment));
                            } else {
                                $comment_content = apply_filters('woocommerce_photo_reviews_grid_review_content', nl2br($comment->comment_content), $comment);
                            }
                            ?>
                            <div class="<?php echo esc_attr($prefix_class); ?>wcpr-review-content"><?php echo $comment_content; ?></div>
                            <?php
                            if ($is_wpml_active) {
                                ?>
                                <div>
                                    <?php
                                    do_action('woocommerce_review_after_comment_text', $comment);//For WPML review translation functions to run only, contents are not supposed to display here
                                    ?>
                                </div>
                                <?php
                            }
                            do_action('woocommerce_photo_reviews_' . $prefix . 'grid_item_after_main_content', $comment, $product);
                            ?>
                        </div>
                        <div class="<?php echo esc_attr($prefix_class); ?>wcpr-wrap-review-helpfull">
                            <?php
                            $edit_enable = !empty($user_id) && !empty($comment->user_id) && $user_id == $comment->user_id;
                            $show_help_button = $settings->get_params('photo', 'helpful_button_enable') && 1 == $comment->comment_approved;
                            if (!empty($edit_enable) || $show_help_button) {
                                $class = 'wcpr-comment-helpful-button-container';
                                if (!empty($edit_enable) && $show_help_button) {
                                    $class .= ' wcpr-comment-helpful-edit-button-container';
                                }
                                if ($show_help_button) {
                                    $helpful_label = $settings->get_params('photo', 'helpful_button_title', VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language());
                                    $up_votes = get_comment_meta($comment->comment_ID, 'wcpr_vote_up', false);
                                    $down_votes = get_comment_meta($comment->comment_ID, 'wcpr_vote_down', false);
                                    if (in_array($vote_info, $up_votes)) {
                                        $class .= ' wcpr-comment-helpful-button-voted-up';
                                    } elseif (in_array($vote_info, $down_votes)) {
                                        $class .= ' wcpr-comment-helpful-button-voted-down';
                                    }
                                }
                                ?>
                                <div class="<?php echo esc_attr($class) ?>"
                                     data-comment_id="<?php echo esc_attr($comment->comment_ID) ?>">
                                    <div class="wcpr-comment-helpful-button-voting-overlay"></div>
                                    <?php
                                    if (!empty($edit_enable)) {
                                        ?>
                                        <div class="wcpr-comment-helpful-button-edit-container">
                                            <span class="wcpr-comment-helpful-button-label wcpr-comment-helpful-button-edit"><?php esc_html_e('Edit', 'woocommerce-photo-reviews'); ?></span>
                                            <span class="woocommerce-review__dash">|</span>
                                            <span class="wcpr-comment-helpful-button-label wcpr-comment-helpful-button-delete"><?php esc_html_e('Delete', 'woocommerce-photo-reviews'); ?></span>
                                        </div>
                                        <?php
                                    }
                                    if ($show_help_button) {
                                        ?>
                                        <div class="wcpr-comment-helpful-button-vote-container">
                                            <?php
                                            if ($helpful_label) {
                                                ?><span class="wcpr-comment-helpful-button-label"><?php echo wp_kses_post($helpful_label) ?></span><?php
                                            }
                                            ?>
                                            <span class="wcpr-comment-helpful-button-up-vote-count">
                                                <?php
                                                if ($updated_cmt_meta) {
                                                    echo esc_html(absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_up_count', true)));
                                                } else {
                                                    echo esc_html(count($up_votes) + absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_up_count', true)));
                                                }
                                                ?>
                                            </span>
                                            <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-up-vote woocommerce-photo-reviews-vote-like"></span>
                                            <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-down-vote woocommerce-photo-reviews-vote-like"></span>
                                            <span class="wcpr-comment-helpful-button-down-vote-count">
                                                <?php if ($updated_cmt_meta) {
                                                    echo esc_html(absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_down_count', true)));
                                                } else {
                                                    echo esc_html(count($down_votes) + absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_down_count', true)));
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <div class="<?php echo esc_attr($prefix_class); ?>review-content-image">
                        <?php
                        if (is_array($img_post_ids) && !empty($img_post_ids)) {
                            ?>
                            <div class="<?php echo esc_attr($prefix_class); ?>reviews-images-container">
                                <div class="<?php echo esc_attr($prefix_class); ?>reviews-images-wrap-left">
                                    <?php

                                        foreach ($img_post_ids as $img_post_ids_k => $img_post_id) {
                                            if (!villatheme_is_url($img_post_id)) {
                                                $image_post = get_post($img_post_id);
                                                if (!$image_post) {
                                                    continue;
                                                }
                                                $image_data = wp_get_attachment_metadata($img_post_id);
                                                $is_video = strpos($image_data['mime_type'] ?? '', 'video/') === 0;
                                                $alt = get_post_meta($img_post_id, '_wp_attachment_image_alt', true);
                                                $image_alt = $alt ?: $product_title;
                                                $data_image_src = $is_video ? wp_get_attachment_url($img_post_id) : wp_get_attachment_image_url($img_post_id, 'full');
                                                $data_image_caption = $caption_enable ? $image_post->post_excerpt : '';
                                                $thumb = wp_get_attachment_thumb_url($img_post_id);
                                                if ($full_image_size || strpos($data_image_src, '.gif') || $is_video) {
                                                    $href = $data_image_src;
                                                } else {
                                                    $href = (isset($image_data['sizes']['wcpr-photo-reviews']) ? wp_get_attachment_image_url($img_post_id, 'wcpr-photo-reviews') : (isset($image_data['sizes']['medium_large']) ? wp_get_attachment_image_url($img_post_id, 'medium_large') : (isset($image_data['sizes']['medium']) ? wp_get_attachment_image_url($img_post_id, 'medium') : $data_image_src)));
                                                }
                                                if ($is_video) {
                                                    printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" data-image_src="%s" data-image_caption="%s" href="%s"><video class="%sreviews-images reviews-videos" src="%s"  >%s</video></a></div>',
                                                        esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($data_image_src), esc_attr($data_image_caption),
                                                        esc_url(apply_filters('woocommerce_photo_reviews_grid_thumbnail_main', $href, $img_post_id)),
                                                        esc_attr($prefix_class), esc_url($href), esc_attr($image_alt)
                                                    );
                                                } else {
                                                    printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" data-image_src="%s" data-image_caption="%s" href="%s"><img class="%sreviews-images" loading="lazy" src="%s" alt="%s"></a></div>',
                                                        esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($href), esc_attr($data_image_caption),
                                                        esc_url(apply_filters('woocommerce_photo_reviews_grid_thumbnail_main', $href, $img_post_id)),
                                                        esc_attr($prefix_class), esc_url($thumb), esc_attr($image_alt)
                                                    );
                                                }
                                            } else {
                                                $file_type = explode('.', $img_post_id);
                                                $file_type = end($file_type);
                                                if (!in_array('image/' . strtolower($file_type), $settings->get_params('upload_allow_images'))) {
                                                    if (strpos($img_post_id, '.mp4') || strpos($img_post_id, '.webm') || strpos($img_post_id, '.mov')) {
                                                        printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" href="%s"><video class="%sreviews-images reviews-videos" src="%s" >%s</video></a></div>',
                                                            esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($img_post_id),
                                                            esc_attr($prefix_class), esc_url($img_post_id), esc_attr($product_title)
                                                        );
                                                    } elseif (strpos($img_post_id, '.shopee.')) {
                                                        printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" href="%s"><img class="%sreviews-images" loading="lazy" src="%s" alt="%s"></a></div>',
                                                            esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($img_post_id),
                                                            esc_attr($prefix_class), esc_url($img_post_id), esc_attr($product_title)
                                                        );
                                                    } else {
                                                        printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" href="%s"><video class="%sreviews-images reviews-iframe" src="%s" >%s</video></a></div>',
                                                            esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($img_post_id),
                                                            esc_attr($prefix_class), esc_url($img_post_id), esc_attr($product_title)
                                                        );
                                                    }
                                                } else {
                                                    printf('<div class="%sreviews-images-wrap"><a data-image_index="%s" href="%s"><img class="%sreviews-images" loading="lazy" src="%s" alt="%s"></a></div>',
                                                        esc_attr($prefix_class), esc_attr($img_post_ids_k), esc_attr($img_post_id),
                                                        esc_attr($prefix_class), esc_url($img_post_id), esc_attr($product_title)
                                                    );
                                                }
                                            }
                                        }

                                    ?>
                                </div>
                            </div>
                            <?php
                        }
                        do_action('woocommerce_photo_reviews_' . $prefix . 'grid_item_before_main_content', $comment, $product);
                        ?>
                    </div>
                </div>

                <?php
                if ($show_product === 'on') {
                    $temp_product = $product;
                    /*Change global $product variable to the current language if the review is not from the current language because this affects the add-to-cart button and variation swatches from viwcpr-quickview-single-product-summary-html.php template*/
                    $product_id_by_lang = '';
                    if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
                        $current_language = apply_filters('wpml_current_language', null);
                        $product_id_by_lang = apply_filters(
                            'wpml_object_id', $product->get_id(), 'product', false, $current_language
                        );
                    } else if (class_exists('Polylang')) {
                        $current_language = pll_current_language('slug');
                        $product_id_by_lang = pll_get_post($product->get_id(), $current_language);
                    }
                    if ($product_id_by_lang && $product_id_by_lang != $product->get_id()) {
                        $product = wc_get_product($product_id_by_lang);
                    }
                    wc_get_template('viwcpr-quickview-single-product-summary-html.php',
                        array(
                            'is_shortcode' => true,
                            'comment' => $comment,
                            'product' => $product
                        ),
                        'woocommerce-photo-reviews' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
                        WOOCOMMERCE_PHOTO_REVIEWS_TEMPLATES);
                    /*Return global $product variable*/
                    $product = $temp_product;
                }
                ?>
            </div>
        </div>
        <?php

    }
    /*Return global $product variable after implementing reviews*/
    $product = $return_product;
}
if ($grid_class) {
    if (!$is_shortcode) {
        printf('<div class="wcpr-grid-overlay wcpr-hidden"></div>');
    }
    printf('</%s>', $grid_tag_html);
}
?>
