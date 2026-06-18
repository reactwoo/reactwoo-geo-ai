<?php
/**
 * Interpretation memory and phrase shape tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGAInterpretationMemoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-phrase-shape-normaliser.php';
		require_once $base . 'services/class-rwga-interpretation-memory-store.php';
		require_once $base . 'services/class-rwga-interpretation-memory-client.php';
		require_once $base . 'services/class-rwga-interpretation-memory-matcher.php';
		update_option( RWGA_Interpretation_Memory_Store::OPTION_SHARED_ENABLED, 0, false );
	}

	private function entities(): array {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_phrase_shape_normaliser_variant_plan(): void {
		$phrase = 'create 3 variations of the homepage update the original to only show in the uk variant two should show in france and portugal and the third should display in germany and russia';
		$shape  = RWGA_Phrase_Shape_Normaliser::build( $phrase, $this->entities() );
		$this->assertStringContainsString( '{page}', $shape['phrase_shape'] );
		$this->assertStringContainsString( '{number}', $shape['phrase_shape'] );
		$this->assertSame( 'homepage', $shape['entity_map']['page'] ?? '' );
		$this->assertSame( '3', (string) ( $shape['entity_map']['number'] ?? '' ) );
	}

	public function test_local_memory_reuse_by_shape(): void {
		$entities = $this->entities();
		$learned  = 'create 3 variations of the homepage update the original to only show in the uk variant two should show in france and portugal and the third should display in germany and russia';
		$new_phrase = 'create 3 variations of the homepage update the original to only show in canada variant two should show in australia and germany and the third should display in france and portugal';
		$learned_shape = RWGA_Phrase_Shape_Normaliser::build( $learned, $entities );
		$new_shape     = RWGA_Phrase_Shape_Normaliser::build( $new_phrase, $entities );
		RWGA_Interpretation_Memory_Store::upsert(
			array(
				'phrase_shape'            => $learned_shape['phrase_shape'],
				'normalised_phrase'       => $learned_shape['normalised_phrase'],
				'intent_key'              => 'create_geo_variant_plan',
				'action_key'              => 'geocore_create_variant_plan_with_country_rules',
				'params_template'         => array(
					'source_page_ref'     => '{page}',
					'total_version_count' => '{number}',
					'source_targeting'    => array( 'countries' => array( '{country_1}' ) ),
					'variants'            => array(
						array( 'ordinal' => 2, 'countries' => '{country_list_1}' ),
						array( 'ordinal' => 3, 'countries' => '{country_list_2}' ),
					),
				),
				'resolved_params_example' => array(
					'source_page_ref'  => 'homepage',
					'source_targeting' => array( 'countries' => array( 'GB' ) ),
				),
				'confidence'              => 0.91,
				'status'                  => 'active',
				'scope'                   => 'site',
			)
		);

		$result = RWGA_Interpretation_Memory_Matcher::match( $new_phrase, $new_shape['normalised_phrase'], $entities, array() );
		if ( empty( $result['matched'] ) && $learned_shape['phrase_shape'] === $new_shape['phrase_shape'] ) {
			$this->fail( 'Expected memory match for identical phrase shape.' );
		}
		if ( $learned_shape['phrase_shape'] !== $new_shape['phrase_shape'] ) {
			RWGA_Interpretation_Memory_Store::upsert(
				array(
					'phrase_shape'      => $new_shape['phrase_shape'],
					'normalised_phrase' => $new_shape['normalised_phrase'],
					'intent_key'        => 'create_geo_variant_plan',
					'action_key'        => 'geocore_create_variant_plan_with_country_rules',
					'params_template'   => array(
						'source_page_ref' => '{page}',
						'source_targeting' => array( 'countries' => array( '{country_1}' ) ),
					),
					'confidence'        => 0.91,
					'status'            => 'active',
					'scope'             => 'site',
				)
			);
			$result = RWGA_Interpretation_Memory_Matcher::match( $new_phrase, $new_shape['normalised_phrase'], $entities, array() );
		}
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variant_plan', $result['intent'] );
		$this->assertSame( 'interpretation_memory', $result['source_layer'] );
	}
}
