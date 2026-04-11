<?php
/**
 * Geo AI-owned ReactWoo API client and JWT cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Platform_Client {

	const TOKEN_TRANSIENT  = 'rwga_rw_jwt_cache';
	const LOGIN_PATH       = '/api/v5/auth/login';
	const DEFAULT_API_BASE = 'https://api.reactwoo.com';
	const PRODUCT_SLUG     = 'reactwoo-geo-ai';

	/**
	 * @return string
	 */
	public static function get_api_base() {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) ) {
			$configured = trim( (string) RWGA_REACTWOO_API_BASE );
			if ( '' !== $configured && wp_http_validate_url( $configured ) ) {
				return untrailingslashit( esc_url_raw( $configured ) );
			}
		}

		$via_filter = apply_filters( 'rwga_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$filtered = esc_url_raw( trim( $via_filter ) );
			if ( $filtered && wp_http_validate_url( $filtered ) ) {
				return untrailingslashit( $filtered );
			}
		}

		if ( class_exists( 'RWGA_Settings', false ) ) {
			$settings = RWGA_Settings::get_settings();
			if ( is_array( $settings ) && ! empty( $settings['reactwoo_api_base'] ) ) {
				$saved = esc_url_raw( trim( (string) $settings['reactwoo_api_base'] ) );
				if ( $saved && wp_http_validate_url( $saved ) ) {
					return untrailingslashit( $saved );
				}
			}
		}

		return self::DEFAULT_API_BASE;
	}

	/**
	 * @return string
	 */
	public static function get_license_key() {
		if ( ! class_exists( 'RWGA_Settings', false ) ) {
			return '';
		}
		return RWGA_Settings::get_saved_license_key();
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_license_key();
	}

	/**
	 * @return string
	 */
	public static function get_site_domain() {
		$home = home_url( '/' );
		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		return '';
	}

	/**
	 * @return void
	 */
	public static function clear_token_cache() {
		delete_transient( self::TOKEN_TRANSIENT );
	}

	/**
	 * Cached access token string for admin introspection (decoded payload only in UI; not for transport).
	 *
	 * @return string|null
	 */
	public static function get_cached_access_token_string() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( ! is_array( $cached ) || empty( $cached['token'] ) ) {
			return null;
		}
		return (string) $cached['token'];
	}

	/**
	 * @return string|null
	 */
	public static function get_bearer_for_updates() {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * @return string|\WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && isset( $cached['expires'] ) && (int) $cached['expires'] > time() + 120 ) {
			return (string) $cached['token'];
		}

		$license = self::get_license_key();
		if ( '' === $license ) {
			return new WP_Error( 'rwga_no_license', __( 'Save a Geo AI license key before using ReactWoo API features.', 'reactwoo-geo-ai' ) );
		}

		$domain = self::get_site_domain();
		if ( '' === $domain ) {
			return new WP_Error( 'rwga_no_domain', __( 'Could not determine this site domain for license login.', 'reactwoo-geo-ai' ) );
		}

		$body = array(
			'license_key'  => $license,
			'domain'       => $domain,
			'product_slug' => self::PRODUCT_SLUG,
			'catalog_slug' => self::PRODUCT_SLUG,
		);
		/**
		 * Same hook as Geo Core {@see RWGC_Platform_Client::get_access_token()} so login JSON
		 * matches multi-product expectations (license server / api.reactwoo.com proxy).
		 *
		 * @param array<string, string> $body    Login JSON body.
		 * @param string                $license License key used for this login.
		 * @param string                $domain  Site host.
		 */
		$filtered = apply_filters( 'rwgc_auth_login_body', $body, $license, $domain );
		$body     = is_array( $filtered ) ? $filtered : $body;

		$response = wp_remote_post(
			self::get_api_base() . self::LOGIN_PATH,
			array(
				'timeout' => 30,
				'headers' => self::base_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'License login failed.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_login_failed', $msg, array( 'status' => $code ) );
		}

		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			return new WP_Error( 'rwga_login_no_token', __( 'License login response did not include a token.', 'reactwoo-geo-ai' ) );
		}

		$ttl = min( self::parse_expires_in( isset( $data['expires_in'] ) ? $data['expires_in'] : null ), 23 * HOUR_IN_SECONDS );
		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token'   => $token,
				'expires' => time() + $ttl,
			),
			$ttl
		);

		return $token;
	}

	/**
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Absolute path starting with /.
	 * @param array<string, mixed>|null $body   Request body.
	 * @param bool                      $auth   Whether to send Authorization.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request( $method, $path, $body = null, $auth = true ) {
		$path = is_string( $path ) ? $path : '';
		if ( '' === $path || '/' !== $path[0] ) {
			return new WP_Error( 'rwga_bad_path', __( 'Invalid API path.', 'reactwoo-geo-ai' ) );
		}

		$headers = self::base_headers();
		if ( $auth ) {
			$token = self::get_access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => $headers,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::get_api_base() . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( 401 === $code && $auth ) {
			self::clear_token_cache();
		}

		return array(
			'code'     => $code,
			'data'     => is_array( $data ) ? $data : null,
			'body_raw' => $raw,
		);
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function ai_health() {
		$response = wp_remote_post(
			self::get_api_base() . '/ai/health',
			array(
				'timeout' => 15,
				'headers' => self::base_headers(),
				'body'    => '{}',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : null,
		);
	}

	/**
	 * GET /api/v5/ai/assistant/usage — success JSON shape:
	 * `{ status: 'success', data: { usage, planLimits, licenseTier } }` (reactwoo-api `assistant.ts`).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_usage() {
		$result = self::request( 'GET', '/api/v5/ai/assistant/usage', null, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;
		if ( $code < 200 || $code >= 300 || null === $data ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : __( 'ReactWoo API request failed.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_api_error', $msg, array( 'status' => $code, 'data' => $data ) );
		}

		return array(
			'success'   => true,
			'http_code' => $code,
			'body'      => $data,
		);
	}

	/**
	 * @param mixed $raw Expiry from API.
	 * @return int
	 */
	private static function parse_expires_in( $raw ) {
		if ( is_numeric( $raw ) ) {
			return max( 300, (int) $raw );
		}
		if ( is_string( $raw ) && preg_match( '/^(\d+)h$/i', $raw, $m ) ) {
			return max( 300, (int) $m[1] * HOUR_IN_SECONDS );
		}
		return DAY_IN_SECONDS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function base_headers() {
		return array(
			'Content-Type'     => 'application/json',
			'X-Requested-With' => 'XMLHttpRequest',
		);
	}
}
