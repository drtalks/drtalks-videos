<?php
/**
 * Settings page HTML — Settings > DrTalks Videos
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$archive_slug   = get_option( 'drtalks_archive_slug', 'videos' );
$expert_slug    = get_option( 'drtalks_expert_slug', '' );
$sync_schedule  = get_option( 'drtalks_sync_schedule', 'daily' );
$next_cron      = function_exists( 'as_next_scheduled_action' )
	? as_next_scheduled_action( DrTalks_Scheduler::HOOK_SYNC_ALL, [], DrTalks_Scheduler::GROUP )
	: false;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'DrTalks Videos', 'drtalks-videos' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'drtalks_videos' ); ?>

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">
					<label for="drtalks_archive_slug"><?php esc_html_e( 'Archive Slug', 'drtalks-videos' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="drtalks_archive_slug"
						name="drtalks_archive_slug"
						value="<?php echo esc_attr( $archive_slug ); ?>"
						class="regular-text"
					/>
					<p class="description">
						<?php
						printf(
							esc_html__( 'Your videos will live at %s', 'drtalks-videos' ),
							'<code>' . esc_html( home_url( '/' . $archive_slug . '/' ) ) . '</code>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="drtalks_expert_slug"><?php esc_html_e( 'Expert Slug', 'drtalks-videos' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="drtalks_expert_slug"
						name="drtalks_expert_slug"
						value="<?php echo esc_attr( $expert_slug ); ?>"
						class="regular-text"
						placeholder="dr-jane-smith"
					/>
					<p class="description">
						<?php esc_html_e( 'Your DrTalks expert slug — the last part of your DrTalks profile URL (e.g. drtalks.com/experts/dr-jane-smith).', 'drtalks-videos' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="drtalks_sync_schedule"><?php esc_html_e( 'Sync Schedule', 'drtalks-videos' ); ?></label>
				</th>
				<td>
					<select id="drtalks_sync_schedule" name="drtalks_sync_schedule">
						<option value="daily" <?php selected( $sync_schedule, 'daily' ); ?>><?php esc_html_e( 'Daily', 'drtalks-videos' ); ?></option>
						<option value="weekly" <?php selected( $sync_schedule, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'drtalks-videos' ); ?></option>
						<option value="manual" <?php selected( $sync_schedule, 'manual' ); ?>><?php esc_html_e( 'Manual only', 'drtalks-videos' ); ?></option>
					</select>
					<?php if ( $next_cron ) : ?>
						<p class="description">
							<?php
							printf(
								esc_html__( 'Next scheduled sync: %s', 'drtalks-videos' ),
								esc_html( wp_date( 'Y-m-d H:i:s', $next_cron ) )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'drtalks-videos' ) ); ?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'Sync Now', 'drtalks-videos' ); ?></h2>
	<p>
		<?php esc_html_e( 'Run an immediate sync for the Expert Slug configured above.', 'drtalks-videos' ); ?>
	</p>
	<button
		id="drtalks-sync-now"
		class="button button-secondary"
		data-expert="<?php echo esc_attr( $expert_slug ); ?>"
		<?php echo $expert_slug ? '' : 'disabled'; ?>
	>
		<?php esc_html_e( 'Sync All Videos', 'drtalks-videos' ); ?>
	</button>
	<span id="drtalks-sync-status" style="margin-left:12px;display:none;"></span>
</div>
