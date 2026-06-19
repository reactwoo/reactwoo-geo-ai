<?php
/**
 * Inferred plan builder tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGAInferredPlanBuilderTest extends TestCase {

	private static bool $booted = false;

	protected function setUp(): void {
		parent::setUp();
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-segment-condition-extractor.php';
		require_once $base . 'services/class-rwga-variant-group-extractor.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-inferred-plan-builder.php';
		require_once $base . 'services/class-rwga-interpretation-status.php';
	}

	private function entities(): array {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_from_params_builds_mixed_weather_plan(): void {
		$params = array(
			'source_page_ref'  => 'homepage',
			'source_targeting' => array(
				'label'     => 'Original homepage',
				'countries' => array( 'GB' ),
				'weather'   => array( 'mode' => 'any' ),
			),
			'variants'         => array(
				array(
					'label'     => 'Variant 1',
					'countries' => array( 'PT' ),
					'weather'   => array( 'condition' => 'rain' ),
				),
				array(
					'label'     => 'Variant 2',
					'countries' => array( 'RU', 'DE' ),
					'weather'   => array( 'condition' => 'sunny' ),
				),
			),
		);
		$plan = RWGA_Inferred_Plan_Builder::from_params( 'homepage', $params, $this->entities() );
		$this->assertIsArray( $plan );
		$this->assertSame( array( 'GB' ), $plan['source_targeting']['countries'] );
		$this->assertSame( 'any', $plan['source_targeting']['weather']['mode'] );
		$this->assertSame( 'rain', $plan['variants'][0]['weather']['condition'] );
		$this->assertSame( array( 'RU', 'DE' ), $plan['variants'][1]['countries'] );
	}

	public function test_partial_with_inferred_plan_gets_clarification_buttons(): void {
		$partial = array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_conditions',
			'confidence'          => 0.62,
			'proposal_ready'      => false,
			'inferred_plan'       => array(
				'source_page_ref'  => 'homepage',
				'source_targeting' => array(
					'label'     => 'Original homepage',
					'countries' => array( 'GB' ),
					'weather'   => array( 'mode' => 'any' ),
				),
				'variants'         => array(
					array(
						'label'     => 'Variant 1',
						'countries' => array( 'PT' ),
						'weather'   => array( 'condition' => 'rain' ),
					),
				),
			),
			'missing_information' => array(
				array( 'key' => 'variant_grouping', 'question' => 'Is this split correct?' ),
			),
		);
		$meta = RWGA_Interpretation_Status::from_result(
			array_merge(
				$partial,
				array(
					'interpretation_source' => 'local_parser',
					'interpretation_status' => RWGA_Interpretation_Status::NEEDS_CLARIFICATION,
				)
			)
		);
		$this->assertFalse( $meta['can_execute'] );
		$this->assertSame( 'accept_inferred_split', $meta['clarification']['options'][0]['key'] ?? '' );
		$this->assertSame( 'edit_split', $meta['clarification']['options'][1]['key'] ?? '' );
	}
}
