<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class VI_WOOCOMMERCE_PHOTO_REVIEWS_Plugins_Elementor {
	public function __construct() {
		if ( ! is_plugin_active( 'elementor/elementor.php' ) ) {
			return;
		}
		add_action( 'init', array( $this, 'init_elements' ));
	}
	public function init_elements(){
		$hook = version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ? 'elementor/widgets/register':'elementor/widgets/widgets_registered';
		add_action( $hook, array($this, 'register'));
	}
	public function register(){
		$args = array(
			'WCPR_Elementor_Reviews_Widget'        => 'reviews-widget.php',
			'WCPR_Elementor_Review_Form_Widget'    => 'review-form-widget.php',
			'WCPR_Elementor_Rating_Widget'         => 'rating-widget.php',
			'WCPR_Elementor_Overall_Rating_Widget' => 'overall-rating-widget.php',
		);
		foreach ( $args as $k => $v ) {
			$file = WOOCOMMERCE_PHOTO_REVIEWS_PLUGINS . 'elementor' . DIRECTORY_SEPARATOR. $v ;
			if ( file_exists($file) ) {
				require_once( $file );
				$widget = new $k();
				if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
					Elementor\Plugin::instance()->widgets_manager->register( $widget );
				} else {
					Elementor\Plugin::instance()->widgets_manager->register_widget_type( $widget );
				}
			}
		}
	}
}