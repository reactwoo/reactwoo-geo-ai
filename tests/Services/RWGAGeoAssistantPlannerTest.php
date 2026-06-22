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
		require_once $base . 'services/planner/class-rwga-planner-parent-variant-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-ordinal-variant-resolver.php';
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

	public function test_pricing_variants_with_devices_and_separate_popup_rule(): void {
		$input = 'Create two new variants of the pricing page — one should show only in Portugal for mobile users, and the other should show in Germany and France for desktop users. Also create a rule to hide the winter promo popup for visitors in England.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertCount( 3, $plan['actions'], 'Expected three independent actions.' );

		foreach ( $plan['actions'] as $action ) {
			$this->assertStringNotContainsString(
				'homepage',
				strtolower( (string) ( $action['target']['label'] ?? '' ) . (string) ( $action['target']['slug'] ?? '' ) ),
				'Homepage must not appear when it was not mentioned.'
			);
		}

		$variant_one = $plan['actions'][0];
		$variant_two = $plan['actions'][1];
		$rule        = $plan['actions'][2];

		$this->assertSame( 'create_variant', $variant_one['type'] );
		$this->assertStringContainsString( 'pricing', strtolower( (string) ( $variant_one['target']['label'] ?? '' ) ) );
		$this->assertSame( array( 'PT' ), $variant_one['conditions']['countries'] );
		$this->assertSame( array(), $variant_one['conditions']['regions'] );
		$this->assertSame( array( 'mobile' ), $variant_one['conditions']['devices'] );
		$this->assertSame( 'only_show', $variant_one['operation']['visibility'] );

		$this->assertSame( 'create_variant', $variant_two['type'] );
		$this->assertEqualsCanonicalizing( array( 'DE', 'FR' ), $variant_two['conditions']['countries'] );
		$this->assertSame( array(), $variant_two['conditions']['regions'] );
		$this->assertSame( array( 'desktop' ), $variant_two['conditions']['devices'] );
		$this->assertSame( 'show', $variant_two['operation']['visibility'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'popup', $rule['target']['type'] );
		$this->assertStringContainsString( 'winter promo popup', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$this->assertSame( array(), $rule['conditions']['countries'] );
		$this->assertContains( 'GB-ENG', $rule['conditions']['regions'] );
		$this->assertSame( array(), $rule['conditions']['devices'] );
		$this->assertNotContains( 'DE', $rule['conditions']['countries'] );
		$this->assertNotContains( 'FR', $rule['conditions']['countries'] );
		$this->assertNotContains( 'PT', $rule['conditions']['countries'] );
		$this->assertNotEmpty( $rule['warnings'] ?? array() );
	}

	public function test_landing_page_three_versions_rule_and_test(): void {
		$input = "Can you make 3 versions of the landing page pls — first one for people in Portugal on mobile, another for Germany or Austria desktop visitors, and the last one only for users in South Africa when it's raining. Also hide the black friday banner for anyone coming from England, and test what a French visitor would see on the landing page.";
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertCount( 5, $plan['actions'], 'Expected five independent actions.' );

		$variant_one = $plan['actions'][0];
		$variant_two = $plan['actions'][1];
		$variant_three = $plan['actions'][2];
		$rule        = $plan['actions'][3];
		$test        = $plan['actions'][4];

		$this->assertSame( 'create_variant', $variant_one['type'] );
		$this->assertStringContainsString( 'landing', strtolower( (string) ( $variant_one['target']['label'] ?? '' ) ) );
		$this->assertSame( 1, $variant_one['variant']['index'] ?? null );
		$this->assertSame( array( 'PT' ), $variant_one['conditions']['countries'] );
		$this->assertSame( array(), $variant_one['conditions']['regions'] );
		$this->assertSame( array( 'mobile' ), $variant_one['conditions']['devices'] );
		$this->assertSame( array(), array_values( array_filter( (array) ( $variant_one['conditions']['weather'] ?? array() ) ) ) );

		$this->assertSame( 'create_variant', $variant_two['type'] );
		$this->assertSame( 2, $variant_two['variant']['index'] ?? null );
		$this->assertEqualsCanonicalizing( array( 'DE', 'AT' ), $variant_two['conditions']['countries'] );
		$this->assertSame( array(), $variant_two['conditions']['regions'] );
		$this->assertSame( array( 'desktop' ), $variant_two['conditions']['devices'] );
		$this->assertNotContains( 'PT', $variant_two['conditions']['countries'] );
		$this->assertNotContains( 'FR', $variant_two['conditions']['countries'] );

		$this->assertSame( 'create_variant', $variant_three['type'] );
		$this->assertSame( 3, $variant_three['variant']['index'] ?? null );
		$this->assertSame( array( 'ZA' ), $variant_three['conditions']['countries'] );
		$this->assertSame( array(), $variant_three['conditions']['regions'] );
		$this->assertSame( array(), $variant_three['conditions']['devices'] );
		$this->assertSame( array( 'rain' ), array_values( array_filter( (array) ( $variant_three['conditions']['weather'] ?? array() ) ) ) );
		$this->assertSame( 'only_show', $variant_three['operation']['visibility'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'banner', $rule['target']['type'] );
		$this->assertStringContainsString( 'black friday banner', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$this->assertSame( array(), $rule['conditions']['countries'] );
		$this->assertContains( 'GB-ENG', $rule['conditions']['regions'] );
		$this->assertNotContains( 'FR', $rule['conditions']['countries'] );
		$this->assertNotContains( 'DE', $rule['conditions']['countries'] );

		$this->assertSame( 'create_test', $test['type'] );
		$this->assertStringContainsString( 'landing', strtolower( (string) ( $test['target']['label'] ?? '' ) ) );
		$this->assertSame( array( 'FR' ), $test['conditions']['countries'] );
		$this->assertSame( array(), $test['conditions']['regions'] );
		$this->assertNotContains( 'GB-ENG', $test['conditions']['regions'] );
		$this->assertNotContains( 'PT', $test['conditions']['countries'] );
	}
}
