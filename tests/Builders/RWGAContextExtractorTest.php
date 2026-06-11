<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Context extractor tests.
 */
class RWGAContextExtractorTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rwga_test_posts']     = array();
		$GLOBALS['rwga_test_post_meta'] = array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function landing_payload() {
		return array(
			'builder'   => 'elementor',
			'page_type' => 'landing_page',
			'sections'  => array(
				array(
					'id'             => 'sec1',
					'classification' => array( 'type' => 'hero' ),
					'heading'        => 'Ship faster with automation',
					'has_cta'        => true,
				),
				array(
					'id'             => 'sec2',
					'classification' => array( 'type' => 'features' ),
					'heading'        => 'Why teams choose us',
				),
				array(
					'id'             => 'sec3',
					'classification' => array( 'type' => 'testimonials' ),
					'heading'        => 'Customer stories',
					'has_cta'        => false,
				),
				array(
					'id'             => 'sec4',
					'classification' => array( 'type' => 'cta' ),
					'heading'        => 'Start your trial',
					'has_cta'        => true,
				),
			),
			'widgets' => array(
				array(
					'id'         => 'h1',
					'type'       => 'heading',
					'section_id' => 'sec1',
					'content'    => 'Ship faster with automation',
					'settings'   => array( 'header_size' => 'h1' ),
				),
				array(
					'id'         => 'btn1',
					'type'       => 'button',
					'section_id' => 'sec1',
					'content'    => 'Get Started Free',
					'is_cta'     => true,
				),
			),
			'ctas' => array(
				array( 'label' => 'Get Started Free' ),
				array( 'label' => 'Book demo' ),
			),
			'forms' => array(),
		);
	}

	/**
	 * @return void
	 */
	public function test_elementor_extractor_builds_narrative_beats() {
		$this->seed_elementor_meta( 201 );
		$result = ( new RWGA_Elementor_Context_Extractor() )->extract( 201, $this->landing_payload() );
		$roles  = array_column( $result['narrative_beats'] ?? array(), 'role' );
		$this->assertSame( array( 'hero', 'features', 'testimonials', 'cta' ), $roles );
		$this->assertSame( 'lead_generation', $result['page_goal'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_persuasion_summary_extracts_headline_and_cta() {
		$this->seed_elementor_meta( 202 );
		$result = ( new RWGA_Elementor_Context_Extractor() )->extract( 202, $this->landing_payload() );
		$this->assertSame( 'Ship faster with automation', $result['persuasion']['headline'] ?? '' );
		$this->assertSame( 'Get Started Free', $result['persuasion']['primary_cta'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_structure_gaps_when_proof_missing_before_cta() {
		$payload = $this->landing_payload();
		$payload['sections'] = array(
			array(
				'id'             => 'sec1',
				'classification' => array( 'type' => 'hero' ),
				'has_cta'        => true,
			),
			array(
				'id'             => 'sec2',
				'classification' => array( 'type' => 'pricing' ),
				'has_cta'        => true,
			),
		);
		$result = ( new RWGA_Elementor_Context_Extractor() )->extract( 203, $payload );
		$this->assertContains( 'proof_before_cta_missing', $result['structure_gaps'] ?? array() );
		$this->assertContains( 'missing_trust_signals', $result['structure_gaps'] ?? array() );
	}

	/**
	 * @return void
	 */
	public function test_registry_resolves_elementor_extractor() {
		$this->seed_elementor_meta( 204 );
		$result = RWGA_Context_Extractor_Registry::extract( 204, $this->landing_payload() );
		$this->assertSame( 'elementor', $result['builder'] ?? '' );
		$this->assertSame( 'page', $result['builder_meta']['template_type'] ?? '' );
	}

	/**
	 * @return void
	 */
	public function test_compact_for_api_omits_widget_inventory() {
		$this->seed_elementor_meta( 205 );
		$full    = ( new RWGA_Elementor_Context_Extractor() )->extract( 205, $this->landing_payload() );
		$compact = RWGA_Context_Extractor_Base::compact_for_api( $full );
		$this->assertArrayNotHasKey( 'widgets', $compact );
		$this->assertArrayHasKey( 'narrative_beats', $compact );
		$this->assertArrayHasKey( 'structure_gaps', $compact );
	}

	/**
	 * @return void
	 */
	public function test_gutenberg_extractor_reads_template_meta() {
		$GLOBALS['rwga_test_post_meta'][ 206 ] = array(
			'_wp_page_template' => 'template-full-width.php',
		);
		$payload = $this->landing_payload();
		$payload['builder'] = 'gutenberg';
		$result = ( new RWGA_Gutenberg_Context_Extractor() )->extract( 206, $payload );
		$this->assertSame( 'template-full-widthphp', $result['builder_meta']['template'] ?? '' );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function seed_elementor_meta( $post_id ) {
		$GLOBALS['rwga_test_post_meta'][ $post_id ] = array(
			'_elementor_template_type' => 'page',
			'_elementor_edit_mode'     => 'builder',
			'_elementor_version'       => '3.24.0',
		);
	}
}
