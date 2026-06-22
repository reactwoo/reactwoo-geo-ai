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
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( ! preg_match(
			'/\b(?:set\s+up|setup)\s+(?:the\s+)?(.+?)\s+campaign\b/i',
			$phrase,
			$m
		) ) {
			return null;
		}
		$label = trim( (string) $m[1] );
		if ( '' === $label ) {
			return null;
		}
		return array(
			'label' => $label,
			'slug'  => sanitize_title( $label ),
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_campaign_setup_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/\b(?:set\s+up|setup)\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $clause );
	}
}
