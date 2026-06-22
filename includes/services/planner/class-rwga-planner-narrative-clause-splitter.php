<?php
/**
 * Split campaign / product narrative phrases into independent action clauses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Narrative_Clause_Splitter {

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( ! preg_match( '/\b(?:set\s+up|setup)\s+.+?\s+campaign\b/i', $phrase )
			&& ! preg_match( '/\bproduct\s+page\b/i', $phrase ) ) {
			return array();
		}

		$block    = $phrase;
		$trailing = array();

		if ( preg_match( '/^(.*?)(?:,\s*(?:and\s+)?add\s+a\s+rule\s+)(.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => 'add a rule ' . trim( (string) $m[2] ),
				'type' => 'rule',
			);
		}

		if ( preg_match( '/^(.*?)(?:\.\s*then\s+)(create\s+(?:a\s+)?(?:second|third|fourth|\d+(?:st|nd|rd|th)?)\s+(?:version|variant).+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'variant_version',
			);
		}

		if ( preg_match( '/^(.*?)(?:,\s*but\s+)(hide\s+.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'rule',
			);
		}

		$clauses = array();
		if ( '' !== trim( $block ) ) {
			$clauses[] = array(
				'raw'   => trim( $block ),
				'index' => 0,
				'type'  => RWGA_Planner_Campaign_Resolver::is_campaign_setup_clause( $block ) ? 'campaign_targeting' : '',
			);
		}

		foreach ( array_reverse( $trailing ) as $idx => $row ) {
			$clauses[] = array(
				'raw'   => (string) ( $row['raw'] ?? '' ),
				'index' => count( $clauses ),
				'type'  => (string) ( $row['type'] ?? '' ),
			);
		}

		return count( $clauses ) >= 2 ? $clauses : array();
	}
}
