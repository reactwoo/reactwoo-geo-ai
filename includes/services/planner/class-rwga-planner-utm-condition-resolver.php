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

		if ( preg_match_all( '/\butm_([\w-]+)=([\w-]+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key = 'utm_' . strtolower( (string) ( $match[1] ?? '' ) );
				$val = (string) ( $match[2] ?? '' );
				if ( '' === $key || '' === $val ) {
					continue;
				}
				$rows[] = array(
					'key'   => $key,
					'value' => $val,
				);
			}
		}

		return $rows;
	}
}
