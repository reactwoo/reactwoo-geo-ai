<?php
/**
 * PHPUnit bootstrap for Geo AI builder tests (minimal WP stubs).
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGA_PATH', dirname( __DIR__ ) . '/' );
define( 'RWGA_VERSION', '0.4.78' );

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal post stub for builder tests.
	 */
	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_title;
		/** @var string */
		public $post_content;
		/** @var string */
		public $post_type;
	}
}

$GLOBALS['rwga_test_posts']      = array();
$GLOBALS['rwga_test_post_meta']  = array();

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		$post_id = (int) $post_id;
		return $GLOBALS['rwga_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		$all     = $GLOBALS['rwga_test_post_meta'][ $post_id ] ?? array();
		if ( '' === $key ) {
			return $all;
		}
		if ( ! isset( $all[ $key ] ) ) {
			return $single ? '' : array();
		}
		return $single ? $all[ $key ] : array( $all[ $key ] );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		$post = get_post( $post_id );
		return $post && isset( $post->post_title ) ? (string) $post->post_title : '';
	}
}

if ( ! function_exists( 'has_blocks' ) ) {
	function has_blocks( $content ) {
		return is_string( $content ) && str_contains( $content, '<!-- wp:' );
	}
}

if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( $content ) {
		if ( ! is_string( $content ) || ! has_blocks( $content ) ) {
			return array();
		}
		$blocks = array();
		if ( preg_match_all( '/<!-- wp:([a-z0-9\/-]+)(?:\s+(\{.*?\}))?\s+(?:\/)?-->/', $content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$attrs = array();
				if ( ! empty( $match[2] ) ) {
					$decoded = json_decode( $match[2], true );
					if ( is_array( $decoded ) ) {
						$attrs = $decoded;
					}
				}
				$name = explode( ' ', $match[1] )[0];
				$blocks[] = array(
					'blockName'   => str_contains( $name, '/' ) ? $name : 'core/' . $name,
					'attrs'       => $attrs,
					'innerHTML'   => '',
					'innerBlocks' => array(),
				);
			}
		}
		return $blocks;
	}
}

require_once RWGA_PATH . 'includes/helpers/rwga-builder-text.php';
require_once RWGA_PATH . 'includes/builders/class-rwga-builder-loader.php';
RWGA_Builder_Loader::load();
