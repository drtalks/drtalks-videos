<?php
/**
 * DrTalks Videos — Documentation page.
 *
 * Same content as README.md, formatted for the WordPress admin.
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap drtalks-docs-wrap">
	<h1>DrTalks Videos — Documentation</h1>

	<div class="drtalks-docs-layout">

		<!-- Sticky table of contents -->
		<nav class="drtalks-docs-toc" aria-label="Table of contents">
			<strong>On this page</strong>
			<ul>
				<li><a href="#requirements">Requirements</a></li>
				<li><a href="#admin-ui">Admin UI</a>
					<ul>
						<li><a href="#video-archive">Video Archive</a></li>
						<li><a href="#individual-videos">Individual Videos</a></li>
						<li><a href="#expert-sync">Expert Auto-Sync</a></li>
					</ul>
				</li>
				<li><a href="#shortcodes">Shortcodes</a>
					<ul>
						<li><a href="#shortcode-full">Full embed</a></li>
						<li><a href="#shortcode-atomic">Atomic shortcodes</a></li>
					</ul>
				</li>
				<li><a href="#block">Gutenberg Block</a></li>
				<li><a href="#theme">Theme Integration</a>
					<ul>
						<li><a href="#template-overrides">Template overrides</a></li>
					</ul>
				</li>
				<li><a href="#troubleshooting">Troubleshooting</a></li>
			</ul>
		</nav>

		<div class="drtalks-docs-content">

			<h2 id="requirements">Requirements</h2>
			<ul>
				<li>WordPress 6.4+</li>
				<li>PHP 8.0+</li>
				<li>A WordPress permalink structure other than <strong>Plain</strong> (<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Settings → Permalinks</a>). Individual video pages return 404s on Plain.</li>
			</ul>
			<p>Background jobs (sync, transcript fetch) run via Action Scheduler, bundled with the plugin. Monitor them at <a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler&action-group=drtalks' ) ); ?>">Tools → Scheduled Actions</a>.</p>

			<h2 id="admin-ui">Admin UI</h2>
			<p>The <a href="<?php echo esc_url( admin_url( 'admin.php?page=drtalks-videos' ) ); ?>">Settings page</a> has three boxes.</p>

			<h3 id="video-archive">1. Video Archive</h3>
			<p>A toggle that enables or disables a public archive page on your site. When <strong>off</strong>, the other two boxes are hidden — you can still embed individual videos via shortcode or block, you just won't have a <code>/{slug}/</code> browse page.</p>
			<p>When <strong>on</strong>:</p>
			<ul>
				<li><strong>Archive URL slug</strong> controls the archive permalink (e.g. <code>/videos/</code>) and individual video URLs (<code>/videos/{video-slug}/</code>). Auto-saves as you type.</li>
				<li>A copy-ready shortcode is always shown at the bottom so you can paste it anywhere.</li>
			</ul>

			<h3 id="individual-videos">2. Individual Videos</h3>
			<p>Search the DrTalks catalogue and add specific videos one at a time. Each added video becomes a post on your site that you can embed via shortcode or block. Removing a video deletes it from your site.</p>
			<p>Videos that belong to an <strong>active expert</strong> are managed in Box 3 instead and don't appear here.</p>

			<h3 id="expert-sync">3. Expert Auto-Sync</h3>
			<p>Add up to 5 DrTalks experts. Their videos sync automatically on the schedule you choose (hourly, twice daily, daily, weekly, or manual).</p>
			<p>Each expert card shows:</p>
			<ul>
				<li>Their photo, name, and progress (e.g. <em>10 of 47 videos synced</em>)</li>
				<li><strong>Sync Now</strong> — forces an immediate full sync</li>
				<li><strong>Remove</strong> — deletes the expert and all their auto-synced videos</li>
			</ul>
			<p><strong>Hidden Videos</strong> column: click "Hide" on any auto-synced video to keep it off your site. Hidden videos won't be re-added by future syncs. Click "Unhide" to restore.</p>
			<p><strong>Sync schedule</strong>: changes apply immediately.</p>

			<h2 id="shortcodes">Shortcodes</h2>

			<h3 id="shortcode-full">Full embed</h3>
			<p><code>[drtalks_video slug="…"]</code> renders the complete video block (player, title, description, transcript, expert bio). Each section can be toggled off:</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Attribute</th>
						<th>Default</th>
						<th>Setting to <code>0</code></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>slug</code></td><td><em>(required)</em></td><td>—</td></tr>
					<tr><td><code>video</code></td><td><code>1</code></td><td>Hides the player and "Watch on DrTalks" button</td></tr>
					<tr><td><code>title</code></td><td><code>1</code></td><td>Hides the title</td></tr>
					<tr><td><code>description</code></td><td><code>1</code></td><td>Hides the description</td></tr>
					<tr><td><code>transcript</code></td><td><code>1</code></td><td>Hides the transcript dropdown</td></tr>
					<tr><td><code>author</code></td><td><code>1</code></td><td>Hides the expert bio</td></tr>
				</tbody>
			</table>
			<p>Accepted falsy values: <code>0</code>, <code>false</code>, <code>no</code>. Anything else is truthy.</p>
			<p>Example — show only the title, description, and author:</p>
			<pre><code>[drtalks_video slug="dr-jane-smith-on-gut-health" video="0" transcript="0"]</code></pre>

			<h3 id="shortcode-atomic">Atomic shortcodes</h3>
			<p>For building custom layouts (in posts, pages, or theme templates), use these single-purpose shortcodes:</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Shortcode</th>
						<th>What it outputs</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>[drtalks_video_iframe slug="…"]</code></td><td>Just the video player iframe</td></tr>
					<tr><td><code>[drtalks_video_title slug="…"]</code></td><td>Title as plain text</td></tr>
					<tr><td><code>[drtalks_video_title slug="…" tag="h2"]</code></td><td>Title wrapped in any heading (<code>h1</code>–<code>h6</code>) or <code>p</code> / <code>span</code> / <code>div</code></td></tr>
					<tr><td><code>[drtalks_video_title slug="…" tag="h2" class="hero"]</code></td><td>With extra CSS class</td></tr>
					<tr><td><code>[drtalks_video_description slug="…"]</code></td><td>Description (HTML)</td></tr>
					<tr><td><code>[drtalks_video_transcript slug="…"]</code></td><td>Transcript HTML</td></tr>
					<tr><td><code>[drtalks_video_transcript slug="…" dropdown="1"]</code></td><td>Transcript inside a collapsible <code>&lt;details&gt;</code> block</td></tr>
					<tr><td><code>[drtalks_video_transcript slug="…" dropdown="1" label="Show transcript"]</code></td><td>Custom dropdown label</td></tr>
					<tr><td><code>[drtalks_expert_name slug="…"]</code></td><td>Expert name (plain text)</td></tr>
					<tr><td><code>[drtalks_expert_photo slug="…"]</code></td><td>Expert photo <code>&lt;img&gt;</code></td></tr>
					<tr><td><code>[drtalks_expert_photo slug="…" class="avatar" alt="…"]</code></td><td>With custom CSS class / alt text</td></tr>
					<tr><td><code>[drtalks_expert_title slug="…"]</code></td><td>Expert's professional title (plain text)</td></tr>
					<tr><td><code>[drtalks_expert_bio slug="…"]</code></td><td>Expert bio with line breaks preserved</td></tr>
				</tbody>
			</table>
			<p>Missing video or empty field returns nothing — safe to drop into a template without breaking the layout.</p>

			<h2 id="block">Gutenberg Block</h2>
			<p>Search for <strong>DrTalks Video</strong> in the block inserter. Pick a video by slug; the block renders the same content as <code>[drtalks_video]</code> with toggleable sections.</p>

			<h2 id="theme">Theme Integration</h2>
			<p>Each video gets its own page at <code>/{archive_slug}/{video-slug}/</code> when the archive is enabled.</p>
			<p>To build a custom layout in a theme template, use the atomic shortcodes:</p>
			<pre><code>&lt;?php
$slug = get_post_meta( get_the_ID(), '_drtalks_video_slug', true );
echo do_shortcode( '[drtalks_video_iframe slug="' . esc_attr( $slug ) . '"]' );
echo do_shortcode( '[drtalks_video_title slug="' . esc_attr( $slug ) . '" tag="h1"]' );
echo do_shortcode( '[drtalks_expert_photo slug="' . esc_attr( $slug ) . '" class="speaker-avatar"]' );
echo do_shortcode( '[drtalks_expert_name slug="' . esc_attr( $slug ) . '"]' );
echo do_shortcode( '[drtalks_video_description slug="' . esc_attr( $slug ) . '"]' );
?&gt;</code></pre>

			<h3 id="template-overrides">Template overrides</h3>
			<p>Like WooCommerce, the plugin's templates can be overridden from your theme. Copy any file from the plugin's <code>templates/</code> folder into a <code>drtalks-videos/</code> folder inside your (child) theme, then edit your copy. Resolution order is <strong>child theme → parent theme → plugin default</strong>, so the plugin keeps working untouched until you provide an override.</p>
			<p>Overridable files (relative to <code>yourtheme/drtalks-videos/</code>):</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>File</th>
						<th>What it controls</th>
						<th>Applies to</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>single-drtalks_video.php</code></td><td>Full single-video page wrapper</td><td>Classic (non-block) themes</td></tr>
					<tr><td><code>archive-drtalks_video.php</code></td><td>Full archive page wrapper</td><td>Classic (non-block) themes</td></tr>
					<tr><td><code>content-archive-card.php</code></td><td>One video card in the archive / search grid</td><td>Everywhere a card appears</td></tr>
					<tr><td><code>content-single-theme.php</code></td><td>Single-video body, "theme" layout</td><td>Everywhere the single body renders</td></tr>
					<tr><td><code>content-single-video.php</code></td><td>Single-video body, "video" layout</td><td>Everywhere the single body renders</td></tr>
					<tr><td><code>partials/expert-bio.php</code></td><td>Expert bio block</td><td>Everywhere the bio renders</td></tr>
				</tbody>
			</table>
			<p>The <code>content-*</code> and <code>partials/*</code> files are shared by the archive, single pages, the shortcode, the Gutenberg block, and AJAX search, so overriding one changes that markup wherever it appears. The two full <code>*-drtalks_video.php</code> templates only affect the classic-PHP rendering path.</p>
			<p><strong>Block themes:</strong> you don't need these overrides. Edit the templates directly in <a href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>">Appearance → Editor (Site Editor)</a>, or place your own <code>templates/single-drtalks_video.html</code> / <code>templates/archive-drtalks_video.html</code> in your theme — WordPress uses those over the plugin's registered block templates automatically.</p>
			<p>Advanced: the resolved path for any template can be filtered via <code>drtalks_locate_template</code> (<code>apply_filters( 'drtalks_locate_template', $template, $template_name )</code>).</p>

			<h2 id="troubleshooting">Troubleshooting</h2>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Symptom</th>
						<th>Fix</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Video URL looks like <code>?post_type=drtalks_video&amp;p=14</code> and 404s</td>
						<td>Go to <a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>">Settings → Permalinks</a>, pick any structure other than Plain (e.g. <code>/%postname%/</code>), click Save</td>
					</tr>
					<tr>
						<td>Expert card shows only the slug, no name or photo</td>
						<td>Click <strong>Sync Now</strong> on the expert card</td>
					</tr>
					<tr>
						<td>Expert says "syncing" forever</td>
						<td>Open <a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler&action-group=drtalks' ) ); ?>">Tools → Scheduled Actions</a> and check the <code>drtalks</code> group. If actions are stuck in "pending", your server's cron isn't firing — click <strong>Sync Now</strong> on the expert card, or set up a real cron job hitting <code>wp-cron.php</code></td>
					</tr>
				</tbody>
			</table>

		</div><!-- .drtalks-docs-content -->

	</div><!-- .drtalks-docs-layout -->
</div>
