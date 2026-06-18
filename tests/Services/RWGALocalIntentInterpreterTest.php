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
		require_once $base . 'services/class-rwga-country-rule-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function entities() {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_duplicate_homepage_twice_france_germany_russia(): void {
		$phrase = 'i would like to duplicate the homepage twice with a version for france only and a version which works in both germany and russia';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertSame( 'geocore_create_variants_with_country_rules', $result['matched_action'] );
		$this->assertSame( 'homepage', $result['params']['source_page_ref'] );
		$this->assertSame( 2, $result['params']['variant_count'] );
		$this->assertCount( 2, $result['params']['variants'] );
		$this->assertSame( array( 'FR' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'DE', 'RU' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_create_two_variants_au_fr_de(): void {
		$phrase = 'create two variants of the homepage one for australia and one for france and germany';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertSame( 2, $result['params']['variant_count'] );
		$this->assertSame( array( 'AU' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'FR', 'DE' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_make_one_version_uk_us(): void {
		$phrase = 'make one version for uk and another for us';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertSame( 2, $result['params']['variant_count'] );
		$this->assertSame( array( 'GB' ), $result['params']['variants'][0]['countries'] );
		$this->assertSame( array( 'US' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_show_france_germany_russia_single_rule(): void {
		$phrase = 'show this in france germany and russia';
		$result = RWGA_Country_Rule_Interpreter::parse( $phrase, $this->entities() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'country_include', $result['intent'] );
		$this->assertSame( 'geocore_create_country_rule', $result['matched_action'] );
		$this->assertSame( 'include_only', $result['params']['mode'] );
		$this->assertEqualsCanonicalizing( array( 'FR', 'DE', 'RU' ), $result['params']['countries'] );
	}

	public function test_ambiguous_duplicate_three_countries(): void {
		$phrase = 'duplicate the homepage for canada, usa and uk';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertSame( 0.62, $result['confidence'] );
		$this->assertNotEmpty( $result['missing_information'] );
		$this->assertSame( 'variant_grouping', $result['missing_information'][0]['key'] );
		$this->assertNotEmpty( $result['suggested_options'] );
	}

	public function test_russia_entity_detected(): void {
		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( 'russia', $this->entities() );
		$this->assertContains( 'RU', $countries );
	}

	public function test_page_reference_homepage(): void {
		$page = RWGA_Page_Reference_Resolver::detect( 'create two variants of the homepage' );
		$this->assertIsArray( $page );
		$this->assertSame( 'homepage', $page['value'] );
	}
}
