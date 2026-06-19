<?php
/**
 * Structured slot/segment parser for variant planning commands.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Variant_Plan_Parser {

	/** @var array<string,int> */
	const COUNT_MAP = array(
		'one'   => 1,
		'two'   => 2,
		'three' => 3,
		'four'  => 4,
		'five'  => 5,
		'1'     => 1,
		'2'     => 2,
		'3'     => 3,
		'4'     => 4,
		'5'     => 5,
	);

	/**
	 * @param string              $message  Raw user message.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	public static function parse( $message, array $entities, array $context = array() ) {
		unset( $context );
		$phrase = self::normalise( $message );
		if ( '' === $phrase ) {
			return array( 'matched' => false, 'reason' => 'empty_input' );
		}

		$debug = array(
			'parser_used'                 => 'RWGA_Variant_Plan_Parser',
			'normalised_input'            => $phrase,
			'variant_plan_terms_detected' => self::is_variant_plan_command( $phrase ),
			'fallback_pattern_match_used' => false,
		);

		if ( ! $debug['variant_plan_terms_detected'] ) {
			return array_merge(
				array( 'matched' => false, 'reason' => 'no_variant_plan_terms' ),
				array( '_debug' => $debug )
			);
		}

		$page_value          = self::detect_page_ref( $phrase );
		$total_version_count = self::detect_total_version_count( $phrase );
		$duplicate_count     = self::detect_duplicate_count( $phrase );
		$segments            = self::split_segments_from_boundaries( $phrase );
		$raw_clauses         = array_values(
			array_filter(
				array_map(
					static function ( $segment ) {
						return trim( (string) ( $segment['raw'] ?? '' ) );
					},
					$segments
				)
			)
		);
		foreach ( $segments as $idx => $segment ) {
			$raw = (string) ( $segment['raw'] ?? '' );
			$segments[ $idx ]['type']    = self::classify_clause_type( $raw );
			$segments[ $idx ]['marker']  = self::clause_marker_label( $raw, $segments[ $idx ]['type'] );
			$segments[ $idx ]['ordinal'] = (int) ( $segment['ordinal'] ?? 0 ) > 0
				? (int) $segment['ordinal']
				: self::clause_ordinal_hint( $raw );
		}

		$debug['source_page_ref']      = $page_value;
		$debug['total_version_count']  = $total_version_count;
		$debug['duplicate_count']      = $duplicate_count;
		$debug['raw_clauses']          = $raw_clauses;
		$debug['segments']             = $segments;

		$source   = null;
		$variants = array();
		$variant_ordinal = 0;

		foreach ( $segments as $segment ) {
			$countries = self::extract_country_list_from_segment( $segment['raw'], $entities );
			$mode      = self::detect_mode( $segment['raw'] );
			$label     = class_exists( 'RWGA_Variant_Group_Extractor', false )
				? RWGA_Variant_Group_Extractor::label_for_countries( $countries, $entities )
				: implode( ' + ', $countries );

			if ( empty( $countries ) ) {
				continue;
			}

			if ( 'source' === $segment['type'] ) {
				$source = array(
					'raw'       => $segment['raw'],
					'marker'    => $segment['marker'] ?? 'original',
					'label'     => __( 'Original homepage', 'reactwoo-geocore' ),
					'countries' => $countries,
					'mode'      => $mode,
				);
				continue;
			}

			$variant_ordinal++;
			$ordinal = (int) ( $segment['ordinal'] ?? 0 );
			if ( $ordinal <= 0 ) {
				$ordinal = $variant_ordinal;
			}
			$variants[] = array(
				'ordinal'   => $ordinal,
				'raw'       => $segment['raw'],
				'marker'    => $segment['marker'] ?? '',
				'label'     => $label,
				'mode'      => $mode,
				'countries' => $countries,
			);
		}

		$debug['detected_source_clause']   = $source;
		$debug['detected_variant_clauses'] = $variants;
		$debug['countries_per_clause']     = array_map(
			static function ( $segment ) use ( $entities ) {
				return array(
					'raw'       => $segment['raw'] ?? '',
					'type'      => $segment['type'] ?? '',
					'countries' => self::extract_country_list_from_segment( (string) ( $segment['raw'] ?? '' ), $entities ),
				);
			},
			$segments
		);

		if ( null === $source && self::has_original_marker( $phrase ) ) {
			$legacy = class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
				? RWGA_Original_Source_Targeting_Extractor::extract( $phrase, $entities )
				: null;
			if ( is_array( $legacy ) && ! empty( $legacy['countries'] ) ) {
				$source = $legacy;
			}
		}

		if ( empty( $variants ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			$groups = RWGA_Variant_Group_Extractor::extract_plan_variant_groups( $phrase, $entities, $source );
			foreach ( $groups as $idx => $group ) {
				$variants[] = array(
					'ordinal'   => $idx + ( $source ? 2 : 1 ),
					'raw'       => $group['raw'] ?? '',
					'marker'    => '',
					'label'     => $group['label'] ?? '',
					'mode'      => $group['mode'] ?? 'include_only',
					'countries' => $group['countries'] ?? array(),
				);
			}
		}

		$all_countries = RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities );
		$debug['countries_detected'] = $all_countries;

		if ( $total_version_count >= 3 && null === $source && ! self::has_original_marker( $phrase ) ) {
			return self::ambiguous_source_usage( $phrase, $page_value, $debug );
		}

		if ( empty( $variants ) ) {
			$debug['segments_found'] = count( $segments );
			$debug['reason']         = empty( $segments ) ? 'failed_to_segment_variant_plan' : 'incomplete_variant_plan';
			if ( ! empty( $all_countries ) && $page_value ) {
				return self::partial_clarification( $phrase, $page_value, $all_countries, $debug );
			}
			return array_merge(
				array( 'matched' => false, 'reason' => $debug['reason'] ),
				array( '_debug' => $debug )
			);
		}

		if ( null === $source && self::has_original_marker( $phrase ) ) {
			$debug['segments_found'] = count( $segments );
			$debug['reason']         = 'incomplete_variant_plan';
			if ( ! empty( $all_countries ) && $page_value ) {
				return self::partial_clarification( $phrase, $page_value, $all_countries, $debug );
			}
			return array_merge(
				array( 'matched' => false, 'reason' => $debug['reason'] ),
				array( '_debug' => $debug )
			);
		}

		$enriched_segments = array();
		foreach ( $segments as $segment ) {
			$countries = self::extract_country_list_from_segment( $segment['raw'], $entities );
			if ( empty( $countries ) ) {
				continue;
			}
			$enriched_segments[] = array_merge(
				$segment,
				array(
					'countries' => $countries,
					'mode'      => self::detect_mode( $segment['raw'] ),
				)
			);
		}
		$debug['segments'] = $enriched_segments;

		$result = self::build_matched_result( $phrase, $page_value, $source, $variants, $total_version_count, $duplicate_count, $enriched_segments, $debug, $entities );
		self::log_parse_result( $phrase, $debug, $result );
		return $result;
	}

	/**
	 * @param string $message Raw message.
	 * @return string
	 */
	public static function normalise( $message ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $message );
		$phrase = str_replace( array( '’', '`' ), "'", $phrase );
		$phrase = preg_replace( '/[.!?;:]+/', ' ', $phrase );
		return trim( preg_replace( '/\s+/', ' ', (string) $phrase ) );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function is_variant_plan_command( $phrase ) {
		$has_variant = (bool) preg_match(
			'/\b(variation|variations|variant|variants|version|versions|duplicate|copy|clone|original|source|default|current page|existing page)\b/i',
			$phrase
		);
		$has_targeting = (bool) preg_match(
			'/\b(show in|show for|display in|display for|works in|target|only show|visible in|for users in|for visitors in|only display)\b/i',
			$phrase
		);
		$has_creation = (bool) preg_match(
			'/\b(create|make|duplicate|copy|clone|build|setup|set up|update)\b/i',
			$phrase
		);
		return $has_variant && ( $has_targeting || $has_creation );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function has_original_marker( $phrase ) {
		if ( class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
			&& RWGA_Original_Source_Targeting_Extractor::has_original_marker( $phrase ) ) {
			return true;
		}
		return (bool) preg_match(
			'/\b(?:update|keep|leave|make)\s+the\s+original\b|\bthe\s+original\b|\boriginal\s+(?:version|homepage|page)\b/i',
			$phrase
		);
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,string>
	 */
	public static function extract_country_list_from_segment( $segment, array $entities ) {
		return RWGA_Multi_Variant_Interpreter::parse_country_list( $segment, $entities );
	}

	/**
	 * @param string $segment Segment text.
	 * @return string
	 */
	public static function detect_mode( $segment ) {
		if ( preg_match( '/\b(hide from|exclude|do not show|don\'t show|block|prevent)\b/i', $segment ) ) {
			return 'exclude';
		}
		return 'include_only';
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return string
	 */
	private static function detect_page_ref( $phrase ) {
		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$page = RWGA_Page_Reference_Resolver::detect( $phrase );
			if ( is_array( $page ) && ! empty( $page['value'] ) ) {
				return (string) $page['value'];
			}
		}
		return 'homepage';
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_total_version_count( $phrase ) {
		$new_variants = self::detect_create_new_variant_count( $phrase );
		if ( $new_variants > 0 ) {
			return self::has_original_marker( $phrase ) ? $new_variants + 1 : $new_variants;
		}
		$total_variations = self::detect_total_variation_count( $phrase );
		if ( $total_variations > 0 ) {
			return $total_variations;
		}
		return 0;
	}

	/**
	 * Count of new variant duplicates from explicit "create N variants" / "create N new …".
	 *
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_create_new_variant_count( $phrase ) {
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))(?:\s+new)?\s+variants\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+new\s+(?:variations?|versions?)\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		return 0;
	}

	/**
	 * Total version count when user names variations/versions (may include original).
	 *
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_total_variation_count( $phrase ) {
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+(?:variations?|versions?)\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		return 0;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 * @deprecated Use detect_create_new_variant_count() or detect_total_variation_count().
	 */
	public static function detect_create_variant_count( $phrase ) {
		return self::detect_create_new_variant_count( $phrase );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_duplicate_count( $phrase ) {
		$new_variants = self::detect_create_new_variant_count( $phrase );
		if ( $new_variants > 0 ) {
			return $new_variants;
		}
		$total_variations = self::detect_total_variation_count( $phrase );
		if ( $total_variations > 0 && self::has_original_marker( $phrase ) ) {
			return max( 0, $total_variations - 1 );
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+twice\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+two times\b/i', $phrase ) ) {
			return 2;
		}
		return 0;
	}

	/**
	 * Split a variant plan into sibling clauses before country extraction.
	 *
	 * @param string $normalised Normalised phrase.
	 * @return array<int,string>
	 */
	public static function split_variant_plan_clauses( $normalised ) {
		$phrase = trim( (string) $normalised );
		if ( '' === $phrase ) {
			return array();
		}

		$segments = self::split_segments_from_boundaries( $phrase );
		if ( empty( $segments ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $segment ) {
						return trim( (string) ( $segment['raw'] ?? '' ) );
					},
					$segments
				)
			)
		);
	}

	/**
	 * @param array<int,string> $raw_clauses Clause strings.
	 * @return array<int,array<string,mixed>>
	 */
	private static function classify_plan_clauses( array $raw_clauses ) {
		$segments = array();
		foreach ( $raw_clauses as $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$type = self::classify_clause_type( $raw );
			$segments[] = array(
				'type'    => $type,
				'marker'  => self::clause_marker_label( $raw, $type ),
				'ordinal' => self::clause_ordinal_hint( $raw ),
				'raw'     => $raw,
			);
		}
		return $segments;
	}

	/**
	 * @param string $clause Raw clause text.
	 * @return string source|variant
	 */
	private static function classify_clause_type( $clause ) {
		if ( preg_match( '/\b(?:update|keep|leave|make)\s+the\s+original\b|\bthe\s+original\s+(?:version|homepage|page)\b|\boriginal\s+(?:should|will|would)\b/i', $clause ) ) {
			return 'source';
		}
		return 'variant';
	}

	/**
	 * @param string $clause Clause text.
	 * @param string $type   source|variant.
	 * @return string
	 */
	private static function clause_marker_label( $clause, $type ) {
		if ( 'source' === $type ) {
			return 'update the original';
		}
		if ( preg_match( '/\b(the\s+other|another)\b/i', $clause ) ) {
			return 'the other should';
		}
		if ( preg_match( '/\bone\s+for\b/i', $clause ) ) {
			return 'one for';
		}
		return 'one should';
	}

	/**
	 * @param string $clause Clause text.
	 * @return int
	 */
	private static function clause_ordinal_hint( $clause ) {
		if ( preg_match( '/\b(?:the\s+third|variant\s+three|version\s+three|3rd|variant\s+3|version\s+3)\b/i', $clause ) ) {
			return 3;
		}
		if ( preg_match( '/\b(?:the\s+second|variant\s+two|version\s+two|2nd|variant\s+2|version\s+2)\b/i', $clause ) ) {
			return 2;
		}
		return 0;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split_segments( $phrase ) {
		return self::split_segments_from_boundaries( $phrase );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	private static function split_segments_from_boundaries( $phrase ) {
		$hits = array();

		foreach ( self::clause_boundary_patterns() as $row ) {
			$capture = (int) ( $row['capture'] ?? 0 );
			if ( ! preg_match_all( $row['pattern'], $phrase, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			if ( ! isset( $matches[ $capture ] ) ) {
				continue;
			}
			foreach ( $matches[ $capture ] as $match ) {
				if ( ! is_array( $match ) || '' === $match[0] ) {
					continue;
				}
				$hits[] = array(
					'offset'  => (int) $match[1],
					'length'  => strlen( $match[0] ),
					'type'    => $row['type'],
					'marker'  => $row['marker'],
					'ordinal' => (int) ( $row['ordinal'] ?? 0 ),
					'priority'=> (int) ( $row['priority'] ?? 10 ),
				);
			}
		}

		if ( empty( $hits ) ) {
			return array();
		}

		usort(
			$hits,
			static function ( $a, $b ) {
				if ( $a['offset'] === $b['offset'] ) {
					if ( $a['priority'] === $b['priority'] ) {
						return $b['length'] - $a['length'];
					}
					return $b['priority'] - $a['priority'];
				}
				return $a['offset'] - $b['offset'];
			}
		);

		$filtered = array();
		$last_end = -1;
		foreach ( $hits as $hit ) {
			if ( $hit['offset'] < $last_end ) {
				continue;
			}
			$filtered[] = $hit;
			$last_end     = $hit['offset'] + $hit['length'];
		}

		$segments = array();
		foreach ( $filtered as $idx => $hit ) {
			$start = $hit['offset'];
			$end   = isset( $filtered[ $idx + 1 ] ) ? $filtered[ $idx + 1 ]['offset'] : strlen( $phrase );
			$raw   = trim( substr( $phrase, $start, $end - $start ) );
			$raw   = preg_replace( '/\s+and\s*$/i', '', (string) $raw );
			$raw   = preg_replace( '/\s*-\s*$/', '', (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$segments[] = array(
				'type'    => $hit['type'],
				'marker'  => $hit['marker'],
				'ordinal' => $hit['ordinal'],
				'raw'     => $raw,
			);
		}

		return $segments;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function clause_boundary_patterns() {
		$variant_should = '(?:(?:one|another|the\\s+other)\\s+should\\s+(?:show|display|will|would))';
		$variant_will   = '(?:(?:one|another|the\\s+other)\\s+will\\s+(?:show|display))';
		$variant_would  = '(?:(?:one|another|the\\s+other)\\s+would\\s+(?:show|display))';
		$variant_for    = '(?:(?:one|another)\\s+for)';
		$version_target = '(?:(?:one|another)\\s+(?:version|variant|variation)\\s+(?:should|will|would)\\s+(?:show|display))';
		$ordinal_target = '(?:(?:the\\s+)?(?:3rd|third|2nd|second)\\s+(?:version|variant|variation)\\s+(?:should|will|would)\\s+(?:show|display))';

		return array(
			array( 'pattern' => '/\band\s+(' . $variant_should . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one should', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_will . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one will', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_would . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one would', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_for . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one for', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $version_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one version should', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(another\s+should\s+(?:show|display|will|would))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and another should', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(another\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and another for', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(?:create\s+)?(one\s+(?:version|variant)\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and create one version for', 'ordinal' => 0, 'priority' => 98 ),
			array( 'pattern' => '/\band\s+(' . $ordinal_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and ordinal version', 'ordinal' => 0, 'priority' => 98 ),
			array( 'pattern' => '/\band\s+((?:variant|variation|version)\s+(?:two|2))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and variant two', 'ordinal' => 2, 'priority' => 95 ),
			array( 'pattern' => '/\band\s+((?:variant|variation|version)\s+(?:three|3))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and variant three', 'ordinal' => 3, 'priority' => 95 ),
			array( 'pattern' => '/\band\s+(the\s+second)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and the second', 'ordinal' => 2, 'priority' => 90 ),
			array( 'pattern' => '/\band\s+(the\s+third)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and the third', 'ordinal' => 3, 'priority' => 90 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+|\s)(the\s+original\s+(?:version|homepage|page)\s+(?:should|will|would)\s+(?:show|display|for))\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'the original version should', 'ordinal' => 0, 'priority' => 88 ),
			array( 'pattern' => '/(?:^|\s)(keep\s+the\s+original(?:\s+homepage)?(?:\s+for)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'keep the original', 'ordinal' => 0, 'priority' => 86 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(then\s+update\s+the\s+original(?:\s+homepage)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'then update the original homepage', 'ordinal' => 0, 'priority' => 85 ),
			array( 'pattern' => '/(?<=\s)(update\s+the\s+original(?:\s+(?:homepage|page|version))?(?:\s+(?:to\s+)?(?:display|show|for))?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update the original', 'ordinal' => 0, 'priority' => 82 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(update\s+the\s+original(?:\s+(?:homepage|page|version))?(?:\s+(?:to\s+)?(?:display|show|for))?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update the original', 'ordinal' => 0, 'priority' => 80 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)((?:keep|leave|make)\s+the\s+original)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'keep the original', 'ordinal' => 0, 'priority' => 80 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_should . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one should', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_will . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one will', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_would . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one would', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_for . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one for', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $version_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one version should', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)(create\s+one\s+(?:version|variant)\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'create one version for', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)(' . $ordinal_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'ordinal version', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)((?:variant|variation|version)\s+(?:two|2))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'variant two', 'ordinal' => 2, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)((?:variant|variation|version)\s+(?:three|3))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'variant three', 'ordinal' => 3, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)((?:2nd|3rd)\s+(?:variant|variation|version))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'ordinal variant', 'ordinal' => 0, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)(the\s+second)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'the second', 'ordinal' => 2, 'priority' => 40 ),
			array( 'pattern' => '/(?<!\band\s)(the\s+third)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'the third', 'ordinal' => 3, 'priority' => 40 ),
			array( 'pattern' => '/(?<!\band\s)(another(?:\s+(?:version|variant|variation))?)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'another', 'ordinal' => 0, 'priority' => 35 ),
		);
	}

	/**
	 * @param string                         $phrase              Phrase.
	 * @param string                         $page_value          Page ref.
	 * @param array<string,mixed>            $source              Source targeting.
	 * @param array<int,array<string,mixed>> $variants            Variants.
	 * @param int                            $total_version_count Total versions.
	 * @param int                            $duplicate_count     Duplicate count.
	 * @param array<int,array>               $segments            Segments.
	 * @param array<string,mixed>            $debug               Debug.
	 * @param array<int,array>               $entities            Entities.
	 * @return array<string,mixed>
	 */
	private static function build_matched_result( $phrase, $page_value, $source, array $variants, $total_version_count, $duplicate_count, array $segments, array $debug, array $entities ) {
		$has_source       = is_array( $source ) && ! empty( $source['countries'] );
		$source_countries = $has_source ? (array) ( $source['countries'] ?? array() ) : array();
		$source_display   = $has_source
			? ( class_exists( 'RWGA_Variant_Group_Extractor', false )
				? RWGA_Variant_Group_Extractor::label_for_countries( $source_countries, $entities )
				: implode( ', ', $source_countries ) )
			: '';

		$steps = array();
		if ( $has_source ) {
			$steps[] = array(
				'label'  => sprintf(
					/* translators: %s: country label */
					__( 'Apply %s targeting to original homepage', 'reactwoo-geocore' ),
					$source_display
				),
				'action' => 'geocore_apply_country_rule_to_source',
				'params' => array(
					'source_page_ref' => $page_value,
					'countries'       => $source_countries,
					'mode'            => $source['mode'] ?? 'include_only',
				),
			);
		}

		$variant_out = array();
		foreach ( $variants as $variant ) {
			$ordinal = (int) ( $variant['ordinal'] ?? 0 );
			$label   = (string) ( $variant['label'] ?? '' );
			$variant_out[] = array(
				'ordinal'   => $ordinal,
				'label'     => $label,
				'mode'      => $variant['mode'] ?? 'include_only',
				'countries' => $variant['countries'] ?? array(),
			);
			$steps[] = array(
				'label'  => sprintf(
					/* translators: 1: variant number, 2: label */
					__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
					$ordinal,
					$label
				),
				'action' => 'geocore_duplicate_page_as_variant',
				'params' => array(
					'source_page_ref' => $page_value,
					'countries'       => $variant['countries'] ?? array(),
					'mode'            => $variant['mode'] ?? 'include_only',
				),
			);
		}

		if ( $duplicate_count <= 0 ) {
			$duplicate_count = count( $variant_out );
		}

		$variant_labels = array_map(
			static function ( $v ) {
				return (string) ( $v['label'] ?? '' );
			},
			$variant_out
		);

		$summary = $has_source
			? sprintf(
				/* translators: 1: source countries, 2: variant labels */
				__( 'Update the original homepage for %1$s visitors, then create variants for %2$s.', 'reactwoo-geocore' ),
				$source_display,
				implode( ' and ', array_filter( $variant_labels ) )
			)
			: sprintf(
				/* translators: %s: variant labels */
				__( 'Create variants for %s.', 'reactwoo-geocore' ),
				implode( ' and ', array_filter( $variant_labels ) )
			);

		$debug['matched_action'] = 'geocore_create_variant_plan_with_country_rules';
		$debug['confidence']     = 0.88;
		$debug['warnings']       = array();

		return array(
			'matched'          => true,
			'intent'           => 'create_geo_variant_plan',
			'matched_action'   => 'geocore_create_variant_plan_with_country_rules',
			'confidence'       => 0.88,
			'source_targeting' => $has_source ? $source : null,
			'variant_groups'   => $variants,
			'duplicate_count'  => $duplicate_count,
			'matched_terms'    => array_filter( array_map( static function ( $s ) {
				return (string) ( $s['marker'] ?? $s['raw'] ?? '' );
			}, $segments ) ),
			'params'           => array(
				'source_page_ref'     => $page_value,
				'total_version_count' => $total_version_count > 0 ? $total_version_count : ( $has_source ? 1 + count( $variant_out ) : count( $variant_out ) ),
				'duplicate_count'     => $duplicate_count,
				'source_targeting'    => $has_source ? array(
					'label'           => __( 'Original homepage', 'reactwoo-geocore' ),
					'targeting_label' => $source_display,
					'mode'            => $source['mode'] ?? 'include_only',
					'countries'       => $source_countries,
				) : null,
				'variants'            => $variant_out,
			),
			'steps'            => $steps,
			'summary'          => $summary,
			'_debug'           => $debug,
		);
	}

	/**
	 * @param string              $phrase Normalised phrase.
	 * @param array<string,mixed> $debug  Debug payload.
	 * @param array<string,mixed> $result Parse result.
	 * @return void
	 */
	private static function log_parse_result( $phrase, array $debug, array $result ) {
		if ( ! class_exists( 'RWGA_Interpreter_Debug', false ) || ! RWGA_Interpreter_Debug::is_enabled() ) {
			return;
		}
		$params = isset( $result['params'] ) && is_array( $result['params'] ) ? $result['params'] : array();
		RWGA_Interpreter_Debug::log(
			'variant_plan_parser',
			array(
				'normalised_input'         => $phrase,
				'parser_used'              => 'RWGA_Variant_Plan_Parser',
				'local_confidence'         => (float) ( $result['confidence'] ?? 0 ),
				'raw_clauses'              => $debug['raw_clauses'] ?? array(),
				'classified_clauses'       => $debug['segments'] ?? array(),
				'detected_source_clause'   => $debug['detected_source_clause'] ?? null,
				'detected_variant_clauses' => $debug['detected_variant_clauses'] ?? array(),
				'countries_per_clause'     => $debug['countries_per_clause'] ?? array(),
				'final_params'             => $params,
				'validation_status'        => ! empty( $result['matched'] ) ? 'matched' : (string) ( $result['reason'] ?? 'unmatched' ),
			)
		);
	}

	/**
	 * @param string              $phrase     Phrase.
	 * @param string              $page_value Page ref.
	 * @param array<string,mixed> $debug      Debug.
	 * @return array<string,mixed>
	 */
	private static function ambiguous_source_usage( $phrase, $page_value, array $debug ) {
		unset( $phrase );
		return array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.65,
			'missing_information' => array(
				array(
					'key'      => 'source_usage',
					'question' => __( 'Should the original homepage become one of these versions, or should I create three new variants?', 'reactwoo-geocore' ),
				),
			),
			'suggested_options'   => array(
				__( 'Use original as the first version', 'reactwoo-geocore' ),
				__( 'Create three new variants', 'reactwoo-geocore' ),
			),
			'params'              => array(
				'source_page_ref' => $page_value,
			),
			'summary'             => __( 'I need to know whether to use the original page as one of the versions.', 'reactwoo-geocore' ),
			'_debug'              => $debug,
		);
	}

	/**
	 * @param string              $phrase     Phrase.
	 * @param string              $page_value Page ref.
	 * @param array<int,string>   $countries  Countries.
	 * @param array<string,mixed> $debug      Debug.
	 * @return array<string,mixed>
	 */
	private static function partial_clarification( $phrase, $page_value, array $countries, array $debug ) {
		unset( $phrase );
		return array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.58,
			'missing_information' => array(
				array(
					'key'      => 'variant_grouping',
					'question' => __( 'I found a page and countries, but could not confidently split them into separate variants. How should I group these countries?', 'reactwoo-geocore' ),
				),
			),
			'suggested_options'   => array(
				__( 'Original homepage for UK, variant for France + Portugal, variant for Germany + Russia', 'reactwoo-geocore' ),
				__( 'One shared rule for all listed countries', 'reactwoo-geocore' ),
				__( 'Something else', 'reactwoo-geocore' ),
			),
			'params'              => array(
				'source_page_ref' => $page_value,
				'countries'       => $countries,
			),
			'summary'             => __( 'I found a page and countries, but I could not confidently split them into separate variants.', 'reactwoo-geocore' ),
			'_debug'              => $debug,
		);
	}
}
