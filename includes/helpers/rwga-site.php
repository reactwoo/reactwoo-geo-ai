<?php
/**
 * Site identity helpers for Geo AI records.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable per-install UUID for workflow records and API payloads.
 *
 * @return string
 */
function rwga_get_site_uuid() {
	$u = get_option( 'rwga_site_uuid', '' );
	if ( is_string( $u ) && '' !== $u ) {
		return $u;
	}
	if ( function_exists( 'wp_generate_uuid4' ) ) {
		$u = wp_generate_uuid4();
	} else {
		$u = md5( (string) home_url() );
	}
	update_option( 'rwga_site_uuid', $u, false );
	return $u;
}
