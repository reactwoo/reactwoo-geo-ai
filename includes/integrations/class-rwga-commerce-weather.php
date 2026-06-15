<?php
/**
 * Geo AI ↔ Geo Commerce weather facet suggestions on product editor.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product editor suggest button + REST wiring when Geo Commerce is active.
 */
class RWGA_Commerce_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce', false ) || ! class_exists( 'RWGCM_Weather_Affinity', false ) ) {
			return;
		}
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_suggest_button' ), 26 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_product_editor' ) );
	}

	/**
	 * @return void
	 */
	public static function render_suggest_button() {
		global $post;
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return;
		}
		echo '<p class="form-field rwgcm-product-weather-suggest">';
		echo '<button type="button" class="button" id="rwga-suggest-weather-facets" data-product-id="' . esc_attr( (string) (int) $post->ID ) . '">';
		esc_html_e( 'Suggest weather facets (Geo AI)', 'reactwoo-geo-ai' );
		echo '</button> ';
		echo '<span class="description" id="rwga-suggest-weather-facets-status"></span>';
		echo '</p>';
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_product_editor( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script(
			'rwga-commerce-weather',
			RWGA_URL . 'assets/js/commerce-weather-suggest.js',
			array( 'wp-api-fetch' ),
			RWGA_VERSION,
			true
		);
		wp_add_inline_script(
			'rwga-commerce-weather',
			'window.rwgaCommerceWeather = ' . wp_json_encode(
				array(
					'restUrl' => rest_url( 'geo-ai/v1/products/' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'i18n'    => array(
						'working' => __( 'Suggesting…', 'reactwoo-geo-ai' ),
						'applied' => __( 'Suggested facets applied — review and save the product.', 'reactwoo-geo-ai' ),
						'none'    => __( 'No strong keyword matches found.', 'reactwoo-geo-ai' ),
						'error'   => __( 'Could not fetch suggestions.', 'reactwoo-geo-ai' ),
					),
				)
			) . ';',
			'before'
		);
	}
}
