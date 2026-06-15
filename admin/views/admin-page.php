<?php
/**
 * DrTalks Videos top-level admin page HTML shell.
 * JavaScript (admin-page.js) handles all dynamic content.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$drtalks_archive_enabled = (bool) get_option( 'drtalks_archive_enabled', true );
$drtalks_section_hidden  = $drtalks_archive_enabled ? '' : ' style="display:none;"';
?>
<div class="wrap drtalks-admin-wrap">
	<h1 class="drtalks-admin-title">
		DrTalks Videos
		<span id="drtalks-save-status" class="drtalks-save-status" style="opacity:0;"></span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=drtalks-videos-docs' ) ); ?>" class="page-title-action" style="margin-left:auto;">Documentation</a>
	</h1>

	<div class="drtalks-embed-instructions">
		<p>
			To embed a single video anywhere on your site, use the <strong>DrTalks Videos</strong> block in the editor,
			or paste this shortcode into any post or page:
		</p>
		<code>[drtalks_video slug="video-slug-here"]</code>
	</div>

	<div id="drtalks-permalink-warning" class="notice notice-warning inline" style="display:none;">
		<p>
			<strong>Pretty permalinks are not enabled.</strong>
			Video page URLs won't work until you go to
			<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Settings &rsaquo; Permalinks</a>
			and choose a URL structure (e.g. <code>/%postname%/</code>), then save.
		</p>
	</div>

	<!-- ============================================================
	     Box 1: Settings
	     ============================================================ -->
	<div class="drtalks-section" id="drtalks-section-archive">
		<h2>Settings</h2>

		<div class="drtalks-archive-toggle">
			<label class="drtalks-toggle-label">
				<input type="checkbox" id="drtalks-show-watch-button" <?php checked( get_option( 'drtalks_show_watch_button', 1 ) ); ?>>
				<span>Show "Watch on DrTalks" button</span>
			</label>
			<p class="description" style="margin:4px 0 0 0;">When enabled, a link to the original video on DrTalks.com is shown on each video page.</p>
		</div>

		<div class="drtalks-archive-toggle" style="margin-top:14px;">
			<label class="drtalks-toggle-label">
				<input type="checkbox" id="drtalks-archive-enabled" <?php checked( get_option( 'drtalks_archive_enabled', true ) ); ?>>
				<span>Enable Videos Archive</span>
			</label>
			<p class="description" style="margin:4px 0 0 0;">Enable a public video archive page on your site where visitors can browse all your videos.</p>
		</div>

		<div id="drtalks-archive-settings" class="drtalks-archive-settings"<?php echo $drtalks_section_hidden; ?>>
			<div class="drtalks-slug-row">
				<label for="drtalks-archive-slug"><strong>Archive URL slug</strong></label>
				<div class="drtalks-slug-input-wrap">
					<span class="drtalks-slug-prefix" id="drtalks-site-url-prefix"><?php echo esc_url( home_url( '/' ) ); ?></span>
					<input type="text" id="drtalks-archive-slug" class="regular-text" value="<?php echo esc_attr( get_option( 'drtalks_archive_slug', 'podcast' ) ); ?>">
					<span class="drtalks-slug-suffix">/</span>
				</div>
				<p class="description" id="drtalks-archive-url-preview"></p>
			</div>
		</div>

		<div id="drtalks-archive-grid-settings" class="drtalks-archive-settings"<?php echo $drtalks_section_hidden; ?> style="margin-top:16px;display:flex;gap:32px;flex-wrap:wrap;">
			<div>
				<label for="drtalks-videos-per-page"><strong>Videos per page</strong></label>
				<div style="margin-top:6px;">
					<input type="number" id="drtalks-videos-per-page" class="small-text" min="1" max="200" value="<?php echo esc_attr( get_option( 'drtalks_videos_per_page', 12 ) ); ?>">
				</div>
				<p class="description" style="margin-top:4px;">Number of videos shown per page on the archive.</p>
			</div>
			<div>
				<label for="drtalks-videos-per-row"><strong>Videos per row</strong></label>
				<div style="margin-top:6px;">
					<input type="number" id="drtalks-videos-per-row" class="small-text" min="1" max="6" value="<?php echo esc_attr( get_option( 'drtalks_videos_per_row', 4 ) ); ?>">
				</div>
				<p class="description" style="margin-top:4px;">Number of video columns on desktop (1–6).</p>
			</div>
		</div>

		<div id="drtalks-template-style-section" class="drtalks-template-style-section"<?php echo $drtalks_section_hidden; ?>>
			<h3>Single Video Page Layout</h3>
			<p class="description">Choose how individual video pages look when a visitor clicks through to a video.</p>

			<div class="drtalks-template-style-cards">

			<!-- Blog layout -->
			<label class="drtalks-style-card" data-style="theme">
				<input type="radio" name="drtalks_template_style" value="theme">
				<div class="drtalks-style-card-preview drtalks-preview--blog">
					<div class="drtalks-preview-bar"></div>
					<div class="drtalks-preview-lines drtalks-preview-title-row">
						<div class="drtalks-preview-line drtalks-preview-line--title"></div>
					</div>
					<div class="drtalks-preview-player drtalks-preview-player--blog"></div>
					<div class="drtalks-preview-lines">
						<div class="drtalks-preview-line"></div>
						<div class="drtalks-preview-line drtalks-preview-line--short"></div>
					</div>
					<div class="drtalks-preview-author-row">
						<div class="drtalks-preview-avatar"></div>
						<div class="drtalks-preview-lines drtalks-preview-author-lines">
							<div class="drtalks-preview-line drtalks-preview-line--short"></div>
						</div>
					</div>
					<div class="drtalks-preview-bar drtalks-preview-bar--footer"></div>
				</div>
				<strong>Blog Layout</strong>
				<span class="drtalks-style-card-desc">Single column, blog-style. All video information flows top to bottom — title, player, description, transcript, author.</span>
			</label>

			<!-- Video Page / YouTube style -->
			<label class="drtalks-style-card" data-style="video">
				<input type="radio" name="drtalks_template_style" value="video">
				<div class="drtalks-style-card-preview drtalks-preview--video">
					<div class="drtalks-preview-bar"></div>
					<div class="drtalks-preview-twocol">
						<div class="drtalks-preview-col-main">
							<div class="drtalks-preview-player drtalks-preview-player--video"></div>
							<div class="drtalks-preview-lines">
								<div class="drtalks-preview-line drtalks-preview-line--title"></div>
								<div class="drtalks-preview-line"></div>
								<div class="drtalks-preview-line drtalks-preview-line--short"></div>
							</div>
						</div>
						<div class="drtalks-preview-col-sidebar">
							<div class="drtalks-preview-line"></div>
							<div class="drtalks-preview-line drtalks-preview-line--short"></div>
							<div class="drtalks-preview-line"></div>
							<div class="drtalks-preview-line"></div>
							<div class="drtalks-preview-line drtalks-preview-line--short"></div>
							<div class="drtalks-preview-line"></div>
						</div>
					</div>
					<div class="drtalks-preview-bar drtalks-preview-bar--footer"></div>
				</div>
				<strong>Video Page Layout</strong>
				<span class="drtalks-style-card-desc">Two-column, YouTube-style. Player and main content on the left, transcript and related info in a sidebar on the right.</span>
			</label>

			</div>
		</div>

	</div><!-- #drtalks-section-archive -->

	<div id="drtalks-archive-content"<?php echo $drtalks_section_hidden; ?>>

	<hr class="drtalks-section-divider">

	<!-- ============================================================
	     Box 2: Individual Videos
	     ============================================================ -->
	<div class="drtalks-section" id="drtalks-section-videos">
		<h2>Individual Videos</h2>
		<p class="description">Add specific videos to embed anywhere using the DrTalks Videos block or <code>[drtalks_video slug="…"]</code> shortcode.</p>

		<div class="drtalks-columns">
			<div class="drtalks-col drtalks-col-left">
				<h3>Search DrTalks Videos</h3>
				<div class="drtalks-card-list drtalks-card-list--searchable">
					<div class="drtalks-card-list-search">
						<input type="search" id="drtalks-video-search" class="regular-text" placeholder="Search by title or expert name…">
					</div>
					<div id="drtalks-video-search-results" class="drtalks-card-list-inner">
						<p class="drtalks-empty-state">Search above to find videos to add.</p>
					</div>
				</div>
			</div>
			<div class="drtalks-col drtalks-col-right">
				<h3>Added Videos <span class="drtalks-section-count" id="drtalks-selected-count"></span></h3>
				<div id="drtalks-selected-videos" class="drtalks-card-list">
					<p class="drtalks-empty-state">No videos added yet. Search on the left to get started.</p>
				</div>
			</div>
		</div>
	</div>

	<hr class="drtalks-section-divider">

	<!-- ============================================================
	     Box 3: Expert Auto-Sync
	     ============================================================ -->
	<div class="drtalks-section" id="drtalks-section-experts">
		<h2>Expert Auto-Sync</h2>
		<p class="description">
			Add up to 5 DrTalks experts. Their videos sync automatically on your chosen schedule.
			Videos you hide won't appear on your site and won't be re-synced.
		</p>

		<div class="drtalks-sync-schedule-row">
			<label for="drtalks-sync-schedule"><strong>Sync schedule</strong></label>
			<select id="drtalks-sync-schedule">
				<option value="hourly"     <?php selected( get_option( 'drtalks_sync_schedule', 'daily' ), 'hourly' ); ?>>Hourly</option>
				<option value="twicedaily" <?php selected( get_option( 'drtalks_sync_schedule', 'daily' ), 'twicedaily' ); ?>>Twice daily</option>
				<option value="daily"      <?php selected( get_option( 'drtalks_sync_schedule', 'daily' ), 'daily' ); ?>>Daily</option>
				<option value="weekly"     <?php selected( get_option( 'drtalks_sync_schedule', 'daily' ), 'weekly' ); ?>>Weekly</option>
				<option value="manual"     <?php selected( get_option( 'drtalks_sync_schedule', 'daily' ), 'manual' ); ?>>Manual only</option>
			</select>
			<p class="description">New videos from your experts are fetched on this schedule.</p>
		</div>

		<div id="drtalks-global-sync-status" class="drtalks-global-sync-status">
			<span class="drtalks-sync-status-dot"></span>
			<span class="drtalks-sync-status-text">Loading sync status…</span>
		</div>

		<div class="drtalks-card-list drtalks-card-list--searchable">
			<div class="drtalks-card-list-search">
				<input type="search" id="drtalks-expert-search" class="regular-text" placeholder="Search experts by name…">
				<p id="drtalks-add-expert-error" class="drtalks-error-msg" style="display:none;margin:6px 0 0;"></p>
			</div>
			<div id="drtalks-expert-search-results" class="drtalks-card-list-inner"></div>
		</div>

		<div id="drtalks-expert-cards" class="drtalks-expert-cards">
			<!-- Expert cards rendered by JS -->
		</div>

		<div id="drtalks-expert-video-section" style="display:none;">
			<div class="drtalks-columns">
				<div class="drtalks-col drtalks-col-left">
					<h3>Hidden Videos <span class="description" style="font-size:13px;font-weight:400;">— not shown on your site and won't be re-synced.</span></h3>
					<div id="drtalks-hidden-videos" class="drtalks-card-list">
						<p class="drtalks-empty-state">No hidden videos.</p>
					</div>
				</div>
				<div class="drtalks-col drtalks-col-right">
					<h3>Auto-Synced Videos <span class="drtalks-section-count" id="drtalks-expert-videos-count"></span></h3>
					<div id="drtalks-expert-videos" class="drtalks-card-list">
						<p class="drtalks-empty-state">Sync in progress… videos will appear here shortly.</p>
					</div>
				</div>
			</div>
		</div>

	</div>

	</div><!-- #drtalks-archive-content -->


</div>

<!-- Reusable confirm modal -->
<div id="drtalks-confirm-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;">
	<div style="background:#fff;border-radius:4px;padding:24px 28px;max-width:400px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.2);">
		<p id="drtalks-confirm-modal-message" style="margin:0 0 20px;font-size:14px;line-height:1.5;"></p>
		<div style="display:flex;gap:8px;justify-content:flex-end;">
			<button id="drtalks-confirm-modal-cancel"  class="button">Cancel</button>
			<button id="drtalks-confirm-modal-confirm" class="button button-primary" style="background:#b32d2e;border-color:#b32d2e;">Yes, Remove</button>
		</div>
	</div>
</div>
