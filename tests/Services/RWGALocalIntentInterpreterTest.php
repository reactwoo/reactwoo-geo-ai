<?php
/**
 * Local intent / multi-variant interpreter tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGALocalIntentInterpreterTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-compound-condition-interpreter.php';
		require_once $base . 'services/class-rwga-page-reference-resolver.php';
		require_once $base . 'services/class-rwga-variant-group-extractor.php';
		require_once $base . 'services/class-rwga-original-source-targeting-extractor.php';
		require_once $base . 'services/class-rwga-country-rule-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-variant-plan-interpreter.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function entities() {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_variant_plan_original_and_two_duplicates(): void {
		$phrase = 'i would like you to duplicate the homepage twice the original version would show in uk one version will show in germany and the 3rd version will show in both france and portugal';
		$result = RWGA_Variant_Plan_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variant_plan', $result['intent'] );
		$this->assertSame( 'geocore_create_variant_plan_with_country_rules', $result['matched_action'] );
		$this->assertSame( 'homepage', $result['params']['source_page_ref'] );
		$this->assertSame( 2, $result['params']['duplicate_count'] );
		$this->assertSame( array( 'GB' ), $result['params']['source_targeting']['countries'] );
		$this->assertCount( 2, $result['params']['variants'] );
		$this->assertSame( array( 'DE' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'FR', 'PT' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_variant_plan_keep_original_uk(): void {
		$phrase = 'keep the original homepage for uk users and create one version for germany and another for france and portugal';
		$result = RWGA_Variant_Plan_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variant_plan', $result['intent'] );
		$this->assertSame( array( 'GB' ), $result['params']['source_targeting']['countries'] );
		$this->assertSame( array( 'DE' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'FR', 'PT' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_three_versions_without_original_asks_clarification(): void {
		$phrase = 'create three versions of the homepage, one for uk, one for germany and one for france and portugal';
		$result = RWGA_Variant_Plan_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variant_plan', $result['intent'] );
		$this->assertNotEmpty( $result['missing_information'] );
		$this->assertSame( 'source_usage', $result['missing_information'][0]['key'] );
	}

	public function test_both_france_and_portugal_country_list(): void {
		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( 'both france and portugal', $this->entities() );
		$this->assertEqualsCanonicalizing( array( 'FR', 'PT' ), $countries );
	}

	public function test_show_multi_country_single_rule(): void {
		$phrase = 'show this in uk, germany, france and portugal';
		$result = RWGA_Country_Rule_Interpreter::parse( $phrase, $this->entities() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'country_include', $result['intent'] );
		$this->assertEqualsCanonicalizing( array( 'GB', 'DE', 'FR', 'PT' ), $result['params']['countries'] );
	}

	public function test_duplicate_homepage_twice_france_germany_russia(): void {
		$phrase = 'i would like to duplicate the homepage twice with a version for france only and a version which works in both germany and russia';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertCount( 2, $result['params']['variants'] );
		$this->assertSame( array( 'FR' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'DE', 'RU' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_make_one_version_uk_us(): void {
		$phrase = 'make one version for uk and another for us';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( array( 'GB' ), $result['params']['variants'][0]['countries'] );
		$this->assertSame( array( 'US' ), $result['params']['variants'][1]['countries'] );
	}
}
