<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Knowledge graph tests.
 */
class RWGAKnowledgeGraphTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Knowledge_Graph', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-knowledge-graph.php';
		}
	}

	/**
	 * @return void
	 */
	public function test_seed_benchmarks_are_anonymous() {
		$rows = RWGA_Knowledge_Graph::seed_benchmarks();
		$this->assertNotEmpty( $rows );
		foreach ( $rows as $row ) {
			$this->assertArrayHasKey( 'finding', $row );
			$this->assertArrayHasKey( 'confidence', $row );
			$this->assertArrayNotHasKey( 'page_id', $row );
			$this->assertArrayNotHasKey( 'site_url', $row );
		}
	}

	/**
	 * @return void
	 */
	public function test_fetch_relevant_prefers_matching_page_type() {
		$rows = RWGA_Knowledge_Graph::fetch_relevant( 'hosting', 'pricing', 'EU', 3 );
		$this->assertNotEmpty( $rows );
		$this->assertSame( 'pricing_testimonials_above_table', $rows[0]['id'] );
	}

	/**
	 * @return void
	 */
	public function test_benchmark_context_shape() {
		$ctx = RWGA_Knowledge_Graph::benchmark_context(
			array(
				'page_type' => 'homepage',
				'industry'  => 'b2b_saas',
			)
		);
		$this->assertSame( 1, $ctx['schema_version'] );
		$this->assertArrayHasKey( 'benchmarks', $ctx );
		$this->assertNotEmpty( $ctx['benchmarks'] );
	}
}
