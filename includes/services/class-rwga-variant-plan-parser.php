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
		$segments            = self::split_segments( $phrase );

		$debug['source_page_ref']      = $page_value;
		$debug['total_version_count']  = $total_version_count;
		$debug['duplicate_count']      = $duplicate_count;
		$debug['segments']             = $segments;

		$source   = null;
		$variants = array();

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

			$ordinal = (int) ( $segment['ordinal'] ?? 0 );
			if ( $ordinal <= 0 ) {
				$ordinal = $source ? ( 2 + count( $variants ) ) : ( 1 + count( $variants ) );
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

		if ( null === $source || empty( $variants ) ) {
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

		return self::build_matched_result( $phrase, $page_value, $source, $variants, $total_version_count, $duplicate_count, $enriched_segments, $debug, $entities );
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
		$new_count = self::detect_create_variant_count( $phrase );
		if ( $new_count <= 0 ) {
			return 0;
		}
		// "create 2 new variants" means N new copies plus the original when the source is named explicitly.
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+new\s+(?:variations?|variants?|versions?)\b/i', $phrase )
			&& self::has_original_marker( $phrase ) ) {
			return $new_count + 1;
		}
		return $new_count;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_create_variant_count( $phrase ) {
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))(?:\s+new)?\s+(?:variations?|variants?|versions?)\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $key;
		}
		return 0;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_duplicate_count( $phrase ) {
		$create_count = self::detect_create_variant_count( $phrase );
		if ( $create_count > 0 ) {
			return $create_count;
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
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split_segments( $phrase ) {
		$markers = self::segment_markers();
		$hits    = array();

		foreach ( $markers as $marker ) {
			if ( ! preg_match( $marker['pattern'], $phrase, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$hits[] = array(
				'offset'  => (int) $match[0][1],
				'length'  => strlen( $match[0][0] ),
				'type'    => $marker['type'],
				'marker'  => $marker['marker'],
				'ordinal' => $marker['ordinal'],
			);
		}

		if ( empty( $hits ) ) {
			return array();
		}

		usort(
			$hits,
			static function ( $a, $b ) {
				if ( $a['offset'] === $b['offset'] ) {
					return $b['length'] - $a['length'];
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
	private static function segment_markers() {
		return array(
			array( 'pattern' => '/\bthen\s+update\s+the\s+original\s+homepage\b/i', 'type' => 'source', 'marker' => 'then update the original homepage', 'ordinal' => 0 ),
			array( 'pattern' => '/\bthe\s+other\s+should\s+(?:show|display)\b/i', 'type' => 'variant', 'marker' => 'the other should display', 'ordinal' => 2 ),
			array( 'pattern' => '/\bone\s+should\s+(?:show|display)\b/i', 'type' => 'variant', 'marker' => 'one should display', 'ordinal' => 1 ),
			array( 'pattern' => '/\bupdate\s+the\s+original\b/i', 'type' => 'source', 'marker' => 'update the original', 'ordinal' => 0 ),
			array( 'pattern' => '/\b(?:keep|leave|make)\s+the\s+original\b/i', 'type' => 'source', 'marker' => 'keep the original', 'ordinal' => 0 ),
			array( 'pattern' => '/\bthe\s+original\s+(?:version|homepage|page)\b/i', 'type' => 'source', 'marker' => 'the original version', 'ordinal' => 0 ),
			array( 'pattern' => '/\bthe\s+original\b/i', 'type' => 'source', 'marker' => 'the original', 'ordinal' => 0 ),
			array( 'pattern' => '/\bthe\s+third\b/i', 'type' => 'variant', 'marker' => 'the third', 'ordinal' => 3 ),
			array( 'pattern' => '/\bthe\s+second\b/i', 'type' => 'variant', 'marker' => 'the second', 'ordinal' => 2 ),
			array( 'pattern' => '/\b(?:variant|variation|version)\s+three\b/i', 'type' => 'variant', 'marker' => 'variant three', 'ordinal' => 3 ),
			array( 'pattern' => '/\b(?:variant|variation|version)\s+two\b/i', 'type' => 'variant', 'marker' => 'variant two', 'ordinal' => 2 ),
			array( 'pattern' => '/\b(?:variant|variation|version)\s+3\b/i', 'type' => 'variant', 'marker' => 'variant 3', 'ordinal' => 3 ),
			array( 'pattern' => '/\b(?:variant|variation|version)\s+2\b/i', 'type' => 'variant', 'marker' => 'variant 2', 'ordinal' => 2 ),
			array( 'pattern' => '/\b3rd\s+(?:variant|variation|version)\b/i', 'type' => 'variant', 'marker' => '3rd version', 'ordinal' => 3 ),
			array( 'pattern' => '/\b2nd\s+(?:variant|variation|version)\b/i', 'type' => 'variant', 'marker' => '2nd version', 'ordinal' => 2 ),
			array( 'pattern' => '/\banother\s+(?:version|variant|variation)\b/i', 'type' => 'variant', 'marker' => 'another version', 'ordinal' => 0 ),
			array( 'pattern' => '/\banother\b/i', 'type' => 'variant', 'marker' => 'another', 'ordinal' => 0 ),
			array( 'pattern' => '/\bone\s+(?:version|variant|variation)\b/i', 'type' => 'variant', 'marker' => 'one version', 'ordinal' => 0 ),
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
	private static function build_matched_result( $phrase, $page_value, array $source, array $variants, $total_version_count, $duplicate_count, array $segments, array $debug, array $entities ) {
		$source_countries = (array) ( $source['countries'] ?? array() );
		$source_display   = class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::label_for_countries( $source_countries, $entities )
			: implode( ', ', $source_countries );

		$steps = array(
			array(
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
			),
		);

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

		$summary = sprintf(
			/* translators: 1: source countries, 2: variant labels */
			__( 'Update the original homepage for %1$s visitors, then create variants for %2$s.', 'reactwoo-geocore' ),
			$source_display,
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
			'source_targeting' => $source,
			'variant_groups'   => $variants,
			'duplicate_count'  => $duplicate_count,
			'matched_terms'    => array_filter( array_map( static function ( $s ) {
				return (string) ( $s['marker'] ?? $s['raw'] ?? '' );
			}, $segments ) ),
			'params'           => array(
				'source_page_ref'     => $page_value,
				'total_version_count' => $total_version_count > 0 ? $total_version_count : ( 1 + count( $variant_out ) ),
				'duplicate_count'     => $duplicate_count,
				'source_targeting'    => array(
					'label'           => __( 'Original homepage', 'reactwoo-geocore' ),
					'targeting_label' => $source_display,
					'mode'            => $source['mode'] ?? 'include_only',
					'countries'       => $source_countries,
				),
				'variants'            => $variant_out,
			),
			'steps'            => $steps,
			'summary'          => $summary,
			'_debug'           => $debug,
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
