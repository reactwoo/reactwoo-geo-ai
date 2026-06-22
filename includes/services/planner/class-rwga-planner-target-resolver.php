<?php
/**
 * Resolve page, popup, and other targets per clause.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Target_Resolver {

	/**
	 * @param string              $clause  Clause text.
	 * @param string              $phrase  Full phrase for context.
	 * @param array<string,mixed> $context Resolver context.
	 * @param string              $action_type Action type.
	 * @param array<string,mixed> $clause_row  Clause metadata.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function resolve( $clause, $phrase, array $context, $action_type, array $clause_row = array() ) {
		unset( $context );
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );

		if ( preg_match( '/\bpopup\b/i', $clause ) ) {
			$label = self::popup_label( $clause );
			return array(
				'type'   => 'popup',
				'label'  => $label,
				'slug'   => sanitize_title( $label ),
				'source' => 'detected',
			);
		}

		if ( 'variant_child' === (string) ( $clause_row['type'] ?? '' )
			&& ! empty( $clause_row['parent'] )
			&& is_array( $clause_row['parent'] ) ) {
			$parent = $clause_row['parent'];
			return array(
				'type'   => 'page',
				'label'  => (string) ( $parent['sourcePageLabel'] ?? $parent['sourcePage'] ?? 'page' ),
				'slug'   => (string) ( $parent['sourcePage'] ?? 'page' ),
				'source' => 'parent_variant',
			);
		}

		$page_context = class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_page_context( $phrase )
			: array();

		if ( RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $action_type ) {
			$original = (string) ( $page_context['original_page'] ?? '' );
			if ( '' === $original && preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|checkout|cart|pricing(?:\s+page)?)\b/i', $clause, $m ) ) {
				$original = self::normalise_page_token( (string) $m[0] );
			}
			if ( '' === $original && preg_match( '/\b(?:homepage|home\s+page)\b/i', $clause ) ) {
				$original = 'homepage';
			}
			if ( '' !== $original ) {
				return self::page_target( $original );
			}
		}

		if ( preg_match( '/\bvariants?\s+of\s+(?:the\s+)?(shop(?:\s+page)?|home\s+page|homepage|checkout|cart|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $clause, $m ) ) {
			return self::page_target( self::normalise_page_token( (string) $m[1] ) );
		}

		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$page = RWGA_Page_Reference_Resolver::detect( $clause );
			if ( is_array( $page ) && ! empty( $page['value'] ) ) {
				return self::page_target( (string) $page['value'] );
			}
		}

		$variant_source = (string) ( $page_context['variant_source'] ?? '' );
		if ( '' !== $variant_source && in_array( $action_type, array( RWGA_Geo_Action_Types::CREATE_VARIANT, RWGA_Geo_Action_Types::UPDATE_VARIANT ), true ) ) {
			return self::page_target( $variant_source );
		}

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $action_type
			|| RWGA_Geo_Action_Types::UPDATE_VARIANT === $action_type ) {
			if ( '' !== $variant_source ) {
				return self::page_target( $variant_source );
			}
			if ( preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $phrase, $m ) ) {
				return self::page_target( self::normalise_page_token( (string) $m[0] ) );
			}
			return array(
				'type'   => 'page',
				'label'  => 'page',
				'slug'   => 'page',
				'source' => 'unknown',
			);
		}

		if ( preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $clause, $m ) ) {
			return self::page_target( self::normalise_page_token( (string) $m[0] ) );
		}

		return array(
			'type'   => 'page',
			'label'  => 'page',
			'slug'   => 'page',
			'source' => 'unknown',
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return string
	 */
	private static function popup_label( $clause ) {
		if ( preg_match( '/\b((?:winter|summer|spring|autumn|fall)\s+promo)\s+popup\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' popup';
		}
		if ( preg_match( '/\b(summer|winter|spring|autumn|fall)\s+popup\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' popup';
		}
		if ( preg_match( '/\b(?:the|a|an)\s+([\w\s-]+?)\s+popup\b/i', $clause, $m ) ) {
			return trim( (string) $m[1] ) . ' popup';
		}
		return 'popup';
	}

	/**
	 * @param string $token Page token.
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	private static function page_target( $token ) {
		$slug  = self::normalise_page_token( $token );
		$label = $slug;
		if ( 'shop' === $slug ) {
			$label = 'shop';
		} elseif ( 'homepage' === $slug ) {
			$label = 'homepage';
		} elseif ( 'pricing' === $slug ) {
			$label = 'pricing page';
		} elseif ( 'contact' === $slug ) {
			$label = 'contact page';
		} elseif ( preg_match( '/\s+page$/', $token ) ) {
			$label = $token;
		}
		return array(
			'type'   => 'page',
			'label'  => $label,
			'slug'   => $slug,
			'source' => 'detected',
		);
	}

	/**
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function normalise_page_token( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( in_array( $token, array( 'shop', 'shop page' ), true ) ) {
			return 'shop';
		}
		if ( in_array( $token, array( 'homepage', 'home page', 'home' ), true ) ) {
			return 'homepage';
		}
		if ( in_array( $token, array( 'pricing', 'pricing page' ), true ) ) {
			return 'pricing';
		}
		if ( in_array( $token, array( 'contact', 'contact page' ), true ) ) {
			return 'contact';
		}
		return $token;
	}
}
