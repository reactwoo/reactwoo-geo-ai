<?php
/**
 * Split clause text into include vs exclude condition groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Condition_Polarity_Resolver {

	/** @var array<int,string> */
	const EXCLUSION_MARKERS = array(
		'except users in',
		'except visitors in',
		'except for users in',
		'except for visitors in',
		'excluding users in',
		'excluding visitors in',
		'excluding',
		'not in',
		'but not',
		'apart from',
	);

	/**
	 * @return array{include:array<string,array>,exclude:array<string,array>}
	 */
	public static function empty_groups() {
		$empty = array(
			'countries' => array(),
			'regions'   => array(),
			'devices'   => array(),
			'weather'   => array(),
			'urls'      => array(),
			'utm'       => array(),
			'campaigns' => array(),
			'audiences' => array(),
		);
		return array(
			'include' => $empty,
			'exclude' => $empty,
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return array{include_text:string,exclude_text:string}
	 */
	public static function split_text( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$include = $clause;
		$exclude = '';

		$patterns = array(
			'/\bexcept\s+when\s+(?:the\s+)?weather\s+is\s+(.+)$/i',
			'/\b(?:except|excluding)\s+(?:users|visitors)\s+in\s+(.+)$/i',
			'/\b(?:except|excluding)\s+for\s+(?:users|visitors)\s+in\s+(.+)$/i',
			'/\bexcluding\s+(.+)$/i',
			'/\bapart\s+from\s+(.+)$/i',
			'/\bnot\s+in\s+(.+)$/i',
			'/\bbut\s+not\s+(.+)$/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $include, $m ) ) {
				$exclude = trim( (string) $m[1] );
				$include = trim( (string) preg_replace( $pattern, '', $include ) );
				break;
			}
		}

		// Pronoun-hide exclusions ("but don't show it to tablet users") only split
		// off when a substantial positive instruction remains; otherwise the clause
		// itself is the hide action and the condition stays in the include group.
		if ( '' === $exclude ) {
			$pronoun_patterns = array(
				'/\bbut\s+(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+(.+)$/i',
				'/\b(?:don\'t|do not|dont)\s+(?:show|display)\s+it\s+to\s+(.+)$/i',
				'/\bbut\s+(?:hide|exclude)\s+it\s+from\s+(.+)$/i',
			);
			foreach ( $pronoun_patterns as $pattern ) {
				if ( ! preg_match( $pattern, $include, $m ) ) {
					continue;
				}
				$candidate_exclude = trim( (string) $m[1] );
				$candidate_include = trim( (string) preg_replace( $pattern, '', $include ) );
				if ( self::is_substantial_include( $candidate_include ) ) {
					$exclude = $candidate_exclude;
					$include = $candidate_include;
				}
				break;
			}
		}

		return array(
			'include_text' => trim( $include, " \t\n\r\0\x0B,." ),
			'exclude_text' => trim( $exclude, " \t\n\r\0\x0B,." ),
		);
	}

	/**
	 * @param string $text Candidate include remainder.
	 * @return bool
	 */
	private static function is_substantial_include( $text ) {
		$text = strtolower( (string) $text );
		$text = (string) preg_replace( '/\b(?:but|and|then|so|it|to|the|a|an|that|which|should|will|would)\b/i', ' ', $text );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		return strlen( $text ) > 3;
	}

	/**
	 * @param array<string,mixed> $conditions Polar conditions.
	 * @return array<string,array>
	 */
	public static function include_group( array $conditions ) {
		if ( isset( $conditions['include'] ) && is_array( $conditions['include'] ) ) {
			return $conditions['include'];
		}
		return $conditions;
	}

	/**
	 * @param array<string,mixed> $conditions Polar conditions.
	 * @return array<string,array>
	 */
	public static function exclude_group( array $conditions ) {
		if ( isset( $conditions['exclude'] ) && is_array( $conditions['exclude'] ) ) {
			return $conditions['exclude'];
		}
		return self::empty_groups()['exclude'];
	}
}
