<?php
/**
 * Tests for planner → portable condition conversion.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/services/planner/class-rwga-planner-condition-polarity-resolver.php';
require_once dirname( __DIR__, 2 ) . '/includes/services/executor/class-rwga-plan-condition-converter.php';

class RWGAPlanConditionConverterTest extends TestCase {

	public function test_free_delivery_popup_exports_page_type_and_or_group() {
		$conditions = array(
			'include' => array(
				'countries'         => array( 'IE', 'GB' ),
				'devices'           => array( 'desktop' ),
				'pageTypes'         => array( 'product' ),
				'utm'               => array(
					array( 'key' => 'utm_source', 'value' => 'google' ),
					array( 'key' => 'utm_medium', 'value' => 'cpc' ),
				),
				'condition_groups'  => array(
					array(
						'label'      => 'Google Ads or URL contains /winter-sale',
						'logic'      => 'OR',
						'conditions' => array(
							array(
								'type'         => 'traffic_source',
								'value'        => 'google_ads',
								'mapping_key'  => 'utm_source_google_and_medium_cpc',
								'label'        => 'Google Ads standard UTM',
							),
							array(
								'type'     => 'url',
								'value'    => '/winter-sale',
								'label'    => 'URL contains /winter-sale',
							),
						),
					),
				),
			),
			'exclude' => array(
				'countries' => array( 'FR', 'DE' ),
			),
		);

		$converted = RWGA_Plan_Condition_Converter::convert( $conditions, array( 'visibility' => 'only_show' ) );
		$types     = array_column( $converted['conditions'], 'type' );

		$this->assertContains( 'page_type', $types );
		$this->assertContains( 'condition_group', $types );
		$this->assertNotContains( 'utm_source', $types, 'Flat UTM rows must not flatten OR traffic groups.' );
		$this->assertNotContains( 'utm_medium', $types );

		$page = null;
		$group = null;
		foreach ( $converted['conditions'] as $row ) {
			if ( 'page_type' === ( $row['type'] ?? '' ) ) {
				$page = $row;
			}
			if ( 'condition_group' === ( $row['type'] ?? '' ) ) {
				$group = $row;
			}
		}
		$this->assertSame( array( 'product' ), $page['value'] ?? null );
		$this->assertIsArray( $group );
		$this->assertSame( 'any', $group['value']['match'] ?? '' );
		$this->assertCount( 2, $group['value']['branches'] ?? array() );
		$branches = $group['value']['branches'];
		$this->assertSame( 'all', $branches[0]['match'] ?? '' );
		$this->assertCount( 2, $branches[0]['conditions'] ?? array() );
		$this->assertSame( 'request_uri', $branches[1]['conditions'][0]['type'] ?? '' );
		$this->assertSame( '/winter-sale', $branches[1]['conditions'][0]['value'][0] ?? '' );
	}
}
