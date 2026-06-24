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
		require_once $base . 'services/planner/class-rwga-planner-region-ambiguity-resolver.php';
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
	 * Bootstrap entities plus a synced audience/campaign registry for resolution.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function synced_entities(): array {
		$audiences = array(
			array( 'entity_type' => 'audience', 'entity_key' => 'aud_returning', 'display_name' => 'Returning Visitors', 'source' => 'ga4', 'aliases' => array( 'returning visitors', 'returning customers' ) ),
			array( 'entity_type' => 'audience', 'entity_key' => 'aud_firsttime', 'display_name' => 'First-Time Visitors', 'source' => 'ga4', 'aliases' => array( 'first-time visitors', 'first time visitors' ) ),
			array( 'entity_type' => 'audience', 'entity_key' => 'aud_new', 'display_name' => 'New Visitors', 'source' => 'ga4', 'aliases' => array( 'new visitors' ) ),
			array( 'entity_type' => 'audience', 'entity_key' => 'aud_vip', 'display_name' => 'VIP Customers', 'source' => 'crm', 'aliases' => array( 'vip customers' ) ),
			array( 'entity_type' => 'audience', 'entity_key' => 'aud_news', 'display_name' => 'Newsletter Subscribers', 'source' => 'email_platform', 'aliases' => array( 'newsletter subscribers' ) ),
		);
		$campaigns = array(
			array( 'entity_type' => 'campaign', 'entity_key' => 'cmp_ny', 'display_name' => 'New Year', 'source' => 'google_ads', 'aliases' => array( 'new year', 'new year campaign' ) ),
			array( 'entity_type' => 'campaign', 'entity_key' => 'cmp_summer', 'display_name' => 'Summer Sale', 'source' => 'google_ads', 'aliases' => array( 'summer sale', 'summer sale campaign' ) ),
		);
		return array_merge( $this->entities(), $audiences, $campaigns );
	}

	/**
	 * @param array<string,mixed> $include Include condition group.
	 * @return array<int,string>
	 */
	private function audience_names( array $include ): array {
		$names = array();
		foreach ( (array) ( $include['audiences'] ?? array() ) as $audience ) {
			$names[] = is_array( $audience ) ? (string) ( $audience['name'] ?? '' ) : (string) $audience;
		}
		return array_values( array_filter( $names ) );
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

	public function test_same_category_page_variants_inherit_named_target(): void {
		$input = 'update the ski jackets category page so it only shows in Norway, then create two new versions of the same category page: one for mobile users in Finland and the other for desktop users in Denmark';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 3, $plan['actions'] );

		$variant_two   = $plan['actions'][1];
		$variant_three = $plan['actions'][2];

		$this->assertSame( 'create_variant', (string) $variant_two['type'] );
		$this->assertSame( 'ski jackets category page', (string) ( $variant_two['target']['label'] ?? '' ) );
		$this->assertSame( 'inherited', (string) ( $variant_two['target']['source'] ?? '' ) );
		$this->assertSame( 'ski jackets category page', (string) ( $variant_two['target']['inheritedFrom'] ?? '' ) );

		$this->assertSame( 'ski jackets category page', (string) ( $variant_three['target']['label'] ?? '' ) );
		$this->assertSame( 'inherited', (string) ( $variant_three['target']['source'] ?? '' ) );

		// The inherited target shares one dependency id across all three cards.
		$cards = $plan['action_cards'];
		$dep1  = (string) ( $cards[0]['target']['dependencyId'] ?? '' );
		$this->assertNotSame( '', $dep1 );
		$this->assertSame( $dep1, (string) ( $cards[1]['target']['dependencyId'] ?? '' ) );
		$this->assertSame( $dep1, (string) ( $cards[2]['target']['dependencyId'] ?? '' ) );
	}

	public function test_explicit_region_clarification_marks_location_and_audience_unresolved(): void {
		$input = 'I want you to create a rule for the Home page. It should show only when the visitor is in England, the weather is sunny, and the audience matches any. If England is unclear, ask me whether I mean United Kingdom country targeting or England region targeting before creating anything.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertCount( 1, $plan['actions'] );
		$card = $plan['action_cards'][0];
		$this->assertSame( 'needs_resolution', (string) $card['status'] );
		$this->assertSame( 2, (int) $plan['fields_needing_attention'] );

		// England is NOT silently converted: no regions resolved on the include group.
		$this->assertSame( array(), (array) $card['conditions']['include']['regions'] );
		$this->assertSame( array(), (array) $card['conditions']['include']['countries'] );

		// Weather "sunny" is detected and not mapped to "hot".
		$this->assertSame( array( 'sunny' ), (array) $card['conditions']['include']['weather'] );

		$rows  = $card['condition_rows'];
		$types = array_column( $rows, 'type' );
		$this->assertContains( 'location', $types );
		$this->assertContains( 'weather', $types );
		$this->assertContains( 'audience', $types );

		$location = $this->row_of_type( $rows, 'location' );
		$this->assertSame( 'needs_resolution', (string) $location['status'] );
		$option_keys = array_column( (array) $location['resolution_options'], 'key' );
		$this->assertContains( 'country_gb', $option_keys );
		$this->assertContains( 'region_gb_eng', $option_keys );
		$this->assertContains( 'remove', $option_keys );

		$weather = $this->row_of_type( $rows, 'weather' );
		$this->assertSame( 'valid', (string) $weather['status'] );
		$this->assertSame( 'sunny', (string) $weather['value'] );

		$audience = $this->row_of_type( $rows, 'audience' );
		$this->assertSame( 'needs_resolution', (string) $audience['status'] );
		$audience_keys = array_column( (array) $audience['resolution_options'], 'key' );
		$this->assertContains( 'any_audience', $audience_keys );
		$this->assertContains( 'choose_audiences', $audience_keys );
	}

	public function test_bare_nation_still_resolves_without_clarification_request(): void {
		$input = 'show the homepage in England';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );
		$this->assertCount( 1, $plan['actions'] );
		$include = $this->include_of( $plan['actions'][0] );
		$this->assertSame( array( 'GB-ENG' ), (array) $include['regions'] );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Condition rows.
	 * @param string                         $type Condition type.
	 * @return array<string,mixed>
	 */
	private function row_of_type( array $rows, string $type ): array {
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? '' ) === $type ) {
				return $row;
			}
		}
		return array();
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
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->synced_entities() );

		$this->assertSame( 'needs_confirmation', $plan['status'] );
		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$campaign = $plan['actions'][0];
		$hide     = $plan['actions'][1];
		$variant  = $plan['actions'][2];
		$banner   = $plan['actions'][3];

		$this->assertSame( 'update_campaign_targeting', $campaign['type'] );
		$this->assertSame( 'New Year', $campaign['campaign']['name'] ?? '' );
		$this->assertSame( 'cmp_ny', $campaign['campaign']['id'] ?? '' );
		$this->assertSame( 'category', $campaign['target']['type'] );
		$this->assertStringContainsString( 'gym equipment category', strtolower( (string) ( $campaign['target']['label'] ?? '' ) ) );
		$this->assertSame( 'only_show', $campaign['operation']['visibility'] );
		$include_campaign = $this->include_of( $campaign );
		$this->assertSame( array( 'GB' ), $include_campaign['countries'] );
		$this->assertSame( array( 'Returning Visitors' ), $this->audience_names( $include_campaign ) );
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
		$this->assertSame( array( 'First-Time Visitors' ), $this->audience_names( $include_variant ) );
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

	public function test_two_homepage_variants_with_rule_and_diagnose(): void {
		$input = "Create two versions of the homepage: the first should only show to returning visitors in Canada, and the second should show to new visitors in Australia on mobile. Then hide the newsletter popup on /checkout for users in France, and diagnose what a desktop visitor from Germany would see on the homepage.";
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->synced_entities() );

		$this->assertSame( 'needs_confirmation', $plan['status'] );
		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$variant_one = $plan['actions'][0];
		$variant_two = $plan['actions'][1];
		$rule        = $plan['actions'][2];
		$diagnose    = $plan['actions'][3];

		$this->assertSame( 'create_variant', $variant_one['type'] );
		$this->assertSame( 1, $variant_one['variant']['index'] ?? null );
		$this->assertStringContainsString( 'homepage', strtolower( (string) ( $variant_one['target']['label'] ?? '' ) ) );
		$this->assertSame( 'only_show', $variant_one['operation']['visibility'] );
		$include_one = $this->include_of( $variant_one );
		$this->assertSame( array( 'CA' ), $include_one['countries'] );
		$this->assertSame( array( 'Returning Visitors' ), $this->audience_names( $include_one ) );
		$this->assertNotContains( 'AU', $include_one['countries'] );

		$this->assertSame( 'create_variant', $variant_two['type'] );
		$this->assertSame( 2, $variant_two['variant']['index'] ?? null );
		$include_two = $this->include_of( $variant_two );
		$this->assertSame( array( 'AU' ), $include_two['countries'] );
		$this->assertSame( array( 'mobile' ), $include_two['devices'] );
		$this->assertSame( array( 'New Visitors' ), $this->audience_names( $include_two ) );
		$this->assertNotContains( 'CA', $include_two['countries'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'popup', $rule['target']['type'] );
		$this->assertStringContainsString( 'newsletter popup', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$include_rule = $this->include_of( $rule );
		$this->assertSame( array( 'FR' ), $include_rule['countries'] );
		$this->assertSame( array( '/checkout' ), $include_rule['urls'] );

		$this->assertSame( 'diagnose', $diagnose['type'] );
		$this->assertStringContainsString( 'homepage', strtolower( (string) ( $diagnose['target']['label'] ?? '' ) ) );
		$include_diag = $this->include_of( $diagnose );
		$this->assertSame( array( 'DE' ), $include_diag['countries'] );
		$this->assertSame( array( 'desktop' ), $include_diag['devices'] );
	}

	public function test_existing_variant_update_with_rule_and_preview(): void {
		$input = "Update the existing Christmas homepage variant so it shows only in Norway and Sweden when it's snowing, but don't show it to tablet users. Then create a new checkout page variant for mobile visitors in Denmark, hide the discount popup for users in Finland, and preview what a desktop visitor from Italy would see on the checkout page.";
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertSame( 'needs_confirmation', $plan['status'] );
		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$update   = $plan['actions'][0];
		$variant  = $plan['actions'][1];
		$rule     = $plan['actions'][2];
		$preview  = $plan['actions'][3];

		$this->assertSame( 'update_variant', $update['type'] );
		$this->assertSame( 'variant', $update['target']['type'] );
		$this->assertStringContainsString( 'christmas homepage variant', strtolower( (string) ( $update['target']['label'] ?? '' ) ) );
		$this->assertSame( 'homepage', $update['variant']['sourcePage'] ?? '' );
		$this->assertSame( 'only_show', $update['operation']['visibility'] );
		$include_update = $this->include_of( $update );
		$exclude_update = $this->exclude_of( $update );
		$this->assertEqualsCanonicalizing( array( 'NO', 'SE' ), $include_update['countries'] );
		$this->assertNotContains( 'IT', $include_update['countries'] );
		$this->assertSame( array( 'snow' ), array_values( array_filter( (array) ( $include_update['weather'] ?? array() ) ) ) );
		$this->assertSame( array( 'tablet' ), $exclude_update['devices'] );

		$this->assertSame( 'create_variant', $variant['type'] );
		$this->assertStringContainsString( 'checkout', strtolower( (string) ( $variant['target']['label'] ?? '' ) ) );
		$include_variant = $this->include_of( $variant );
		$this->assertSame( array( 'DK' ), $include_variant['countries'] );
		$this->assertSame( array( 'mobile' ), $include_variant['devices'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'popup', $rule['target']['type'] );
		$this->assertStringContainsString( 'discount popup', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$include_rule = $this->include_of( $rule );
		$this->assertSame( array( 'FI' ), $include_rule['countries'] );

		$this->assertSame( 'diagnose', $preview['type'] );
		$this->assertSame( 'page', $preview['target']['type'] );
		$this->assertStringContainsString( 'checkout', strtolower( (string) ( $preview['target']['label'] ?? '' ) ) );
		$include_preview = $this->include_of( $preview );
		$this->assertSame( array( 'IT' ), $include_preview['countries'] );
		$this->assertSame( array( 'desktop' ), $include_preview['devices'] );
	}

	public function test_existing_rule_update_with_audience_and_utm_exclusion(): void {
		$input = 'Update the existing VIP discount rule so it only applies to logged-in customers in Belgium and Netherlands, but exclude anyone arriving from utm_source=email. Then create a new variant of the accessories category page for mobile users in Switzerland, hide the exit-intent popup for visitors in Austria, and check what a desktop user from Poland would see on the accessories category page.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertSame( 'needs_confirmation', $plan['status'] );
		$this->assertCount( 4, $plan['actions'], 'Expected four independent actions.' );

		$update  = $plan['actions'][0];
		$variant = $plan['actions'][1];
		$rule    = $plan['actions'][2];
		$test    = $plan['actions'][3];

		$this->assertSame( 'update_rule', $update['type'] );
		$this->assertSame( 'rule', $update['target']['type'] );
		$this->assertStringContainsString( 'vip discount rule', strtolower( (string) ( $update['target']['label'] ?? '' ) ) );
		$this->assertSame( 'only_apply', $update['operation']['visibility'] );
		$include_update = $this->include_of( $update );
		$exclude_update = $this->exclude_of( $update );
		$this->assertEqualsCanonicalizing( array( 'BE', 'NL' ), $include_update['countries'] );
		$this->assertSame( array(), $this->audience_names( $include_update ) );
		$this->assertSame( array( 'logged_in' ), array_values( (array) ( $include_update['visitorStates'] ?? array() ) ) );
		$this->assertSame( 'utm_source', $exclude_update['utm'][0]['key'] ?? '' );
		$this->assertSame( 'email', $exclude_update['utm'][0]['value'] ?? '' );

		$this->assertSame( 'create_variant', $variant['type'] );
		$this->assertStringContainsString( 'accessories category page', strtolower( (string) ( $variant['target']['label'] ?? '' ) ) );
		$include_variant = $this->include_of( $variant );
		$this->assertSame( array( 'CH' ), $include_variant['countries'] );
		$this->assertSame( array( 'mobile' ), $include_variant['devices'] );

		$this->assertSame( 'create_rule', $rule['type'] );
		$this->assertSame( 'popup', $rule['target']['type'] );
		$this->assertStringContainsString( 'exit-intent popup', strtolower( (string) ( $rule['target']['label'] ?? '' ) ) );
		$this->assertSame( 'hide', $rule['operation']['visibility'] );
		$this->assertSame( array( 'AT' ), $this->include_of( $rule )['countries'] );

		$this->assertSame( 'create_test', $test['type'] );
		$this->assertStringContainsString( 'accessories category page', strtolower( (string) ( $test['target']['label'] ?? '' ) ) );
		$include_test = $this->include_of( $test );
		$this->assertSame( array( 'PL' ), $include_test['countries'] );
		$this->assertSame( array( 'desktop' ), $include_test['devices'] );
	}

	public function test_existing_rule_classified_as_page_fails_safe(): void {
		$actions = array(
			array(
				'type'       => 'create_rule',
				'target'     => array( 'type' => 'page', 'label' => 'page' ),
				'operation'  => array( 'visibility' => 'hide', 'mode' => 'create' ),
				'conditions' => array(
					'include' => array( 'countries' => array( 'BE', 'NL' ) ),
					'exclude' => array(),
				),
			),
		);
		$phrase     = 'update the existing vip discount rule so it only applies to logged-in customers in belgium and netherlands';
		$validation = RWGA_Planner_Plan_Validator::validate( $phrase, $actions, $this->entities(), array() );
		$this->assertIsArray( $validation );
		$this->assertContains( 'existing_rule_not_update', (array) ( $validation['issues'] ?? array() ) );
	}

	public function test_preview_target_mismatch_fails_safe(): void {
		$actions = array(
			array(
				'type'       => 'diagnose',
				'target'     => array( 'type' => 'popup', 'label' => 'discount popup' ),
				'operation'  => array( 'visibility' => 'show', 'mode' => 'diagnose' ),
				'conditions' => array(
					'include' => array( 'countries' => array( 'IT' ), 'devices' => array( 'desktop' ) ),
					'exclude' => array(),
				),
			),
		);
		$phrase     = 'preview what a desktop visitor from italy would see on the checkout page';
		$validation = RWGA_Planner_Plan_Validator::validate( $phrase, $actions, $this->entities(), array() );
		$this->assertIsArray( $validation );
		$this->assertContains( 'preview_target_mismatch', (array) ( $validation['issues'] ?? array() ) );
	}

	public function test_two_versions_with_single_variant_fails_safe(): void {
		$input = 'Create two versions of the homepage for returning visitors in Canada and Australia.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->synced_entities() );

		$this->assertSame( 'needs_clarification', $plan['status'] );
		$this->assertSame( 'plan_validation_failed', $plan['clarification']['type'] ?? '' );
		$this->assertContains( 'variant_count_mismatch', (array) ( $plan['clarification']['issues'] ?? array() ) );
		$this->assertSame( array(), $plan['actions'] );
	}

	public function test_unsynced_audience_returns_clarification(): void {
		$input = 'Show the homepage only to VIP customers in France.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertSame( 'needs_clarification', $plan['status'] );
		$this->assertSame( 'synced_entity_unresolved', $plan['clarification']['type'] ?? '' );
		$this->assertSame( 'audience_not_defined', $plan['clarification']['reason'] ?? '' );
		$unresolved = (array) ( $plan['clarification']['unresolved']['audiences'] ?? array() );
		$this->assertNotEmpty( $unresolved );
		$this->assertSame( 'vip customers', strtolower( (string) ( $unresolved[0]['raw'] ?? '' ) ) );
		$this->assertNotEmpty( $plan['actions'], 'Actions stay visible so the user can choose an audience.' );
		$include = $this->include_of( $plan['actions'][0] );
		$this->assertSame( array(), $this->audience_names( $include ) );

		$ambiguities = (array) ( $plan['clarification']['ambiguities'] ?? array() );
		$this->assertNotEmpty( $ambiguities, 'Each unresolved entity becomes an action-scoped ambiguity row.' );
		$this->assertSame( 'audience', $ambiguities[0]['field'] ?? '' );
		$this->assertSame( 1, $ambiguities[0]['action_index'] ?? null );
		$this->assertNotSame( '', (string) ( $ambiguities[0]['target_label'] ?? '' ), 'Ambiguity row names its action target.' );
	}

	public function test_unresolved_ambiguities_name_their_action(): void {
		$input = 'Show the homepage only to VIP customers in France, then show the shop page only to loyal members in Spain.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertSame( 'needs_clarification', $plan['status'] );
		$ambiguities = (array) ( $plan['clarification']['ambiguities'] ?? array() );
		$this->assertGreaterThanOrEqual( 2, count( $ambiguities ), 'Both unresolved audiences become ambiguity rows.' );

		$indexes = array_map( static function ( $row ) {
			return (int) ( $row['action_index'] ?? 0 );
		}, $ambiguities );
		$this->assertContains( 1, $indexes );
		$this->assertContains( 2, $indexes );

		foreach ( $ambiguities as $row ) {
			$this->assertNotSame( '', (string) ( $row['target_label'] ?? '' ), 'Every ambiguity row names which action it is for.' );
		}
	}

	public function test_legacy_adapter_exposes_action_scoped_ambiguities(): void {
		$input  = 'Show the homepage only to VIP customers in France.';
		$result = RWGA_Geo_Assistant_Planner::interpret_as_legacy( $input, array(), $this->entities() );

		$this->assertIsArray( $result );
		$ambiguities = (array) ( $result['ambiguities'] ?? array() );
		$this->assertNotEmpty( $ambiguities, 'Legacy interpreter result surfaces ambiguities for the assistant UI.' );
		$this->assertSame( 'audience', $ambiguities[0]['field'] ?? '' );
		$this->assertArrayHasKey( 'target_label', $ambiguities[0] );
		$this->assertArrayHasKey( 'action_index', $ambiguities[0] );
	}

	public function test_synced_audience_exact_match_resolves(): void {
		$input = 'Show the homepage only to VIP Customers in France.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->synced_entities() );

		$this->assertNotSame( 'needs_clarification', $plan['status'] );
		$include = $this->include_of( $plan['actions'][0] );
		$this->assertSame( array( 'VIP Customers' ), $this->audience_names( $include ) );
		$audience = $include['audiences'][0];
		$this->assertSame( 'aud_vip', $audience['id'] ?? '' );
		$this->assertSame( 'crm', $audience['source'] ?? '' );
	}

	public function test_unsynced_campaign_returns_clarification_with_suggestions(): void {
		$input = 'For the new year campaign, show the homepage only in France.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret(
			$input,
			array(),
			array_merge(
				$this->entities(),
				array(
					array( 'entity_type' => 'campaign', 'entity_key' => 'cmp_jan', 'display_name' => 'January Promo', 'source' => 'google_ads', 'aliases' => array( 'january promo', 'january promo campaign' ) ),
				)
			)
		);

		$this->assertSame( 'needs_clarification', $plan['status'] );
		$this->assertSame( 'synced_entity_unresolved', $plan['clarification']['type'] ?? '' );
		$this->assertSame( 'campaign_not_defined', $plan['clarification']['reason'] ?? '' );
		$campaigns = (array) ( $plan['clarification']['unresolved']['campaigns'] ?? array() );
		$this->assertNotEmpty( $campaigns );
		$this->assertSame( 'new year', strtolower( (string) ( $campaigns[0]['raw'] ?? '' ) ) );
	}

	public function test_logged_in_maps_to_native_visitor_state(): void {
		$input = 'Show the homepage only to logged-in customers in France.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->entities() );

		$this->assertNotSame( 'needs_clarification', $plan['status'] );
		$include = $this->include_of( $plan['actions'][0] );
		$this->assertSame( array( 'logged_in' ), array_values( (array) ( $include['visitorStates'] ?? array() ) ) );
		$this->assertSame( array(), $this->audience_names( $include ) );
	}

	public function test_complex_campaign_phrase_yields_four_action_cards(): void {
		$input   = 'For the Spring Promo campaign, update the trainers category page so it only shows to VIP Buyers in Spain and Portugal, but exclude visitors from utm_source=email. Then create a new variant of the same category page for mobile users in Italy, hide the loyalty popup for Returning Customers in Germany, and preview what a desktop visitor from France would see on the trainers category page.';
		$context = array(
			'targets' => array(
				'categories' => array(
					array( 'id' => 'cat_12', 'name' => 'Shoes' ),
					array( 'id' => 'cat_18', 'name' => 'Accessories' ),
					array( 'id' => 'cat_24', 'name' => 'Sale' ),
				),
			),
		);
		$plan = RWGA_Geo_Assistant_Planner::interpret( $input, $context, $this->entities() );

		$this->assertCount( 4, (array) ( $plan['actions'] ?? array() ), 'Exclusion clause must not become a 5th action.' );
		$this->assertCount( 4, (array) ( $plan['action_cards'] ?? array() ) );

		// Action 1 keeps the UTM exclusion attached.
		$exclude = $this->exclude_of( $plan['actions'][0] );
		$utm     = (array) ( $exclude['utm'] ?? array() );
		$this->assertNotEmpty( $utm, 'utm_source=email stays an exclusion on action 1.' );

		// Action 2 inherits the trainers category page.
		$inherited = (array) ( $plan['action_cards'][1]['target'] ?? array() );
		$this->assertTrue( (bool) ( $inherited['inherited'] ?? false ) );
		$this->assertStringContainsStringIgnoringCase( 'trainers', (string) ( $inherited['inheritedFrom'] ?? '' ) );

		// The plan is gated until unknown mappings are resolved.
		$this->assertSame( 'needs_clarification', $plan['status'] );
		$this->assertTrue( (bool) ( $plan['requires_resolution'] ?? false ) );
		$this->assertGreaterThan( 0, (int) ( $plan['fields_needing_attention'] ?? 0 ) );

		// The trainers category target is unresolved and offers suggested targets.
		$target = (array) ( $plan['action_cards'][0]['target'] ?? array() );
		$this->assertContains( (string) ( $target['status'] ?? '' ), array( 'not_found', 'ambiguous' ) );
		$this->assertNotEmpty( (array) ( $target['suggestions'] ?? array() ) );
	}

	public function test_action_card_marks_required_resolutions(): void {
		$input = 'For the Spring Promo campaign, show the homepage to VIP Buyers in Spain.';
		$plan  = RWGA_Geo_Assistant_Planner::interpret( $input, array(), $this->synced_entities() );

		$cards = (array) ( $plan['action_cards'] ?? array() );
		$this->assertNotEmpty( $cards );
		$required = (array) ( $cards[0]['requiredResolutions'] ?? array() );
		$fields   = array_map(
			static function ( $row ) {
				return (string) ( $row['field'] ?? '' );
			},
			$required
		);
		$this->assertContains( 'campaign', $fields, 'Unknown campaign requires resolution.' );
		$this->assertContains( 'audience', $fields, 'Unknown audience requires resolution.' );
		$this->assertSame( 'needs_resolution', (string) ( $cards[0]['status'] ?? '' ) );
	}
}
