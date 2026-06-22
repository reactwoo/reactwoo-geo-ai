<?php
/**
 * Local-first multi-action geo assistant interpretation planner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Geo_Assistant_Planner {

	const AI_CONFIDENCE_THRESHOLD = 0.72;

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    Context.
	 * @param array<int,array>    $entities   Entity rows.
	 * @return array<string,mixed>
	 */
	public static function interpret( $raw_phrase, array $context = array(), array $entities = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $raw_phrase );
		$plan   = self::empty_plan( $raw_phrase );
		$decisions = array();

		if ( '' === $phrase ) {
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_FAILED;
			return $plan;
		}

		$learned = RWGA_Planner_Learned_Patterns::match( $phrase );
		if ( is_array( $learned ) ) {
			$decisions[] = 'learned_pattern_matched';
		}

		$page_context = array();
		$session      = array( 'currentTarget' => null );
		$campaign     = class_exists( 'RWGA_Planner_Campaign_Resolver', false )
			? RWGA_Planner_Campaign_Resolver::detect_from_clause( $phrase )
			: null;

		$actions = array();
		$clauses = RWGA_Planner_Action_Clause_Splitter::split( $phrase );
		$plan['debug']['clauses'] = $clauses;

		if ( count( $clauses ) > 1 || self::clauses_have_typed_rows( $clauses ) ) {
			foreach ( $clauses as $clause_row ) {
				$context['session']  = $session;
				$context['campaign'] = $campaign;
				$built = self::build_actions_from_clause_row(
					$clause_row,
					$phrase,
					$entities,
					$context,
					$page_context
				);
				foreach ( $built as $action ) {
					if ( is_array( $action ) && class_exists( 'RWGA_Planner_Inherited_Target_Resolver', false ) ) {
						$session = RWGA_Planner_Inherited_Target_Resolver::remember_target(
							$session,
							is_array( $action['target'] ?? null ) ? $action['target'] : array()
						);
					}
				}
				$actions = array_merge( $actions, $built );
			}
			$decisions[] = 'multi_clause_split';
		} elseif ( ! self::has_multi_action_markers( $phrase )
			&& class_exists( 'RWGA_Variant_Plan_Parser', false )
			&& RWGA_Variant_Plan_Parser::is_variant_plan_command( $phrase ) ) {
			$variant_plan = RWGA_Variant_Plan_Parser::parse( $raw_phrase, $entities, $context );
			$actions      = RWGA_Planner_Variant_Resolver::from_variant_plan_parse( $variant_plan, $entities );
			if ( empty( $actions ) ) {
				$actions = self::build_actions_from_clause( $phrase, $phrase, $entities, $context, $page_context );
			}
			$decisions[] = 'variant_plan_parser';
		} else {
			$actions = self::build_actions_from_clause( $phrase, $phrase, $entities, $context, $page_context );
		}

		if ( self::is_variant_list_grouping_ambiguous( $phrase, $entities )
			&& count( $actions ) < 2 ) {
			$plan['clarification'] = array(
				'type'    => 'variant_grouping',
				'message' => __( 'Do you want one shared variant for all listed countries, or separate variants for each country?', 'reactwoo-geocore' ),
				'options' => array(
					array( 'label' => __( 'One shared variant', 'reactwoo-geocore' ), 'value' => 'shared_variant' ),
					array( 'label' => __( 'Separate variants per country', 'reactwoo-geocore' ), 'value' => 'separate_variants' ),
				),
			);
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
			$decisions[]    = 'variant_grouping_ambiguous';
		}

		$page_context = class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_page_context( $phrase )
			: array();
		$clarification = RWGA_Planner_Resolve_Clarifications::detect( $actions, $phrase, $page_context, $entities );
		if ( is_array( $clarification ) ) {
			$plan['clarification'] = $clarification;
			$plan['status']        = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
		}

		$warnings = array();
		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( ! empty( $action['warnings'] ) && is_array( $action['warnings'] ) ) {
				$warnings = array_merge( $warnings, $action['warnings'] );
				continue;
			}
			$loc = RWGA_Planner_Location_Resolver::resolve_from_text(
				(string) ( $action['sourceClause'] ?? $phrase ),
				$entities
			);
			if ( ! empty( $loc['warnings'] ) ) {
				$actions[ $idx ]['warnings'] = $loc['warnings'];
				$warnings                    = array_merge( $warnings, $loc['warnings'] );
			}
		}
		$plan['warnings'] = array_values( array_unique( $warnings ) );
		$plan['actions']  = $actions;
		$plan['confidence'] = self::plan_confidence( $actions, $learned );
		$plan['debug']['decisions'] = $decisions;

		if ( class_exists( 'RWGA_Planner_Plan_Validator', false ) ) {
			$validation = RWGA_Planner_Plan_Validator::validate( $phrase, $actions, $entities, $clauses );
			if ( is_array( $validation ) ) {
				$plan['debug']['draft_actions'] = $actions;
				$plan['actions']                = array();
				$plan['clarification']          = $validation;
				$plan['status']                 = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
				$plan['confidence']             = min( (float) $plan['confidence'], 0.45 );
				$decisions[]                    = 'plan_validation_failed';
				$plan['debug']['decisions']     = $decisions;
			}
		}

		if ( empty( $plan['actions'] ) && empty( $plan['clarification'] ) ) {
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_FAILED;
			return $plan;
		}

		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION !== $plan['status'] ) {
			$plan['status'] = $plan['confidence'] >= self::AI_CONFIDENCE_THRESHOLD
				? RWGA_Geo_Action_Types::STATUS_NEEDS_CONFIRMATION
				: RWGA_Geo_Action_Types::STATUS_DRAFT;
		}

		$ai_plan = RWGA_Planner_Ai_Fallback::maybe_improve( $raw_phrase, $plan, $context, $entities );
		if ( is_array( $ai_plan ) ) {
			$plan = array_merge( $plan, $ai_plan );
			$plan['debug']['decisions'][] = 'ai_fallback_merged';
		}

		$copy = RWGA_Planner_Confirmation_Builder::build( $plan );
		$plan['confirmationSummary'] = $copy['summary'];
		$plan['setupSummary']        = $copy['setup_summary'];

		return $plan;
	}

	/**
	 * @param array<string,mixed> $clause_row   Clause row from splitter.
	 * @param string              $phrase       Full phrase.
	 * @param array<int,array>    $entities     Entities.
	 * @param array<string,mixed> $context      Context.
	 * @param array<string,mixed> $page_context Page context.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_actions_from_clause_row( array $clause_row, $phrase, array $entities, array $context, array $page_context ) {
		$clause = trim( (string) ( $clause_row['raw'] ?? '' ) );
		$type   = (string) ( $clause_row['type'] ?? '' );

		if ( 'variant_child' === $type && ! empty( $clause_row['parent'] ) && is_array( $clause_row['parent'] ) ) {
			$child_index = isset( $clause_row['childIndex'] )
				? (int) $clause_row['childIndex']
				: ( isset( $clause_row['index'] ) ? (int) $clause_row['index'] + 1 : null );
			return array(
				RWGA_Planner_Parent_Variant_Resolver::build_child_action(
					$clause,
					$clause_row['parent'],
					$entities,
					$child_index
				),
			);
		}

		return self::build_actions_from_clause( $clause, $phrase, $entities, $context, $page_context, $clause_row );
	}

	/**
	 * @param array<int,array<string,mixed>> $clauses Clause rows.
	 * @return bool
	 */
	private static function clauses_have_typed_rows( array $clauses ) {
		foreach ( $clauses as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['type'] ?? '' );
			if ( in_array( $type, array( 'variant_child', 'rule', 'test', 'diagnose', 'update', 'campaign_targeting', 'variant_version', 'variant_create' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string                   $clause       Clause text.
	 * @param string                   $phrase       Full phrase.
	 * @param array<int,array>         $entities     Entities.
	 * @param array<string,mixed>      $context      Context.
	 * @param array<string,mixed>      $page_context Page context.
	 * @param array<string,mixed>|null $clause_row   Optional clause metadata.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_actions_from_clause( $clause, $phrase, array $entities, array $context, array $page_context, $clause_row = null ) {
		unset( $page_context );
		$clause   = trim( (string) $clause );
		$type_row = RWGA_Planner_Action_Type_Detector::detect( $clause, is_array( $clause_row ) ? $clause_row : array() );
		$target   = RWGA_Planner_Target_Resolver::resolve( $clause, $phrase, $context, (string) $type_row['type'], is_array( $clause_row ) ? $clause_row : array() );

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type']
			&& ( RWGA_Planner_Action_Clause_Splitter::has_variant_pair_marker( $clause )
				|| preg_match( '/\bone\s+will\b|\bone\s+should\b/i', $clause ) ) ) {
			$expanded = RWGA_Planner_Variant_Resolver::expand_from_clause( $clause, $target, $entities, $type_row );
			if ( ! empty( $expanded ) ) {
				return $expanded;
			}
		}

		$cond         = RWGA_Planner_Condition_Resolver::resolve( $clause, $entities );
		$relationship = RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $type_row['type'] ? 'original' : 'variant';
		$variant_idx  = RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type'] ? 1 : null;
		$variant_lbl  = '';

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type']
			&& class_exists( 'RWGA_Planner_Second_Version_Resolver', false ) ) {
			$version_row = RWGA_Planner_Second_Version_Resolver::detect( $clause );
			if ( is_array( $version_row ) ) {
				$variant_idx = (int) ( $version_row['index'] ?? 2 );
			}
		}
		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type'] && '' === $variant_lbl ) {
			$variant_lbl = self::variant_label_from_conditions( $target, $cond );
		}

		$campaign_row = class_exists( 'RWGA_Planner_Campaign_Resolver', false )
			? RWGA_Planner_Campaign_Resolver::detect_from_clause( $clause )
			: null;

		$action = array(
			'id'                  => self::new_id(),
			'type'                => (string) $type_row['type'],
			'target'              => $target,
			'variant'             => array(
				'index'        => $variant_idx,
				'label'        => $variant_lbl,
				'sourcePage'   => (string) ( $target['label'] ?? '' ),
				'relationship' => $relationship,
			),
			'conditions'          => $cond['conditions'],
			'location_labels'     => $cond['location_labels'] ?? array(),
			'warnings'            => $cond['warnings'] ?? array(),
			'operation'           => array(
				'visibility' => (string) $type_row['visibility'],
				'mode'       => (string) $type_row['mode'],
			),
			'confidence'          => min( (float) $type_row['confidence'], (float) $cond['confidence'] ),
			'needsClarification'  => false,
			'clarificationReason' => null,
			'sourceClause'        => $clause,
		);

		if ( RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING === $type_row['type']
			&& is_array( $campaign_row ) ) {
			$action['campaign'] = $campaign_row;
		}

		return array( $action );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	private static function has_multi_action_markers( $phrase ) {
		return class_exists( 'RWGA_Planner_Plan_Validator', false )
			&& RWGA_Planner_Plan_Validator::expected_action_count( $phrase ) > 1;
	}

	/**
	 * @param array<string,mixed> $target Target row.
	 * @param array<string,mixed> $cond   Condition bundle.
	 * @return string
	 */
	private static function variant_label_from_conditions( array $target, array $cond ) {
		$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
		$parts   = array();
		if ( ! empty( $cond['location_labels'] ) ) {
			$parts = (array) $cond['location_labels'];
		} else {
			$loc = RWGA_Planner_Location_Resolver::display_label(
				array(
					'countries' => $include['countries'] ?? array(),
					'regions'   => $include['regions'] ?? array(),
					'labels'    => array(),
				)
			);
			if ( '' !== $loc ) {
				$parts[] = $loc;
			}
		}
		if ( ! empty( $include['devices'] ) ) {
			$parts[] = ucfirst( implode( ' + ', (array) $include['devices'] ) );
		}
		if ( ! empty( $include['audiences'] ) ) {
			$parts[] = ucwords( str_replace( '_', ' ', implode( ' + ', (array) $include['audiences'] ) ) );
		}
		$page = ucfirst( (string) ( $target['label'] ?? 'page' ) );
		return $page . ( $parts ? ' - ' . implode( ' ', $parts ) : '' );
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return bool
	 */
	private static function is_variant_list_grouping_ambiguous( $phrase, $entities ) {
		if ( ! preg_match( '/\bvariants?\b/i', $phrase ) ) {
			return false;
		}
		if ( RWGA_Planner_Action_Clause_Splitter::has_variant_pair_marker( $phrase ) ) {
			return false;
		}
		if ( preg_match( '/\b(?:one|two|three|four|five)\s+(?:variant|variants|shared)\b/i', $phrase ) ) {
			return false;
		}
		$countries = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
			? RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities )
			: array();
		if ( count( $countries ) < 3
			&& preg_match_all( '/\b(france|spain|italy|germany|portugal|russia|ireland|poland|netherlands|belgium|sweden|norway|denmark|finland|austria|switzerland|greece|turkey|japan|china|india|brazil|mexico|canada|australia|uae|america|usa)\b/i', $phrase, $matches )
			&& count( $matches[0] ) >= 3 ) {
			return true;
		}
		return count( $countries ) >= 3;
	}

	/**
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @param array<string,mixed>|null       $learned Learned pattern.
	 * @return float
	 */
	private static function plan_confidence( array $actions, $learned ) {
		if ( empty( $actions ) ) {
			return 0.0;
		}
		$sum = 0.0;
		foreach ( $actions as $action ) {
			$sum += (float) ( $action['confidence'] ?? 0.5 );
		}
		$avg = $sum / count( $actions );
		if ( is_array( $learned ) ) {
			$avg += (float) ( $learned['confidenceBoost'] ?? 0 );
		}
		return min( 0.98, round( $avg, 2 ) );
	}

	/**
	 * @param string $source_text Source text.
	 * @return array<string,mixed>
	 */
	private static function empty_plan( $source_text ) {
		return array(
			'id'          => self::new_id(),
			'intent'      => RWGA_Geo_Action_Types::PLAN_INTENT,
			'status'      => RWGA_Geo_Action_Types::STATUS_DRAFT,
			'sourceText'  => $source_text,
			'confidence'  => 0,
			'actions'     => array(),
			'clarification' => null,
			'warnings'    => array(),
			'debug'       => array(
				'clauses'   => array(),
				'entities'  => array(),
				'decisions' => array(),
			),
		);
	}

	/**
	 * @return string
	 */
	public static function new_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return 'geo_' . bin2hex( random_bytes( 8 ) );
	}

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    Context.
	 * @param array<int,array>    $entities   Entities.
	 * @return array<string,mixed>|null Legacy interpreter result or null when planner cannot match.
	 */
	public static function interpret_as_legacy( $raw_phrase, array $context = array(), array $entities = array() ) {
		$plan = self::interpret( $raw_phrase, $context, $entities );
		if ( empty( $plan['actions'] ) && empty( $plan['clarification'] ) ) {
			return null;
		}
		return RWGA_Planner_Legacy_Adapter::to_interpreter_result( $plan, $context, $entities );
	}
}
