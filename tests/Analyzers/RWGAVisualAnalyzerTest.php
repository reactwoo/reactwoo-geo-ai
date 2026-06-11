<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Visual analyzer tests.
 */
class RWGAVisualAnalyzerTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Builder_Normalize', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/builders/class-rwga-builder-normalize.php';
		}
		if ( ! class_exists( 'RWGA_Visual_Analyzer', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/analyzers/class-rwga-visual-analyzer.php';
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function landing_payload() {
		return array(
			'sections' => array(
				array(
					'id'             => 'hero1',
					'classification' => array( 'type' => 'hero' ),
					'has_cta'        => true,
					'widget_count'   => 4,
				),
				array(
					'classification' => array( 'type' => 'features' ),
					'has_cta'        => false,
				),
				array(
					'classification' => array( 'type' => 'cta' ),
					'has_cta'        => true,
				),
			),
			'ctas' => array(
				array( 'label' => 'Get Started Free' ),
				array( 'label' => 'Book a demo' ),
			),
			'widgets' => array(
				array(
					'is_cta'     => true,
					'section_id' => 'hero1',
					'settings'   => array(
						'background_color' => '#e63946',
						'background_role'  => 'primary_action',
					),
				),
				array(
					'is_cta'   => true,
					'settings' => array(
						'background_color' => '#457b9d',
						'background_role'  => 'trust',
					),
				),
			),
			'ux_scores' => array(
				'cta_score' => 70,
			),
		);
	}

	/**
	 * @return void
	 */
	public function test_attention_flow_stages() {
		$result = RWGA_Visual_Analyzer::analyze( $this->landing_payload() );
		$stages = $result['attention_flow']['stages'] ?? array();
		$this->assertSame( array( 'hero', 'benefits', 'cta' ), $stages );
	}

	/**
	 * @return void
	 */
	public function test_cta_emphasis_scores() {
		$result = RWGA_Visual_Analyzer::analyze( $this->landing_payload() );
		$cta    = $result['cta_emphasis'] ?? array();
		$this->assertGreaterThanOrEqual( 70, (int) ( $cta['primary_cta_emphasis'] ?? 0 ) );
		$this->assertGreaterThan( 0, (int) ( $cta['secondary_cta_competition'] ?? 0 ) );
	}

	/**
	 * @return void
	 */
	public function test_colour_roles_from_cta_widgets() {
		$result = RWGA_Visual_Analyzer::analyze( $this->landing_payload() );
		$roles  = $result['colour_roles'] ?? array();
		$this->assertArrayHasKey( 'primary_action', $roles );
		$this->assertArrayHasKey( 'secondary_action', $roles );
	}

	/**
	 * @return void
	 */
	public function test_focus_conflicts_with_many_cta_sections() {
		$payload = $this->landing_payload();
		$payload['sections'][] = array(
			'classification' => array( 'type' => 'pricing' ),
			'has_cta'        => true,
		);
		$payload['ctas'][] = array( 'label' => 'Buy now' );
		$payload['ctas'][] = array( 'label' => 'Contact sales' );

		$result = RWGA_Visual_Analyzer::analyze( $payload );
		$this->assertGreaterThanOrEqual( 1, (int) ( $result['visual_competition']['focus_conflicts'] ?? 0 ) );
	}

	/**
	 * @return void
	 */
	public function test_to_insight_rows_not_empty() {
		$result = RWGA_Visual_Analyzer::analyze( $this->landing_payload() );
		$rows   = RWGA_Visual_Analyzer::to_insight_rows( $result, 'h1', 's1' );
		$keys   = array_column( $rows, 'insight_key' );
		$this->assertContains( 'visual_cta_emphasis', $keys );
		$this->assertContains( 'attention_flow', $keys );
	}

	/**
	 * @return void
	 */
	public function test_color_normalize_and_role() {
		$this->assertSame( '#e63946', RWGA_Builder_Normalize::normalize_color_value( '#e63946' ) );
		$this->assertSame( '#ff0000', RWGA_Builder_Normalize::normalize_color_value( 'rgb(255, 0, 0)' ) );
		$this->assertSame( 'primary_action', RWGA_Builder_Normalize::interpret_color_role( '#e63946' ) );
	}
}
