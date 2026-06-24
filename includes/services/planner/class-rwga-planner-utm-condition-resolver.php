<?php
/**
 * Extract UTM query parameters from clause text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Utm_Condition_Resolver {

	/**
	 * @param string $text Normalised text.
	 * @return array<int,array{key:string,value:string}>
	 */
	public static function extract( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( $text );
		$rows = array();

		if ( preg_match( '/\bwhen\s+the\s+campaign\s+is\s+([\w-]+)/i', $text, $m ) ) {
			$val = trim( (string) ( $m[1] ?? '' ) );
			if ( '' !== $val ) {
				$rows[] = array(
					'key'   => 'utm_campaign',
					'field' => 'campaign',
					'value' => $val,
				);
			}
		} elseif ( preg_match( '/\b(?:the\s+)?campaign\s+is\s+([\w-]+)/i', $text, $m ) ) {
			$val = trim( (string) ( $m[1] ?? '' ) );
			if ( '' !== $val && ! preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $text ) ) {
				$rows[] = array(
					'key'   => 'utm_campaign',
					'field' => 'campaign',
					'value' => $val,
				);
			}
		}

		if ( preg_match( '/\b(?:from\s+)?email\s+traffic\b/i', $text )
			|| preg_match( '/\bvisitors?\s+from\s+email\b/i', $text )
			|| preg_match( '/\bemail\s+(?:referral|source|medium)\b/i', $text ) ) {
			$rows[] = array(
				'key'   => 'utm_medium',
				'field' => 'medium',
				'value' => 'email',
			);
		}

		if ( preg_match_all( '/\butm_([\w-]+)=([\w-]+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key = 'utm_' . strtolower( (string) ( $match[1] ?? '' ) );
				$val = (string) ( $match[2] ?? '' );
				if ( '' === $key || '' === $val ) {
					continue;
				}
				$rows[] = array(
					'key'   => $key,
					'field' => str_replace( 'utm_', '', $key ),
					'value' => $val,
				);
			}
		}

		return self::dedupe_rows( $rows );
	}

	/**
	 * @param array<int,array{key:string,field:string,value:string}> $rows Rows.
	 * @return array<int,array{key:string,field:string,value:string}>
	 */
	private static function dedupe_rows( array $rows ) {
		$seen = array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = (string) ( $row['key'] ?? '' ) . '=' . (string) ( $row['value'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	}
}
