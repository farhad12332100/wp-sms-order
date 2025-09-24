<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_WOOCOMMERCE_PHOTO_REVIEWS_Process extends WP_Background_Process {

	/**
	 * @var string
	 */
	protected $action = 'wcpr_process';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		try {
			$comment_id = isset( $item['comment_id'] ) ? $item['comment_id'] : '';
			if ( $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( $comment ) {
					$settings                                                   = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
					VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::$product_id = $comment->comment_post_ID;
					if ( get_comment_meta( $comment_id, 'id_import_reviews_from_ali', true ) || get_comment_meta( $comment_id, 'id_import_reviews_from_amazon', true ) ) {
						$download_img_param = 'import_reviews_download_images';
						$download_video     = 'import_reviews_download_videos';
					} else {
						$download_img_param = 'import_csv_download_images';
						$download_video     = 'import_csv_download_videos';
					}
					$reviews_images = get_comment_meta( $comment_id, 'reviews-images', true );
					if ( is_array( $reviews_images ) && count( $reviews_images ) ) {
						$images = array();
						add_image_size( 'wcpr-photo-reviews', 500, 500 );
						add_filter( 'intermediate_image_sizes', array(
							'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
							'reduce_image_sizes'
						) );
						add_filter( 'upload_dir', array(
							'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
							'import_upload_folder'
						) );
						foreach ( $reviews_images as $key => $value ) {
							if ( villatheme_is_url( $value ) ) {
								$file_type = explode( '.', $value );
								$file_type = end( $file_type );
								if ( ! in_array( 'image/' . strtolower( $file_type ), $settings->get_params( 'upload_allow_images' ) ) && ! $settings->get_params( $download_video ) ) {
									$images[] = $value;
									continue;
								} elseif ( ! $settings->get_params( $download_img_param ) ) {
									$images[] = $value;
									continue;
								}
								$image_id = vi_wcpr_handle_sideload( $value, $comment_id, $comment->comment_post_ID );
								if ( ! is_wp_error( $image_id ) ) {
									$images[] = $image_id;
								} else {
									$images[] = $value;
									error_log( 'WooCommerce Photo Reviews error log - background download images: ' . $image_id->get_error_message() );
								}
							} else {
								$images[] = $value;
							}
						}
						remove_filter( 'intermediate_image_sizes', array(
							'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
							'reduce_image_sizes'
						) );
						remove_filter( 'upload_dir', array(
							'VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend',
							'import_upload_folder'
						) );
						if ( count( $images ) ) {
							update_comment_meta( $comment_id, 'reviews-images', $images );
						}
					}
				}
			}
		} catch ( Exception $e ) {
			error_log( 'WooCommerce Photo Reviews error log - background download images: ' . $e->getMessage() );

			return false;
		}

		return false;
	}

	/**
	 * Is the updater running?
	 *
	 * @return boolean
	 */
	public function is_downloading() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';


		return boolval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} LIKE %s", $key ) ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		// Show notice to user or perform some other arbitrary task...
		parent::complete();
	}

	/**
	 * Delete all batches.
	 *
	 * @return WP_WOOCOMMERCE_PHOTO_REVIEWS_Process
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Kill process.
	 *
	 * Stop processing queue items, clear cronjob and delete all batches.
	 */
	public function kill_process() {
		if ( ! $this->is_queue_empty() ) {
			$this->delete_all_batches();
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}
	}
}