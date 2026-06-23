<?php
/**
 * Detect visitor audience conditions from clause text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Audience_Resolver {

	/**
	 * @param string $text Normalised text.
	 * @return array<int,string>
	 */
	public static function extract( $text ) {
		$text      = RWGA_Local_Intent_Interpreter::normalise( $text );
		$audiences = array();

		if ( preg_match( '/\breturning\s+visitors?\b/i', $text ) ) {
			$audiences[] = 'returning_visitors';
		}
		if ( preg_match( '/\bfirst[-\s]?time\s+visitors?\b/i', $text ) ) {
			$audiences[] = 'first_time_visitors';
		}
		if ( preg_match( '/\bnew\s+visitors?\b/i', $text ) ) {
			$audiences[] = 'new_visitors';
		}
		if ( preg_match( '/\blogged[-\s]?in\s+(?:customers?|users?|visitors?|members?)\b/i', $text ) ) {
			$audiences[] = 'logged_in_customers';
		}
		if ( preg_match( '/\b(?:logged[-\s]?out|guest|anonymous)\s+(?:customers?|users?|visitors?)\b/i', $text ) ) {
			$audiences[] = 'logged_out_customers';
		}

		return array_values( array_unique( $audiences ) );
	}
}
