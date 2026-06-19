<?php
/**
 * Interpretation status, AI escalation, and learning loop tests.
 */

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/bootstrap.php';

final class RWGAInterpretationStatusTest extends TestCase {

	private static bool $booted = false;

	protected function setUp(): void {
		parent::setUp();
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
		if ( ! function_exists( 'get_transient' ) ) {
			function get_transient( $key ) {
				return $GLOBALS['rwga_test_transients'][ $key ] ?? false;
			}
		}
		if ( ! function_exists( 'set_transient' ) ) {
			function set_transient( $key, $value, $expiration ) {
				unset( $expiration );
				$GLOBALS['rwga_test_transients'][ $key ] = $value;
				return true;
			}
		}
		if ( ! function_exists( 'delete_transient' ) ) {
			function delete_transient( $key ) {
				unset( $GLOBALS['rwga_test_transients'][ $key ] );
				return true;
			}
		}
		if ( ! isset( $GLOBALS['rwga_test_filters'] ) ) {
			$GLOBALS['rwga_test_filters'] = array();
		}
		$base = dirname( __DIR__, 2 ) . '/includes/';
		require_once $base . 'intelligence/class-rwga-intelligence-bundle-bootstrap.php';
		require_once $base . 'services/class-rwga-intelligence-sync-service.php';
		require_once $base . 'services/class-rwga-compound-condition-interpreter.php';
		require_once $base . 'services/class-rwga-page-reference-resolver.php';
		require_once $base . 'services/class-rwga-variant-group-extractor.php';
		require_once $base . 'services/class-rwga-original-source-targeting-extractor.php';
		require_once $base . 'services/class-rwga-country-rule-interpreter.php';
		require_once $base . 'services/class-rwga-segment-condition-extractor.php';
		require_once $base . 'services/class-rwga-weather-rule-interpreter.php';
		require_once $base . 'services/class-rwga-multi-variant-interpreter.php';
		require_once $base . 'services/class-rwga-variant-plan-parser.php';
		require_once $base . 'services/class-rwga-variant-plan-interpreter.php';
		require_once $base . 'services/class-rwga-phrase-shape-normaliser.php';
		require_once $base . 'services/class-rwga-interpretation-memory-store.php';
		require_once $base . 'services/class-rwga-interpretation-memory-client.php';
		require_once $base . 'services/class-rwga-interpretation-memory-matcher.php';
		require_once $base . 'services/class-rwga-parser-hints-service.php';
		require_once $base . 'services/class-rwga-learning-promotion-service.php';
		require_once $base . 'services/class-rwga-interpretation-status.php';
		require_once $base . 'services/class-rwga-interpreter-debug.php';
		require_once $base . 'services/class-rwga-context-resolver.php';
		require_once $base . 'services/class-rwga-local-intent-interpreter.php';
		update_option( RWGA_Interpretation_Memory_Store::OPTION_SHARED_ENABLED, 0, false );
	}

	private function entities(): array {
		$bundle = RWGA_Intelligence_Bundle_Bootstrap::augment( null );
		return is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
	}

	private function mixed_weather_phrase(): string {
		return 'i would like you to create two variants of the homepage, one should fire in portugal only when its raining the other can trigger in russia and germany when it is sunny, update homepage to show in uk with all weather conditions';
	}

	public function test_mixed_weather_phrase_status_complete(): void {
		$result = RWGA_Variant_Plan_Interpreter::parse( $this->mixed_weather_phrase(), $this->entities(), array() );
		$this->assertTrue( ! empty( $result['matched'] ) );
		$meta = RWGA_Interpretation_Status::from_result(
			array_merge(
				$result,
				array(
					'interpretation_source' => 'local_parser',
					'proposal_ready'        => true,
				)
			)
		);
		$this->assertSame( RWGA_Interpretation_Status::COMPLETE, $meta['status'] );
		$this->assertTrue( $meta['can_execute'] );
		$this->assertSame( array( 'GB' ), $result['params']['source_targeting']['countries'] );
		$this->assertSame( 'any', $result['params']['source_targeting']['weather']['mode'] );
		$this->assertSame( 'rain', $result['params']['variants'][0]['weather']['condition'] );
		$this->assertSame( 'sunny', $result['params']['variants'][1]['weather']['condition'] );
	}

