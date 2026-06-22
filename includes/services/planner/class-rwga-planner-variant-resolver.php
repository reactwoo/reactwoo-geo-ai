<?php
/**
 * Expand variant pair phrases into separate create_variant actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Variant_Resolver {

	/**
	 * Build variant actions from a clause (handles one/other pairs).
	 *
	 * @param string              $clause       Clause text.
	 * @param array{type:string,label:string,slug:string,source:string} $target Target.
	 * @param array<int,array>    $entities     Entities.
	 * @param array{type:string,visibility:string,mode:string,confidence:float} $type_row Type row.
	 * @return array<int,array<string,mixed>>
	 */
	public static function expand_from_clause( $clause, array $target, array $entities, array $type_row ) {
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false ) ) {
			return array();
		}

		$pair = self::extract_pair_from_clause( $clause, $entities );
		if ( count( $pair ) >= 2 ) {
			return self::pair_to_actions( $pair, $target, $type_row, $entities );
		}

		$cond    = RWGA_Planner_Condition_Resolver::resolve( $clause, $entities );
		$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
		if ( empty( $include['countries'] ) && empty( $include['regions'] ) ) {
			return array();
		}

		return array(
			self::make_action(
				$type_row,
				$target,
				1,
				$cond,
				$clause
			),
		);
	}

	/**
	 * Convert variant plan parser output into planner actions.
	 *
	 * @param array<string,mixed> $plan     Parser result.
	 * @param array<int,array>    $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	public static function from_variant_plan_parse( array $plan, array $entities ) {
		unset( $entities );
		if ( empty( $plan['matched'] ) ) {
			return array();
		}

		$actions      = array();
		$page_ref     = (string) ( $plan['params']['source_page_ref'] ?? 'homepage' );
		$variant_page = (string) ( $plan['params']['variant_source_page'] ?? $page_ref );
		$target       = array(
			'type'   => 'page',
			'label'  => $variant_page,
			'slug'   => $variant_page,
			'source' => 'detected',
		);
		$type_row = array(
			'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
			'visibility' => 'show',
			'mode'       => 'create',
			'confidence' => 0.88,
		);

		$source = $plan['params']['source_targeting'] ?? null;
		if ( is_array( $source ) && ! empty( $source['countries'] ) ) {
			$original_target = array(
				'type'   => 'page',
				'label'  => (string) ( $plan['params']['original_page_ref'] ?? $plan['params']['page_context']['original_page'] ?? 'homepage' ),
				'slug'   => (string) ( $plan['params']['original_page_ref'] ?? 'homepage' ),
				'source' => 'detected',
			);
			if ( '' === $original_target['label'] || 'homepage' === $original_target['slug'] ) {
				$original_target['label'] = 'homepage';
				$original_target['slug']  = 'homepage';
			}
			$regions = array();
			$countries = (array) $source['countries'];
			foreach ( $countries as $code ) {
				if ( 'GB' === $code && preg_match( '/\bengland\b/i', (string) ( $source['raw'] ?? '' ) ) ) {
					$regions[] = 'GB-ENG';
				}
			}
			if ( ! empty( $regions ) ) {
				$countries = array_values( array_diff( $countries, array( 'GB' ) ) );
			}
			$actions[] = self::make_action(
				array(
					'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
					'visibility' => 'only_show',
					'mode'       => 'update',
					'confidence' => 0.88,
				),
				$original_target,
				null,
				array(
					'conditions' => array(
						'countries' => $countries,
						'regions'   => $regions,
						'devices'   => array(),
						'weather'   => array(),
						'campaigns' => array(),
						'urls'      => array(),
						'audiences' => array(),
					),
					'warnings'   => array(),
					'confidence' => 0.88,
				),
				(string) ( $source['raw'] ?? '' ),
				'original'
			);
		}

		foreach ( (array) ( $plan['params']['variants'] ?? array() ) as $idx => $variant ) {
			$countries = (array) ( $variant['countries'] ?? array() );
			$actions[] = self::make_action(
				$type_row,
				$target,
				$idx + 1,
				array(
					'conditions' => array(
						'countries' => $countries,
						'regions'   => array(),
						'devices'   => array(),
						'weather'   => array(),
						'campaigns' => array(),
						'urls'      => array(),
						'audiences' => array(),
					),
					'warnings'   => array(),
					'confidence' => 0.88,
				),
				(string) ( $variant['raw'] ?? '' ),
				'variant',
				(string) ( $variant['label'] ?? '' )
			);
		}

		return $actions;
	}

	/**
	 * @param string           $clause   Clause text.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function extract_pair_from_clause( $clause, array $entities ) {
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false ) ) {
			return array();
		}
		$pair = RWGA_Variant_Plan_Parser::split_one_other_pair( $clause, $entities );
		if ( count( $pair ) >= 2 ) {
			return $pair;
		}
		if ( preg_match( '/\bone\s+variant\s+should\s+(?:display|show)\s+in\s+(.+?)\s+and\s+the\s+other\s+in\s+(.+)$/i', $clause, $m ) ) {
			$first  = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[1] ), $entities );
			$second = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[2] ), $entities );
			if ( ! empty( $first ) && ! empty( $second ) ) {
				return array(
					array(
						'ordinal'   => 1,
						'raw'       => trim( (string) $m[1] ),
						'countries' => $first,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $first, 'regions' => array(), 'labels' => array() )
						),
					),
					array(
						'ordinal'   => 2,
						'raw'       => trim( (string) $m[2] ),
						'countries' => $second,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $second, 'regions' => array(), 'labels' => array() )
						),
					),
				);
			}
		}
		$patterns = array(
			'/\bone\s+(?:variant|variation|version)?\s*(?:should|will|would|can).+?\b(?:and\s+)?(?:the\s+other|another)\s+(?:variant|variation|version)?\s*(?:should|will|would|can).+$/i',
			'/\bone\s+will\b.+?\bone\s+will\b.+$/i',
			'/\bone\s+should\b.+?\bthe\s+other\b.+$/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $clause, $m ) ) {
				continue;
			}
			$pair = RWGA_Variant_Plan_Parser::split_one_other_pair( trim( (string) $m[0] ), $entities );
			if ( count( $pair ) >= 2 ) {
				return $pair;
			}
		}
		if ( preg_match( '/\bone\s+will\s+(?:display|show)\s+(?:in|for)\s+(.+?)\s+and\s+one\s+will\s+(?:display|show)\s+(?:in|for)\s+(.+)$/i', $clause, $m ) ) {
			$first  = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[1] ), $entities );
			$second = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[2] ), $entities );
			if ( ! empty( $first ) && ! empty( $second ) ) {
				return array(
					array(
						'ordinal'   => 1,
						'raw'       => trim( (string) $m[1] ),
						'countries' => $first,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $first, 'regions' => array(), 'labels' => array() )
						),
					),
					array(
						'ordinal'   => 2,
						'raw'       => trim( (string) $m[2] ),
						'countries' => $second,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $second, 'regions' => array(), 'labels' => array() )
						),
					),
				);
			}
		}
		if ( preg_match( '/\s+-\s+(.+)$/i', $clause, $m ) ) {
			return RWGA_Variant_Plan_Parser::split_one_other_pair( trim( (string) $m[1] ), $entities );
		}
		return array();
	}

	/**
	 * @param array<int,array<string,mixed>> $pair     Pair rows.
	 * @param array<string,mixed>            $target   Target.
	 * @param array<string,mixed>            $type_row Type row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function pair_to_actions( array $pair, array $target, array $type_row, array $entities ) {
		$actions = array();
		foreach ( $pair as $idx => $row ) {
			$raw       = (string) ( $row['raw'] ?? '' );
			$child_raw = (string) ( $row['child_clause'] ?? $raw );
			$cond    = RWGA_Planner_Condition_Resolver::resolve( $child_raw, $entities );
			$visibility = preg_match( '/\b(?:only|just)\s+(?:show|display)\b|\bshow\s+only\b|\bonly\s+show\b/i', $child_raw )
				? 'only_show'
				: (string) $type_row['visibility'];
			$type_row['visibility'] = $visibility;
			$actions[] = self::make_action(
				$type_row,
				$target,
				$idx + 1,
				array(
					'conditions'      => $cond['conditions'],
					'location_labels' => $cond['location_labels'] ?? array(),
					'warnings'        => $cond['warnings'] ?? array(),
					'confidence'      => (float) ( $cond['confidence'] ?? 0.9 ),
				),
				$child_raw,
				'variant',
				(string) ( $row['label'] ?? '' )
			);
		}
		return $actions;
	}

	/**
	 * @param array<string,mixed> $type_row     Type row.
	 * @param array<string,mixed> $target       Target.
	 * @param int|null            $index        Variant index.
	 * @param array<string,mixed> $cond         Condition bundle.
	 * @param string              $clause       Source clause.
	 * @param string              $relationship original|variant|other.
	 * @param string              $label        Optional variant label.
	 * @return array<string,mixed>
	 */
	private static function make_action( array $type_row, array $target, $index, array $cond, $clause, $relationship = 'variant', $label = '' ) {
		$page_label = (string) ( $target['label'] ?? 'page' );
		if ( '' === $label ) {
			$include   = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
			$loc_label = RWGA_Planner_Location_Resolver::display_label(
				array(
					'countries' => $include['countries'] ?? array(),
					'regions'   => $include['regions'] ?? array(),
					'labels'    => array(),
				)
			);
			$label = ucfirst( $page_label ) . ( $loc_label ? ' - ' . $loc_label : '' );
		}

		return array(
			'id'                  => RWGA_Geo_Assistant_Planner::new_id(),
			'type'                => (string) $type_row['type'],
			'target'              => $target,
			'variant'             => array(
				'index'         => $index,
				'label'         => $label,
				'sourcePage'    => $page_label,
				'relationship'  => $relationship,
			),
			'conditions'          => $cond['conditions'],
			'location_labels'     => $cond['location_labels'] ?? array(),
			'operation'           => array(
				'visibility' => (string) $type_row['visibility'],
				'mode'       => (string) $type_row['mode'],
			),
			'confidence'          => (float) ( $cond['confidence'] ?? $type_row['confidence'] ?? 0.8 ),
			'needsClarification'  => false,
			'clarificationReason' => null,
			'sourceClause'        => $clause,
		);
	}
}
