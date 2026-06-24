<?php
/**
 * Plugin Name: ReactWoo Geo AI
 * Description: AI-assisted geo variant drafts for WordPress. Requires ReactWoo Geo Core. Uses ReactWoo API; not a replacement for Geo Core detection.
 * Version: 0.4.122
 * Author: ReactWoo
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reactwoo-geo-ai
 * Requires Plugins: reactwoo-geocore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RWGA_VERSION' ) ) {
	define( 'RWGA_VERSION', '0.4.122' );
}
if ( ! defined( 'RWGA_FILE' ) ) {
	define( 'RWGA_FILE', __FILE__ );
}
if ( ! defined( 'RWGA_PATH' ) ) {
	define( 'RWGA_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RWGA_URL' ) ) {
	define( 'RWGA_URL', plugin_dir_url( __FILE__ ) );
}

add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'RWGC_I18n', false ) ) {
			RWGC_I18n::bootstrap( RWGA_FILE, 'reactwoo-geo-ai' );
		}
	},
	6
);

require_once RWGA_PATH . 'includes/class-rwga-plugin.php';

register_activation_hook(
	RWGA_FILE,
	static function () {
		require_once RWGA_PATH . 'includes/class-rwga-install.php';
		RWGA_Install::activate();
	}
);

register_deactivation_hook(
	RWGA_FILE,
	static function () {
		require_once RWGA_PATH . 'includes/class-rwga-cron.php';
		RWGA_Cron::deactivate();
		if ( class_exists( 'RWGA_Weather_Catalog_Audit', false ) ) {
			RWGA_Weather_Catalog_Audit::deactivate();
		} else {
			require_once RWGA_PATH . 'includes/services/class-rwga-weather-catalog-audit.php';
			RWGA_Weather_Catalog_Audit::deactivate();
		}
	}
);

/**
 * Bootstrap after Geo Core (plugins_loaded priority 20).
 *
 * @return void
 */
function rwga_boot() {
	RWGA_Plugin::instance()->boot();
}

add_action( 'plugins_loaded', 'rwga_boot', 20 );
