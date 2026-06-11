<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Context builder and payload guard tests.
 */
class RWGAContextBuilderTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Builder_Normalize', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/builders/class-rwga-builder-normalize.php';
		}
		if ( ! class_exists( 'RWGA_Payload_Guard', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-payload-guard.php';
		}
		if ( ! class_exists( 'RWGA_Context_Builder', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-context-builder.php';
		}
	}

	/**
	 * @return void
	 */
	public function test_payload_guard_strips_forbidden_keys() {
		$dirty = array(
			'page_id'         => 10,
			'_elementor_data' => '{"secret":"tree"}',
			'post_content'    => '<p>Full html body</p>',
			'intelligence'    => array( 'uvp' => 'Test value' ),
		);
		$clean = RWGA_Payload_Guard::sanitize( $dirty );
		$this->assertArrayNotHasKey( '_elementor_data', $clean );
		$this->assertArrayNotHasKey( 'post_content', $clean );
		$this->assertSame( 10, $clean['page_id'] );
		$this->assertArrayHasKey( 'intelligence', $clean );
	}

	/**
	 * @return void
	 */
	public function test_payload_guard_audit_lists_removed_keys() {
		$before = array( 'page_id' => 1, 'ai_page_context' => array( 'widgets' => array() ) );
		$after  = RWGA_Payload_Guard::sanitize( $before );
		$audit  = RWGA_Payload_Guard::audit_exclusions( $before, $after );
		$this->assertContains( 'ai_page_context', $audit );
	}

	/**
	 * @return void
	 */
	public function test_build_ux_analysis_includes_intelligence_shape() {
		$bundle = RWGA_Context_Builder::build(
			'ux_analysis',
			array(
				'page_id'        => 0,
				'analysis_focus' => 'messaging',
			)
		);
		$this->assertSame( 'ux_analysis', $bundle['workflow_key'] ?? '' );
		$this->assertSame( RWGA_Context_Builder::VERSION, $bundle['context_version'] ?? '' );
		$this->assertArrayHasKey( 'relationships', $bundle );
		$this->assertArrayHasKey( '_payload_audit', $bundle );
	}

	/**
	 * @return void
	 */
	public function test_for_remote_api_strips_audit_metadata() {
		$bundle = RWGA_Context_Builder::build( 'ux_analysis', array( 'page_id' => 0 ) );
		$remote = RWGA_Context_Builder::for_remote_api( $bundle );
		$this->assertArrayNotHasKey( '_payload_audit', $remote );
	}

	/**
	 * @return void
	 */
	public function test_compact_messaging_via_page_bundle_empty_page() {
		$bundle = RWGA_Context_Builder::page_intelligence_bundle( 0 );
		$this->assertSame( array(), $bundle );
	}
}
