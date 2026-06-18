<?php
/**
 * Extract country targeting for the original/source page (not a duplicate variant).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Original_Source_Targeting_Extractor {

	/** @var string[] */
	const MARKERS = array(
		'original version',
		'original homepage',
		'original page',
		'source page',
		'source version',
		'existing page',
		'existing version',
		'current page',
		'current version',
		'main page',
		'default version',
		'base page',
		'leave the original',
		'keep the original',
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function has_original_marker( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return false;
		}
		foreach ( self::MARKERS as $marker ) {
			if ( false !== strpos( $phrase, $marker ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/\b(?:keep|leave|make)\s+the\s+original\b/i', $phrase );
	}

	/**
	 * @param string           $phrase   Normalised phrase.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<string,mixed>|null
	 */
	public static function extract( $phrase, array $entities ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( ! self::has_original_marker( $phrase ) ) {
			return null;
		}

		$patterns = array(
			'/(?:keep|leave|make)\s+the\s+original(?:\s+(?:version|homepage|page))?\s+(?:for|show\s+in|would\s+show\s+in|should\s+show\s+in|will\s+show\s+in)\s+(.+?)(?=\s+and\s+(?:create|one|\d+(?:st|nd|rd|th)|another)\s+|\s+(?:one|\d+(?:st|nd|rd|th)|another)\s+version|$)/i',
			'/((?:the\s+)?(?:original|existing|current|default|source|main|base)\s+(?:version|homepage|page)?)\s+(?:would\s+show\s+in|should\s+show\s+in|will\s+show\s+in|for|show\s+in)\s+(.+?)(?=\s+(?:one|\d+(?:st|nd|rd|th)|another)\s+version|\s+and\s+(?:the\s+)?(?:one|\d+(?:st|nd|rd|th)|another)\s+version|$)/i',
		);

		foreach ( $patterns as $regex ) {
			if ( ! preg_match( $regex, $phrase, $m ) ) {
				continue;
			}
			$raw          = trim( $m[0] );
			$country_part = trim( (string) $m[ count( $m ) - 1 ] );
			$group        = class_exists( 'RWGA_Variant_Group_Extractor', false )
				? RWGA_Variant_Group_Extractor::group_from_segment( $country_part, $entities )
				: array( 'countries' => array(), 'mode' => 'include_only', 'label' => '' );
			if ( empty( $group['countries'] ) ) {
				continue;
			}
			return array(
				'raw'       => $raw,
				'label'     => __( 'Original homepage', 'reactwoo-geocore' ),
				'countries' => $group['countries'],
				'mode'      => $group['mode'] ?? 'include_only',
			);
		}

		return null;
	}
}
