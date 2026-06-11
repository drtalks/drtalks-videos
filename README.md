# DrTalks Videos

WordPress plugin that syncs and embeds videos from the DrTalks catalogue.

Pull individual videos by slug, or auto-sync entire expert libraries on a schedule. Surface them via a public archive page, individual post pages, a Gutenberg block, the `[drtalks_video]` shortcode, or atomic shortcodes you can drop into theme templates.

---

## Requirements

- WordPress 6.4+
- PHP 8.0+
- A WordPress permalink structure other than **Plain** (`Settings → Permalinks`). The plugin shows a yellow warning banner if this isn't set; individual video pages will return 404s on Plain.

Background jobs (sync, transcript fetch) run via Action Scheduler, bundled with the plugin. You can monitor them at `Tools → Scheduled Actions`.

---

## Installation

1. Copy the plugin folder into `wp-content/plugins/drtalks-videos/`.
2. Activate via `Plugins → Installed Plugins`.
3. Go to `Settings → Permalinks` and click **Save** if the warning banner is visible.
4. Open `DrTalks Videos` from the admin menu.

---

## Admin UI

The admin page (`DrTalks Videos` in the sidebar) has three boxes.

### 1. Video Archive

A toggle that enables or disables a public archive page on your site. When **off**, the other two boxes are hidden — you can still embed individual videos via shortcode or block, you just won't have a `/{slug}/` browse page.

When **on**:

- **Archive URL slug** controls the archive permalink (`/videos/`) and individual video URLs (`/videos/{video-slug}/`). Auto-saves as you type.
- A copy-ready shortcode is always shown at the bottom so you can paste it anywhere.

### 2. Individual Videos

Search the DrTalks catalogue and add specific videos one at a time. Each added video becomes a post on your site that you can embed via shortcode or block.

Removing a video deletes it from your site.

Videos that belong to an **active expert** are managed in Box 3 instead and don't appear here.

### 3. Expert Auto-Sync

Add up to 5 DrTalks experts. Their videos sync automatically on the schedule you choose (hourly, twice daily, daily, weekly, or manual).

Each expert card shows:

- Their photo, name, and progress (e.g. `10 of 47 videos synced`)
- **Sync Now** — forces an immediate full sync
- **Remove** — deletes the expert and all their auto-synced videos

**Hidden Videos** column: click "Hide" on any auto-synced video to keep it off your site. Hidden videos won't be re-added by future syncs. Click "Unhide" to restore.

**Sync schedule**: changes apply immediately.

---

## Shortcodes

### Full embed

`[drtalks_video slug="…"]` renders the complete video block (player, title, description, transcript, expert bio). Each section can be toggled off:

| Attribute | Default | Setting to `0` |
|---|---|---|
| `slug` | *(required)* | — |
| `video` | `1` | Hides the player and "Watch on DrTalks" button |
| `title` | `1` | Hides the title |
| `description` | `1` | Hides the description |
| `transcript` | `1` | Hides the transcript dropdown |
| `author` | `1` | Hides the expert bio |

Accepted falsy values: `0`, `false`, `no`. Anything else is truthy.

Example — show only the title, description, and author:
```
[drtalks_video slug="dr-jane-smith-on-gut-health" video="0" transcript="0"]
```

### Atomic shortcodes

For building custom layouts (in posts, pages, or theme templates), use these single-purpose shortcodes:

