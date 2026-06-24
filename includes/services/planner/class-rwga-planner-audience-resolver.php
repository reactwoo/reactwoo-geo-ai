<?php
/**
 * Detect visitor conditions from clause text.
 *
 * Two distinct things are handled here:
 *
 *  1. Native visitor states (e.g. logged-in / guest) — these are real Geo Core
 *     conditions (`logged_in`) and resolve to a `visitorStates` value.
 *  2. Audience phrases (returning visitors, VIP customers, newsletter
 *     subscribers, …) — these are NOT invented as slugs. They are resolved
 *     against the site's synced audience registry. Anything that does not match
 *     becomes an unresolved candidate so the assistant can ask for clarification.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Audience_Resolver {

	/**
	 * Native visitor-state conditions supported by Geo Core's evaluator.
	 *
	 * @var array<string,string> regex => state
	 */
	const NATIVE_VISITOR_STATES = array(
		'/\b(?:logged[-\s]?in|signed[-\s]?in)\s+(?:customers?|users?|visitors?|members?|people|shoppers?)\b/i' => 'logged_in',
		'/\b(?:logged[-\s]?in|signed[-\s]?in)\b/i'                                                              => 'logged_in',
		'/\b(?:logged[-\s]?out|signed[-\s]?out)\s+(?:customers?|users?|visitors?|members?|people|shoppers?)\b/i' => 'logged_out',
		'/\b(?:logged[-\s]?out|signed[-\s]?out)\b/i'                                                             => 'logged_out',
		'/\b(?:guest|anonymous)\s+(?:customers?|users?|visitors?|shoppers?)\b/i'                                 => 'logged_out',
	);

	/**
	 * Curated audience phrase patterns (resolved against the synced registry).
	 *
	 * @var array<int,string>
	 */
	const AUDIENCE_PATTERNS = array(
		'/\breturning\s+(?:visitors?|customers?|buyers?|shoppers?)\b/i',
		'/\bfirst[-\s]?time\s+(?:visitors?|customers?|buyers?|shoppers?)\b/i',
		'/\bnew\s+(?:visitors?|customers?)\b/i',
		'/\brepeat\s+(?:buyers?|customers?|shoppers?)\b/i',
		'/\bloyal\s+(?:customers?|members?|shoppers?)\b/i',
		'/\bvip\s+(?:customers?|members?|shoppers?|users?|visitors?|buyers?)\b/i',
		'/\bnewsletter\s+subscribers?\b/i',
		'/\bhigh[-\s]?intent\s+(?:users?|visitors?|shoppers?|buyers?|customers?)\b/i',
		'/\babandoned[-\s]?cart\s+(?:visitors?|users?|shoppers?|customers?)\b/i',
		'/\b([a-z][a-z-]+(?:\s+[a-z][a-z-]+){0,2})\s+(?:audience|segment)\b/i',
		'/\b([a-z][a-z-]+(?:\s+[a-z][a-z-]+){0,1})\s+(?:subscribers?|members?)\b/i',
	);

	/** Words that should never start an audience phrase. */
	const STOPWORDS = array( 'to', 'the', 'a', 'an', 'in', 'for', 'from', 'of', 'and', 'or', 'this', 'that', 'it', 'only', 'all', 'show', 'hide', 'who', 'with', 'so', 'just' );

	/**
	 * Resolve visitor states + synced audiences from text.
	 *
	 * @param string              $text     Normalised text.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Planner context (may carry syncedAudiences).
	 * @return array{audiences:array<int,array>,visitorStates:array<int,string>,unresolved:array<int,array>}
	 */
	public static function resolve( $text, array $entities = array(), array $context = array() ) {
		$text   = RWGA_Local_Intent_Interpreter::normalise( $text );
		$states = array();

		// "audience matches any" / "any audience" is an explicit decision between
		// "no audience restriction" and "selected audience groups" — not a synced
		// audience lookup. Surface it as a distinct ambiguity and strip it so the
		// generic phrase detector does not also flag a bare "audience".
		$audience_any = false;
		if ( preg_match( '/\b(?:audiences?\s+match(?:es)?\s+any|match(?:es)?\s+any\s+audience|any\s+audience|all\s+audiences?)\b/i', $text ) ) {
			$audience_any = true;
			$text         = (string) preg_replace( '/\b(?:the\s+)?audiences?\s+match(?:es)?\s+any\b/i', ' ', $text );
			$text         = (string) preg_replace( '/\b(?:to\s+|for\s+)?any\s+audience\b/i', ' ', $text );
			$text         = (string) preg_replace( '/\b(?:match(?:es)?\s+any\s+audience|all\s+audiences?)\b/i', ' ', $text );
			$text         = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		}

		foreach ( self::NATIVE_VISITOR_STATES as $pattern => $state ) {
			if ( preg_match( $pattern, $text ) ) {
				$states[] = $state;
				$text     = trim( (string) preg_replace( $pattern, ' ', $text ) );
			}
		}

		$registry   = class_exists( 'RWGA_Planner_Synced_Entity_Resolver', false )
			? RWGA_Planner_Synced_Entity_Resolver::audiences( $context, $entities )
			: array();
		$matched    = array();
		$unresolved = array();

		foreach ( self::detect_phrases( $text ) as $raw ) {
			if ( ! class_exists( 'RWGA_Planner_Synced_Entity_Resolver', false ) ) {
				continue;
			}
			$resolution = RWGA_Planner_Synced_Entity_Resolver::resolve_phrase( $raw, $registry );
			if ( RWGA_Planner_Synced_Entity_Resolver::STATUS_MATCHED === $resolution['status'] ) {
				$matched[] = array_merge( $resolution['matched'], array( 'raw' => $resolution['raw'] ) );
			} else {
				$unresolved[] = $resolution;
			}
		}

		if ( $audience_any ) {
			$unresolved[] = array(
				'raw'         => 'audience matches any',
				'status'      => 'audience_any',
				'mode'        => 'any',
				'suggestions' => array(),
				'message'     => __( 'Choose whether this means any audience or selected audience groups.', 'reactwoo-geocore' ),
			);
		}

		return array(
			'audiences'     => $matched,
			'visitorStates' => array_values( array_unique( $states ) ),
			'unresolved'    => $unresolved,
		);
	}

	/**
	 * Detect raw audience phrases (after native visitor states are stripped).
	 *
	 * @param string $text Text.
	 * @return array<int,string>
	 */
	public static function detect_phrases( $text ) {
		$text   = RWGA_Local_Intent_Interpreter::normalise( $text );
		$found  = array();

		foreach ( self::AUDIENCE_PATTERNS as $pattern ) {
			if ( ! preg_match_all( $pattern, $text, $matches ) ) {
				continue;
			}
			foreach ( $matches[0] as $match ) {
				$phrase = self::clean_phrase( $match );
				if ( '' !== $phrase ) {
					$found[ $phrase ] = true;
				}
			}
		}

		return array_keys( $found );
	}

	/**
	 * Backwards-compatible helper: raw detected audience phrases.
	 *
	 * @param string $text Text.
	 * @return array<int,string>
	 */
	public static function extract( $text ) {
		return self::detect_phrases( $text );
	}

	/**
	 * Native visitor states detected in the text.
	 *
	 * @param string $text Text.
	 * @return array<int,string>
	 */
	public static function detect_visitor_states( $text ) {
		$text   = RWGA_Local_Intent_Interpreter::normalise( $text );
		$states = array();
		foreach ( self::NATIVE_VISITOR_STATES as $pattern => $state ) {
			if ( preg_match( $pattern, $text ) ) {
				$states[] = $state;
			}
		}
		return array_values( array_unique( $states ) );
	}

	/**
	 * @param string $phrase Candidate phrase.
	 * @return string
	 */
	private static function clean_phrase( $phrase ) {
		$phrase = strtolower( trim( (string) $phrase ) );
		$phrase = (string) preg_replace( '/\s+/', ' ', $phrase );

		$tokens = array_values( array_filter( explode( ' ', $phrase ) ) );
		while ( ! empty( $tokens ) && in_array( $tokens[0], self::STOPWORDS, true ) ) {
			array_shift( $tokens );
		}
		return implode( ' ', $tokens );
	}
}
