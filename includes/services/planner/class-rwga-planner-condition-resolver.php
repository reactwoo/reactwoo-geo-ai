<?php
/**
 * Resolve per-clause targeting conditions (location, device, weather, audience)
 * with include/exclude polarity. Audiences resolve against synced data only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Condition_Resolver {

	/**
	 * @param string              $clause   Clause text (or sub-segment).
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Planner context (synced registries).
	 * @return array{conditions:array,warnings:array,confidence:float,location_labels:array,unresolved:array}
	 */
	public static function resolve( $clause, array $entities, array $context = array() ) {
		$clause  = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$split   = RWGA_Planner_Condition_Polarity_Resolver::split_text( $clause );
		$include = self::resolve_group( (string) $split['include_text'], $entities, $context );
		$exclude = '' !== (string) $split['exclude_text']
			? self::resolve_group( (string) $split['exclude_text'], $entities, $context )
			: self::empty_group();

		$conditions = array(
			'include' => self::group_conditions( $include ),
			'exclude' => self::group_conditions( $exclude ),
		);

		$warnings   = array_values( array_unique( array_merge( $include['warnings'], $exclude['warnings'] ) ) );
		$unresolved = array(
			'audiences' => array_merge(
				(array) ( $include['unresolved_audiences'] ?? array() ),
				(array) ( $exclude['unresolved_audiences'] ?? array() )
			),
		);

		$confidence = 0.7;
		if ( ! empty( $include['countries'] ) || ! empty( $include['regions'] ) || ! empty( $exclude['countries'] ) ) {
			$confidence = 0.88;
		}
		if ( ! empty( $include['devices'] ) || ! empty( $exclude['devices'] ) ) {
			$confidence = min( 0.95, $confidence + 0.04 );
		}
		if ( ! empty( $include['urls'] ) ) {
			$confidence = min( 0.96, $confidence + 0.03 );
		}

		return array(
			'conditions'      => $conditions,
			'warnings'        => $warnings,
			'location_labels' => $include['labels'],
			'confidence'      => $confidence,
			'unresolved'      => $unresolved,
		);
	}

	/**
	 * @param array<string,mixed> $group Group row.
	 * @return array<string,mixed>
	 */
	private static function group_conditions( array $group ) {
		return array(
			'countries'     => $group['countries'],
			'regions'       => $group['regions'],
			'devices'       => $group['devices'],
			'weather'       => $group['weather'],
			'urls'          => $group['urls'],
			'utm'           => $group['utm'],
			'campaigns'     => $group['campaigns'],
			'audiences'     => $group['audiences'],
			'visitorStates' => $group['visitorStates'],
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function empty_group() {
		return array(
			'countries'            => array(),
			'regions'              => array(),
			'devices'              => array(),
			'weather'              => array(),
			'urls'                 => array(),
			'utm'                  => array(),
			'campaigns'            => array(),
			'audiences'            => array(),
			'visitorStates'        => array(),
			'unresolved_audiences' => array(),
			'warnings'             => array(),
			'labels'               => array(),
		);
	}

	/**
	 * @param string              $text     Text segment.
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	private static function resolve_group( $text, array $entities, array $context = array() ) {
		$text     = RWGA_Local_Intent_Interpreter::normalise( $text );
		$location = RWGA_Planner_Location_Resolver::resolve_from_text( $text, $entities );
		$devices  = self::detect_devices( $text );
		$weather  = class_exists( 'RWGA_Segment_Condition_Extractor', false )
			? RWGA_Segment_Condition_Extractor::extract_weather( $text, $entities )
			: null;
		$urls = class_exists( 'RWGA_Planner_Url_Condition_Resolver', false )
			? RWGA_Planner_Url_Condition_Resolver::extract_paths( $text )
			: array();
		$utm = class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false )
			? RWGA_Planner_Utm_Condition_Resolver::extract( $text )
			: array();
		$audience = class_exists( 'RWGA_Planner_Audience_Resolver', false )
			? RWGA_Planner_Audience_Resolver::resolve( $text, $entities, $context )
			: array( 'audiences' => array(), 'visitorStates' => array(), 'unresolved' => array() );
		$weather_values = self::detect_weather_values( $text, $entities, $weather );

		return array(
			'countries'            => $location['countries'],
			'regions'              => $location['regions'],
			'devices'              => $devices,
			'weather'              => $weather_values,
			'urls'                 => $urls,
			'utm'                  => $utm,
			'campaigns'            => array(),
			'audiences'            => (array) ( $audience['audiences'] ?? array() ),
			'visitorStates'        => (array) ( $audience['visitorStates'] ?? array() ),
			'unresolved_audiences' => (array) ( $audience['unresolved'] ?? array() ),
			'warnings'             => $location['warnings'],
			'labels'               => $location['labels'],
		);
	}

	/**
	 * @param string                $text        Text.
	 * @param array<int,array>      $entities    Entities.
	 * @param string|null           $weather_key Extracted weather key.
	 * @return array<int,string>
	 */
	private static function detect_weather_values( $text, array $entities, $weather_key ) {
		if ( preg_match( '/\brainy\b/i', $text ) ) {
			return array( 'rainy' );
		}
		if ( null !== $weather_key && '' !== $weather_key ) {
			if ( 'rain' === $weather_key && preg_match( '/\brainy\b/i', $text ) ) {
				return array( 'rainy' );
			}
			return array( (string) $weather_key );
		}
		return array();
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
