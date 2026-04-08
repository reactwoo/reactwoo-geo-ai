<?php
/**
 * Persistence for recommendation cards.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_recommendations.
 */
class RWGA_DB_Recommendations {

	/**
	 * Insert one recommendation row.
	 *
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::recommendations_table();
		$now   = current_time( 'mysql', true );

		$aid = isset( $row['analysis_run_id'] ) ? (int) $row['analysis_run_id'] : 0;
		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$conf = null;
		if ( isset( $row['confidence'] ) && null !== $row['confidence'] && '' !== $row['confidence'] ) {
			$conf = (float) $row['confidence'];
		}

		$exp = null;
		if ( isset( $row['expected_impact'] ) && null !== $row['expected_impact'] && '' !== $row['expected_impact'] ) {
			$exp = sanitize_text_field( (string) $row['expected_impact'] );
		}

		$data = array(
			'analysis_run_id'    => $aid > 0 ? $aid : null,
			'workflow_key'       => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'agent_key'          => isset( $row['agent_key'] ) ? sanitize_key( (string) $row['agent_key'] ) : '',
			'page_id'            => $pid > 0 ? $pid : null,
			'geo_target'         => '' !== $geo ? $geo : null,
			'priority_level'     => isset( $row['priority_level'] ) ? sanitize_key( (string) $row['priority_level'] ) : 'medium',
			'category'           => isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : 'general',
			'title'              => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
			'problem'            => isset( $row['problem'] ) ? wp_kses_post( (string) $row['problem'] ) : '',
			'why_it_matters'     => isset( $row['why_it_matters'] ) ? wp_kses_post( (string) $row['why_it_matters'] ) : '',
			'recommendation'     => isset( $row['recommendation'] ) ? wp_kses_post( (string) $row['recommendation'] ) : '',
			'expected_impact'    => $exp,
			'confidence'         => $conf,
			'status'             => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'open',
			'created_by'         => $uid > 0 ? $uid : null,
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		// 17 columns — nulls use %s for wpdb compatibility.
		$formats = array(
			null === $data['analysis_run_id'] ? '%s' : '%d',
			'%s',
			'%s',
			null === $data['page_id'] ? '%s' : '%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			null === $data['confidence'] ? '%s' : '%f',
			'%s',
			null === $data['created_by'] ? '%s' : '%d',
			'%s',
			'%s',
		);

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $id Recommendation ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::recommendations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $analysis_run_id Run ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_analysis( $analysis_run_id ) {
		global $wpdb;
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id <= 0 ) {
			return array();
		}
		$table = RWGA_DB::recommendations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE analysis_run_id = %d ORDER BY id ASC",
				$analysis_run_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total rows.
	 *
	 * @param int $analysis_run_id Optional filter by analysis run.
	 * @return int
	 */
	public static function count_rows( $analysis_run_id = 0 ) {
		global $wpdb;
		$table           = RWGA_DB::recommendations_table();
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE analysis_run_id = %d", $analysis_run_id ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Paginated list (newest first).
	 *
	 * @param int $per_page Items per page.
	 * @param int $paged    Page number (1-based).
	 * @param int $analysis_run_id Optional filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, $analysis_run_id = 0 ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::recommendations_table();
		$analysis_run_id = (int) $analysis_run_id;

		if ( $analysis_run_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE analysis_run_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$analysis_run_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}
}
