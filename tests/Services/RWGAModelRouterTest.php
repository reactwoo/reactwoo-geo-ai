<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Model router tests.
 */
class RWGAModelRouterTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Model_Router', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-model-router.php';
		}
	}

	/**
	 * @return void
	 */
	public function test_ux_workflows_use_premium_tier() {
		$route = RWGA_Model_Router::resolve( 'ux_analysis' );
		$this->assertSame( 'premium', $route['tier'] );
		$this->assertSame( 'llm', $route['engine'] );
	}

	/**
	 * @return void
	 */
	public function test_debug_workflows_use_deterministic_tier() {
		$route = RWGA_Model_Router::resolve( 'rule_debug' );
		$this->assertSame( 'deterministic', $route['tier'] );
		$this->assertSame( 'internal', $route['provider_hint'] );
	}

	/**
	 * @return void
	 */
	public function test_for_api_strips_internal_keys() {
		$route = RWGA_Model_Router::resolve( 'ux_recommend' );
		$api   = RWGA_Model_Router::for_api( $route );
		$this->assertArrayHasKey( 'tier', $api );
		$this->assertArrayHasKey( 'model_hint', $api );
		$this->assertArrayNotHasKey( 'workflow_key', $api );
	}
}
