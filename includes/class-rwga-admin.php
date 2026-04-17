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
		add_action( 'admin_post_rwga_sample_ux', array( __CLASS__, 'handle_sample_ux' ) );
		add_action( 'admin_post_rwga_recommend_ux', array( __CLASS__, 'handle_recommend_ux' ) );
		add_action( 'admin_post_rwga_generate_recommendation_report', array( __CLASS__, 'handle_generate_recommendation_report' ) );
		add_action( 'admin_post_rwga_start_workflow', array( __CLASS__, 'handle_start_workflow' ) );
		add_action( 'admin_post_rwga_copy_implement', array( __CLASS__, 'handle_copy_implement' ) );
		add_action( 'admin_post_rwga_seo_implement', array( __CLASS__, 'handle_seo_implement' ) );
		add_action( 'admin_post_rwga_competitor_research', array( __CLASS__, 'handle_competitor_research' ) );
		add_action( 'admin_post_rwga_automation_rule_save', array( __CLASS__, 'handle_automation_rule_save' ) );
		add_action( 'admin_post_rwga_automation_rule_run', array( __CLASS__, 'handle_automation_rule_run' ) );
		add_action( 'admin_post_rwga_automation_rule_delete', array( __CLASS__, 'handle_automation_rule_delete' ) );
		add_action( 'admin_post_rwga_analysis_delete', array( __CLASS__, 'handle_analysis_delete' ) );
		add_action( 'admin_post_rwga_bulk_implement_analysis', array( __CLASS__, 'handle_bulk_implement_analysis' ) );
		add_action( 'admin_post_rwga_recommendation_delete', array( __CLASS__, 'handle_recommendation_delete' ) );
		add_action( 'admin_post_rwga_draft_delete', array( __CLASS__, 'handle_draft_delete' ) );
		add_action( 'admin_post_rwga_set_implementation_route', array( __CLASS__, 'handle_set_implementation_route' ) );
		add_action( 'admin_post_rwga_apply_drafts_to_live', array( __CLASS__, 'handle_apply_drafts_to_live' ) );
		add_action( 'admin_post_rwga_create_variant_from_drafts', array( __CLASS__, 'handle_create_variant_from_drafts' ) );
		add_action( 'admin_post_rwga_send_variant_to_geo_optimise', array( __CLASS__, 'handle_send_variant_to_geo_optimise' ) );
		add_action( 'admin_post_rwga_clear_geo_ai_license', array( __CLASS__, 'handle_clear_license_post' ) );
		add_action( 'rwgc_dashboard_satellite_panels', array( __CLASS__, 'render_geo_core_summary_card' ) );
	}

	/**
	 * Summary card on Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}
		$s = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		$lic = ! empty( $s['license_configured'] );
		$rest_on = ! empty( $s['rest_enabled'] );
		$url = admin_url( 'admin.php?page=' . self::MENU_PARENT );
		?>
		<div class="rwgc-addon-card">
			<div class="rwgc-addon-card__header">
				<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-star-filled"></span></div>
				<div class="rwgc-addon-card__heading">
					<h3><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h3>
					<p><?php esc_html_e( 'Generate and manage geo-based content variants. Licensing and usage are handled in Geo AI.', 'reactwoo-geo-ai' ); ?></p>
				</div>
			</div>
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
			<div class="rwgc-addon-card__meta">
				<?php
				RWGC_Admin_UI::render_pill(
					$lic ? __( 'License: Configured', 'reactwoo-geo-ai' ) : __( 'License: Not set', 'reactwoo-geo-ai' ),
					$lic ? 'success' : 'danger'
				);
				RWGC_Admin_UI::render_pill(
					$rest_on ? __( 'REST: On', 'reactwoo-geo-ai' ) : __( 'REST: Off', 'reactwoo-geo-ai' ),
					$rest_on ? 'success' : 'danger'
				);
				?>
			</div>
			<?php endif; ?>
			<div class="rwgc-addon-card__actions">
				<a href="<?php echo esc_url( $url ); ?>" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Open Geo AI', 'reactwoo-geo-ai' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Settings', 'reactwoo-geo-ai' ); ?></a>
			</div>
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
			self::MENU_PARENT            => __( 'Start', 'reactwoo-geo-ai' ),
			'rwga-analyses'              => __( 'Reports', 'reactwoo-geo-ai' ),
			'rwga-recommendations'       => __( 'Recommendations', 'reactwoo-geo-ai' ),
			'rwga-implementation-drafts' => __( 'Drafts', 'reactwoo-geo-ai' ),
			'rwga-competitors'           => __( 'Competitors', 'reactwoo-geo-ai' ),
			'rwga-automation'            => __( 'Automation', 'reactwoo-geo-ai' ),
			'rwga-license'               => __( 'Settings', 'reactwoo-geo-ai' ),
			'rwga-drafts'                => __( 'Queue', 'reactwoo-geo-ai' ),
			'rwga-advanced'              => __( 'Advanced', 'reactwoo-geo-ai' ),
			'rwga-help'                  => __( 'Help', 'reactwoo-geo-ai' ),
		);
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo AI section navigation', 'reactwoo-geo-ai' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Render current workflow state panel.
	 *
	 * @return void
	 */
	public static function render_current_workflow_state() {
		if ( ! class_exists( 'RWGA_Current_Workflow', false ) ) {
			return;
		}
		$state = RWGA_Current_Workflow::get();
		if ( empty( $state ) ) {
			return;
		}
		$asset_id = isset( $state['asset_id'] ) ? (int) $state['asset_id'] : 0;
		$asset    = $asset_id > 0 ? get_the_title( $asset_id ) : __( 'Unknown', 'reactwoo-geo-ai' );
		$step     = isset( $state['journey_step'] ) ? (string) $state['journey_step'] : 'started';
		$analysis = isset( $state['analysis_run_id'] ) ? (int) $state['analysis_run_id'] : 0;
		$cta_url  = $analysis > 0 && class_exists( 'RWGA_Journey_Router', false ) ? RWGA_Journey_Router::analysis_detail_url( $analysis ) : admin_url( 'admin.php?page=' . self::MENU_PARENT );
		$cta_lab  = __( 'Continue workflow', 'reactwoo-geo-ai' );
		if ( in_array( $step, array( 'analysis_complete', 'recommendations_selected' ), true ) && class_exists( 'RWGA_Journey_Router', false ) ) {
			$cta_url = RWGA_Journey_Router::recommendation_report_url( $analysis );
			$cta_lab = __( 'Continue to recommendations', 'reactwoo-geo-ai' );
		} elseif ( in_array( $step, array( 'recommendation_report_ready', 'implementation_generated' ), true ) && class_exists( 'RWGA_Journey_Router', false ) ) {
			$draft_ids = isset( $state['draft_ids'] ) && is_array( $state['draft_ids'] ) ? $state['draft_ids'] : array();
			$cta_url   = RWGA_Journey_Router::implementation_review_url( $draft_ids, $analysis );
			$cta_lab   = __( 'Continue to implementation', 'reactwoo-geo-ai' );
		}
		echo '<div class="rwgc-card rwga-workflow-state">';
		echo '<strong>' . esc_html__( 'Current workflow', 'reactwoo-geo-ai' ) . '</strong>';
		echo '<p>' . esc_html__( 'Asset:', 'reactwoo-geo-ai' ) . ' ' . esc_html( (string) $asset ) . '</p>';
		echo '<p>' . esc_html__( 'Analysis:', 'reactwoo-geo-ai' ) . ' ' . esc_html( $analysis > 0 ? (string) $analysis : __( 'Not started', 'reactwoo-geo-ai' ) ) . '</p>';
		echo '<p>' . esc_html__( 'Step:', 'reactwoo-geo-ai' ) . ' ' . esc_html( str_replace( '_', ' ', $step ) ) . '</p>';
		echo '<p><a class="rwgc-btn rwgc-btn--primary" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_lab ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Notices after redirect from “Refresh usage” (`?rwga_usage=…`).
	 *
	 * @return void
	 */
	public static function render_usage_refresh_notices() {
		if ( empty( $_GET['rwga_usage'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$flag = sanitize_key( wp_unslash( $_GET['rwga_usage'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$uid  = get_current_user_id();
		$tkey = 'rwga_usage_flash_' . $uid;

		if ( 'ok' === $flag ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Usage and plan information were refreshed.', 'reactwoo-geo-ai' ) . '</p></div>';
			return;
		}
		if ( 'no_license' === $flag ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Save a Geo AI license key before refreshing usage.', 'reactwoo-geo-ai' ) . '</p></div>';
			return;
		}
		if ( 'parse' === $flag ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Usage response could not be read. Your plan may still be active — try again or check your ReactWoo account.', 'reactwoo-geo-ai' ) . '</p></div>';
			return;
		}
		if ( 'api_err' === $flag ) {
			$msg = get_transient( $tkey );
			delete_transient( $tkey );
			if ( ! is_string( $msg ) || '' === $msg ) {
				$msg = __( 'Could not refresh usage from the API.', 'reactwoo-geo-ai' );
			}
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
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
	 * Context banner when opened from Geo Suite workflow (GET handoff params from Geo Core).
	 *
	 * @return void
	 */
	public static function render_suite_handoff_panel() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_suite_handoff_request_context' ) ) {
			return;
		}
		$ctx = rwgc_get_suite_handoff_request_context();
		if ( empty( $ctx['active'] ) ) {
			return;
		}
		$clean_url = remove_query_arg( array( 'rwgc_handoff', 'rwgc_from', 'rwgc_launcher', 'rwgc_variant_page_id' ) );
		$launcher_labels = array(
			'ai_draft'       => __( 'Generate a localised draft (AI)', 'reactwoo-geo-ai' ),
			'create_variant' => __( 'Create page version', 'reactwoo-geo-ai' ),
			'experiment'     => __( 'Split test', 'reactwoo-geo-ai' ),
			'commerce_rule'  => __( 'Commerce rule', 'reactwoo-geo-ai' ),
		);
		$launcher = isset( $ctx['launcher'] ) ? (string) $ctx['launcher'] : '';
		$launcher_note = '';
		if ( '' !== $launcher ) {
			$launcher_note = isset( $launcher_labels[ $launcher ] ) ? $launcher_labels[ $launcher ] : $launcher;
		}
		$vid = isset( $ctx['variant_page_id'] ) ? (int) $ctx['variant_page_id'] : 0;
		$page = null;
		if ( $vid > 0 ) {
			$p = get_post( $vid );
			if ( $p instanceof \WP_Post && 'page' === $p->post_type && current_user_can( 'edit_page', $p->ID ) ) {
				$page = $p;
			}
		}
		?>
		<div class="rwgc-card rwgc-card--highlight rwga-suite-handoff" role="region" aria-label="<?php echo esc_attr__( 'Geo Suite handoff', 'reactwoo-geo-ai' ); ?>">
			<h2><?php esc_html_e( 'Opened from Geo Suite', 'reactwoo-geo-ai' ); ?></h2>
			<p class="description">
				<?php
				if ( isset( $ctx['from'] ) && 'suite' === $ctx['from'] ) {
					esc_html_e( 'You arrived from Suite Home or Getting Started.', 'reactwoo-geo-ai' );
				} else {
					esc_html_e( 'Geo Core linked you here to continue your workflow.', 'reactwoo-geo-ai' );
				}
				if ( '' !== $launcher_note ) {
					echo ' ';
					echo esc_html(
						sprintf(
							/* translators: %s: workflow label */
							__( 'Workflow: %s', 'reactwoo-geo-ai' ),
							$launcher_note
						)
					);
				}
				?>
			</p>
			<?php if ( $page instanceof \WP_Post ) : ?>
				<p>
					<strong><?php echo esc_html( get_the_title( $page ) ); ?></strong>
					<?php
					$edit_url = get_edit_post_link( $page->ID, 'raw' );
					if ( is_string( $edit_url ) && '' !== $edit_url ) {
						echo ' ';
						echo '<a class="button button-primary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Open in editor', 'reactwoo-geo-ai' ) . '</a>';
					}
					?>
				</p>
				<p class="description"><?php esc_html_e( 'Use the Geo AI tools in the block sidebar to run variant drafts for this page.', 'reactwoo-geo-ai' ); ?></p>
			<?php elseif ( $vid > 0 ) : ?>
				<p class="description"><?php esc_html_e( 'The linked page could not be loaded or you do not have permission to edit it.', 'reactwoo-geo-ai' ); ?></p>
			<?php endif; ?>
			<p><a class="button-link" href="<?php echo esc_url( $clean_url ); ?>"><?php esc_html_e( 'Dismiss banner', 'reactwoo-geo-ai' ); ?></a></p>
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
		if ( ! is_admin() || ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['rwga_action'] ) || empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = isset( $_GET['rwga_action'] ) ? sanitize_key( wp_unslash( $_GET['rwga_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed_pages = array( 'rwga-dashboard', 'rwga-advanced' );
		if ( 'ai_usage' === $action ) {
			$allowed_pages[] = 'rwga-license';
		}
		if ( ! in_array( $page, $allowed_pages, true ) ) {
			return;
		}
		if ( ! in_array( $action, array( 'ai_health', 'ai_usage', 'rest_post_smoke' ), true ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwga_dash_' . $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 'rest_post_smoke' !== $action && ! class_exists( 'RWGA_Platform_Client', false ) ) {
			add_settings_error(
				'rwga_geo_ai',
				'rwga_no_platform_client',
				__( 'Geo AI platform client is not available.', 'reactwoo-geo-ai' ),
				'error'
			);
			return;
		}
		if ( 'ai_health' === $action ) {
			$result = RWGA_Platform_Client::ai_health();
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
			$flag = 'err';
			if ( ! class_exists( 'RWGA_Settings', false ) || ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
				if ( class_exists( 'RWGA_License_State', false ) ) {
					RWGA_License_State::clear_all( 'ai_usage_no_license' );
				} else {
					delete_option( 'rwga_assistant_usage_cache' );
				}
				$flag = 'no_license';
			} else {
				if ( class_exists( 'RWGA_License_State', false ) ) {
					RWGA_License_State::log_debug( 'ai_usage_refresh_before', RWGA_License_State::get_snapshot() );
				}
				if ( class_exists( 'RWGA_Platform_Client', false ) ) {
					RWGA_Platform_Client::clear_token_cache();
				}
				$result = RWGA_Platform_Client::get_usage();
				if ( is_wp_error( $result ) ) {
					if ( class_exists( 'RWGA_License_State', false ) ) {
						RWGA_License_State::log_debug( 'ai_usage_api_error', array( 'message' => $result->get_error_message() ) );
					}
					set_transient( 'rwga_usage_flash_' . get_current_user_id(), $result->get_error_message(), 120 );
					$flag = 'api_err';
				} else {
					$http  = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
					$body  = isset( $result['body'] ) ? $result['body'] : null;
					if ( class_exists( 'RWGA_License_State', false ) ) {
						RWGA_License_State::log_debug(
							'ai_usage_remote_body_summary',
							array(
								'http' => $http,
								'keys' => is_array( $body ) ? array_slice( array_keys( $body ), 0, 12 ) : array(),
							)
						);
					}
					$saved = false;
					if ( class_exists( 'RWGA_Usage', false ) && is_array( $body ) ) {
						$saved = RWGA_Usage::save_from_api_body( $body, $http );
					}
					if ( class_exists( 'RWGA_License_State', false ) ) {
						RWGA_License_State::log_debug( 'ai_usage_refresh_after', RWGA_License_State::get_snapshot() );
					}
					if ( $saved ) {
						$flag = 'ok';
					} elseif ( is_array( $body ) ) {
						$flag = 'parse';
					} else {
						$flag = 'parse';
					}
					if ( in_array( $flag, array( 'ok', 'parse' ), true ) && class_exists( 'RWGA_Settings', false ) ) {
						RWGA_Settings::bust_plugin_update_check_cache();
					}
					if ( in_array( $flag, array( 'ok', 'parse' ), true ) && class_exists( 'RWGA_Updates_Diagnostics', false ) ) {
						RWGA_Updates_Diagnostics::maybe_clear_stale_no_bearer_record();
					}
				}
			}
			wp_safe_redirect( add_query_arg( 'rwga_usage', $flag, admin_url( 'admin.php?page=' . $page ) ) );
			exit;
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
	 * Disconnect license (POST from Settings — preferred over GET for a single reliable clear).
	 *
	 * @return void
	 */
	public static function handle_clear_license_post() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to disconnect the license.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_clear_license' );
		if ( class_exists( 'RWGA_Settings', false ) ) {
			RWGA_Settings::clear_license_key();
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		$redirect = isset( $_POST['rwga_disconnect_redirect'] ) ? sanitize_key( wp_unslash( $_POST['rwga_disconnect_redirect'] ) ) : 'license'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$target   = 'advanced' === $redirect
			? 'admin.php?page=rwga-advanced&rwga_disconnected=1'
			: 'admin.php?page=rwga-license&rwga_disconnected=1';
		wp_safe_redirect( admin_url( $target ) );
		exit;
	}

	/**
	 * License screen GET actions (disconnect, import).
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
		if ( empty( $_GET['rwga_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['rwga_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'clear_license' === $action ) {
			if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwga_clear_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			if ( class_exists( 'RWGA_Settings', false ) ) {
				RWGA_Settings::clear_license_key();
			}
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-license&rwga_disconnected=1' ) );
			exit;
		}
		if ( 'import_license' !== $action ) {
			return;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwga_import_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::import_license_from_source( $source ) : new WP_Error( 'rwga_missing_settings', __( 'Geo AI settings are not available.', 'reactwoo-geo-ai' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'            => 'rwga-license',
						'rwga_import_err' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwga-license&rwga_imported=' . rawurlencode( $source ) ) );
		exit;
	}

	/**
	 * Run a bounded sample UX analysis (foundation / stub engine).
	 *
	 * @return void
	 */
	public static function handle_sample_ux() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_sample_ux' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&rwga_sample=unlicensed' ) );
			exit;
		}

		$page_id = isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $page_id <= 0 ) {
			$front = (int) get_option( 'page_on_front' );
			$page_id = $front > 0 ? $front : 0;
		}
		if ( $page_id <= 0 ) {
			$ids = get_posts(
				array(
					'post_type'              => 'page',
					'post_status'            => 'publish',
					'numberposts'            => 1,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( is_array( $ids ) && ! empty( $ids[0] ) ) {
				$page_id = (int) $ids[0];
			}
		}

		if ( $page_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&rwga_sample=nopage' ) );
			exit;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'ux_analysis' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&rwga_sample=noflow' ) );
			exit;
		}

		$focus = isset( $_POST['analysis_focus'] ) ? sanitize_key( wp_unslash( $_POST['analysis_focus'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$exec  = array(
			'page_id'     => $page_id,
			'page_type'   => 'page',
			'device_type' => 'desktop',
		);
		if ( '' !== $focus ) {
			$exec['analysis_focus'] = $focus;
		}

		$out = $wf->execute( $exec );

		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_PARENT,
						'rwga_sample' => 'error',
						'rwga_err'    => rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$run_id = isset( $out['analysis_run_id'] ) ? (int) $out['analysis_run_id'] : 0;
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::update(
				array(
					'asset_type'       => 'page',
					'asset_id'         => $page_id,
					'analysis_run_id'  => $run_id,
					'selected_categories' => array(),
				)
			);
			RWGA_Current_Workflow::set_step( 'analysis_complete' );
		}
		$url = class_exists( 'RWGA_Journey_Router', false ) ? RWGA_Journey_Router::analysis_detail_url( $run_id ) : admin_url( 'admin.php?page=rwga-analyses&run_id=' . $run_id );
		$url = add_query_arg( 'rwga_sample', 'ok', $url );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Generate UX recommendations from a stored analysis run.
	 *
	 * @return void
	 */
	public static function handle_recommend_ux() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_recommend_ux' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&rwga_rec=unlicensed' ) );
			exit;
		}

		$analysis_run_id = isset( $_POST['analysis_run_id'] ) ? (int) wp_unslash( $_POST['analysis_run_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $analysis_run_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-analyses&rwga_rec=bad' ) );
			exit;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'ux_recommend' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-analyses&run_id=' . $analysis_run_id . '&rwga_rec=noflow' ) );
			exit;
		}

		$goal = isset( $_POST['business_goal'] ) ? sanitize_text_field( wp_unslash( $_POST['business_goal'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cats = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['categories'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$out = $wf->execute(
			array(
				'analysis_run_id' => $analysis_run_id,
				'business_goal'   => $goal,
				'selected_categories' => $cats,
			)
		);

		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'rwga-analyses',
						'run_id'  => $analysis_run_id,
						'rwga_rec'=> 'error',
						'rwga_err'=> rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$count = isset( $out['recommendation_ids'] ) && is_array( $out['recommendation_ids'] ) ? count( $out['recommendation_ids'] ) : 0;
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::update(
				array(
					'analysis_run_id'      => $analysis_run_id,
					'recommendation_ids'   => isset( $out['recommendation_ids'] ) && is_array( $out['recommendation_ids'] ) ? $out['recommendation_ids'] : array(),
					'selected_categories'  => $cats,
				)
			);
			RWGA_Current_Workflow::set_step( 'recommendation_report_ready' );
		}
		$url = class_exists( 'RWGA_Journey_Router', false )
			? RWGA_Journey_Router::recommendation_report_url( $analysis_run_id )
			: admin_url( 'admin.php?page=rwga-recommendations&analysis_run=' . (int) $analysis_run_id . '&view=report&journey=1' );
		$url = add_query_arg( array( 'rwga_rec' => 'ok', 'rwga_rec_count' => (int) $count ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Alias action for recommendation report generation.
	 *
	 * @return void
	 */
	public static function handle_generate_recommendation_report() {
		self::handle_recommend_ux();
	}

	/**
	 * Delete one analysis run with related findings/recommendations/drafts.
	 *
	 * @return void
	 */
	public static function handle_analysis_delete() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to delete analyses.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_analysis_delete' );

		$run_id = isset( $_POST['run_id'] ) ? (int) wp_unslash( $_POST['run_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $run_id <= 0 || ! class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-analyses&rwga_analysis=bad' ) );
			exit;
		}

		$rec_ids = array();
		if ( class_exists( 'RWGA_DB_Recommendations', false ) ) {
			$recs = RWGA_DB_Recommendations::list_for_analysis( $run_id );
			if ( is_array( $recs ) ) {
				foreach ( $recs as $r ) {
					if ( is_array( $r ) && ! empty( $r['id'] ) ) {
						$rec_ids[] = (int) $r['id'];
					}
				}
			}
		}
		if ( class_exists( 'RWGA_DB_Implementation_Drafts', false ) && ! empty( $rec_ids ) ) {
			RWGA_DB_Implementation_Drafts::delete_for_recommendations( $rec_ids );
		}
		if ( class_exists( 'RWGA_DB_Recommendations', false ) ) {
			RWGA_DB_Recommendations::delete_for_analysis( $run_id );
		}
		if ( class_exists( 'RWGA_DB_Analysis_Findings', false ) ) {
			RWGA_DB_Analysis_Findings::delete_for_run( $run_id );
		}
		RWGA_DB_Analysis_Runs::delete( $run_id );

		wp_safe_redirect( admin_url( 'admin.php?page=rwga-analyses&rwga_analysis=deleted' ) );
		exit;
	}

	/**
	 * Generate copy + SEO drafts for every recommendation in one analysis run.
	 *
	 * @return void
	 */
	public static function handle_bulk_implement_analysis() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_bulk_implement_analysis' );

		$analysis_run_id = isset( $_POST['analysis_run_id'] ) ? (int) wp_unslash( $_POST['analysis_run_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $analysis_run_id <= 0 || ! class_exists( 'RWGA_DB_Recommendations', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&rwga_impl=bad' ) );
			exit;
		}
		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&analysis_run=' . $analysis_run_id . '&rwga_impl=unlicensed' ) );
			exit;
		}

		$copy = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'copy_implement' ) : null;
		$seo  = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'seo_implement' ) : null;
		if ( ! $copy || ! $seo ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&analysis_run=' . $analysis_run_id . '&rwga_impl=noflow' ) );
			exit;
		}

		$recs = RWGA_DB_Recommendations::list_for_analysis( $analysis_run_id );
		$draft_count = 0;
		$all_draft_ids = array();
		foreach ( $recs as $rec ) {
			if ( ! is_array( $rec ) || empty( $rec['id'] ) ) {
				continue;
			}
			$rid = (int) $rec['id'];
			$payload = array( 'recommendation_id' => $rid );
			if ( ! empty( $rec['page_id'] ) ) {
				$payload['page_id'] = (int) $rec['page_id'];
			}
			if ( ! empty( $rec['geo_target'] ) ) {
				$payload['geo_target'] = (string) $rec['geo_target'];
			}
			$c = $copy->execute( $payload );
			if ( is_array( $c ) && ! empty( $c['draft_ids'] ) && is_array( $c['draft_ids'] ) ) {
				$draft_count += count( $c['draft_ids'] );
				$all_draft_ids = array_merge( $all_draft_ids, $c['draft_ids'] );
			}
			$s = $seo->execute( $payload );
			if ( is_array( $s ) && ! empty( $s['draft_ids'] ) && is_array( $s['draft_ids'] ) ) {
				$draft_count += count( $s['draft_ids'] );
				$all_draft_ids = array_merge( $all_draft_ids, $s['draft_ids'] );
			}
		}
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_draft_ids( $all_draft_ids );
			RWGA_Current_Workflow::set_step( 'implementation_generated' );
		}
		$url = class_exists( 'RWGA_Journey_Router', false )
			? RWGA_Journey_Router::implementation_review_url( $all_draft_ids, $analysis_run_id )
			: admin_url( 'admin.php?page=rwga-implementation-drafts&view=review&journey=1' );
		$url = add_query_arg( array( 'rwga_impl' => 'ok', 'rwga_draft_count' => (int) $draft_count, 'analysis_run' => (int) $analysis_run_id ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Guided workflow launcher action.
	 *
	 * @return void
	 */
	public static function handle_start_workflow() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_start_workflow' );

		$asset_type = isset( $_POST['asset_type'] ) ? sanitize_key( wp_unslash( $_POST['asset_type'] ) ) : 'page'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$asset_id   = isset( $_POST['asset_id'] ) ? (int) wp_unslash( $_POST['asset_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$focus      = isset( $_POST['analysis_focus'] ) ? sanitize_key( wp_unslash( $_POST['analysis_focus'] ) ) : 'messaging'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$geo        = isset( $_POST['geo_target'] ) ? sanitize_text_field( wp_unslash( $_POST['geo_target'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$competitor = isset( $_POST['competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['competitor_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'competitor' === $asset_type ) {
			$_POST['competitor_url'] = $competitor; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $asset_id > 0 ) {
				$_POST['page_id'] = $asset_id; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( '' !== $geo ) {
				$_POST['geo_target'] = $geo; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			$_POST['_wpnonce'] = wp_create_nonce( 'rwga_competitor_research' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::handle_competitor_research();
			return;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'ux_analysis' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&rwga_sample=noflow' ) );
			exit;
		}

		$payload = array(
			'page_id'        => $asset_id,
			'analysis_focus' => in_array( $focus, array( 'messaging', 'layout', 'both' ), true ) ? $focus : 'messaging',
			'geo_target'     => $geo,
			'page_type'      => 'product' === $asset_type ? 'product' : 'page',
		);
		$out = $wf->execute( $payload );
		if ( is_wp_error( $out ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&rwga_sample=error&rwga_err=' . rawurlencode( $out->get_error_message() ) ) );
			exit;
		}
		$run_id = isset( $out['analysis_run_id'] ) ? (int) $out['analysis_run_id'] : 0;
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::update(
				array(
					'asset_type'      => $asset_type,
					'asset_id'        => $asset_id,
					'analysis_focus'  => $focus,
					'analysis_run_id' => $run_id,
				)
			);
			RWGA_Current_Workflow::set_step( 'analysis_complete' );
		}
		$url = class_exists( 'RWGA_Journey_Router', false ) ? RWGA_Journey_Router::analysis_detail_url( $run_id ) : admin_url( 'admin.php?page=rwga-analyses&run_id=' . $run_id );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Delete a single recommendation and linked implementation drafts.
	 *
	 * @return void
	 */
	public static function handle_recommendation_delete() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to delete recommendations.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_recommendation_delete' );

		$rec_id      = isset( $_POST['recommendation_id'] ) ? (int) wp_unslash( $_POST['recommendation_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$analysis_id = isset( $_POST['analysis_run'] ) ? (int) wp_unslash( $_POST['analysis_run'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $rec_id <= 0 || ! class_exists( 'RWGA_DB_Recommendations', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&rwga_rec=bad' ) );
			exit;
		}

		if ( class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			RWGA_DB_Implementation_Drafts::delete_for_recommendations( array( $rec_id ) );
		}
		RWGA_DB_Recommendations::delete( $rec_id );

		$redir = admin_url( 'admin.php?page=rwga-recommendations&rwga_rec=deleted' );
		if ( $analysis_id > 0 ) {
			$redir = add_query_arg( 'analysis_run', $analysis_id, $redir );
		}
		wp_safe_redirect( $redir );
		exit;
	}

	/**
	 * Delete a single implementation draft row.
	 *
	 * @return void
	 */
	public static function handle_draft_delete() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to delete drafts.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_draft_delete' );

		$draft_id          = isset( $_POST['draft_id'] ) ? (int) wp_unslash( $_POST['draft_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$recommendation_id = isset( $_POST['recommendation_id'] ) ? (int) wp_unslash( $_POST['recommendation_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$workflow_key      = isset( $_POST['workflow_key'] ) ? sanitize_key( wp_unslash( $_POST['workflow_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $draft_id <= 0 || ! class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_draft=bad' ) );
			exit;
		}

		RWGA_DB_Implementation_Drafts::delete( $draft_id );
		$redir = admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_draft=deleted' );
		if ( $recommendation_id > 0 ) {
			$redir = add_query_arg( 'recommendation_id', $recommendation_id, $redir );
		}
		if ( '' !== $workflow_key ) {
			$redir = add_query_arg( 'workflow_key', $workflow_key, $redir );
		}
		wp_safe_redirect( $redir );
		exit;
	}

	/**
	 * Save implementation route choice for generated drafts.
	 *
	 * @return void
	 */
	public static function handle_set_implementation_route() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update implementation route.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_set_implementation_route' );
		$recommendation_id = isset( $_POST['recommendation_id'] ) ? (int) $_POST['recommendation_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$route             = isset( $_POST['implementation_route'] ) ? sanitize_key( wp_unslash( $_POST['implementation_route'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed           = array( 'replace', 'variant', 'geo_optimise' );
		if ( $recommendation_id <= 0 || ! in_array( $route, $allowed, true ) || ! class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&rec_id=' . $recommendation_id . '&rwga_route=bad' ) );
			exit;
		}
		RWGA_DB_Implementation_Drafts::set_route_for_recommendation( $recommendation_id, $route );
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::update(
				array(
					'implementation_mode' => $route,
					'recommendation_ids'  => array( $recommendation_id ),
				)
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwga-recommendations&rec_id=' . $recommendation_id . '&rwga_route=ok' ) );
		exit;
	}

	/**
	 * Apply generated drafts to live page.
	 *
	 * @return void
	 */
	public static function handle_apply_drafts_to_live() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to apply drafts.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_apply_drafts_to_live' );
		$page_id   = isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$draft_ids = isset( $_POST['draft_ids'] ) ? array_filter( array_map( 'intval', explode( ',', (string) wp_unslash( $_POST['draft_ids'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result    = class_exists( 'RWGA_Implementation_Router', false ) ? RWGA_Implementation_Router::apply_drafts_to_live( $draft_ids, $page_id ) : new WP_Error( 'rwga_missing_router', __( 'Implementation router unavailable.', 'reactwoo-geo-ai' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'rwga-implementation-drafts', 'view' => 'review', 'rwga_apply' => 'error', 'rwga_err' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_step( 'applied_live' );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'rwga-implementation-drafts', 'view' => 'review', 'rwga_apply' => 'ok' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create variant from drafts.
	 *
	 * @return void
	 */
	public static function handle_create_variant_from_drafts() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create variants.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_create_variant_from_drafts' );
		$page_id   = isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$draft_ids = isset( $_POST['draft_ids'] ) ? array_filter( array_map( 'intval', explode( ',', (string) wp_unslash( $_POST['draft_ids'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result    = class_exists( 'RWGA_Implementation_Router', false ) ? RWGA_Implementation_Router::create_variant_from_drafts( $draft_ids, $page_id ) : new WP_Error( 'rwga_missing_router', __( 'Implementation router unavailable.', 'reactwoo-geo-ai' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'rwga-implementation-drafts', 'view' => 'review', 'rwga_variant' => 'error', 'rwga_err' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
		$variant_page_id = isset( $result['variant_page_id'] ) ? (int) $result['variant_page_id'] : 0;
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_variant_page_id( $variant_page_id );
			RWGA_Current_Workflow::set_step( 'variant_created' );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'rwga-implementation-drafts', 'view' => 'review', 'rwga_variant' => 'ok', 'variant_page_id' => $variant_page_id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Send variant to Geo Optimise.
	 *
	 * @return void
	 */
	public static function handle_send_variant_to_geo_optimise() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to send variants.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_send_variant_to_geo_optimise' );
		$variant_page_id = isset( $_POST['variant_page_id'] ) ? (int) wp_unslash( $_POST['variant_page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$analysis_run_id = isset( $_POST['analysis_run_id'] ) ? (int) wp_unslash( $_POST['analysis_run_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $variant_page_id <= 0 && class_exists( 'RWGA_Current_Workflow', false ) ) {
			$state           = RWGA_Current_Workflow::get();
			$variant_page_id = isset( $state['variant_page_id'] ) ? (int) $state['variant_page_id'] : 0;
		}
		$url = class_exists( 'RWGA_Implementation_Router', false ) ? RWGA_Implementation_Router::send_variant_to_geo_optimise( $variant_page_id, array( 'analysis_run_id' => $analysis_run_id ) ) : new WP_Error( 'rwga_missing_router', __( 'Implementation router unavailable.', 'reactwoo-geo-ai' ) );
		if ( is_wp_error( $url ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'rwga-implementation-drafts', 'view' => 'review', 'rwga_geo' => 'error', 'rwga_err' => rawurlencode( $url->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_step( 'sent_to_geo_optimise' );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Generate copy implementation drafts from a recommendation and/or page.
	 *
	 * @return void
	 */
	public static function handle_copy_implement() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_copy_implement' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_copy=unlicensed' ) );
			exit;
		}

		$recommendation_id = isset( $_POST['recommendation_id'] ) ? (int) wp_unslash( $_POST['recommendation_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page_id           = isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$geo               = isset( $_POST['geo_target'] ) ? sanitize_text_field( wp_unslash( $_POST['geo_target'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $recommendation_id <= 0 && $page_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_copy=bad' ) );
			exit;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'copy_implement' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_copy=noflow' ) );
			exit;
		}

		$payload = array();
		if ( $recommendation_id > 0 ) {
			$payload['recommendation_id'] = $recommendation_id;
		}
		if ( $page_id > 0 ) {
			$payload['page_id'] = $page_id;
		}
		if ( '' !== $geo ) {
			$payload['geo_target'] = $geo;
		}

		$out = $wf->execute( $payload );

		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'     => 'rwga-implementation-drafts',
						'rwga_copy'=> 'error',
						'rwga_err' => rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$count = isset( $out['draft_ids'] ) && is_array( $out['draft_ids'] ) ? count( $out['draft_ids'] ) : 0;
		$draft_ids = isset( $out['draft_ids'] ) && is_array( $out['draft_ids'] ) ? $out['draft_ids'] : array();
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_draft_ids( $draft_ids );
			RWGA_Current_Workflow::set_step( 'implementation_generated' );
		}
		$redir = class_exists( 'RWGA_Journey_Router', false )
			? RWGA_Journey_Router::implementation_review_url( $draft_ids )
			: admin_url( 'admin.php?page=rwga-implementation-drafts&view=review&journey=1' );
		$redir = add_query_arg( array( 'rwga_copy' => 'ok', 'rwga_draft_count' => (int) $count ), $redir );
		wp_safe_redirect( $redir );
		exit;
	}

	/**
	 * Generate SEO implementation drafts from a recommendation and/or page.
	 *
	 * @return void
	 */
	public static function handle_seo_implement() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_seo_implement' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_seo=unlicensed' ) );
			exit;
		}

		$recommendation_id = isset( $_POST['recommendation_id'] ) ? (int) wp_unslash( $_POST['recommendation_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$page_id           = isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$geo               = isset( $_POST['geo_target'] ) ? sanitize_text_field( wp_unslash( $_POST['geo_target'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $recommendation_id <= 0 && $page_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_seo=bad' ) );
			exit;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'seo_implement' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-implementation-drafts&rwga_seo=noflow' ) );
			exit;
		}

		$payload = array();
		if ( $recommendation_id > 0 ) {
			$payload['recommendation_id'] = $recommendation_id;
		}
		if ( $page_id > 0 ) {
			$payload['page_id'] = $page_id;
		}
		if ( '' !== $geo ) {
			$payload['geo_target'] = $geo;
		}

		$out = $wf->execute( $payload );

		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'rwga-implementation-drafts',
						'rwga_seo'=> 'error',
						'rwga_err'=> rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$count = isset( $out['draft_ids'] ) && is_array( $out['draft_ids'] ) ? count( $out['draft_ids'] ) : 0;
		$draft_ids = isset( $out['draft_ids'] ) && is_array( $out['draft_ids'] ) ? $out['draft_ids'] : array();
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			RWGA_Current_Workflow::set_draft_ids( $draft_ids );
			RWGA_Current_Workflow::set_step( 'implementation_generated' );
		}
		$redir = class_exists( 'RWGA_Journey_Router', false )
			? RWGA_Journey_Router::implementation_review_url( $draft_ids )
			: admin_url( 'admin.php?page=rwga-implementation-drafts&view=review&journey=1' );
		$redir = add_query_arg( array( 'rwga_seo' => 'ok', 'rwga_draft_count' => (int) $count, 'workflow_key' => 'seo_implement' ), $redir );
		wp_safe_redirect( $redir );
		exit;
	}

	/**
	 * Run competitor research workflow from admin.
	 *
	 * @return void
	 */
	public static function handle_competitor_research() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_competitor_research' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-competitors&rwga_cr=unlicensed' ) );
			exit;
		}

		$url = isset( $_POST['competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['competitor_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $url ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-competitors&rwga_cr=badurl' ) );
			exit;
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'competitor_research' ) : null;
		if ( ! $wf ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-competitors&rwga_cr=noflow' ) );
			exit;
		}

		$payload = array(
			'competitor_url' => $url,
		);
		if ( isset( $_POST['page_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payload['page_id'] = (int) wp_unslash( $_POST['page_id'] );
		}
		if ( isset( $_POST['geo_target'] ) && '' !== $_POST['geo_target'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payload['geo_target'] = sanitize_text_field( wp_unslash( $_POST['geo_target'] ) );
		}
		if ( isset( $_POST['page_type'] ) && '' !== $_POST['page_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payload['page_type'] = sanitize_key( wp_unslash( $_POST['page_type'] ) );
		}

		$out = $wf->execute( $payload );

		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'rwga-competitors',
						'rwga_cr' => 'error',
						'rwga_err'=> rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$rid = isset( $out['competitor_research_id'] ) ? (int) $out['competitor_research_id'] : 0;
		wp_safe_redirect( admin_url( 'admin.php?page=rwga-competitors&rwga_cr=ok&research_id=' . $rid ) );
		exit;
	}

	/**
	 * Create or update an automation rule.
	 *
	 * @return void
	 */
	public static function handle_automation_rule_save() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_MANAGE_AUTOMATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage automation rules.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_automation_rule_save' );

		$rule_id = isset( $_POST['rule_id'] ) ? (int) wp_unslash( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$wk      = isset( $_POST['workflow_key'] ) ? sanitize_key( wp_unslash( $_POST['workflow_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $name || '' === $wk ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=bad' ) );
			exit;
		}

		$purl = isset( $_POST['rwga_auto_page_url'] ) ? esc_url_raw( wp_unslash( $_POST['rwga_auto_page_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$curl = isset( $_POST['rwga_auto_competitor_url'] ) ? esc_url_raw( wp_unslash( $_POST['rwga_auto_competitor_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$af_raw = isset( $_POST['rwga_auto_analysis_focus'] ) ? sanitize_key( wp_unslash( $_POST['rwga_auto_analysis_focus'] ) ) : 'inherit'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $af_raw, array( 'inherit', 'messaging', 'layout', 'both' ), true ) ) {
			$af_raw = 'inherit';
		}

		$row = array(
			'name'          => $name,
			'workflow_key'  => $wk,
			'trigger_type'  => isset( $_POST['trigger_type'] ) ? sanitize_key( wp_unslash( $_POST['trigger_type'] ) ) : 'manual', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'target_scope'  => isset( $_POST['target_scope'] ) ? sanitize_key( wp_unslash( $_POST['target_scope'] ) ) : 'site', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'page_id'       => isset( $_POST['page_id'] ) ? (int) wp_unslash( $_POST['page_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'geo_target'    => isset( $_POST['geo_target'] ) ? sanitize_text_field( wp_unslash( $_POST['geo_target'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'status'        => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'rule_config'   => array(
				'notes'          => isset( $_POST['rule_notes'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_notes'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'page_url'       => ( $purl && wp_http_validate_url( $purl ) ) ? $purl : '',
				'competitor_url' => ( $curl && wp_http_validate_url( $curl ) ) ? $curl : '',
				'analysis_focus' => $af_raw,
			),
		);

		if ( $rule_id > 0 ) {
			$ok       = RWGA_DB_Automation_Rules::update_rule( $rule_id, $row );
			$redir_id = $rule_id;
		} else {
			$row['created_by'] = get_current_user_id();
			$redir_id          = RWGA_DB_Automation_Rules::insert( $row );
			$ok                = $redir_id > 0;
		}

		if ( ! $ok || $redir_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=fail' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=saved&rule_id=' . (int) $redir_id ) );
		exit;
	}

	/**
	 * Run automation rule (dispatches workflow then updates schedule timestamps).
	 *
	 * @return void
	 */
	public static function handle_automation_rule_run() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run Geo AI workflows.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_automation_rule_run' );

		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::can_run_workflows() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=unlicensed' ) );
			exit;
		}

		$rule_id = isset( $_POST['rule_id'] ) ? (int) wp_unslash( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $rule_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=bad' ) );
			exit;
		}

		$out = RWGA_Automation_Runner::run( $rule_id );
		if ( is_wp_error( $out ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'rwga-automation',
						'rule_id'    => $rule_id,
						'rwga_auto'  => 'runerr',
						'rwga_err'   => rawurlencode( $out->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=ran&rule_id=' . $rule_id ) );
		exit;
	}

	/**
	 * Delete an automation rule.
	 *
	 * @return void
	 */
	public static function handle_automation_rule_delete() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_MANAGE_AUTOMATIONS ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage automation rules.', 'reactwoo-geo-ai' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'rwga_automation_rule_delete' );

		$rule_id = isset( $_POST['rule_id'] ) ? (int) wp_unslash( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $rule_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=bad' ) );
			exit;
		}

		RWGA_DB_Automation_Rules::delete( $rule_id );
		wp_safe_redirect( admin_url( 'admin.php?page=rwga-automation&rwga_auto=deleted' ) );
		exit;
	}

	/**
	 * Recent recommendations for admin &lt;select&gt;s (newest first).
	 *
	 * @param int $limit Max rows (capped).
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_recommendation_rows_for_select( $limit = 300 ) {
		if ( ! class_exists( 'RWGA_DB_Recommendations', false ) ) {
			return array();
		}
		$limit = max( 1, min( 500, (int) $limit ) );
		return RWGA_DB_Recommendations::list_paged( $limit, 1, 0 );
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		$cap_view = RWGA_Capabilities::CAP_VIEW_REPORTS;

		add_menu_page(
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			$cap_view,
			self::MENU_PARENT,
			array( __CLASS__, 'render_start' ),
			'dashicons-admin-generic',
			57
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Start', 'reactwoo-geo-ai' ),
			__( 'Start', 'reactwoo-geo-ai' ),
			$cap_view,
			self::MENU_PARENT,
			array( __CLASS__, 'render_start' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Analyse', 'reactwoo-geo-ai' ),
			__( 'Analyse', 'reactwoo-geo-ai' ),
			$cap_view,
			'rwga-analyses',
			array( __CLASS__, 'render_analyses' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Recommendations', 'reactwoo-geo-ai' ),
			__( 'Recommendations', 'reactwoo-geo-ai' ),
			$cap_view,
			'rwga-recommendations',
			array( __CLASS__, 'render_recommendations' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Implement', 'reactwoo-geo-ai' ),
			__( 'Implement', 'reactwoo-geo-ai' ),
			$cap_view,
			'rwga-implementation-drafts',
			array( __CLASS__, 'render_implementation_drafts' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Competitor research', 'reactwoo-geo-ai' ),
			__( 'Competitors', 'reactwoo-geo-ai' ),
			$cap_view,
			'rwga-competitors',
			array( __CLASS__, 'render_competitor_research' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Automation', 'reactwoo-geo-ai' ),
			__( 'Automation', 'reactwoo-geo-ai' ),
			$cap_view,
			'rwga-automation',
			array( __CLASS__, 'render_automation' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Settings', 'reactwoo-geo-ai' ),
			__( 'Settings', 'reactwoo-geo-ai' ),
			'manage_options',
			'rwga-license',
			array( __CLASS__, 'render_license_settings' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo AI — Queue', 'reactwoo-geo-ai' ),
			__( 'Queue', 'reactwoo-geo-ai' ),
			$cap_view,
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
			$cap_view,
			'rwga-help',
			array( __CLASS__, 'render_help' )
		);
	}

	/**
	 * Analyses list or single run detail.
	 *
	 * @return void
	 */
	public static function render_analyses() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}

		$run_id = isset( $_GET['run_id'] ) ? (int) $_GET['run_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $run_id > 0 ) {
			self::render_analysis_detail( $run_id );
			return;
		}

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters  = array(
			'asset_type'       => isset( $_GET['asset_type'] ) ? sanitize_key( wp_unslash( $_GET['asset_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'lifecycle_status' => isset( $_GET['lifecycle_status'] ) ? sanitize_key( wp_unslash( $_GET['lifecycle_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'from_date'        => isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to_date'          => isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$total    = class_exists( 'RWGA_DB_Analysis_Runs', false ) ? RWGA_DB_Analysis_Runs::count_rows( $filters ) : 0;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$rwga_rows = class_exists( 'RWGA_DB_Analysis_Runs', false ) ? RWGA_DB_Analysis_Runs::list_paged( $per_page, $paged, $filters ) : array();

		$rwga_pagination = array(
			'total'   => $total,
			'pages'   => $pages,
			'current' => $paged,
		);
		$rwga_filters = $filters;
		$rwgc_nav_current = 'rwga-analyses';
		include RWGA_PATH . 'admin/views/analyses-list.php';
	}

	/**
	 * @param int $run_id Run ID.
	 * @return void
	 */
	private static function render_analysis_detail( $run_id ) {
		$run_id = (int) $run_id;
		if ( $run_id <= 0 || ! class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			wp_die( esc_html__( 'Invalid analysis.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwga_run = RWGA_DB_Analysis_Runs::get( $run_id );
		if ( ! is_array( $rwga_run ) ) {
			wp_die( esc_html__( 'Analysis not found.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwga_findings = class_exists( 'RWGA_DB_Analysis_Findings', false )
			? RWGA_DB_Analysis_Findings::list_for_run( $run_id )
			: array();
		$rwga_recommendations = class_exists( 'RWGA_DB_Recommendations', false )
			? RWGA_DB_Recommendations::list_for_analysis( $run_id )
			: array();

		$rwgc_nav_current = 'rwga-analyses';
		include RWGA_PATH . 'admin/views/analysis-detail.php';
	}

	/**
	 * Recommendations list.
	 *
	 * @return void
	 */
	public static function render_recommendations() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}

		$rec_id = isset( $_GET['rec_id'] ) ? (int) $_GET['rec_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $rec_id > 0 ) {
			self::render_recommendation_detail( $rec_id );
			return;
		}
		$analysis_run_report = isset( $_GET['analysis_run'] ) ? (int) $_GET['analysis_run'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_mode           = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $analysis_run_report > 0 && 'report' === $view_mode ) {
			self::render_recommendation_report( $analysis_run_report );
			return;
		}

		$filter = isset( $_GET['analysis_run'] ) ? (int) $_GET['analysis_run'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters  = array(
			'lifecycle_status' => isset( $_GET['lifecycle_status'] ) ? sanitize_key( wp_unslash( $_GET['lifecycle_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'from_date'        => isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to_date'          => isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$total    = class_exists( 'RWGA_DB_Recommendations', false ) ? RWGA_DB_Recommendations::count_rows( $filter, $filters ) : 0;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$rwga_rows = class_exists( 'RWGA_DB_Recommendations', false )
			? RWGA_DB_Recommendations::list_paged( $per_page, $paged, $filter, $filters )
			: array();

		$rwga_pagination = array(
			'total'   => $total,
			'pages'   => $pages,
			'current' => $paged,
		);
		$rwga_filter_analysis = $filter;
		$rwga_filters         = $filters;
		$rwgc_nav_current     = 'rwga-recommendations';
		include RWGA_PATH . 'admin/views/recommendations-list.php';
	}

	/**
	 * Render grouped recommendation report for one analysis run.
	 *
	 * @param int $analysis_run_id Analysis run ID.
	 * @return void
	 */
	private static function render_recommendation_report( $analysis_run_id ) {
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id <= 0 || ! class_exists( 'RWGA_DB_Recommendations', false ) ) {
			wp_die( esc_html__( 'Invalid recommendation report context.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}
		$rwga_recommendation_rows = RWGA_DB_Recommendations::list_for_analysis( $analysis_run_id );
		$rwga_report_html = class_exists( 'RWGA_Report_Formatter', false )
			? RWGA_Report_Formatter::format_recommendation_report( $rwga_recommendation_rows, array( 'show_title' => true ) )
			: '';
		$rwga_analysis_run_id = $analysis_run_id;
		if ( class_exists( 'RWGA_Current_Workflow', false ) ) {
			$ids = array();
			foreach ( $rwga_recommendation_rows as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$ids[] = (int) $row['id'];
				}
			}
			RWGA_Current_Workflow::update(
				array(
					'analysis_run_id'    => $analysis_run_id,
					'recommendation_ids' => $ids,
				)
			);
		}
		$rwgc_nav_current     = 'rwga-recommendations';
		include RWGA_PATH . 'admin/views/recommendation-report.php';
	}

	/**
	 * Single recommendation detail + copy drafts action.
	 *
	 * @param int $rec_id Recommendation ID.
	 * @return void
	 */
	private static function render_recommendation_detail( $rec_id ) {
		$rec_id = (int) $rec_id;
		if ( $rec_id <= 0 || ! class_exists( 'RWGA_DB_Recommendations', false ) ) {
			wp_die( esc_html__( 'Invalid recommendation.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwga_rec = RWGA_DB_Recommendations::get( $rec_id );
		if ( ! is_array( $rwga_rec ) ) {
			wp_die( esc_html__( 'Recommendation not found.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwgc_nav_current = 'rwga-recommendations';
		include RWGA_PATH . 'admin/views/recommendation-detail.php';
	}

	/**
	 * Implementation drafts list or single draft.
	 *
	 * @return void
	 */
	public static function render_implementation_drafts() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}

		$draft_id = isset( $_GET['draft_id'] ) ? (int) $_GET['draft_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $draft_id > 0 ) {
			self::render_implementation_draft_detail( $draft_id );
			return;
		}
		$view_mode = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'review' === $view_mode ) {
			self::render_implementation_review();
			return;
		}
		$journey = isset( $_GET['journey'] ) ? (int) $_GET['journey'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $journey > 0 && class_exists( 'RWGA_Current_Workflow', false ) ) {
			$state = RWGA_Current_Workflow::get();
			if ( ! empty( $state['draft_ids'] ) && is_array( $state['draft_ids'] ) ) {
				self::render_implementation_review();
				return;
			}
		}

		$filter   = isset( $_GET['recommendation_id'] ) ? (int) $_GET['recommendation_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wk_raw   = isset( $_GET['workflow_key'] ) ? sanitize_key( wp_unslash( $_GET['workflow_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wk_allow = array( '', 'copy_implement', 'seo_implement' );
		$wk       = in_array( $wk_raw, $wk_allow, true ) ? $wk_raw : '';

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filters  = array(
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'from_date' => isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to_date'   => isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$total    = class_exists( 'RWGA_DB_Implementation_Drafts', false ) ? RWGA_DB_Implementation_Drafts::count_rows( $filter, $wk, $filters ) : 0;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$rwga_rows = class_exists( 'RWGA_DB_Implementation_Drafts', false )
			? RWGA_DB_Implementation_Drafts::list_paged( $per_page, $paged, $filter, $wk, $filters )
			: array();

		$rwga_pagination = array(
			'total'   => $total,
			'pages'   => $pages,
			'current' => $paged,
		);
		$rwga_filter_recommendation = $filter;
		$rwga_filter_workflow       = $wk;
		$rwga_filters               = $filters;
		$rwgc_nav_current           = 'rwga-implementation-drafts';
		$rwga_recommendation_rows   = self::get_recommendation_rows_for_select();
		include RWGA_PATH . 'admin/views/implementation-drafts-list.php';
	}

	/**
	 * Render workflow-first implementation review screen.
	 *
	 * @return void
	 */
	private static function render_implementation_review() {
		$state = class_exists( 'RWGA_Current_Workflow', false ) ? RWGA_Current_Workflow::get() : array();
		$draft_ids = array();
		if ( isset( $_GET['draft_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$draft_ids = array_filter( array_map( 'intval', explode( ',', (string) wp_unslash( $_GET['draft_ids'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( empty( $draft_ids ) && ! empty( $state['draft_ids'] ) && is_array( $state['draft_ids'] ) ) {
			$draft_ids = array_values( array_map( 'intval', $state['draft_ids'] ) );
		}
		$rwga_drafts = array();
		if ( class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			foreach ( $draft_ids as $id ) {
				$row = RWGA_DB_Implementation_Drafts::get( (int) $id );
				if ( is_array( $row ) ) {
					$rwga_drafts[] = $row;
				}
			}
		}
		$rwga_workflow_state = $state;
		$rwgc_nav_current    = 'rwga-implementation-drafts';
		include RWGA_PATH . 'admin/views/implementation-review.php';
	}

	/**
	 * @param int $draft_id Draft row ID.
	 * @return void
	 */
	private static function render_implementation_draft_detail( $draft_id ) {
		$draft_id = (int) $draft_id;
		if ( $draft_id <= 0 || ! class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			wp_die( esc_html__( 'Invalid draft.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwga_draft = RWGA_DB_Implementation_Drafts::get( $draft_id );
		if ( ! is_array( $rwga_draft ) ) {
			wp_die( esc_html__( 'Draft not found.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwgc_nav_current = 'rwga-implementation-drafts';
		include RWGA_PATH . 'admin/views/implementation-draft-detail.php';
	}

	/**
	 * Competitor research list or single row.
	 *
	 * @return void
	 */
	public static function render_competitor_research() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}

		$research_id = isset( $_GET['research_id'] ) ? (int) $_GET['research_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $research_id > 0 ) {
			self::render_competitor_research_detail( $research_id );
			return;
		}

		$filter = isset( $_GET['filter_page'] ) ? (int) $_GET['filter_page'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total    = class_exists( 'RWGA_DB_Competitor_Research', false ) ? RWGA_DB_Competitor_Research::count_rows( $filter ) : 0;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$rwga_rows = class_exists( 'RWGA_DB_Competitor_Research', false )
			? RWGA_DB_Competitor_Research::list_paged( $per_page, $paged, $filter )
			: array();

		$rwga_pagination      = array(
			'total'   => $total,
			'pages'   => $pages,
			'current' => $paged,
		);
		$rwga_filter_page     = $filter;
		$rwgc_nav_current     = 'rwga-competitors';
		include RWGA_PATH . 'admin/views/competitor-research-list.php';
	}

	/**
	 * @param int $research_id Row ID.
	 * @return void
	 */
	private static function render_competitor_research_detail( $research_id ) {
		$research_id = (int) $research_id;
		if ( $research_id <= 0 || ! class_exists( 'RWGA_DB_Competitor_Research', false ) ) {
			wp_die( esc_html__( 'Invalid research id.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwga_item = RWGA_DB_Competitor_Research::get( $research_id );
		if ( ! is_array( $rwga_item ) ) {
			wp_die( esc_html__( 'Record not found.', 'reactwoo-geo-ai' ), '', array( 'response' => 404 ) );
		}

		$rwgc_nav_current = 'rwga-competitors';
		include RWGA_PATH . 'admin/views/competitor-research-detail.php';
	}

	/**
	 * Automation rules list or edit one rule.
	 *
	 * @return void
	 */
	public static function render_automation() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}

		$rule_id = isset( $_GET['rule_id'] ) ? (int) $_GET['rule_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$rwga_edit_rule = null;
		if ( $rule_id > 0 && class_exists( 'RWGA_DB_Automation_Rules', false ) ) {
			$rwga_edit_rule = RWGA_DB_Automation_Rules::get( $rule_id );
			if ( ! is_array( $rwga_edit_rule ) ) {
				$rwga_edit_rule = null;
				$rule_id        = 0;
			}
		}

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total    = class_exists( 'RWGA_DB_Automation_Rules', false ) ? RWGA_DB_Automation_Rules::count_rows() : 0;
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$rwga_rows = class_exists( 'RWGA_DB_Automation_Rules', false )
			? RWGA_DB_Automation_Rules::list_paged( $per_page, $paged )
			: array();

		$rwga_pagination = array(
			'total'   => $total,
			'pages'   => $pages,
			'current' => $paged,
		);

		$rwga_workflow_keys = array();
		if ( class_exists( 'RWGA_Workflow_Registry', false ) ) {
			$rwga_workflow_keys = array_keys( RWGA_Workflow_Registry::all() );
		}

		$rwgc_nav_current = 'rwga-automation';
		include RWGA_PATH . 'admin/views/automation-rules.php';
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}
		self::render_start();
	}

	/**
	 * Guided start screen.
	 *
	 * @return void
	 */
	public static function render_start() {
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
			return;
		}
		$settings    = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::get_settings() : array();
		$guided_mode = ! isset( $settings['guided_mode_enabled'] ) || (bool) $settings['guided_mode_enabled'];
		$mode_param  = isset( $_GET['rwga_mode'] ) ? sanitize_key( wp_unslash( $_GET['rwga_mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $guided_mode || 'admin' === $mode_param ) {
			self::render_dashboard_legacy();
			return;
		}
		$rwga_summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		$rwga_cache   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
		$rwga_queue_preview = class_exists( 'RWGA_Drafts', false ) ? RWGA_Drafts::get_queue_rows() : array();
		$rwga_queue_preview = is_array( $rwga_queue_preview ) ? array_slice( $rwga_queue_preview, 0, 5 ) : array();
		$rwga_analysis_preview = array();
		if ( class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			$rwga_analysis_preview = RWGA_DB_Analysis_Runs::list_recent( 5 );
		}
		$rwgc_nav_current = self::MENU_PARENT;
		include RWGA_PATH . 'admin/views/start.php';
	}

	/**
	 * Legacy admin dashboard render path.
	 *
	 * @return void
	 */
	private static function render_dashboard_legacy() {
		$rwga_summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
		$rwga_cache   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
		$rwga_queue_preview = class_exists( 'RWGA_Drafts', false ) ? RWGA_Drafts::get_queue_rows() : array();
		$rwga_queue_preview = is_array( $rwga_queue_preview ) ? array_slice( $rwga_queue_preview, 0, 5 ) : array();
		$rwga_analysis_preview = array();
		if ( class_exists( 'RWGA_DB_Analysis_Runs', false ) ) {
			$rwga_analysis_preview = RWGA_DB_Analysis_Runs::list_recent( 5 );
		}
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
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
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
		if ( ! current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS ) ) {
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
