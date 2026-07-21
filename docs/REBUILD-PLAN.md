# Rhillane Rebuild — Elementor → ACF / Custom Post Types

> **Migration runbook · rhillane.com**
> Move rhillane.com off page-by-page Elementor onto the code-owned **vault-child**
> theme — Custom Post Types + ACF Flexible Content — **without losing SEO**.
> Built on staging, page-for-page, then cut over to production wave by wave.

| Environment | Parent theme | Child theme | Deploy | SEO |
|---|---|---|---|---|
| WordPress multisite | UiCore Vault | `vault-child` | push → FTP staging | Rank Math |

## ⏱ The one deadline

**Wed 2026-07-22, 17:00** — Phase 1 (W0) ships on **staging.rhillane.com**:
system + Case Studies live. All later phases are sequenced by sign-off, not by
calendar (see [Part III](#part-iii--roadmap--the-waves)).

## ✅ Decisions — 20/07 meeting

- **Rank Math** is the SEO plugin; all meta parity targets its fields.
- Theme is **network-activated**: CPTs + field groups register once
  network-wide; content & Rank Math meta stay per-site, so crawls run per subsite.
- **rhillane.com is the main site** of the multisite — the primary target for all waves.
- **Six services confirmed** — see Part III, W2.
- Header and footer are **sitewide**; a CPT can get its own only if it truly needs one.

---

# PART I — Foundation: System + Case Studies
*now → Wed 22 Jul 17:00*

One deliverable, two halves that prove each other: the **system** (scaffold, ACF
wiring, section library) and the **first real content** (Case Studies). Case
Studies is greenfield — no Elementor page to preserve — so it's the safe place
to exercise the whole pipeline end-to-end before any SEO-sensitive migration.

## 1.1 Prerequisites — clear these first

| ID | Check | Why | Status |
|----|-------|-----|--------|
| **P1** | **ACF Pro active** on the multisite | Flexible Content is Pro-only — no Pro, no plan | 🔴 Blocker |
| **P2** | **`acf-json/` writable** in vault-child | Field groups live in git, not the DB | 🔴 Blocker |
| **P3** | Staging is a **true clone** of prod | Parity testing is meaningless otherwise | 🟡 Verify |
| **P4** | Baseline **Screaming Frog crawl** of prod saved | The "before" snapshot for every diff | 🟡 Verify |
| **P5** | **PHP ≥ 8.1** on staging | Typed code / ACF | 🟡 Verify |
| **P6** | Rank Math network mode + meta storage confirmed | Where title/meta get copied to | ✅ Decided |

> ⚠️ If **P1** or **P2** fails — stop and fix before anything else. The whole
> approach depends on them.

## 1.2 Architecture — the scaffold and the engine

```text
vault-child/
├── functions.php                # bootstraps /inc
├── style.css                    # design tokens (CSS custom properties)
├── acf-json/                    # field groups as code — the git win
├── inc/
│   ├── setup.php                # supports, image sizes
│   ├── acf.php                  # Local JSON save/load paths
│   ├── helpers.php              # rmd_render_sections()
│   ├── post-types/case-study.php
│   └── taxonomies/case-study-cat.php
├── template-parts/
│   ├── flexible/                # ONE file per section layout
│   │   └── hero.php · text.php · text-media.php · …
│   └── cards/case-study-card.php
├── single-case_study.php
├── archive-case_study.php
└── single.php · archive.php     # blog — Part III, W1
```

**The engine.** One renderer loops the Flexible Content field and includes the
matching template-part. Every template becomes ~3 lines: header → render → footer.

```php
// inc/helpers.php
function rmd_render_sections( $field = 'sections', $post_id = null ) {
    if ( ! have_rows( $field, $post_id ) ) return;
    while ( have_rows( $field, $post_id ) ) : the_row();
        get_template_part( 'template-parts/flexible/' . get_row_layout() );
    endwhile;
}
```

```php
// inc/acf.php — field groups deploy through git/FTP
add_filter('acf/settings/save_json', fn() => get_stylesheet_directory() . '/acf-json');
add_filter('acf/settings/load_json', function($paths){
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});
```

**Header & footer are sitewide.** Every template wraps its sections in the
global `get_header()` / `get_footer()` — one header, one footer, rendered by the
theme on every URL:

```php
// single-case_study.php — the whole template
get_header();
rmd_render_sections();
get_footer();
```

**Per-CPT exceptions** use WordPress's native variants — no duplication, opt-in
only where a CPT genuinely needs it: `get_header( 'case_study' )` loads
`header-case_study.php` if it exists, otherwise falls back to the global one.
Same for footers. Until an exception is real, everything stays on the sitewide pair.

> 💡 **Extending the system** = one new ACF layout + one new file in
> `template-parts/flexible/`. Nothing else.

## 1.3 Section library — one set, every wave reuses it

Audit the existing Elementor sections, collapse them into a minimal set. Each
row = one Flexible Content layout + one template-part.

| Layout | Purpose | Key fields |
|--------|---------|-----------|
| `hero` | Page / post hero | eyebrow, heading, subheading, media, CTA |
| `text` | Rich text | wysiwyg, width, alignment |
| `text_media` | Text + image/video, L/R | heading, wysiwyg, media, side, CTA |
| `gallery` | Image grid / slider | images[], columns, style |
| `stats` | Metric row | repeater(value, label) |
| `testimonial` | Client quote | quote, author, role, logo |
| `logos` | Logo strip | images[], grayscale |
| `cta` | Conversion band | heading, text, button, bg |
| `features` | Icon + text grid | repeater(icon, title, text) |
| `faq` | Accordion | repeater(q, a) |

- Every layout carries a shared **"block settings" clone** (spacing / background /
  anchor) — this replaces Elementor's per-section controls.
- Lift colours, fonts and the spacing scale from Elementor's global styles into
  **CSS custom properties** in `style.css` so rebuilt sections match the live look.

## 1.4 Editor experience — see it before you save it

The section picker must sell the system to editors. Two upgrades, both powered
by **ACF Extended** (free tier covers all of this) on top of our Flexible
Content field:

- **Visual section picker.** ACFE replaces ACF's plain dropdown with a **grid
  modal showing a thumbnail per layout** — editors pick sections by sight, not
  by name. One screenshot per layout (captured once the template-parts render),
  registered via `acfe/flexible/thumbnail/layout=…`.
- **Live preview before saving.** ACFE's **Dynamic Render** displays the
  section's real front-end template inside the admin as the editor fills it —
  in an **isolated iframe**, so the theme's actual CSS renders without colliding
  with admin styles. Collapsed rows show the rendered section instead of a field
  list; editing happens in a modal.
- **Logical switches & conditional fields.** ACF's native conditional logic
  drives the picker's intelligence, per layout:
  - `media_type` select (image / video / none) → only the matching fields appear
  - `show_cta` toggle → CTA fields appear only when on
  - `background` select (color / image) in the shared block-settings clone →
    shows a color picker or an image field, never both
  - ACFE's **layout toggle** → an editor can switch a section off (grayed out,
    not rendered) without deleting it — drafts survive experiments

> 💡 **Fallback if ACFE is ever dropped:** the thumbnail-modal picker also exists
> as a tiny standalone approach (the `acf-flexible-content-preview` pattern) —
> our layouts and field logic don't depend on ACFE; it only enhances the
> picking/preview UX. Conditional logic is native ACF and works regardless.

## 1.5 Media pipeline — images that are fast by default

Every image travels through one helper (`rmd_image()`) so performance rules are
enforced by code, not by editor discipline. The renderer passes each section its
index — the first section knows it's above the fold.

| Rule | Implementation |
|------|----------------|
| **Hero loads first, everything else waits** | Section 0 imagery: `loading="eager"` + `fetchpriority="high"` (the LCP image must never be lazy — costs 300–1500 ms). All below-fold images: `loading="lazy"` + `decoding="async"`. We do not trust WP's position-based heuristic — the renderer decides. |
| **No layout shift** | Always output `width`/`height` (CSS `aspect-ratio` reserves the box before the image arrives). |
| **Loading effect** | LQIP blur-up: a tiny inlined placeholder shows instantly, the real image fades in on load (`opacity` transition, disabled under `prefers-reduced-motion`). Skeleton shimmer for galleries. |
| **Right size per screen** | `srcset` + accurate `sizes` per block type (WP generates the widths; our helper writes the `sizes`). 4–6 widths up to 1600–2000px for full-bleed heroes. WebP served by the host. |
| **Conditional images (art direction)** | `hero` and `text_media` get an optional **mobile image** subfield → rendered as `<picture>` with a `media` breakpoint: different crop on phones, not just a smaller copy. Falls back to the desktop image automatically when empty. |
| **No CSS-background heroes** | Background-style sections still render a real `<img>` (absolutely positioned) — CSS background URLs are discovered too late for LCP. |

## 1.6 Schedule — Monday to Wednesday 17:00

| When | Focus | Tasks |
|------|-------|-------|
| **Mon PM** | Foundations | Verify P1–P6; escalate any red immediately · `inc/` bootstrap in `functions.php`; ACF Local JSON paths; create `acf-json/` · register `case_study` CPT (public, `has_archive`) + `case_study_cat` taxonomy · push → confirm FTP deploy lands and the CPT appears in staging admin |
| **Tue** | The section library | ACF field group "Case Study — Sections": Flexible Content field `sections` (hero, text, text_media, stats, gallery, testimonial, cta) · shared "block settings" clone on every layout · **commit the generated `acf-json/group_*.json`** — fields are now code · build the matching `template-parts/flexible/*.php`; add renderer + `single-case_study.php` · design tokens → CSS variables in `style.css` |
| **Wed AM** | Prove it | Author one real case study from sections — must render styled, matching an Elementor reference · `archive-case_study.php` + card partial; responsive/QA pass |
| **Wed 17:00** | Sign-off | Final deploy; demo CPT + sections + the live case study · short notes: "how to add a section type" / "how to author a case study" |

> ✅ **Definition of done:** a non-dev can assemble a new case study from
> sections on staging — and adding a new section type is one ACF layout + one
> template-part file.

---

# PART II — The migration method
*repeated every wave*

Every Elementor page moves through the same three steps. The method is the
product — waves just apply it to different content.

1. **Blueprint — export the Elementor JSON.** Editor → Export (or Saved
   Templates → Export). Store under a non-deployed
   `_reference/elementor-exports/`. Read it as a spec — section order, columns,
   spacing, colours, copy. It is **never imported back**; it's the plan for
   rebuilding the page as Flexible Content rows, and the visual-diff reference
   for QA.
2. **Rebuild — same URL, new rendering layer.**
   - *Pages staying Pages* (Home, About): keep the **same post object, slug and
     permalink**; swap the template to ACF, move the copy into sections — zero
     redirects.
   - *Content becoming a CPT*: map slugs **1:1**; only if a base must change,
     add a single-hop **301**. Copy the Rank Math title / meta / schema onto the
     rebuilt page.
3. **Gate — SEO parity, then cutover.** Crawl prod (before) and staging (after)
   with the `rmd-seo-technical-audit` skill (Screaming Frog + GSC + PageSpeed +
   Ahrefs) and diff per URL. The crawl diff is the sign-off artifact. Then:
   deploy to prod → re-crawl → watch GSC coverage and rankings.

> 🔒 **The noindex bridge — how old and new coexist safely.** Rebuilt CPT pages
> launch on production with Rank Math **noindex** and excluded from the XML
> sitemap, while the live Elementor pages keep their index and rankings
> untouched. QA and parity run against the noindexed rebuilds. At cutover, per
> wave: **kill the Elementor page → 301 its URL to the rebuilt one (or swap in
> place on the same URL) → lift noindex → update the sitemap**. The two versions
> are never indexable at the same time — zero duplicate-content risk, and
> rollback is trivial until the flip.

## 2.1 The parity checklist — every URL must pass

- [ ] URL / permalink identical (or single-hop 301 → 200)
- [ ] Title + meta description carried over (Rank Math)
- [ ] H1 identical; H2/H3 order preserved
- [ ] Body copy parity (word-count check)
- [ ] Canonical, OG/Twitter, robots meta unchanged
- [ ] Schema present & valid
- [ ] Image alts kept; responsive sizes
- [ ] Internal links preserved
- [ ] Core Web Vitals equal or better
- [ ] Sitemap intact; no new 404s

> A wave ships **only** when its full URL set passes this list. Anything less waits.

---

# PART III — Roadmap: the waves
*sequenced by sign-off*

Each wave is independently shippable and reversible (revert = repoint the
template / restore Elementor). Ordered easiest-first so the risky pages inherit
a proven system.

## 3.0 Sequence & effort

**Only W0 has a calendar deadline — Wed 22 Jul, 17:00, on staging.rhillane.com.**
Every later phase starts when the previous phase's gate passes, and carries an
effort estimate, not a date.

| Phase | Effort | Starts when | Gate deliverable |
|-------|--------|-------------|------------------|
| **W0** | ≈ 2.5 days | **now → Wed 22 Jul 17:00** | System + first case study live on staging |
| **W0.5** | ≈ 2 days | W0 signed off | Visual picker, live preview, conditional fields, media pipeline v1 |
| **W1** | ≈ 1 week | W0.5 done | Blog migrated, parity passed, Elementor off for posts |
| **W2** | ≈ 2 weeks | W1 gate green | Six services rebuilt + cut over, Elementor service pages retired |
| **W3** | ≈ 2 weeks | W2 gate green | Home, header/footer, mobile menu live; full-site crawl clean |
| **W4** | ≈ 1 week | W3 gate green | Elementor removed from migrated content; performance audit |

### W0 — System + Case Studies
*deadline: Wed 22 Jul 17:00 · staging.rhillane.com*

The foundation (Part I). Ships the scaffold, the section library, and Case
Studies as its proof.

**Done when:** a non-dev builds a case study from sections on staging.

### W0.5 — Editor UX + media hardening
*≈ 2 days · after W0 sign-off*

Two days to make the system pleasant and fast *before* real migration volume
hits it.

- **Day 1:** ACF Extended in — thumbnail grid picker (capture one screenshot per
  layout), modal edit, layout on/off toggle; wire conditional fields
  (`media_type`, `show_cta`, background switch) into every layout
- **Day 2:** Dynamic Render live preview (iframe mode); `rmd_image()` helper —
  eager/lazy by section index, `fetchpriority`, LQIP blur-up, srcset/sizes,
  mobile-image `<picture>` support; retrofit onto the W0 template-parts

**Done when:** editors pick sections visually, preview before saving, and every
image passes the §1.5 rules — verified on the W0 case study.

### W1 — Blog
*≈ 1 week · after W0.5*

Posts are uniform and low-risk — the proving ground before money pages.

- **Days 1–2:** `single.php` + `archive.php`/`index.php` on the renderer,
  reusing the same section library (shared or "Post — Sections" group)
- **Day 3:** migrate posts keeping slug, title, meta, date, author, categories
- **Day 4:** parity diff on the blog URL set; fix deltas
- **Day 5:** cut over → retire Elementor for posts → re-crawl

**Done when:** every post renders via ACF and the blog URL set passes the gate.

### W2 — Services
*≈ 2 weeks · after W1 gate*

Register a `service` CPT (public, `has_archive`) on the same section library.
**Six services, confirmed:**

> Website creation · SEO · Social media · Graphic design · Ads · Media buying

Modelling choice (drives SEO / URLs):

| Option | When |
|--------|------|
| **A — one entry per service** *(recommended)* | Each line is a CPT entry: `/services/seo/`. Simplest; right when each service is one page. |
| **B — CPT + `service_type` taxonomy** | Only if a line has many sub-pages (SEO → technical, local, content); archive per type. |

Build all six as **noindex** first (Part II bridge). Map slugs 1:1 to today's
Elementor service URLs; keep Rank Math title / meta / schema per service. When a
rebuilt service passes the gate, **kill its live Elementor page** — 301 → lift
noindex — service by service, in waves.

- **Days 1–2:** `service` CPT + single/archive templates
- **Days 3–5:** build all six services, noindexed, from the section library
- **Days 6–7:** parity diff + stakeholder visual sign-off per service
- **Days 8–10:** cutover in mini-waves (2 services/day): kill Elementor page →
  301 → lift noindex

**Done when:** every service URL passes the gate and its Elementor original is retired.

### W3 — Home + global
*≈ 2 weeks · after W2 gate*

Home page, header / footer, global CTAs — highest-traffic surfaces move last, on
a fully proven system.

- **Days 1–3:** rebuild header + footer in the child theme (replacing Vault's
  chrome), including the **mobile menu** — see below
- **Days 4–6:** rebuild the homepage from sections, noindexed
- **Days 7–8:** full-site parity crawl + CWV pass
- **Days 9–10:** cutover + monitor GSC

**Mobile menu & conditional behaviour spec.** The hamburger is a real `<button>`
(never a div) with `aria-expanded`; the panel is the native `<dialog>` element —
focus trap, `Esc` to close, and focus return come free and correct. Menu markup
is rendered *once* and adapted by CSS (no duplicate desktop/mobile menus in the
DOM). Conditional behaviour: primary CTA surfaces as a fixed bottom action on
phones; deep submenu levels collapse to accordions; ≥44px touch targets; opening
animation honours `prefers-reduced-motion`; the header reserves its height up
front — zero layout shift on toggle.

**Done when:** full-site crawl diff is clean and the menu passes keyboard +
screen-reader checks.

### W4 — Decommission Elementor
*≈ 1 week · after W3 gate*

Remove Elementor from all migrated content; final performance pass (target:
every migrated URL beats its Elementor CWV baseline).

**Done when:** Elementor renders no migrated URL.

---

# PART IV — Working rules

- **Keys:** prefix `rmd_`; layout keys snake_case, matching the template-part
  filename exactly — the renderer maps them 1:1.
- **Field groups:** author in staging, commit `acf-json/`, deploy. Never edit
  only in the prod DB.
- **Branch per wave** (`wave/blog`, `wave/services`); the PR carries the crawl
  diff as evidence.
- **Keep `_reference/` out of the FTP deploy** — extend the workflow `exclude:` list.

---

## Research references

- [ACFE Flexible Content](https://www.acf-extended.com/features/fields/flexible-content) — thumbnails, modal, layout toggle
- [ACFE Dynamic Render (iframe)](https://www.acf-extended.com/features/fields/flexible-content/dynamic-render-iframe)
- [ACF conditional logic](https://www.advancedcustomfields.com/resources/conditional-logic/)
- [acf-flexible-content-preview](https://github.com/jameelmoses/acf-flexible-content-preview) — fallback picker
- [Never lazy-load the LCP image](https://unlighthouse.dev/learn-lighthouse/lcp/lcp-lazy-loaded)
- [fetchpriority in WP](https://perfmatters.io/docs/fetch-priority/)
- [MDN responsive images / art direction](https://developer.mozilla.org/en-US/docs/Web/HTML/Guides/Responsive_images)
- [Accessible burger menu](https://www.accede-web.com/en/guidelines/rich-interface-components/burger-menu/)

---

*Companion documents: styled runbook `docs/rebuild-plan.html` · plain-language
version `docs/rebuild-plan-simple.html`. This file is the source of truth.*
