<?php
/**
 * Function include all files in folder
 *
 * @param $path   Directory address
 * @param $ext    array file extension what will include
 * @param $prefix string Class prefix
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'vi_include_folder' ) ) {
	function vi_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {

		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );

							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class;
								}
							}
						}
					}
				}
			}
		}
	}
}
if ( ! function_exists( 'vi_wcpr_handle_sideload' ) ) {
	/**
	 * @param $url
	 * @param $comment_id
	 * @param $post_id
	 *
	 * @return int|string|WP_Error
	 */
	function vi_wcpr_handle_sideload( $url, $comment_id, $post_id ) {
		//add product image:
		add_filter( 'big_image_size_threshold', '__return_false', 999 );
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		}
		$parse_url = wp_parse_url( $url );
		$scheme    = empty( $parse_url['scheme'] ) ? 'http' : $parse_url['scheme'];
		$url       = "{$scheme}://{$parse_url['host']}{$parse_url['path']}";
		// Download file to temp location
		$tmp = download_url( $url );
		// Set variables for storage
		// fix file name for query strings
		preg_match( '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|bmp|BMP|webp|WEBP|mp4|MP4|mov|MOV|webm|WEBM)/', $url, $matches );
		$file_array['name']     = apply_filters( 'woocommerce_photo_reviews_image_file_name', basename( $matches[0] ), $comment_id, $post_id, true );
		$file_array['tmp_name'] = $tmp;
		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@wp_delete_file( $file_array['tmp_name'] );

			return $tmp;
		}
		//use media_handle_sideload to upload img:
		$thumbid = media_handle_sideload( $file_array, '' );
		// If error storing permanently, unlink
		if ( is_wp_error( $thumbid ) ) {
			@wp_delete_file( $file_array['tmp_name'] );
		}

		return $thumbid;
	}
}
if ( ! function_exists( 'woocommerce_version_check' ) ) {
	function woocommerce_version_check( $version = '3.0' ) {
		global $woocommerce;

		if ( version_compare( $woocommerce->version, $version, ">=" ) ) {
			return true;
		}

		return false;
	}
}
if ( ! function_exists( '_sort_priority_callback' ) ) {
	/**
	 * Sort Priority Callback Function
	 *
	 * @param array $a Comparison A.
	 * @param array $b Comparison B.
	 *
	 * @return bool
	 */
	function _sort_priority_callback( $a, $b ) {
		if ( ! isset( $a['priority'], $b['priority'] ) || $a['priority'] === $b['priority'] ) {
			return 0;
		}

		return ( $a['priority'] < $b['priority'] ) ? - 1 : 1;
	}
}
if ( ! function_exists( 'villatheme_array_flatten' ) ) {
	function villatheme_array_flatten( $params, $allow_empty = true ) {
		if ( ! is_array( $params ) ) {
			return ! $allow_empty && ! $params ? array() : array( $params );
		}
		$result = array();
		foreach ( $params as $val ) {
			if ( ! $allow_empty && ! $val ) {
				continue;
			}
			$result = array_merge( $result, villatheme_array_flatten( $val ) );
		}

		return $result;
	}
}
if ( ! function_exists( 'villatheme_sanitize_fields' ) ) {
	function villatheme_sanitize_fields( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'villatheme_sanitize_fields', $data );
		} else {
			return is_scalar( $data ) ? sanitize_text_field( wp_unslash( $data ) ) : $data;
		}
	}
}
if ( ! function_exists( 'villatheme_sanitize_kses' ) ) {
	function villatheme_sanitize_kses( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'villatheme_sanitize_kses', $data );
		} else {
			return is_scalar( $data ) ? wp_kses_post( wp_unslash( $data ) ) : $data;
		}
	}
}

if ( ! function_exists( 'viwcpr_set_time_limit' ) ) {
	function viwcpr_set_time_limit() {
		ini_set( 'max_execution_time', '3000' );
		ini_set( 'max_input_time', '3000' );
		ini_set( 'default_socket_timeout', '3000' );
		@set_time_limit( 0 );
	}
}
if ( ! function_exists( 'villatheme_remove_object_filter' ) ) {
	/**
	 * Remove an object filter.
	 *
	 * @param string $tag Hook name.
	 * @param string $class Class name. Use 'Closure' for anonymous functions.
	 * @param string|void $method Method name. Leave empty for anonymous functions.
	 * @param string|int|void $priority Priority
	 *
	 * @return void
	 */
	function villatheme_remove_object_filter( $tag, $class, $method = null, $priority = null ) {
		global $wp_filter;
		$filters = $wp_filter[ $tag ] ?? '';
		if ( empty ( $filters ) ) {
			return;
		}
		foreach ( $filters as $p => $filter ) {

			if ( ! is_null( $priority ) && ( (int) $priority !== (int) $p ) ) {
				continue;
			}
			$remove = false;
			foreach ( $filter as $identifier => $function ) {
				$function = $function['function'];
				if (
					is_array( $function )
					&& (
						is_a( $function[0], $class )
						|| ( is_array( $function ) && $function[0] === $class )
					)
				) {
					$remove = ( $method && ( $method === $function[1] ) );
				} elseif ( $function instanceof Closure && $class === 'Closure' ) {
					$remove = true;
				}
				if ( $remove ) {
					$temp = $wp_filter[ $tag ][ $p ];
					unset( $temp[ $identifier ] );
					$wp_filter[ $tag ][ $p ] = $temp;
				}
			}
		}
	}
}
if ( ! function_exists( 'villatheme_json_decode' ) ) {
    function villatheme_json_decode( $json, $assoc = true, $depth = 512, $options = 2 ) {
        if (is_array($json)){
            return $json;
        }
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $json = mb_convert_encoding( $json, 'UTF-8', 'UTF-8' );
        }
        return json_decode( is_string( $json ) ? $json : '{}', $assoc, $depth, $options );
    }
}
if ( ! function_exists( 'villatheme_json_encode' ) ) {
	function villatheme_json_encode( $value, $options = 256, $depth = 512 ) {
		return wp_json_encode( $value, $options, $depth );
	}
}
if ( ! function_exists( 'villatheme_is_equal' ) ) {
	function villatheme_is_equal( $arg1, $arg2 ) {
		if (is_array($arg1) && is_array($arg2)) {
			if ( count( $arg1 ) !== count( $arg2 ) || ! empty( array_diff( array_keys( $arg1 ), array_keys( $arg2 ) ) ) ) {
				return false;
			}
			$result = true;
			foreach ( $arg2 as $k => $v ) {
				if ( ! villatheme_is_equal( $v, $arg1[ $k ] ) ) {
					$result = false;
					break;
				}
			}
		}else{
			$result = $arg1 === $arg2;
		}
		return $result;
	}
}
if ( ! function_exists( 'villatheme_is_url' ) ) {
    function villatheme_is_url( $link ) {
        if (is_numeric($link)){
            return false;
        }
		if (esc_url_raw($link) === $link){
			return true;
		}
        return wc_is_valid_url($link);
    }
}