<?php
/**
 * Apply user card resolutions to a stored multi-action plan.
 *
 * The targeting assistant renders each action as a review card with field-level
 * controls. When the user chooses a synced entity, ignores a field, or removes
 * an action, the client sends those decisions to the execute endpoint. This
 * service applies them to the stored planner actions so the plan can be
 * re-validated server-side before anything is created.
 *
 * Resolution row shape (from the client):
 *   { card: int, field: 'target'|'campaign'|'audience', raw: string,
 *     action: 'choose'|'ignore'|'remove_action', id?: string, label?: string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Card_Resolution_Applier {

	/**
	 * @param array<int,array<string,mixed>> $actions     Planner actions.
	 * @param array<int,array<string,mixed>> $resolutions Client resolution rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function apply( array $actions, array $resolutions ) {
		$removed = array();
		$by_card = array();

		foreach ( $resolutions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$card = (int) ( $row['card'] ?? -1 );
			$kind = (string) ( $row['action'] ?? '' );
			if ( 'remove_action' === $kind || 'remove' === $kind ) {
				$removed[ $card ] = true;
				continue;
			}
			$by_card[ $card ][] = $row;
		}

		$out = array();
		foreach ( array_values( $actions ) as $index => $action ) {
			if ( ! is_array( $action ) || isset( $removed[ $index ] ) ) {
				continue;
			}
			if ( isset( $by_card[ $index ] ) ) {
				$action = self::apply_to_action( $action, $by_card[ $index ] );
			}
			$out[] = $action;
		}

		return array_values( $out );
	}

	/**
	 * @param array<string,mixed>            $action Action.
	 * @param array<int,array<string,mixed>> $rows   Field resolution rows for this action.
	 * @return array<string,mixed>
	 */
	private static function apply_to_action( array $action, array $rows ) {
		foreach ( $rows as $row ) {
			$field = (string) ( $row['field'] ?? '' );
			$kind  = (string) ( $row['action'] ?? '' );
			$raw   = (string) ( $row['raw'] ?? '' );
			$id    = (string) ( $row['id'] ?? '' );
			$label = (string) ( $row['label'] ?? '' );

			if ( 'target' === $field ) {
				$action = self::apply_target( $action, $kind, $id, $label );
			} elseif ( 'campaign' === $field ) {
				$action = self::apply_campaign( $action, $kind, $id, $label, $raw );
			} elseif ( 'audience' === $field ) {
				$action = self::apply_audience( $action, $kind, $id, $label, $raw );
			}
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @return array<string,mixed>
	 */
	private static function apply_target( array $action, $kind, $id, $label ) {
		if ( ! is_array( $action['target'] ?? null ) ) {
			$action['target'] = array();
		}
		if ( 'choose' === $kind ) {
			$type = (string) ( $action['target']['type'] ?? 'page' );
			$action['target']['user_resolved'] = array(
				'id'   => '' !== $id ? $id : sanitize_title( $label ),
				'name' => '' !== $label ? $label : (string) ( $action['target']['label'] ?? '' ),
				'type' => $type,
			);
			if ( '' !== $label ) {
				$action['target']['label'] = $label;
			}
			unset( $action['target']['user_ignored'] );
		} else {
			$action['target']['user_ignored'] = true;
			unset( $action['target']['user_resolved'] );
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @param string              $raw    Raw phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_campaign( array $action, $kind, $id, $label, $raw ) {
		self::clear_unresolved( $action, 'campaigns', $raw );
		if ( 'choose' === $kind ) {
			$action['campaign'] = array(
				'id'     => '' !== $id ? $id : sanitize_title( $label ),
				'name'   => $label,
				'source' => '',
			);
		} else {
			unset( $action['campaign'] );
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @param string              $raw    Raw phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_audience( array $action, $kind, $id, $label, $raw ) {
		self::clear_unresolved( $action, 'audiences', $raw );
		if ( 'choose' === $kind ) {
			if ( ! is_array( $action['conditions'] ?? null ) ) {
				$action['conditions'] = array();
			}
			if ( ! is_array( $action['conditions']['include'] ?? null ) ) {
				$action['conditions']['include'] = array();
			}
			if ( ! is_array( $action['conditions']['include']['audiences'] ?? null ) ) {
				$action['conditions']['include']['audiences'] = array();
			}
			$action['conditions']['include']['audiences'][] = array(
				'id'     => '' !== $id ? $id : sanitize_title( $label ),
				'name'   => $label,
				'source' => '',
				'raw'    => $raw,
			);
		}
		return $action;
	}

	/**
	 * Remove an unresolved entity row (campaign/audience) matching a raw phrase.
	 *
	 * @param array<string,mixed> $action Action (by reference).
	 * @param string              $type   'campaigns'|'audiences'.
	 * @param string              $raw    Raw phrase to drop.
	 * @return void
	 */
	private static function clear_unresolved( array &$action, $type, $raw ) {
		if ( empty( $action['unresolved'][ $type ] ) || ! is_array( $action['unresolved'][ $type ] ) ) {
			return;
		}
		$needle = self::normalise( $raw );
		$kept   = array();
		foreach ( $action['unresolved'][ $type ] as $entry ) {
			$entry_raw = is_array( $entry ) ? (string) ( $entry['raw'] ?? '' ) : (string) $entry;
			if ( '' !== $needle && self::normalise( $entry_raw ) === $needle ) {
				continue;
			}
			$kept[] = $entry;
		}
		$action['unresolved'][ $type ] = array_values( $kept );
	}

	/**
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function normalise( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return (string) preg_replace( '/\s+/', ' ', $value );
	}
}
