# ACF Flexible-Content Theme — Architecture & Feature Spec

> A **generic, reusable blueprint** for a WordPress theme where editors compose every page from interchangeable "sections", the structured data lives in custom post types, and the whole content model is version-controlled as JSON.
>
> This is **not tied to any single site** — replace the illustrative prefix `theme_` with your own, and define your own content entities. Sections marked **★ NEW** are proposed features to build (image-gallery viewer, extended element options, section-ownership model, image lazy-loading & loading effects); the rest document a proven architecture.

---

## Table of contents

1. [What this is](#1-what-this-is)
2. [Architecture at a glance](#2-architecture-at-a-glance)
3. [ACF Local JSON synchronisation (the "JSON files" system)](#3-acf-local-json-synchronisation-the-json-files-system)
4. [Custom post types — the generic content-entity pattern](#4-custom-post-types--the-generic-content-entity-pattern)
5. [The page builder — flexible-content sections](#5-the-page-builder--flexible-content-sections)
6. [Reusable section catalog (archetypes)](#6-reusable-section-catalog-archetypes)
7. [Logical switches & conditions](#7-logical-switches--conditions)
8. [The live section preview system](#8-the-live-section-preview-system)
9. [★ NEW — Image gallery viewer inside flexible-content fields](#9--new--image-gallery-viewer-inside-flexible-content-fields)
10. [★ NEW — Extended element options (CTA link attributes & more)](#10--new--extended-element-options-cta-link-attributes--more)
11. [★ NEW — Section ownership: page-scoped instances & cross-page picker](#11--new--section-ownership-page-scoped-instances--cross-page-picker)
12. [★ NEW — Image lazy-loading & loading effects](#12--new--image-lazy-loading--loading-effects)
13. [Front-end interactivity](#13-front-end-interactivity)
14. [SEO features](#14-seo-features)
15. [Multilingual](#15-multilingual)
16. [Graceful degradation & helpers](#16-graceful-degradation--helpers)
17. [File map](#17-file-map)

---

## 1. What this is

A pattern for a **section-composed WordPress theme** built on ACF Pro. The three ideas that make it work:

- **Editors build pages from sections.** A single ACF *Flexible Content* field holds an ordered, reorderable list of "sections". Each section is a small, self-contained template.
- **Structured data lives in CPTs.** Repeating real-world entities (people, items, logos, reviews, metrics — whatever your site is about) are custom post types with their own fields, queried by the sections.
- **The whole schema is JSON.** Every field group, CPT, and taxonomy is stored as a `.json` file in `/acf-json`, so the content model is version-controlled and portable across environments.

**Stack (recommended):** WordPress 6.x + PHP 8.1, ACF Pro, Tailwind (compiled & committed), a light motion library (e.g. GSAP), an icon set (e.g. Lucide), optional Polylang for i18n. Naming prefix throughout: `theme_` (choose your own).

**Defensive by design.** Every third-party integration (ACF, ACF Pro options page, form plugin, i18n plugin) is feature-detected and degrades to sensible defaults, so the site never fatals if a plugin is off.

---

## 2. Architecture at a glance

```
                    ┌─────────────────────────────────────────┐
                    │           ACF Pro (the engine)          │
                    └─────────────────────────────────────────┘
                                     │
   ┌──────────────────────┬─────────┴──────────┬──────────────────────┐
CONTENT-ENTITY CPTs   PAGE BUILDER          LOCAL JSON            SITE SETTINGS
(your domain          `page_layouts`        /acf-json/*.json      (options page)
 objects, defined     flexible-content      = version-controlled  contact / social /
 by YOU)              with N sections       schema, syncable      footer, site-wide
   │                  │
   │   ┌──────────────┴───────────────────────────────────────────┐
   │   │  front-page.php / page.php loop `page_layouts` rows,      │
   │   │  strip the `_section` suffix, and                         │
   │   │  get_template_part('template-parts/layouts/<name>.php')   │
   │   └──────────────────────────────────────────────────────────┘
   │                  │
   └──────────────────┴──> Section templates read ACF sub-fields OR
                            query the CPTs (the "source switch", §7)
```

**The single dispatch convention.** A flexible-content layout named `foo_section` is rendered by `template-parts/layouts/foo.php`. The suffix `_section` is stripped and passed to `get_template_part()`. The **same transform** is used by the live-preview endpoint, so preview and front-end always resolve to the same file.

**`functions.php`** covers these concerns: ACF safety wrappers → CPT/taxonomy registration → ACF field-group PHP fallbacks → the live section preview → asset enqueue → theme setup / menus / i18n → dynamic XML sitemap → structured data → helpers → activation seeding → one-time migrations. **Do not write all of that inline** — split it into includes (§2.1).

### 2.1 Keep `functions.php` thin — split into includes

**Yes, this is possible and it's the recommended pattern.** `functions.php` is just PHP; a `require`d file behaves exactly like code written inline (same global scope, same hook timing), so a 5,000-line `functions.php` should become a short **loader** that pulls in focused files from an `inc/` directory. Nothing about WordPress or ACF changes — hooks, `add_action`, ACF JSON, everything works identically.

**`functions.php` becomes a loader only:**

```php
<?php
/**
 * Theme bootstrap — this file only loads modules. All real code lives in /inc.
 */
if (!defined('ABSPATH')) exit;

define('THEME_DIR', get_stylesheet_directory());

$theme_modules = array(
    'inc/acf-guards.php',        // ACF-active constant + theme_get_field() wrappers
    'inc/acf-json.php',          // save_json / load_json wiring
    'inc/post-types.php',        // CPT + taxonomy registration (PHP fallback)
    'inc/field-groups.php',      // acf_add_local_field_group() fallbacks (or split further)
    'inc/options-page.php',      // Site Settings options page
    'inc/enqueue.php',           // front-end + admin asset enqueue
    'inc/page-builder.php',      // dispatch helpers, layout allowlist
    'inc/section-preview.php',   // the live section preview endpoint (§8)
    'inc/gallery-viewer.php',    // ★ in-field gallery viewer (§9)
    'inc/links.php',             // ★ theme_render_link() + link options (§10)
    'inc/seo.php',               // structured data + sitemap
    'inc/menus.php',             // nav menu registration + helpers
    'inc/i18n.php',              // theme_t() + string registration
    'inc/helpers.php',           // theme_field_default(), image chain, badges…
    'inc/setup.php',             // after_setup_theme, theme supports
    'inc/seeding.php',           // activation seeding (load last)
    'inc/migrations.php',        // one-time data migrations
);

foreach ($theme_modules as $module) {
    require_once THEME_DIR . '/' . $module;
}
```

**Rules that keep this safe:**

- **`require_once`, not `include`** — a fatal "file not found" (`require`) is better than a silently half-loaded theme (`include`), and `_once` prevents accidental double-loads.
- **Load order matters for definitions, not for hooks.** Functions must be *defined* before they're *called*, but `add_action('init', …)` just registers a callback — WordPress runs it later, so the include order among hook-registering files is flexible. Still, load **guards/helpers first** (things others call at include time) and **seeding/migrations last**.
- **Don't gate hooks behind `after_setup_theme`-style ordering by accident** — each module should register its own hooks at file scope, exactly as it did inline.
- **Child themes:** use `get_stylesheet_directory()` (active theme) if the code belongs to the child; use `get_template_directory()` for parent-theme code. Pick one consistently via a `THEME_DIR` constant as above.
- **An auto-loader is optional.** You can `glob(THEME_DIR.'/inc/*.php')` and require all — but an **explicit array is safer** (deterministic order, no surprise file picked up). Avoid `GLOB_BRACE` (undefined on some PHP builds); if you must glob, sort the result.
- **One concern per file.** Aim for files in the low hundreds of lines; split further (e.g. `inc/field-groups/hero.php`) if a single concern grows large.

This is purely an organisational change — behaviour is identical, and it makes the codebase reviewable, diffable, and navigable.

---

## 3. ACF Local JSON synchronisation (the "JSON files" system)

This is what gives every custom post type, taxonomy, and field group a **`.json` file for synchronisation**. It lives in `acf-json/`.

### 3.1 How it works

ACF's **Local JSON** feature persists every Field Group, Custom Post Type, and Taxonomy definition as a plain JSON snapshot in `/acf-json` (filename = the object's `key`). The definition also lives in the database. Result: the content model is expressed as version-controllable text that travels with the theme — deploy the files and the whole schema is reproduced, no manual re-entry.

Wiring in `functions.php`:

```php
function theme_acf_json_path() {
    return get_stylesheet_directory() . '/acf-json';
}
add_filter('acf/settings/save_json', fn() => theme_acf_json_path()); // WRITE here
add_filter('acf/settings/load_json', function ($paths) {              // LOAD from here
    $paths[] = theme_acf_json_path();
    return $paths;
});
```

- **Field groups load directly from JSON at runtime** — the file is authoritative for what fields render, even on an environment where they were never saved through the UI.
- **Post types & taxonomies** (ACF 6.1+) are read from JSON and registered on `init`.

### 3.2 The "Sync available" mechanism

Because a definition can exist in two places (DB record vs JSON file), ACF compares them by two fields present in every file:

- **`key`** — the stable, globally-unique identifier (e.g. `group_page_builder`). Never changes; it's the join key across environments.
- **`modified`** — a Unix timestamp recorded at save time.

| State | ACF behaviour |
| --- | --- |
| No DB record for this `key` | Shows under **"Sync available"** (normal after a fresh deploy) |
| DB record exists but its `modified` is **older** than the file | **"Sync available"** offered (the file is newer) |
| Timestamps match / DB is newer | No sync; loads from JSON |

Clicking **Sync** imports the JSON into the DB. Standard deploy step: pull the repo, then Sync (field groups auto-load even without it).

**Why it matters:** portability (schema travels with the theme), version control (readable Git diffs, reviewable in PRs), deterministic conflict detection (`key` + `modified` mean the newer file wins the sync prompt, so environments converge instead of silently diverging).

Keep an `acf-json/index.php` with `<?php // Silence is golden.` to prevent directory listing.

### 3.3 JSON vs PHP fallback — precedence

- **JSON is canonical.** ACF loads field groups directly from the files; CPTs/taxonomies are synced from JSON into the DB.
- **PHP is the fallback / bootstrap.** `functions.php` (a) registers the **options page** (which cannot live in JSON — only the field group bound to it can), (b) registers CPTs/taxonomies in raw PHP as a safety net for when ACF is off or JSON isn't synced, (c) ships `acf_add_local_field_group()` arrays gated behind "no JSON groups exist", and (d) fills dynamic `choices` at load time via an `acf/load_field` filter.
- **When both exist for the same `key`, Local JSON wins** for field-group loading — which keeps the model consistent across environments.

---

## 4. Custom post types — the generic content-entity pattern

> The reference theme this spec was distilled from had domain-specific CPTs. **Those are intentionally removed here.** Define your own content entities; the *pattern* is what's reusable.

**When to make something a CPT vs a section field:** if it's a **repeating real-world entity** that appears in more than one place, needs its own edit screen, or is queried/sorted/filtered (people, products, projects, logos, reviews, metrics, locations…), make it a CPT. If it's **presentational copy that belongs to one section on one page**, make it a section sub-field.

### 4.1 Registration pattern

Register CPTs on `init` (priority 20), but **skip PHP registration when JSON post-type files exist**, to avoid double-registration:

```php
function theme_register_cpts() {
    if (theme_acf_json_manages_content_types()) {
        return; // JSON (post_type_*.json / taxonomy_*.json) is present → it registers them
    }
    register_post_type('entity', array(
        'labels'       => array( /* … */ ),
        'public'       => true,
        'has_archive'  => true,          // true only if the entity has public single/archive pages
        'show_in_rest' => true,          // false if you want the classic editor
        'supports'     => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
        'menu_icon'    => 'dashicons-…',
        'taxonomies'   => array('entity_category'),
    ));
    // register_taxonomy('entity_category', 'entity', array('hierarchical' => true, …));
}
add_action('init', 'theme_register_cpts', 20);
```

`page-attributes` support gives editors a manual **menu order** — use it when a section renders the entity in an editor-controlled order.

### 4.2 Field group per CPT

Each CPT gets a JSON field group (`group_<entity>_fields.json`) with a location rule `post_type == <entity>`. Keep field names generic and reusable (`icon_svg`, `image`, `image_url`, `rank`, `rating`, `link`, `sub_items` repeater, …). Provide sensible defaults so an unfilled entity still renders.

### 4.3 Common image pattern (relevant to §9)

For any image on a CPT or a section, offer **two inputs**: an ACF *image* field (media library, `return_format: url`) **and** a text URL field for external hotlinking, plus fall back to the native **featured image**. The uniform priority chain used throughout: **featured image → ACF media field → ACF URL field → placeholder.**

---

## 5. The page builder — flexible-content sections

A field group **`group_page_builder`** (location: `post_type == page`, so it attaches to **all** Pages) contains one flexible-content field, **`page_layouts`** (button "Add a Section"), offering N selectable section layouts.

**Namespace each layout label with a bracket tag** that signals which page/group it belongs to — e.g. `[Home]`, `[Common]`, `[Blog]`, `[Contact]`. This single signal drives the preview's grouping, category chips, and default filtering (see §8.8). Use whatever groups fit your site.

### The dispatch loop (`front-page.php`, `page.php`)

```php
if (theme_have_rows('page_layouts')) :
    while (theme_have_rows('page_layouts')) : theme_the_row();
        $layout = theme_get_row_layout();               // e.g. "hero_section"
        $clean  = str_replace('_section', '', $layout); // "hero"
        get_template_part('template-parts/layouts/' . $clean);
    endwhile;
endif;
```

- `front-page.php` renders a "welcome / edit this page" fallback when there are no rows.
- `page.php` additionally outputs `the_content()` when a page has classic content **and** no `page_layouts` rows — so a page can be classic, builder, or a mix.

The `theme_have_rows()` / `theme_the_row()` / `theme_get_row_layout()` wrappers (§14) make the loop null-safe when ACF is inactive.

---

## 6. Reusable section catalog (archetypes)

These are **generic, site-agnostic** section archetypes. Build the ones you need; each is one file in `template-parts/layouts/`.

| Archetype (`*_section`) | What it is | Notable fields / behaviour |
| --- | --- | --- |
| **hero** | Page-opening banner | eyebrow, title (HTML with accent-word spans), description, **CTA repeater** (see §10), social-proof lines |
| **common_hero** | Simple interior-page header | breadcrumb text, title, description, optional CTA |
| **content_grid** | N cards/features | header fields + `cards` repeater (icon, title, description) |
| **media_text** | Image/text split | image field (+ §9 gallery), heading, body, CTA |
| **★ gallery** | Image gallery | ACF **Gallery** field or `images` repeater → grid + lightbox (see §9) |
| **logos_band** ✦ | Trust/partner marquee | pulls from a "logo" CPT; infinite marquee; renders nothing if empty |
| **stats_band** ✦ | Key numbers | pulls from a "metric" CPT (ordered); renders nothing if empty |
| **entity_grid** ✦ | Directory/listing of a CPT | manual `selected` relationship → else auto-query top-N by a rank/date field |
| **testimonials** ✦ | Reviews | **three-way source**: inline cards → selected CPT posts → all CPT posts |
| **posts_grid** | Blog listing | category filter (client-side pills), per-page count, optional recent-posts rail |
| **cta_band** | Call-to-action strip | eyebrow, title, description, **1–2 CTAs** (see §10) |
| **newsletter** | Email capture | label, title, description, placeholder, button; wire to your ESP |
| **form** | Embedded form | picks a form from your form plugin; `mailto:` fallback if plugin absent |
| **faq** | Accordion | `items` repeater (question/answer); JS toggle |
| **process / steps** | Numbered process | `steps` repeater (num/title/description/icon); dynamic column layout |

`✦` = queries a CPT. The **source-switch** behaviour of the CPT-backed ones is documented in §7.3.

**Field idioms used across sections:**
- Titles are `text` fields that allow HTML, with default copy containing accent-word spans (e.g. `<span class="accent">word</span>`) that the CSS/motion system styles.
- Icons: two systems — a raw `icon_svg` textarea, or an icon-name `text`/`select` for a library like Lucide.
- Images: the featured → media → URL → placeholder chain (§4.3).

---

## 7. Logical switches & conditions

Three distinct layers of "logical switches" — understand all three.

### 7.1 ACF conditional logic (admin field show/hide)

Real `conditional_logic` rules that show/hide fields as another field changes. Canonical examples:

**CTA link cascade** (inside a CTA repeater — see §10):

```
link_type (select: page | url | none)
 ├── url field        → shown when link_type == "url"
 └── page field       → shown when link_type == "page"

style (select: primary | secondary | custom)
 ├── bg_color picker  → shown when style == "custom"
 └── text_color picker→ shown when style == "custom"
```

**Toggle gate** (a `true_false` that reveals dependent fields):

```
show_recent_rail (true_false, default on)
 ├── rail_title  → shown when show_recent_rail == "1"
 └── rail_count  → shown when show_recent_rail == "1"
```

### 7.2 Admin-side JavaScript guard-rails (`acf/input/admin_footer`)

Because ACF conditional logic can't *count* selections or detect duplicates, inject small admin scripts for editor feedback:

- **Count-aware warning** — when a switch only has an effect above a threshold (e.g. a category filter that needs ≥2 categories), watch the source field live and show an inline note + dim the now-inert toggle when the condition isn't met.
- **Duplicate-section warning** — the flexible-content field allows duplicate sections; scan rows on every change, key by `data-layout`, and flag every **repeat** with a dismissible amber note. Non-blocking.

### 7.3 Template-level content switches ("source switches")

The most pervasive pattern: a section decides *at render time* where its content comes from.

| Pattern | Behaviour |
| --- | --- |
| **Inline → selected → all** (three-way) | Inline repeater cards → else specifically-selected CPT posts → else all posts of that CPT. |
| **Manual → auto** | A `relationship` selection if non-empty → else auto-query top-N by a rank/date field. |
| **Pure CPT** | Pull entirely from a CPT; render **nothing** when empty (no inline option). |
| **`=== null` fallback** | A *never-set* repeater shows static demo content; a *saved-but-emptied* repeater shows nothing — distinguishes "new" from "deliberately cleared". |
| **Priority chain** | Images: featured → media → URL → placeholder. Badge/author: field → derived value → default. |

The helper behind this is **`theme_field_default($name, $default)`**: returns the saved value, or `$default` **only when the field is `null`/`false`** (unset). An explicitly cleared field (`""`) passes through as `""` and stays hidden — the technical basis of the never-set-vs-cleared distinction.

---

## 8. The live section preview system

An **editor-only** feature that renders any single section standalone, inside a scaled iframe modal, from the page builder. It renders the **real section template** (not a screenshot), so it can never go stale. Code: `functions.php` preview block + `assets/admin/section-preview.js` + `.css`.

### 8.1 Architecture — a real server round-trip into a same-origin iframe

An `admin-ajax.php` endpoint renders the actual section template into a **complete standalone HTML document**, loaded as the `src` of a same-origin `<iframe>`. Registered **logged-in only** (no `nopriv` variant).

### 8.2 Two entry points, one modal

1. **"Add Row" popup → demo preview.** Every layout in the picker gets an **eye button**; clicking previews it as a **generic demo** (no ACF row yet → fields fall back to defaults; CPT-driven layouts show real DB content). Modal shows **"Insert this section"**.
2. **Existing row toolbar → saved-value preview.** Every inserted row gets an eye button; it previews **that row with its saved values**. Unsaved/never-saved rows show an amber warning instead of stale content.

Mode is passed **explicitly** as an `isRow` flag (never derived from the row index).

### 8.3 The server endpoint

1. **Nonce** check. 2. **Sanitise** input (`layout` via `sanitize_key`, `post_id` via `absint`, `row` int with `-1` = demo). 3. **Capability** (`edit_post`/`edit_posts`, else 403). 4. **Allowlist** — `layout` must be a key of the preview-data map, else 400; **the template path is never built from raw input**. 5. **Post context** via `setup_postdata()`. 6. **Render** — saved row vs demo. 7. **Empty-content probe** — strip `<style>`/`<script>`/comments then `strip_tags()` keeping only media tags; if nothing renderable remains, show a placeholder. 8. **Emit a standalone doc** with `X-Frame-Options: SAMEORIGIN`, `noindex`, the theme CSS, and the **real motion JS** so the preview animates like the live site; disable navigation with `a { pointer-events: none; }`; cancel any leading negative top margin so isolated sections don't render off-frame.

### 8.4 Saved-row rendering

Walk `have_rows('page_layouts', $post_id)` to the target index, then **guard against DOM/saved drift** (only render if `get_row_layout() === $layout`; mismatch → render nothing so the caller shows the "save first" notice). Rendering happens *inside* that row's ACF context so `get_sub_field()` resolves to saved values; `reset_rows()` afterwards.

### 8.5 Iframe scaling & height

Render the iframe at a fixed design width (e.g. 1280px) and CSS-`transform: scale()` it down (`scale = min(1, available / 1280)`, origin top-left), with the clip element carrying the *scaled* height. Measure height from the same-origin `body.scrollHeight` (not `documentElement`, which over-reports for short sections), re-measuring after fonts/images settle.

### 8.6 Insertion

The "Insert" button replays the click on ACF's own `<a data-layout>` anchor (handling position + min/max), with a fallback to `acf.getField(key).add(name)`. Capture the anchor **before** closing the modal.

### 8.7 Client-side notes

No jQuery, no ACF JS-action hooks — native DOM + a `MutationObserver` (ACF builds popups/rows on demand); decorators idempotent. Dirtiness read from `wp.data.select('core/editor').isEditedPostDirty()`. On a real save, re-baseline row counts and reload the open preview. Enqueue **only on `post.php` / `post-new.php`** when ACF is active.

### 8.8 "Section groups" — bracket-prefix labels

The `[Group]` label prefix is parsed (`^\[([^\]]+)\]\s*(.*)$`) into a category slug that drives (1) coloured category chips on cards and the modal heading, (2) per-page grouping of the picker (a server helper maps each page slug to a default category; cards partition into "This page's sections / Common sections / [toggle] Other sections"), and (3) a **"Used"** badge on sections already on the page, counted live from the DOM.

---

## 9. ★ NEW — Image gallery viewer inside flexible-content fields

**Goal:** when a section has an image field or a set of images (a gallery, or a repeater whose rows contain images), let the editor **see and browse those images as a gallery/lightbox directly inside the flexible-content row** — without opening the media library one item at a time, and without leaving the page-builder screen.

This complements the live section preview (§8): the preview shows the *rendered* section; the gallery viewer is a fast, in-field way to inspect *just the images*.

### 9.1 Field design

Offer image content through one of two ACF shapes, both supported by the viewer:

- **ACF Gallery field** (`type: gallery`, `return_format: array` or `id`) — the primary shape for a "gallery section". Store an ordered list of attachments.
- **Repeater with an image sub-field** (e.g. a `cards`/`slides` repeater where each row has an `image`) — the viewer collects the image sub-field across all rows.

Add a small, generic **image meta group** as sub-fields next to each image so the front-end and the viewer can show captions/alt: `image`, `alt` (text — falls back to the attachment's alt), `caption` (text), optional `link` (see §10). Keep `return_format` consistent (recommend `array` so you get `url`, `sizes`, `alt`, `title` in one read).

### 9.2 Admin behaviour (what the editor sees)

Enqueue a small admin script/style alongside the section-preview assets (same gate: `post.php`/`post-new.php`, ACF active). For every flexible-content row that contains a gallery or image-bearing repeater:

1. **Thumbnail strip.** Render a compact horizontal strip of thumbnails inside the row header/handle area — e.g. the first 5–8 images plus a `+N` chip. This gives an at-a-glance sense of the section's images without expanding it.
2. **"View gallery" button.** A small button (mirroring the §8 eye-button style) opens a **lightbox modal** overlaying the editor:
   - A responsive grid of all images in the field (with count, e.g. "12 images").
   - Click a thumbnail → full-size view with **prev/next** navigation, keyboard arrows, `Esc` to close, and the image's caption/alt shown beneath.
   - A footer action to **"Edit in Media Library"** (opens the native `wp.media` frame focused on that attachment) so the viewer stays read-first but editing is one click away.
3. **Live updates.** A `MutationObserver` (reuse the section-preview one) re-scans rows as ACF adds/removes them or the editor changes the gallery, so the strip and count stay current.

### 9.3 Implementation approach

- **Detection:** on scan, for each `.layout` row, find gallery fields (`.acf-field[data-type="gallery"]`) and image sub-fields inside repeaters (`.acf-field[data-type="image"] img`), and read their attachment data from ACF's own DOM (ACF renders selected images as `<img>` with the attachment URL; for richer data, read `acf.getField($el).val()`).
- **Rendering:** build the strip and lightbox with plain DOM (no jQuery), matching the section-preview modal's CSS system (backdrop, dialog, focus trap, `Esc`, `prefers-reduced-motion`). Reuse the existing modal shell where possible.
- **Media frame:** use the built-in `wp.media({ frame: 'select' })` for the "edit in media library" jump — no external lightbox library needed.
- **Isolation:** decorators must be idempotent (guard with a `data-*` flag) and null-safe (a row with no images simply gets no strip/button).

### 9.4 Front-end rendering (the `gallery_section` template)

- Render the gallery as a responsive grid (CSS `grid` with `minmax`), each image lazy-loaded (`loading="lazy"`, `decoding="async"`), with correct `alt` (field alt → attachment alt) and `<figure>/<figcaption>` for captions.
- Progressive-enhancement lightbox: works as plain links/images with no JS; JS upgrades it to an in-page lightbox with prev/next and keyboard/`Esc`, honouring `prefers-reduced-motion`.
- Emit `ImageGallery`/`ImageObject` structured data if galleries are SEO-relevant (see §12).
- Empty gallery → render nothing (a section is never a broken empty box).

### 9.5 Acceptance criteria

- [ ] Every flexible-content row with a gallery/image field shows a thumbnail strip + count.
- [ ] "View gallery" opens a browsable lightbox (grid → full view, prev/next, keyboard, `Esc`).
- [ ] Adding/removing images updates the strip without a page reload.
- [ ] "Edit in Media Library" opens the native frame on the right attachment.
- [ ] Works with both an ACF Gallery field and an image-bearing repeater.
- [ ] Front-end gallery is responsive, lazy-loaded, accessible, and degrades without JS.

---

## 10. ★ NEW — Extended element options (CTA link attributes & more)

**Goal:** any element that renders a link or needs HTML attributes — CTAs above all, but also cards, logos, image links, menu-like items — gets a **reusable "extended options" field set**: link target, `rel` flags (**nofollow**, sponsored, ugc, noopener, noreferrer), plus generic attributes (title, aria-label, id, custom classes, `data-*`), and download.

Today a typical CTA only has text + a URL/page + a style. This adds the SEO/accessibility/behaviour attributes editors keep needing.

### 10.1 The reusable "Link + attributes" field group (a clone field)

Define **one** ACF group and reuse it everywhere via ACF's **Clone** field, so every CTA/link element gets identical options and you maintain them in one place.

**Group `group_link_options`** (sub-fields):

| Field | Type | Notes |
| --- | --- | --- |
| `label` | text | The visible link/button text (required for CTAs) |
| `link_type` | select | `page` \| `url` \| `none` — drives the cascade (§7.1) |
| `page` | page_link | shown when `link_type == page` |
| `url` | url/text | shown when `link_type == url` |
| `target` | select/true_false | `_self` \| `_blank`. New tab. When `_blank`, auto-add `noopener` |
| `rel` | checkbox (multi) | **`nofollow`**, `sponsored`, `ugc`, `noopener`, `noreferrer` |
| `download` | true_false | render the `download` attribute (for file links) |
| `aria_label` | text | accessible name when the label isn't descriptive |
| `title_attr` | text | native `title` tooltip (optional) |
| `element_id` | text | `id` attribute (validated to a safe token) |
| `css_classes` | text | extra classes appended to the element |
| `data_attrs` | repeater | rows of `{ key, value }` → rendered as `data-<key>="<value>"` |

**CTA-specific styling** (keep alongside, or in a second clone group `group_cta_style`): `style` (primary/secondary/custom), `bg_color` + `text_color` (color_picker, shown when `style == custom`), `icon` (select from your icon set), `icon_position` (before/after), `size` (sm/md/lg), `full_width` (true_false).

> **Tip:** ACF's native **Link** field already returns `{ url, title, target }`. It's fine for simple cases, but it **cannot express `rel`/nofollow, download, aria, id, classes, or data-attrs** — which is exactly why this custom group exists. Use the custom group for CTAs; the native Link field is acceptable for throwaway internal links.

### 10.2 Using it in sections

In any section (hero CTA repeater, `cta_band`, card link, logo link, gallery image link), add a **Clone** sub-field pointing at `group_link_options` (and `group_cta_style` for buttons). The hero's `hero_ctas` repeater, for example, becomes: one clone of link-options + one clone of cta-style per row. Editors now get the full attribute set on every CTA with zero duplication.

### 10.3 The front-end renderer (single source of truth)

Provide one helper that turns a link-options array into a safe `<a>` — used by **every** section so behaviour never drifts:

```php
/**
 * Render an anchor from a theme_link_options array.
 * Escapes every attribute; composes rel from checkboxes + target; supports data-*.
 */
function theme_render_link($opts, $inner_html = '', $base_class = '') {
    // Resolve href
    $href = '';
    if (($opts['link_type'] ?? '') === 'page' && !empty($opts['page'])) {
        $href = get_permalink($opts['page']);
    } elseif (($opts['link_type'] ?? '') === 'url') {
        $href = $opts['url'] ?? '';
    }
    if ($href === '' && ($opts['link_type'] ?? '') !== 'none') {
        return ''; // no destination → render nothing (or a <span> if you prefer)
    }

    // rel: user checkboxes + auto-noopener/noreferrer for _blank
    $rel = (array) ($opts['rel'] ?? array());
    $target = $opts['target'] ?? '_self';
    if ($target === '_blank') { $rel[] = 'noopener'; $rel[] = 'noreferrer'; }
    $rel = array_values(array_unique(array_filter($rel)));

    $classes = trim($base_class . ' ' . ($opts['css_classes'] ?? ''));

    $attrs = array(
        'href'       => esc_url($href),
        'class'      => $classes !== '' ? esc_attr($classes) : null,
        'target'     => $target === '_blank' ? '_blank' : null,
        'rel'        => $rel ? esc_attr(implode(' ', $rel)) : null,
        'id'         => !empty($opts['element_id']) ? esc_attr(sanitize_html_class($opts['element_id'])) : null,
        'title'      => !empty($opts['title_attr']) ? esc_attr($opts['title_attr']) : null,
        'aria-label' => !empty($opts['aria_label']) ? esc_attr($opts['aria_label']) : null,
        'download'   => !empty($opts['download']) ? '' : null, // boolean attribute
    );

    // data-* rows
    foreach ((array) ($opts['data_attrs'] ?? array()) as $row) {
        if (!empty($row['key'])) {
            $attrs['data-' . sanitize_html_class($row['key'])] = esc_attr($row['value'] ?? '');
        }
    }

    $html = '<a';
    foreach ($attrs as $k => $v) {
        if ($v === null) continue;
        $html .= $v === '' ? ' ' . $k : ' ' . $k . '="' . $v . '"';
    }
    $html .= '>' . $inner_html . '</a>';
    return $html;
}
```

Key rules baked in: **every attribute is escaped**; `rel` is composed from the editor's checkboxes **plus** an automatic `noopener noreferrer` whenever `target="_blank"` (security); a link with no destination renders nothing rather than a dead `#`; `download` and other boolean attributes render bare.

### 10.4 SEO/accessibility notes

- **`nofollow`** for untrusted/paid destinations; **`sponsored`** for paid/affiliate links; **`ugc`** for user-generated links — expose all three so editors can annotate outbound links correctly.
- Always pair `target="_blank"` with `rel="noopener noreferrer"` (done automatically above).
- `aria_label` lets editors give icon-only or vague CTAs an accessible name.

### 10.5 Acceptance criteria

- [x] A single reusable link-options group is cloned into every CTA/link element.
      *Built as one shared PHP definition (`inc/link-fields.php`) rather than an ACF Clone
      field: `rmd_link_advanced_field()` returns an ACF **group**, whose value is a clean
      assoc array that drops straight into the renderer. `src/gen-acf.php` reads the same
      file, so the generated case-study JSON can never drift from the header's fields.*
- [x] Editors can set target, **nofollow**/sponsored/ugc/noopener/noreferrer, download, title, aria-label, id, custom classes, and arbitrary `data-*`.
      *Folded behind a "⚙ Options avancées" popup — `inc/admin-link-options.php` +
      `assets/admin/link-options.{js,css}`. The group's inner field list is MOVED into a
      dialog that lives inside `<form id="post">`, so ACF's save/validation/repeater
      handling is untouched; if the JS never loads, the fields simply render inline.*
- [x] One PHP renderer outputs the anchor with all attributes escaped; `_blank` auto-adds `noopener noreferrer`.
      *`rmd_render_link()` — used by the `cta` section and both header CTAs.*
- [ ] CTA style options (variant, custom colors, icon, size, full-width) work alongside link options.
      *Not built: sections carry their own styling today (`btn-cta`, `rmd-cta`).*
- [x] A link with no destination renders nothing (no dead `#`).

**Live in:** `cta` section (`button_advanced`) and the Site Settings header CTA
(`rmd_header_cta_advanced`). Any future section gets the same set with one call to
`rmd_link_advanced_field()` plus `rmd_link_with_advanced()` in its template.

---

## 11. ★ NEW — Section ownership: page-scoped instances & cross-page picker

Two complementary guarantees about *where a section lives*. Together they form the **section-ownership model**: you can add **any section type** to any page, but **every section instance belongs to exactly one page**.

### 11.1 Page-scoped section instances

**Goal:** a section instance is unique to the page it's placed on and bound to it — editing or deleting it affects **only that page**, never another.

**Why:** prevents accidental cross-page edits; keeps each page's content self-contained and predictable.

**How it already works — and how to make the guarantee explicit:**

- ACF flexible-content rows in `page_layouts` are stored in **the page's own post meta**, so a section is *inherently local to its page today*. There is no shared instance to accidentally edit — deleting a row on Page A cannot touch Page B. This item is about **stating and protecting that guarantee**, and keeping page sections cleanly separate **if global/shared blocks are ever introduced**.
- **Do not reach for `wp_block` (reusable blocks) or a shared options-page repeater** for page sections — those *are* shared instances and would break the guarantee. Keep `page_layouts` as the only home for page sections.
- **If you ever add global blocks** (e.g. a site-wide banner), model them as a **separate, clearly-labelled field** (e.g. a `global_blocks` options-page field, or a distinct CPT) — never mixed into `page_layouts`. Label them unmistakably in the UI (a `[Global]` prefix / a warning note) so an editor always knows "this edit affects every page" vs "this edit affects only this page".
- **Optional hardening / clarity:**
  - A tiny admin note at the top of the `page_layouts` field: *"Les sections ci-dessous appartiennent à cette page uniquement."* ("The sections below belong to this page only.")
  - Keep section CSS/JS **scoped** (a wrapper class per instance, e.g. `section--<layout>-<row_index>`) so styling one instance never leaks to the same layout on another page.
  - If a section needs a stable per-instance identity (anchor links, analytics), give it a generated `element_id` (from §10) rather than a shared slug.

**Relationship to CPT-backed sections:** sections that *query a CPT* (logos, metrics, testimonials — §7.3) still show the **same shared data** on every page that includes them. That's intentional and separate: the *section instance* is page-scoped, but the *data it queries* is global. If you need page-specific data, use inline sub-fields (which are page-scoped) instead of a CPT query, or the manual-selection override.

### 11.2 Cross-page section picker (add sections from other pages)

**Goal:** the "Add a Section" picker surfaces **this page's own sections first**, with everything else — common sections and sections that belong to other pages — **available behind a toggle**. (This is the feature already built in the reference theme; documented in depth at §8.8.)

Recap of the mechanism (see §8.8 for the full detail):

- Each layout label is prefixed with a `[Group]` tag; a server helper (`theme_page_builder_category($post_id)`) maps the current page to a category slug.
- The picker JS partitions cards into **"This page's sections" → "Common sections (used)" → [toggle] → "Other common sections" + "Sections from other pages"**, so other pages' sections are *loadable* but not in the way by default.
- Cards already on the page get a **"Used"** badge; guards prevent an empty picker.

**How the two features coexist:** §11.2 controls which section **types** (layouts) are *offered* and how they're grouped; §11.1 guarantees that once inserted, each section **instance** is *owned by this page*. Loading a layout that "belongs to another page" just adds a fresh, independent instance here — it does **not** link back to or share anything with that other page.

### 11.3 Acceptance criteria

- [ ] Editing/deleting a section on one page provably never affects another page.
- [ ] `page_layouts` is the only store for page sections; no `wp_block`/shared-repeater instances used for them.
- [ ] Any future global block lives in a separate, clearly-labelled field — never mixed into `page_layouts`.
- [ ] The picker shows this page's sections first, with other pages'/common sections behind a toggle (§8.8).
- [ ] Per-instance CSS/JS scoping so one instance's styling never leaks to another.

---

## 12. ★ NEW — Image lazy-loading & loading effects

**Goal:** every image on the site (logo bands, case-study visuals, blog/guide thumbnails, hero media) **defers off-screen loading** and shows a **placeholder transition** — blur-up, skeleton, or fade-in — while it resolves, instead of a blank box that shifts the layout or an abrupt pop-in. Editors get **per-field options** to control the loading strategy and effect where it matters.

**Why:** on an image-heavy marketing site, unmanaged image loading works directly against a "vitesse extrême" / performance goal — it hurts both *perceived* speed and *actual* Core Web Vitals (**LCP** and **CLS**).

### 12.1 The baseline (free, do this everywhere)

- **`loading="lazy"`** on every non-critical image; **`decoding="async"`**.
- **Explicit `width`/`height`** (or a CSS `aspect-ratio` on the wrapper) on *every* image — this alone eliminates layout shift (**CLS**) for free.
- **The hero / LCP image is the exception:** it must be **eager** (`loading="eager"`, `fetchpriority="high"`, and *not* lazy) so it doesn't get deprioritised — lazy-loading your LCP image is a classic performance regression.

### 12.2 The loading effect (the transition on top)

Pick one system (or offer all three as an option — see §12.3):

- **Fade-in** — image starts at `opacity:0`, transitions to `1` on `load`. Cheapest; CSS + a one-line JS `load` handler (or `<img>` `onload`).
- **Skeleton** — a shimmering placeholder background on the wrapper (CSS gradient animation) that's revealed until the image fades in. Best for cards/thumbnails.
- **Blur-up (LQIP)** — a tiny blurred placeholder (a base64 micro-thumb or the attachment's smallest size) shown scaled-up and blurred, swapped for the full image on load. Best for hero/large visuals; needs the small source.

All effects must **honour `prefers-reduced-motion`** (no shimmer/fade — just show the image).

### 12.3 ★ Extended per-field options (what editors control)

Add a reusable **image-options** clone group (like §10's link options) next to any image/gallery field, so editors can tune loading behaviour per image without touching code:

**Group `group_image_options`** (sub-fields):

| Field | Type | Notes |
| --- | --- | --- |
| `image` | image | `return_format: array` (gives url + sizes + alt) |
| `alt` | text | overrides attachment alt (falls back to it) |
| `loading` | select | `lazy` (default) \| `eager` — set `eager` for the hero/LCP image |
| `priority` | true_false | adds `fetchpriority="high"` (use only for the LCP image) |
| `effect` | select | `fade` (default) \| `skeleton` \| `blur` \| `none` |
| `aspect_ratio` | select/text | e.g. `16/9`, `1/1`, `4/3`, or `auto` — drives the wrapper's reserved space (CLS) |
| `focal_point` | select | object-position (center / top / bottom…) for cropped fills |
| `link` | clone → `group_link_options` | optional — make the image a link with full §10 attributes |

**Sensible defaults** so editors never *have* to touch it: `loading=lazy`, `effect=fade`, `aspect_ratio` inferred from the attachment dimensions, `priority=off`.

### 12.4 The front-end renderer (single source of truth)

One helper renders every managed image, so behaviour is uniform:

```php
/**
 * Render an <img> (wrapped for the loading effect) from a theme_image_options array.
 * Reserves space via aspect-ratio (CLS), sets lazy/eager + fetchpriority, and
 * emits the LQIP/skeleton/fade markup the front-end JS/CSS upgrades.
 */
function theme_render_image($opts, $size = 'large', $base_class = '') {
    $img = $opts['image'] ?? null;
    if (!$img) return ''; // no image → render nothing, never a broken box

    $url    = is_array($img) ? ($img['sizes'][$size] ?? $img['url']) : $img;
    $w      = is_array($img) ? ($img['width']  ?? '') : '';
    $h      = is_array($img) ? ($img['height'] ?? '') : '';
    $alt    = $opts['alt'] ?? (is_array($img) ? ($img['alt'] ?? '') : '');
    $eager  = ($opts['loading'] ?? 'lazy') === 'eager';
    $effect = $opts['effect'] ?? 'fade';
    $ratio  = $opts['aspect_ratio'] ?? '';
    $lqip   = ($effect === 'blur' && is_array($img)) ? ($img['sizes']['thumbnail'] ?? '') : '';

    $wrap_style = $ratio && $ratio !== 'auto' ? ' style="aspect-ratio:' . esc_attr($ratio) . '"' : '';

    ob_start(); ?>
    <span class="img-wrap img-effect--<?php echo esc_attr($effect); ?> <?php echo esc_attr($base_class); ?>"<?php echo $wrap_style; ?>>
        <?php if ($lqip): ?><img class="img-lqip" src="<?php echo esc_url($lqip); ?>" alt="" aria-hidden="true"><?php endif; ?>
        <img
            class="img-main"
            src="<?php echo esc_url($url); ?>"
            <?php if ($w) echo 'width="' . esc_attr($w) . '"'; ?>
            <?php if ($h) echo 'height="' . esc_attr($h) . '"'; ?>
            alt="<?php echo esc_attr($alt); ?>"
            loading="<?php echo $eager ? 'eager' : 'lazy'; ?>"
            decoding="async"
            <?php if (!empty($opts['priority'])) echo 'fetchpriority="high"'; ?>
            onload="this.closest('.img-wrap')?.classList.add('is-loaded')">
    </span>
    <?php
    return ob_get_clean();
}
```

Companion CSS/JS: `.img-wrap` reserves space via `aspect-ratio`; `.img-main` starts hidden and reveals on `.is-loaded`; `.img-effect--skeleton` animates a shimmer until loaded; `.img-effect--blur .img-lqip` is the blurred placeholder that fades out. A small `theme-scripts.js` addition handles images already cached (fire the `.is-loaded` class if `img.complete` on init) and respects `prefers-reduced-motion`.

### 12.5 Where it applies

Every image surface — the logo band, blog/guide grids, case-study visuals, and any hero/media field — routes through `theme_render_image()`. The **hero/LCP image sets `loading=eager` + `priority=on`**; everything else stays lazy with a fade or skeleton.

### 12.6 Acceptance criteria

- [ ] All non-LCP images are `loading="lazy"` + `decoding="async"` with explicit dimensions/aspect-ratio (zero CLS).
- [ ] The hero/LCP image is eager + `fetchpriority="high"` (never lazy).
- [ ] Editors can set loading strategy, effect (fade/skeleton/blur/none), aspect-ratio, priority, and alt per image field.
- [ ] Loading effect degrades gracefully (cached images show instantly; `prefers-reduced-motion` disables animation).
- [ ] One `theme_render_image()` helper is the single renderer for all managed images.

---

## 13. Front-end interactivity

A single `theme-scripts.js` bootstraps on `DOMContentLoaded`; everything respects `prefers-reduced-motion`.

- **Motion system** — add a `motion-enhanced` body class (reveals FOUC-guarded elements), run a page-entrance timeline, and defer scroll-triggered animations to `requestIdleCallback`. Re-init for dynamically added nodes via `MutationObserver`.
- **Custom selects** — replace native `<select>`s with styled dropdowns, syncing back to the hidden real select (dispatch `change` so any redirect logic still reads `.value`).
- **Marquee** — rAF-driven infinite scroll with hover-pause and drag; duration scales with item count.
- **Command-palette search** (⌘/Ctrl-K) — overlay with a filtered index; wire it to real WP search or a static index.
- **Lightbox** (for §9 galleries) — grid → full view, prev/next, keyboard, `Esc`.
- **Accordions / mobile menu / smooth-scroll** — plain `.hidden`/`.open` toggles with CSS transitions.
- Any submit-only widgets (newsletter, wizards) should be wired to a real backend or clearly marked cosmetic.

Keep a hand-written `theme-styles.css` (loaded after compiled Tailwind) for scrollbars, sticky-header offsets, accent-word underline variables, dialog backdrops, and card hover effects.

---

## 14. SEO features

- **Organization JSON-LD** on every page via a `wp_head` action (name/url/description, dynamic email, logo if set, `sameAs` from filled social links).
- **Context-specific schema** — emit `ItemList` for ranked lists, `BlogPosting` for articles, `BreadcrumbList` for interior pages, `ImageGallery`/`ImageObject` for galleries (§9). Emit nothing when the data isn't there — never fake schema.
- **Custom XML sitemap** at `/sitemap.xml` (rewrite rule + `template_redirect` handler): homepage + dynamically-discovered published pages + posts, with per-type priority/changefreq. Disable core `/wp-sitemap.xml` (`wp_sitemaps_enabled → false`) and append a `Sitemap:` line to the virtual `robots.txt`.
- **Rendering** — `title-tag` support; fonts loaded non-render-blocking; icon/motion libraries deferred.
- **Link SEO** — the extended CTA options (§10) let editors set `nofollow`/`sponsored`/`ugc` per link.

---

## 15. Multilingual

- Register hardcoded UI strings with the i18n plugin (e.g. Polylang's `pll_register_string`) on `init`.
- Route every user-facing string through a `theme_t()` wrapper that returns the translation when the plugin is active, else the original — the theme works identically without the plugin.
- Provide a compact language switcher that renders only when ≥2 languages exist.
- Make menu resolution plugin-aware and hardened so a blanked location can't accidentally grab the wrong menu.

---

## 16. Graceful degradation & helpers

- **ACF guard wrappers** — define an `ACF_ACTIVE` constant once (all of `get_field`/`get_sub_field`/`have_rows`/`update_field` exist), and route every ACF call through `theme_get_field()` / `theme_get_sub_field()` / `theme_have_rows()` / `theme_the_row()` / `theme_get_row_layout()` / `theme_update_field()`, each returning a safe empty value when ACF is off. Show an admin notice when in fallback mode.
- **`theme_field_default()`** — the never-set-vs-cleared helper (§7.3).
- **Image/priority helpers** — one function for the featured → media → URL → placeholder chain; one for badge/label resolution.
- **`theme_render_link()`** — the single link renderer (§10.3).
- **SVG uploads** (admins only) — `upload_mimes` + `wp_check_filetype_and_ext` filters to accept `image/svg+xml`, plus admin CSS to render SVG thumbnails.
- **Site Settings options page** — register on `acf/init` (ACF-Pro only): contact / social / footer, read via `theme_get_field('<name>', 'option')`.
- **Activation seeding & versioned migrations** — seed pages/menus/demo content fill-only on `after_switch_theme` (force the destructive flag off — WP passes the previous theme name as arg 1), and run one-time data migrations gated by a version option on `admin_init`.

---

## 17. File map

```
your-theme/
├── functions.php                 # Thin LOADER only — requires /inc modules (§2.1)
├── inc/                          # All PHP logic, one concern per file (§2.1)
│   ├── acf-guards.php            #   ACF-active constant + theme_get_field() wrappers
│   ├── acf-json.php              #   save_json / load_json wiring (§3)
│   ├── post-types.php            #   CPT + taxonomy registration (§4)
│   ├── page-builder.php          #   dispatch helpers + layout allowlist (§5)
│   ├── section-preview.php       #   live section preview endpoint (§8)
│   ├── gallery-viewer.php        #   ★ in-field gallery viewer (§9)
│   ├── links.php                 #   ★ theme_render_link() + link options (§10)
│   ├── images.php                #   ★ theme_render_image() + lazy/effect options (§12)
│   ├── enqueue.php / seo.php / menus.php / i18n.php / helpers.php
│   └── setup.php / seeding.php / migrations.php
├── header.php / footer.php       # Chrome, nav, footer, global modals
├── front-page.php / page.php     # page_layouts dispatch loop (§5)
├── single.php / single-*.php     # Post templates
├── index.php / 404.php           # Fallback + branded 404
│
├── acf-json/                     # LOCAL JSON — the syncable schema (§3)
│   ├── post_type_*.json          #   Your CPT definitions
│   ├── taxonomy_*.json           #   Your taxonomy definitions
│   ├── group_page_builder.json   #   The flexible-content page builder
│   ├── group_link_options.json   #   ★ Reusable CTA/link options (§10)
│   ├── group_image_options.json  #   ★ Reusable image loading/effect options (§12)
│   ├── group_*_fields.json        #   Field groups per CPT / options page
│   └── index.php                 #   "Silence is golden" guard
│
├── template-parts/
│   ├── layouts/*.php             # One file per builder section (§6)
│   └── components/*.php          # Shared components (rails, cards, gallery, link)
│
├── assets/
│   ├── admin/section-preview.{js,css}   # Live preview UI (§8)
│   ├── admin/gallery-viewer.{js,css}    # ★ In-field gallery viewer (§9)
│   ├── css/{tailwind,theme-styles}.css  # Compiled TW + hand-written
│   └── js/theme-scripts.js              # Front-end interactivity (§13) + image loading (§12)
│
├── docs/                         # Section audits / change logs
└── CLAUDE.md / README.md         # Conventions + project overview
```

---

### Build order (suggested)

1. **Foundation** — ACF Local JSON wiring (§3), the page-builder field + dispatch loop (§5), the ACF guard wrappers (§16), and split `functions.php` into `/inc` from day one (§2.1).
2. **Content model** — your CPTs (§4), a first handful of section archetypes (§6), and the section-ownership guarantee (§11).
3. **Editor experience** — the live section preview (§8) and cross-page picker (§8.8/§11.2), then the new features: **gallery viewer** (§9), **extended link/CTA options** (§10).
4. **Performance & polish** — **image lazy-loading & loading effects** (§12), front-end motion (§13), SEO/schema (§14), i18n (§15), seeding & migrations (§16).

*The ★ NEW features (§9 gallery viewer, §10 extended element options, §11 section ownership, §12 image lazy-loading & effects) are written as buildable specs with acceptance criteria — the rest documents the proven architecture they slot into.*
