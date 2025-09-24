<?php

/**
 * Class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Status
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Status {
	protected $settings;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
	}

	public function admin_menu() {
		$manage_role = $this->settings->get_setting_capability();
		add_submenu_page(
			'woocommerce-photo-reviews',
			esc_html__( 'Status', 'woocommerce-photo-reviews' ),
			esc_html__( 'System Status', 'woocommerce-photo-reviews' ),
			$manage_role, 'kt-wcpr-status', array(
				$this,
				'status'
			)
		);
	}

	private static function get_status( $ok = true ) {
		if ( $ok ) {
			return '<span class="status-ok">OK</span>';
		} else {
			return '<span class="status-bad">X</span>';
		}
	}

	public function status() {
		$woocommerce_reviews = ( 'yes' === get_option( 'woocommerce_enable_reviews' ) );
		$page_comments       = get_option( 'page_comments' );
		$comments_per_page   = get_option( 'comments_per_page' );

		if ( ini_get( 'post_max_size' ) > ( absint( ini_get( 'upload_max_filesize' ) ) * ini_get( 'max_file_uploads' ) + 1 ) && ini_get( 'upload_max_filesize' ) > 0 && ini_get( 'max_file_uploads' ) > 0 ) {
			$size_status = self::get_status();
		} else {
			$size_status = self::get_status( false );
		}
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'System status', 'woocommerce-photo-reviews' ) ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php esc_html_e( 'File upload:', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php if ( ini_get( 'file_uploads' ) == 1 ) {
							esc_html_e( 'On', 'woocommerce-photo-reviews' );
						} else {
							esc_html_e( 'Off', 'woocommerce-photo-reviews' );
						} ?></td>
                    <td><?php echo wp_kses_post( self::get_status( ini_get( 'file_uploads' ) == 1 ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Upload max filesize:', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php echo esc_html( ini_get( 'upload_max_filesize' ) ); ?></td>
                    <td><?php echo wp_kses_post( $size_status ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Max file uploads:', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php esc_html_e( ini_get( 'max_file_uploads' ), 'woocommerce-photo-reviews' ); ?></td>
                    <td><?php echo esc_html( ini_get( 'max_file_uploads' ) ); ?></td>
                    <td><?php echo wp_kses_post( $size_status ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Post maxsize:', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php esc_html_e( ini_get( 'post_max_size' ), 'woocommerce-photo-reviews' ); ?></td>
                    <td><?php echo esc_html( ini_get( 'post_max_size' ) ); ?></td>
                    <td><?php echo wp_kses_post( $size_status ); ?></td>
                </tr>
                <tr>
                    <td colspan="3">
						<?php
						echo wp_kses_post( __( '<i>Post maxsize</i> should be greater than <i>Max file upload</i> plus <i>Upload max filesize</i>.', 'woocommerce-photo-reviews' ) )
						?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WooCommerce reviews', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php $woocommerce_reviews ? esc_html_e( 'Enabled', 'woocommerce-photo-reviews' ) : esc_html_e( 'Disabled', 'woocommerce-photo-reviews' ); ?></td>
                    <td>
						<?php
						if ( ! $woocommerce_reviews ) {
							?>
                            <a target="_blank"
                               href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=products#woocommerce_enable_reviews' ) ) ?>"><?php esc_html_e( 'Enable reviews', 'woocommerce-photo-reviews' ) ?></a>
							<?php
						}
						?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Paginate reviews', 'woocommerce-photo-reviews' ) ?></th>
                    <td><?php $page_comments ? printf( _n( '%s review per page', '%s reviews per page', $comments_per_page, 'woocommerce-photo-reviews' ), $comments_per_page ) : esc_html_e( 'No', 'woocommerce-photo-reviews' ); ?></td>
                    <td><?php /* translators: %s: review count */
						$page_comments ? printf( wp_kses_post( _n( '%s review per page', '%s reviews per page', $comments_per_page, 'woocommerce-photo-reviews' ) ), wp_kses_post( $comments_per_page  ) ) : esc_html_e( 'No', 'woocommerce-photo-reviews' ); ?></td>
                    <td>
                        <a target="_blank"
                           href="<?php echo esc_url( admin_url( 'options-discussion.php#page_comments' ) ) ?>"><?php esc_html_e( 'Configure', 'woocommerce-photo-reviews' ) ?></a>
                    </td>
                </tr>
            </table>
        </div>
		<?php
	}
}

