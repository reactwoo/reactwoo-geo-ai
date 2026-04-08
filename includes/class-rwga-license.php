<?php
/**
 * License and product availability for Geo AI workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin checks: key on file means workflows may run (API still validates server-side).
 */
class RWGA_License {

	/**
	 * Whether a ReactWoo license key is configured for this plugin.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		if ( ! class_exists( 'RWGA_Settings', false ) ) {
			return false;
		}
		$s = RWGA_Settings::get_settings();
		$k = is_array( $s ) && isset( $s['reactwoo_license_key'] ) ? trim( (string) $s['reactwoo_license_key'] ) : '';
		return '' !== $k;
	}

	/**
	 * Workflows require a configured license (per product rules).
	 *
	 * @return bool
	 */
	public static function can_run_workflows() {
		return self::is_configured();
	}
}
