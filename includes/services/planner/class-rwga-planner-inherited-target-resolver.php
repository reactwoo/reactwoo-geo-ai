<?php
/**
 * Track and resolve inherited targets across multi-clause plans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Inherited_Target_Resolver {

	/**
	 * @param string              $clause  Clause text.
	 * @param string              $phrase  Full phrase.
	 * @param array<string,mixed> $session Session state.
	 * @return array<string,mixed>|null
	 */
	public static function detect_named_target( $clause, $phrase, array $session = array() ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );

		if ( preg_match( '/\b(?:same|the)\s+product\s+page\b/i', $clause ) && ! empty( $session['currentTarget'] ) ) {
			return $session['currentTarget'];
		}

		if ( preg_match( '/\b([\w\s-]+?)\s+product\s+page\b/i', $clause, $m ) ) {
			$label = trim( (string) $m[1] ) . ' product page';
			if ( preg_match( '/\b(?:same|the)\s+product\s+page\b/i', $label ) && ! empty( $session['currentTarget'] ) ) {
				return $session['currentTarget'];
			}
			return self::product_page_target( $label );
		}

		if ( preg_match( '/\b([\w\s-]+?)\s+product\s+page\b/i', $phrase, $m )
			&& self::needs_pronoun_target( $clause ) ) {
			return self::product_page_target( trim( (string) $m[1] ) . ' product page' );
		}

		return null;
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function needs_pronoun_target( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match(
			'/^(?:but\s+)?(?:hide|show)\s+it\b|\b(?:hide|show)\s+it\s+from\b|\bsame\s+product\s+page\b/i',
			$clause
		);
	}

	/**
	 * @param array<string,mixed>|null $target Target row.
	 * @return bool
	 */
	public static function is_named_target( $target ) {
		if ( ! is_array( $target ) ) {
			return false;
		}
		$label = strtolower( trim( (string) ( $target['label'] ?? '' ) ) );
		if ( '' === $label || in_array( $label, array( 'page', 'product', 'product page' ), true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $label Product page label.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function product_page_target( $label ) {
		$label = trim( (string) $label );
		return array(
			'type'   => 'product_page',
			'label'  => $label,
			'slug'   => sanitize_title( $label ),
			'source' => 'detected',
		);
	}

	/**
	 * @param array<string,mixed> $session Session.
	 * @param array<string,mixed> $target  Target row.
	 * @return array<string,mixed>
	 */
	public static function remember_target( array $session, array $target ) {
		if ( self::is_named_target( $target ) ) {
			$session['currentTarget'] = $target;
		}
		return $session;
	}
}
