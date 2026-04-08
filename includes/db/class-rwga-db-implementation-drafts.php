<?php
/**
 * Persistence for implementation drafts (copy, SEO assets, etc.).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_implementation_drafts.
 */
class RWGA_DB_Implementation_Drafts {

	/**
	 * Insert one draft row. draft_payload must be JSON-encoded string or array (encoded here).
	 *
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::implementation_drafts_table();
		$now   = current_time( 'mysql', true );

		$rid = isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0;
		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$payload = isset( $row['draft_payload'] ) ? $row['draft_payload'] : array();
		if ( is_array( $payload ) ) {
			$payload = wp_json_encode( $payload );
		}
		if ( ! is_string( $payload ) ) {
			$payload = '{}';
		}

		$input_ctx = isset( $row['input_context'] ) ? (string) $row['input_context'] : '';
		$diff        = isset( $row['diff_payload'] ) ? $row['diff_payload'] : null;
		if ( is_array( $diff ) ) {
			$diff = wp_json_encode( $diff );
		}

		$data = array(
			'recommendation_id' => $rid > 0 ? $rid : null,
			'workflow_key'      => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'draft_type'        => isset( $row['draft_type'] ) ? sanitize_key( (string) $row['draft_type'] ) : 'copy',
			'page_id'           => $pid > 0 ? $pid : null,
			'geo_target'        => '' !== $geo ? $geo : null,
			'title'             => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
			'input_context'     => $input_ctx !== '' ? $input_ctx : null,
			'draft_payload'     => $payload,
			'diff_payload'      => is_string( $diff ) && '' !== $diff ? $diff : null,
			'status'            => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'draft',
			'applied_at'        => null,
			'created_by'        => $uid > 0 ? $uid : null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$formats = array(
			null === $data['recommendation_id'] ? '%s' : '%d',
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
			null === $data['created_by'] ? '%s' : '%d',
			'%s',
			'%s',
		);

		if ( null === $data['geo_target'] ) {
			$formats[4] = '%s';
		}
		if ( null === $data['input_context'] ) {
			$formats[6] = '%s';
		}
		if ( null === $data['diff_payload'] ) {
			$formats[8] = '%s';
		}
		if ( null === $data['applied_at'] ) {
			$formats[10] = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $id Draft ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::implementation_drafts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $recommendation_id Recommendation ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_recommendation( $recommendation_id ) {
		global $wpdb;
		$recommendation_id = (int) $recommendation_id;
		if ( $recommendation_id <= 0 ) {
			return array();
		}
		$table = RWGA_DB::implementation_drafts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE recommendation_id = %d ORDER BY id ASC", $recommendation_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int    $recommendation_id Optional filter.
	 * @param string $workflow_key      Optional filter (e.g. copy_implement, seo_implement).
	 * @return int
	 */
	public static function count_rows( $recommendation_id = 0, $workflow_key = '' ) {
		global $wpdb;
		$table             = RWGA_DB::implementation_drafts_table();
		$recommendation_id = (int) $recommendation_id;
		$wk                = sanitize_key( (string) $workflow_key );

		if ( $recommendation_id > 0 && '' !== $wk ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE recommendation_id = %d AND workflow_key = %s",
					$recommendation_id,
					$wk
				)
			);
		}
		if ( $recommendation_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE recommendation_id = %d", $recommendation_id ) );
		}
		if ( '' !== $wk ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE workflow_key = %s", $wk ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @param int    $per_page          Items per page.
	 * @param int    $paged             Page number.
	 * @param int    $recommendation_id Optional filter.
	 * @param string $workflow_key      Optional filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, $recommendation_id = 0, $workflow_key = '' ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::implementation_drafts_table();
		$recommendation_id = (int) $recommendation_id;
		$wk                  = sanitize_key( (string) $workflow_key );

		if ( $recommendation_id > 0 && '' !== $wk ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE recommendation_id = %d AND workflow_key = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$recommendation_id,
					$wk,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} elseif ( $recommendation_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE recommendation_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$recommendation_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} elseif ( '' !== $wk ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE workflow_key = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$wk,
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
