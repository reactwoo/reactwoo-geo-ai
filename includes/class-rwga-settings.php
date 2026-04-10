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
	 * Separate option so disconnect survives `rwga_settings` merges / boolean quirks in wp_options.
	 *
	 * @var string
	 */
	const OPTION_BLOCK_CORE_LICENSE_BRIDGE = 'rwga_block_core_license_bridge';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_clear_jwt_on_change' ), 10, 2 );
	}

	/**
	 * Legacy no-op: Geo AI now owns its own platform client and does not register shared license filters.
	 *
	 * @return void
	 */
	public static function register_platform_filters() {
		return;
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
	 * Legacy no-op: automatic cross-plugin license migration has been removed.
	 *
	 * @return void
	 */
	public static function maybe_migrate_from_geo_core() {
		return;
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
	 * Final effective license for the ReactWoo API (runs after Optimise / Commerce filters).
	 *
	 * @param string $key Value built by earlier filters (may include Geo Core via other plugins).
	 * @return string
	 */
	public static function filter_license_key_final( $key ) {
		$s      = self::get_settings();
		$rwga_k = isset( $s['reactwoo_license_key'] ) ? trim( (string) $s['reactwoo_license_key'] ) : '';
		if ( '' !== $rwga_k ) {
			return $rwga_k;
		}
		// Geo AI → Disconnect: no own key — do not keep using Core / Optimise / Commerce fallback keys
		// (those plugins often store a migrated copy of the same key, which defeated earlier strip logic).
		if ( self::is_geo_ai_license_disconnected() ) {
			return '';
		}
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Whether Geo AI itself has a saved license key.
	 * The License screen should reflect Geo AI's own saved product key, not a shared fallback key.
	 *
	 * @return bool
	 */
	public static function is_license_configured_for_geo_ai_ui() {
		$s      = self::get_settings();
		$rwga_k = isset( $s['reactwoo_license_key'] ) ? trim( (string) $s['reactwoo_license_key'] ) : '';
		return '' !== $rwga_k;
	}

	/**
	 * Geo AI → Disconnect: stop using Geo Core’s key when Geo AI’s own field is empty.
	 *
	 * @return bool
	 */
	private static function is_geo_ai_license_disconnected() {
		if ( 1 === (int) get_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, 0 ) ) {
			return true;
		}
		$raw = get_option( self::OPTION_KEY, array() );
		if ( is_array( $raw ) && array_key_exists( 'reactwoo_license_use_core_fallback', $raw ) ) {
			$v = $raw['reactwoo_license_use_core_fallback'];
			return false === $v || 0 === $v || '0' === $v;
		}
		return false;
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
		$s['reactwoo_license_key']                  = '';
		$s['reactwoo_license_use_core_fallback'] = false;
		update_option( self::OPTION_KEY, $s );
		update_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, 1 );
		delete_option( 'rwga_assistant_usage_cache' );
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			RWGA_Platform_Client::clear_token_cache();
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
			if ( class_exists( 'RWGA_Platform_Client', false ) ) {
				RWGA_Platform_Client::clear_token_cache();
			}
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_manual_import_sources() {
		$sources = array();
		foreach ( self::get_manual_import_source_map() as $source => $cfg ) {
			$raw = get_option( $cfg['option_key'], array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				$sources[ $source ] = (string) $cfg['label'];
			}
		}
		return $sources;
	}

	/**
	 * @param string $source Source key.
	 * @return true|\WP_Error
	 */
	public static function import_license_from_source( $source ) {
		$map = self::get_manual_import_source_map();
		if ( ! isset( $map[ $source ] ) ) {
			return new WP_Error( 'rwga_bad_import_source', __( 'Unknown import source.', 'reactwoo-geo-ai' ) );
		}

		$raw = get_option( $map[ $source ]['option_key'], array() );
		if ( ! is_array( $raw ) || empty( $raw['reactwoo_license_key'] ) ) {
			return new WP_Error( 'rwga_import_missing_key', __( 'The selected source does not have a saved license key.', 'reactwoo-geo-ai' ) );
		}

		$settings                         = self::get_settings();
		$settings['reactwoo_license_key'] = sanitize_text_field( (string) $raw['reactwoo_license_key'] );
		if ( ! empty( $raw['reactwoo_api_base'] ) ) {
			$base = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
			if ( $base && wp_http_validate_url( $base ) ) {
				$settings['reactwoo_api_base'] = untrailingslashit( $base );
			}
		}

		update_option( self::OPTION_KEY, self::sanitize_settings( $settings ) );
		delete_option( 'rwga_assistant_usage_cache' );
		delete_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE );
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			RWGA_Platform_Client::clear_token_cache();
		}
		return true;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function get_manual_import_source_map() {
		return array(
			'geo_optimise' => array(
				'label'      => __( 'Geo Optimise', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgo_settings',
			),
			'geo_commerce' => array(
				'label'      => __( 'Geo Commerce', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgcm_settings',
			),
			'geo_core_legacy' => array(
				'label'      => __( 'Geo Core (legacy)', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgc_settings',
			),
		);
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

		if ( 'advanced' === $scope && isset( $settings['ux_analysis_focus'] ) ) {
			$allowed = array( 'messaging', 'layout', 'both' );
			$f       = sanitize_key( (string) $settings['ux_analysis_focus'] );
			$out['ux_analysis_focus'] = in_array( $f, $allowed, true ) ? $f : ( isset( $prev['ux_analysis_focus'] ) ? (string) $prev['ux_analysis_focus'] : $defaults['ux_analysis_focus'] );
		}

		$new_license = isset( $settings['reactwoo_license_key'] ) ? sanitize_text_field( (string) $settings['reactwoo_license_key'] ) : '';
		$bridge_on   = ( 1 === (int) get_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, 0 ) );
		if ( 'license' === $scope || 'advanced' === $scope ) {
			// After Disconnect, do not restore the previous key when the field is empty or omitted (password inputs may be absent from POST).
			if ( $bridge_on && '' === trim( (string) $new_license ) ) {
				$out['reactwoo_license_key'] = '';
			} else {
				$out['reactwoo_license_key'] = ( '' !== $new_license ) ? $new_license : $prev_license;
			}
			if ( '' !== trim( (string) $out['reactwoo_license_key'] ) ) {
				$out['reactwoo_license_use_core_fallback'] = true;
				delete_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE );
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'reactwoo_api_base'                       => 'https://api.reactwoo.com',
			'reactwoo_license_key'                    => '',
			/** When true (default), empty Geo AI key may use Geo Core’s license. False after Disconnect. */
			'reactwoo_license_use_core_fallback'     => true,
			'workflow_engine'                        => 'local',
			'ux_analysis_focus'                      => 'messaging',
		);
	}
}
