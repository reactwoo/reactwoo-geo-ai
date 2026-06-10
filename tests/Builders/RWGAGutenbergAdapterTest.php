<?php

use PHPUnit\Framework\TestCase;

/**
 * Gutenberg adapter tests.
 */
class RWGAGutenbergAdapterTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rwga_test_posts']     = array();
		$GLOBALS['rwga_test_post_meta'] = array();
	}

	/**
	 * @return void
	 */
	public function test_parses_blocks_and_extracts_headings() {
		$content = '<!-- wp:heading {"level":1,"content":"Hero title"} /-->' . "\n"
			. '<!-- wp:paragraph {"content":"Lead copy"} /-->' . "\n"
			. '<!-- wp:button {"text":"Buy now","url":"https://example.com"} /-->';

		$this->seed_post( 201, $content );
		$adapter = new RWGA_Gutenberg_Adapter();
		$this->assertTrue( $adapter->supports( 201 ) );

		$widgets = $adapter->extract_widgets( 201 );
		$types   = array_column( $widgets, 'type' );
		$this->assertContains( 'heading', $types );
		$this->assertContains( 'button', $types );
	}

	/**
	 * @return void
	 */
	public function test_classic_fallback_when_no_blocks() {
		$this->seed_post( 202, '<p>Classic only</p>' );
		$adapter = new RWGA_Default_Post_Content_Adapter();
		$this->assertTrue( $adapter->supports( 202 ) );
		$ctx = $adapter->extract_page_context( 202 );
		$this->assertSame( 'classic', $ctx['builder'] );
		$this->assertNotEmpty( $ctx['content_blocks'] );
	}

	/**
	 * @param int    $id      Post ID.
	 * @param string $content Post content.
	 * @return void
	 */
	private function seed_post( $id, $content ) {
		$post                = new WP_Post();
		$post->ID            = $id;
		$post->post_title    = 'Gutenberg Page';
		$post->post_content  = $content;
		$post->post_type     = 'page';
		$GLOBALS['rwga_test_posts'][ $id ] = $post;
	}
}
