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
		require_once $base . 'services/planner/class-rwga-planner-condition-polarity-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-audience-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-utm-condition-resolver.php';
		require_once $base . 'services/planner/class-rwga-planner-inherited-target-resolver.php';
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
	 * @param array<string,mixed> $action Action row.
	 * @return array<string,mixed>
	 */
	private function include_of( array $action ): array {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		return is_array( $conditions['include'] ?? null ) ? $conditions['include'] : $conditions;
	}

	/**
	 * @param array<string,mixed> $action Action row.
	 * @return array<string,mixed>
	 */
	private function exclude_of( array $action ): array {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		return is_array( $conditions['exclude'] ?? null ) ? $conditions['exclude'] : array();
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
			$include   = $this->include_of( $action );
			$regions   = (array) ( $include['regions'] ?? array() );
			$countries = (array) ( $include['countries'] ?? array() );
			$location = ! empty( $regions ) ? $regions[0] : ( $countries[0] ?? '' );
			if ( count( $countries ) > 1 && empty( $regions ) ) {
				$location = $countries;
			}
			$rows[] = array(
				(string) ( $action['type'] ?? '' ),
				(string) ( $action['target']['label'] ?? '' ),
				$location,
			);
			if ( ! empty( $include['devices'] ) ) {
				$rows[ count( $rows ) - 1 ][] = implode( ',', (array) $include['devices'] );
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
		$include = $this->include_of( $action );
		$this->assertSame( array( 'DE' ), $include['countries'] );
		$this->assertSame( array( 'mobile' ), $include['devices'] );
	}

	public function test_create_test_portugal_shop_page(): void {
		$input = 'test what users in Portugal see on the shop page';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 1, $plan['actions'] );
		$action = $plan['actions'][0];
		$this->assertSame( 'create_test', $action['type'] );
		$this->assertSame( 'shop', $action['target']['slug'] ?? $action['target']['label'] );
		$this->assertSame( array( 'PT' ), $this->include_of( $action )['countries'] );
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
		$include_one = $this->include_of( $variant_one );
		$this->assertSame( array( 'PT' ), $include_one['countries'] );
		$this->assertSame( array(), $include_one['regions'] );
		$this->assertSame( array( 'mobile' ), $include_one['devices'] );
		$this->assertSame( 'only_show', $variant_one['operation']['visibility'] );

		$this->assertSame( 'create_variant', $variant_two['type'] );
		$include_two = $this->include_of( $variant_two );
		$this->assertEqualsCanonicalizing( array( 'DE', 'FR' ), $include_two['countries'] );
		$this->assertSame( array(), $include_two['regions'] );
		$this->assertSame( array( 'desktop' ), $include_two['devices'] );
		$this->assertSame( 'show', $variant_two['operation']['visibility'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'popup', $rule['target']['type'] );
		$this->assertStringContainsString( 'winter promo popup', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$include_rule = $this->include_of( $rule );
		$this->assertSame( array(), $include_rule['countries'] );
		$this->assertContains( 'GB-ENG', $include_rule['regions'] );
		$this->assertSame( array(), $include_rule['devices'] );
		$this->assertNotContains( 'DE', $include_rule['countries'] );
		$this->assertNotContains( 'FR', $include_rule['countries'] );
		$this->assertNotContains( 'PT', $include_rule['countries'] );
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
		$include_one = $this->include_of( $variant_one );
		$this->assertSame( array( 'PT' ), $include_one['countries'] );
		$this->assertSame( array(), $include_one['regions'] );
		$this->assertSame( array( 'mobile' ), $include_one['devices'] );
		$this->assertSame( array(), array_values( array_filter( (array) ( $include_one['weather'] ?? array() ) ) ) );

		$this->assertSame( 'create_variant', $variant_two['type'] );
		$this->assertSame( 2, $variant_two['variant']['index'] ?? null );
		$include_two = $this->include_of( $variant_two );
		$this->assertEqualsCanonicalizing( array( 'DE', 'AT' ), $include_two['countries'] );
		$this->assertSame( array(), $include_two['regions'] );
		$this->assertSame( array( 'desktop' ), $include_two['devices'] );
		$this->assertNotContains( 'PT', $include_two['countries'] );
		$this->assertNotContains( 'FR', $include_two['countries'] );

		$this->assertSame( 'create_variant', $variant_three['type'] );
		$this->assertSame( 3, $variant_three['variant']['index'] ?? null );
		$include_three = $this->include_of( $variant_three );
		$this->assertSame( array( 'ZA' ), $include_three['countries'] );
		$this->assertSame( array(), $include_three['regions'] );
		$this->assertSame( array(), $include_three['devices'] );
		$this->assertSame( array( 'rain' ), array_values( array_filter( (array) ( $include_three['weather'] ?? array() ) ) ) );
		$this->assertSame( 'only_show', $variant_three['operation']['visibility'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'banner', $rule['target']['type'] );
		$this->assertStringContainsString( 'black friday banner', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$include_rule = $this->include_of( $rule );
		$this->assertSame( array(), $include_rule['countries'] );
		$this->assertContains( 'GB-ENG', $include_rule['regions'] );
		$this->assertNotContains( 'FR', $include_rule['countries'] );
		$this->assertNotContains( 'DE', $include_rule['countries'] );

		$this->assertSame( 'create_test', $test['type'] );
		$this->assertStringContainsString( 'landing', strtolower( (string) ( $test['target']['label'] ?? '' ) ) );
		$include_test = $this->include_of( $test );
		$this->assertSame( array( 'FR' ), $include_test['countries'] );
		$this->assertSame( array(), $include_test['regions'] );
		$this->assertNotContains( 'GB-ENG', $include_test['regions'] );
		$this->assertNotContains( 'PT', $include_test['countries'] );
	}

	public function test_summer_sale_campaign_product_polarity_and_inherited_targets(): void {
		$input = "Set up the summer sale campaign so the beach towels product page only shows to visitors in Spain or Portugal when it's sunny, but hide it from desktop users. Then create a second version of the same product page for mobile visitors in France, and add a rule so anyone landing from /facebook-ad sees the promo popup except users in Germany.";
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$campaign = $plan['actions'][0];
		$hide     = $plan['actions'][1];
		$variant  = $plan['actions'][2];
		$popup    = $plan['actions'][3];

		$this->assertSame( 'update_campaign_targeting', $campaign['type'] );
		$this->assertSame( 'summer sale', $campaign['campaign']['label'] ?? '' );
		$this->assertSame( 'product_page', $campaign['target']['type'] );
		$this->assertStringContainsString( 'beach towels product page', strtolower( (string) ( $campaign['target']['label'] ?? '' ) ) );
		$this->assertSame( 'only_show', $campaign['operation']['visibility'] );
		$include_campaign = $this->include_of( $campaign );
		$this->assertEqualsCanonicalizing( array( 'ES', 'PT' ), $include_campaign['countries'] );
		$this->assertSame( array( 'sunny' ), array_values( array_filter( (array) ( $include_campaign['weather'] ?? array() ) ) ) );
		$this->assertSame( array(), $include_campaign['devices'] );
		$this->assertNotContains( 'FR', $include_campaign['countries'] );
		$this->assertNotContains( 'DE', $include_campaign['countries'] );

		$this->assertSame( 'create_rule', $hide['type'] );
		$this->assertSame( 'product_page', $hide['target']['type'] );
		$this->assertStringContainsString( 'beach towels product page', strtolower( (string) ( $hide['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $hide['operation']['visibility'] );
		$include_hide = $this->include_of( $hide );
		$this->assertSame( array( 'desktop' ), $include_hide['devices'] );
		$this->assertSame( array(), $include_hide['countries'] );
		$this->assertNotContains( 'ES', $include_hide['countries'] );
		$this->assertNotContains( 'PT', $include_hide['countries'] );

		$this->assertSame( 'create_variant', $variant['type'] );
		$this->assertSame( 2, $variant['variant']['index'] ?? null );
		$this->assertSame( 'product_page', $variant['target']['type'] );
		$this->assertStringContainsString( 'beach towels product page', strtolower( (string) ( $variant['target']['label'] ?? '' ) ) );
		$include_variant = $this->include_of( $variant );
		$this->assertSame( array( 'FR' ), $include_variant['countries'] );
		$this->assertSame( array( 'mobile' ), $include_variant['devices'] );
		$this->assertNotContains( 'DE', $include_variant['countries'] );
		$this->assertNotContains( 'ES', $include_variant['countries'] );

		$this->assertSame( 'create_rule', $popup['type'] );
		$this->assertSame( 'popup', $popup['target']['type'] );
		$this->assertStringContainsString( 'promo popup', strtolower( (string) ( $popup['target']['label'] ?? '' ) ) );
		$this->assertSame( 'show', $popup['operation']['visibility'] );
		$include_popup = $this->include_of( $popup );
		$exclude_popup = $this->exclude_of( $popup );
		$this->assertSame( array( '/facebook-ad' ), $include_popup['urls'] );
		$this->assertSame( array( 'DE' ), $exclude_popup['countries'] );
		$this->assertNotContains( 'DE', $include_popup['countries'] );
		$this->assertNotContains( 'FR', $include_popup['countries'] );
		$this->assertNotContains( 'ES', $include_popup['countries'] );
	}

	public function test_new_year_campaign_four_actions_with_validation(): void {
		$input = "For the new year campaign, show the gym equipment category only to returning visitors in the UK who arrive with utm_campaign=ny-sale, but don't show it to tablet users. Then create a variant of the protein powder product page for first-time visitors in Ireland, and add a rule to show the free-shipping banner to mobile users in Spain except when the weather is rainy.";
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertSame( 'needs_confirmation', $plan['status'] );
		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$campaign = $plan['actions'][0];
		$hide     = $plan['actions'][1];
		$variant  = $plan['actions'][2];
		$banner   = $plan['actions'][3];

		$this->assertSame( 'update_campaign_targeting', $campaign['type'] );
		$this->assertSame( 'new year', $campaign['campaign']['label'] ?? '' );
		$this->assertSame( 'category', $campaign['target']['type'] );
		$this->assertStringContainsString( 'gym equipment category', strtolower( (string) ( $campaign['target']['label'] ?? '' ) ) );
		$this->assertSame( 'only_show', $campaign['operation']['visibility'] );
		$include_campaign = $this->include_of( $campaign );
		$this->assertSame( array( 'GB' ), $include_campaign['countries'] );
		$this->assertSame( array( 'returning_visitors' ), $include_campaign['audiences'] );
		$this->assertSame(
			array( array( 'key' => 'utm_campaign', 'value' => 'ny-sale' ) ),
			$include_campaign['utm']
		);
		$this->assertNotContains( 'IE', $include_campaign['countries'] );

		$this->assertSame( 'create_rule', $hide['type'] );
		$this->assertSame( 'category', $hide['target']['type'] );
		$this->assertStringContainsString( 'gym equipment category', strtolower( (string) ( $hide['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $hide['operation']['visibility'] );
		$include_hide = $this->include_of( $hide );
		$this->assertSame( array( 'tablet' ), $include_hide['devices'] );
		$this->assertSame( array(), $include_hide['countries'] );

		$this->assertSame( 'create_variant', $variant['type'] );
		$this->assertSame( 'product_page', $variant['target']['type'] );
		$this->assertStringContainsString( 'protein powder product page', strtolower( (string) ( $variant['target']['label'] ?? '' ) ) );
		$include_variant = $this->include_of( $variant );
		$this->assertSame( array( 'IE' ), $include_variant['countries'] );
		$this->assertSame( array( 'first_time_visitors' ), $include_variant['audiences'] );
		$this->assertNotContains( 'GB', $include_variant['countries'] );

		$this->assertSame( 'create_rule', $banner['type'] );
		$this->assertSame( 'banner', $banner['target']['type'] );
		$this->assertStringContainsString( 'free-shipping banner', strtolower( (string) ( $banner['target']['label'] ?? '' ) ) );
		$this->assertSame( 'show', $banner['operation']['visibility'] );
		$include_banner = $this->include_of( $banner );
		$exclude_banner = $this->exclude_of( $banner );
		$this->assertSame( array( 'ES' ), $include_banner['countries'] );
		$this->assertSame( array( 'mobile' ), $include_banner['devices'] );
		$this->assertSame( array( 'rainy' ), array_values( array_filter( (array) ( $exclude_banner['weather'] ?? array() ) ) ) );
	}
}
