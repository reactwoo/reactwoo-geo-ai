<?php

use PHPUnit\Framework\TestCase;

/**
 * Section classifier tests.
 */
class RWGASectionClassifierTest extends TestCase {

	/**
	 * @return void
	 */
	public function test_detects_hero() {
		$ctx = array(
			'sections' => array(
				array(
					'id'           => 's1',
					'index'        => 0,
					'has_h1'       => true,
					'has_cta'      => true,
					'has_form'     => false,
					'has_media'    => true,
					'widget_count' => 3,
					'heading'      => 'Welcome',
				),
			),
			'widgets' => array(
				array( 'id' => 'h1', 'section_id' => 's1', 'type' => 'heading', 'content' => 'Welcome', 'settings' => array( 'header_size' => 'h1' ) ),
				array( 'id' => 'b1', 'section_id' => 's1', 'type' => 'button', 'content' => 'Start', 'is_cta' => true ),
			),
		);
		$classified = RWGA_Section_Classifier::classify( $ctx );
		$this->assertSame( 'hero', $classified[0]['classification']['type'] );
		$this->assertGreaterThan( 0.5, $classified[0]['classification']['confidence'] );
	}

	/**
	 * @return void
	 */
	public function test_detects_faq() {
		$ctx = array(
			'sections' => array(
				array( 'id' => 's1', 'index' => 0, 'widget_count' => 1 ),
			),
			'widgets' => array(
				array( 'id' => 'a1', 'section_id' => 's1', 'type' => 'accordion', 'content' => 'Q1 | Q2' ),
			),
		);
		$classified = RWGA_Section_Classifier::classify( $ctx );
		$this->assertSame( 'faq', $classified[0]['classification']['type'] );
	}

	/**
	 * @return void
	 */
	public function test_detects_pricing() {
		$ctx = array(
			'sections' => array( array( 'id' => 's1', 'index' => 0, 'widget_count' => 1 ) ),
			'widgets'  => array(
				array( 'id' => 'p1', 'section_id' => 's1', 'type' => 'price-table', 'content' => 'Pro $99' ),
			),
		);
		$classified = RWGA_Section_Classifier::classify( $ctx );
		$this->assertSame( 'pricing', $classified[0]['classification']['type'] );
	}

	/**
	 * @return void
	 */
	public function test_detects_testimonials() {
		$ctx = array(
			'sections' => array( array( 'id' => 's1', 'index' => 0, 'widget_count' => 1 ) ),
			'widgets'  => array(
				array( 'id' => 't1', 'section_id' => 's1', 'type' => 'testimonial', 'content' => 'Great product' ),
			),
		);
		$classified = RWGA_Section_Classifier::classify( $ctx );
		$this->assertSame( 'testimonials', $classified[0]['classification']['type'] );
	}

	/**
	 * @return void
	 */
	public function test_unknown_section_safe() {
		$ctx = array(
			'sections' => array( array( 'id' => 's1', 'index' => 0, 'widget_count' => 0 ) ),
			'widgets'  => array(),
		);
		$classified = RWGA_Section_Classifier::classify( $ctx );
		$this->assertContains( $classified[0]['classification']['type'], array( 'unknown', 'content' ) );
	}
}