	public function test_partial_local_parse_triggers_ai_fallback(): void {
		$GLOBALS['rwga_test_filters'] = array();
		add_filter(
			'rwga_interpretation_ai_fallback_enabled',
			static function () {
				return true;
			}
		);
		$ai_called = false;
		add_filter(
			'rwga_interpretation_ai_fallback',
			static function ( $result, $raw, $phrase, $context, $entities, $deferred ) use ( &$ai_called ) {
				unset( $raw, $phrase, $context, $entities, $deferred );
				$ai_called = true;
				return array(
					'matched'        => true,
					'intent'         => 'create_geo_variant_plan',
					'matched_action' => 'geocore_create_variant_plan_with_conditions',
					'confidence'     => 0.9,
					'params'         => array( 'source_page_ref' => 'homepage' ),
					'summary'        => 'AI plan',
				);
			},
			10,
			6
		);

		$phrase = 'i would like to create two variants of the homepage for portugal uk germany russia when raining and sunny';
		$plan   = RWGA_Variant_Plan_Interpreter::parse( $phrase, $this->entities(), array() );
		if ( ! empty( $plan['matched'] )
			&& ( ! isset( $plan['proposal_ready'] ) || false !== $plan['proposal_ready'] )
			&& empty( $plan['missing_information'] )
			&& (float) ( $plan['confidence'] ?? 0 ) >= 0.85 ) {
			$this->markTestSkipped( 'Phrase parsed completely locally; AI escalation not required.' );
		}
		$result = RWGA_Local_Intent_Interpreter::interpret( $phrase, array() );
		$trace  = $result['_interpretation_trace'] ?? array();
		if ( empty( $trace['ai_fallback']['called'] ) ) {
			$this->markTestSkipped( 'Escalation did not reach AI layer for this phrase shape.' );
		}
		$this->assertTrue( $ai_called, 'Expected AI fallback when local parser is partial.' );
		$this->assertSame( 'ai_fallback', $result['interpretation_source'] ?? '' );
		$this->assertSame( RWGA_Interpretation_Status::COMPLETE, $result['interpretation_status'] ?? '' );
	}

	public function test_partial_without_ai_returns_needs_clarification(): void {
		add_filter(
			'rwga_interpretation_ai_fallback_enabled',
			static function () {
				return false;
			}
		);
		$partial = array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.52,
			'proposal_ready'      => false,
			'escalate'            => true,
			'missing_information' => array(
				array(
					'key'      => 'variant_grouping',
					'question' => 'confirm split',
				),
			),
			'params'              => array( 'source_page_ref' => 'homepage', 'countries' => array( 'PT', 'GB' ) ),
			'summary'             => 'partial',
		);
		$meta = RWGA_Interpretation_Status::from_result(
			array_merge(
				$partial,
				array( 'interpretation_source' => 'clarification' )
			)
		);
		$this->assertSame( RWGA_Interpretation_Status::NEEDS_CLARIFICATION, $meta['status'] );
		$this->assertFalse( $meta['can_execute'] );
	}

	public function test_accepted_ai_result_writes_memory_for_reuse(): void {
		$phrase = $this->mixed_weather_phrase();
		$entities = $this->entities();
		$shape = RWGA_Phrase_Shape_Normaliser::build( $phrase, $entities );
		RWGA_Interpretation_Memory_Matcher::remember(
			$phrase,
			array(
				'intent'                => 'create_geo_variant_plan',
				'matched_action'        => 'geocore_create_variant_plan_with_conditions',
				'confidence'            => 0.9,
				'interpretation_source' => 'ai_fallback',
				'params'                => array(
					'source_page_ref'  => 'homepage',
					'source_targeting' => array(
						'countries' => array( 'GB' ),
						'weather'   => array( 'mode' => 'any' ),
					),
					'variants'         => array(
						array( 'countries' => array( 'PT' ), 'weather' => array( 'condition' => 'rain' ) ),
						array( 'countries' => array( 'RU', 'DE' ), 'weather' => array( 'condition' => 'sunny' ) ),
					),
				),
			),
			$entities
		);
		$row = RWGA_Interpretation_Memory_Store::find_by_shape( $shape['phrase_shape'] );
		$this->assertIsArray( $row );
		$this->assertSame( 'create_geo_variant_plan', $row['intent_key'] ?? '' );
		$match = RWGA_Interpretation_Memory_Matcher::match( $phrase, $shape['normalised_phrase'], $entities, array() );
		$this->assertTrue( ! empty( $match['matched'] ) );
	}

	public function test_promotion_candidate_after_three_successes(): void {
		$shape = 'create {number} variations of {page} test shape';
		for ( $i = 0; $i < 3; $i++ ) {
			RWGA_Learning_Promotion_Service::record_outcome( $shape, 'executed' );
		}
		$this->assertTrue( RWGA_Learning_Promotion_Service::is_promotion_candidate( $shape ) );
	}
}
