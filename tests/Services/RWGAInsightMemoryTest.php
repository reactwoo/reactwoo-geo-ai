<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Insight memory tests.
 */
class RWGAInsightMemoryTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Payload_Guard', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-payload-guard.php';
		}
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/db/class-rwga-db.php';
			require_once dirname( __DIR__, 2 ) . '/includes/db/class-rwga-db-local-intelligence.php';
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-local-intelligence.php';
		}
		if ( ! class_exists( 'RWGA_Insight_Memory', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-insight-memory.php';
		}
	}

	/**
	 * @return void
	 */
	public function test_store_and_lookup_round_trip() {
		$workflow = 'ux_analysis';
		$payload  = array( 'analysis_focus' => 'messaging', 'page_type' => 'homepage' );
		$route    = array(
			'model_hint'     => 'gpt-4o-mini',
			'prompt_version' => '1.0.0',
		);
		$parsed   = array(
			'remote_run_id'   => 'run_test_1',
			'engine_response' => array( 'summary' => 'Cached insight', 'score' => 72 ),
		);

		RWGA_Insight_Memory::store( $workflow, $payload, $route, $parsed );
		$hit = RWGA_Insight_Memory::lookup( $workflow, $payload, $route );

		$this->assertIsArray( $hit );
		$this->assertTrue( $hit['cache_hit'] );
		$this->assertSame( 'Cached insight', $hit['engine_response']['summary'] );
	}

	/**
	 * @return void
	 */
	public function test_input_key_changes_with_workflow() {
		$payload = array( 'analysis_focus' => 'layout' );
		$route   = array( 'model_hint' => 'gpt-4o-mini', 'prompt_version' => '1.0.0' );
		$a       = RWGA_Insight_Memory::input_key( 'ux_analysis', $payload, $route );
		$b       = RWGA_Insight_Memory::input_key( 'ux_recommend', $payload, $route );
		$this->assertNotSame( $a, $b );
	}
}
