<?php
/**
 * Cached assistant token usage from GET /api/v5/ai/assistant/usage (Geo Core orchestrator).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists last successful usage response for the Geo AI dashboard.
 */
class RWGA_Usage {

	const OPTION_KEY = 'rwga_assistant_usage_cache';

	/**
	 * @param array<string, mixed>|null $body     JSON body from {@see RWGA_Platform_Client::get_usage()}.
	 * @param int                       $http_code HTTP status.
	 * @return bool True if cache was updated.
	 */
	public static function save_from_api_body( $body, $http_code ) {
		$norm = self::normalize_body( $body );
		if ( null === $norm ) {
			return false;
		}
		$payload = array(
			'refreshed_at_gmt' => gmdate( 'c' ),
			'http_code'        => (int) $http_code,
			'license_tier'     => $norm['license_tier'],
			'period'           => $norm['period'],
			'used'             => $norm['used'],
			'limit'            => $norm['limit'],
			'remaining'        => $norm['remaining'],
			'over_limit'       => $norm['over_limit'],
			'plan_limits'      => $norm['plan_limits'],
		);
		update_option( self::OPTION_KEY, $payload, false );
		return true;
	}

	/**
	 * @param array<string, mixed>|null $body API JSON (success shape).
	 * @return array<string, mixed>|null
	 */
	private static function normalize_body( $body ) {
		if ( ! is_array( $body ) ) {
			return null;
		}
		$inner = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : null;
		if ( null === $inner ) {
			return null;
		}
		$usage = isset( $inner['usage'] ) && is_array( $inner['usage'] ) ? $inner['usage'] : null;
		if ( null === $usage ) {
			return null;
		}
		$plan_limits = isset( $inner['planLimits'] ) && is_array( $inner['planLimits'] ) ? $inner['planLimits'] : array();
		$tier        = isset( $inner['licenseTier'] ) ? sanitize_key( (string) $inner['licenseTier'] ) : '';

		return array(
			'license_tier' => $tier,
			'period'       => isset( $usage['period'] ) ? sanitize_text_field( (string) $usage['period'] ) : '',
			'used'         => isset( $usage['used'] ) ? (int) $usage['used'] : 0,
			'limit'        => isset( $usage['limit'] ) ? (int) $usage['limit'] : 0,
			'remaining'    => isset( $usage['remaining'] ) ? (int) $usage['remaining'] : 0,
			'over_limit'   => ! empty( $usage['overLimit'] ),
			'plan_limits'  => $plan_limits,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_cache() {
		if ( class_exists( 'RWGA_Settings', false ) && ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			if ( false !== get_option( self::OPTION_KEY, false ) ) {
				delete_option( self::OPTION_KEY );
			}
			return null;
		}
		$c = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $c ) ) {
			return null;
		}
		if ( ! isset( $c['refreshed_at_gmt'], $c['used'], $c['limit'] ) ) {
			return null;
		}
		return $c;
	}

	/**
	 * Rows for a widefat table (label => display value).
	 *
	 * @return array<string, string>
	 */
	/**
	 * Human-readable plan line for dashboard / license (tier + token cap from last API refresh).
	 *
	 * @param array<string, mixed>|null $cache From {@see get_cache()}.
	 * @return string
	 */
	public static function format_plan_label( $cache ) {
		if ( ! is_array( $cache ) ) {
			return '';
		}
		if ( class_exists( 'RWGA_Settings', false ) && ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			return '';
		}
		$tier = isset( $cache['license_tier'] ) ? sanitize_key( (string) $cache['license_tier'] ) : '';
		if ( '' === $tier ) {
			$tier = 'free';
		}
		$limit = isset( $cache['limit'] ) ? (int) $cache['limit'] : 0;
		$names = array(
			'free'       => __( 'Free', 'reactwoo-geo-ai' ),
			'pro'        => __( 'Pro', 'reactwoo-geo-ai' ),
			'enterprise' => __( 'Enterprise', 'reactwoo-geo-ai' ),
		);
		$name = isset( $names[ $tier ] ) ? $names[ $tier ] : ucfirst( $tier );
		if ( $limit > 0 ) {
			return sprintf(
				/* translators: 1: plan name (Free, Pro, Enterprise), 2: formatted token limit */
				__( '%1$s — %2$s assistant tokens this billing period (API quota)', 'reactwoo-geo-ai' ),
				$name,
				number_format_i18n( $limit )
			);
		}
		return $name;
	}

	public static function get_display_rows() {
		$c = self::get_cache();
		if ( null === $c ) {
			return array();
		}

		$rows = array(
			__( 'Last refreshed (GMT)', 'reactwoo-geo-ai' ) => (string) $c['refreshed_at_gmt'],
			__( 'License tier', 'reactwoo-geo-ai' )        => (string) ( $c['license_tier'] ?? '' ),
			__( 'Billing period', 'reactwoo-geo-ai' )       => (string) ( $c['period'] ?? '' ),
			__( 'Tokens used', 'reactwoo-geo-ai' )          => (string) (int) ( $c['used'] ?? 0 ),
			__( 'Plan limit', 'reactwoo-geo-ai' )            => (string) (int) ( $c['limit'] ?? 0 ),
			__( 'Remaining', 'reactwoo-geo-ai' )             => (string) (int) ( $c['remaining'] ?? 0 ),
			__( 'Over limit', 'reactwoo-geo-ai' )            => ! empty( $c['over_limit'] ) ? __( 'Yes', 'reactwoo-geo-ai' ) : __( 'No', 'reactwoo-geo-ai' ),
		);

		if ( ! empty( $c['plan_limits'] ) && is_array( $c['plan_limits'] ) ) {
			$enc = wp_json_encode( $c['plan_limits'], JSON_UNESCAPED_SLASHES );
			if ( is_string( $enc ) ) {
				$rows[ __( 'Plan limits (all tiers)', 'reactwoo-geo-ai' ) ] = $enc;
			}
		}

		/**
		 * Filter dashboard labels/values for cached assistant usage.
		 *
		 * @param array<string, string>  $rows Row labels to values.
		 * @param array<string, mixed>   $cache Raw cache option.
		 */
		return apply_filters( 'rwga_usage_display_rows', $rows, $c );
	}
}
