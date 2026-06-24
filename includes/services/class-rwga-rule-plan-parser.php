<?php
/**
 * Detect and guard rule-creation journeys that must stay a single create_rule action.
 *
 * Phrases like "Create a rule for the Winter Sale page. Show it only to mobile…"
 * describe include/exclude conditions inside one rule — they must not be split into
 * separate show/hide/update_original actions by the multi-clause splitter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Rule_Plan_Parser {

	/**
	 * @param string $phrase Normalised phrase (confirmation/meta already stripped).
	 * @return bool
	 */
	public static function is_rule_plan_command( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		if ( '' === $phrase ) {
			return false;
		}

		if ( preg_match( '/^(?:create|add)\s+(?:a\s+)?rule\b/i', $phrase ) ) {
			if ( preg_match( '/\b(?:create|make|build)\s+(?:\d+|two|three|four|five|\d+)\s+(?:new\s+)?variants?\b/i', $phrase ) ) {
				return false;
			}
			if ( preg_match( '/\b(?:then|and then)\s+(?:create|make|build)\s+(?:\d+|two|three|four|five|\d+|\ba\s+)?(?:variants?|versions?)\b/i', $phrase ) ) {
				return false;
			}
			return true;
		}

		if ( preg_match( '/^(?:show|display)\b.+\bbut\s+exclude\b/is', $phrase )
			&& ! preg_match( '/\b(?:variants?|versions?|variations?)\s+of\b/i', $phrase )
			&& ! preg_match( '/\b(?:leave|keep|update)\s+(?:the\s+)?original\b/i', $phrase ) ) {
			return true;
		}

		return false;
	}
}
