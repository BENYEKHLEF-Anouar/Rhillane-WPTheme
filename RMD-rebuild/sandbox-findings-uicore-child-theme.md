# Sandbox findings — UICORE Pro + child theme + ACF flexible content

**Sandbox:** orbixwp.com (throwaway single-site WP, Hostinger, WP 7.0.1, UICORE Pro 7.0.1, Elementor + Elementor Pro, ACF Pro 6.8.2)
**Test window:** 2026-07-15 · **Operator:** Jalal · **ClickUp:** "UICORE Pro + child theme sandbox test" task in *0. Planning Phase* (all 8 subtasks closed with per-task findings comments)
**Verdict: the in-place child-theme architecture is CONFIRMED viable.** Every mechanism the final plan depends on works on a real UICORE site.

## What was proven

| # | Question                                              | Result                                                                                                                             |
| - | ----------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| 1 | Child theme installs + activates beside UICORE Pro    | ✅ (manually — see gotcha 1)                                                                                                        |
| 2 | G1: do UICORE Theme Options survive child activation? | ✅ Options are global `wp_options` (`uicore_*` keys) — theme-independent                                                            |
| 3 | G2: menus + widgets after activation                  | ✅ Menu location seeded into child theme\_mods by WP; UICORE header uses its own Theme Builder anyway; no widget orphans            |
| 4 | Standalone template bypasses UICORE header/footer     | ✅ Own markup, `wp_head()`/`wp_footer()` kept, zero UICORE chrome                                                                   |
| 5 | ACF flexible content + dispatch loop                  | ✅ `page_layouts` → `{name}_section` → `template-parts/layouts/{name}.php`; acf-json auto-sync works                                |
| 6 | Legacy assets removable on new-stack pages            | ✅ Zero elementor/uicore/block-library assets; legacy pages untouched (verified against control page)                               |
| 7 | CPT in code + archive/single templates                | ✅ `case_study` registered in child code; `/case-studies/` + singles render via new stack; same block library powers pages AND CPTs |
| 8 | Findings documented                                   | ✅ this document                                                                                                                    |

## Production runbook rules earned in the sandbox

1. **Don't use UICORE's one-click child installer** — it failed with "Download Error" (v7.0.1, valid license, after Refresh Data). Create the child manually; ours is git-versioned anyway.
2. **After child activation: Elementor → Tools → Regenerate CSS & Data + purge caches.** Stale generated CSS broke footer icon styling until regenerated. Mandatory P1 step, before the parity spot-check.
3. **Asset dequeue must run on 3 hooks** — `wp_print_styles@999`, `wp_print_scripts@999`, **and** **`wp_print_footer_scripts@1`**: Elementor enqueues theme-builder CSS *during* rendering and WP prints it before `</body>`; head-time sweeps miss it.
4. **After (re)registering any CPT: Settings → Permalinks → Save** to flush rewrite rules, or the new URLs 404.
5. **ACF location rules:** additional surfaces (CPTs) are separate OR rule groups — an AND row inside an existing group silently hides the fields.
6. **ACF field names are contracts.** Label ≠ name; a stray `subtitle_` made `get_sub_field('subtitle')` empty while sibling fields rendered. Lock names in review; renaming orphans saved data.
7. **`rmd_is_new_stack_page()`** **is the single ownership test** — every new-stack surface gets added there once, and all guards (asset strip, future enqueues) follow automatically.

## What the sandbox could NOT prove (stays in Testing Phase T3 on the real staging clone)

* rhillane's actual Theme Options / menus / Theme Builder templates surviving activation (sandbox had demo data, not 10 years of production state).
* Multisite behavior: per-sub-site activation, per-blog tables, forced-subdirectory legacy.
* Interaction with rhillane's plugin set (Polylang/Rank Math redirects, LiteSpeed plugin, custom scripts).
* Elementor Pro forms/popups on legacy pages while the child is active.

## Artifacts

* Theme code (versioned mirror): `Website-Rebuild/uicore-pro-child/` — every function docblocked for contributors; README.md is the onboarding entry point.
* Live sandbox: orbixwp.com (`/rmd-template-test/`, `/case-studies/`).
* Plan this feeds: `plan-draft-inplace-child-theme.md` → Testing Phase T3 gate tests.