| Shortcode | What it outputs |
|---|---|
| `[drtalks_video_iframe slug="…"]` | Just the video player iframe |
| `[drtalks_video_title slug="…"]` | Title as plain text |
| `[drtalks_video_title slug="…" tag="h2"]` | Title wrapped in any heading (`h1`–`h6`) or `p` / `span` / `div` |
| `[drtalks_video_title slug="…" tag="h2" class="hero"]` | With extra CSS class |
| `[drtalks_video_description slug="…"]` | Description (HTML) |
| `[drtalks_video_transcript slug="…"]` | Transcript HTML |
| `[drtalks_video_transcript slug="…" dropdown="1"]` | Transcript inside a collapsible `<details>` block |
| `[drtalks_video_transcript slug="…" dropdown="1" label="Show transcript"]` | Custom dropdown label |
| `[drtalks_expert_name slug="…"]` | Expert name (plain text) |
| `[drtalks_expert_photo slug="…"]` | Expert photo `<img>` |
| `[drtalks_expert_photo slug="…" class="avatar" alt="…"]` | With custom CSS class / alt text |
| `[drtalks_expert_title slug="…"]` | Expert's professional title (plain text) |
| `[drtalks_expert_bio slug="…"]` | Expert bio with line breaks preserved |

Missing video or empty field returns nothing — safe to drop into a template without breaking the layout.

---

## Gutenberg Block

Search for **DrTalks Video** in the block inserter. Pick a video by slug; the block renders the same content as `[drtalks_video]` with toggleable sections.

---

## Theme Integration

Each video gets its own page at `/{archive_slug}/{video-slug}/` when the archive is enabled.

To build a custom layout in a theme template, use the atomic shortcodes:

```php
<?php
$slug = get_post_meta( get_the_ID(), '_drtalks_video_slug', true );
echo do_shortcode( '[drtalks_video_iframe slug="' . esc_attr( $slug ) . '"]' );
echo do_shortcode( '[drtalks_video_title slug="' . esc_attr( $slug ) . '" tag="h1"]' );
echo do_shortcode( '[drtalks_expert_photo slug="' . esc_attr( $slug ) . '" class="speaker-avatar"]' );
echo do_shortcode( '[drtalks_expert_name slug="' . esc_attr( $slug ) . '"]' );
echo do_shortcode( '[drtalks_video_description slug="' . esc_attr( $slug ) . '"]' );
?>
```

### Custom queries

Each added video is a custom post type post (`drtalks_video`), so you can query them with a standard `WP_Query` and surface a grid, slider, or "latest videos" list anywhere on your site — not just on the plugin's archive page.

Two helpers make this clean (both are loaded only when the plugin is active, so guard with `function_exists()` / `class_exists()` if your code can run independently):

| Helper | Returns |
|---|---|
| `DrTalks_Post_Type::get_allowed_video_slugs()` | `string[]` of video slugs that should be **publicly visible** — selected videos + active-expert videos, minus hidden ones. This is the same gate the plugin's own archive uses. |
| `drtalks_get_video_meta( int $post_id )` | An associative array of all the metadata for one video (see the field table below). |

> **Visibility caveat:** a bare `WP_Query` on `drtalks_video` returns **every** synced post — including videos an admin explicitly hid and orphans left behind by old syncs. To match what the archive shows, filter by the allowed slugs as below.

```php
<?php
// Guard in case the plugin is deactivated.
if ( ! class_exists( 'DrTalks_Post_Type' ) ) {
	return;
}

$allowed = DrTalks_Post_Type::get_allowed_video_slugs();

if ( ! empty( $allowed ) ) {
	$videos = new WP_Query( [
		'post_type'      => 'drtalks_video',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => [
			[
				'key'     => '_drtalks_video_slug',
				'value'   => $allowed,
				'compare' => 'IN',
			],
		],
	] );

	if ( $videos->have_posts() ) {
		echo '<div class="drtalks-grid">';
		while ( $videos->have_posts() ) {
			$videos->the_post();
			// Reuse the plugin's archive card markup (player thumbnail, title,
			// duration, expert) — keeps your grid consistent with the archive:
			echo drtalks_render_archive_card( get_the_ID() );
		}
		echo '</div>';
		wp_reset_postdata();
	}
}
```

> Omit the `meta_query` only if you genuinely want every post (e.g. an internal admin report). For anything public-facing, keep it.

#### Available metadata

