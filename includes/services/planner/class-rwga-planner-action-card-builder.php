<?php
/**
 * Build per-action review cards with explicit resolution status.
 *
 * Each card turns a parsed action into a reviewable unit: target, campaign,
 * audiences, include/exclude conditions, warnings and — crucially — the list of
 * fields that still require the user to resolve an unknown mapping before the
 * setup can be created. This is what powers the guided "review card" UI instead
 * of a flat, optimistic summary.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Card_Builder {

	const STATUS_READY           = 'ready';
	const STATUS_NEEDS_RESOLUTION = 'needs_resolution';

	/**
	 * @param array<int,array<string,mixed>> $actions  Plan actions.
	 * @param array<string,mixed>            $context  Planner context.
	 * @param array<int,array>               $entities Entity rows.
	 * @return array{cards:array<int,array<string,mixed>>,fields_needing_attention:int,requires_resolution:bool}
	 */
	public static function build( array $actions, array $context = array(), array $entities = array() ) {
		$cards = array();
		$total = 0;

		$position = 0;
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$position++;
			$card   = self::build_card( $action, $position, $context, $entities );
			$total += count( $card['requiredResolutions'] );
			$cards[] = $card;
		}

		return array(
			'cards'                    => $cards,
			'fields_needing_attention' => $total,
			'requires_resolution'      => $total > 0,
		);
	}

	/**
	 * @param array<string,mixed> $action   Action.
	 * @param int                 $position 1-based index.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function build_card( array $action, $position, array $context, array $entities ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$include    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions )
			: ( is_array( $conditions['include'] ?? null ) ? $conditions['include'] : array() );
		$exclude    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions )
			: ( is_array( $conditions['exclude'] ?? null ) ? $conditions['exclude'] : array() );

		$target    = self::build_target( $action, $context, $entities );
		$campaign  = self::build_campaign( $action );
		$audiences = self::build_audiences( $action, $include );

		$required = array();
		if ( self::target_needs_resolution( $target ) ) {
			$required[] = array(
				'field'   => 'target',
				'raw'     => (string) $target['raw'],
				'status'  => (string) $target['status'],
				'actions' => array( 'choose_target', 'search_targets', 'remove_action' ),
			);
		}
		if ( null !== $campaign && self::entity_needs_resolution( $campaign ) ) {
			$required[] = array(
				'field'   => 'campaign',
				'raw'     => (string) $campaign['raw'],
				'status'  => (string) $campaign['status'],
				'actions' => array( 'choose_campaign', 'ignore_campaign', 'refresh_campaigns' ),
			);
		}
		foreach ( $audiences as $audience ) {
			if ( self::entity_needs_resolution( $audience ) ) {
				$required[] = array(
					'field'   => 'audience',
					'raw'     => (string) $audience['raw'],
					'status'  => (string) $audience['status'],
					'actions' => array( 'choose_audience', 'ignore_audience', 'refresh_audiences' ),
				);
			}
		}

		return array(
			'id'                  => (string) ( $action['id'] ?? 'action_' . $position ),
			'index'               => $position,
			'type'                => (string) ( $action['type'] ?? '' ),
			'status'              => empty( $required ) ? self::STATUS_READY : self::STATUS_NEEDS_RESOLUTION,
			'target'              => $target,
			'campaign'            => $campaign,
			'operation'           => is_array( $action['operation'] ?? null ) ? $action['operation'] : array(),
			'conditions'          => array(
				'include' => self::public_include( $include ),
				'exclude' => $exclude,
			),
			'audiences'           => $audiences,
			'warnings'            => array_values( (array) ( $action['warnings'] ?? array() ) ),
			'requiredResolutions' => $required,
		);
	}

	/**
	 * @param array<string,mixed> $action   Action.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function build_target( array $action, array $context, array $entities ) {
		$raw_target = is_array( $action['target'] ?? null ) ? $action['target'] : array();

		if ( ! empty( $raw_target['user_resolved'] ) && is_array( $raw_target['user_resolved'] ) ) {
			$chosen = $raw_target['user_resolved'];
			return array(
				'type'        => (string) ( $chosen['type'] ?? ( $raw_target['type'] ?? 'page' ) ),
				'raw'         => (string) ( $chosen['name'] ?? ( $raw_target['label'] ?? '' ) ),
				'resolved'    => array(
					'id'   => (string) ( $chosen['id'] ?? '' ),
					'name' => (string) ( $chosen['name'] ?? '' ),
				),
				'status'      => 'matched',
				'suggestions' => array(),
			);
		}
		if ( ! empty( $raw_target['user_ignored'] ) ) {
			return array(
				'type'        => (string) ( $raw_target['type'] ?? 'page' ),
				'raw'         => (string) ( $raw_target['label'] ?? '' ),
				'resolved'    => null,
				'status'      => 'ignored',
				'suggestions' => array(),
			);
		}

		$resolution = class_exists( 'RWGA_Planner_Target_Registry_Resolver', false )
			? RWGA_Planner_Target_Registry_Resolver::resolve( $raw_target, $context, $entities )
			: array(
				'type'        => (string) ( $raw_target['type'] ?? 'page' ),
				'raw'         => (string) ( $raw_target['label'] ?? '' ),
				'resolved'    => null,
				'status'      => 'registry_unavailable',
				'suggestions' => array(),
			);

		$source = (string) ( $raw_target['source'] ?? '' );
		if ( in_array( $source, array( 'parent_variant', 'inherited' ), true ) ) {
			$resolution['inherited'] = true;
			$resolution['inheritedFrom'] = (string) ( $raw_target['inheritedFrom'] ?? $raw_target['sourcePage'] ?? $raw_target['label'] ?? '' );
			if ( in_array( $resolution['status'], array( 'not_found', 'ambiguous' ), true ) ) {
				$resolution['status'] = 'inherited_unresolved';
			}
		}

		return $resolution;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>|null
	 */
	private static function build_campaign( array $action ) {
		$campaign   = is_array( $action['campaign'] ?? null ) ? $action['campaign'] : array();
		$unresolved = (array) ( $action['unresolved']['campaigns'] ?? array() );

		if ( ! empty( $campaign['id'] ) ) {
			return array(
				'raw'         => (string) ( $campaign['name'] ?? '' ),
				'resolved'    => array(
					'id'     => (string) $campaign['id'],
					'name'   => (string) ( $campaign['name'] ?? '' ),
					'source' => (string) ( $campaign['source'] ?? '' ),
				),
				'status'      => 'matched',
				'suggestions' => array(),
			);
		}

		if ( ! empty( $unresolved ) && is_array( $unresolved[0] ) ) {
			$row = $unresolved[0];
			return array(
				'raw'         => (string) ( $row['raw'] ?? ( $campaign['label'] ?? '' ) ),
				'resolved'    => null,
				'status'      => (string) ( $row['status'] ?? 'not_found' ),
				'suggestions' => self::entity_suggestions( (array) ( $row['suggestions'] ?? array() ) ),
			);
		}

		if ( ! empty( $campaign['label'] ) ) {
			return array(
				'raw'         => (string) $campaign['label'],
				'resolved'    => null,
				'status'      => (string) ( $campaign['status'] ?? 'not_found' ),
				'suggestions' => array(),
			);
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $action  Action.
	 * @param array<string,mixed> $include Include condition group.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_audiences( array $action, array $include ) {
		$audiences = array();

		foreach ( (array) ( $include['audiences'] ?? array() ) as $matched ) {
			if ( is_array( $matched ) && '' !== (string) ( $matched['name'] ?? '' ) ) {
				$audiences[] = array(
					'raw'         => (string) ( $matched['raw'] ?? $matched['name'] ),
					'resolved'    => array(
						'id'     => (string) ( $matched['id'] ?? '' ),
						'name'   => (string) $matched['name'],
						'source' => (string) ( $matched['source'] ?? '' ),
					),
					'status'      => 'matched',
					'suggestions' => array(),
				);
			} elseif ( is_string( $matched ) && '' !== $matched ) {
				$audiences[] = array(
					'raw'         => $matched,
					'resolved'    => array( 'id' => '', 'name' => $matched, 'source' => '' ),
					'status'      => 'matched',
					'suggestions' => array(),
				);
			}
		}

		foreach ( (array) ( $action['unresolved']['audiences'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['raw'] ?? '' ) ) {
				continue;
			}
			$audiences[] = array(
				'raw'         => (string) $row['raw'],
				'resolved'    => null,
				'status'      => (string) ( $row['status'] ?? 'not_found' ),
				'suggestions' => self::entity_suggestions( (array) ( $row['suggestions'] ?? array() ) ),
			);
		}

		return $audiences;
	}

	/**
	 * Strip internal audience objects from the public include group (the card
	 * exposes audiences separately with resolution status).
	 *
	 * @param array<string,mixed> $include Include group.
	 * @return array<string,mixed>
	 */
	private static function public_include( array $include ) {
		unset( $include['audiences'] );
		return $include;
	}

	/**
	 * @param array<int,array> $suggestions Raw suggestions.
	 * @return array<int,array{id:string,name:string,source:string,confidence:float}>
	 */
	private static function entity_suggestions( array $suggestions ) {
		$out = array();
		foreach ( $suggestions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = trim( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'         => (string) ( $row['id'] ?? '' ),
				'name'       => $name,
				'source'     => (string) ( $row['source'] ?? '' ),
				'confidence' => (float) ( $row['confidence'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $target Target resolution.
	 * @return bool
	 */
	private static function target_needs_resolution( array $target ) {
		return in_array(
			(string) ( $target['status'] ?? '' ),
			array( 'not_found', 'ambiguous', 'inherited_unresolved' ),
			true
		);
	}

	/**
	 * @param array<string,mixed> $entity Campaign/audience resolution.
	 * @return bool
	 */
	private static function entity_needs_resolution( array $entity ) {
		$status = (string) ( $entity['status'] ?? '' );
		return '' !== $status && 'matched' !== $status;
	}
}
