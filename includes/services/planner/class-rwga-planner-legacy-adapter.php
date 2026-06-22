<?php
/**
 * Convert interpretation plans to legacy interpreter / proposal shapes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Legacy_Adapter {

	/**
	 * @param array<string,mixed> $plan     Interpretation plan.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	public static function to_interpreter_result( array $plan, array $context = array(), array $entities = array() ) {
		unset( $context, $entities );
		$actions  = isset( $plan['actions'] ) && is_array( $plan['actions'] ) ? $plan['actions'] : array();
		$copy     = RWGA_Planner_Confirmation_Builder::build( $plan );
		$warnings = isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ? $plan['warnings'] : array();
		$status   = (string) ( $plan['status'] ?? RWGA_Geo_Action_Types::STATUS_NEEDS_CONFIRMATION );

		$has_original = false;
		$variants     = array();
		$source_page  = 'homepage';
		$steps        = array();

		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type = (string) ( $action['type'] ?? '' );
			if ( RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $type ) {
				$has_original = true;
				$source_page  = (string) ( $action['target']['slug'] ?? 'homepage' );
			}
			if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type ) {
				$source_page = (string) ( $action['target']['slug'] ?? $source_page );
				$variants[]  = self::variant_param( $action );
			}
			$steps[] = array(
				'label'  => self::step_label( $action, $idx ),
				'action' => self::matched_action_for_type( $type ),
				'params' => self::step_params( $action ),
			);
		}

		$intent         = RWGA_Geo_Action_Types::PLAN_INTENT;
		$matched_action = 'geocore_geo_targeting_plan';
		$params         = array(
			'interpretation_plan' => $plan,
			'actions'             => $actions,
		);

		if ( $has_original || count( $variants ) > 0 ) {
			$intent         = 'create_geo_variant_plan';
			$matched_action = 'geocore_create_variant_plan_with_country_rules';
			$params         = array_merge(
				$params,
				array(
					'source_page_ref'     => $source_page,
					'variant_source_page' => $source_page,
					'variants'            => $variants,
					'duplicate_count'     => count( $variants ),
				)
			);
			if ( $has_original ) {
				foreach ( $actions as $action ) {
					if ( ! is_array( $action ) || RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING !== (string) ( $action['type'] ?? '' ) ) {
						continue;
					}
					$params['source_targeting'] = self::source_targeting_param( $action );
					break;
				}
			}
		} elseif ( 1 === count( $actions ) ) {
			$single = $actions[0];
			$type   = (string) ( $single['type'] ?? '' );
			if ( RWGA_Geo_Action_Types::CREATE_RULE === $type || RWGA_Geo_Action_Types::HIDE === $type ) {
				$intent         = 'country_exclude';
				$matched_action = 'geocore_create_country_rule';
				if ( 'show' === (string) ( $single['operation']['visibility'] ?? '' ) || 'only_show' === (string) ( $single['operation']['visibility'] ?? '' ) ) {
					$intent = 'country_include';
				}
				$params = array_merge( $params, self::step_params( $single ) );
			} elseif ( RWGA_Geo_Action_Types::CREATE_TEST === $type ) {
				$intent         = 'geo_preview_test';
				$matched_action = 'geocore_create_geo_test';
				$params         = array_merge( $params, self::step_params( $single ) );
			}
		}

		$missing = array();
		if ( ! empty( $plan['clarification'] ) && is_array( $plan['clarification'] ) ) {
			$missing[] = array(
				'key'      => (string) ( $plan['clarification']['type'] ?? 'clarification' ),
				'question' => (string) ( $plan['clarification']['message'] ?? '' ),
				'options'  => $plan['clarification']['options'] ?? array(),
			);
		}

		$proposal_ready = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION !== $status
			&& RWGA_Geo_Action_Types::STATUS_FAILED !== $status
			&& empty( $missing );

		return array(
			'matched'                 => count( $actions ) > 0,
			'intent'                    => $intent,
			'matched_action'            => $matched_action,
			'confidence'                => (float) ( $plan['confidence'] ?? 0 ),
			'proposal_ready'            => $proposal_ready,
			'interpretation_status'     => self::interpretation_status( $status ),
			'requires_confirmation'     => true,
			'summary'                   => $copy['summary'],
			'setup_summary'             => $copy['setup_summary'],
			'interpretation_plan'       => $plan,
			'params'                    => $params,
			'steps'                     => $steps,
			'warnings'                  => $warnings,
			'missing_information'       => $missing,
			'interpretation_source'     => 'geo_assistant_planner',
		);
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>
	 */
	private static function variant_param( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$countries  = (array) ( $conditions['countries'] ?? array() );
		if ( ! empty( $conditions['regions'] ) ) {
			foreach ( (array) $conditions['regions'] as $region ) {
				if ( 'GB-ENG' === $region && ! in_array( 'GB', $countries, true ) ) {
					$countries[] = 'GB';
				}
			}
		}
		return array(
			'label'     => (string) ( $action['variant']['label'] ?? '' ),
			'countries' => array_values( array_unique( $countries ) ),
			'regions'   => (array) ( $conditions['regions'] ?? array() ),
			'mode'      => 'include_only',
			'raw'       => (string) ( $action['sourceClause'] ?? '' ),
		);
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>
	 */
	private static function source_targeting_param( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$countries  = (array) ( $conditions['countries'] ?? array() );
		$regions    = (array) ( $conditions['regions'] ?? array() );
		if ( in_array( 'GB-ENG', $regions, true ) && ! in_array( 'GB', $countries, true ) ) {
			$countries[] = 'GB';
		}
		$label = RWGA_Planner_Location_Resolver::display_label(
			array(
				'countries' => $countries,
				'regions'   => $regions,
				'labels'    => array(),
			)
		);
		return array(
			'label'           => (string) ( $action['variant']['label'] ?? '' ),
			'targeting_label' => $label,
			'mode'            => 'include_only',
			'countries'       => array_values( array_unique( $countries ) ),
			'regions'         => $regions,
			'raw'             => (string) ( $action['sourceClause'] ?? '' ),
		);
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param int                 $idx    Index.
	 * @return string
	 */
	private static function step_label( array $action, $idx ) {
		$title = RWGA_Planner_Confirmation_Builder::build(
			array(
				'actions' => array( $action ),
				'status'  => RWGA_Geo_Action_Types::STATUS_DRAFT,
			)
		);
		$lines = explode( "\n", (string) ( $title['summary'] ?? '' ) );
		foreach ( $lines as $line ) {
			if ( preg_match( '/^\d+\.\s+/', $line ) ) {
				return preg_replace( '/^\d+\.\s+/', '', $line );
			}
		}
		return sprintf( 'Action %d', $idx + 1 );
	}

	/**
	 * @param string $type Action type.
	 * @return string
	 */
	private static function matched_action_for_type( $type ) {
		$map = array(
			RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING => 'geocore_update_original_targeting',
			RWGA_Geo_Action_Types::CREATE_VARIANT            => 'geocore_create_variant',
			RWGA_Geo_Action_Types::CREATE_RULE               => 'geocore_create_country_rule',
			RWGA_Geo_Action_Types::CREATE_TEST               => 'geocore_create_geo_test',
			RWGA_Geo_Action_Types::DIAGNOSE                  => 'geocore_diagnose_targeting',
		);
		return $map[ $type ] ?? 'geocore_geo_targeting_plan';
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>
	 */
	private static function step_params( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		return array(
			'page_ref'  => (string) ( $action['target']['slug'] ?? '' ),
			'countries' => (array) ( $conditions['countries'] ?? array() ),
			'regions'   => (array) ( $conditions['regions'] ?? array() ),
			'devices'   => (array) ( $conditions['devices'] ?? array() ),
			'mode'      => 'hide' === (string) ( $action['operation']['visibility'] ?? '' ) ? 'exclude' : 'include_only',
			'target'    => $action['target'] ?? array(),
		);
	}

	/**
	 * @param string $status Plan status.
	 * @return string
	 */
	private static function interpretation_status( $status ) {
		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION === $status ) {
			return class_exists( 'RWGA_Interpretation_Status', false )
				? RWGA_Interpretation_Status::NEEDS_CONFIRMATION
				: 'needs_clarification';
		}
		if ( RWGA_Geo_Action_Types::STATUS_READY === $status ) {
			return class_exists( 'RWGA_Interpretation_Status', false )
				? RWGA_Interpretation_Status::COMPLETE
				: 'ready';
		}
		return class_exists( 'RWGA_Interpretation_Status', false )
			? RWGA_Interpretation_Status::NEEDS_CONFIRMATION
			: 'needs_confirmation';
	}
}
