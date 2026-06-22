<?php
/**
 * Split user input into action-owning clauses without breaking paired variant groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Clause_Splitter {

	/** @var array<int,string> */
	const HARD_BOUNDARIES = array(
		'and then',
		'then',
		'also',
		'plus',
		'as well as',
		'after that',
		'next',
	);

	/** @var array<int,string> */
	const VARIANT_PAIR_MARKERS = array(
		'one variant',
		'another variant',
		'the other',
		'second variant',
		'third variant',
		'first one',
		'second one',
		'one will',
		'one should',
		'the other will',
		'the other should',
		'one for',
		'another for',
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array{raw:string,index:int}>
	 */
	public static function split( $phrase ) {
		$phrase = trim( (string) $phrase );
		if ( '' === $phrase ) {
			return array();
		}

		$segments     = self::split_rule_segments( $phrase );
		$clauses      = array();
		$main_clauses = self::coarse_split( $segments['main'] );
		if ( count( $main_clauses ) < 1 ) {
			$main_clauses = array(
				array(
					'raw'   => $segments['main'],
					'index' => 0,
				),
			);
		}

		foreach ( $main_clauses as $row ) {
			$raw    = trim( (string) ( $row['raw'] ?? '' ) );
			$parent = class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false )
				? RWGA_Planner_Parent_Variant_Resolver::detect_parent( $raw )
				: null;
			$children = ( is_array( $parent ) && class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false ) )
				? RWGA_Planner_Parent_Variant_Resolver::split_child_clauses( $raw, $parent )
				: array();

			if ( is_array( $parent ) && count( $children ) >= 2 ) {
				foreach ( $children as $idx => $child ) {
					$clauses[] = array(
						'raw'    => $child,
						'index'  => count( $clauses ),
						'type'   => 'variant_child',
						'parent' => $parent,
					);
				}
				continue;
			}

			if ( class_exists( 'RWGA_Variant_Plan_Parser', false )
				&& RWGA_Variant_Plan_Parser::is_variant_plan_command( $raw ) ) {
				$variant_segments = RWGA_Variant_Plan_Parser::split_segments( $raw );
				if ( count( $variant_segments ) >= 2 ) {
					foreach ( self::segments_to_clauses( $variant_segments ) as $segment_clause ) {
						$segment_clause['index'] = count( $clauses );
						$clauses[]               = $segment_clause;
					}
					continue;
				}
			}

			$clauses[] = array(
				'raw'   => $raw,
				'index' => count( $clauses ),
			);
		}

		foreach ( $segments['rules'] as $rule_clause ) {
			$clauses[] = array(
				'raw'   => $rule_clause,
				'index' => count( $clauses ),
				'type'  => 'rule',
			);
		}

		if ( empty( $clauses ) ) {
			return array(
				array(
					'raw'   => $phrase,
					'index' => 0,
				),
			);
		}

		return $clauses;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array{main:string,rules:array<int,string>}
	 */
	private static function split_rule_segments( $phrase ) {
		$rules = array();
		$main  = $phrase;

		if ( preg_match( '/^(.*?)(?:\.\s*)?\balso\s+create\s+(?:a\s+)?rule\b(.+)$/i', $phrase, $m ) ) {
			$main = trim( (string) $m[1] );
			$rules[] = trim( 'create a rule' . (string) $m[2] );
		}

		return array(
			'main'  => $main,
			'rules' => $rules,
		);
	}

	/**
	 * @param string $phrase Phrase.
	 * @return array<int,array{raw:string,index:int}>
	 */
	private static function coarse_split( $phrase ) {
		$pattern = '/(?:^|[,.;]|\s+-\s+|\s*[—–]\s+|\band then\b|\bthen\b)(?=\s*(?:update|create|change|show|hide|only show|display|make|test|diagnose)\b)/i';
		$parts   = preg_split( $pattern, $phrase, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array();
		}

		$clauses = array();
		foreach ( array_values( array_filter( array_map( 'trim', $parts ) ) ) as $idx => $raw ) {
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => $idx,
			);
		}
		return $clauses;
	}

	/**
	 * @param array<int,array<string,mixed>> $segments Parser segments.
	 * @return array<int,array{raw:string,index:int}>
	 */
	private static function segments_to_clauses( array $segments ) {
		$clauses = array();
		foreach ( $segments as $idx => $segment ) {
			$raw = trim( (string) ( $segment['raw'] ?? '' ) );
			if ( '' === $raw ) {
				continue;
			}
			$clauses[] = array(
				'raw'   => $raw,
				'index' => $idx,
				'type'  => (string) ( $segment['type'] ?? '' ),
			);
		}
		return $clauses;
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function has_variant_pair_marker( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		foreach ( self::VARIANT_PAIR_MARKERS as $marker ) {
			if ( false !== strpos( $clause, $marker ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/\bone\s+(?:variant|version|variation)\b.*\bthe\s+other\b/i', $clause );
	}
}
