<?php
/**
 * Build human-readable confirmation copy for interpretation plans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Confirmation_Builder {

	/**
	 * @param array<string,mixed> $plan Interpretation plan.
	 * @return array{summary:string,setup_summary:string}
	 */
	public static function build( array $plan ) {
		$actions  = isset( $plan['actions'] ) && is_array( $plan['actions'] ) ? $plan['actions'] : array();
		$warnings = isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ? $plan['warnings'] : array();
		$count    = count( $actions );

		if ( 0 === $count ) {
			return array(
				'summary'       => __( 'I could not detect any geo actions in that message.', 'reactwoo-geo-ai' ),
				'setup_summary' => __( 'No actions detected', 'reactwoo-geo-ai' ),
			);
		}

		$summary_lines = array(
			sprintf(
				/* translators: %d: action count */
				_n( 'I found %d action:', 'I found %d actions:', $count, 'reactwoo-geo-ai' ),
				$count
			),
			'',
		);
		$setup_lines = array(
			__( 'Setup', 'reactwoo-geocore' ),
			'',
			sprintf(
				/* translators: %d: action count */
				_n( '%d action detected', '%d actions detected', $count, 'reactwoo-geo-ai' ),
				$count
			),
			'',
		);

		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$num          = $idx + 1;
			$title        = self::action_title( $action );
			$type_label   = self::type_label( (string) ( $action['type'] ?? '' ) );
			$location     = self::location_line( $action );
			$summary_lines[] = sprintf( '%d. %s', $num, $title );
			if ( $location ) {
				$summary_lines[] = '   ' . sprintf(
					/* translators: %s: location label */
					__( 'Location: %s', 'reactwoo-geocore' ),
					$location
				);
			}
			$setup_lines[] = sprintf( '%d. %s', $num, $title );
			$setup_lines[] = $type_label;
			if ( $location ) {
				$setup_lines[] = sprintf(
					/* translators: %s: location label */
					__( 'Location: %s', 'reactwoo-geocore' ),
					$location
				);
			}
			$setup_lines[] = '';
		}

		foreach ( $warnings as $warning ) {
			$warning = (string) $warning;
			if ( '' === $warning ) {
				continue;
			}
			$summary_lines[] = '';
			$summary_lines[] = $warning;
			$setup_lines[]   = $warning;
		}

		if ( ! empty( $plan['clarification'] ) ) {
			$summary_lines[] = '';
			$summary_lines[] = (string) ( $plan['clarification']['message'] ?? '' );
		} else {
			$summary_lines[] = '';
			$summary_lines[] = __( 'Is this correct?', 'reactwoo-geo-ai' );
		}

		$setup_lines[] = __( 'Status', 'reactwoo-geocore' );
		$setup_lines[] = self::status_label( (string) ( $plan['status'] ?? '' ) );

		return array(
			'summary'       => implode( "\n", array_filter( $summary_lines, static function ( $line ) {
				return '' !== $line || "\n" === $line;
			} ) ),
			'setup_summary' => implode( "\n", $setup_lines ),
		);
	}

	/**
	 * @param array<string,mixed> $action Action row.
	 * @return string
	 */
	private static function action_title( array $action ) {
		$type   = (string) ( $action['type'] ?? '' );
		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$label  = (string) ( $target['label'] ?? 'page' );

		switch ( $type ) {
			case RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING:
				return sprintf(
					/* translators: %s: page label */
					__( 'Update %s targeting', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_VARIANT:
				return sprintf(
					/* translators: %s: page label */
					__( 'Create %s variant', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_RULE:
				return sprintf(
					/* translators: %s: target label */
					__( 'Rule for %s', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_TEST:
				return sprintf(
					/* translators: %s: page label */
					__( 'Test %s view', 'reactwoo-geo-ai' ),
					$label
				);
			default:
				return ucfirst( $label );
		}
	}

	/**
	 * @param string $type Action type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$map = array(
			RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING => __( 'Update original targeting', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_VARIANT            => __( 'Create variant', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_RULE               => __( 'Create rule', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_TEST               => __( 'Create test', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::DIAGNOSE                  => __( 'Diagnose', 'reactwoo-geo-ai' ),
		);
		return $map[ $type ] ?? $type;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function location_line( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$labels     = (array) ( $action['location_labels'] ?? array() );
		if ( ! empty( $labels ) ) {
			return implode( ' + ', $labels );
		}
		foreach ( (array) ( $conditions['regions'] ?? array() ) as $region ) {
			if ( 'GB-ENG' === $region ) {
				$labels[] = 'England';
			} else {
				$labels[] = (string) $region;
			}
		}
		if ( ! empty( $labels ) ) {
			return implode( ' + ', $labels );
		}
		return RWGA_Planner_Location_Resolver::display_label(
			array(
				'countries' => $conditions['countries'] ?? array(),
				'regions'   => $conditions['regions'] ?? array(),
				'labels'    => array(),
			)
		);
	}

	/**
	 * @param string $status Plan status.
	 * @return string
	 */
	private static function status_label( $status ) {
		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION === $status ) {
			return __( 'Needs clarification', 'reactwoo-geocore' );
		}
		if ( RWGA_Geo_Action_Types::STATUS_READY === $status ) {
			return __( 'Ready', 'reactwoo-geocore' );
		}
		return __( 'Needs confirmation', 'reactwoo-geocore' );
	}
}
