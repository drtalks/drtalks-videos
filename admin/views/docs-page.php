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
						<li><a href="#custom-queries">Custom queries</a></li>
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
				<li><strong>Fetch Missing Videos</strong> — adds any of this expert's DrTalks videos that aren't on your site yet (the quick fix when the count is short, e.g. <em>181 of 182</em>)</li>
				<li><strong>Update to Latest</strong> — re-downloads the newest details (titles, descriptions, hosts, guests, transcripts) for videos already added</li>
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
					<tr><td><code>author</code></td><td><code>1</code></td><td>Hides the expert (host) bio</td></tr>
					<tr><td><code>guests</code></td><td><code>1</code></td><td>Hides the guest bios</td></tr>
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
					<tr><td><code>[drtalks_expert_bio slug="…"]</code></td><td>Expert (host) bio with line breaks preserved</td></tr>
					<tr><td><code>[drtalks_guests slug="…"]</code></td><td>Full guests section — one card per guest (name, title, credentials, bio, photo, profile link). Outputs nothing if the video has no guests.</td></tr>
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

			<h3 id="custom-queries">Custom queries</h3>
			<p>Each added video is a custom post type post (<code>drtalks_video</code>), so you can query them with a standard <code>WP_Query</code> and surface a grid, slider, or "latest videos" list anywhere on your site — not just on the plugin's archive page.</p>
			<p>Two helpers make this clean. Both are loaded only when the plugin is active, so guard with <code>function_exists()</code> / <code>class_exists()</code> if your code can run independently.</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Helper</th>
						<th>Returns</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>DrTalks_Post_Type::get_allowed_video_slugs()</code></td><td><code>string[]</code> of video slugs that should be <strong>publicly visible</strong> — selected videos + active-expert videos, minus hidden ones. The same gate the plugin's own archive uses.</td></tr>
					<tr><td><code>drtalks_get_video_meta( int $post_id )</code></td><td>An associative array of all the metadata for one video (see the field table below).</td></tr>
				</tbody>
			</table>
			<p><strong>Visibility caveat:</strong> a bare <code>WP_Query</code> on <code>drtalks_video</code> returns <strong>every</strong> synced post — including videos an admin explicitly hid and orphans left behind by old syncs. To match what the archive shows, filter by the allowed slugs as below.</p>
			<pre><code>&lt;?php
// Guard in case the plugin is deactivated.
if ( ! class_exists( 'DrTalks_Post_Type' ) ) {
	return;
}

$allowed = DrTalks_Post_Type::get_allowed_video_slugs();

