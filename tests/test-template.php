<?php
/**
 * Test 2 — Template XSS escaping.
 *
 * Stores a meta value containing an XSS payload, then renders the
 * single-drtalks_video.php template and asserts the <script> tag is escaped.
 *
 * @package DrTalksVideos
 */

use WP_UnitTestCase;

class Test_Template extends WP_UnitTestCase {

	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$this->post_id = self::factory()->post->create( [
			'post_type'   => 'drtalks_video',
			'post_status' => 'publish',
			'post_title'  => 'XSS Test Video',
		] );

		$xss = '<script>alert(1)</script>';

		update_post_meta( $this->post_id, '_drtalks_embed_url',          'https://drtalks.com/embed/videos/test-slug' );
		update_post_meta( $this->post_id, '_drtalks_expert_name',        $xss );
		update_post_meta( $this->post_id, '_drtalks_expert_title',       $xss );
		update_post_meta( $this->post_id, '_drtalks_expert_credentials', $xss );
		update_post_meta( $this->post_id, '_drtalks_expert_bio',         $xss );
		update_post_meta( $this->post_id, '_drtalks_description',        $xss );
		update_post_meta( $this->post_id, '_drtalks_transcript',         $xss );
	}

	public function test_no_raw_script_tag_in_output(): void {
		global $post, $wp_query;

		// Boot the post loop as if viewing this post.
		$post     = get_post( $this->post_id );
		$wp_query = new WP_Query( [ 'p' => $this->post_id, 'post_type' => 'drtalks_video' ] );
		setup_postdata( $post );

		ob_start();
		// Simulate template include without running get_header/get_footer.
		// We include only the body portion (between those calls).
		$template = DRTALKS_VIDEOS_DIR . 'templates/single-drtalks_video.php';
		if ( file_exists( $template ) ) {
			// Stub out header/footer so they don't fail in unit test context.
			add_filter( 'get_header', '__return_false' );
			add_filter( 'get_footer', '__return_false' );
			include $template;
		}
		$output = ob_get_clean();

		wp_reset_postdata();

		$this->assertStringNotContainsString(
			'<script>',
			$output,
			'Raw <script> tag must not appear in template output'
		);
	}
}
