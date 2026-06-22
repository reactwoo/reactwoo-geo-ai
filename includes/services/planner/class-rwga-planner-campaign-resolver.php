<?php
/**
 * Extract campaign context from setup phrases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Campaign_Resolver {

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array{label:string,slug:string}|null
	 */
	public static function detect( $phrase ) {
		return self::detect_from_clause( $phrase );
	}

	/**
	 * @param string $clause Clause or phrase text.
	 * @return array{label:string,slug:string}|null
	 */
	public static function detect_from_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$patterns = array(
			'/\bfor\s+(?:the\s+)?(.+?)\s+campaign\b/i',
			'/\b(?:set\s+up|setup)\s+(?:the\s+)?(.+?)\s+campaign\b/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $clause, $m ) ) {
				continue;
			}
			$label = trim( (string) $m[1] );
			if ( '' === $label ) {
				continue;
			}
			return array(
				'label' => $label,
				'slug'  => sanitize_title( $label ),
			);
		}

		return null;
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_campaign_setup_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/\b(?:set\s+up|setup)\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $clause );
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_campaign_targeting_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $clause );
	}
}
