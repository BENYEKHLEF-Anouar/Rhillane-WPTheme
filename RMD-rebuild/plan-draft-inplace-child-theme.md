# rhillane.com Rebuild — DRAFT v2: Testing Phase → Production Phase (In-Place Child-Theme Gradual Rebuild)

> Status: draft v2 for review — not yet approved. Supersedes nothing until signed off.
> Companion docs: `plan-approach-1-migrate-first.md`, `plan-approach-2-rebuild-first.md` (both build a new network + cutover; this draft replaces that architecture with an in-place gradual approach).

## Context

rhillane.com is a WordPress **subdirectory multisite** (4 market sub-sites) on Hostinger/LiteSpeed running **UICORE Pro** (Elementor-based). Operator decisions baked into this draft:

- Production stays on the **current live install** — no new network, no domain cutover.
- Two macro phases: a **Testing Phase** entirely on staging, then a **Production Phase** that begins only when every open question is answered.
- Pilot content type: **Case Studies** CPT built with ACF flexible content (an old case-studies CPT exists on live with a single draft post — resolved deliberately in T4).
- Production entry: child theme activation (invisible) + case studies published as the first visible new-design content.
- Design system already exists — the HTML ads landing pages; the child theme's Tailwind foundation ports those tokens.
- URLs/sitemap preserved exactly for all indexed pages and post types.
- End-state: fully manageable via the new workflow (ACF flexible content + git-versioned theme); Elementor, UICORE, legacy plugins, custom scripts removed; multisite DB cleaned.
- No dates, hour estimates, or owner assignments (plan, not roadmap).
- Core mechanism: keep UICORE Pro active as parent, add a **child theme** carrying the new UI via self-contained page templates, flip pages one by one, swap to a standalone theme only at the very end.

## Feasibility verdict: VIABLE — with 3 gate tests required on staging

Research findings (official sources only — UiCore, WordPress, Elementor, ACF docs):

**Confirmed working:**

1. UiCore ships an **official child theme** (one-click: Theme Options → Systems → Install & Activate Child Theme). — help.uicore.co
2. **Custom page templates** in a child theme are per-page assignable, stored in `_wp_page_template` postmeta, and **persist across a later theme switch** — if the final standalone theme keeps the same template filenames, every page assignment keeps working. Missing template files fall back silently to `page.php` (no fatal). — developer.wordpress.org
3. A template can be **fully standalone** (own `<html>` markup, `wp_head()`/`wp_footer()`, never `get_header()`) — the UICORE header/footer never render on it. New pages get the new header/footer from template parts; old pages keep UICORE's Theme Builder header. — developer.wordpress.org
4. **Elementor frontend assets already don't load on pages with no Elementor data** (official Elementor docs) — the new templates are clean by default; only UiCore Framework globals may need targeted dequeues.
5. **`_elementor_data` and ACF field data coexist on the same post** (postmeta, no exclusivity) → flipping a page = reassign its template; rollback = reassign back. Old Elementor data stays untouched until final cleanup. Per-page rollback is trivial.
6. **Theme activation is per-site on multisite** → child theme rolls out sub-site by sub-site.
7. UICORE Pro hard-requires four plugins: Elementor, UiCore Framework, UiCore Elements, UiCore Animate — all four stay until the end-game swap, then all four go.

**The 3 undocumented risks → staging gate tests:**

- **G1:** Do UICORE Theme Options survive child activation? (wp_options = survives; theme_mods = resets). Test on staging clone; mitigation if theme_mods: copy `theme_mods_{parent}` → `theme_mods_{child}` before activation.
- **G2:** Menu locations (`nav_menu_locations`) and widgets on child activation — menus themselves survive, location assignments reset. Same theme-mods copy fixes it; verify.
- **G3:** UICORE on multisite + ACF Pro license activations (quota-based, date-dependent rules; must cover 4 sub-sites + staging). Verify both licenses before build.

## Architecture

**Environments (3, one job each):**

