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

		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		RWGA_Settings::register_platform_filters();
		RWGA_Settings::maybe_migrate_from_geo_core();
		RWGA_Settings::init();

		require_once RWGA_PATH . 'includes/class-rwga-connection.php';
		require_once RWGA_PATH . 'includes/class-rwga-stats.php';
		require_once RWGA_PATH . 'includes/class-rwga-usage.php';
		require_once RWGA_PATH . 'includes/class-rwga-admin.php';
		require_once RWGA_PATH . 'includes/class-rwga-block-editor.php';
		RWGA_Admin::init();
		RWGA_Block_Editor::init();

		/**
		 * Fires when Geo AI satellite is ready (Geo Core is active).
		 */
		do_action( 'rwga_loaded' );
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
