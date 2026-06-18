<?php
/**
 * Targeting assistant: preview, interpret, execute orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Assistant_Service {

	/**
	 * @param string              $message Raw user message.
	 * @param array<string,mixed> $context Admin context.
	 * @return array<string,mixed>
	 */
	public static function preview( $message, array $context = array() ) {
		$phrase  = RWGA_Local_Intent_Interpreter::normalise( $message );
		$bundle  = self::bundle();
		$debug   = self::bundle_debug( $bundle );
		$flat    = is_array( $bundle ) && is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		$ctx     = self::resolve_context( $context );

		$detected = self::detect_terms( $phrase, $flat, $ctx );
		$multi    = class_exists( 'RWGA_Variant_Plan_Interpreter', false )
			? RWGA_Variant_Plan_Interpreter::parse( $phrase, $flat, $ctx )
			: array( 'matched' => false );
		if ( empty( $multi['matched'] ) ) {
			$multi = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
				? RWGA_Multi_Variant_Interpreter::parse( $phrase, $flat, $ctx )
				: array( 'matched' => false );
		}

		$summary    = '';
		$confidence = 0.0;
		$missing    = array();

		if ( ! empty( $multi['matched'] ) ) {
			$summary    = (string) ( $multi['summary'] ?? '' );
			$confidence = (float) ( $multi['confidence'] ?? 0.8 );
		} elseif ( ! empty( $detected['intents'] ) ) {
			$summary    = (string) ( $detected['intents'][0]['label'] ?? '' );
			$confidence = (float) ( $detected['intents'][0]['confidence'] ?? 0.5 );
		}

		if ( empty( $debug['bundle_loaded'] ) ) {
			$missing[] = 'intelligence_bundle';
		}

		return array(
			'success'             => true,
			'status'              => 'preview',
			'request_id'          => (int) ( $context['preview_request_id'] ?? 0 ),
			'detected'            => $detected,
			'summary'             => $summary,
			'missing_information' => $missing,
			'confidence'          => round( $confidence, 2 ),
			'debug'               => $debug,
		);
	}

	/**
	 * @param string              $message Raw user message.
	 * @param array<string,mixed> $context Admin context.
	 * @param bool                $include_debug Include debug block.
	 * @return array<string,mixed>
	 */
	public static function interpret( $message, array $context = array(), $include_debug = false ) {
		$ctx    = self::resolve_context( $context );
		$raw    = RWGA_Local_Intent_Interpreter::interpret( $message, $ctx );
		$bundle = self::bundle();
		$debug  = self::build_interpret_debug( $message, $raw, $bundle );

		if ( empty( $raw['matched_action'] ) && empty( $raw['compound'] ) && empty( $raw['steps'] ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'message' => (string) ( $raw['summary'] ?? __( 'Could not interpret that command.', 'reactwoo-geo-ai' ) ),
				'debug'   => $include_debug ? $debug : null,
			);
		}

		$proposal = self::format_proposal( $raw, $message );
		$id       = RWGA_Proposal_Store::save( $proposal );

		$response = array(
			'success'     => true,
			'status'      => 'proposal',
			'message'     => self::format_message_with_badge( (string) ( $proposal['summary'] ?? '' ), $raw ),
			'proposal_id' => $id,
			'proposal'    => $proposal,
			'actions'     => self::action_buttons(),
			'badge'       => self::interpretation_badge( $raw ),
		);
		if ( $include_debug ) {
			$response['debug'] = $debug;
		}
		return $response;
	}

	/**
	 * @param string $proposal_id Proposal ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function execute( $proposal_id ) {
		$proposal = RWGA_Proposal_Store::get( $proposal_id );
		if ( ! $proposal ) {
			return new WP_Error( 'rwga_proposal_expired', __( 'Proposal expired or not found. Send your message again.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		$action = (string) ( $proposal['matched_action'] ?? '' );
		$steps  = isset( $proposal['steps'] ) && is_array( $proposal['steps'] ) ? $proposal['steps'] : array();

		/**
		 * Execute a confirmed assistant proposal (no-op until handlers register).
		 *
		 * @param array<string,mixed> $proposal Stored proposal.
		 * @param string              $action   Action key.
		 */
		$result = apply_filters( 'rwga_assistant_execute_proposal', null, $proposal, $action );

		if ( null === $result ) {
			$result = array(
				'executed'        => false,
				'message'         => __( 'Proposal confirmed. Complete setup in the guided workflow.', 'reactwoo-geo-ai' ),
				'redirect_steps'  => self::build_redirect_steps( $proposal ),
				'matched_action'  => $action,
				'steps'           => $steps,
			);
		}

		RWGA_Proposal_Store::delete( $proposal_id );

		if ( class_exists( 'RWGA_Learning_Event_Service', false ) ) {
			do_action(
				'rwga_intelligence_interpretation_feedback',
				$proposal,
				array(
					'outcome'          => 'executed',
					'approved_by_user' => true,
					'raw_phrase'       => (string) ( $proposal['original_message'] ?? '' ),
				)
			);
		}

		return array(
			'success' => true,
			'status'  => 'executed',
			'result'  => $result,
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function bundle() {
		if ( ! class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			return null;
		}
		return RWGA_Intelligence_Sync_Service::ensure_bundle();
	}

	/**
	 * @param array<string,mixed>|null $bundle Bundle.
	 * @return array<string,mixed>
	 */
	private static function bundle_debug( $bundle ) {
		$status = class_exists( 'RWGA_Intelligence_Sync_Service', false )
			? RWGA_Intelligence_Sync_Service::get_status()
			: array();
		return array(
			'bundle_loaded'    => is_array( $bundle ) && ! empty( $bundle['phrase_patterns'] ),
			'bundle_source'    => (string) ( $status['source'] ?? ( is_array( $bundle ) ? (string) ( $bundle['_source'] ?? 'unknown' ) : 'none' ) ),
			'pattern_count'    => is_array( $bundle ) ? count( (array) ( $bundle['phrase_patterns'] ?? array() ) ) : 0,
			'entity_count'     => is_array( $bundle ) ? count( (array) ( $bundle['entities'] ?? array() ) ) : 0,
			'bundle_version'   => (string) ( $status['version'] ?? '' ),
			'last_sync'        => (int) ( $status['last_sync'] ?? 0 ),
			'last_error'       => (string) ( $status['last_error'] ?? '' ),
		);
	}

	/**
	 * @param string              $message Raw message.
	 * @param array<string,mixed> $raw     Interpreter result.
	 * @param array<string,mixed>|null $bundle Bundle.
	 * @return array<string,mixed>
	 */
	private static function build_interpret_debug( $message, array $raw, $bundle ) {
		$base = self::bundle_debug( $bundle );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $message );
		$base['normalised_input']    = $phrase;
		$base['detected_intent']     = (string) ( $raw['intent'] ?? '' );
		$base['matched_patterns']    = isset( $raw['_debug_patterns'] ) ? $raw['_debug_patterns'] : array();
		$base['detected_entities']   = isset( $raw['_debug_entities'] ) ? $raw['_debug_entities'] : array();
		$base['proposed_action']     = (string) ( $raw['matched_action'] ?? '' );
		$base['confidence']          = (float) ( $raw['confidence'] ?? 0 );
		$base['missing_information'] = isset( $raw['missing_information'] ) ? $raw['missing_information'] : array();
		$base['variant_count']       = (int) ( $raw['variant_count'] ?? 0 );
		$base['duplicate_count']     = (int) ( $raw['duplicate_count'] ?? ( $raw['params']['duplicate_count'] ?? 0 ) );
		$base['source_targeting']    = $raw['source_targeting'] ?? ( $raw['params']['source_targeting'] ?? null );
		$base['variant_groups']      = isset( $raw['variant_groups'] ) && is_array( $raw['variant_groups'] )
			? $raw['variant_groups']
			: ( $raw['_debug_entities']['variant_groups'] ?? array() );
		$base['matched_terms']       = $raw['_debug_entities']['matched_terms'] ?? array();
		$parser_debug                = is_array( $raw['_debug_entities'] ?? null ) ? $raw['_debug_entities'] : array();
		foreach ( array( 'parser_used', 'variant_plan_terms_detected', 'source_page_ref', 'total_version_count', 'segments', 'fallback_pattern_match_used', 'warnings', 'countries_detected' ) as $key ) {
			if ( isset( $parser_debug[ $key ] ) ) {
				$base[ $key ] = $parser_debug[ $key ];
			}
		}
		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$page = RWGA_Page_Reference_Resolver::detect( $phrase );
			if ( $page ) {
				$base['detected_page'] = array(
					'label' => (string) ( $page['label'] ?? $page['value'] ?? '' ),
					'value' => (string) ( $page['value'] ?? '' ),
				);
			}
		}
		$base['proposal'] = $raw;
		$base['_interpretation_trace'] = isset( $raw['_interpretation_trace'] ) ? $raw['_interpretation_trace'] : array();
		$base['interpretation_source'] = (string) ( $raw['interpretation_source'] ?? '' );
		return $base;
	}

	/**
	 * @param array<string,mixed> $raw Interpreter output.
	 * @return string
	 */
	private static function interpretation_badge( array $raw ) {
		$source = (string) ( $raw['interpretation_source'] ?? '' );
		if ( 'interpretation_memory' === $source ) {
			return __( 'Learned interpretation', 'reactwoo-geo-ai' );
		}
		if ( 'ai_fallback' === $source ) {
			return __( 'AI-assisted interpretation', 'reactwoo-geo-ai' );
		}
		if ( in_array( $source, array( 'local_parser', 'pattern_bundle' ), true ) ) {
			return __( 'Local smart action', 'reactwoo-geo-ai' );
		}
		return '';
	}

	/**
	 * @param string              $summary Summary text.
	 * @param array<string,mixed> $raw     Raw interpreter output.
	 * @return string
	 */
	private static function format_message_with_badge( $summary, array $raw ) {
		$badge = self::interpretation_badge( $raw );
		if ( '' === $badge ) {
			return $summary;
		}
		return $summary . "\n\n" . $badge;
	}

	/**
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private static function resolve_context( array $context ) {
		if ( class_exists( 'RWGA_Context_Resolver', false ) ) {
			return RWGA_Context_Resolver::resolve( $context );
		}
		return $context;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	private static function detect_terms( $phrase, array $entities, array $context ) {
		$intents          = array();
		$entities_out     = array();
		$keywords         = array();
		$variant_groups   = array();
		$source_targeting = null;

		$plan = class_exists( 'RWGA_Variant_Plan_Interpreter', false )
			? RWGA_Variant_Plan_Interpreter::parse( $phrase, $entities, $context )
			: array( 'matched' => false );

		if ( ! empty( $plan['matched'] ) && ! empty( $plan['params']['source_targeting'] ) ) {
			$source    = $plan['params']['source_targeting'];
			$src_label = (string) ( $source['label'] ?? '' );
			if ( ! empty( $source['countries'] ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
				$src_label = (string) ( $source['targeting_label'] ?? RWGA_Variant_Group_Extractor::label_for_countries( (array) $source['countries'], $entities ) );
			}
			$source_targeting = array(
				'label' => sprintf(
					/* translators: %s: country targeting label */
					__( 'Original: %s', 'reactwoo-geocore' ),
					$src_label
				),
			);
			$total_versions = (int) ( $plan['params']['total_version_count'] ?? 0 );
			if ( $total_versions > 0 ) {
				$keywords[] = array(
					'text' => sprintf(
						/* translators: %d: version count */
						__( 'Create %d variations', 'reactwoo-geocore' ),
						$total_versions
					),
					'type' => 'version_signal',
				);
			} elseif ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?\w+\s+twice\b/i', $phrase ) ) {
				$keywords[] = array(
					'text' => __( 'Duplicate homepage twice', 'reactwoo-geocore' ),
					'type' => 'duplicate_signal',
				);
			}
			$intents[] = array(
				'key'        => 'create_geo_variant_plan',
				'label'      => __( 'Homepage targeting plan', 'reactwoo-geocore' ),
				'confidence' => (float) ( $plan['confidence'] ?? 0.88 ),
			);
			foreach ( (array) ( $plan['params']['variants'] ?? array() ) as $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				$ordinal = (int) ( $variant['ordinal'] ?? 0 );
				if ( $ordinal <= 0 ) {
					$ordinal = count( $variant_groups ) + ( $source ? 2 : 1 );
				}
				$variant_groups[] = array(
					'index' => $ordinal,
					'label' => sprintf(
						/* translators: 1: variant number, 2: targeting label */
						__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
						$ordinal,
						$label
					),
				);
			}
		} elseif ( ! empty( $plan['matched'] ) && ! empty( $plan['missing_information'] ) ) {
			$intents[] = array(
				'key'        => 'create_geo_variant_plan',
				'label'      => __( 'Variant plan (needs clarification)', 'reactwoo-geocore' ),
				'confidence' => (float) ( $plan['confidence'] ?? 0.65 ),
			);
		} else {
			$multi = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
				? RWGA_Multi_Variant_Interpreter::parse( $phrase, $entities, $context )
				: array( 'matched' => false );

			if ( ! empty( $multi['matched'] ) ) {
				self::append_multi_variant_detected( $phrase, $multi, $intents, $entities_out, $keywords, $variant_groups );
			} else {
				if ( preg_match( '/\b(create|show|hide|target|audit|diagnose|clean|apply|exclude)\b/i', $phrase, $m ) ) {
					$keywords[] = array( 'text' => $m[1], 'type' => 'action' );
				}

				$country_rule = class_exists( 'RWGA_Country_Rule_Interpreter', false )
					? RWGA_Country_Rule_Interpreter::parse( $phrase, $entities )
					: array( 'matched' => false );
				if ( ! empty( $country_rule['matched'] ) ) {
					$intents[] = array(
						'key'        => (string) ( $country_rule['intent'] ?? 'country_include' ),
						'label'      => (string) ( $country_rule['summary'] ?? __( 'Country rule', 'reactwoo-geocore' ) ),
						'confidence' => (float) ( $country_rule['confidence'] ?? 0.75 ),
					);
					foreach ( (array) ( $country_rule['params']['countries'] ?? array() ) as $code ) {
						$entities_out[] = array(
							'type'   => 'country',
							'label'  => $code,
							'value'  => $code,
							'source' => 'phrase',
						);
					}
				}
			}
		}

		$page = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;
		if ( $page ) {
			$entities_out[] = array(
				'type'   => 'page',
				'label'  => (string) ( $page['label'] ?? $page['value'] ),
				'value'  => (string) ( $page['value'] ?? '' ),
				'source' => 'phrase',
			);
		}

		if ( preg_match( '/\bonly\b/i', $phrase ) && empty( $variant_groups ) && empty( $source_targeting ) ) {
			$keywords[] = array( 'text' => 'only', 'type' => 'rule_mode' );
		}

		foreach ( array( 'mobile', 'desktop', 'tablet' ) as $device ) {
			if ( false !== strpos( $phrase, $device ) ) {
				$entities_out[] = array(
					'type'   => 'device',
					'label'  => ucfirst( $device ),
					'value'  => $device,
					'source' => 'phrase',
				);
			}
		}

		return array(
			'intents'          => $intents,
			'entities'         => $entities_out,
			'keywords'         => $keywords,
			'variant_groups'   => $variant_groups,
			'source_targeting' => $source_targeting,
		);
	}

	/**
	 * @param string              $phrase         Phrase.
	 * @param array<string,mixed> $multi          Multi-variant parse result.
	 * @param array<int,array>    $intents        Intent chips (by ref).
	 * @param array<int,array>    $entities_out   Entity chips (by ref).
	 * @param array<int,array>    $keywords       Keyword chips (by ref).
	 * @param array<int,array>    $variant_groups Variant group chips (by ref).
	 * @return void
	 */
	private static function append_multi_variant_detected( $phrase, array $multi, array &$intents, array &$entities_out, array &$keywords, array &$variant_groups ) {
		$variants = isset( $multi['params']['variants'] ) && is_array( $multi['params']['variants'] )
			? $multi['params']['variants']
			: array();
		$count    = (int) ( $multi['variant_count'] ?? count( $variants ) );
		if ( $count < 1 && ! empty( $multi['missing_information'] ) ) {
			$count = max( 1, count( (array) ( $multi['params']['countries'] ?? array() ) ) );
		}
		$intents[] = array(
			'key'        => 'create_geo_variants',
			'label'      => sprintf(
				/* translators: %d: variant count */
				__( 'Create %d variants', 'reactwoo-geocore' ),
				max( 1, $count )
			),
			'confidence' => (float) ( $multi['confidence'] ?? 0.8 ),
		);

		if ( ! empty( $multi['params']['source_page_ref'] ) ) {
			$entities_out[] = array(
				'type'   => 'page',
				'label'  => ucfirst( (string) $multi['params']['source_page_ref'] ),
				'value'  => (string) $multi['params']['source_page_ref'],
				'source' => 'phrase',
			);
		}

		foreach ( $variants as $idx => $variant ) {
			$label = (string) ( $variant['label'] ?? '' );
			if ( '' === $label && ! empty( $variant['countries'] ) ) {
				$label = implode( ' + ', (array) $variant['countries'] );
			}
			$variant_groups[] = array(
				'index' => $idx + 1,
				'label' => sprintf(
					/* translators: 1: variant number, 2: targeting label */
					__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
					$idx + 1,
					$label
				),
			);
		}

		if ( preg_match( '/\b(duplicate|twice|another|the other)\b/i', $phrase, $m ) ) {
			$keywords[] = array( 'text' => $m[1], 'type' => 'variant_signal' );
		}
	}

	/**
	 * @param array<string,mixed> $raw     Interpreter output.
	 * @param string              $message Original message.
	 * @return array<string,mixed>
	 */
	private static function format_proposal( array $raw, $message ) {
		$steps = isset( $raw['steps'] ) && is_array( $raw['steps'] ) ? $raw['steps'] : array();
		if ( empty( $steps ) && ! empty( $raw['params']['variants'] ) ) {
			foreach ( (array) $raw['params']['variants'] as $idx => $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				if ( '' === $label ) {
					$countries = is_array( $variant['countries'] ?? null ) ? $variant['countries'] : array();
					$label     = implode( ', ', $countries );
				}
				$steps[] = array(
					'label'  => sprintf(
						/* translators: 1: variant number, 2: variant label */
						__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
						$idx + 1,
						$label
					),
					'action' => 'geocore_create_variant',
					'params' => array(
						'countries' => $variant['countries'] ?? array(),
						'mode'      => $variant['mode'] ?? 'include_only',
					),
				);
			}
		}

		return array(
			'intent'                => (string) ( $raw['intent'] ?? '' ),
			'matched_action'        => (string) ( $raw['matched_action'] ?? '' ),
			'confidence'            => (float) ( $raw['confidence'] ?? 0 ),
			'requires_confirmation' => ! empty( $raw['requires_confirmation'] ),
			'summary'               => (string) ( $raw['summary'] ?? '' ),
			'setup_summary'         => self::format_setup_summary( $raw ),
			'params'                => isset( $raw['params'] ) && is_array( $raw['params'] ) ? $raw['params'] : array(),
			'steps'                 => $steps,
			'warnings'              => isset( $raw['warnings'] ) && is_array( $raw['warnings'] ) ? $raw['warnings'] : array(),
			'missing_information'   => isset( $raw['missing_information'] ) && is_array( $raw['missing_information'] ) ? $raw['missing_information'] : array(),
			'suggested_options'     => isset( $raw['suggested_options'] ) && is_array( $raw['suggested_options'] ) ? $raw['suggested_options'] : array(),
			'conditions'            => in_array( (string) ( $raw['intent'] ?? '' ), array( 'create_geo_variants', 'create_geo_variant_plan' ), true ) ? array() : ( isset( $raw['conditions'] ) && is_array( $raw['conditions'] ) ? $raw['conditions'] : array() ),
			'condition_match'       => in_array( (string) ( $raw['intent'] ?? '' ), array( 'create_geo_variants', 'create_geo_variant_plan' ), true ) ? '' : (string) ( $raw['condition_match'] ?? '' ),
			'portable_rule_set'     => $raw['portable_rule_set'] ?? null,
			'resolved_target'       => $raw['resolved_target'] ?? null,
			'original_message'      => $message,
			'interpretation_source' => (string) ( $raw['interpretation_source'] ?? '' ),
			'interpretation_badge'  => self::interpretation_badge( $raw ),
		);
	}

	/**
	 * Human-readable setup panel summary (not raw condition syntax).
	 *
	 * @param array<string,mixed> $raw Interpreter output.
	 * @return string
	 */
	private static function format_setup_summary( array $raw ) {
		$intent = (string) ( $raw['intent'] ?? '' );
		if ( 'create_geo_variant_plan' === $intent ) {
			$page_ref = (string) ( $raw['params']['source_page_ref'] ?? 'homepage' );
			$lines    = array(
				ucfirst( $page_ref ) . ' ' . __( 'targeting plan', 'reactwoo-geocore' ),
				'',
				__( 'Original homepage', 'reactwoo-geocore' ),
			);
			$source = $raw['params']['source_targeting'] ?? null;
			if ( is_array( $source ) ) {
				$label = (string) ( $source['targeting_label'] ?? $source['label'] ?? '' );
				if ( '' === $label && ! empty( $source['countries'] ) ) {
					$label = implode( ', ', (array) $source['countries'] );
				}
				$lines[] = $label;
			}
			foreach ( (array) ( $raw['params']['variants'] ?? array() ) as $variant ) {
				$ordinal = (int) ( $variant['ordinal'] ?? 0 );
				if ( $ordinal <= 0 ) {
					$ordinal = 1;
				}
				$lines[] = '';
				$lines[] = sprintf(
					/* translators: %d: variant number */
					__( 'Variant %d', 'reactwoo-geocore' ),
					$ordinal
				);
				$lines[] = (string) ( $variant['label'] ?? implode( ', ', (array) ( $variant['countries'] ?? array() ) ) );
			}
			return implode( "\n", $lines );
		}
		if ( 'create_geo_variants' === $intent && ! empty( $raw['params']['variants'] ) ) {
			$page_ref = (string) ( $raw['params']['source_page_ref'] ?? $raw['params']['page_ref'] ?? 'page' );
			$lines    = array( ucfirst( $page_ref ) . ' ' . __( 'variants', 'reactwoo-geocore' ) );
			foreach ( (array) $raw['params']['variants'] as $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				if ( '' === $label && ! empty( $variant['countries'] ) ) {
					$label = implode( ', ', (array) $variant['countries'] );
				}
				$lines[] = $label;
			}
			return implode( "\n", $lines );
		}
		if ( 'country_include' === $intent ) {
			return __( 'Country rule', 'reactwoo-geocore' ) . "\n" . (string) ( $raw['summary'] ?? '' );
		}
		if ( 'country_exclude' === $intent ) {
			return __( 'Country rule', 'reactwoo-geocore' ) . "\n" . (string) ( $raw['summary'] ?? '' );
		}
		return (string) ( $raw['summary'] ?? '' );
	}

	/**
	 * @return array<int,array<string,string>>
	 */
	private static function action_buttons() {
		return array(
			array( 'key' => 'confirm', 'label' => __( 'Create setup', 'reactwoo-geocore' ) ),
			array( 'key' => 'edit', 'label' => __( 'Edit setup', 'reactwoo-geocore' ) ),
			array( 'key' => 'debug', 'label' => __( 'Show debug', 'reactwoo-geocore' ) ),
			array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $proposal Proposal.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_redirect_steps( array $proposal ) {
		$page_id = 0;
		if ( ! empty( $proposal['resolved_target']['id'] ) ) {
			$page_id = (int) $proposal['resolved_target']['id'];
		} elseif ( ! empty( $proposal['params']['page_ref'] ) && class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$ref = RWGA_Page_Reference_Resolver::detect( (string) $proposal['params']['page_ref'] );
			if ( $ref && ! empty( $ref['page_id'] ) ) {
				$page_id = (int) $ref['page_id'];
			}
		}
		$base = admin_url( 'admin.php?page=rwgc-workflow-variant' );
		$out  = array();
		foreach ( (array) ( $proposal['steps'] ?? array() ) as $step ) {
			$url = $base;
			if ( $page_id ) {
				$url = add_query_arg( 'rwgc_master_page_id', $page_id, $url );
			}
			$countries = $step['params']['countries'] ?? array();
			if ( ! empty( $countries ) ) {
				$url = add_query_arg( 'rwgc_condition_type', 'countries', $url );
			}
			$out[] = array(
				'label' => (string) ( $step['label'] ?? '' ),
				'url'   => $url,
			);
		}
		if ( empty( $out ) ) {
			$out[] = array(
				'label' => __( 'Open variant workflow', 'reactwoo-geocore' ),
				'url'   => $page_id ? add_query_arg( 'rwgc_master_page_id', $page_id, $base ) : $base,
			);
		}
		return $out;
	}
}
