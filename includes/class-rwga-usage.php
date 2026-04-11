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
			'refreshed_at_gmt'     => gmdate( 'c' ),
			'http_code'            => (int) $http_code,
			'license_tier'         => $norm['license_tier'],
			'api_license_tier_raw' => isset( $norm['api_license_tier_raw'] ) ? (string) $norm['api_license_tier_raw'] : '',
			'period'               => $norm['period'],
			'used'                 => $norm['used'],
			'limit'                => $norm['limit'],
			'remaining'            => $norm['remaining'],
			'over_limit'           => $norm['over_limit'],
			'plan_limits'          => $norm['plan_limits'],
		);
		if ( class_exists( 'RWGA_License_Introspection', false ) ) {
			$claims = RWGA_License_Introspection::get_bearer_claims();
			if ( is_array( $claims ) ) {
				$payload['jwt_domain']         = isset( $claims['domain'] ) ? sanitize_text_field( (string) $claims['domain'] ) : '';
				$payload['jwt_product_slug']  = isset( $claims['product_slug'] ) ? sanitize_key( (string) $claims['product_slug'] ) : '';
				$payload['jwt_catalog_slug']  = isset( $claims['catalog_slug'] ) ? sanitize_key( (string) $claims['catalog_slug'] ) : '';
				$payload['jwt_package_type']  = isset( $claims['packageType'] ) ? sanitize_text_field( (string) $claims['packageType'] ) : '';
				$payload['jwt_plan_code']     = isset( $claims['plan_code'] ) ? sanitize_text_field( (string) $claims['plan_code'] ) : '';
				$payload['jwt_tier']          = RWGA_License_Introspection::tier_from_claims( $claims );
				$payload['jwt_package_label'] = RWGA_License_Introspection::format_package_summary( $claims );
			}
		}
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
		// API returns { status, data: { usage, licenseTier, planLimits } }; accept unwrapped payloads too.
		$inner = null;
		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$inner = $body['data'];
		} elseif ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) {
			$inner = $body;
		}
		if ( null === $inner || ! is_array( $inner ) ) {
			return null;
		}
		// Some gateways double-wrap: { data: { data: { usage, licenseTier } } }.
		if ( isset( $inner['data'] ) && is_array( $inner['data'] ) && isset( $inner['data']['usage'] ) && is_array( $inner['data']['usage'] ) ) {
			$inner = $inner['data'];
		}
		$usage = isset( $inner['usage'] ) && is_array( $inner['usage'] ) ? $inner['usage'] : null;
		if ( null === $usage ) {
			return null;
		}
		$plan_limits = array();
		if ( isset( $inner['planLimits'] ) && is_array( $inner['planLimits'] ) ) {
			$plan_limits = $inner['planLimits'];
		} elseif ( isset( $inner['plan_limits'] ) && is_array( $inner['plan_limits'] ) ) {
			$plan_limits = $inner['plan_limits'];
		}
		$tier = '';
		if ( isset( $inner['licenseTier'] ) ) {
			$tier = sanitize_key( (string) $inner['licenseTier'] );
		} elseif ( isset( $inner['license_tier'] ) ) {
			$tier = sanitize_key( (string) $inner['license_tier'] );
		}
		if ( '' === $tier && is_array( $usage ) ) {
			if ( isset( $usage['licenseTier'] ) ) {
				$tier = sanitize_key( (string) $usage['licenseTier'] );
			} elseif ( isset( $usage['license_tier'] ) ) {
				$tier = sanitize_key( (string) $usage['license_tier'] );
			}
		}
		if ( '' === $tier && is_array( $body ) ) {
			if ( isset( $body['licenseTier'] ) ) {
				$tier = sanitize_key( (string) $body['licenseTier'] );
			} elseif ( isset( $body['license_tier'] ) ) {
				$tier = sanitize_key( (string) $body['license_tier'] );
			}
		}

		$used      = isset( $usage['used'] ) ? (int) $usage['used'] : ( isset( $usage['used_tokens'] ) ? (int) $usage['used_tokens'] : 0 );
		$limit     = isset( $usage['limit'] ) ? (int) $usage['limit'] : 0;
		$remaining = isset( $usage['remaining'] ) ? (int) $usage['remaining'] : 0;
		$period    = '';
		if ( isset( $usage['period'] ) ) {
			$period = sanitize_text_field( (string) $usage['period'] );
		} elseif ( isset( $usage['billing_period'] ) ) {
			$period = sanitize_text_field( (string) $usage['billing_period'] );
		}
		$over = ! empty( $usage['overLimit'] ) || ! empty( $usage['over_limit'] );

		$api_license_tier_raw = is_string( $tier ) ? sanitize_key( $tier ) : '';
		$tier                 = self::normalize_tier_with_limit( $tier, $limit );

		return array(
			'license_tier'         => $tier,
			'api_license_tier_raw' => $api_license_tier_raw,
			'period'               => $period,
			'used'                 => $used,
			'limit'                => $limit,
			'remaining'            => $remaining,
			'over_limit'           => $over,
			'plan_limits'          => $plan_limits,
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
	 * Infer plan tier from the API token cap when licenseTier is missing or stale.
	 * Fills the gap between the free cap (~100k) and 1M+ quotas so mid-tier Pro limits are not shown as Free.
	 *
	 * @param int $limit Assistant token limit for the billing period.
	 * @return string One of free, pro, enterprise, or '' when unknown.
	 */
	private static function infer_tier_from_token_limit( $limit ) {
		$lim = (int) $limit;
		if ( $lim <= 0 ) {
			return '';
		}
		if ( $lim >= 9_000_000 ) {
			return 'enterprise';
		}
		if ( $lim >= 1_000_000 ) {
			return 'pro';
		}
		if ( $lim <= 100_000 ) {
			return 'free';
		}
		return 'pro';
	}

	/**
	 * Combine API tier with token limit so cached usage matches the real quota (paid caps vs stale "free").
	 *
	 * @param string $tier  Sanitized tier from API (may be empty).
	 * @param int    $limit Token limit for the period.
	 * @return string
	 */
	private static function normalize_tier_with_limit( $tier, $limit ) {
		$tier = is_string( $tier ) ? sanitize_key( $tier ) : '';
		$inf  = self::infer_tier_from_token_limit( $limit );
		if ( '' === $tier ) {
			return '' !== $inf ? $inf : 'free';
		}
		if ( 'free' === $tier && '' !== $inf && 'free' !== $inf ) {
			return $inf;
		}
		return $tier;
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
		$tier  = isset( $cache['license_tier'] ) ? sanitize_key( (string) $cache['license_tier'] ) : '';
		$limit = isset( $cache['limit'] ) ? (int) $cache['limit'] : 0;
		$tier  = self::normalize_tier_with_limit( $tier, $limit );
		if ( '' === $tier ) {
			$tier = 'free';
		}
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
