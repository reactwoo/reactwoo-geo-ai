<?php
/**
 * @package ReactWoo_Geo_AI
 */

use PHPUnit\Framework\TestCase;

/**
 * Relationship graph tests.
 */
class RWGARelationshipGraphTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		if ( ! class_exists( 'RWGA_Builder_Normalize', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/builders/class-rwga-builder-normalize.php';
		}
		if ( ! class_exists( 'RWGA_Relationship_Graph', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-relationship-graph.php';
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function sample_snapshot() {
		return array(
			'schema_version'   => 1,
			'snapshot_hash'    => 'snap123',
			'generated_at_gmt' => '2026-06-11T12:00:00+00:00',
			'rules'            => array(
				array(
					'rule_id' => '12',
					'title'   => 'US visitors',
				),
			),
			'variants'         => array(
				array(
					'master_page_id'   => '10',
					'master_title'     => 'Home',
					'variant_page_id'  => '11',
					'variant_title'    => 'Home US',
				),
			),
			'popups'           => array(
				array(
					'popup_id' => '5',
					'title'    => 'Exit intent',
				),
			),
			'relationships'    => array(
				array(
					'from_type' => 'rule',
					'from_id'   => '12',
					'to_type'   => 'variant',
					'to_id'     => '11',
					'type'      => 'targets',
				),
			),
			'geo_optimise'     => array(
				'experiments' => array(
					array(
						'experiment_id'  => '99',
						'name'           => 'Hero CTA test',
						'source_page_id' => '10',
					),
				),
			),
		);
	}

	/**
	 * @return void
	 */
	public function test_build_from_snapshot_creates_nodes_and_edges() {
		$graph = RWGA_Relationship_Graph::build_from_snapshot( $this->sample_snapshot() );
		$this->assertSame( 'snap123', $graph['snapshot_hash'] ?? '' );
		$this->assertGreaterThanOrEqual( 4, (int) ( $graph['counts']['nodes'] ?? 0 ) );
		$this->assertGreaterThanOrEqual( 2, (int) ( $graph['counts']['edges'] ?? 0 ) );

		$types = array_column( $graph['nodes'] ?? array(), 'type' );
		$this->assertContains( 'rule', $types );
		$this->assertContains( 'page', $types );
		$this->assertContains( 'popup', $types );
		$this->assertContains( 'experiment', $types );
	}

	/**
	 * @return void
	 */
	public function test_variant_of_edge_between_pages() {
		$graph = RWGA_Relationship_Graph::build_from_snapshot( $this->sample_snapshot() );
		$types = array_column( $graph['edges'] ?? array(), 'type' );
		$this->assertContains( 'variant_of', $types );
		$this->assertContains( 'tests', $types );
		$this->assertContains( 'targets', $types );
	}

	/**
	 * @return void
	 */
	public function test_extend_with_local_intelligence_adds_page_meta() {
		$base = RWGA_Relationship_Graph::build_from_snapshot( $this->sample_snapshot() );
		$graph = RWGA_Relationship_Graph::extend_with_local_intelligence( $base );
		$this->assertArrayHasKey( 'counts', $graph );
		$this->assertArrayHasKey( 'page_intelligence', $graph['counts'] );
	}

	/**
	 * @return void
	 */
	public function test_compact_for_api_limits_nodes() {
		$graph = RWGA_Relationship_Graph::build_from_snapshot( $this->sample_snapshot() );
		$compact = RWGA_Relationship_Graph::compact_for_api( $graph );
		$this->assertLessThanOrEqual( 80, count( $compact['nodes'] ?? array() ) );
		$this->assertArrayHasKey( 'counts', $compact );
	}
}
