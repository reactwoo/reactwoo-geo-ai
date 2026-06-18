<?php
/**
 * Parse multi-variant creation phrases (country-specific page versions).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Multi_Variant_Interpreter {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Resolved context.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities, array $context = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase || ! preg_match( '/\bvariants?\b|\bversions?\b/i', $phrase ) ) {
			return array( 'matched' => false );
		}

		$page_ref = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;

		$variants = self::extract_variants( $phrase, $entities );
		if ( count( $variants ) < 2 && ! preg_match( '/\btwo\b|\b2\b/', $phrase ) ) {
			return array( 'matched' => false );
		}
		if ( empty( $variants ) ) {
			return array( 'matched' => false );
		}

		if ( 1 === count( $variants ) && preg_match( '/\btwo\b|\b2\b/', $phrase ) ) {
			$variants = self::split_dual_segment( $phrase, $entities );
		}

		if ( count( $variants ) < 1 ) {
			return array( 'matched' => false );
		}

		$page_value = $page_ref ? (string) ( $page_ref['value'] ?? 'homepage' ) : self::extract_page_token( $phrase );
		$steps      = array();
		foreach ( $variants as $idx => $variant ) {
			$countries = $variant['countries'] ?? array();
			$label     = 1 === count( $countries )
				? sprintf(
					/* translators: %s: country code or name */
					__( 'Create homepage variant for %s', 'reactwoo-geocore' ),
					implode( ', ', $countries )
				)
				: sprintf(
					/* translators: %s: comma-separated country list */
					__( 'Create homepage variant for %s', 'reactwoo-geocore' ),
					implode( ' + ', $countries )
				);
			$steps[] = array(
				'label'  => str_replace( 'homepage', $page_value, $label ),
				'action' => 'geocore_create_variant',
				'params' => array(
					'page_ref'  => $page_value,
					'countries' => $countries,
					'mode'      => 'include_only',
					'name'      => sprintf( 'Variant %d', $idx + 1 ),
				),
			);
		}

		return array(
			'matched'        => true,
			'intent'         => 'create_geo_variants',
			'matched_action' => 'geocore_create_variants_with_country_rules',
			'confidence'     => min( 0.98, 0.72 + ( 0.08 * count( $variants ) ) ),
			'page_ref'       => $page_ref,
			'params'         => array(
				'page_ref' => $page_value,
				'variants' => array_map(
					static function ( $v ) {
						return array(
							'countries' => $v['countries'] ?? array(),
							'mode'      => 'include_only',
						);
					},
					$variants
				),
			),
			'steps'          => $steps,
			'summary'        => self::build_summary( $page_value, $variants ),
		);
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array{countries:array<int,string>}>
	 */
	private static function split_dual_segment( $phrase, array $entities ) {
		$patterns = array(
			'/one will display in (.+?) only the other in (.+)$/i',
			'/one for (.+?) and (?:one|another) for (.+)$/i',
			'/one version for (.+?) and another for (.+)$/i',
			'/one in (.+?) and (?:one|another) in (.+)$/i',
		);
		foreach ( $patterns as $regex ) {
			if ( preg_match( $regex, $phrase, $m ) ) {
				$a = self::parse_country_list( trim( $m[1] ), $entities );
				$b = self::parse_country_list( trim( $m[2] ), $entities );
				if ( ! empty( $a ) && ! empty( $b ) ) {
					return array(
						array( 'countries' => $a ),
						array( 'countries' => $b ),
					);
				}
			}
		}
		return array();
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array{countries:array<int,string>}>
	 */
	private static function extract_variants( $phrase, array $entities ) {
		$dual = self::split_dual_segment( $phrase, $entities );
		if ( ! empty( $dual ) ) {
			return $dual;
		}
		$all = self::parse_country_list( $phrase, $entities );
		if ( count( $all ) >= 2 && preg_match( '/\bvariants?\b/', $phrase ) ) {
			return array(
				array( 'countries' => array( $all[0] ) ),
				array( 'countries' => array_slice( $all, 1 ) ),
			);
		}
		return array();
	}

	/**
	 * @param string           $segment  Text segment.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<int,string>
	 */
	public static function parse_country_list( $segment, array $entities ) {
		$segment = RWGA_Local_Intent_Interpreter::normalise( $segment );
		$segment = preg_replace( '/\b(only|just|display|show|will|the|other|in|for|and)\b/i', ' ', $segment );
		$segment = preg_replace( '/\s+/', ' ', trim( (string) $segment ) );
		$found   = array();
		$indexed = self::index_countries( $entities );
		foreach ( $indexed as $code => $aliases ) {
			foreach ( $aliases as $alias ) {
				$alias = RWGA_Local_Intent_Interpreter::normalise( $alias );
				if ( '' !== $alias && false !== strpos( $segment, $alias ) ) {
					if ( ! in_array( $code, $found, true ) ) {
						$found[] = $code;
					}
					$segment = str_replace( $alias, ' ', $segment );
				}
			}
		}
		return $found;
	}

	/**
	 * @param array<int,array> $entities Entities.
	 * @return array<string,array<int,string>>
	 */
	private static function index_countries( array $entities ) {
		$out = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== 'country' ) {
				continue;
			}
			$code = (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
			if ( '' === $code ) {
				continue;
			}
			$aliases   = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = strtolower( (string) ( $row['display_name'] ?? '' ) );
			$aliases[] = $code;
			$out[ $code ] = array_values( array_unique( array_filter( $aliases ) ) );
		}
		return $out;
	}

	/**
	 * @param string $phrase Phrase.
	 * @return string
	 */
	private static function extract_page_token( $phrase ) {
		if ( preg_match( '/variants? of (?:the )?([a-z0-9\s-]+?)(?:\s+one|\s*,|\s*$)/i', $phrase, $m ) ) {
			return trim( $m[1] );
		}
		return 'homepage';
	}

	/**
	 * @param string                              $page     Page ref.
	 * @param array<int,array{countries:array}> $variants Variants.
	 * @return string
	 */
	private static function build_summary( $page, array $variants ) {
		$parts = array();
		foreach ( $variants as $v ) {
			$codes = $v['countries'] ?? array();
			$parts[] = implode( ' + ', $codes );
		}
		return sprintf(
			/* translators: 1: page reference, 2: variant country groups */
			__( 'Create %1$d %2$s variants: %3$s.', 'reactwoo-geocore' ),
			count( $variants ),
			$page,
			implode( '; ', $parts )
		);
	}
}
