<?php
/**
 * Local phrase → intent/action interpreter (no external AI).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Local_Intent_Interpreter {

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    From RWGA_Context_Resolver.
	 * @return array<string,mixed>
	 */
	public static function interpret( $raw_phrase, array $context = array() ) {
		$phrase = self::normalise( $raw_phrase );
		$bundle = class_exists( 'RWGA_Intelligence_Sync_Service', false )
			? RWGA_Intelligence_Sync_Service::ensure_bundle()
			: null;

		if ( ! is_array( $bundle ) || empty( $bundle['phrase_patterns'] ) ) {
			return self::empty_result( $phrase, __( 'Intelligence bundle not loaded.', 'reactwoo-geo-ai' ), array(
				'_debug_bundle' => array(
					'loaded' => false,
					'hint'   => __( 'Ensure ReactWoo Geo AI is active and the intelligence bundle file is present.', 'reactwoo-geo-ai' ),
				),
			) );
		}

		$patterns      = is_array( $bundle['phrase_patterns'] ) ? $bundle['phrase_patterns'] : array();
		$flat_entities = is_array( $bundle['entities'] ) ? $bundle['entities'] : array();
		$entities      = self::index_entities( $flat_entities );
		$actions       = self::index_actions( is_array( $bundle['actions'] ) ? $bundle['actions'] : array() );
		$intents       = self::index_intents( is_array( $bundle['intents'] ) ? $bundle['intents'] : array() );
		$editor_opts   = self::editor_options( $context );

		if ( class_exists( 'RWGA_Variant_Plan_Interpreter', false ) ) {
			$plan = RWGA_Variant_Plan_Interpreter::parse( $phrase, $flat_entities, $context );
			if ( ! empty( $plan['matched'] ) ) {
				return self::build_variant_plan_result( $plan, $context, $phrase );
			}
		}

		if ( class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
			$multi = RWGA_Multi_Variant_Interpreter::parse( $phrase, $flat_entities, $context );
			if ( ! empty( $multi['matched'] ) ) {
				return self::build_multi_variant_result( $multi, $context, $phrase );
			}
		}

		if ( class_exists( 'RWGA_Country_Rule_Interpreter', false ) ) {
			$country_rule = RWGA_Country_Rule_Interpreter::parse( $phrase, $flat_entities );
			if ( ! empty( $country_rule['matched'] ) ) {
				return self::build_country_rule_result( $country_rule, $context, $phrase );
			}
		}

		$compound = class_exists( 'RWGA_Compound_Condition_Interpreter', false )
			&& ( ! class_exists( 'RWGA_Variant_Group_Extractor', false ) || ! RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase ) )
			? RWGA_Compound_Condition_Interpreter::parse( $phrase, $flat_entities, $editor_opts )
			: array( 'compound' => false );

		$best       = null;
		$best_score = 0.0;

		foreach ( $patterns as $pattern_row ) {
			if ( ! is_array( $pattern_row ) || ( $pattern_row['status'] ?? 'active' ) !== 'active' ) {
				continue;
			}
			$match = self::match_pattern( $phrase, $pattern_row, $entities );
			if ( ! $match ) {
				continue;
			}
			$bootstrap = (float) ( $pattern_row['confidence_weight'] ?? 0.5 );
			$score     = $match['confidence'] * $bootstrap;
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = array_merge( $match, array( 'pattern' => $pattern_row ) );
			}
		}

		if ( ! $best ) {
			if ( ! empty( $compound['compound'] ) && ! empty( $compound['conditions'] ) ) {
				return self::build_compound_result( $compound, $context, $phrase );
			}
			return self::empty_result( $phrase, __( 'No matching command pattern found.', 'reactwoo-geo-ai' ) );
		}

		$pattern_row = $best['pattern'];
		$intent_key  = (string) ( $pattern_row['intent_key'] ?? '' );
		$action_key  = (string) ( $pattern_row['action_key'] ?? '' );
		$action      = isset( $actions[ $action_key ] ) ? $actions[ $action_key ] : null;
		$intent      = isset( $intents[ $intent_key ] ) ? $intents[ $intent_key ] : null;

		$params = self::build_params( $best['extracted'], $pattern_row, array_merge( $context, array( 'normalised_phrase' => $phrase ) ) );
		$params = self::apply_context_diagnostics( $params, $intent_key, $context, $phrase );

		if ( ! empty( $params['_resolved_action'] ) ) {
			$action_key = (string) $params['_resolved_action'];
			$action     = isset( $actions[ $action_key ] ) ? $actions[ $action_key ] : $action;
			unset( $params['_resolved_action'] );
		}

		$resolved_target = RWGA_Context_Resolver::resolve_reference( 'this', $context );
		$missing         = array();
		if ( $action && ! empty( $action['required_params'] ) ) {
			foreach ( (array) $action['required_params'] as $req ) {
				if ( ! isset( $params[ $req ] ) || '' === $params[ $req ] || ( is_array( $params[ $req ] ) && empty( $params[ $req ] ) ) ) {
					$missing[] = (string) $req;
				}
			}
		}
		if ( ( $intent['requires_context'] ?? false ) && ! $resolved_target ) {
			$missing[] = 'target_context';
		}

		$confidence = min( 1.0, $best_score );
		if ( $intent && $confidence < (float) ( $intent['min_confidence'] ?? 0 ) ) {
			return self::empty_result(
				$phrase,
				__( 'Confidence below minimum threshold for this intent.', 'reactwoo-geo-ai' ),
				array(
					'intent'     => $intent_key,
					'confidence' => $confidence,
				)
			);
		}

		$requires_confirmation = ! empty( $action['requires_confirmation'] );
		$summary               = self::build_summary( $intent_key, $action_key, $params, $resolved_target );

		$result = array(
			'intent'                => $intent_key,
			'matched_action'        => $action_key,
			'confidence'            => round( $confidence, 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved_target,
			'params'                => $params,
			'missing_information'   => array_values( array_unique( $missing ) ),
			'requires_confirmation' => $requires_confirmation,
			'summary'               => $summary,
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
		);

		return self::merge_compound_into_result( $result, $compound, $phrase );
	}

	/**
	 * @param array<string,mixed> $multi   Multi-variant parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_multi_variant_result( array $multi, array $context, $phrase ) {
		$page_ref = $multi['page_ref'] ?? null;
		$page_id  = is_array( $page_ref ) && ! empty( $page_ref['page_id'] ) ? (int) $page_ref['page_id'] : 0;
		if ( $page_id <= 0 && ! empty( $context['page_id'] ) ) {
			$page_id = (int) $context['page_id'];
		}
		$resolved = null;
		if ( $page_id > 0 ) {
			$resolved = array(
				'type' => 'page',
				'id'   => $page_id,
			);
		}

		return array(
			'intent'                => (string) ( $multi['intent'] ?? 'create_geo_variants' ),
			'matched_action'        => (string) ( $multi['matched_action'] ?? 'geocore_create_variants_with_country_rules' ),
			'confidence'            => round( (float) ( $multi['confidence'] ?? 0.85 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved,
			'params'                => isset( $multi['params'] ) && is_array( $multi['params'] ) ? $multi['params'] : array(),
			'steps'                 => isset( $multi['steps'] ) && is_array( $multi['steps'] ) ? $multi['steps'] : array(),
			'variant_groups'        => isset( $multi['variant_groups'] ) && is_array( $multi['variant_groups'] ) ? $multi['variant_groups'] : array(),
			'variant_count'         => (int) ( $multi['variant_count'] ?? count( $multi['params']['variants'] ?? array() ) ),
			'missing_information'   => isset( $multi['missing_information'] ) && is_array( $multi['missing_information'] )
				? $multi['missing_information']
				: ( $page_id > 0 ? array() : array( 'page_ref' ) ),
			'suggested_options'     => isset( $multi['suggested_options'] ) && is_array( $multi['suggested_options'] ) ? $multi['suggested_options'] : array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $multi['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'_debug_entities'       => array(
				'matched_terms'  => $multi['matched_terms'] ?? array(),
				'variant_groups' => $multi['variant_groups'] ?? array(),
			),
		);
	}

	/**
	 * @param array<string,mixed> $plan    Variant plan parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_variant_plan_result( array $plan, array $context, $phrase ) {
		$page_ref = $plan['page_ref'] ?? null;
		$page_id  = is_array( $page_ref ) && ! empty( $page_ref['page_id'] ) ? (int) $page_ref['page_id'] : 0;
		if ( $page_id <= 0 && ! empty( $context['page_id'] ) ) {
			$page_id = (int) $context['page_id'];
		}
		$resolved = null;
		if ( $page_id > 0 ) {
			$resolved = array(
				'type' => 'page',
				'id'   => $page_id,
			);
		}

		return array(
			'intent'                => (string) ( $plan['intent'] ?? 'create_geo_variant_plan' ),
			'matched_action'        => (string) ( $plan['matched_action'] ?? 'geocore_create_variant_plan_with_country_rules' ),
			'confidence'            => round( (float) ( $plan['confidence'] ?? 0.88 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved,
			'params'                => isset( $plan['params'] ) && is_array( $plan['params'] ) ? $plan['params'] : array(),
			'steps'                 => isset( $plan['steps'] ) && is_array( $plan['steps'] ) ? $plan['steps'] : array(),
			'source_targeting'      => $plan['source_targeting'] ?? ( $plan['params']['source_targeting'] ?? null ),
			'variant_groups'        => isset( $plan['variant_groups'] ) && is_array( $plan['variant_groups'] ) ? $plan['variant_groups'] : array(),
			'duplicate_count'       => (int) ( $plan['duplicate_count'] ?? 0 ),
			'missing_information'   => isset( $plan['missing_information'] ) && is_array( $plan['missing_information'] )
				? $plan['missing_information']
				: ( $page_id > 0 ? array() : array( 'page_ref' ) ),
			'suggested_options'     => isset( $plan['suggested_options'] ) && is_array( $plan['suggested_options'] ) ? $plan['suggested_options'] : array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $plan['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'_debug_entities'       => array(
				'matched_terms'    => $plan['matched_terms'] ?? array(),
				'variant_groups'     => $plan['variant_groups'] ?? array(),
				'source_targeting'   => $plan['source_targeting'] ?? null,
				'duplicate_count'    => $plan['duplicate_count'] ?? 0,
			),
		);
	}

	/**
	 * @param array<string,mixed> $rule    Country rule parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_country_rule_result( array $rule, array $context, $phrase ) {
		return array(
			'intent'                => (string) ( $rule['intent'] ?? 'country_include' ),
			'matched_action'        => (string) ( $rule['matched_action'] ?? 'geocore_create_country_rule' ),
			'confidence'            => round( (float) ( $rule['confidence'] ?? 0.8 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => RWGA_Context_Resolver::resolve_reference( 'this', $context ),
			'params'                => isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array(),
			'missing_information'   => array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $rule['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
		);
	}

	/**
	 * Editor context for compound condition gating (Core vs Pro types).
	 *
	 * @param array<string,mixed> $context Resolved context.
	 * @return array<string,mixed>
	 */
	private static function editor_options( array $context ) {
		$pro = (bool) apply_filters( 'rwgc_pro_enabled', false );
		if ( isset( $context['pro'] ) ) {
			$pro = (bool) $context['pro'];
		}
		return array(
			'pro' => $pro,
		);
	}

	/**
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @param array<string,mixed> $context  Resolved context.
	 * @param string              $phrase   Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_compound_result( array $compound, array $context, $phrase ) {
		$resolved_target = RWGA_Context_Resolver::resolve_reference( 'this', $context );
		$missing         = array();
		if ( ! $resolved_target && preg_match( '/\b(this|current|page)\b/i', $phrase ) ) {
			$missing[] = 'target_context';
		}

		$variant_hint = (bool) preg_match( '/\b(version|variant|different page|different version|local page)\b/i', $phrase );

		return array(
			'intent'                => $variant_hint ? 'create_variant' : (string) ( $compound['intent'] ?? 'compound_targeting' ),
			'matched_action'        => (string) ( $compound['matched_action'] ?? 'geocore_create_portable_rule' ),
			'confidence'            => round( (float) ( $compound['confidence'] ?? 0.75 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved_target,
			'params'                => self::params_from_compound( $compound ),
			'missing_information'   => $missing,
			'requires_confirmation' => true,
			'summary'               => (string) ( $compound['summary'] ?? '' ),
			'warnings'              => isset( $compound['warnings'] ) && is_array( $compound['warnings'] ) ? $compound['warnings'] : array(),
			'normalised_phrase'     => $phrase,
			'compound'              => true,
			'condition_match'       => (string) ( $compound['condition_match'] ?? 'all' ),
			'conditions'            => isset( $compound['conditions'] ) && is_array( $compound['conditions'] ) ? $compound['conditions'] : array(),
			'portable_rule_set'     => isset( $compound['portable_rule_set'] ) && is_array( $compound['portable_rule_set'] ) ? $compound['portable_rule_set'] : null,
		);
	}

	/**
	 * @param array<string,mixed> $result   Pattern match result.
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @param string              $phrase   Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function merge_compound_into_result( array $result, array $compound, $phrase ) {
		if ( empty( $compound['compound'] ) || empty( $compound['conditions'] ) ) {
			return $result;
		}

		$result['compound']          = true;
		$result['condition_match']   = (string) ( $compound['condition_match'] ?? 'all' );
		$result['conditions']        = $compound['conditions'];
		$result['portable_rule_set'] = $compound['portable_rule_set'] ?? null;
		$result['params']            = array_merge( self::params_from_compound( $compound ), is_array( $result['params'] ) ? $result['params'] : array() );
		$result['summary']           = (string) ( $compound['summary'] ?? $result['summary'] );
		if ( ! empty( $compound['warnings'] ) && is_array( $compound['warnings'] ) ) {
			$result['warnings'] = array_values(
				array_unique(
					array_merge(
						is_array( $result['warnings'] ) ? $result['warnings'] : array(),
						$compound['warnings']
					)
				)
			);
		}
		if ( preg_match( '/\b(version|variant|different page|different version|local page)\b/i', $phrase ) ) {
			$result['intent'] = 'create_variant';
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @return array<string,mixed>
	 */
	private static function params_from_compound( array $compound ) {
		$params = array();
		$conds  = isset( $compound['conditions'] ) && is_array( $compound['conditions'] ) ? $compound['conditions'] : array();
		foreach ( $conds as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			if ( 'country' === $type && ! empty( $cond['value'] ) ) {
				$params['countries'] = is_array( $cond['value'] ) ? $cond['value'] : array( $cond['value'] );
			}
			if ( 'device_type' === $type && ! empty( $cond['value'] ) ) {
				$params['device'] = is_array( $cond['value'] ) ? (string) reset( $cond['value'] ) : (string) $cond['value'];
			}
		}
		return $params;
	}

	/**
	 * @param string $phrase Raw phrase.
	 * @return string
	 */
	public static function normalise( $phrase ) {
		$text = strtolower( trim( (string) $phrase ) );
		$text = str_replace( array( '’', '`' ), "'", $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @param string $message Error message.
	 * @param array  $extra   Extra fields.
	 * @return array<string,mixed>
	 */
	private static function empty_result( $phrase, $message, array $extra = array() ) {
		return array_merge(
			array(
				'intent'                => '',
				'matched_action'        => '',
				'confidence'            => 0,
				'target_reference'      => '',
				'resolved_target'       => null,
				'params'                => array(),
				'missing_information'   => array(),
				'requires_confirmation' => false,
				'summary'               => $message,
				'warnings'              => array(),
				'normalised_phrase'     => $phrase,
			),
			$extra
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $entities Entity rows.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private static function index_entities( array $entities ) {
		$out = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['entity_type'] ?? '' );
			if ( '' === $type ) {
				continue;
			}
			if ( ! isset( $out[ $type ] ) ) {
				$out[ $type ] = array();
			}
			$out[ $type ][] = $row;
		}
		return $out;
	}

	/**
	 * @param array<string,array<int,array>> $indexed Indexed entities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function flatten_entities( array $indexed ) {
		$out = array();
		foreach ( $indexed as $rows ) {
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( is_array( $row ) ) {
						$out[] = $row;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $actions Action rows.
	 * @return array<string,array<string,mixed>>
	 */
	private static function index_actions( array $actions ) {
		$out = array();
		foreach ( $actions as $row ) {
			if ( is_array( $row ) && ! empty( $row['action_key'] ) ) {
				$out[ (string) $row['action_key'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $intents Intent rows.
	 * @return array<string,array<string,mixed>>
	 */
	private static function index_intents( array $intents ) {
		$out = array();
		foreach ( $intents as $row ) {
			if ( is_array( $row ) && ! empty( $row['intent_key'] ) ) {
				$out[ (string) $row['intent_key'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<string,mixed> $pattern  Pattern row.
	 * @param array<string,array> $entities Indexed entities.
	 * @return array{confidence:float,extracted:array<string,mixed>}|null
	 */
	private static function match_pattern( $phrase, array $pattern, array $entities ) {
		$type    = (string) ( $pattern['pattern_type'] ?? 'contains' );
		$pattern_text = (string) ( $pattern['pattern'] ?? '' );
		$extracted = array();

		switch ( $type ) {
			case 'exact':
				if ( $phrase === self::normalise( $pattern_text ) ) {
					return array( 'confidence' => 1.0, 'extracted' => $extracted );
				}
				return null;

			case 'regex':
				$regex = '/' . str_replace( '/', '\/', $pattern_text ) . '/i';
				if ( @preg_match( $regex, $phrase, $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					return array( 'confidence' => 0.9, 'extracted' => array( 'match' => $m ) );
				}
				return null;

			case 'template':
				return self::match_template( $phrase, $pattern_text, $entities );

			case 'semantic_alias':
			case 'contains':
			default:
				if ( false !== strpos( $phrase, self::normalise( $pattern_text ) ) ) {
					$extracted = self::extract_entities_from_phrase( $phrase, $entities );
					return array( 'confidence' => 0.85, 'extracted' => $extracted );
				}
				return null;
		}
	}

	/**
	 * @param string              $phrase        Normalised phrase.
	 * @param string              $template      Template with {placeholders}.
	 * @param array<string,array> $entities      Indexed entities.
	 * @return array{confidence:float,extracted:array<string,mixed>}|null
	 */
	private static function match_template( $phrase, $template, array $entities ) {
		$parts = preg_split( '/\{([a-z_]+)\}/', $template, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) || count( $parts ) < 1 ) {
			return null;
		}

		$regex_parts = array();
		$placeholders = array();
		for ( $i = 0; $i < count( $parts ); $i++ ) {
			if ( $i % 2 === 0 ) {
				$regex_parts[] = preg_quote( self::normalise( $parts[ $i ] ), '/' );
			} else {
				$placeholders[] = $parts[ $i ];
				$regex_parts[]  = '(.+?)';
			}
		}
		$regex = '/^' . implode( '', $regex_parts ) . '$/i';
		if ( ! preg_match( $regex, $phrase, $m ) ) {
			return null;
		}

		$extracted = array();
		foreach ( $placeholders as $idx => $placeholder ) {
			$raw_value = isset( $m[ $idx + 1 ] ) ? trim( (string) $m[ $idx + 1 ] ) : '';
			if ( 'country_list' === $placeholder && class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
				$list = RWGA_Multi_Variant_Interpreter::parse_country_list( $raw_value, self::flatten_entities( $entities ) );
				$extracted[ $placeholder ] = $list;
				continue;
			}
			if ( 'page' === $placeholder ) {
				$extracted[ $placeholder ] = self::normalise( $raw_value );
				continue;
			}
			$entity_type = self::placeholder_entity_type( $placeholder );
			$resolved    = self::resolve_entity_value( $raw_value, $entity_type, $entities );
			if ( $resolved ) {
				$extracted[ $placeholder ] = $resolved;
			} else {
				$extracted[ $placeholder ] = $raw_value;
			}
		}

		return array( 'confidence' => 0.95, 'extracted' => $extracted );
	}

	/**
	 * @param string $placeholder Placeholder name.
	 * @return string
	 */
	private static function placeholder_entity_type( $placeholder ) {
		$map = array(
			'country'            => 'country',
			'country_list'       => 'country',
			'page'               => 'page',
			'device'             => 'device',
			'weather_condition'  => 'weather_condition',
		);
		return isset( $map[ $placeholder ] ) ? $map[ $placeholder ] : $placeholder;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<string,array> $entities Indexed entities.
	 * @return array<string,mixed>
	 */
	private static function extract_entities_from_phrase( $phrase, array $entities ) {
		$extracted = array();
		foreach ( array( 'weather_condition', 'device', 'country' ) as $type ) {
			if ( empty( $entities[ $type ] ) ) {
				continue;
			}
			$val = self::find_entity_in_phrase( $phrase, $entities[ $type ] );
			if ( $val ) {
				$extracted[ $type ] = $val;
			}
		}
		return $extracted;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $rows     Entity rows.
	 * @return string|null
	 */
	private static function find_entity_in_phrase( $phrase, array $rows ) {
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aliases = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = (string) ( $row['entity_key'] ?? '' );
			usort(
				$aliases,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			foreach ( $aliases as $alias ) {
				$alias = self::normalise( $alias );
				if ( '' !== $alias && false !== strpos( $phrase, $alias ) ) {
					return (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
				}
			}
		}
		return null;
	}

	/**
	 * @param string              $raw_value   Raw captured value.
	 * @param string              $entity_type Entity type.
	 * @param array<string,array> $entities    Indexed entities.
	 * @return string|null
	 */
	private static function resolve_entity_value( $raw_value, $entity_type, array $entities ) {
		if ( empty( $entities[ $entity_type ] ) ) {
			return null;
		}
		return self::find_entity_in_phrase( self::normalise( $raw_value ), $entities[ $entity_type ] )
			?: self::find_entity_in_phrase( self::normalise( $raw_value ), $entities[ $entity_type ] );
	}

	/**
	 * @param array<string,mixed> $extracted   Extracted placeholders.
	 * @param array<string,mixed> $pattern_row Pattern row.
	 * @param array<string,mixed> $context     Admin context.
	 * @return array<string,mixed>
	 */
	private static function build_params( array $extracted, array $pattern_row, array $context ) {
		$params = array();
		$schema = isset( $pattern_row['param_schema'] ) && is_array( $pattern_row['param_schema'] )
			? $pattern_row['param_schema']
			: array();

		foreach ( $schema as $key => $value ) {
			if ( is_string( $value ) && preg_match( '/^\{([a-z_]+)\}$/', $value, $m ) ) {
				$placeholder = $m[1];
				if ( isset( $extracted[ $placeholder ] ) ) {
					$params[ $key ] = 'countries' === $key ? array( $extracted[ $placeholder ] ) : $extracted[ $placeholder ];
				}
			} else {
				$params[ $key ] = $value;
			}
		}

		// Merge extracted entities not in param_schema.
		foreach ( $extracted as $key => $value ) {
			if ( 'country' === $key && empty( $params['countries'] ) ) {
				$params['countries'] = array( $value );
			} elseif ( 'country_list' === $key && empty( $params['variants'] ) ) {
				$params['variants'] = array(
					array(
						'countries' => is_array( $value ) ? $value : array( $value ),
						'mode'      => 'include_only',
					),
				);
			} elseif ( 'page' === $key && empty( $params['page_ref'] ) ) {
				$params['page_ref'] = is_string( $value ) ? $value : '';
			} elseif ( 'weather_condition' === $key && empty( $params['weather_condition'] ) ) {
				$params['weather_condition'] = $value;
			} elseif ( 'device' === $key && empty( $params['device'] ) ) {
				$params['device'] = $value;
				if ( empty( $params['mode'] ) ) {
					$params['mode'] = false !== strpos( (string) ( $context['normalised_phrase'] ?? '' ), 'hide' ) ? 'exclude' : 'include_only';
				}
			}
		}

		if ( ! empty( $context['target_type'] ) && empty( $params['target_type'] ) ) {
			$params['target_type'] = (string) $context['target_type'];
		}
		if ( ! empty( $context['target_id'] ) && empty( $params['target_id'] ) ) {
			$params['target_id'] = (int) $context['target_id'];
		}

		return $params;
	}

	/**
	 * Map diagnostics intents to context-specific actions.
	 *
	 * @param array<string,mixed> $params     Params.
	 * @param string              $intent_key Intent key.
	 * @param array<string,mixed> $context    Context.
	 * @param string              $phrase     Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_context_diagnostics( array $params, $intent_key, array $context, $phrase ) {
		if ( 'diagnose_visibility' !== $intent_key && 'rule_conflict_explain' !== $intent_key ) {
			return $params;
		}
		if ( false !== strpos( $phrase, 'popup' ) || ! empty( $context['popup_id'] ) ) {
			$params['_resolved_action'] = 'geocore_run_popup_diagnostics';
		} elseif ( ! empty( $context['rule_id'] ) || false !== strpos( $phrase, 'rule' ) ) {
			$params['_resolved_action'] = 'geocore_run_rule_diagnostics';
		} elseif ( false !== strpos( $phrase, 'conflict' ) || false !== strpos( $phrase, 'explain' ) ) {
			$params['_resolved_action'] = 'geocore_explain_rule_conflicts';
		} else {
			$params['_resolved_action'] = 'geocore_run_page_audit';
		}
		return $params;
	}

	/**
	 * @param string                   $intent_key Intent.
	 * @param string                   $action_key Action.
	 * @param array<string,mixed>      $params     Params.
	 * @param array{type:string,id:int}|null $target Target.
	 * @return string
	 */
	private static function build_summary( $intent_key, $action_key, array $params, $target ) {
		$target_label = $target ? sprintf( '%s #%d', $target['type'], $target['id'] ) : __( 'current context', 'reactwoo-geo-ai' );

		if ( 'country_include' === $intent_key && ! empty( $params['countries'] ) ) {
			$countries = is_array( $params['countries'] ) ? implode( ', ', $params['countries'] ) : (string) $params['countries'];
			return sprintf(
				/* translators: 1: country codes, 2: target label */
				__( 'Create a country-only targeting rule for %2$s (%1$s).', 'reactwoo-geo-ai' ),
				$countries,
				$target_label
			);
		}
		if ( 'country_exclude' === $intent_key && ! empty( $params['countries'] ) ) {
			$countries = is_array( $params['countries'] ) ? implode( ', ', $params['countries'] ) : (string) $params['countries'];
			return sprintf(
				/* translators: 1: country codes, 2: target label */
				__( 'Exclude countries from targeting for %2$s (%1$s).', 'reactwoo-geo-ai' ),
				$countries,
				$target_label
			);
		}

		return sprintf(
			/* translators: 1: action key, 2: target label */
			__( 'Proposed action: %1$s for %2$s.', 'reactwoo-geo-ai' ),
			str_replace( 'geocore_', '', $action_key ),
			$target_label
		);
	}
}
