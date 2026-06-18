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
			? RWGA_Intelligence_Sync_Service::get_local_bundle()
			: null;

		if ( ! is_array( $bundle ) || empty( $bundle['phrase_patterns'] ) ) {
			return self::empty_result( $phrase, __( 'Intelligence bundle not loaded.', 'reactwoo-geo-ai' ) );
		}

		$patterns = is_array( $bundle['phrase_patterns'] ) ? $bundle['phrase_patterns'] : array();
		$entities = self::index_entities( is_array( $bundle['entities'] ) ? $bundle['entities'] : array() );
		$actions  = self::index_actions( is_array( $bundle['actions'] ) ? $bundle['actions'] : array() );
		$intents  = self::index_intents( is_array( $bundle['intents'] ) ? $bundle['intents'] : array() );

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

		return array(
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
