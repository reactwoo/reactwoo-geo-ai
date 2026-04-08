<?php
/**
 * ReactWoo API URL + product license for Geo AI (commercial satellite — not stored in Geo Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for ReactWoo Geo AI.
 */
class RWGA_Settings {

	const OPTION_KEY = 'rwga_settings';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_clear_jwt_on_change' ), 10, 2 );
	}

	/**
	 * Register JWT filters as soon as Geo AI loads (before `init`).
	 *
	 * @return void
	 */
	public static function register_platform_filters() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_filter( 'rwgc_reactwoo_license_key', array( __CLASS__, 'filter_license_key' ), 10, 1 );
		add_filter( 'rwgc_reactwoo_api_base', array( __CLASS__, 'filter_api_base' ), 10, 1 );
		add_filter( 'rwgc_auth_login_body', array( __CLASS__, 'filter_auth_login_body' ), 10, 3 );
	}

	/**
	 * When this site uses Geo AI’s license key, tell the API which catalog product the token is for.
	 *
	 * @param array<string, string> $body    Login JSON body.
	 * @param string                $license Effective license key.
	 * @param string                $domain  Site host.
	 * @return array<string, string>
	 */
	public static function filter_auth_login_body( $body, $license, $domain ) {
		unset( $domain );
		$s = self::get_settings();
		$our = is_array( $s ) && isset( $s['reactwoo_license_key'] ) ? trim( (string) $s['reactwoo_license_key'] ) : '';
		if ( '' === $our || trim( (string) $license ) !== $our ) {
			return $body;
		}
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$body['product_slug']  = 'reactwoo-geo-ai';
		$body['catalog_slug'] = 'reactwoo-geo-ai';
		return $body;
	}

	/**
	 * Copy legacy Geo Core credentials once so existing sites keep working.
	 *
	 * @return void
	 */
	public static function maybe_migrate_from_geo_core() {
		$rwga = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $rwga ) ) {
			$rwga = array();
		}
		if ( ! empty( $rwga['reactwoo_license_key'] ) && ! empty( $rwga['reactwoo_api_base'] ) ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Settings', false ) ) {
			return;
		}
		$core = get_option( RWGC_Settings::OPTION_KEY, array() );
		if ( ! is_array( $core ) ) {
			return;
		}
		$changed = false;
		if ( empty( $rwga['reactwoo_license_key'] ) && ! empty( $core['reactwoo_license_key'] ) ) {
			$rwga['reactwoo_license_key'] = (string) $core['reactwoo_license_key'];
			$changed                      = true;
		}
		if ( empty( $rwga['reactwoo_api_base'] ) && ! empty( $core['reactwoo_api_base'] ) ) {
			$rwga['reactwoo_api_base'] = (string) $core['reactwoo_api_base'];
			$changed                   = true;
		}
		if ( $changed ) {
			update_option( self::OPTION_KEY, self::sanitize_settings( $rwga ) );
		}
	}

	/**
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'rwga_license_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * @param string $key Default from Core.
	 * @return string
	 */
	public static function filter_license_key( $key ) {
		$s = self::get_settings();
		if ( is_array( $s ) && isset( $s['reactwoo_license_key'] ) ) {
			$k = trim( (string) $s['reactwoo_license_key'] );
			if ( '' !== $k ) {
				return $k;
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				return trim( (string) $raw['reactwoo_license_key'] );
			}
		}
		return (string) $key;
	}

	/**
	 * @param string $base Default URL.
	 * @return string
	 */
	public static function filter_api_base( $base ) {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) ) {
			$c = trim( (string) RWGA_REACTWOO_API_BASE );
			if ( '' !== $c && wp_http_validate_url( $c ) ) {
				return untrailingslashit( esc_url_raw( $c ) );
			}
		}

		$via_filter = apply_filters( 'rwga_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$u = esc_url_raw( trim( $via_filter ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}

		$s = self::get_settings();
		if ( is_array( $s ) && ! empty( $s['reactwoo_api_base'] ) ) {
			$u = esc_url_raw( trim( (string) $s['reactwoo_api_base'] ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_api_base'] ) ) {
				$u = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
				if ( $u && wp_http_validate_url( $u ) ) {
					return untrailingslashit( $u );
				}
			}
		}
		$def = is_string( $base ) && '' !== trim( $base ) ? trim( $base ) : 'https://api.reactwoo.com';
		return untrailingslashit( $def );
	}

	/**
	 * Whether the API base field may be shown and saved (Advanced / support).
	 *
	 * @return bool
	 */
	public static function can_edit_api_base_field() {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) && '' !== trim( (string) RWGA_REACTWOO_API_BASE ) ) {
			return false;
		}
		if ( defined( 'RWGA_SHOW_API_BASE_UI' ) && RWGA_SHOW_API_BASE_UI ) {
			return true;
		}
		return (bool) apply_filters( 'rwga_show_api_base_field', false );
	}

	/**
	 * Clear saved license key (disconnect).
	 *
	 * @return void
	 */
	public static function clear_license_key() {
		$s = self::get_settings();
		$s['reactwoo_license_key'] = '';
		update_option( self::OPTION_KEY, $s );
		if ( class_exists( 'RWGC_Platform_Client', false ) ) {
			RWGC_Platform_Client::clear_token_cache();
		}
	}

	/**
	 * @param mixed $old_value Previous option.
	 * @param mixed $value     New option.
	 * @return void
	 */
	public static function maybe_clear_jwt_on_change( $old_value, $value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$val = is_array( $value ) ? $value : array();
		$o_k = isset( $old['reactwoo_license_key'] ) ? (string) $old['reactwoo_license_key'] : '';
		$n_k = isset( $val['reactwoo_license_key'] ) ? (string) $val['reactwoo_license_key'] : '';
		$o_b = isset( $old['reactwoo_api_base'] ) ? (string) $old['reactwoo_api_base'] : '';
		$n_b = isset( $val['reactwoo_api_base'] ) ? (string) $val['reactwoo_api_base'] : '';
		if ( $o_k !== $n_k || $o_b !== $n_b ) {
			if ( class_exists( 'RWGC_Platform_Client', false ) ) {
				RWGC_Platform_Client::clear_token_cache();
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param array $input Raw.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults     = self::get_defaults();
		$settings     = is_array( $input ) ? $input : array();
		$prev         = get_option( self::OPTION_KEY, array() );
		$prev         = is_array( $prev ) ? $prev : array();
		$out          = array_merge( $defaults, $prev );
		$scope        = isset( $settings['rwga_form_scope'] ) ? sanitize_key( (string) $settings['rwga_form_scope'] ) : 'license';
		$prev_license = isset( $prev['reactwoo_license_key'] ) ? (string) $prev['reactwoo_license_key'] : '';

		if ( 'advanced' === $scope && self::can_edit_api_base_field() ) {
			$base = isset( $settings['reactwoo_api_base'] ) ? trim( (string) $settings['reactwoo_api_base'] ) : '';
			$base = esc_url_raw( $base );
			$out['reactwoo_api_base'] = ( $base && wp_http_validate_url( $base ) )
				? untrailingslashit( $base )
				: ( isset( $prev['reactwoo_api_base'] ) ? (string) $prev['reactwoo_api_base'] : $defaults['reactwoo_api_base'] );
		} else {
			if ( isset( $prev['reactwoo_api_base'] ) ) {
				$out['reactwoo_api_base'] = (string) $prev['reactwoo_api_base'];
			}
		}

		if ( 'advanced' === $scope && isset( $settings['workflow_engine'] ) ) {
			$allowed = array( 'local', 'remote', 'remote_fallback' );
			$w       = sanitize_key( (string) $settings['workflow_engine'] );
			$out['workflow_engine'] = in_array( $w, $allowed, true ) ? $w : ( isset( $prev['workflow_engine'] ) ? (string) $prev['workflow_engine'] : $defaults['workflow_engine'] );
		}

		$new_license = isset( $settings['reactwoo_license_key'] ) ? sanitize_text_field( (string) $settings['reactwoo_license_key'] ) : '';
		if ( 'license' === $scope || 'advanced' === $scope ) {
			$out['reactwoo_license_key'] = ( '' !== $new_license ) ? $new_license : $prev_license;
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'reactwoo_api_base'     => 'https://api.reactwoo.com',
			'reactwoo_license_key'  => '',
			'workflow_engine'       => 'local',
		);
	}
}
