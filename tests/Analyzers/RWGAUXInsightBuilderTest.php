<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * UX insight builder tests.
 */
class RWGAUXInsightBuilderTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Builder_Normalize', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/builders/class-rwga-builder-normalize.php';
		}
		if ( ! class_exists( 'RWGA_UX_Insight_Builder', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/analyzers/class-rwga-ux-insight-builder.php';
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function landing_payload() {
		return array(
			'sections' => array(
				array( 'classification' => array( 'type' => 'hero' ), 'has_cta' => true ),
				array( 'classification' => array( 'type' => 'features' ) ),
				array( 'classification' => array( 'type' => 'testimonials' ) ),
				array( 'classification' => array( 'type' => 'cta' ), 'has_cta' => true ),
			),
			'ctas' => array(
				array( 'label' => 'Get Started Free' ),
				array( 'label' => 'Learn more' ),
			),
			'forms'     => array(),
			'widgets'   => array_fill( 0, 12, array( 'type' => 'text-editor' ) ),
			'ux_scores' => array(
				'cta_score'   => 72,
				'trust_score' => 68,
			),
		);
	}

	/**
	 * @return void
	 */
	public function test_message_hierarchy_includes_proof_before_cta() {
		$result = RWGA_UX_Insight_Builder::analyze( $this->landing_payload() );
		$order  = $result['messaging_hierarchy']['message_order'] ?? array();
		$this->assertContains( 'problem', $order );
		$this->assertContains( 'proof', $order );
		$this->assertContains( 'cta', $order );
		$this->assertTrue( ! empty( $result['messaging_hierarchy']['ideal_match'] ) );
	}

	/**
	 * @return void
	 */
	public function test_cta_effectiveness_scores() {
		$result = RWGA_UX_Insight_Builder::analyze( $this->landing_payload() );
		$cta    = $result['cta_effectiveness'] ?? array();
		$this->assertSame( 'Get Started Free', $cta['primary_cta'] ?? '' );
		$this->assertGreaterThanOrEqual( 50, (int) ( $cta['cta_strength'] ?? 0 ) );
		$this->assertSame( 'high', $cta['commitment_level'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_trust_gap_when_proof_missing_before_cta() {
		$payload = $this->landing_payload();
		$payload['sections'] = array(
			array( 'classification' => array( 'type' => 'hero' ), 'has_cta' => true ),
			array( 'classification' => array( 'type' => 'pricing' ) ),
		);
		$result = RWGA_UX_Insight_Builder::analyze( $payload );
		$this->assertSame( 'proof_before_cta_missing', $result['trust']['trust_gap'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_to_insight_rows_not_empty() {
		$result = RWGA_UX_Insight_Builder::analyze( $this->landing_payload() );
		$rows   = RWGA_UX_Insight_Builder::to_insight_rows( $result, 'h1', 's1' );
		$keys   = array_column( $rows, 'insight_key' );
		$this->assertContains( 'cta_effectiveness', $keys );
		$this->assertContains( 'message_hierarchy', $keys );
	}
}
