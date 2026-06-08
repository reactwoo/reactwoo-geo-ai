<?php
/**
 * Geo AI ownership of site intelligence cloud sync (Geo Core builds; Geo AI uploads).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates licence checks, Geo Core snapshot build, hash dedupe, and API upload.
 */
class RWGA_Site_Intelligence_Sync {

	const OPTION_KEY = 'rwga_site_intelligence_sync';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_loaded', array( __CLASS__, 'on_loaded' ), 40 );
		add_filter( 'rwga_usage_display_rows', array( __CLASS__, 'filter_usage_display_rows' ), 15, 2 );
	}

	/**
	 * @return void
	 */
	public static function on_loaded() {
		add_action( RWGA_Cron::HOOK, array( __CLASS__, 'maybe_sync_on_cron' ), 20 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$defaults = array(
			'cloud_site_id'       => '',
			'last_snapshot_hash'  => '',
			'last_synced_at_gmt'  => '',
			'last_sync_status'    => '',
			'last_sync_error'     => '',
			'last_skip_reason'    => '',
		);

		$status = array_merge( $defaults, $raw );

		if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
			$core = RWGC_AI_Snapshot_Sync_Status::get_status();
			if ( empty( $status['last_snapshot_hash'] ) && ! empty( $core['last_built_hash'] ) ) {
				$status['last_snapshot_hash'] = (string) $core['last_built_hash'];
			}
		}

		return $status;
	}

	/**
	 * @param array<string, mixed> $patch Partial status update.
	 * @return void
	 */
	private static function save_status( array $patch ) {
		$status = self::get_status();
		update_option( self::OPTION_KEY, array_merge( $status, $patch ), false );
	}

	/**
	 * Sync site intelligence to the cloud.
	 *
	 * @param bool $force Upload even when snapshot hash is unchanged.
	 * @return array<string, mixed>|\WP_Error Result with status key.
	 */
	public static function sync( $force = false ) {
		$guard = RWGA_AI_Usage_Guard::can_sync_snapshot();
		if ( empty( $guard['allowed'] ) ) {
			$reason = isset( $guard['reason'] ) ? (string) $guard['reason'] : __( 'Sync not allowed.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'blocked',
					'last_sync_error'  => $reason,
				)
			);
			return new WP_Error( 'rwga_sync_blocked', $reason );
		}

		$snapshot = rwgc_build_ai_snapshot();
		if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
			$err = __( 'Geo Core returned an empty site intelligence snapshot.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $err,
				)
			);
			return new WP_Error( 'rwga_empty_snapshot', $err );
		}

		$size_check = RWGA_AI_Usage_Guard::check_payload_size( $snapshot );
		if ( is_wp_error( $size_check ) ) {
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $size_check->get_error_message(),
				)
			);
			return $size_check;
		}

		$hash = isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '';
		if ( '' === $hash && class_exists( 'RWGC_AI_Snapshot_Schema', false ) ) {
			$hash = RWGC_AI_Snapshot_Schema::compute_hash( $snapshot );
		}

		$status = self::get_status();
		if ( ! $force && '' !== $hash && $hash === (string) ( $status['last_snapshot_hash'] ?? '' ) && 'synced' === (string) ( $status['last_sync_status'] ?? '' ) ) {
			$skip = __( 'Snapshot unchanged since last sync.', 'reactwoo-geo-ai' );
			self::save_status(
				array(
					'last_sync_status' => 'skipped',
					'last_skip_reason' => $skip,
					'last_sync_error'  => '',
				)
			);
			return array(
				'status'  => 'skipped',
				'message' => $skip,
				'hash'    => $hash,
			);
		}

		$site_id = isset( $status['cloud_site_id'] ) ? (string) $status['cloud_site_id'] : '';
		if ( '' === $site_id ) {
			$registered = RWGA_Site_Snapshot_Client::register_site();
			if ( is_wp_error( $registered ) ) {
				self::save_status(
					array(
						'last_sync_status' => 'error',
						'last_sync_error'  => $registered->get_error_message(),
					)
				);
				return $registered;
			}
			$site_id = (string) $registered['site_id'];
			self::save_status( array( 'cloud_site_id' => $site_id ) );
		}

		$upload = RWGA_Site_Snapshot_Client::upload_snapshot( $site_id, $snapshot );
		if ( is_wp_error( $upload ) ) {
			self::save_status(
				array(
					'last_sync_status' => 'error',
					'last_sync_error'  => $upload->get_error_message(),
				)
			);
			if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
				RWGC_AI_Snapshot_Sync_Status::record_sync_error( $upload->get_error_message() );
			}
			return $upload;
		}

		$now = gmdate( 'c' );
		self::save_status(
			array(
				'cloud_site_id'      => $site_id,
				'last_snapshot_hash' => $hash,
				'last_synced_at_gmt' => $now,
				'last_sync_status'   => 'synced',
				'last_sync_error'    => '',
				'last_skip_reason'   => '',
			)
		);

		if ( class_exists( 'RWGC_AI_Snapshot_Sync_Status', false ) ) {
			RWGC_AI_Snapshot_Sync_Status::record_sync_success( $hash );
		}

		/**
		 * Fires after a successful site intelligence cloud sync.
		 *
		 * @param array<string, mixed> $upload   Upload response from {@see RWGA_Site_Snapshot_Client::upload_snapshot()}.
		 * @param array<string, mixed> $snapshot Snapshot payload sent.
		 */
		do_action( 'rwga_site_intelligence_synced', $upload, $snapshot );

		return array(
			'status'   => 'synced',
			'hash'     => $hash,
			'site_id'  => $site_id,
			'synced_at_gmt' => $now,
		);
	}

	/**
	 * Cron hook: sync when licensed and snapshot changed.
	 *
	 * @return void
	 */
	public static function maybe_sync_on_cron() {
		if ( ! class_exists( 'RWGA_License', false ) || ! RWGA_License::is_configured() ) {
			return;
		}
		self::sync( false );
	}

	/**
	 * Append site intelligence sync rows to usage table.
	 *
	 * @param array<string, string> $rows  Display rows.
	 * @param array<string, mixed>  $cache Usage cache.
	 * @return array<string, string>
	 */
	public static function filter_usage_display_rows( $rows, $cache ) {
		unset( $cache );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$st = self::get_status();
		$rows[ __( 'Site intelligence sync', 'reactwoo-geo-ai' ) ] = self::format_status_label( $st );
		if ( ! empty( $st['last_synced_at_gmt'] ) ) {
			$rows[ __( 'Last intelligence sync (GMT)', 'reactwoo-geo-ai' ) ] = (string) $st['last_synced_at_gmt'];
		}
		if ( ! empty( $st['last_snapshot_hash'] ) ) {
			$hash = (string) $st['last_snapshot_hash'];
			$rows[ __( 'Snapshot hash', 'reactwoo-geo-ai' ) ] = substr( $hash, 0, 12 ) . '…';
		}
		return $rows;
	}

	/**
	 * @param array<string, mixed> $status Sync status row.
	 * @return string
	 */
	public static function format_status_label( array $status ) {
		$key = isset( $status['last_sync_status'] ) ? sanitize_key( (string) $status['last_sync_status'] ) : '';
		switch ( $key ) {
			case 'synced':
				return __( 'Synced', 'reactwoo-geo-ai' );
			case 'skipped':
				return __( 'Skipped (unchanged)', 'reactwoo-geo-ai' );
			case 'blocked':
				return __( 'Blocked', 'reactwoo-geo-ai' );
			case 'error':
				return __( 'Error', 'reactwoo-geo-ai' );
			default:
				return __( 'Not synced yet', 'reactwoo-geo-ai' );
		}
	}
}
