<?php
/**
 * Geo Assistant multi-action planner tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGAGeoAssistantPlannerTest extends TestCase {

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
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			function wp_generate_uuid4() {
				return 'test-uuid-' . bin2hex( random_bytes( 4 ) );
			}
		}
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-compound-condition-interpreter.php';
		require_once $base . 'services/class-rwga-page-reference-resolver.php';
		require_once $base . 'services/class-rwga-variant-group-extractor.php';
		require_once $base . 'services/class-rwga-original-source-targeting-extractor.php';
		require_once $base . 'services/class-rwga-country-rule-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-variant-plan-parser.php';
		require_once $base . 'services/class-rwga-segment-condition-extractor.php';
		require_once $base . 'services/class-rwga-site-interpretation-preferences.php';
		require_once $base . 'services/planner/class-rwga-geo-action-types.php';
		require_once $base . 'services/planner/class-rwga-planner-location-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-action-clause-splitter.php';
		require_once $base . 'services/planner/class-rwga-planner-action-type-detector.php';
		require_once $base . 'services/planner/class-rwga-planner-target-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-condition-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-variant-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-resolve-clarifications.php';
		require_once $base . 'services/planner/class-rwga-planner-confirmation-builder.php';
		require_once $base . 'services/planner/class-rwga-planner-learned-patterns.php';
		require_once $base . 'services/planner/class-rwga-planner-ai-fallback.php';
		require_once $base . 'services/planner/class-rwga-planner-legacy-adapter.php';
		require_once $base . 'services/planner/class-rwga-geo-assistant-planner.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function entities(): array {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	/**
	 * @param array<string,mixed> $plan Plan.
	 * @return array<int,array<int,string|array<int,string>>>
	 */
	private function action_signatures( array $plan ): array {
		$rows = array();
		foreach ( (array) ( $plan['actions'] ?? array() ) as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$conds    = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
			$regions  = (array) ( $conds['regions'] ?? array() );
			$countries = (array) ( $conds['countries'] ?? array() );
			$location = ! empty( $regions ) ? $regions[0] : ( $countries[0] ?? '' );
			if ( count( $countries ) > 1 && empty( $regions ) ) {
				$location = $countries;
			}
			$rows[] = array(
				(string) ( $action['type'] ?? '' ),
				(string) ( $action['target']['label'] ?? '' ),
				$location,
			);
			if ( ! empty( $conds['devices'] ) ) {
				$rows[ count( $rows ) - 1 ][] = implode( ',', (array) $conds['devices'] );
			}
		}
		return $rows;
	}

	public function test_multi_action_homepage_and_shop_variants(): void {
		$input = 'update the homepage to only show in England and then create two new variants of shop - one will display in England and one will display in Portugal';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 3, $plan['actions'] );
		$signatures = $this->action_signatures( $plan );
		$this->assertSame( 'update_original_targeting', $signatures[0][0] );
		$this->assertSame( 'homepage', $signatures[0][1] );
		$this->assertContains( 'GB-ENG', (array) $signatures[0][2] );
		$this->assertSame( 'create_variant', $signatures[1][0] );
		$this->assertSame( 'shop', $signatures[1][1] );
		$this->assertContains( 'GB-ENG', (array) $signatures[1][2] );
		$this->assertSame( 'create_variant', $signatures[2][0] );
		$this->assertSame( 'PT', $signatures[2][2] );
	}

	public function test_shop_variants_with_homepage_original_page_mismatch(): void {
		$input = 'create an additional two variants of shop page, one variant should display in Portugal and the other in Germany and Russia - update the original homepage to show in England';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 3, $plan['actions'] );
		$this->assertSame( 'page_mismatch', $plan['clarification']['type'] ?? '' );
		$signatures = $this->action_signatures( $plan );
		$this->assertSame( array( 'PT' ), (array) $signatures[0][2] );
		$this->assertEqualsCanonicalizing( array( 'DE', 'RU' ), (array) $signatures[1][2] );
		$this->assertContains( 'GB-ENG', (array) $signatures[2][2] );
	}

	public function test_show_homepage_only_in_france(): void {
		$input = 'show homepage only in France';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 1, $plan['actions'] );
		$signatures = $this->action_signatures( $plan );
		$this->assertSame( 'update_original_targeting', $signatures[0][0] );
		$this->assertSame( 'homepage', $signatures[0][1] );
		$this->assertSame( 'FR', $signatures[0][2] );
	}

	public function test_hide_summer_popup_mobile_germany(): void {
		$input = 'hide the summer popup for mobile users in Germany';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 1, $plan['actions'] );
		$action = $plan['actions'][0];
		$this->assertSame( 'create_rule', $action['type'] );
		$this->assertStringContainsString( 'summer', (string) ( $action['target']['label'] ?? '' ) );
		$this->assertSame( array( 'DE' ), $action['conditions']['countries'] );
		$this->assertSame( array( 'mobile' ), $action['conditions']['devices'] );
	}

	public function test_create_test_portugal_shop_page(): void {
		$input = 'test what users in Portugal see on the shop page';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 1, $plan['actions'] );
		$action = $plan['actions'][0];
		$this->assertSame( 'create_test', $action['type'] );
		$this->assertSame( 'shop', $action['target']['slug'] ?? $action['target']['label'] );
		$this->assertSame( array( 'PT' ), $action['conditions']['countries'] );
	}

	public function test_variant_grouping_ambiguous(): void {
		$input = 'create variants for France, Spain and Italy';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertSame( 'variant_grouping', $plan['clarification']['type'] ?? '' );
		$this->assertSame( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION, $plan['status'] );
	}

	public function test_confirmation_summary_lists_each_action(): void {
		$input = 'show homepage only in France';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertStringContainsString( '1 action', (string) ( $plan['setupSummary'] ?? '' ) );
		$this->assertStringContainsString( 'France', (string) ( $plan['confirmationSummary'] ?? '' ) );
	}
}