- **Local:** theme code only (git repo: child theme PHP, Tailwind build, `acf-json/`, scripts). No local WP multisite — reproducing forced-subdirectory multisite + LiteSpeed locally costs more than it protects.
- **Staging:** manual full clone of the multisite (DB dump + `wp search-replace --network` + files rsync) on the same Hostinger server, HTTP-auth + noindex. Hostinger's one-click staging is not trusted for multisite; the clone is a scripted, re-runnable procedure. **Re-cloned from production before every phase** (kills drift). Defanged on every clone: outgoing mail off, external wp-cron jobs off, tracking IDs blanked, license activations not consumed where avoidable.
- **Production:** receives changes only via (a) git + CI code deploys (existing `deploy1.yml` pattern + manual-approval production workflow; ACF field groups travel as `acf-json/` inside the theme), (b) the per-page flip procedure. No direct file/field edits on live, ever.

**The child theme** (`uicore-child`, extended):

- `functions.php`: ACF JSON path, options page, asset enqueue scoped to the new templates, new-CPT registrations (code, not UI), theme-mods copy utility.
- `template-rmd.php` (+ variants if needed): standalone template — own markup, new header/footer from `template-parts/`, `page_layouts` ACF flexible-content dispatch loop (convention: layout slug `foo_section` loads `template-parts/layouts/foo.php`).
- All new templates written **with zero calls into parent functions** from day one → the end-game "standalone theme" is this child re-headed (drop `Template:` line in style.css, add minimal `index.php`), template filenames unchanged so `_wp_page_template` assignments survive.

## Where to start — first three actions

1. **Licenses + access audit (gate G3):** confirm ACF Pro activation quota covers 4 sub-sites + staging, UiCore license status, Hostinger/SSH/DB access, GSC + GitHub access. Nothing else can safely start without this.
2. **Scripted staging clone (T1):** the clone procedure is the foundation of every later rehearsal — build it as a re-runnable script, not a one-off.
3. **Repo + child theme skeleton (start of T3):** git repo with the official uicore-child as base, Tailwind build wired, CI deploy to staging.

## TESTING PHASE — everything on staging; exit only when every question is answered

**T0 — Prep & light inventory (parallel with T1–T3):**

- Licenses/access audit (gate G3).
- Plugin + custom-script + CPT inventory: how is the existing case-studies CPT registered (theme? plugin? UiCore portfolio?), what other CPTs exist, keep/retire decision per item.
- Redirect-layer inventory: .htaccess rules, Rank Math redirects, Polylang behavior (`x-redirect-by` checks; LiteSpeed `Redirect` quirk).
- **Design tokens extraction:** port colors/type/spacing/components from the existing ads LP HTML files into the Tailwind config — this is the "styling rules" source.

**T1 — Create the staging website:**

- Scripted, re-runnable clone: DB dump + `wp search-replace --network` (serialization-aware) + files rsync, same Hostinger server, HTTP auth + noindex.
- Defang checklist applied on every (re-)clone: outgoing mail off, external wp-cron off, tracking IDs blanked, license activations not consumed where avoidable.
- Acceptance: staging renders identical to live behind auth; re-clone runs from one command/runbook.

**T2 — Add the dependencies:**

- ACF Pro installed + activated (license verified in T0), official UiCore child theme installed (not yet active), Rank Math confirmed working on the clone.
- Git repo + CI wired: push → deploy to staging child-theme directory (existing `deploy1.yml` pattern); production workflow created but approval-gated.
- Acceptance: a test commit lands on staging via CI.

**T3 — Create the child theme with styling rules and everything:**

- `functions.php`: ACF JSON path, options page, scoped asset enqueue, theme-mods copy utility, CPT registrations.
- Tailwind foundation from the T0 tokens; compiled assets committed/built via CI.
- Standalone template scaffold (`template-rmd.php` pattern) + new header/footer template parts; zero calls into parent functions.
- **Gate tests G1/G2 here:** activate the child on the staging FR sub-site → do UICORE Theme Options survive? menu locations/widgets? Apply the theme-mods copy fix; then run the automated parity script (Python + Playwright: status/title/meta/H1/canonical/hreflang/robots/content-hash/screenshot pairs → XLSX report) expecting **zero diffs with the child active and no ACF pages yet**.
- Acceptance: parity report green with child active; styling foundation renders a sample page with the LP design system.

**T4 — First custom post type: Case Studies (flexible content):**

