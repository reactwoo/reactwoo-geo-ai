<?php
/**
 * Guided site intelligence wizard — step state, progress, and automated setup.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes wizard steps and runs sync + site_audit as one automated flow.
 */
class RWGA_Site_Intelligence_Journey {

	const OPTION_KEY = 'rwga_site_intelligence_journey';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_site_intelligence_synced', array( __CLASS__, 'maybe_auto_audit_after_sync' ), 10, 2 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_state() {
		$raw = get_option( self::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param array<string, mixed> $patch Patch.
	 * @return void
	 */
	private static function save_state( array $patch ) {
		update_option( self::OPTION_KEY, array_merge( self::get_state(), $patch ), false );
	}

	/**
	 * Record a completed site audit run.
	 *
	 * @param string $workflow_key Workflow key.
	 * @param int    $action_count Pending actions created.
	 * @return void
	 */
	public static function record_audit_run( $workflow_key, $action_count ) {
		self::save_state(
			array(
				'last_audit_workflow'   => sanitize_key( (string) $workflow_key ),
				'last_audit_at_gmt'     => gmdate( 'c' ),
				'last_audit_action_count' => max( 0, (int) $action_count ),
			)
		);
	}

	/**
	 * Whether remote engine mode is enabled for intelligence workflows.
	 *
	 * @return bool
	 */
	public static function is_remote_engine_ready() {
		if ( ! class_exists( 'RWGA_Engine', false ) ) {
			return false;
		}
		return RWGA_Engine::should_try_remote();
	}

	/**
	 * @return int
	 */
	public static function count_all_pending_actions() {
		if ( ! class_exists( 'RWGA_DB_Intelligence_Actions', false ) ) {
			return 0;
		}
		return (int) RWGA_DB_Intelligence_Actions::count_rows( '', array( 'status' => 'pending' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_auto_audit_enabled() {
		if ( ! class_exists( 'RWGA_Settings', false ) ) {
			return true;
		}
		$s = RWGA_Settings::get_settings();
		return ! empty( $s['auto_site_audit_after_sync'] );
	}

	/**
	 * Wizard steps for the guided UI.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_steps() {
		$license_url  = admin_url( 'admin.php?page=rwga-license' );
		$advanced_url = admin_url( 'admin.php?page=rwga-advanced' );
		$wizard_url   = admin_url( 'admin.php?page=rwga-intelligence-wizard' );
		$cloud_url    = admin_url( 'admin.php?page=rwga-intelligence-cloud' );
		$actions_url  = admin_url( 'admin.php?page=rwga-intelligence-actions' );

		$lic_ok = class_exists( 'RWGA_Settings', false ) && RWGA_Settings::is_license_configured_for_geo_ai_ui();
		$remote = self::is_remote_engine_ready();
		$intel  = class_exists( 'RWGA_Site_Intelligence_Sync', false )
			? RWGA_Site_Intelligence_Sync::get_license_page_status()
			: array();
		$synced = ! empty( $intel['status']['last_synced_at_gmt'] )
			&& ( empty( $intel['status']['last_sync_status'] ) || 'synced' === (string) $intel['status']['last_sync_status'] );
		$state  = self::get_state();
		$audited = ! empty( $state['last_audit_at_gmt'] );
		if ( ! $audited && class_exists( 'RWGA_Intelligence_Cloud_Client', false ) ) {
			$cloud_site = RWGA_Intelligence_Cloud_Client::get_cloud_site_id();
			if ( '' !== $cloud_site ) {
				$list = RWGA_Intelligence_Cloud_Client::list_runs( $cloud_site, 1 );
				if ( ! is_wp_error( $list ) && ! empty( $list['runs'] ) && is_array( $list['runs'] ) ) {
					$audited = true;
				}
			}
		}
		$pending = self::count_all_pending_actions();
		$reviewed = $audited && 0 === $pending;

		$sync_nonce = wp_nonce_url(
			admin_url( 'admin.php?page=rwga-intelligence-wizard&rwga_action=wizard_sync' ),
			'rwga_dash_wizard_sync'
		);

		return array(
			array(
				'id'    => 'license',
				'label' => __( 'Connect your Geo AI license', 'reactwoo-geo-ai' ),
				'hint'  => __( 'Pro plan required for cloud site intelligence.', 'reactwoo-geo-ai' ),
				'done'  => $lic_ok,
				'url'   => $license_url,
				'cta'   => __( 'Open license settings', 'reactwoo-geo-ai' ),
			),
			array(
				'id'    => 'remote',
				'label' => __( 'Turn on remote analysis', 'reactwoo-geo-ai' ),
				'hint'  => __( 'Set execution mode to Remote or Remote with local fallback under Advanced.', 'reactwoo-geo-ai' ),
				'done'  => $remote,
				'url'   => $advanced_url,
				'cta'   => __( 'Open Advanced settings', 'reactwoo-geo-ai' ),
			),
			array(
				'id'    => 'sync',
				'label' => __( 'Sync your site snapshot', 'reactwoo-geo-ai' ),
				'hint'  => $synced && ! empty( $intel['status']['last_synced_at_gmt'] )
					/* translators: %s: GMT timestamp */
					? sprintf( __( 'Last synced: %s (GMT).', 'reactwoo-geo-ai' ), (string) $intel['status']['last_synced_at_gmt'] )
					: __( 'Uploads rules, variants, and relationships — not page content.', 'reactwoo-geo-ai' ),
				'done'  => $synced,
				'url'   => $sync_nonce,
				'cta'   => __( 'Sync now', 'reactwoo-geo-ai' ),
			),
			array(
				'id'    => 'audit',
				'label' => __( 'Run a site audit', 'reactwoo-geo-ai' ),
				'hint'  => $audited
					/* translators: %s: GMT timestamp */
					? sprintf( __( 'Last audit: %s (GMT).', 'reactwoo-geo-ai' ), (string) $state['last_audit_at_gmt'] )
					: __( 'Analyzes your synced snapshot and suggests improvements.', 'reactwoo-geo-ai' ),
				'done'  => $audited,
				'url'   => wp_nonce_url(
					admin_url( 'admin-post.php?action=rwga_intelligence_wizard_audit' ),
					'rwga_intelligence_wizard_audit'
				),
				'cta'   => __( 'Run site audit', 'reactwoo-geo-ai' ),
			),
			array(
				'id'    => 'review',
				'label' => __( 'Review suggestions', 'reactwoo-geo-ai' ),
				'hint'  => $pending > 0
					/* translators: %d: pending action count */
					? sprintf( _n( '%d item waiting for approve or dismiss.', '%d items waiting for approve or dismiss.', $pending, 'reactwoo-geo-ai' ), $pending )
					: ( $audited ? __( 'No pending suggestions — you are up to date.', 'reactwoo-geo-ai' ) : __( 'Complete a site audit first.', 'reactwoo-geo-ai' ) ),
				'done'  => $reviewed,
				'url'   => $actions_url,
				'cta'   => $pending > 0 ? __( 'Review pending actions', 'reactwoo-geo-ai' ) : __( 'View intelligence actions', 'reactwoo-geo-ai' ),
			),
			array(
				'id'       => 'explore',
				'label'    => __( 'Explore results (optional)', 'reactwoo-geo-ai' ),
				'hint'     => __( 'Relationship graph, run history, and audit findings.', 'reactwoo-geo-ai' ),
				'done'     => $audited,
				'optional' => true,
				'url'      => $cloud_url,
				'cta'      => __( 'Open cloud intelligence', 'reactwoo-geo-ai' ),
			),
		);
	}

	/**
	 * @return array{completed:int,total:int,percent:int,current_step:string}
	 */
	public static function get_progress() {
		$steps     = self::get_steps();
		$required  = array_filter(
			$steps,
			static function ( $step ) {
				return empty( $step['optional'] );
			}
		);
		$total     = count( $required );
		$completed = 0;
		$current   = 'license';
		foreach ( $required as $step ) {
			if ( ! empty( $step['done'] ) ) {
				++$completed;
			} else {
				$current = isset( $step['id'] ) ? (string) $step['id'] : $current;
				break;
			}
		}
		if ( $completed >= $total ) {
			$current = 'complete';
		}
		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;

		return array(
			'completed'    => $completed,
			'total'        => $total,
			'percent'      => $percent,
			'current_step' => $current,
		);
	}

	/**
	 * Sync snapshot then run site_audit when prerequisites pass.
	 *
	 * @param bool $force_sync Force upload even when hash unchanged.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function run_automated_setup( $force_sync = false ) {
		if ( ! class_exists( 'RWGA_Settings', false ) || ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			return new WP_Error( 'rwga_wizard_no_license', __( 'Add a Geo AI license key first.', 'reactwoo-geo-ai' ) );
		}
		if ( ! self::is_remote_engine_ready() ) {
			return new WP_Error(
				'rwga_wizard_no_remote',
				__( 'Site intelligence requires Remote or Remote with local fallback in Advanced → Workflow engine.', 'reactwoo-geo-ai' )
			);
		}
		if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
			return new WP_Error(
				'rwga_wizard_no_geocore',
				__( 'Update ReactWoo Geo Core so the site intelligence snapshot builder is available.', 'reactwoo-geo-ai' )
			);
		}

		$sync_result = null;
		if ( class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			$sync_result = RWGA_Site_Intelligence_Sync::sync( $force_sync );
			if ( is_wp_error( $sync_result ) ) {
				return $sync_result;
			}
		}

		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'site_audit' ) : null;
		if ( ! $wf ) {
			return new WP_Error( 'rwga_wizard_no_workflow', __( 'Site audit workflow is not available.', 'reactwoo-geo-ai' ) );
		}

		$out = $wf->execute( array() );
		if ( is_wp_error( $out ) ) {
			return $out;
		}

		$action_count = isset( $out['action_ids'] ) && is_array( $out['action_ids'] ) ? count( $out['action_ids'] ) : 0;
		self::record_audit_run( 'site_audit', $action_count );

		return array(
			'sync'          => $sync_result,
			'audit'         => $out,
			'action_count'  => $action_count,
			'pending_total' => self::count_all_pending_actions(),
		);
	}

	/**
	 * Run site_audit only (used by auto-audit hook).
	 *
	 * @return array<string, mixed>|\WP_Error|null Null when skipped.
	 */
	public static function run_site_audit_if_allowed() {
		if ( ! self::is_remote_engine_ready() ) {
			return null;
		}
		if ( ! class_exists( 'RWGA_Settings', false ) || ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			return null;
		}
		$wf = class_exists( 'RWGA_Workflow_Registry', false ) ? RWGA_Workflow_Registry::get( 'site_audit' ) : null;
		if ( ! $wf ) {
			return null;
		}
		$out = $wf->execute( array() );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$action_count = isset( $out['action_ids'] ) && is_array( $out['action_ids'] ) ? count( $out['action_ids'] ) : 0;
		self::record_audit_run( 'site_audit', $action_count );
		return array(
			'audit'        => $out,
			'action_count' => $action_count,
		);
	}

	/**
	 * After successful sync, optionally run site audit automatically.
	 *
	 * @param array<string, mixed> $upload   Upload response.
	 * @param array<string, mixed> $snapshot Snapshot payload.
	 * @return void
	 */
	public static function maybe_auto_audit_after_sync( $upload, $snapshot ) {
		unset( $upload, $snapshot );
		if ( ! self::is_auto_audit_enabled() ) {
			return;
		}
		// Avoid duplicate audits when cron re-syncs unchanged snapshots (skipped path does not fire this hook).
		self::run_site_audit_if_allowed();
	}
}
