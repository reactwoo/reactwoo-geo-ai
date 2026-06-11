<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Messaging analyzer unit tests.
 */
class RWGAMessagingAnalyzerTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Builder_Normalize', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/builders/class-rwga-builder-normalize.php';
		}
		if ( ! class_exists( 'RWGA_Messaging_Analyzer', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/analyzers/class-rwga-messaging-analyzer.php';
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function sample_payload() {
		return array(
			'page_title' => 'Warehouse OS',
			'page_type'  => 'landing_page',
			'sections'   => array(
				array(
					'id'            => 'sec-1',
					'heading'       => 'Reduce warehouse costs by 30%',
					'subheading'    => 'African warehouse expertise combined with automation technology',
					'classification' => array(
						'type'       => 'hero',
						'confidence' => 0.9,
					),
				),
				array(
					'classification' => array( 'type' => 'pricing' ),
				),
			),
			'widgets'    => array(
				array(
					'type'    => 'heading',
					'content' => 'Reduce warehouse costs by 30%',
				),
				array(
					'type'    => 'text-editor',
					'content' => 'Only platform built for African logistics teams. Free trial available.',
				),
			),
			'ux_scores'  => array(
				'trust_score' => 45,
			),
		);
	}

	/**
	 * @return void
	 */
	public function test_extracts_promise_and_uvp() {
		$result = RWGA_Messaging_Analyzer::analyze( $this->sample_payload(), 0 );
		$this->assertStringContainsString( '30%', (string) ( $result['promise']['text'] ?? '' ) );
		$this->assertNotEmpty( $result['uvp']['text'] ?? '' );
		$this->assertSame( 'warehouse_operations_manager', $result['audience']['persona'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_clarity_scores_are_bounded() {
		$result  = RWGA_Messaging_Analyzer::analyze( $this->sample_payload(), 0 );
		$clarity = $result['clarity'] ?? array();
		foreach ( array( 'clarity', 'specificity', 'differentiation', 'credibility', 'overall' ) as $key ) {
			$this->assertArrayHasKey( $key, $clarity );
			$this->assertGreaterThanOrEqual( 0, (int) $clarity[ $key ] );
			$this->assertLessThanOrEqual( 100, (int) $clarity[ $key ] );
		}
	}

	/**
	 * @return void
	 */
	public function test_detects_pricing_without_trust_objection() {
		$result = RWGA_Messaging_Analyzer::analyze( $this->sample_payload(), 0 );
		$keys   = array();
		foreach ( $result['objections'] ?? array() as $obj ) {
			if ( is_array( $obj ) && ! empty( $obj['key'] ) ) {
				$keys[] = (string) $obj['key'];
			}
		}
		$this->assertContains( 'price_justification_missing', $keys );
	}

	/**
	 * @return void
	 */
	public function test_to_insight_rows_includes_promise() {
		$result = RWGA_Messaging_Analyzer::analyze( $this->sample_payload(), 0 );
		$rows   = RWGA_Messaging_Analyzer::to_insight_rows( $result, 'hash1', 'snap1' );
		$keys   = array_column( $rows, 'insight_key' );
		$this->assertContains( 'primary_promise', $keys );
		$this->assertContains( 'messaging_clarity', $keys );
	}
}
