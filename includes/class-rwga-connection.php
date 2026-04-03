<?php
/**
 * Read-only snapshot of Geo Core ReactWoo API settings (credentials stay in Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection summary for Geo AI admin (no secrets exposed).
 */
class RWGA_Connection {

	/**
	 * @return array{core_ready: bool, api_base: string, license_configured: bool, rest_enabled: bool}
	 */
	public static function get_summary() {
		if ( ! class_exists( 'RWGC_Settings', false ) ) {
			return array(
				'core_ready'           => false,
				'api_base'             => '',
				'license_configured'   => false,
				'rest_enabled'         => false,
			);
		}

		$key = RWGC_Settings::get( 'reactwoo_license_key', '' );
		$key = is_string( $key ) ? trim( $key ) : '';

		$api_base = 'https://api.reactwoo.com';
		if ( class_exists( 'RWGC_Platform_Client', false ) ) {
			$api_base = RWGC_Platform_Client::get_api_base();
		} else {
			$stored = RWGC_Settings::get( 'reactwoo_api_base', $api_base );
			$api_base = is_string( $stored ) && '' !== trim( $stored ) ? trim( $stored ) : $api_base;
		}

		return array(
			'core_ready'         => true,
			'api_base'           => $api_base,
			'license_configured' => '' !== $key,
			'rest_enabled'       => (bool) RWGC_Settings::get( 'rest_enabled', 1 ),
		);
	}
}