The post type only supports `title` — everything else lives in post meta. Get the whole set at once with `drtalks_get_video_meta( $post_id )`:

| Array key | Source meta key | Contents |
|---|---|---|
| `video_slug` | `_drtalks_video_slug` | The DrTalks video slug (the unique identifier) |
| `embed_url` | `_drtalks_embed_url` | Player iframe `src` URL |
| `thumbnail` | `_drtalks_thumbnail` | Thumbnail image URL |
| `description` | `_drtalks_description` | Video description (HTML) |
| `transcript` | `_drtalks_transcript` | Full transcript (HTML) |
| `expert_name` | `_drtalks_expert_name` | Expert's name |
| `expert_slug` | `_drtalks_expert_slug` | Expert's slug |
| `expert_title` | `_drtalks_expert_title` | Expert's professional title |
| `expert_creds` | `_drtalks_expert_credentials` | Expert's credentials |
| `expert_photo` | `_drtalks_expert_photo` | Expert photo URL |
| `expert_bio` | `_drtalks_expert_bio` | Expert bio (HTML) |
| `drtalks_url` | *(derived)* | Canonical `https://drtalks.com/videos/{slug}` URL |
| `allowed_html` | *(derived)* | `wp_kses()` whitelist for safely echoing the HTML fields |

A few fields are **not** in that helper and must be read directly:

| Meta key | Contents | Tip |
|---|---|---|
| `_drtalks_duration` | Runtime in **seconds** (int) | Format with `drtalks_format_duration( $secs )` → `MM:SS` |
| `_drtalks_synced_at` | Unix timestamp of the last sync (int) | — |

#### Outputting every field

Inside the loop, with `$id = get_the_ID();`:

```php
<?php
$id   = get_the_ID();
$meta = drtalks_get_video_meta( $id );
?>
<article class="drtalks-video">

	<!-- Title (post title, not a meta field) -->
	<h2><?php echo esc_html( get_the_title( $id ) ); ?></h2>

	<!-- Player iframe -->
	<?php if ( $meta['embed_url'] ) : ?>
		<div class="drtalks-player">
			<iframe src="<?php echo esc_url( $meta['embed_url'] ); ?>"
			        loading="lazy" allowfullscreen></iframe>
		</div>
	<?php endif; ?>

	<!-- Thumbnail (e.g. for a poster / fallback) -->
	<?php if ( $meta['thumbnail'] ) : ?>
		<img src="<?php echo esc_url( $meta['thumbnail'] ); ?>"
		     alt="<?php echo esc_attr( get_the_title( $id ) ); ?>">
	<?php endif; ?>

	<!-- Duration (raw meta, formatted) -->
	<?php $secs = (int) get_post_meta( $id, '_drtalks_duration', true ); ?>
	<?php if ( $secs > 0 ) : ?>
		<span class="drtalks-duration"><?php echo esc_html( drtalks_format_duration( $secs ) ); ?></span>
	<?php endif; ?>

	<!-- Description (HTML — sanitize with the provided whitelist) -->
	<?php if ( $meta['description'] ) : ?>
		<div class="drtalks-description">
			<?php echo wp_kses( $meta['description'], $meta['allowed_html'] ); ?>
		</div>
	<?php endif; ?>

	<!-- Transcript (HTML) -->
	<?php if ( $meta['transcript'] ) : ?>
		<details class="drtalks-transcript">
			<summary>Transcript</summary>
			<?php echo wp_kses( $meta['transcript'], $meta['allowed_html'] ); ?>
		</details>
	<?php endif; ?>

	<!-- Expert block -->
	<div class="drtalks-expert">
		<?php if ( $meta['expert_photo'] ) : ?>
			<img class="drtalks-expert-photo"
			     src="<?php echo esc_url( $meta['expert_photo'] ); ?>"
			     alt="<?php echo esc_attr( $meta['expert_name'] ); ?>">
		<?php endif; ?>
		<p class="drtalks-expert-name"><?php echo esc_html( $meta['expert_name'] ); ?></p>
		<p class="drtalks-expert-title"><?php echo esc_html( $meta['expert_title'] ); ?></p>
		<p class="drtalks-expert-creds"><?php echo esc_html( $meta['expert_creds'] ); ?></p>
		<?php if ( $meta['expert_bio'] ) : ?>
			<div class="drtalks-expert-bio">
				<?php echo wp_kses( $meta['expert_bio'], $meta['allowed_html'] ); ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Link back to DrTalks -->
	<?php if ( $meta['drtalks_url'] ) : ?>
		<a class="drtalks-watch" href="<?php echo esc_url( $meta['drtalks_url'] ); ?>"
		   target="_blank" rel="noopener">Watch on DrTalks</a>
	<?php endif; ?>

	<!-- Link to the video's own page on this site -->
	<a href="<?php echo esc_url( get_permalink( $id ) ); ?>">Read more</a>

</article>
```

