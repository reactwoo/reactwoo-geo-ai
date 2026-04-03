<?php
/**
 * Geo AI — wp-admin (top-level menu; summary on Geo Core dashboard).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo AI.
 */
class RWGA_Admin {

	/**
	 * Parent admin page slug.
	 */
	const MENU_PARENT = 'rwga-dashboard';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_dashboard_actions' ) );
		add_action( 'rwgc_dashboard_satellite_panels', array( __CLASS__, 'render_geo_core_summary_card' ) );
	}

	/**
	 * Summary card on Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		$lic = ! empty( $s['license_configured'] );
		$url = admin_url( 'admin.php?page=' . self::MENU_PARENT );
		?>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'ReactWoo API + product license live under the Geo AI menu — not in Geo Core Settings.', 'reactwoo-geo-ai' ); ?></p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'License / API', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php echo $lic ? esc_html__( 'Configured', 'reactwoo-geo-ai' ) : esc_html__( 'Not set', 'reactwoo-geo-ai' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Geo Core REST', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php echo ! empty( $s['rest_enabled'] ) ? esc_html__( 'Enabled', 'reactwoo-geo-ai' ) : esc_html__( 'Disabled', 'reactwoo-geo-ai' ); ?>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo AI', 'reactwoo-geo-ai' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="button"><?php esc_html_e( 'License & API', 'reactwoo-geo-ai' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Section navigation (Overview, License, Help).
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		$items = array(
			self::MENU_PARENT => __( 'Overview', 'reactwoo-geo-ai' ),
			'rwga-license'    => __( 'License & API', 'reactwoo-geo-ai' ),
			'rwga-help'       => __( 'Help', 'reactwoo-geo-ai' ),
		);
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo AI section navigation', 'reactwoo-geo-ai' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rwga-' ) === false && strpos( $hook, self::MENU_PARENT ) === false ) {
			return;
		}
		if ( defined( 'RWGC_URL' ) && defined( 'RWGC_VERSION' ) ) {
			wp_enqueue_style(
				'rwgc-admin',
				RWGC_URL . 'admin/css/admin.css',
				array(),
				RWGC_VERSION
			);
		}
		wp_enqueue_style(
			'rwga-admin',
			RWGA_URL . 'admin/css/rwga-admin.css',
			array(),
			RWGA_VERSION
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
		add_menu_page(
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-admin-generic',
			57
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Overview', 'reactwoo-geo-ai' ),
			__( 'Overview', 'reactwoo-geo-ai' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — License & API', 'reactwoo-geo-ai' ),
			__( 'License & API', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-license',
			array( __CLASS__, 'render_license_settings' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Help', 'reactwoo-geo-ai' ),
			__( 'Help', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-help',
			array( __CLASS__, 'render_help' )
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
		$rwgc_nav_current = self::MENU_PARENT;
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

	/**
	 * @return void
	 */
	public static function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwga-help';
		include RWGA_PATH . 'admin/views/help.php';
	}
}