- Resolve the existing CPT first: identify its registration source; decide reuse-slug (register the same slug in child code, remove the old registration at cleanup) vs new slug + delete the single draft. One draft, nothing indexed → either is safe; decide deliberately and log it.
- Register the CPT in child-theme code; build the flexible-content field group(s) (`case_study_layouts`) exported to `acf-json/` and committed.
- Create 2–3 real sample case studies on staging with actual content.
- Acceptance: field group versioned in git; sample posts fully editable through ACF only.

**T5 — Archive + single templates for Case Studies:**

- `archive-{slug}.php` + `single-{slug}.php` in the child theme, built standalone (new header/footer), archive pagination, breadcrumbs.
- Rank Math integration: titles/meta, schema type decision (Article/CaseStudy), sitemap inclusion rules (CPT stays noindex/sitemap-excluded until production content is ready).
- Acceptance: archive + singles render the sample posts correctly desktop/mobile; meta + schema validate; CWV sample beats the live-site baseline.

**T6 — Heavy inventory + testing-phase exit gate:**

- Full Screaming Frog crawl ×4 markets (JS rendering on), GSC exports per property, master URL inventory (100% keep), ranking baseline (Ahrefs + GSC), content-freeze/delta-log convention per wave.
- **Exit checklist (all must be YES to enter production):**
  - [ ] G1–G3 green
  - [ ] Parity report green with child theme active
  - [ ] Case-studies pilot approved on staging (design + workflow + performance)
  - [ ] CPT slug decision logged
  - [ ] Master URL inventory complete
  - [ ] Rollback runbooks written (child activation + per-page flip)
  - [ ] Open-questions list empty

## PRODUCTION PHASE — live rollout, per service, in order

**P1 — Child theme activation on live (invisible):** one sub-site at a time, smallest market first; same-day rehearsal on a fresh clone; full verified backup minutes before; theme-mods copy; activate; purge LiteSpeed + CDN (probe with `?cbust=`); parity spot-check against the pre-activation state. Rollback: reactivate parent + restore theme mods (minutes). Visible change target: **none**.

**P2 — Case studies live (first visible new design):** the theme deploy already carries the CPT + templates; enter real case studies on live; publish; enable sitemap inclusion + submit in GSC; add navigation entry point(s). Additive launch — no existing URL changes.

**P3 — Redesign waves per service (per market):**

1. Build/QA the wave's pages on a fresh staging clone.
2. Enter content on live as ACF fields while the page still renders Elementor (invisible to visitors).
3. Pre-flight checklist per page: title/meta/H1/substantive content/internal anchors/image alts unchanged.
4. **Flip = assign the new template** to the page. Purge caches.
5. Parity-lite + CWV check; live form + tracking test if the page has either.
6. Monitor GSC for the wave's URLs; wave retro before the next wave opens.

Rollback per page = reassign the old template (Elementor data intact). Editors are blocked from re-editing flipped pages with Elementor (capability/UI lock) to prevent stale republish.

**P4 — Blog wave:** template-level (`single.php` equivalent) — posts render through it automatically, no per-post assembly.

**P5 — End-game theme swap:** precondition = 100% of pages/posts/CPTs on new templates + agreed stability window. Convert child → standalone theme (same folder/file names → `_wp_page_template` assignments survive), activate per sub-site (a much smaller "global moment" — nothing renders through the parent anymore), verify with a full parity crawl.

**P6 — Cleanup (in-place DB surgery, per sub-site):**

1. Deactivate + delete: Elementor (+Pro/addons), UiCore Framework, UiCore Elements, UiCore Animate, UICORE parent theme, every legacy plugin/script on the T0 retire list (including the old case-studies CPT registration source).
2. DB per blog tables: `_elementor_*` postmeta, `elementor_library` CPT posts, `elementor*`/`uicore*` options, expired transients, orphaned tables from removed plugins, autoload audit; sitemeta cruft at network level.
3. Files: `uploads/elementor/`, retired theme dirs, orphaned custom scripts (mu-plugins, WPCode snippets).
4. Legacy multisite audit: upload-path constants (`UPLOADS`/`blogs.dir`/ms-files rewriting), stale `.htaccess` rules — remove one at a time and re-test (LiteSpeed Redirect quirk).
5. Keepers: ACF Pro, Rank Math, forms plugin, LiteSpeed Cache (+ Polylang if load-bearing for hreflang).
6. Final verification: full crawl green, CWV sample vs baseline, GSC coverage stable.

