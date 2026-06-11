<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Local intelligence helpers.
 */
class RWGALocalIntelligenceTest extends TestCase {

	/**
	 * @return void
	 */
	public function test_hash_json_is_stable() {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-local-intelligence.php';
		}
		$data = array( 'builder' => 'elementor', 'score' => 71 );
		$h1   = RWGA_Local_Intelligence::hash_json( $data );
		$h2   = RWGA_Local_Intelligence::hash_json( $data );
		$this->assertSame( $h1, $h2 );
		$this->assertSame( 64, strlen( $h1 ) );
	}

	/**
	 * @return void
	 */
	public function test_build_run_cache_key_includes_workflow() {
		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-local-intelligence.php';
		}
		$a = RWGA_Local_Intelligence::build_run_cache_key( 'ux_analysis', 'snap1', 'ent1', '1.0.0', 'gpt-4o-mini', array() );
		$b = RWGA_Local_Intelligence::build_run_cache_key( 'ux_recommend', 'snap1', 'ent1', '1.0.0', 'gpt-4o-mini', array() );
		$this->assertNotSame( $a, $b );
	}
}