if ( ! empty( $allowed ) ) {
	$videos = new WP_Query( [
		'post_type'      =&gt; 'drtalks_video',
		'post_status'    =&gt; 'publish',
		'posts_per_page' =&gt; 12,
		'orderby'        =&gt; 'date',
		'order'          =&gt; 'DESC',
		'meta_query'     =&gt; [
			[
				'key'     =&gt; '_drtalks_video_slug',
				'value'   =&gt; $allowed,
				'compare' =&gt; 'IN',
			],
		],
	] );

	if ( $videos-&gt;have_posts() ) {
		echo '&lt;div class="drtalks-grid"&gt;';
		while ( $videos-&gt;have_posts() ) {
			$videos-&gt;the_post();
			// Reuse the plugin's archive card markup (thumbnail, title,
			// duration, expert) — keeps your grid consistent with the archive:
			echo drtalks_render_archive_card( get_the_ID() );
		}
		echo '&lt;/div&gt;';
		wp_reset_postdata();
	}
}</code></pre>
			<p>Omit the <code>meta_query</code> only if you genuinely want every post (e.g. an internal admin report). For anything public-facing, keep it.</p>

			<h4>Available metadata</h4>
			<p>The post type only supports <code>title</code> — everything else lives in post meta. Get the whole set at once with <code>drtalks_get_video_meta( $post_id )</code>:</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Array key</th>
						<th>Source meta key</th>
						<th>Contents</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>video_slug</code></td><td><code>_drtalks_video_slug</code></td><td>The DrTalks video slug (the unique identifier)</td></tr>
					<tr><td><code>embed_url</code></td><td><code>_drtalks_embed_url</code></td><td>Player iframe <code>src</code> URL</td></tr>
					<tr><td><code>thumbnail</code></td><td><code>_drtalks_thumbnail</code></td><td>Thumbnail image URL</td></tr>
					<tr><td><code>description</code></td><td><code>_drtalks_description</code></td><td>Video description (HTML)</td></tr>
					<tr><td><code>transcript</code></td><td><code>_drtalks_transcript</code></td><td>Full transcript (HTML)</td></tr>
					<tr><td><code>published_at</code></td><td><code>_drtalks_published_at</code></td><td>Original DrTalks publish date (ISO 8601, UTC); used as schema.org <code>uploadDate</code></td></tr>
					<tr><td><code>expert_name</code></td><td><code>_drtalks_expert_name</code></td><td>Expert's name</td></tr>
					<tr><td><code>expert_slug</code></td><td><code>_drtalks_expert_slug</code></td><td>Expert's slug</td></tr>
					<tr><td><code>expert_title</code></td><td><code>_drtalks_expert_title</code></td><td>Expert's professional title</td></tr>
					<tr><td><code>expert_creds</code></td><td><code>_drtalks_expert_credentials</code></td><td>Expert's credentials</td></tr>
					<tr><td><code>expert_photo</code></td><td><code>_drtalks_expert_photo</code></td><td>Expert photo URL</td></tr>
					<tr><td><code>expert_bio</code></td><td><code>_drtalks_expert_bio</code></td><td>Expert (host) bio (HTML)</td></tr>
					<tr><td><code>guests</code></td><td><code>_drtalks_guests</code></td><td>Array of guests, each: <code>slug</code>, <code>name</code>, <code>photo_url</code>, <code>bio</code>, <code>credentials</code>, <code>title</code>. Empty array when none.</td></tr>
					<tr><td><code>drtalks_url</code></td><td><em>(derived)</em></td><td>Canonical <code>https://drtalks.com/videos/{slug}</code> URL</td></tr>
					<tr><td><code>allowed_html</code></td><td><em>(derived)</em></td><td><code>wp_kses()</code> whitelist for safely echoing the HTML fields</td></tr>
				</tbody>
			</table>
			<p>A few fields are <strong>not</strong> in that helper and must be read directly:</p>
			<table class="widefat striped drtalks-docs-table">
				<thead>
					<tr>
						<th>Meta key</th>
						<th>Contents</th>
						<th>Tip</th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>_drtalks_duration</code></td><td>Runtime in <strong>seconds</strong> (int)</td><td>Format with <code>drtalks_format_duration( $secs )</code> → <code>MM:SS</code></td></tr>
					<tr><td><code>_drtalks_synced_at</code></td><td>Unix timestamp of the last sync (int)</td><td>—</td></tr>
				</tbody>
			</table>

			<h4>Outputting every field</h4>
			<p>Inside the loop, with <code>$id = get_the_ID();</code>:</p>
			<pre><code>&lt;?php
