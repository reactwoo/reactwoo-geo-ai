<?php
/**
 * Geo AI — wp-admin (under Geo Core menu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo AI.
 */
class RWGA_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'handle_dashboard_actions' ) );
		add_filter( 'rwgc_inner_nav_items', array( __CLASS__, 'filter_inner_nav_items' ), 10, 1 );
	}

	/**
	 * Add Geo AI screens to the shared Geo Core inner nav (same style as Geo Elementor section nav).
	 *
	 * @param array $items Page slug => label.
	 * @return array
	 */
	public static function filter_inner_nav_items( $items ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		return array_merge(
			$items,
			array(
				'rwga-dashboard' => __( 'Geo AI', 'reactwoo-geo-ai' ),
				'rwga-license'   => __( 'Geo AI License', 'reactwoo-geo-ai' ),
			)
		);
	}

	/**
	 * Run Geo AI dashboard test actions (same API calls as Geo Core → Tools).
	 *
	 * @return void
	 */
	public static function handle_dashboard_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'rwga-dashboard' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['rwga_action'] ) || empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = isset( $_GET['rwga_action'] ) ? sanitize_key( wp_unslash( $_GET['rwga_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, array( 'ai_health', 'ai_usage', 'rest_post_smoke' ), true ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwga_dash_' . $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 'rest_post_smoke' !== $action && ! class_exists( 'RWGC_AI_Orchestrator', false ) ) {
			add_settings_error(
				'rwga_dashboard',
				'rwga_no_orchestrator',
				__( 'Geo Core AI orchestrator is not available.', 'reactwoo-geo-ai' ),
				'error'
			);
			return;
		}
		if ( 'ai_health' === $action ) {
			$result = RWGC_AI_Orchestrator::ai_health();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwga_dashboard', 'rwga_ai_health_err', $result->get_error_message(), 'error' );
			} else {
				$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
				$snippet = '';
				if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
					$snippet = wp_json_encode( $result['data'] );
					if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
						$snippet = substr( $snippet, 0, 280 ) . '…';
					}
				}
				add_settings_error(
					'rwga_dashboard',
					'rwga_ai_health_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: response JSON or note */
						__( 'AI service reachability: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
						(string) $code,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geo-ai' )
					),
					'updated'
				);
			}
			return;
		}
		if ( 'ai_usage' === $action ) {
			$result = RWGC_AI_Orchestrator::get_usage();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwga_dashboard', 'rwga_ai_usage_err', $result->get_error_message(), 'error' );
			} else {
				$http = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
				$body = isset( $result['body'] ) ? $result['body'] : null;
				if ( class_exists( 'RWGA_Usage', false ) && is_array( $body ) ) {
					RWGA_Usage::save_from_api_body( $body, $http );
				}
				$snippet = is_array( $body ) ? wp_json_encode( $body ) : '';
				if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
					$snippet = substr( $snippet, 0, 280 ) . '…';
				}
				add_settings_error(
					'rwga_dashboard',
					'rwga_ai_usage_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: API JSON or note */
						__( 'ReactWoo API (authenticated) assistant usage: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
						(string) $http,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geo-ai' )
					),
					'updated'
				);
			}
			return;
		}
		if ( 'rest_post_smoke' === $action ) {
			$summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
			if ( empty( $summary['rest_enabled'] ) ) {
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_off',
					__( 'Enable REST in Geo Core → Settings to register the variant-draft route.', 'reactwoo-geo-ai' ),
					'error'
				);
				return;
			}
			if ( ! current_user_can( 'edit_pages' ) ) {
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_cap',
					__( 'This check requires the edit_pages capability (same as the variant-draft REST route).', 'reactwoo-geo-ai' ),
					'error'
				);
				return;
			}
			if ( ! function_exists( 'rest_do_request' ) ) {
				require_once ABSPATH . 'wp-includes/rest-api.php';
			}
			$request = new \WP_REST_Request( 'POST', '/reactwoo-geocore/v1/ai/variant-draft' );
			$response = rest_do_request( $request );
			$code     = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 0;
			$data     = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : null;
			$err_msg  = '';
			if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$err_msg = $data['message'];
			}
			if ( 400 === $code ) {
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: optional REST message */
						__( 'Geo Core REST POST smoke test: HTTP %1$s (expected: missing page_id). %2$s', 'reactwoo-geo-ai' ),
						(string) $code,
						$err_msg ? $err_msg : __( 'Validation failed before any external AI call.', 'reactwoo-geo-ai' )
					),
					'updated'
				);
			} elseif ( 404 === $code ) {
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_404',
					__( 'Geo Core REST routes were not found. Confirm the plugin is active and REST is enabled.', 'reactwoo-geo-ai' ),
					'error'
				);
			} elseif ( 403 === $code ) {
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_403',
					__( 'REST returned HTTP 403 for this user. The variant-draft route requires edit_pages.', 'reactwoo-geo-ai' ),
					'error'
				);
			} else {
				$snippet = is_array( $data ) ? wp_json_encode( $data ) : '';
				if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
					$snippet = substr( $snippet, 0, 280 ) . '…';
				}
				add_settings_error(
					'rwga_dashboard',
					'rwga_rest_smoke_other',
					sprintf(
						/* translators: 1: HTTP status, 2: response snippet */
						__( 'Geo Core REST POST smoke test: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
						(string) $code,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geo-ai' )
					),
					'error'
				);
			}
		}
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'rwgc-dashboard',
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);
		add_submenu_page(
			'rwgc-dashboard',
			__( 'Geo AI License', 'reactwoo-geo-ai' ),
			__( 'Geo AI License', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-license',
			array( __CLASS__, 'render_license_settings' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$variant_draft_url = function_exists( 'rwgc_get_rest_v1_url' )
			? rwgc_get_rest_v1_url( 'ai/variant-draft' )
			: '';
		$rest_capabilities_url = function_exists( 'rwgc_get_rest_capabilities_url' )
			? rwgc_get_rest_capabilities_url()
			: '';
		$rest_location_url     = function_exists( 'rwgc_get_rest_location_url' )
			? rwgc_get_rest_location_url()
			: '';
		$rest_v1_base = '';
		$rwga_summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		if ( ! empty( $rwga_summary['rest_enabled'] ) && function_exists( 'rest_url' ) ) {
			$rest_v1_base = trailingslashit( rest_url( 'reactwoo-geocore/v1' ) );
			$rest_v1_base = (string) apply_filters( 'rwgc_rest_v1_url', $rest_v1_base, '' );
		}
		$rwga_stats   = class_exists( 'RWGA_Stats', false ) ? RWGA_Stats::get_snapshot() : array();
		$rwga_usage   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_display_rows() : array();
		$rwgc_nav_current = 'rwga-dashboard';
		include RWGA_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * ReactWoo API + product license (commercial satellite — not stored in Geo Core).
	 *
	 * @return void
	 */
	public static function render_license_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwga-license';
		include RWGA_PATH . 'admin/views/license-settings.php';
	}
}
