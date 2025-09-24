<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Sms_settings {
    protected $settings;
    public function __construct() {
        $this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
        add_action( 'admin_footer', array( $this, 'render_sms_ui' ) );
        add_filter( 'pre_update_option__wcpr_nkt_setting_coupons', array( $this, 'merge_sms_into_coupons' ), 10, 2 );
    }

    public function merge_sms_into_coupons( $value, $old_value ) {
        if ( isset( $_POST['coupon_rules']['sms'] ) && is_array( $_POST['coupon_rules']['sms'] ) ) {
            $value['sms'] = villatheme_sanitize_kses( $_POST['coupon_rules']['sms'] );
        }
        // Handle multilingual sms_* keys if present
        if ( isset( $_POST['coupon_rules'] ) && is_array( $_POST['coupon_rules'] ) ) {
            foreach ( $_POST['coupon_rules'] as $k => $v ) {
                if ( strpos( $k, 'sms_' ) === 0 && is_array( $v ) ) {
                    $value[ $k ] = villatheme_sanitize_kses( $v );
                }
            }
        }
        return $value;
    }

    public function render_sms_ui() {
        if ( ! isset( $_REQUEST['page'] ) || sanitize_text_field( $_REQUEST['page'] ) !== 'woocommerce-photo-reviews' ) {
            return;
        }
        $coupon_ids = $this->settings->get_params( 'coupons', 'ids' );
        if ( empty( $coupon_ids ) || ! is_array( $coupon_ids ) ) {
            $coupon_ids = array( 'coupon_discount' );
        }
        ?>
        <script type="text/javascript">
            jQuery(function ($) {
                <?php foreach ( $coupon_ids as $rule_index => $rule_id ) :
                    $require      = $this->settings->get_current_setting( 'coupons', 'require', $rule_id, '', array() );
                    $cart_only    = isset( $require['cart_only'] ) ? intval( $require['cart_only'] ) : 0;
                    $sms_settings = $this->settings->get_current_setting( 'coupons', 'sms', $rule_id, '', array() );
                    $sms_enable   = isset( $sms_settings['enable'] ) ? intval( $sms_settings['enable'] ) : 0;
                    $sms_content  = isset( $sms_settings['content'] ) ? wp_kses_post( $sms_settings['content'] ) : '';
                ?>
                (function(){
                    var rule = $('.viwcpr-rule-wrap[data-rule_id="<?php echo esc_js( $rule_id ); ?>"]');
                    if (!rule.length) return;
                    // Insert "Use For Cart Only" toggle before Minimum required rating field
                    var minRating = rule.find('input[name="coupon_rules[require][<?php echo esc_js( $rule_id ); ?>][min_rating]"]').first();
                    if (minRating.length && !rule.find('.viwcpr-cart-only-toggle').length) {
                        var cartHtml = ''+
                            '<div class="field viwcpr-cart-only-toggle">'+
                            '  <label><?php echo esc_js( __( 'Use For Cart Only', 'woocommerce-photo-reviews' ) ); ?></label>'+
                            '  <div class="vi-ui toggle checkbox">'+
                            '    <input type="hidden" name="coupon_rules[require][<?php echo esc_js( $rule_id ); ?>][cart_only]" value="<?php echo esc_attr( $cart_only ); ?>">'+
                            '    <input type="checkbox" '+(<?php echo $cart_only ? 'true' : 'false'; ?> ? 'checked' : '')+'><label></label>'+
                            '  </div>'+
                            '  <p class="description"><?php echo esc_js( __( 'Send only 1 coupon per order when a customer reviews any product from that order.', 'woocommerce-photo-reviews' ) ); ?></p>'+
                            '</div>';
                        minRating.closest('.field').before(cartHtml);
                    }

                    // Insert SMS block inside Email section (after Email content)
                    var emailContentArea = rule.find('#coupon_rules--email--<?php echo esc_js( $rule_id ); ?>--content').closest('.field');
                    if (emailContentArea.length && !rule.find('.viwcpr-sms-block').length) {
                        var smsHtml = ''+
                            '<div class="field viwcpr-sms-block">'+
                            '   <label><?php echo esc_js( __( 'SMS', 'woocommerce-photo-reviews' ) ); ?></label>'+
                            '   <div class="vi-ui toggle checkbox">'+
                            '       <input type="hidden" name="coupon_rules[sms][<?php echo esc_js( $rule_id ); ?>][enable]" value="<?php echo esc_attr( $sms_enable ); ?>">'+
                            '       <input type="checkbox" '+(<?php echo $sms_enable ? 'true' : 'false'; ?> ? 'checked' : '')+'><label><?php echo esc_js( __( 'Enable SMS', 'woocommerce-photo-reviews' ) ); ?></label>'+
                            '   </div>'+
                            '   <p><?php echo esc_js( __( 'SMS content', 'woocommerce-photo-reviews' ) ); ?></p>'+
                            '   <textarea style="width:100%;min-height:140px;" name="coupon_rules[sms][<?php echo esc_js( $rule_id ); ?>][content]">'+<?php echo wp_json_encode( $sms_content ); ?>+'</textarea>'+
                            '   <ul style="margin-top:8px">'+
                            '       <li><?php echo esc_js( __( "{customer_name} - Customer's name.", 'woocommerce-photo-reviews' ) ); ?></li>'+
                            '       <li><?php echo esc_js( __( '{coupon_code} - Discount coupon code will be sent to customer.', 'woocommerce-photo-reviews' ) ); ?></li>'+
                            '       <li><?php echo esc_js( __( '{date_expires} - Expiry date of the coupon.', 'woocommerce-photo-reviews' ) ); ?></li>'+
                            '       <li><?php echo esc_js( __( '{last_valid_date} - The last day that the coupon is valid to use.', 'woocommerce-photo-reviews' ) ); ?></li>'+
                            '   </ul>'+
                            '</div>';
                        emailContentArea.after(smsHtml);
                    }

                    // Sync toggle checkboxes with hidden inputs (same behavior as existing form)
                    rule.find('.viwcpr-cart-only-toggle .vi-ui.toggle.checkbox input[type="checkbox"]').on('change', function(){
                        var hidden = $(this).closest('.vi-ui.toggle.checkbox').find('input[type="hidden"]').first();
                        hidden.val($(this).is(':checked') ? 1 : 0);
                    });
                    rule.find('.viwcpr-sms-block .vi-ui.toggle.checkbox input[type="checkbox"]').on('change', function(){
                        var hidden = $(this).closest('.vi-ui.toggle.checkbox').find('input[type="hidden"]').first();
                        hidden.val($(this).is(':checked') ? 1 : 0);
                    });
                })();
                <?php endforeach; ?>
            });
        </script>
        <?php
    }
}

new VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Sms_settings();
