<?php
/**
 * Persistence for analysis runs.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_analysis_runs.
 */
class RWGA_DB_Analysis_Runs {

	/**
	 * Insert a run row.
	 *
	 * @param array<string, mixed> $row Row fields.
	 * @return int Insert ID or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::analysis_runs_table();
		$now   = current_time( 'mysql', true );

		$page_id = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid     = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$data = array(
			'site_id'               => isset( $row['site_id'] ) ? (string) $row['site_id'] : '',
			'workflow_key'          => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'agent_key'             => isset( $row['agent_key'] ) ? sanitize_key( (string) $row['agent_key'] ) : '',
			'page_type'             => isset( $row['page_type'] ) ? sanitize_text_field( (string) $row['page_type'] ) : '',
			'status'                => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'complete',
			'result_schema_version' => isset( $row['result_schema_version'] ) ? sanitize_text_field( (string) $row['result_schema_version'] ) : '1.0.0',
			'created_at'            => isset( $row['created_at'] ) ? (string) $row['created_at'] : $now,
			'updated_at'            => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $page_id > 0 ) {
			$data['page_id'] = $page_id;
			$formats[]       = '%d';
		} else {
			$data['page_id'] = null;
			$formats[]       = '%s';
		}

		$data['page_url'] = isset( $row['page_url'] ) ? esc_url_raw( (string) $row['page_url'] ) : null;
		$formats[]        = '%s';

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';
		$data['geo_target'] = '' !== $geo ? $geo : null;
		$formats[]          = '%s';

		$dev = isset( $row['device_type'] ) ? sanitize_key( (string) $row['device_type'] ) : '';
		$data['device_type'] = '' !== $dev ? $dev : null;
		$formats[]           = '%s';

		if ( isset( $row['score'] ) && null !== $row['score'] && '' !== $row['score'] ) {
			$data['score'] = (float) $row['score'];
			$formats[]     = '%f';
		} else {
			$data['score'] = null;
			$formats[]     = '%s';
		}

		if ( isset( $row['confidence'] ) && null !== $row['confidence'] && '' !== $row['confidence'] ) {
			$data['confidence'] = (float) $row['confidence'];
			$formats[]          = '%f';
		} else {
			$data['confidence'] = null;
			$formats[]          = '%s';
		}

		$data['summary'] = isset( $row['summary'] ) ? wp_kses_post( (string) $row['summary'] ) : null;
		$formats[]       = '%s';

		$data['input_hash'] = isset( $row['input_hash'] ) ? sanitize_text_field( (string) $row['input_hash'] ) : null;
		$formats[]          = '%s';

		$data['remote_run_id'] = isset( $row['remote_run_id'] ) ? sanitize_text_field( (string) $row['remote_run_id'] ) : null;
		$formats[]             = '%s';

		if ( $uid > 0 ) {
			$data['created_by'] = $uid;
			$formats[]          = '%d';
		} else {
			$data['created_by'] = null;
			$formats[]          = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		if ( ! $ok ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Run ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Recent runs for admin preview.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_recent( $limit = 10 ) {
		global $wpdb;
		$limit = max( 1, min( 50, (int) $limit ) );
		$table = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total rows for pagination.
	 *
	 * @return int
	 */
	public static function count_rows() {
		global $wpdb;
		$table = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$c = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return (int) $c;
	}

	/**
	 * Paginated list (newest first).
	 *
	 * @param int $per_page Items per page.
	 * @param int $paged    Page number (1-based).
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1 ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
