<?php
/**
 * Debug logger for DrTalks Videos plugin.
 *
 * Enabled by adding to wp-config.php:
 *   define( 'DRTALKS_DEBUG', true );
 *
 * Logs are written to wp-content/drtalks-debug.log.
 * The admin debug tab is always visible at DrTalks Videos → Debug.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DrTalks_Debug {

	const LOG_FILE = 'drtalks-debug.log';
	const MAX_LOG_BYTES = 512000; // 500 KB — rotate when exceeded.

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Is debug logging enabled?
	 */
	public static function enabled(): bool {
		return defined( 'DRTALKS_DEBUG' ) && DRTALKS_DEBUG;
	}

	/**
	 * Write a timestamped log entry.
	 *
	 * @param string $message
	 * @param array  $context  Any extra data to JSON-encode next to the message.
	 * @param string $level    'INFO' | 'WARN' | 'ERROR'
	 */
	public static function log( string $message, array $context = [], string $level = 'INFO' ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$path = self::log_path();
		self::maybe_rotate( $path );

		$line = sprintf(
			"[%s] [%s] %s%s\n",
			current_time( 'Y-m-d H:i:s' ),
			$level,
			$message,
			$context ? ' ' . wp_json_encode( $context ) : ''
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	public static function info( string $message, array $context = [] ): void {
		self::log( $message, $context, 'INFO' );
	}

	public static function warn( string $message, array $context = [] ): void {
		self::log( $message, $context, 'WARN' );
	}

	public static function error( string $message, array $context = [] ): void {
		self::log( $message, $context, 'ERROR' );
		// Also push into PHP error log so it surfaces even without DRTALKS_DEBUG.
		error_log( '[DrTalks] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
	}

	/**
	 * Log an outbound API request + response summary.
	 *
	 * @param string           $url
	 * @param array|WP_Error   $response  Raw wp_remote_get result or WP_Error.
	 * @param float            $elapsed   Seconds.
	 */
	public static function log_api( string $url, $response, float $elapsed = 0.0 ): void {
		if ( is_wp_error( $response ) ) {
			self::error( 'API request failed', [
				'url'     => $url,
				'error'   => $response->get_error_message(),
				'elapsed' => round( $elapsed, 3 ),
			] );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			return; // Successful calls are silent.
		}

		$body = wp_remote_retrieve_body( $response );
		$preview = strlen( $body ) > 300 ? substr( $body, 0, 300 ) . '…' : $body;
		self::warn( 'API response', [
			'url'     => $url,
			'status'  => $code,
			'body'    => $preview,
			'elapsed' => round( $elapsed, 3 ),
		] );
	}

	/**
	 * Log a sync operation result.
	 */
	public static function log_sync( string $expert_slug, array $result ): void {
		$level = $result['error'] ? 'ERROR' : 'INFO';
		self::log( "Sync finished for expert: $expert_slug", $result, $level );
	}

	/**
	 * Log an AJAX handler entry (action + redacted POST data).
	 */
	public static function log_ajax( string $action ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$safe = [];
		foreach ( $_POST as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( $k === 'nonce' ) {
				$safe[ $k ] = '***';
			} else {
				$safe[ $k ] = is_string( $v ) ? substr( sanitize_text_field( $v ), 0, 100 ) : gettype( $v );
			}
		}
		self::info( "AJAX: $action", $safe );
	}

	// -------------------------------------------------------------------------
	// Admin debug tab
	// -------------------------------------------------------------------------

	public static function init_admin(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_debug_page' ] );
	}

	public static function add_debug_page(): void {
		add_submenu_page(
			'drtalks-videos',
			'DrTalks Debug',
			'Debug',
			'manage_options',
			'drtalks-debug',
			[ __CLASS__, 'render_debug_page' ]
		);
	}

	public static function render_debug_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle log-clear action.
		if ( isset( $_POST['drtalks_clear_log'] ) && check_admin_referer( 'drtalks_clear_log' ) ) {
			self::clear_log();
			echo '<div class="notice notice-success"><p>Log cleared.</p></div>';
		}

		// Handle manual sync trigger — runs the fan-out immediately, which queues
		// per-expert chunks via Action Scheduler.
		if ( isset( $_POST['drtalks_run_cron'] ) && check_admin_referer( 'drtalks_run_cron' ) ) {
			DrTalks_Scheduler::run_sync_all_experts();
			echo '<div class="notice notice-success"><p>Sync fan-out triggered. Watch progress at <a href="' . esc_url( admin_url( 'tools.php?page=action-scheduler&s=&status=&action-group=drtalks' ) ) . '">Tools &rsaquo; Scheduled Actions</a>.</p></div>';
		}

		// Handle schedule-orphan-cleanup action.
		if ( isset( $_POST['drtalks_schedule_orphan_cleanup'] ) && check_admin_referer( 'drtalks_schedule_orphan_cleanup' ) ) {
			DrTalks_Scheduler::queue_cleanup_orphans();
			echo '<div class="notice notice-success"><p>Orphan cleanup scheduled. Orphaned posts will be permanently deleted shortly via Action Scheduler.</p></div>';
		}

		$log_path    = self::log_path();
		$log_exists  = file_exists( $log_path );
		$log_size    = $log_exists ? size_format( filesize( $log_path ) ) : '—';
		$log_content = '';
		if ( $log_exists ) {
			// Show last 100 lines.
			$lines       = file( $log_path );
			$last        = array_slice( $lines, -100 );
			$log_content = implode( '', array_reverse( $last ) );
		}

		$options = [
			'drtalks_archive_enabled'     => get_option( 'drtalks_archive_enabled' ),
			'drtalks_archive_slug'        => get_option( 'drtalks_archive_slug' ),
			'drtalks_expert_slugs'        => get_option( 'drtalks_expert_slugs' ),
			'drtalks_selected_videos'     => get_option( 'drtalks_selected_videos' ),
			'drtalks_hidden_videos'       => get_option( 'drtalks_hidden_videos' ),
			'drtalks_sync_schedule'       => get_option( 'drtalks_sync_schedule' ),
			'drtalks_video_template_style'=> get_option( 'drtalks_video_template_style' ),
			'drtalks_experts_meta'        => get_option( 'drtalks_experts_meta' ),
		];

		$cron_next   = function_exists( 'as_next_scheduled_action' )
			? as_next_scheduled_action( DrTalks_Scheduler::HOOK_SYNC_ALL, [], DrTalks_Scheduler::GROUP )
			: false;
		$cpt_count   = wp_count_posts( 'drtalks_video' )->publish ?? 0;
		$php_version = PHP_VERSION;
		$wp_version  = get_bloginfo( 'version' );
		$debug_on    = self::enabled() ? '<span style="color:green">YES — logging to ' . esc_html( $log_path ) . '</span>' : '<span style="color:#888">NO — add <code>define(\'DRTALKS_DEBUG\', true);</code> to wp-config.php to enable</span>';

		?>
		<div class="wrap">
			<h1>DrTalks Videos — Debug</h1>

			<h2>Environment</h2>
			<table class="widefat fixed" style="max-width:700px;">
				<tbody>
					<tr><th>Plugin version</th><td><?php echo esc_html( DRTALKS_VIDEOS_VERSION ); ?></td></tr>
					<tr><th>PHP</th><td><?php echo esc_html( $php_version ); ?></td></tr>
					<tr><th>WordPress</th><td><?php echo esc_html( $wp_version ); ?></td></tr>
					<tr><th>API base URL</th><td><?php echo esc_html( DRTALKS_API_URL ); ?></td></tr>
					<tr><th>Debug logging</th><td><?php echo $debug_on; // phpcs:ignore ?></td></tr>
					<tr><th>CPT posts (publish)</th><td><?php echo esc_html( $cpt_count ); ?></td></tr>
					<tr><th>Next cron run</th><td><?php echo $cron_next ? esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $cron_next ) ) ) : '<em>not scheduled</em>'; ?></td></tr>
					<tr><th>Permalink structure</th><td><?php echo get_option( 'permalink_structure' ) ? esc_html( get_option( 'permalink_structure' ) ) : '<span style="color:red">PLAIN (video pages will break!)</span>'; ?></td></tr>
					<tr><th>blocks/build/index.js</th><td><?php echo file_exists( DRTALKS_VIDEOS_DIR . 'blocks/build/index.js' ) ? '<span style="color:green">exists</span>' : '<span style="color:red">MISSING — Gutenberg block editor UI disabled</span>'; ?></td></tr>
				</tbody>
			</table>

			<h2>Stored Options</h2>
			<table class="widefat fixed" style="max-width:700px;">
				<thead><tr><th>Option</th><th>Value</th></tr></thead>
				<tbody>
					<?php foreach ( $options as $key => $val ) : ?>
					<tr>
						<td><code><?php echo esc_html( $key ); ?></code></td>
						<td><pre style="margin:0;white-space:pre-wrap;font-size:11px;"><?php echo esc_html( is_string( $val ) ? $val : wp_json_encode( $val ) ); ?></pre></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Transients</h2>
			<?php
			$sync_error = get_transient( 'drtalks_sync_error' );
			$slugs      = json_decode( get_option( 'drtalks_expert_slugs', '[]' ), true );
			echo '<table class="widefat fixed" style="max-width:700px;"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>';
			echo '<tr><td><code>drtalks_sync_error</code></td><td>' . ( $sync_error ? esc_html( $sync_error ) : '<em>not set</em>' ) . '</td></tr>';
			if ( is_array( $slugs ) ) {
				foreach ( $slugs as $slug ) {
					$status = get_transient( 'drtalks_sync_status_' . $slug );
					echo '<tr><td><code>drtalks_sync_status_' . esc_html( $slug ) . '</code></td><td>' . ( $status ? esc_html( wp_json_encode( $status ) ) : '<em>not set</em>' ) . '</td></tr>';
				}
			}
			echo '</tbody></table>';
			?>

		<h2>Orphaned Video Posts</h2>
		<p>Posts in the <code>drtalks_video</code> database table that are no longer tracked by any expert or individual video selection. They are hidden from the front-end but waste database space. The plugin automatically removes them once per day.</p>
		<?php
		$orphan_ids   = DrTalks_Scheduler::get_orphan_post_ids();
		$orphan_count = count( $orphan_ids );
		$next_cleanup = function_exists( 'as_next_scheduled_action' )
			? as_next_scheduled_action( DrTalks_Scheduler::HOOK_CLEANUP_ORPHANS, [], DrTalks_Scheduler::GROUP )
			: false;
		$next_cleanup_str = $next_cleanup
			? get_date_from_gmt( date( 'Y-m-d H:i:s', $next_cleanup ) )
			: '<em>not scheduled — will be set up on next page load</em>';
		?>
		<table class="widefat fixed" style="max-width:700px;margin-bottom:12px;">
			<tbody>
				<tr><th>Orphaned posts found</th><td><strong><?php echo esc_html( $orphan_count ); ?></strong></td></tr>
				<tr><th>Next automatic cleanup</th><td><?php echo $next_cleanup_str; // phpcs:ignore ?></td></tr>
			</tbody>
		</table>
		<?php if ( $orphan_count > 0 ) : ?>
		<details style="margin-bottom:12px;">
			<summary style="cursor:pointer;font-weight:600;">Show <?php echo esc_html( $orphan_count ); ?> orphaned post<?php echo $orphan_count === 1 ? '' : 's'; ?></summary>
			<table class="widefat fixed" style="max-width:700px;margin-top:8px;">
				<thead><tr><th>ID</th><th>Title</th><th>Slug</th></tr></thead>
				<tbody>
					<?php foreach ( $orphan_ids as $oid ) : ?>
					<tr>
						<td><?php echo esc_html( $oid ); ?></td>
						<td><?php echo esc_html( get_the_title( $oid ) ); ?></td>
						<td><code><?php echo esc_html( get_post_meta( $oid, '_drtalks_video_slug', true ) ?: '—' ); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<form method="post" style="display:inline-block;">
			<?php wp_nonce_field( 'drtalks_schedule_orphan_cleanup' ); ?>
			<input type="hidden" name="drtalks_schedule_orphan_cleanup" value="1">
			<button class="button button-secondary" type="submit" style="color:#b32d2e;border-color:#b32d2e;"
				onclick="return confirm('Schedule immediate deletion of <?php echo esc_js( $orphan_count ); ?> orphaned post<?php echo $orphan_count === 1 ? '' : 's'; ?>?')">
				Schedule Cleanup Now (<?php echo esc_html( $orphan_count ); ?> post<?php echo $orphan_count === 1 ? '' : 's'; ?>)
			</button>
		</form>
		<?php else : ?>
		<p style="color:green;">&#10003; No orphaned posts found.</p>
		<?php endif; ?>

		<h2>Actions</h2>
		<form method="post" style="display:inline-block;margin-right:12px;">
			<?php wp_nonce_field( 'drtalks_run_cron' ); ?>
			<input type="hidden" name="drtalks_run_cron" value="1">
			<button class="button button-primary" type="submit">Run Cron Sync Now</button>
		</form>
		<form method="post" style="display:inline-block;">
			<?php wp_nonce_field( 'drtalks_clear_log' ); ?>
			<input type="hidden" name="drtalks_clear_log" value="1">
			<button class="button" type="submit" onclick="return confirm('Clear the entire debug log?')">Clear Log</button>
		</form>

		<h2>Debug Log <?php echo $log_exists ? '(' . esc_html( $log_size ) . ')' : '(no log file)'; ?></h2>
			<?php if ( ! self::enabled() ) : ?>
			<p style="color:#888">Logging is disabled. Add <code>define('DRTALKS_DEBUG', true);</code> to <code>wp-config.php</code> to start recording.</p>
			<?php elseif ( $log_content ) : ?>
			<textarea readonly style="width:100%;height:400px;font-family:monospace;font-size:11px;background:#1e1e1e;color:#d4d4d4;padding:10px;border:0;"><?php echo esc_textarea( $log_content ); ?></textarea>
			<?php else : ?>
			<p><em>Log is empty.</em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public static function log_path(): string {
		return WP_CONTENT_DIR . '/' . self::LOG_FILE;
	}

	private static function maybe_rotate( string $path ): void {
		if ( file_exists( $path ) && filesize( $path ) > self::MAX_LOG_BYTES ) {
			$backup = $path . '.1';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			rename( $path, $backup );
		}
	}

	private static function clear_log(): void {
		$path = self::log_path();
		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, '' );
		}
	}
}
