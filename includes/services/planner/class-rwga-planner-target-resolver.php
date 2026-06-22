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
	 * @return array{type:string,label:string,slug:string,source:string}
	 */
	public static function resolve( $clause, $phrase, array $context, $action_type ) {
		unset( $context );
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );

		if ( preg_match( '/\bpopup\b/i', $clause ) ) {
			$label = 'popup';
			if ( preg_match( '/\b(summer|winter|spring|autumn|fall)\s+popup\b/i', $clause, $m ) ) {
				$label = trim( (string) $m[1] ) . ' popup';
			} elseif ( preg_match( '/\b(?:the|a|an)\s+([\w-]+)\s+popup\b/i', $clause, $m ) ) {
				$label = trim( (string) $m[1] ) . ' popup';
			}
			return array(
				'type'   => 'popup',
				'label'  => $label,
				'slug'   => sanitize_title( $label ),
				'source' => 'detected',
			);
		}

		$page_context = class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_page_context( $phrase )
			: array();

		if ( RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $action_type ) {
			$original = (string) ( $page_context['original_page'] ?? '' );
			if ( '' === $original && preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|checkout|cart)\b/i', $clause, $m ) ) {
				$original = self::normalise_page_token( (string) $m[0] );
			}
			if ( '' === $original ) {
				$original = 'homepage';
			}
			return self::page_target( $original );
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

		if ( preg_match( '/\b(?:homepage|home\s+page|shop(?:\s+page)?|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $clause, $m ) ) {
			return self::page_target( self::normalise_page_token( (string) $m[0] ) );
		}

		return self::page_target( 'homepage' );
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