**Escaping rules:** plain-text fields (names, titles, URLs) go through `esc_html()` / `esc_url()`; the HTML fields (`description`, `transcript`, `expert_bio`) should go through `wp_kses( …, $meta['allowed_html'] )` — never echo them raw.

If you'd rather not hand-build markup, you can mix in the [atomic shortcodes](#atomic-shortcodes) using `$meta['video_slug']`, or just call `drtalks_render_archive_card( $id )` (shown above) to reuse the archive's card layout — which also picks up any `content-archive-card.php` template override you've made.

### Template overrides

Like WooCommerce, the plugin's templates can be overridden from your theme — copy any of the files from the plugin's `templates/` folder into a `drtalks-videos/` folder inside your (child) theme and edit your copy. Resolution order is: **child theme → parent theme → plugin default**, so the plugin keeps working untouched until you provide an override.

Overridable files (relative to `yourtheme/drtalks-videos/`):

| File | What it controls | Applies to |
|---|---|---|
| `single-drtalks_video.php` | Full single-video page wrapper | Classic (non-block) themes |
| `archive-drtalks_video.php` | Full archive page wrapper | Classic (non-block) themes |
| `content-archive-card.php` | One video card in the archive / search grid | Everywhere a card appears |
| `content-single-theme.php` | Single-video body, "theme" layout | Everywhere the single body renders |
| `content-single-video.php` | Single-video body, "video" layout | Everywhere the single body renders |
| `partials/expert-bio.php` | Expert bio block | Everywhere the bio renders |

The `content-*` and `partials/*` files are shared by the archive, single pages, the shortcode, the Gutenberg block, and AJAX search, so overriding one changes that markup wherever it appears. The two full `*-drtalks_video.php` templates only affect the classic-PHP rendering path.

**Block themes:** you don't need these overrides. Edit the templates directly in **Appearance → Editor (Site Editor)**, or place your own `templates/single-drtalks_video.html` / `templates/archive-drtalks_video.html` in your theme — WordPress uses those over the plugin's registered block templates automatically.

Advanced: the resolved path for any template can be filtered via `drtalks_locate_template` (`apply_filters( 'drtalks_locate_template', $template, $template_name )`).

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Video URL looks like `?post_type=drtalks_video&p=14` and 404s | Go to `Settings → Permalinks`, pick any structure other than Plain (e.g. `/%postname%/`), click Save |
| Expert card shows only the slug, no name or photo | Click **Sync Now** on the expert card |
| Expert says "syncing" forever | Open `Tools → Scheduled Actions` and check the `drtalks` group. If actions are stuck in "pending", your server's cron isn't firing — click **Sync Now** on the expert card, or set up a real cron job hitting `wp-cron.php` |

---

## Uninstall

Deleting the plugin from `Plugins → Installed Plugins` removes:

- All DrTalks video posts on your site
- All plugin settings (archive on/off, expert list, hidden videos, etc.)

Deactivating alone leaves your data intact.
