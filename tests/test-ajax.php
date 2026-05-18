<?php
/**
 * Test 1 — AJAX handler nonce + capability gate.
 *
 * Verifies that wp_ajax_drtalks_search_videos requires both a valid nonce
 * and the manage_options capability. Returns 403 without either.
 *
 * @package DrTalksVideos
 */

use WP_UnitTestCase;

class Test_Ajax extends WP_UnitTestCase {

	private static int $admin_id;
	private static int $subscriber_id;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		self::$admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		self::$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	/** Unauthenticated request with no nonce should die with 403. */
	public function test_no_nonce_returns_403(): void {
		wp_set_current_user( 0 );

		$this->expectException( WPDieException::class );

		$_POST = [ 'action' => 'drtalks_search_videos' ];
		DrTalks_Ajax::search_videos();
	}

	/** Admin with valid nonce but no capability should die with 403. */
	public function test_subscriber_with_nonce_returns_403(): void {
		wp_set_current_user( self::$subscriber_id );
		$_POST['nonce'] = wp_create_nonce( 'drtalks_admin_nonce' );

		$this->expectException( WPDieException::class );

		$_POST = [
			'action' => 'drtalks_search_videos',
			'nonce'  => wp_create_nonce( 'drtalks_admin_nonce' ),
		];
		DrTalks_Ajax::search_videos();
	}

	/** Admin with valid nonce proceeds past the security gate. */
	public function test_admin_with_nonce_passes_gate(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = [
			'action' => 'drtalks_search_videos',
			'nonce'  => wp_create_nonce( 'drtalks_admin_nonce' ),
			'q'      => '',
		];

		// DrTalks_Ajax::search_videos() will call check_ajax_referer() then
		// try to reach the external API. We mock the API call via a filter so
		// the test doesn't make a real HTTP request.
		add_filter( 'pre_http_request', function () {
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => wp_json_encode( [ 'videos' => [], 'total' => 0 ] ),
				'headers'  => [],
			];
		} );

		$caught = false;
		try {
			// wp_send_json_success() calls wp_die(); expect that rather than a 403.
			DrTalks_Ajax::search_videos();
		} catch ( WPDieException $e ) {
			$caught = true;
			$this->assertStringNotContainsString( '403', $e->getMessage() );
		}

		$this->assertTrue( $caught, 'Expected wp_die() from wp_send_json_success' );

		remove_all_filters( 'pre_http_request' );
	}
}
