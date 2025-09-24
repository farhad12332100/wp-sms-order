<?php
if (!defined('ABSPATH')) {
    exit;
}
if (empty($comment_form) || empty($settings)) {
    return;
}
global $product;
$max = $settings->get_params('photo', 'maxsize');
$max_files = $settings->get_params('photo', 'maxfiles');
$multi_language = $settings->get_params('multi_language');
$language = VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language();
$upload_images_requirement = apply_filters('woocommerce_photo_reviews_upload_images_details', $settings->get_params('photo', 'upload_images_requirement', $language), $max, $max_files);
$upload_images_requirement = str_replace(array('{max_size}', '{max_files}'), array(
    $max . ' KB',
    $max_files
), $upload_images_requirement);
$upload_button_text = apply_filters('woocommerce_photo_reviews_upload_button_text', $settings->get_params('photo', 'upload_button_text', $language), $max, $max_files);
$upload_button_text = str_replace(array('{max_size}', '{max_files}'), array(
    $max . ' KB',
    $max_files
), $upload_button_text);
if ($settings->get_params('review_title_enable')) {
    printf('<div class="wcpr-comment-form-title"><input type="text" name="wcpr_review_title" placeholder="%s"></div>', wp_kses_post($settings->get_params('review_title_placeholder', '', $language)));
}
printf('%s', $comment_form['comment_field'] ?? '');
do_action('viwcpr_before_comment_fields');
if ($settings->get_params('custom_fields_enable')) {
    $custom_fields = apply_filters('woocommerce_photo_reviews_custom_fields_input', $settings->get_params('custom_fields'), $product, $language);
    if (is_array($custom_fields) && count($custom_fields)) {
        ?>
        <div class="wcpr-custom-fields">
            <?php
            foreach ($custom_fields as $custom_field) {
                if (empty($custom_field['name']) || ($multi_language && isset($custom_field['language']) && $language !== $custom_field['language'])) {
                    continue;
                }
                ?>
                <div class="wcpr-custom-field">
                    <div class="wcpr-custom-field-name"><?php echo wp_kses_post($custom_field['label'] ? $custom_field['label'] : $custom_field['name']); ?>
                        <input type="hidden" name="wcpr_custom_fields[name][]"
                               value="<?php echo esc_attr($custom_field['name']) ?>">
                    </div>
                    <?php
                    $has_unit = false;
                    if (is_array($custom_field['unit']) && count($custom_field['unit'])) {
                        $has_unit = true;
                    }
                    ?>
                    <div class="wcpr-custom-field-input <?php echo esc_attr($has_unit ? 'wcpr-custom-field-input-has-unit' : 'wcpr-custom-field-input-no-unit') ?>">
                        <div class="wcpr-custom-field-input-value">
                            <?php
                            if (is_array($custom_field['value']) && count($custom_field['value'])) {
                                ?>
                                <select name="wcpr_custom_fields[value][]">
                                    <?php
                                    if (empty($custom_field['placeholder'])) {
                                        ?>
                                        <option value=""><?php printf(esc_html__('Select %s', 'woocommerce-photo-reviews'), $custom_field['name']) ?></option>
                                        <?php
                                    } else {
                                        ?>
                                        <option value=""><?php echo esc_html($custom_field['placeholder']) ?></option>
                                        <?php
                                    }
                                    foreach ($custom_field['value'] as $custom_field_value_k => $custom_field_value_v) {
                                        ?>
                                        <option value="<?php echo esc_attr($custom_field_value_v) ?>"><?php echo wp_kses_post($custom_field_value_v) ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <?php
                            } else {
                                ?>
                                <input type="text" name="wcpr_custom_fields[value][]"
                                       placeholder="<?php echo esc_attr(isset($custom_field['placeholder']) ? esc_attr($custom_field['placeholder']) : '') ?>">
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                        if ($has_unit) {
                            ?>
                            <div class="wcpr-custom-field-input-unit">
                                <?php
                                if (count($custom_field['unit']) == 1) {
                                    printf('s', $custom_field['unit'][0]);
                                } else {
                                    ?>
                                    <select name="wcpr_custom_fields[unit][]">
                                        <?php
                                        foreach ($custom_field['unit'] as $custom_field_unit_k => $custom_field_unit_v) {
                                            ?>
                                            <option value="<?php echo esc_attr($custom_field_unit_v) ?>"><?php echo wp_kses_post($custom_field_unit_v) ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                    <?php
                                }
                                ?>
                            </div>
                            <?php
                        } else {
                            ?>
                            <input type="hidden" name="wcpr_custom_fields[unit][]">
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <?php
            }

            ?>
        </div>
        <?php
    }
}
if ('on' == $settings->get_params('photo', 'enable')) {
    $form_images_class = 'wcpr-comment-form-images';
    $form_images_class .= $settings->get_params('image_caption_enable') ? ' wcpr-comment-form-images-with-caption' : '';
    $button_upload_class = $settings->get_params('photo', 'upload_button_display_type');

    ?>
    <div class="<?php echo esc_attr($form_images_class) ?>">
        <label for="wcpr_image_upload">
            <?php
            echo wp_kses_post($upload_images_requirement);
            if ($settings->get_params('photo', 'required') == 'on') {
                ?>
                <span class="required">*</span>
                <?php
            }
            ?>
        </label>
        <div class="wcpr-input-file-container">
            <div class="wcpr-input-file-wrap">
                <button class="wcpr_image_upload_button <?php echo esc_attr($button_upload_class); ?>"
                        type="button"><?php echo wp_kses_post($upload_button_text); ?></button>
                <input type="file" name="wcpr_image_upload[]" id="wcpr_image_upload"
                       class="wcpr_image_upload wcpr-hidden" multiple
                       accept=".jpg, .jpeg, .png, .bmp, .webp, .gif, .mp4, .mov, .webm"/>
                <div class="wcpr-selected-image-container"></div>
            </div>
        </div>
    </div>
    <?php
}
if ($settings->get_params('photo', 'gdpr') === 'on') {
    printf('<p class="wcpr-gdpr-policy"><input type="checkbox" name="wcpr_gdpr_checkbox" id="wcpr_gdpr_checkbox"><label for="wcpr_gdpr_checkbox">%s</label></p>', wp_kses_post($settings->get_params('photo', 'gdpr_message', $language)));
}
do_action('viwcpr_after_comment_fields');
printf('<p  class="wcpr-comment-form-error-wraps wcpr-comment-form-error wcpr-hidden" role="alert"></p>');
printf('<p  class="wcpr-comment-form-notify-wraps wcpr-comment-form-notify wcpr-hidden" role="alert">%1s <span><span></span><span></span><span></span></span></p>', esc_html__('Your review is being submitted. Please wait a moment', 'woocommerce-photo-reviews'));
//do_action( 'litespeed_nonce', 'wcpr_image_upload' );
wp_nonce_field('wcpr_image_upload', 'wcpr_image_upload_nonce', false);
?>