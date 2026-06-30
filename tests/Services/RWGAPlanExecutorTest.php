<?php
/**
 * Tests for the multi-action plan executor pipeline:
 * resolution applier, condition converter, and plan executor.
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $id, $context = 'display' ) {
		unset( $context );
		return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
	}
}

if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema' ) ) {
	/**
	 * Minimal schema stub: keeps conditions, returns null when a rule has none.
	 */
	class RWGC_Targeting_Rule_Set_Schema {
		const VERSION = 2;

		public static function sanitize( $raw ) {
			if ( ! is_array( $raw ) || empty( $raw['rules'] ) ) {
				return null;
			}
			$rules = array();
			foreach ( $raw['rules'] as $rule ) {
				if ( empty( $rule['conditions'] ) ) {
					continue;
				}
				$rules[] = $rule;
			}
			if ( empty( $rules ) ) {
				return null;
			}
			$raw['rules'] = $rules;
			return $raw;
		}
	}
}

if ( ! class_exists( 'RWGC_Visibility_Rule_Repository' ) ) {
	/**
	 * In-memory repository stub.
	 */
	class RWGC_Visibility_Rule_Repository {
		/** @var int */
		public static $next_id = 100;
		/** @var array<int,array<string,mixed>> */
		public static $saved = array();

		public static function save( $title, $status, $portable_json, $post_id = 0 ) {
			unset( $post_id );
			$id = self::$next_id++;
			self::$saved[ $id ] = array(
				'title'    => $title,
				'status'   => $status,
				'portable' => $portable_json,
			);
			return $id;
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/services/planner/class-rwga-geo-action-types.php';
require_once dirname( __DIR__, 2 ) . '/includes/services/planner/class-rwga-planner-condition-polarity-resolver.php';
require_once dirname( __DIR__, 2 ) . '/includes/services/executor/class-rwga-card-resolution-applier.php';
require_once dirname( __DIR__, 2 ) . '/includes/services/executor/class-rwga-plan-condition-converter.php';
require_once dirname( __DIR__, 2 ) . '/includes/services/executor/class-rwga-plan-executor.php';

class RWGAPlanExecutorTest extends TestCase {

	protected function setUp(): void {
		RWGC_Visibility_Rule_Repository::$saved   = array();
		RWGC_Visibility_Rule_Repository::$next_id = 100;
	}

	public function test_applier_resolves_choose_ignore_and_remove() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
				'target'     => array( 'type' => 'category_page', 'label' => 'trainers category page' ),
				'campaign'   => array( 'label' => 'Spring Promo campaign' ),
				'unresolved' => array(
					'campaigns' => array( array( 'raw' => 'Spring Promo campaign', 'status' => 'not_found' ) ),
					'audiences' => array( array( 'raw' => 'VIP Buyers', 'status' => 'not_found' ) ),
				),
				'conditions' => array( 'include' => array(), 'exclude' => array() ),
			),
			array(
				'type'   => RWGA_Geo_Action_Types::CREATE_RULE,
				'target' => array( 'type' => 'popup', 'label' => 'loyalty popup' ),
			),
		);

		$resolutions = array(
			array( 'card' => 0, 'field' => 'target', 'raw' => 'trainers category page', 'action' => 'choose', 'id' => 'cat_12', 'label' => 'Shoes category page' ),
			array( 'card' => 0, 'field' => 'campaign', 'raw' => 'Spring Promo campaign', 'action' => 'ignore' ),
			array( 'card' => 0, 'field' => 'audience', 'raw' => 'VIP Buyers', 'action' => 'choose', 'id' => 'aud_3', 'label' => 'VIP Customers' ),
			array( 'card' => 1, 'action' => 'remove_action' ),
		);

		$out = RWGA_Card_Resolution_Applier::apply( $actions, $resolutions );

		$this->assertCount( 1, $out, 'Removed action should be dropped.' );
		$first = $out[0];
		$this->assertSame( 'cat_12', $first['target']['user_resolved']['id'] );
		$this->assertArrayNotHasKey( 'campaign', $first, 'Ignored campaign should be unset.' );
		$this->assertSame( array(), $first['unresolved']['campaigns'] );
		$this->assertSame( array(), $first['unresolved']['audiences'] );
		$this->assertSame( 'VIP Customers', $first['conditions']['include']['audiences'][0]['name'] );
	}

	public function test_applier_resolves_location_country_region_and_any_audience() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'target'     => array( 'type' => 'page', 'label' => 'Home page' ),
				'unresolved' => array(
					'locations' => array( array( 'raw' => 'England', 'status' => 'needs_resolution' ) ),
					'audiences' => array( array( 'raw' => 'audience matches any', 'status' => 'audience_any' ) ),
				),
				'conditions' => array( 'include' => array( 'weather' => array( 'sunny' ) ), 'exclude' => array() ),
			),
		);

		// Choose England region; treat "audience matches any" as any audience (ignore).
		$out = RWGA_Card_Resolution_Applier::apply(
			$actions,
			array(
				array( 'card' => 0, 'field' => 'location', 'raw' => 'England', 'action' => 'choose', 'id' => 'region:GB-ENG' ),
				array( 'card' => 0, 'field' => 'audience', 'raw' => 'audience matches any', 'action' => 'ignore' ),
				array( 'card' => 0, 'field' => 'logic', 'action' => 'set', 'id' => 'AND' ),
			)
		);

		$first = $out[0];
		$this->assertSame( array( 'GB-ENG' ), $first['conditions']['include']['regions'] );
		$this->assertSame( array(), $first['unresolved']['locations'] );
		$this->assertSame( array(), $first['unresolved']['audiences'] );
		$this->assertSame( 'all', $first['condition_match'] );
		$this->assertSame( array( 'sunny' ), $first['conditions']['include']['weather'] );
	}

	public function test_applier_resolves_location_to_country() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'target'     => array( 'type' => 'page', 'label' => 'Home page' ),
				'unresolved' => array( 'locations' => array( array( 'raw' => 'England', 'status' => 'needs_resolution' ) ) ),
				'conditions' => array( 'include' => array(), 'exclude' => array() ),
			),
		);
		$out = RWGA_Card_Resolution_Applier::apply(
			$actions,
			array( array( 'card' => 0, 'field' => 'location', 'raw' => 'England', 'action' => 'choose', 'id' => 'country:GB' ) )
		);
		$this->assertSame( array( 'GB' ), $out[0]['conditions']['include']['countries'] );
	}

	public function test_condition_converter_maps_include_and_exclude() {
		$conditions = array(
			'include' => array(
				'countries' => array( 'ES', 'PT' ),
				'audiences' => array( array( 'name' => 'VIP Customers' ) ),
			),
			'exclude' => array(
				'utm' => array( array( 'key' => 'utm_source', 'value' => 'email' ) ),
			),
		);

		$converted = RWGA_Plan_Condition_Converter::convert( $conditions, array( 'visibility' => 'only_show' ) );

		$this->assertSame( 'show_if', $converted['mode'] );
		$types = array_column( $converted['conditions'], 'type' );
		$this->assertContains( 'country', $types );
		$this->assertContains( 'audience', $types );
		$this->assertContains( 'utm_source', $types );

		foreach ( $converted['conditions'] as $row ) {
			if ( 'utm_source' === $row['type'] ) {
				$this->assertSame( 'is_not', $row['operator'] );
				$this->assertSame( array( 'email' ), $row['value'] );
			}
			if ( 'country' === $row['type'] ) {
				$this->assertSame( 'in', $row['operator'] );
			}
		}
	}

	public function test_condition_converter_hide_mode() {
		$converted = RWGA_Plan_Condition_Converter::convert(
			array( 'include' => array( 'countries' => array( 'DE' ) ), 'exclude' => array() ),
			array( 'visibility' => 'hide' )
		);
		$this->assertSame( 'hide_if', $converted['mode'] );
	}

	public function test_executor_creates_rules_manual_and_preview() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'target'     => array( 'type' => 'popup', 'label' => 'loyalty popup' ),
				'operation'  => array( 'visibility' => 'hide' ),
				'conditions' => array( 'include' => array( 'countries' => array( 'DE' ) ), 'exclude' => array() ),
			),
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
				'target'     => array( 'type' => 'category_page', 'label' => 'trainers category page' ),
				'operation'  => array( 'visibility' => 'only_show' ),
				'conditions' => array( 'include' => array( 'countries' => array( 'IT' ), 'devices' => array( 'mobile' ) ), 'exclude' => array() ),
			),
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_TEST,
				'target'     => array( 'type' => 'category_page', 'label' => 'trainers category page' ),
				'conditions' => array( 'include' => array( 'countries' => array( 'FR' ) ), 'exclude' => array() ),
			),
		);

		$result = RWGA_Plan_Executor::execute_plan( $actions );

		$this->assertTrue( $result['executed'] );
		$this->assertCount( 2, $result['created_rules'], 'Rule + variant rule should be created.' );
		$this->assertCount( 1, $result['manual_steps'], 'Variant action needs a manual step.' );
		$this->assertCount( 1, $result['preview_only'], 'Test action is preview only.' );
		$this->assertCount( 2, RWGC_Visibility_Rule_Repository::$saved );
		foreach ( RWGC_Visibility_Rule_Repository::$saved as $row ) {
			$this->assertIsString( $row['portable'] );
			$this->assertStringStartsWith( '{', $row['portable'] );
		}
	}

	public function test_executor_uses_execution_context_without_fatal() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'target'     => array(
					'type'          => 'popup',
					'label'         => 'Free Delivery',
					'user_resolved' => array(
						'id'   => '99',
						'name' => 'Free Delivery',
						'type' => 'popup',
					),
				),
				'operation'  => array( 'visibility' => 'only_show' ),
				'conditions' => array(
					'include' => array( 'countries' => array( 'IE', 'GB' ) ),
					'exclude' => array( 'countries' => array( 'FR', 'DE' ) ),
				),
			),
		);

		$result = RWGA_Plan_Executor::execute_plan(
			$actions,
			array(
				'proposal_id'           => 'proposal_test_1',
				'source_phrase'         => 'Create a rule for the Free Delivery popup.',
				'interpretation_source' => 'local_parser',
			)
		);

		$this->assertCount( 1, $result['created_rules'] );
		$this->assertCount( 1, RWGC_Visibility_Rule_Repository::$saved );
	}

	public function test_executor_reports_needs_attention_when_no_conditions() {
		$actions = array(
			array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'target'     => array( 'type' => 'page', 'label' => 'home' ),
				'operation'  => array( 'visibility' => 'only_show' ),
				'conditions' => array( 'include' => array(), 'exclude' => array() ),
			),
		);

		$result = RWGA_Plan_Executor::execute_plan( $actions );

		$this->assertSame( array(), $result['created_rules'] );
		$this->assertCount( 1, $result['needs_attention'] );
	}
}
