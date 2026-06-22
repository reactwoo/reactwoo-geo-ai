<?php
/**
 * Ambiguity detection and confirmation gating tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGAAmbiguityDetectorTest extends TestCase {

	private static bool $booted = false;

	protected function setUp(): void {
		parent::setUp();
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
		require_once $base . 'services/class-rwga-site-interpretation-preferences.php';
		require_once $base . 'services/class-rwga-ambiguity-detector.php';
		require_once $base . 'services/class-rwga-ai-interpretation-builder.php';
		require_once $base . 'services/class-rwga-ambiguity-gate.php';
		require_once $base . 'services/class-rwga-interpretation-status.php';
		require_once $base . 'services/class-rwga-page-reference-resolver.php';
	}

	public function test_county_england_detects_location_and_audience_ambiguity(): void {
		$phrase = RWGA_Local_Intent_Interpreter::normalise(
			'i want you to create a rule, it should match the following conditions show in county england when the weather is sunny and the audiences matches any'
		);
		$rows = RWGA_Ambiguity_Detector::detect( $phrase, array(), array(), array() );
		$fields = array_map(
			static function ( array $row ): string {
				return (string) ( $row['field'] ?? '' );
			},
			$rows
		);
		$this->assertContains( 'location', $fields );
		$this->assertContains( 'audience', $fields );
		foreach ( $rows as $row ) {
			if ( 'location' === ( $row['field'] ?? '' ) ) {
				$this->assertContains( 'GB', (array) ( $row['alternatives'] ?? array() ) );
				$this->assertNotEmpty( $row['question'] ?? '' );
			}
		}
	}

	public function test_ambiguity_gate_blocks_execution_until_confirmation(): void {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( 'show in england when weather is sunny' );
		$seed   = array(
			'intent'         => 'compound_targeting',
			'matched_action' => 'geocore_create_portable_rule',
			'confidence'     => 0.7,
		);
		$trace  = array();
		$result = RWGA_Ambiguity_Gate::apply( $seed, $phrase, $phrase, array(), array(), $trace );
		$this->assertSame( RWGA_Interpretation_Status::NEEDS_CONFIRMATION, $result['interpretation_status'] ?? '' );
		$this->assertFalse( $result['proposal_ready'] ?? true );
		$this->assertNotEmpty( $result['ambiguities'] ?? array() );
		$this->assertNotEmpty( $result['ai_interpretation']['likely_meaning'] ?? '' );

		$meta = RWGA_Interpretation_Status::from_result( $result );
		$this->assertSame( RWGA_Interpretation_Status::NEEDS_CONFIRMATION, $meta['status'] );
		$this->assertFalse( $meta['can_execute'] );
	}

	public function test_confirmed_interpretation_clears_ambiguities_and_allows_execute(): void {
		$phrase      = RWGA_Local_Intent_Interpreter::normalise( 'show in england when sunny' );
		$ambiguities = RWGA_Ambiguity_Detector::detect( $phrase, array(), array(), array() );
		$raw         = RWGA_AI_Interpretation_Builder::build_confirmed_raw(
			$phrase,
			$ambiguities,
			RWGA_AI_Interpretation_Builder::build( $phrase, $ambiguities, array(), array(), array() ),
			array()
		);
		$this->assertSame( RWGA_Interpretation_Status::COMPLETE, $raw['interpretation_status'] ?? '' );
		$this->assertNotEmpty( $raw['portable_rule_set'] ?? null );
		$meta = RWGA_Interpretation_Status::from_result( $raw );
		$this->assertTrue( $meta['can_execute'] );
	}
}