$id   = get_the_ID();
$meta = drtalks_get_video_meta( $id );
?&gt;
&lt;article class="drtalks-video"&gt;

	&lt;!-- Title (post title, not a meta field) --&gt;
	&lt;h2&gt;&lt;?php echo esc_html( get_the_title( $id ) ); ?&gt;&lt;/h2&gt;

	&lt;!-- Player iframe --&gt;
	&lt;?php if ( $meta['embed_url'] ) : ?&gt;
		&lt;div class="drtalks-player"&gt;
			&lt;iframe src="&lt;?php echo esc_url( $meta['embed_url'] ); ?&gt;"
			        loading="lazy" allowfullscreen&gt;&lt;/iframe&gt;
		&lt;/div&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Thumbnail (e.g. for a poster / fallback) --&gt;
	&lt;?php if ( $meta['thumbnail'] ) : ?&gt;
		&lt;img src="&lt;?php echo esc_url( $meta['thumbnail'] ); ?&gt;"
		     alt="&lt;?php echo esc_attr( get_the_title( $id ) ); ?&gt;"&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Duration (raw meta, formatted) --&gt;
	&lt;?php $secs = (int) get_post_meta( $id, '_drtalks_duration', true ); ?&gt;
	&lt;?php if ( $secs &gt; 0 ) : ?&gt;
		&lt;span class="drtalks-duration"&gt;&lt;?php echo esc_html( drtalks_format_duration( $secs ) ); ?&gt;&lt;/span&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Description (HTML — sanitize with the provided whitelist) --&gt;
	&lt;?php if ( $meta['description'] ) : ?&gt;
		&lt;div class="drtalks-description"&gt;
			&lt;?php echo wp_kses( $meta['description'], $meta['allowed_html'] ); ?&gt;
		&lt;/div&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Transcript (HTML) --&gt;
	&lt;?php if ( $meta['transcript'] ) : ?&gt;
		&lt;details class="drtalks-transcript"&gt;
			&lt;summary&gt;Transcript&lt;/summary&gt;
			&lt;?php echo wp_kses( $meta['transcript'], $meta['allowed_html'] ); ?&gt;
		&lt;/details&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Expert block --&gt;
	&lt;div class="drtalks-expert"&gt;
		&lt;?php if ( $meta['expert_photo'] ) : ?&gt;
			&lt;img class="drtalks-expert-photo"
			     src="&lt;?php echo esc_url( $meta['expert_photo'] ); ?&gt;"
			     alt="&lt;?php echo esc_attr( $meta['expert_name'] ); ?&gt;"&gt;
		&lt;?php endif; ?&gt;
		&lt;p class="drtalks-expert-name"&gt;&lt;?php echo esc_html( $meta['expert_name'] ); ?&gt;&lt;/p&gt;
		&lt;p class="drtalks-expert-title"&gt;&lt;?php echo esc_html( $meta['expert_title'] ); ?&gt;&lt;/p&gt;
		&lt;p class="drtalks-expert-creds"&gt;&lt;?php echo esc_html( $meta['expert_creds'] ); ?&gt;&lt;/p&gt;
		&lt;?php if ( $meta['expert_bio'] ) : ?&gt;
			&lt;div class="drtalks-expert-bio"&gt;
				&lt;?php echo wp_kses( $meta['expert_bio'], $meta['allowed_html'] ); ?&gt;
			&lt;/div&gt;
		&lt;?php endif; ?&gt;
	&lt;/div&gt;

	&lt;!-- Link back to DrTalks --&gt;
	&lt;?php if ( $meta['drtalks_url'] ) : ?&gt;
		&lt;a class="drtalks-watch" href="&lt;?php echo esc_url( $meta['drtalks_url'] ); ?&gt;"
		   target="_blank" rel="noopener"&gt;Watch on DrTalks&lt;/a&gt;
	&lt;?php endif; ?&gt;

	&lt;!-- Link to the video's own page on this site --&gt;
	&lt;a href="&lt;?php echo esc_url( get_permalink( $id ) ); ?&gt;"&gt;Read more&lt;/a&gt;

&lt;/article&gt;</code></pre>
			<p><strong>Escaping rules:</strong> plain-text fields (names, titles, URLs) go through <code>esc_html()</code> / <code>esc_url()</code>; the HTML fields (<code>description</code>, <code>transcript</code>, <code>expert_bio</code>) should go through <code>wp_kses( …, $meta['allowed_html'] )</code> — never echo them raw.</p>
			<p>If you'd rather not hand-build markup, you can mix in the <a href="#shortcode-atomic">atomic shortcodes</a> using <code>$meta['video_slug']</code>, or just call <code>drtalks_render_archive_card( $id )</code> (shown above) to reuse the archive's card layout — which also picks up any <code>content-archive-card.php</code> template override you've made.</p>

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
						<td>Click <strong>Update to Latest</strong> on the expert card</td>
					</tr>
					<tr>
						<td>Expert count never reaches the total (e.g. stuck at "181 of 182")</td>
						<td>Click <strong>Fetch Missing Videos</strong> on the expert card to pull in any videos not yet imported. If the count still won't reach the total, the remaining video is likely hidden or trashed — check <strong>DrTalks Videos → Debug → Expert Sync Diagnostics</strong>, which names the exact video and why</td>
					</tr>
					<tr>
						<td>Expert says "syncing" forever</td>
						<td>Open <a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler&action-group=drtalks' ) ); ?>">Tools → Scheduled Actions</a> and check the <code>drtalks</code> group. If actions are stuck in "pending", your server's cron isn't firing — click <strong>Update to Latest</strong> on the expert card, or set up a real cron job hitting <code>wp-cron.php</code></td>
					</tr>
				</tbody>
			</table>

		</div><!-- .drtalks-docs-content -->

	</div><!-- .drtalks-docs-layout -->
</div>
