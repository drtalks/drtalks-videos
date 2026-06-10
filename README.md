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
