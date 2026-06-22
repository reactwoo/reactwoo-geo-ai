<?php
/**
 * Resolve per-clause targeting conditions (location, device, weather).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Condition_Resolver {

	/**
	 * @param string           $clause   Clause text (or sub-segment).
	 * @param array<int,array> $entities Entity rows.
	 * @return array{conditions:array,warnings:array,confidence:float}
	 */
	public static function resolve( $clause, array $entities ) {
		$clause   = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$location = RWGA_Planner_Location_Resolver::resolve_from_text( $clause, $entities );
		$devices  = self::detect_devices( $clause );
		$weather  = class_exists( 'RWGA_Segment_Condition_Extractor', false )
			? RWGA_Segment_Condition_Extractor::extract_weather( $clause, $entities )
			: null;

		$conditions = array(
			'countries'  => $location['countries'],
			'regions'    => $location['regions'],
			'devices'    => $devices,
			'weather'    => array_filter( array( $weather ) ),
			'campaigns'  => array(),
			'urls'       => array(),
			'audiences'  => array(),
		);

		$confidence = 0.7;
		if ( ! empty( $conditions['countries'] ) || ! empty( $conditions['regions'] ) ) {
			$confidence = 0.88;
		}
		if ( ! empty( $devices ) ) {
			$confidence = min( 0.95, $confidence + 0.04 );
		}

		return array(
			'conditions'       => $conditions,
			'warnings'         => $location['warnings'],
			'location_labels'  => $location['labels'],
			'confidence'       => $confidence,
		);
	}

	/**
	 * @param string $clause Clause.
	 * @return array<int,string>
	 */
	private static function detect_devices( $clause ) {
		$devices = array();
		if ( preg_match( '/\bmobile\b/i', $clause ) ) {
			$devices[] = 'mobile';
		}
		if ( preg_match( '/\bdesktop\b/i', $clause ) ) {
			$devices[] = 'desktop';
		}
		if ( preg_match( '/\btablet\b/i', $clause ) ) {
			$devices[] = 'tablet';
		}
		return array_values( array_unique( $devices ) );
	}
}
