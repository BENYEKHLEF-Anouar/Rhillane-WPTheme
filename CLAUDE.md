# Vault Child — rhillane.com rebuild

Child theme of **UiCore Vault** on a **WordPress multisite** (rhillane.com = main site).
Goal: move the site off Elementor onto ACF Flexible Content + CPTs, **without losing SEO**.
Full runbook: `REBUILD-PLAN.md` (waves W0→W4). AMD architecture reference: `FLEXIBLE-CONTENT-THEME-SPEC.md`.

## Locked decisions — do not contradict

- **Rank Math owns SEO** (titles, meta, schema, canonicals, sitemap). The theme must NEVER
  emit its own schema/sitemap. `inc/seo.php` is for cross-subsite hreflang only.
- **No ACF Extended.** ACF Pro is the only plugin dependency. Editor UX is our own code,
  ported from the AMD project → `inc/admin-ux.php` + `assets/admin/`. Live section preview
  is DONE (card-grid picker + scaled-iframe modal + saved-row preview + demo mode +
  duplicate-section warning + runtime field hints). Gallery viewer (§9) is still a TODO.
- **Theme is network-activated** — CPTs/fields register network-wide; content and Rank Math
  meta stay per-site.
- **Native i18n** (`languages/`, textdomain `vault-child`) — no Polylang.

## Architecture rules

- `functions.php` is a **loader only** (~30 lines). All logic lives in `inc/`, one job per file.
- Prefix everything `rmd_`. Never call ACF functions directly — use the null-safe wrappers
  in `inc/acf.php` (`rmd_get_field`, `rmd_have_rows`, …); the site must not fatal if ACF is off.
- **Sections:** one Flexible Content field `sections`; layout key = template file name, 1:1,
  snake_case, no transform → `template-parts/layouts/<key>.php`. Renderer: `rmd_render_sections()`.
- **Images:** every section image goes through `rmd_image()` — srcset, width/height,
  lazy by default, `eager` + `fetchpriority=high` only for section-0 (LCP) imagery.
- **Field groups live in `acf-json/`** (Local JSON) — author on staging, commit the JSON,
  deploy. Never edit only in the prod DB.
- An empty section renders nothing — never a broken box.

## ⚠ Template traps

- **NEVER create** `page.php`, `front-page.php`, `single.php`, `header.php`, `footer.php`,
  `404.php` before their wave — a child-theme file overrides parent Vault instantly and
  breaks the live Elementor pages on deploy. (Blog templates = W1, home/header/footer = W3.)
- Safe now: CPT-specific templates only (`single-case_study.php`, `archive-case_study.php`).
- **RMD chrome** (header/footer): lives in `template-parts/site-{header,footer}.php` + `rmd_render_header()`/
  `rmd_render_footer()` (`inc/chrome.php`), called **only** from the case-study templates — NOT a global
  `header.php`/`footer.php`. Editable via Site Settings (logo/CTA/copyright) + the `rmd_header` menu location
  (Appearance → Menus). Site-wide = W3: make `header.php`/`footer.php` one-liners that call these helpers.

## Build & deploy

- CSS: Tailwind source in `src/tailwind.css`, compiled **locally** and committed to
  `assets/css/main.css` — no server build. `npx @tailwindcss/cli -i src/tailwind.css -o assets/css/main.css --minify`
- **Hand-written CSS** (NOT Tailwind, no rebuild needed): `assets/css/rmd-media.css` (image
  loading effects, §12); `assets/admin/*.css` (section preview, gallery viewer) are editor-only.
  Front-end helpers: `rmd_render_link()` (inc/links.php, §10), `rmd_render_image()`
  (inc/images.php, §12), `rmd_field_default()` (inc/acf.php, §7.3).
- **§12 effects are OFF by default** — the `rmd_render_image()` renderer + `rmd-media.css` + the
  `rmd-js` head flag only load when `add_filter('rmd_image_effects', '__return_true')` is set
  (do this once a section actually renders via `rmd_render_image()`). Baseline lazy/eager loading
  (via `rmd_image()`) is always on. Theme floor is **PHP 7.4** (arrow functions; `Requires PHP` set).
- Deploy: push to `main` → GitHub Action FTPs the repo root into
  `/staging/wp-content/themes/vault-child/`. Non-theme files (src/, docs, this file) must be
  in the workflow's `exclude:` list — it's repeated **3×** (retry blocks); edit all three.
- Branch per wave (`wave/…`); PRs carry the crawl diff as SEO evidence.
- Watch encoding: content is FR/EN (later AR) — never corrupt accented characters.
