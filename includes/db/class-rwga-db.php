<?php
/**
 * Custom tables for Geo AI workflows and intelligence.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema install via dbDelta.
 */
class RWGA_DB {

	const VERSION_OPTION = 'rwga_db_version';
	const SCHEMA_VERSION = '1.1.0';

	/**
	 * @return string
	 */
	public static function analysis_runs_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_analysis_runs';
	}

	/**
	 * @return string
	 */
	public static function analysis_findings_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_analysis_findings';
	}

	/**
	 * @return string
	 */
	public static function recommendations_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_recommendations';
	}

	/**
	 * @return string
	 */
	public static function implementation_drafts_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_implementation_drafts';
	}

	/**
	 * @return string
	 */
	public static function competitor_research_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_competitor_research';
	}

	/**
	 * @return string
	 */
	public static function automation_rules_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_automation_rules';
	}

	/**
	 * @return string
	 */
	public static function memory_events_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_memory_events';
	}

	/**
	 * @return string
	 */
	public static function outcomes_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwga_outcomes';
	}

	/**
	 * Install or upgrade tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$runs = 'CREATE TABLE ' . self::analysis_runs_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id varchar(64) NOT NULL DEFAULT '',
			workflow_key varchar(64) NOT NULL DEFAULT '',
			agent_key varchar(64) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NULL,
			page_url text NULL,
			page_type varchar(50) NOT NULL DEFAULT '',
			asset_type varchar(50) NOT NULL DEFAULT 'page',
			asset_id bigint(20) unsigned NULL,
			analysis_focus varchar(20) NOT NULL DEFAULT 'messaging',
			geo_target varchar(20) NULL,
			device_type varchar(20) NULL,
			status varchar(20) NOT NULL DEFAULT 'complete',
			lifecycle_status varchar(40) NOT NULL DEFAULT 'analysed',
			score decimal(5,2) NULL,
			confidence decimal(5,2) NULL,
			summary longtext NULL,
			report_html longtext NULL,
			input_hash varchar(64) NULL,
			result_schema_version varchar(20) NOT NULL DEFAULT '',
			remote_run_id varchar(128) NULL,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_key (workflow_key),
			KEY page_id (page_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY page_workflow_created (page_id, workflow_key, created_at)
		) $charset_collate;";

		$findings = 'CREATE TABLE ' . self::analysis_findings_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			analysis_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
			finding_key varchar(100) NOT NULL DEFAULT '',
			category varchar(50) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT '',
			confidence decimal(5,2) NULL,
			title varchar(255) NOT NULL DEFAULT '',
			evidence longtext NULL,
			recommendation_hint longtext NULL,
			impact_estimate varchar(100) NULL,
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY analysis_run_id (analysis_run_id),
			KEY category (category),
			KEY severity (severity)
		) $charset_collate;";

		$recs = 'CREATE TABLE ' . self::recommendations_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			analysis_run_id bigint(20) unsigned NULL,
			workflow_key varchar(64) NOT NULL DEFAULT '',
			agent_key varchar(64) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NULL,
			geo_target varchar(20) NULL,
			priority_level varchar(20) NOT NULL DEFAULT '',
			category varchar(50) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			problem longtext NOT NULL,
			why_it_matters longtext NOT NULL,
			recommendation longtext NOT NULL,
			selected_categories longtext NULL,
			report_html longtext NULL,
			expected_impact varchar(100) NULL,
			confidence decimal(5,2) NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			lifecycle_status varchar(40) NOT NULL DEFAULT 'recommendations_generated',
			dismiss_reason text NULL,
			accepted_at datetime NULL,
			dismissed_at datetime NULL,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY analysis_run_id (analysis_run_id),
			KEY page_id (page_id),
			KEY status (status),
			KEY priority_level (priority_level)
		) $charset_collate;";

		$drafts = 'CREATE TABLE ' . self::implementation_drafts_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			recommendation_id bigint(20) unsigned NULL,
			workflow_key varchar(64) NOT NULL DEFAULT '',
			draft_type varchar(50) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NULL,
			geo_target varchar(20) NULL,
			title varchar(255) NOT NULL DEFAULT '',
			input_context longtext NULL,
			draft_payload longtext NOT NULL,
			report_html longtext NULL,
			implementation_route varchar(40) NULL,
			variant_page_id bigint(20) unsigned NULL,
			geo_optimise_id bigint(20) unsigned NULL,
			diff_payload longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			applied_at datetime NULL,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY recommendation_id (recommendation_id),
			KEY page_id (page_id),
			KEY draft_type (draft_type),
			KEY status (status)
		) $charset_collate;";

		$comp = 'CREATE TABLE ' . self::competitor_research_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_id bigint(20) unsigned NULL,
			competitor_url text NOT NULL,
			page_type varchar(50) NULL,
			geo_target varchar(20) NULL,
			workflow_key varchar(64) NOT NULL DEFAULT '',
			summary longtext NOT NULL,
			strengths longtext NULL,
			weaknesses longtext NULL,
			patterns longtext NULL,
			opportunities longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'complete',
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY page_id (page_id),
			KEY workflow_key (workflow_key),
			KEY created_at (created_at)
		) $charset_collate;";

		$auto = 'CREATE TABLE ' . self::automation_rules_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			workflow_key varchar(64) NOT NULL DEFAULT '',
			trigger_type varchar(50) NOT NULL DEFAULT '',
			target_scope varchar(50) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NULL,
			geo_target varchar(20) NULL,
			rule_config longtext NOT NULL,
			last_run_at datetime NULL,
			next_run_at datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY workflow_key (workflow_key),
			KEY status (status),
			KEY next_run_at (next_run_at)
		) $charset_collate;";

		$mem = 'CREATE TABLE ' . self::memory_events_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL DEFAULT '',
			entity_type varchar(50) NOT NULL DEFAULT '',
			entity_id bigint(20) unsigned NULL,
			page_id bigint(20) unsigned NULL,
			geo_target varchar(20) NULL,
			payload longtext NULL,
			importance tinyint(3) unsigned NOT NULL DEFAULT 5,
			created_by bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY entity (entity_type, entity_id),
			KEY page_id (page_id),
			KEY created_at (created_at)
		) $charset_collate;";

		$out = 'CREATE TABLE ' . self::outcomes_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			analysis_run_id bigint(20) unsigned NULL,
			recommendation_id bigint(20) unsigned NULL,
			draft_id bigint(20) unsigned NULL,
			page_id bigint(20) unsigned NULL,
			geo_target varchar(20) NULL,
			metric_key varchar(100) NOT NULL DEFAULT '',
			metric_before decimal(12,4) NULL,
			metric_after decimal(12,4) NULL,
			delta_value decimal(12,4) NULL,
			delta_percent decimal(12,4) NULL,
			observation_window varchar(50) NULL,
			notes longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY analysis_run_id (analysis_run_id),
			KEY recommendation_id (recommendation_id),
			KEY page_id (page_id),
			KEY metric_key (metric_key)
		) $charset_collate;";

		dbDelta( $runs );
		dbDelta( $findings );
		dbDelta( $recs );
		dbDelta( $drafts );
		dbDelta( $comp );
		dbDelta( $auto );
		dbDelta( $mem );
		dbDelta( $out );

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * @return bool
	 */
	public static function tables_ready() {
		global $wpdb;
		$table = self::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $table === $found;
	}
}
