<?php
/**
 * Geo AI — satellite plugin (requires Geo Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main controller for ReactWoo Geo AI.
 */
class RWGA_Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function boot() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_admin_notice_missing_core' ) );
		}

		if ( ! $this->is_geo_core_active() ) {
			return;
		}

		require_once RWGA_PATH . 'includes/class-rwga-platform-client.php';
		$this->load_workflow_engine();

		require_once RWGA_PATH . 'includes/class-rwga-cron.php';
		RWGA_Cron::init();

		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		RWGA_Settings::init();

		require_once RWGA_PATH . 'includes/class-rwga-connection.php';
		require_once RWGA_PATH . 'includes/class-rwga-stats.php';
		require_once RWGA_PATH . 'includes/class-rwga-usage.php';
		require_once RWGA_PATH . 'includes/class-rwga-drafts.php';
		require_once RWGA_PATH . 'includes/class-rwga-admin.php';
		require_once RWGA_PATH . 'includes/class-rwga-block-editor.php';
		RWGA_Admin::init();
		RWGA_Block_Editor::init();

		if ( class_exists( 'RWGC_Satellite_Updater', false ) ) {
			RWGC_Satellite_Updater::register(
				array(
					'basename'              => plugin_basename( RWGA_FILE ),
					'version'               => RWGA_VERSION,
					'catalog_slug'          => 'reactwoo-geo-ai',
					'name'                  => __( 'ReactWoo Geo AI', 'reactwoo-geo-ai' ),
					'description'           => __( 'AI-assisted geo variant drafts using the ReactWoo API with Geo Core.', 'reactwoo-geo-ai' ),
					'get_bearer_callback'   => array( 'RWGA_Platform_Client', 'get_bearer_for_updates' ),
					'get_api_base_callback' => array( 'RWGA_Platform_Client', 'get_api_base' ),
				)
			);
		}

		/**
		 * Fires when Geo AI satellite is ready (Geo Core is active).
		 */
		do_action( 'rwga_loaded' );
	}

	/**
	 * DB, capabilities, workflows, REST.
	 *
	 * @return void
	 */
	private function load_workflow_engine() {
		require_once RWGA_PATH . 'includes/helpers/rwga-site.php';
		require_once RWGA_PATH . 'includes/helpers/rwga-builder-text.php';
		require_once RWGA_PATH . 'includes/class-rwga-platform-client.php';
		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db.php';
		require_once RWGA_PATH . 'includes/class-rwga-install.php';
		RWGA_Install::maybe_upgrade();

		require_once RWGA_PATH . 'includes/class-rwga-capabilities.php';
		RWGA_Capabilities::install();

		require_once RWGA_PATH . 'includes/class-rwga-license.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-analysis-runs.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-analysis-findings.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-recommendations.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-implementation-drafts.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-competitor-research.php';
		require_once RWGA_PATH . 'includes/db/class-rwga-db-automation-rules.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-memory-service.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-automation-runner.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-engine.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-remote-client.php';
		require_once RWGA_PATH . 'includes/services/class-rwga-page-context.php';
		require_once RWGA_PATH . 'includes/workflows/interface-rwga-workflow.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-base.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-ux-analysis.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-ux-recommend.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-copy-implement.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-seo-implement.php';
		require_once RWGA_PATH . 'includes/workflows/class-rwga-workflow-competitor-research.php';
		require_once RWGA_PATH . 'includes/class-rwga-workflow-registry.php';
		RWGA_Workflow_Registry::init();

		require_once RWGA_PATH . 'includes/class-rwga-agent-registry.php';
		require_once RWGA_PATH . 'includes/api/class-rwga-rest.php';
		RWGA_REST::init();
	}

	/**
	 * @return bool
	 */
	private function is_geo_core_active() {
		if ( function_exists( 'rwgc_is_geo_core_active' ) ) {
			return (bool) rwgc_is_geo_core_active();
		}
		return class_exists( 'RWGC_Plugin', false )
			|| ( defined( 'RWGC_VERSION' ) && defined( 'RWGC_FILE' ) );
	}

	/**
	 * @return void
	 */
	public function maybe_admin_notice_missing_core() {
		if ( $this->is_geo_core_active() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'ReactWoo Geo AI requires ReactWoo Geo Core to be installed and active.', 'reactwoo-geo-ai' );
		echo '</p></div>';
	}
}
