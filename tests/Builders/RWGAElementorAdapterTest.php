<?php

use PHPUnit\Framework\TestCase;

/**
 * Elementor adapter tests.
 */
class RWGAElementorAdapterTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rwga_test_posts']     = array();
		$GLOBALS['rwga_test_post_meta'] = array();
	}

	/**
	 * @return void
	 */
	public function test_parses_valid_elementor_data() {
		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor-sample.json' );
		$this->seed_elementor_post( 101, $json );

		$adapter = new RWGA_Elementor_Adapter();
		$ctx     = $adapter->extract_page_context( 101 );

		$this->assertSame( 'elementor', $ctx['builder'] );
		$this->assertTrue( $ctx['raw_builder_meta_available'] );
		$this->assertGreaterThanOrEqual( 2, count( $ctx['sections'] ) );
		$this->assertNotEmpty( $ctx['widgets'] );
	}

	/**
	 * @return void
	 */
	public function test_extracts_headings_buttons_forms_images() {
		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor-sample.json' );
		$this->seed_elementor_post( 102, $json );
		$widgets = ( new RWGA_Elementor_Adapter() )->extract_widgets( 102 );
		$types   = array_column( $widgets, 'type' );

		$this->assertContains( 'heading', $types );
		$this->assertContains( 'button', $types );
		$this->assertContains( 'form', $types );
		$this->assertContains( 'image', $types );
	}

	/**
	 * @return void
	 */
	public function test_detects_cta_widgets() {
		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor-sample.json' );
		$this->seed_elementor_post( 103, $json );
		$ctx = ( new RWGA_Elementor_Adapter() )->extract_page_context( 103 );
		$this->assertNotEmpty( $ctx['ctas'] );
		$this->assertSame( 'btn1', $ctx['ctas'][0]['widget_id'] );
	}

	/**
	 * @return void
	 */
	public function test_handles_empty_json() {
		$this->seed_elementor_post( 104, '' );
		$this->assertFalse( RWGA_Elementor_Adapter::post_has_elementor_data( 104 ) );
		$tree = ( new RWGA_Elementor_Adapter() )->get_element_tree( 104 );
		$this->assertSame( array(), $tree );
	}

	/**
	 * @return void
	 */
	public function test_handles_invalid_json() {
		$this->seed_elementor_post( 105, '{not-json' );
		$this->assertFalse( RWGA_Elementor_Adapter::post_has_elementor_data( 105 ) );
	}

	/**
	 * @return void
	 */
	public function test_nested_containers() {
		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/elementor-sample.json' );
		$this->seed_elementor_post( 106, $json );
		$sections = ( new RWGA_Elementor_Adapter() )->extract_sections( 106 );
		$types    = array_column( $sections, 'type' );
		$this->assertContains( 'container', $types );
	}

	/**
	 * @param int    $id   Post ID.
	 * @param string $json Elementor JSON.
	 * @return void
	 */
	private function seed_elementor_post( $id, $json ) {
		$post                = new WP_Post();
		$post->ID            = $id;
		$post->post_title    = 'Test Page';
		$post->post_content  = '';
		$post->post_type     = 'page';
		$GLOBALS['rwga_test_posts'][ $id ] = $post;
		$GLOBALS['rwga_test_post_meta'][ $id ] = array(
			'_elementor_data' => $json,
		);
	}
}
