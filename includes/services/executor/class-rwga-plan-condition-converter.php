<?php
/**
 * Convert a planner action's include/exclude groups into a portable Geo Core
 * targeting rule set (conditions + visibility mode).
 *
 * Include conditions become positive operators ("in" / "is"); exclude
 * conditions become their negations ("not_in" / "is_not"). Unsupported inputs
 * (regions, raw URLs, custom visitor states) are reported as warnings so the
 * executor can surface a "manual step needed" instead of silently dropping
 * intent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Plan_Condition_Converter {

	/**
	 * @param array<string,mixed> $conditions Action conditions (include/exclude groups).
	 * @param array<string,mixed> $operation  Action operation (mode/visibility).
	 * @return array{conditions:array<int,array<string,mixed>>,mode:string,warnings:array<int,string>}
	 */
	public static function convert( array $conditions, array $operation = array() ) {
		$include = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions )
			: ( is_array( $conditions['include'] ?? null ) ? $conditions['include'] : $conditions );
		$exclude = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions )
			: ( is_array( $conditions['exclude'] ?? null ) ? $conditions['exclude'] : array() );

		$rows     = array();
		$warnings = array();

		self::convert_group( $include, false, $rows, $warnings );
		self::convert_group( $exclude, true, $rows, $warnings );

		return array(
			'conditions' => $rows,
			'mode'       => self::mode_from_operation( $operation ),
			'warnings'   => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * @param array<string,mixed>            $group    Condition group.
	 * @param bool                           $negate   Whether this is the exclude group.
	 * @param array<int,array<string,mixed>> $rows     Output rows (by reference).
	 * @param array<int,string>              $warnings Warnings (by reference).
	 * @return void
	 */
	private static function convert_group( array $group, $negate, array &$rows, array &$warnings ) {
		$countries = self::clean_list( $group['countries'] ?? array() );
		if ( ! empty( $countries ) ) {
			$rows[] = array(
				'type'     => 'country',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtoupper', $countries ),
			);
		}

		$devices = self::clean_list( $group['devices'] ?? array() );
		if ( ! empty( $devices ) ) {
			$rows[] = array(
				'type'     => 'device_type',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtolower', $devices ),
			);
		}

		foreach ( (array) ( $group['utm'] ?? array() ) as $utm ) {
			if ( ! is_array( $utm ) || empty( $utm['key'] ) ) {
				continue;
			}
			$type = strtolower( (string) $utm['key'] );
			if ( ! in_array( $type, array( 'utm_source', 'utm_medium', 'utm_campaign' ), true ) ) {
				$warnings[] = sprintf( 'Unsupported UTM parameter: %s', $type );
				continue;
			}
			$rows[] = array(
				'type'     => $type,
				'operator' => $negate ? 'is_not' : 'is',
				'value'    => array( (string) ( $utm['value'] ?? '' ) ),
			);
		}

		$audiences = self::audience_names( $group['audiences'] ?? array() );
		if ( ! empty( $audiences ) ) {
			$rows[] = array(
				'type'     => 'audience',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => $audiences,
			);
		}

		$weather = self::clean_list( $group['weather'] ?? array() );
		if ( ! empty( $weather ) ) {
			$rows[] = array(
				'type'     => 'weather_facet',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtolower', $weather ),
			);
		}

		foreach ( self::clean_list( $group['visitorStates'] ?? array() ) as $state ) {
			$state = strtolower( $state );
			if ( 'logged_in' === $state ) {
				$rows[] = array( 'type' => 'logged_in', 'operator' => $negate ? 'is_not' : 'is', 'value' => true );
			} elseif ( 'logged_out' === $state ) {
				$rows[] = array( 'type' => 'logged_in', 'operator' => $negate ? 'is' : 'is_not', 'value' => true );
			} else {
				$warnings[] = sprintf( 'Visitor state not supported in rules: %s', $state );
			}
		}

		if ( ! empty( $group['regions'] ) ) {
			$warnings[] = 'Region targeting is not available in portable rules — set it manually.';
		}
		if ( ! empty( $group['urls'] ) ) {
			$warnings[] = 'URL/path targeting must be configured manually.';
		}
	}

	/**
	 * @param array<string,mixed> $operation Operation.
	 * @return string show_if|hide_if
	 */
	private static function mode_from_operation( array $operation ) {
		$visibility = strtolower( (string) ( $operation['visibility'] ?? '' ) );
		return 'hide' === $visibility ? 'hide_if' : 'show_if';
	}

	/**
	 * @param mixed $list Raw list.
	 * @return array<int,string>
	 */
	private static function clean_list( $list ) {
		$out = array();
		foreach ( (array) $list as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $audiences Audience rows (objects or strings).
	 * @return array<int,string>
	 */
	private static function audience_names( $audiences ) {
		$names = array();
		foreach ( (array) $audiences as $audience ) {
			if ( is_array( $audience ) ) {
				$name = trim( (string) ( $audience['name'] ?? '' ) );
			} else {
				$name = trim( (string) $audience );
			}
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}
}
