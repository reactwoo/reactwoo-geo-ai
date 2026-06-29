<?php
/**
 * Execute validation for resolved create-rule journeys.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		private $code;
		/** @var string */
		private $message;
		/** @var mixed */
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_data( $code = '' ) {
			unset( $code );
			return $this->data;
		}
	}
}

final class RWGAAssistantExecuteValidationTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! function_exists( '_n' ) ) {
			function _n( $single, $plural, $number, $domain = 'default' ) {
				unset( $domain );
				return (int) $number === 1 ? $single : $plural;
			}
		}
		if ( ! function_exists( 'sanitize_title' ) ) {
			function sanitize_title( $title ) {
				return strtolower( preg_replace( '/[^a-z0-9]+/', '-', (string) $title ) );
			}
		}
		if ( ! function_exists( 'get_posts' ) ) {
			function get_posts( $args = array() ) {
				unset( $args );
				return array();
			}
		}
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'services/class-rwga-compound-condition-interpreter.php';
		require_once $base . 'services/class-rwga-page-reference-resolver.php';
		require_once $base . 'services/class-rwga-variant-group-extractor.php';
		require_once $base . 'services/class-rwga-original-source-targeting-extractor.php';
		require_once $base . 'services/class-rwga-country-rule-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-variant-plan-parser.php';
		require_once $base . 'services/class-rwga-rule-plan-parser.php';
		require_once $base . 'services/class-rwga-segment-condition-extractor.php';
		require_once $base . 'services/class-rwga-site-interpretation-preferences.php';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-rule-plan-parser.php';
		require_once $base . 'services/planner/class-rwga-geo-action-types.php';
		require_once $base . 'services/planner/class-rwga-planner-location-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-region-ambiguity-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-confirmation-instruction-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-condition-polarity-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-audience-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-utm-condition-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-inherited-target-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-synced-entity-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-campaign-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-url-condition-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-narrative-clause-splitter.php';
		require_once $base . 'services/planner/class-rwga-planner-second-version-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-action-clause-splitter.php';
		require_once $base . 'services/planner/class-rwga-planner-action-type-detector.php';
		require_once $base . 'services/planner/class-rwga-planner-target-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-condition-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-variant-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-parent-variant-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-ordinal-variant-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-plan-validator.php';
		require_once $base . 'services/planner/class-rwga-planner-resolve-clarifications.php';
		require_once $base . 'services/planner/class-rwga-planner-confirmation-builder.php';
		require_once $base . 'services/planner/class-rwga-planner-target-registry-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-action-card-builder.php';
		require_once $base . 'services/planner/class-rwga-planner-learned-patterns.php';
		require_once $base . 'services/planner/class-rwga-planner-ai-fallback.php';
		require_once $base . 'services/planner/class-rwga-planner-legacy-adapter.php';
		require_once $base . 'services/planner/class-rwga-geo-assistant-planner.php';
		require_once $base . 'services/executor/class-rwga-card-resolution-applier.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
		require_once $base . 'services/class-rwga-interpretation-status.php';
		require_once $base . 'services/class-rwga-assistant-service.php';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function entities(): array {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_validate_plan_rejects_unresolved_create_rule(): void {
		$input   = 'Create a rule for the Free Delivery popup. Show it only on product pages for desktop visitors from Ireland and the United Kingdom, but do not show it to visitors from France or Germany. Also only trigger it when the visitor came from Google Ads or the URL contains /winter-sale.';
		$plan    = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$actions = (array) ( $plan['actions'] ?? array() );

		$result = RWGA_Assistant_Service::validate_plan_actions_for_execute( $actions, $this->entities() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rwga_plan_unresolved', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertIsArray( $data );
		$this->assertContains( 'Popup target', (array) ( $data['unresolved'] ?? array() ) );
		$this->assertContains( 'Google Ads mapping', (array) ( $data['unresolved'] ?? array() ) );
	}

	public function test_validate_plan_accepts_fully_resolved_create_rule(): void {
		$input   = 'Create a rule for the Free Delivery popup. Show it only on product pages for desktop visitors from Ireland and the United Kingdom, but do not show it to visitors from France or Germany. Also only trigger it when the visitor came from Google Ads or the URL contains /winter-sale.';
		$plan    = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$actions = RWGA_Card_Resolution_Applier::apply(
			(array) ( $plan['actions'] ?? array() ),
			array(
				array(
					'card'   => 0,
					'field'  => 'target',
					'raw'    => 'Free Delivery popup',
					'action' => 'choose',
					'id'     => 'popup_42',
					'label'  => 'Free Delivery popup',
				),
				array(
					'card'   => 0,
					'field'  => 'traffic_source',
					'raw'    => 'Google Ads traffic',
					'action' => 'choose',
					'id'     => 'utm_source_google_and_medium_cpc',
					'label'  => 'Match utm_source=google AND utm_medium=cpc',
				),
			)
		);

		$result = RWGA_Assistant_Service::validate_plan_actions_for_execute( $actions, $this->entities() );
		$this->assertIsArray( $result );
		$this->assertFalse( (bool) ( $result['requires_resolution'] ?? true ) );
		$this->assertSame( RWGA_Planner_Action_Card_Builder::STATUS_READY, (string) ( $result['cards'][0]['status'] ?? '' ) );
	}

	public function test_plan_unresolved_error_includes_structured_details(): void {
		$input   = 'Create a rule for the Free Delivery popup. Show it only on product pages for desktop visitors from Ireland and the United Kingdom, but do not show it to visitors from France or Germany. Also only trigger it when the visitor came from Google Ads or the URL contains /winter-sale.';
		$plan    = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$actions = (array) ( $plan['actions'] ?? array() );

		$result = RWGA_Assistant_Service::validate_plan_actions_for_execute( $actions, $this->entities() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$data = $result->get_error_data();
		$this->assertSame( 'unresolved_fields', $data['code'] ?? '' );
		$this->assertIsArray( $data['unresolved_details'] ?? null );
		$keys = array_column( (array) ( $data['unresolved_details'] ?? array() ), 'key' );
		$this->assertContains( 'google_ads_mapping', $keys );
	}
}
