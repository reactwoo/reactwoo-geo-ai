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
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_license_actions' ) );
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
			<p class="description"><?php esc_html_e( 'AI-assisted content drafts and variant workflows. License and usage are managed in Geo AI — not in Geo Core Settings.', 'reactwoo-geo-ai' ); ?></p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'License', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php echo $lic ? esc_html__( 'Configured', 'reactwoo-geo-ai' ) : esc_html__( 'Not set', 'reactwoo-geo-ai' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Site REST (Geo Core)', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php echo ! empty( $s['rest_enabled'] ) ? esc_html__( 'On', 'reactwoo-geo-ai' ) : esc_html__( 'Off', 'reactwoo-geo-ai' ); ?>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo AI', 'reactwoo-geo-ai' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="button"><?php esc_html_e( 'License', 'reactwoo-geo-ai' ); ?></a>
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
			'rwga-license'    => __( 'License', 'reactwoo-geo-ai' ),
			'rwga-drafts'     => __( 'Drafts / Queue', 'reactwoo-geo-ai' ),
			'rwga-advanced'   => __( 'Advanced', 'reactwoo-geo-ai' ),
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
	 * Cross-satellite links on Overview: Geo Core, Elementor, Commerce, Optimise when active (not Geo AI).
	 *
	 * @return void
	 */
	public static function render_suite_satellite_quick_links() {
		if ( ! class_exists( 'RWGC_Admin_UI', false ) ) {
			return;
		}
		$actions = array(
			array(
				'url'     => admin_url( 'admin.php?page=rwgc-dashboard' ),
				'label'   => __( 'Geo Core', 'reactwoo-geo-ai' ),
				'primary' => true,
			),
		);
		if ( self::is_geo_elementor_active() ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=geo-elementor' ),
				'label' => __( 'Geo Elementor', 'reactwoo-geo-ai' ),
			);
		}
		if ( RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' ) ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=rwgcm-dashboard' ),
				'label' => __( 'Geo Commerce', 'reactwoo-geo-ai' ),
			);
		}
		if ( RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ) ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=rwgo-dashboard' ),
				'label' => __( 'Geo Optimise', 'reactwoo-geo-ai' ),
			);
		}
		?>
		<div class="rwgc-card rwgc-card--highlight rwga-suite-satellite-links" role="region" aria-label="<?php echo esc_attr__( 'Other ReactWoo Geo plugins', 'reactwoo-geo-ai' ); ?>">
			<h2 class="rwga-suite-satellite-links__title"><?php esc_html_e( 'Geo suite', 'reactwoo-geo-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Open other ReactWoo Geo plugins when they are installed. Geo AI stays on this screen.', 'reactwoo-geo-ai' ); ?></p>
			<?php RWGC_Admin_UI::render_quick_actions( $actions ); ?>
		</div>
		<?php
	}

	/**
	 * @return bool
	 */
	private static function is_geo_elementor_active() {
		if ( ! class_exists( 'RWGC_Admin_UI', false ) ) {
			return false;
		}
		foreach ( array( 'geo-elementor/elementor-geo-popup.php', 'GeoElementor/elementor-geo-popup.php' ) as $file ) {
			if ( RWGC_Admin_UI::is_plugin_active( $file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rwga-' ) === false && strpos( $hook, self::MENU_PARENT ) === false ) {
			return;
		}
		$deps = array();
		if ( defined( 'RWGC_URL' ) && defined( 'RWGC_VERSION' ) ) {
			wp_enqueue_style(
				'rwgc-admin',
				RWGC_URL . 'admin/css/admin.css',
				array(),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-admin';
			wp_enqueue_style(
				'rwgc-suite',
				RWGC_URL . 'admin/css/rwgc-suite.css',
				array( 'rwgc-admin' ),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-suite';
		}
		wp_enqueue_style(
			'rwga-admin',
			RWGA_URL . 'admin/css/rwga-admin.css',
			$deps,
			RWGA_VERSION
		);
	}

	/**
	 * Run Geo AI checks from Overview or Advanced (connection tools).
	 *
	 * @return void
	 */
	public static function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( 'rwga-dashboard', 'rwga-advanced' ), true ) ) {
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
				'rwga_geo_ai',
				'rwga_no_orchestrator',
				__( 'Geo Core AI orchestrator is not available.', 'reactwoo-geo-ai' ),
				'error'
			);
			return;
		}
		if ( 'ai_health' === $action ) {
			$result = RWGC_AI_Orchestrator::ai_health();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwga_geo_ai', 'rwga_ai_health_err', $result->get_error_message(), 'error' );
			} else {
				$code    = isset( $result['code'] ) ? (int) $result['code'] : 0;
				$snippet = '';
				if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
					$snippet = wp_json_encode( $result['data'] );
					if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
						$snippet = substr( $snippet, 0, 280 ) . '…';
					}
				}
				add_settings_error(
					'rwga_geo_ai',
					'rwga_ai_health_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: response JSON or note */
						__( 'AI connection check: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
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
				add_settings_error( 'rwga_geo_ai', 'rwga_ai_usage_err', $result->get_error_message(), 'error' );
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
					'rwga_geo_ai',
					'rwga_ai_usage_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: API JSON or note */
						__( 'Usage refreshed: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
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
					'rwga_geo_ai',
					'rwga_rest_smoke_off',
					__( 'Turn on REST in Geo Core → Settings so the variant-draft route is registered.', 'reactwoo-geo-ai' ),
					'error'
				);
				return;
			}
			if ( ! current_user_can( 'edit_pages' ) ) {
				add_settings_error(
					'rwga_geo_ai',
					'rwga_rest_smoke_cap',
					__( 'This check needs the same permission as the variant-draft route (edit pages).', 'reactwoo-geo-ai' ),
					'error'
				);
				return;
			}
			if ( ! function_exists( 'rest_do_request' ) ) {
				require_once ABSPATH . 'wp-includes/rest-api.php';
			}
			$request  = new \WP_REST_Request( 'POST', '/reactwoo-geocore/v1/ai/variant-draft' );
			$response = rest_do_request( $request );
			$code     = is_object( $response ) && method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 0;
			$data     = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : null;
			$err_msg  = '';
			if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$err_msg = $data['message'];
			}
			if ( 400 === $code ) {
				add_settings_error(
					'rwga_geo_ai',
					'rwga_rest_smoke_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: optional REST message */
						__( 'Variant route validation: HTTP %1$s (expected without page_id). %2$s', 'reactwoo-geo-ai' ),
						(string) $code,
						$err_msg ? $err_msg : __( 'Stopped before any external AI call.', 'reactwoo-geo-ai' )
					),
					'updated'
				);
			} elseif ( 404 === $code ) {
				add_settings_error(
					'rwga_geo_ai',
					'rwga_rest_smoke_404',
					__( 'Geo Core REST routes were not found. Confirm the plugin is active and REST is enabled.', 'reactwoo-geo-ai' ),
					'error'
				);
			} elseif ( 403 === $code ) {
				add_settings_error(
					'rwga_geo_ai',
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
					'rwga_geo_ai',
					'rwga_rest_smoke_other',
					sprintf(
						/* translators: 1: HTTP status, 2: response snippet */
						__( 'Variant route check: HTTP %1$s. %2$s', 'reactwoo-geo-ai' ),
						(string) $code,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geo-ai' )
					),
					'error'
				);
			}
		}
	}

	/**
	 * License screen GET actions (disconnect).
	 *
	 * @return void
	 */
	public static function handle_license_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'rwga-license' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['rwga_action'] ) || 'clear_license' !== $_GET['rwga_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwga_clear_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( class_exists( 'RWGA_Settings', false ) ) {
			RWGA_Settings::clear_license_key();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwga-license&rwga_disconnected=1' ) );
		exit;
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
			__( 'Geo AI — License', 'reactwoo-geo-ai' ),
			__( 'License', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-license',
			array( __CLASS__, 'render_license_settings' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Drafts / Queue', 'reactwoo-geo-ai' ),
			__( 'Drafts / Queue', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-drafts',
			array( __CLASS__, 'render_drafts_queue' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Advanced', 'reactwoo-geo-ai' ),
			__( 'Advanced', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-advanced',
			array( __CLASS__, 'render_advanced' )
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
		$rwga_summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		$rwga_cache   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
		$rwga_queue_preview = class_exists( 'RWGA_Drafts', false ) ? RWGA_Drafts::get_queue_rows() : array();
		$rwga_queue_preview = is_array( $rwga_queue_preview ) ? array_slice( $rwga_queue_preview, 0, 5 ) : array();
		$rwgc_nav_current   = self::MENU_PARENT;
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

	/**
	 * Drafts / queue (UX shell; data via rwga_draft_queue_rows).
	 *
	 * @return void
	 */
	public static function render_drafts_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwga-drafts';
		$rwga_queue_rows  = class_exists( 'RWGA_Drafts', false ) ? RWGA_Drafts::get_queue_rows() : array();
		include RWGA_PATH . 'admin/views/drafts-queue.php';
	}

	/**
	 * Developer diagnostics, REST URLs, connection checks.
	 *
	 * @return void
	 */
	public static function render_advanced() {
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
		$rwgc_nav_current = 'rwga-advanced';
		include RWGA_PATH . 'admin/views/advanced.php';
	}
}
