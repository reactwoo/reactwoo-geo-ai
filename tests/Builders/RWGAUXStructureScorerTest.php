<?php

use PHPUnit\Framework\TestCase;

/**
 * UX structure scorer tests.
 */
class RWGAUXStructureScorerTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function complete_page_context() {
		$sections = array(
			array(
				'id' => 's1', 'has_h1' => true, 'has_cta' => true, 'has_form' => false, 'has_media' => true,
				'classification' => array( 'type' => 'hero', 'confidence' => 0.9, 'signals' => array() ),
			),
			array(
				'id' => 's2', 'has_h1' => false, 'has_cta' => false, 'has_form' => false,
				'classification' => array( 'type' => 'trust', 'confidence' => 0.8, 'signals' => array() ),
			),
			array(
				'id' => 's3', 'has_h1' => false, 'has_cta' => true, 'has_form' => false,
				'classification' => array( 'type' => 'cta', 'confidence' => 0.7, 'signals' => array() ),
			),
		);
		return array(
			'sections' => $sections,
			'widgets'  => array(
				array( 'id' => 'b1', 'type' => 'button', 'content' => 'Start free trial', 'is_cta' => true ),
				array( 'id' => 'b2', 'type' => 'button', 'content' => 'Contact sales', 'is_cta' => true ),
			),
			'ctas'     => array(
				array( 'widget_id' => 'b1', 'label' => 'Start free trial' ),
				array( 'widget_id' => 'b2', 'label' => 'Contact sales' ),
			),
			'forms'    => array(),
		);
	}

	/**
	 * @return void
	 */
	public function test_complete_page_scores_higher_than_incomplete() {
		$complete   = RWGA_UX_Structure_Scorer::score( $this->complete_page_context() );
		$incomplete = RWGA_UX_Structure_Scorer::score(
			array(
				'sections' => array(),
				'widgets'  => array(),
				'ctas'     => array(),
				'forms'    => array(),
			)
		);
		$this->assertGreaterThan( $incomplete['overall_score'], $complete['overall_score'] );
	}

	/**
	 * @return void
	 */
	public function test_flags_missing_cta() {
		$result = RWGA_UX_Structure_Scorer::score(
			array(
				'sections' => array(
					array( 'classification' => array( 'type' => 'content' ) ),
				),
				'widgets' => array(),
				'ctas'    => array(),
				'forms'   => array(),
			)
		);
		$codes = array_column( $result['detected_issues'], 'code' );
		$this->assertContains( 'missing_cta', $codes );
	}

	/**
	 * @return void
	 */
	public function test_flags_missing_trust() {
		$result = RWGA_UX_Structure_Scorer::score(
			array(
				'sections' => array(
					array( 'classification' => array( 'type' => 'hero' ), 'has_h1' => true, 'has_cta' => true ),
				),
				'widgets' => array(),
				'ctas'    => array( array( 'label' => 'Go' ) ),
				'forms'   => array(),
			)
		);
		$codes = array_column( $result['detected_issues'], 'code' );
		$this->assertContains( 'missing_trust', $codes );
	}
}