## Collaboration options (mechanics; staffing deferred)

- **Code:** git PRs into the theme repo; `acf-json/` is the merge-conflict hotspot → one person per field group per branch; CI lint + staging deploy on merge; production deploy behind manual approval.
- **Content:** non-dev helpers enter ACF content on live drafts from a mapping sheet (no code access); per-market ownership splits cleanly since sub-sites are independent.
- **QA:** parity script runs are delegable; top-50 manual review per market is parallelizable per person.
- **Wave discipline:** only one wave open per market at a time; every wave has a named flip executor and a rollback owner.

## Risk register

| # | Risk | Mitigation |
|---|------|------------|
| 1 | UICORE options stored as theme_mods → settings vanish at child activation | Gate test G1; theme-mods copy script |
| 2 | Menu locations/widgets reset at child activation | Theme-mods copy; parity check before/after |
| 3 | Plugin/theme auto-updates mid-wave change behavior on live | Disable auto-updates for the Elementor/UiCore stack during transition; pin versions |
| 4 | LiteSpeed/CDN serve mixed old/new states after flips | Purge procedure per flip; `?cbust=` probing; documented cache rules |
| 5 | Staging leaks: emails to real clients, cron pings, Google indexing the clone, license quota consumed | Defang checklist on every re-clone; HTTP auth + noindex; license activation audit |
| 6 | `wp search-replace` corrupting serialized data on manual clone | WP-CLI (serialization-aware) with `--network`; spot-check after clone |
| 7 | Multiple redirect layers (.htaccess/Rank Math/Polylang) interact with flipped pages unpredictably | T0 redirect inventory; check `x-redirect-by` when probing |
| 8 | Two different headers/brands visible simultaneously during waves | Accepted by design — board must sign this off explicitly |
| 9 | Google re-evaluates each flipped page (DOM change, same content) | Pre-flight parity checklist; per-wave GSC watch; per-page rollback |
| 10 | Retired CPTs 404 indexed URLs | Inventory-driven: preserve slugs in new CPT registration or 301 map; sitemap diff before/after each wave |
| 11 | Editor republishes a flipped page from Elementor with stale data | Capability/UI lock on migrated pages |
| 12 | Rollback impossible after cleanup deletes Elementor data | Cleanup runs only after full-site stability window; verified backups retained |
| 13 | ACF Pro license quota insufficient (4 sub-sites + staging; date-dependent rules) | Gate test G3 before build |
| 14 | Forms/tracking continuity breaks on flipped pages (Elementor Pro forms retired) | Forms + GTM/GA4 parity in every wave's pre-flight; test submission per flip |
| 15 | Hreflang set breaks when one market's page is new and siblings are old | Hreflang preserved at meta layer (Rank Math/Polylang), independent of template; verify in parity script |
| 16 | Forced-subdirectory legacy (upload paths, ms-files, custom rewrites) surfaces during cleanup | Dedicated legacy audit step; remove rules one at a time with re-tests |
| 17 | Content drift between staging rehearsal and live execution | Re-clone staging before every phase; per-wave freeze + delta log |
| 18 | Backup not actually restorable when needed | Restore-test the backup on staging before each live-touching phase |
| 19 | Old case-studies CPT registration conflicts with the new one (duplicate slug/rewrite rules) | Resolved in T4 before any registration ships |
| 20 | Design tokens drift between the ads LPs and the site build | Tokens live in one committed Tailwind config; LPs adopt it later if desired |

## Open questions before this becomes final

1. Gate tests G1–G3 must pass on staging — the plan's viability rests on them.
2. Board sign-off on risk #8 (mixed headers during the transition window).
3. Plugin/CPT retire list (T0 output) — decides the exact cleanup scope.
4. Whether Polylang is load-bearing for hreflang (keeper vs retire).
5. Case-studies CPT slug decision (reuse vs new — T4).
6. Staffing + timeline — deliberately excluded here; layered on once the approach is final.
