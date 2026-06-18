<?php
/**
 * Parse variant plans: source/original page targeting + duplicate variants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Variant_Plan_Interpreter {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Resolved context.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities, array $context = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return array( 'matched' => false );
		}

		$has_original = class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
			&& RWGA_Original_Source_Targeting_Extractor::has_original_marker( $phrase );

		if ( preg_match( '/\bcreate\s+(?:three|3|four|4|five|5)\s+versions?\b/i', $phrase ) && ! $has_original ) {
			return self::ambiguous_total_versions( $phrase, $entities );
		}

		if ( ! $has_original && ! self::has_plan_signals( $phrase ) ) {
			return array( 'matched' => false );
		}

		$source = class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
			? RWGA_Original_Source_Targeting_Extractor::extract( $phrase, $entities )
			: null;

		if ( ! $source && ! $has_original ) {
			return array( 'matched' => false );
		}

		$duplicate_count = self::detect_duplicate_count( $phrase );
		$groups          = class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::extract_plan_variant_groups( $phrase, $entities, $source )
			: array();

		if ( empty( $source['countries'] ) || count( $groups ) < 1 ) {
			return array( 'matched' => false );
		}

		if ( $duplicate_count > 0 && count( $groups ) !== $duplicate_count ) {
			$duplicate_count = count( $groups );
		}

		$page_ref   = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;
		$page_value = $page_ref ? (string) ( $page_ref['value'] ?? 'homepage' ) : 'homepage';

		$variants = array();
		$steps    = array();

		$source_label = (string) ( $source['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) );
		$source_countries = (array) ( $source['countries'] ?? array() );
		$source_display = class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::label_for_countries( $source_countries, $entities )
			: implode( ', ', $source_countries );
		$steps[] = array(
			'label'  => sprintf(
				/* translators: %s: country group label */
				__( 'Apply %s targeting to original homepage', 'reactwoo-geocore' ),
				(string) ( $source['label'] ?? implode( ', ', $source_countries ) )
			),
			'action' => 'geocore_apply_country_rule_to_source',
			'params' => array(
				'source_page_ref' => $page_value,
				'countries'       => $source_countries,
				'mode'            => $source['mode'] ?? 'include_only',
			),
		);

		foreach ( $groups as $idx => $group ) {
			$ordinal  = $idx + 1;
			$label    = (string) ( $group['label'] ?? '' );
			$variants[] = array(
				'ordinal'   => $ordinal,
				'label'     => $label,
				'mode'      => $group['mode'] ?? 'include_only',
				'countries' => $group['countries'] ?? array(),
			);
			$steps[] = array(
				'label'  => sprintf(
					/* translators: 1: variant number, 2: country label */
					__( 'Create new variant %1$d: %2$s', 'reactwoo-geocore' ),
					$ordinal,
					$label
				),
				'action' => 'geocore_duplicate_page_as_variant',
				'params' => array(
					'source_page_ref' => $page_value,
					'countries'       => $group['countries'] ?? array(),
					'mode'            => $group['mode'] ?? 'include_only',
				),
			);
		}

		$dup_count = $duplicate_count > 0 ? $duplicate_count : count( $variants );

		return array(
			'matched'          => true,
			'intent'           => 'create_geo_variant_plan',
			'matched_action'   => 'geocore_create_variant_plan_with_country_rules',
			'confidence'       => 0.88,
			'page_ref'         => $page_ref,
			'source_targeting' => $source,
			'variant_groups'   => $groups,
			'duplicate_count'  => $dup_count,
			'matched_terms'    => array_merge(
				array( 'duplicate', 'original' ),
				array_map(
					static function ( $g ) {
						return (string) ( $g['raw'] ?? '' );
					},
					$groups
				)
			),
			'params'           => array(
				'source_page_ref'  => $page_value,
				'duplicate_count'  => $dup_count,
				'source_targeting' => array(
					'label'           => $source_label,
					'targeting_label' => $source_display,
					'mode'            => $source['mode'] ?? 'include_only',
					'countries'       => $source_countries,
				),
				'variants'         => $variants,
			),
			'steps'            => $steps,
			'summary'          => self::build_summary( $page_value, $source, $variants ),
		);
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	private static function has_plan_signals( $phrase ) {
		return (bool) preg_match( '/\b(?:duplicate|copy|clone)\b/i', $phrase )
			|| (bool) preg_match( '/\b(?:keep|leave|make)\s+the\s+original\b/i', $phrase );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_duplicate_count( $phrase ) {
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+twice\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+two times\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+three times\b/i', $phrase ) ) {
			return 3;
		}
		return 0;
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function ambiguous_total_versions( $phrase, array $entities ) {
		unset( $entities );
		return array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.65,
			'requires_confirmation' => true,
			'missing_information' => array(
				array(
					'key'      => 'source_usage',
					'question' => __( 'Should the original homepage become one of these versions, or should I create three new variants?', 'reactwoo-geocore' ),
				),
			),
			'suggested_options'   => array(
				__( 'Use original as the first version', 'reactwoo-geocore' ),
				__( 'Create three new variants', 'reactwoo-geocore' ),
			),
			'summary'             => __( 'I need to know whether to use the original page as one of the versions.', 'reactwoo-geocore' ),
		);
	}

	/**
	 * @param string                         $page     Page ref.
	 * @param array<string,mixed>            $source   Source targeting.
	 * @param array<int,array<string,mixed>> $variants Duplicate variants.
	 * @return string
	 */
	private static function build_summary( $page, array $source, array $variants ) {
		$source_label = (string) ( $source['label'] ?? '' );
		if ( '' === $source_label && ! empty( $source['countries'] ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			$source_label = RWGA_Variant_Group_Extractor::label_for_countries( (array) $source['countries'], array() );
		}
		$variant_labels = array_map(
			static function ( $v ) {
				return (string) ( $v['label'] ?? '' );
			},
			$variants
		);
		return sprintf(
			/* translators: 1: page, 2: source label, 3: variant labels */
			__( 'Update the original %1$s for %2$s, then create %3$d duplicate variant(s): %4$s.', 'reactwoo-geocore' ),
			$page,
			$source_label,
			count( $variants ),
			implode( '; ', array_filter( $variant_labels ) )
		);
	}
}
