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
		if ( ! class_exists( 'RWGA_Intelligence_Bundle_Bootstrap', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		}
		if ( ! class_exists( 'RWGA_Local_Intent_Interpreter', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-local-intent-interpreter.php';
		}
		if ( ! class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-multi-variant-interpreter.php';
		}
		if ( ! class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/services/class-rwga-page-reference-resolver.php';
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function entities() {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	public function test_multi_variant_homepage_phrase(): void {
		$phrase = 'i would like to create two variants of the homepage one will display in australia only the other in france and germany';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( 'create_geo_variants', $result['intent'] );
		$this->assertSame( 'geocore_create_variants_with_country_rules', $result['matched_action'] );
		$this->assertCount( 2, $result['params']['variants'] );
		$this->assertSame( array( 'AU' ), $result['params']['variants'][0]['countries'] );
		$this->assertEqualsCanonicalizing( array( 'FR', 'DE' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_comma_variant_phrase(): void {
		$phrase = 'create two variants of the homepage, one for australia and one for france and germany';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertCount( 2, $result['params']['variants'] );
	}

	public function test_uk_us_phrase(): void {
		$phrase = 'make one version for uk and another for us';
		$result = RWGA_Multi_Variant_Interpreter::parse( $phrase, $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$this->assertSame( array( 'GB' ), $result['params']['variants'][0]['countries'] );
		$this->assertSame( array( 'US' ), $result['params']['variants'][1]['countries'] );
	}

	public function test_show_canada_phrase_detects_country(): void {
		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( 'canada', $this->entities() );
		$this->assertContains( 'CA', $countries );
	}

	public function test_page_reference_homepage(): void {
		$page = RWGA_Page_Reference_Resolver::detect( 'create two variants of the homepage' );
		$this->assertIsArray( $page );
		$this->assertSame( 'homepage', $page['value'] );
	}
}
