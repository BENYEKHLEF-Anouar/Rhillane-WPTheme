# RMD Website Rebuild — Custom Post Type Inventory & UI Scope

**ClickUp task:** [86bb20c9c — Prepare UI Plan (HTML) for all CPTs](https://app.clickup.com/t/86bb20c9c)
**ClickUp folder:** RMD website rebuild (ACF Flexible content) → *0. Planning Phase*
**Date:** 23 July 2026 · **Owner:** Anouar · **Status:** for review

---

## 0. Decisions taken

Recorded here as they are made, so the tables below always reflect the current agreed architecture.

| # | Date | Decision | Impact on this document |
| :-- | :-- | :-- | :-- |
| **D-01** | 23 Jul 2026 | **Services are not a custom post type.** They remain WordPress **Pages**. Keep the current structure — same URLs, same post type — and redesign the template. | The `services` CPT is dropped from scope and its empty registration deleted. A redesigned **service page template** is added to the build. The open question on migrating the service pages into a CPT is closed. See §5.1. |
| **D-02** | 23 Jul 2026 | **Every author profile gets its own single page** — not only an archive listing of their articles. | A dedicated **Author Profiles CPT** is added, with an archive and a single. The core `/author/{slug}/` archive is retired in its favour. See §5.9. |
| **D-03** | 23 Jul 2026 | **Service pages are implemented last.** The page migration completes first — all pages move onto the new stack unchanged, inside the `page` post type — and only then are the services redesigned. They are the riskiest surface in the programme. | The service page template moves out of wave 1 into a dedicated **wave 3**, executed after the wave-2 page migration. Wave 1 drops to **14** templates. Risk evidence in §5.1; sequencing in §6. |
| **D-04** | 23 Jul 2026 | **Services convert in two stages, separated by an SEO observation window.** Stage A ports the 8 service pages to ACF flexible content with the **current UI reproduced exactly** — no visual change, no change to any SEO signal. Google behaviour and rankings are then monitored. Only once stability is confirmed does stage B apply the new UI and style. | Wave 3 splits into **3a — structural port** and **3b — visual redesign**, with a monitoring gate between them. Adds one build template that carries no new design work. See §5.1. |
| **D-05** | 23 Jul 2026 | **Pages are triaged by click volume.** Pages with zero or minimal clicks may have their UI changed early — they are the proving ground for the new design system. They may be converted to a CPT, or served by a section library on the `page` post type. | Adds a **low-click page template** to wave 2, and a triage step that must precede it. Requires a Search Console clicks-per-page export. See §5.11. |
| **D-06** | 23 Jul 2026 | **Team Members and Author Profiles are two separate content types.** They answer different questions, carry different fields and serve different audiences. They are not merged into one object. | Two distinct CPTs — **7 CPTs in scope**, and **four templates** in the people layer instead of three. Wave 1 rises to **15** templates. See §5.9. |

---

## 1. Purpose & method

This document establishes the **authoritative inventory of every content type** on rhillane.com, and fixes the UI production scope for the rebuild: which templates must be designed, in which order, and which decisions must be settled before design work starts.

Every row below was verified against the **live production site** on 23 July 2026, not from memory or from the ClickUp brief:

| Source | What was checked |
| :-- | :-- |
| `/wp-json/wp/v2/types` | Every registered post type, its archive flag and attached taxonomies — on all four market sub-sites |
| `/wp-json/wp/v2/taxonomies` | Every registered taxonomy and its post-type binding |
| REST collection endpoints | Actual published item counts per post type |
| `sitemap_index.xml` + child sitemaps | Indexed URL volume and sitemap coverage per type |
| Rendered archive URLs | Whether each archive actually resolves, and what it renders today |
| `<meta name="robots">` | Index/noindex status of each archive surface |

> **Terminology note.** The brief lists eleven "custom posts", but several of them are not custom post types: *Services* (per D-01), *About us*, *Author pages* and *Candidature spontanée* are core WordPress surfaces or a page-plus-form. They are all still in scope for UI, so they are inventoried in **Table B** rather than dropped.

### Build-type convention

A post type existing in the WordPress database does **not** make it an existing asset. Several CPTs are registered but hold no usable content and have no designed UI — an empty archive, or unstyled cards with no titles. These are **planned and costed as net-new builds**; the pre-existing registration is recorded only as a legacy remnant to be resolved at cleanup.

| Build type | Meaning |
| :-- | :-- |
| 🆕 **New build** | Designed and built from scratch. Nothing usable exists today. |
| 🔄 **Redesign** | Real content exists and must be migrated or kept in place, then restyled on the new stack. |
| 🔁 **Restructure** | The concept survives, but as a different object (e.g. CPT → taxonomy). |
| ➖ **Port as-is** | Existing UI carried forward unchanged onto the new stack. |

---

## 2. Table A — Custom Post Types (the ClickUp deliverable)

| # | Content type | Slug | Build type | Legacy on live | Archive | Taxonomy | Wave |
| :-- | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| 1 | **Case Studies** | `case-studies` | 🔄 **Redesign** | CPT registered · 1 published item ("Mariner") · archive resolves | ✅ **Yes** — `/case-studies/` | ❌ **No** | **1** (pilot) |
| 2 | **Portfolio** | `portfolio` | 🔄 **Redesign** | CPT registered · 6 real items · archive is a fully designed page | ✅ **Yes** — `/portfolio/` | ❌ **No** — `portfolio_category` exists with 0 terms | **1** |
| 3 | **Reviews** | `reviews` | 🆕 **New build** | *Small note:* CPT registered, but the 2 items have **empty titles** and the archive renders unstyled cards (date + button only) | ✅ **Yes** — designed hub (see §5.4) | ✅ **Yes** — by source **and** service | **1** |
| 4 | **Job Offers** *(Offres d'emploi)* | *to define* | 🆕 **New build** | None — nothing exists | ❌ **No** — listings live on the Recrutement hub (§5.5) | ✅ **Yes** — department, contract type, location | **1** |
| 5 | **Departments** | `departements` | 🔁 **Restructure** → taxonomy of Job Offers | *Small note:* CPT registered, but **0 posts** and the archive returns **404** | ➖ N/A as a taxonomy | — *(it becomes the taxonomy)* | **1** |
| 6 | **Team Members** | `team` *(slug TBD)* | 🆕 **New build** | None — nothing exists | ✅ **Yes** — `/team/` | ➖ Optional (department) | **1** |
| 7 | **Author Profiles** | `author-profile` *(slug TBD)* | 🆕 **New build** | None — only the bare core author archive | ✅ **Yes** — `/authors/` | ➖ No | **1** |
| ~~—~~ | ~~**Services**~~ | ~~`services`~~ | ❌ **Not a CPT** — see **D-01** | CPT registered with **0 posts**; registration to be deleted | — | — | Moved to **Table B, row 9a** |

**Legend** — 🆕 built from scratch · 🔄 real content, kept or migrated · 🔁 changes object type · ✅ in scope · ❌ not needed · ➖ not applicable

**Seven custom post types remain in scope.** Services was in the brief as a CPT; decision **D-01** moved it to the `page` post type, where it is redesigned in place. Team Members and Author Profiles are two separate types per **D-06** — see §5.9 for why they are not merged.

### Legacy registrations to resolve

Four types carry a pre-existing registration that must be deliberately dealt with — not silently inherited. This is the same decision the rebuild plan already flags for Case Studies in **T4**.

| Legacy object | What exists | Resolution |
| :-- | :-- | :-- |
| `case-studies` | 1 published item | Decide **reuse the slug** (register it in child-theme code, drop the old registration at cleanup) or **new slug + migrate the item**. Log the decision. |
| `reviews` | 2 items, no titles, no usable markup | Reuse the slug; **discard both items** and re-enter reviews properly in the new ACF model. |
| `services` | 0 posts | **Delete the registration** (D-01). Decide what happens to the `/services/` URL — see §5.1. |
| `departements` | 0 posts, archive 404 | Delete the CPT registration entirely; the concept returns as a taxonomy on Job Offers. |

Because none of these has indexed, ranking content behind it, slug reuse and deletion are low-risk in every case — but each must be **decided and recorded**, not assumed.

### Post types deliberately excluded from scope

Registered on the site but owned by plugins, not by the brand. They are **retire-list candidates** under the rebuild plan's P6 cleanup, not UI work:

`elementor_library` · `elementor_snippet` · `e-floating-buttons` · `jet-form-builder` · `jet-engine` · `uicore-cd` · `uicore-tb` · `rank_math_locations`

---

## 3. Table B — Core content types & special surfaces

Not custom post types, but each needs a template and appears in the brief. Note that the `page` post type now carries **two distinct templates** on two different waves — a direct consequence of D-01.

| # | Surface | Type | Build type | Live status | Items | Archive | Taxonomy | Wave |
| :-- | :-- | :-- | :-- | :-- | --: | :-- | :-- | :-- |
| **9a** | **Service pages** ⭐ | `page` (core) | 🔄 **Redesign, two-stage** | ✅ Live — the 8 services in the main navigation. **Keep the current structure and URLs** (D-01) | **8** | ➖ N/A — nav-driven | ✅ Service family — see §5.2 | **3a → ⏸ → 3b** ⚠️ *last* |
| **9b** | **Standard pages — tier A** *(low-click)* | `page` (core) | 🔄 **Redesign** — the proving ground (D-05) | ✅ Live — the low-value end of the SEO landing matrix; set determined by the Search Console export | *TBD* | ➖ None natively — see §5.10 | ➖ Optional `page_cluster` — see §5.10 | **2** |
| **9c** | **Standard pages — tiers B & C** | `page` (core) | ➖ **Port as-is** | ✅ Live — legal, contact, city pages, and the trafficked part of the SEO matrix | **146** *minus tier A* | ➖ None natively — see §5.10 | ➖ None natively — see §5.10 | **2** |
| 8 | **Blog** | `post` (core) | ➖ **Port as-is** | ✅ Live, highest content volume | **193** indexed | ✅ Yes | ✅ Yes — `category` (42 terms) + `post_tag` | **2** |
| 10 | **Core author archive** | `/author/{slug}/` | ❌ **Retired** — 301 to the Author Profile single (§5.9) | ⚠️ Bare post list — no bio, no avatar, no credentials; **`noindex`** | 7 authors | ➖ Redirected, no template | ➖ N/A | **1** |
| 11 | **About us** | `page` | 🔄 **Redesign** | ✅ Live — `/agence-de-communication/` | 1 | ➖ N/A | ➖ N/A | **1** |
| 12 | **Recrutement hub** *(incl. candidature spontanée)* | `page` + form | 🔄 **Redesign** | ✅ Live — `/recrutement/`; form only, **no job listings**; **`noindex`** | 1 | ➖ N/A | ➖ N/A | **1** |

⭐ **Row 9a is the D-01 outcome, sequenced last by D-03.** Services stay in the `page` post type at their existing URLs. Because `_wp_page_template` is assignable per page, the eight service pages can be flipped onto the new template **one at a time** — exactly the per-page flip mechanism the rebuild plan describes in P3. No URL changes, no migration, per-page rollback.

Note that rows 9a, 9b and 9c are the **same post type on different tracks**. In wave 2 all 154 pages — services included — migrate onto the new stack unchanged; the low-click tier A set is then redesigned as the proving ground (D-05); and in wave 3 the eight service pages are flipped, structurally first and visually only after the observation window (D-04).

**The 8 service pages:** Sites Web · SEO – Référencement naturel · Publicité en ligne · Développement d'Application · Gestion des réseaux sociaux · Design Graphique · Copywriting · Photographie

**Existing spontaneous-application form fields** (to be preserved in the redesign): Nom · Adresse e-mail · Téléphone · Profil · CV (file upload) · Vos motivations · consent checkbox.

The Recrutement hub absorbs the Job Offers listing (§5.5), so it carries three jobs in one template: employer-brand content, the open-positions list, and the spontaneous application form.

---

## 4. UI production scope — templates to design

The ClickUp brief requires, for every CPT with a taxonomy, **both an archive template and a single template**. Applied to the inventory above:

Three waves, ordered by **ascending SEO risk** (D-03) — the lowest-exposure content first, the commercial core last. Within the page layer, exposure is measured by actual Search Console clicks (D-05), and the final wave separates the structural change from the visual one with a monitoring gate (D-04).

### Wave 1 — 15 templates · design & build, low risk

| Content type | Archive | Single / page | Taxonomy term | Subtotal |
| :-- | :-: | :-: | :-: | --: |
| Case Studies | ✅ | ✅ | — | 2 |
| Portfolio | ✅ | ✅ | — | 2 |
| Reviews | ✅ (hub) | ✅ | ✅ (by source) | 3 |
| Job Offers | — *(no archive)* | ✅ | ✅ (by department) | 2 |
| **Team Members** *(D-06)* | ✅ | ✅ | — | **2** |
| **Author Profiles** *(D-02, D-06)* | ✅ | ✅ | — | **2** |
| About us | — | ✅ | — | 1 |
| Recrutement hub | — | ✅ | — | 1 |
| **Total** | **6** | **7** | **2** | **15** |

**Nine of the fifteen are built from scratch** — Reviews ×3, Job Offers ×2, Team Members ×2, Author Profiles ×2. The other six are redesigns of surfaces with real content behind them.

> **The people layer is four templates, not three.** Team Members and Author Profiles are separate content types (D-06), each with an archive and a single. The core `/author/{slug}/` archive is retired and 301'd to the author profile single, so it needs no template of its own — see §5.9.

### Wave 2 — 6 templates · migrate the page layer, redesign only what is safe

| Content type | Templates | New design? | Subtotal |
| :-- | :-- | :-- | --: |
| Blog | archive · single · category · tag | ❌ Existing UI carried forward | 4 |
| Standard pages — as-is port | reproduces the current page design on the new stack | ❌ Reproduction | 1 |
| **Low-click pages — new UI** *(D-05)* | flexible-content page template + section library | ✅ Yes | 1 |
| **Total** | | | **6** |

All 154 pages first move onto the new stack **as they are**, inside the `page` post type, with no design change. The pages that Search Console shows carry **zero or minimal clicks** are then redesigned on the new template — this is the proving ground for the new design system, on content with nothing to lose. See §5.11.

Wave 2 must complete before wave 3 opens.

### Wave 3 — 2 templates · the commercial core, highest risk (D-03, D-04)

Services convert in **two stages with a monitoring gate between them**. Stage A changes the plumbing without changing anything a visitor or a crawler sees; only after the site proves stable does stage B change the design.

| Stage | What happens | New design? | Templates |
| :-- | :-- | :-- | --: |
| **3a — structural port** | The 8 service pages move to ACF flexible content with the **current UI reproduced exactly**. No visual change, no change to titles, meta, headings, content or internal links. | ❌ Reproduction | 1 |
| **⏸ Observation window** | Monitor Google behaviour, rankings, impressions, clicks and Core Web Vitals across the 8 pages. **No further change until stable.** | — | — |
| **3b — visual redesign** | The new UI and style are applied to the same pages, reusing the section library built in wave 2. | ✅ Yes | 1 |
| *Optional* | Services overview page at `/services/` — see §5.1 | ✅ Yes | *+1* |
| **Total** | | | **2** *(3 with the overview)* |

**Grand total: 23 templates to build** (24 with the optional services overview), of which **21 carry new UI design work** — the two as-is reproductions (standard pages in wave 2, and stage 3a) replicate designs that already exist rather than creating new ones.

Wave 1 and the redesign halves of waves 2 and 3 are design-and-build; the reproductions are ports of the current UI onto the new stack, per the ClickUp instruction *"For the POST CPT: keep the UI the same as it currently is on the RMD website."*

---

## 5. Findings & decisions required before design starts

### 5.1 ✅ DECIDED — Services stay as Pages (D-01)

**Decision:** the services are handled inside the `page` post type. Keep the current structure; redesign the template.

This closes the question the inventory raised. The evidence behind it:

- `services` was registered as a CPT with `has_archive: true`, but held **zero** posts — `/services/` rendered "No posts found". It was a registration with nothing behind it.
- The eight real services already live as WordPress **Pages** linked from the main navigation, at established and indexed URLs.

**What follows from the decision:**

| Consequence | Detail |
| :-- | :-- |
| CPT dropped | The empty `services` registration is deleted at cleanup. Nothing to migrate. |
| URLs untouched | The 8 service pages keep their exact current URLs — no rewrite rules, no redirects, no ranking risk. This is the main advantage of the decision. |
| Per-page flip | `_wp_page_template` is assignable per page, so the services can move onto the new template **one at a time**, with per-page rollback. Matches the rebuild plan's P3 mechanism exactly. |
| Template count | Services drop from 3 templates (archive + single + term) to **1** — a redesigned service page template. Wave 1 goes from 18 to 15. |
| Taxonomy still needed | The service-family taxonomy (§5.2, §5.3) is still required — it now attaches to `page` instead of a `services` CPT. |

**Sequencing (D-03) — services go last.** The service page redesign is the **final step of the programme**, executed only after the wave-2 page migration has completed and the 154 pages sit on the new stack unchanged. Until then the services are simply migrated as they are, inside the `page` post type.

**Why this is the riskiest surface — the evidence behind D-03:**

| Risk factor | Evidence |
| :-- | :-- |
| They are the commercial core | The 8 services are the agency's entire offer and the primary navigation dropdown. |
| Real rankings to lose | Unlike every other type in this plan — Case Studies (1 item), Portfolio (6), Reviews (2 unusable), Job Offers and Team (nothing exists) — the service pages carry established rankings and revenue-driving traffic. |
| Largest dependency cluster | Roughly 100 SEO landing pages (city / industry / platform) point at and support them. Any change ripples through that cluster. |
| Google re-evaluates on DOM change | Rebuild plan risk #9: same content, new markup, forced re-crawl and re-assessment per flipped page. |
| Forms and tracking live on them | Rebuild plan risk #14: Elementor Pro forms and GTM/GA4 continuity must survive every flip. |
| High blast radius per page | Eight pages, each individually significant. Losing one matters — unlike a single blog post among 193. |

Going last means the component library, the ACF field model, the per-page flip procedure and the rollback runbook have all been exercised on six lower-stakes content types before these pages are touched.

**Two stages, one gate (D-04).** Even when the services' turn comes, the structural change and the visual change are separated:

| | Stage 3a — structural port | ⏸ Gate | Stage 3b — visual redesign |
| :-- | :-- | :-- | :-- |
| **What changes** | Content moves from Elementor into ACF flexible content | — | The design system is applied |
| **What a visitor sees** | Nothing | — | The new UI |
| **What a crawler sees** | Nothing — same title, meta, headings, copy, internal links; markup reproduced | — | New markup, same content |
| **Exit condition** | Rankings, impressions, clicks and CWV stable across all 8 pages over the observation window | | Post-flip monitoring per page |

This is what makes the sequencing safe rather than merely late. If rankings move after 3a, the cause is unambiguous — it is the stack change, not a design change — and the rollback is a template reassignment. Bundling both stages would make any ranking movement impossible to attribute.

**Recommended observation window:** long enough for Google to re-crawl and re-evaluate all 8 pages, confirmed via Search Console coverage and the rankings baseline captured in the rebuild plan's T6. The exit criterion is stability across the full set, not elapsed time — one page still moving means the window stays open.

**Still open — the `/services/` URL.** It currently returns `200` and is `index, follow`. Once the CPT registration is deleted, that URL disappears. Two options:

1. **Build a services overview page** (a Page using the redesigned template) at `/services/` — gives the nav dropdown a real landing page and a hub for internal linking. *(+1 template)*
2. **301 `/services/`** to the About page or the homepage. Cheapest, but loses a natural hub.

**Recommendation:** option 1. Eight service pages with no parent hub is a missed internal-linking opportunity for an SEO agency.

**Also to confirm:** does the redesigned service page template also cover the **~100 SEO landing pages** (`agence-seo-{ville}`, `agence-seo-{secteur}`, `creation-site-web-{ville}`), or do those keep the current design until wave 2? They are structurally service pages, so the template will likely need a **location/industry variant**. This materially changes wave-1 volume and needs an explicit answer.

### 5.2 Service taxonomy: 8 navigation services vs 6 existing taxonomy terms

The brief specifies "6 services". The only existing 6-term taxonomy on the site is `review-categories`, which is scoped to *service families*. Mapped against the 8 navigation entries:

| Existing term (`review-categories`) | Navigation service(s) it covers |
| :-- | :-- |
| Website Development | Sites Web **+ Développement d'Application** *(two nav items merged)* |
| SEO & Organic Search | SEO – Référencement naturel |
| Online Advertising | Publicité en ligne |
| Social Media & Community Management | Gestion des réseaux sociaux |
| Graphic Design & Branding | Design Graphique |
| Professional Photo & Video | Photographie |
| — *(no term)* | **Copywriting** ← unmapped |

**Decision required:** confirm the canonical service taxonomy at **6 or 8 terms**. If 6: App Development stays folded into Website Development, and Copywriting needs a home. If 8: split App Development out and add Copywriting.

**Recommendation:** 8 terms — one per commercial offer, matching the navigation and the 8 service pages confirmed in D-01. A taxonomy that does not mirror what is sold creates permanent mapping friction across service pages, Reviews, Case Studies and Portfolio.

> **Still an early blocker despite D-03.** Sequencing the service pages last does *not* defer this decision. Reviews (step 3), Case Studies and Portfolio all classify against the same taxonomy, so the term list must be fixed before wave 1 gets far — even though the pages it describes are not touched until wave 3. Deciding it late means re-tagging content that has already been entered.

### 5.3 One taxonomy must serve four content types

Service pages, Case Studies, Portfolio and Reviews all need to be filterable by service family. Registering separate taxonomies per type would fragment the data and make cross-linking ("other SEO case studies", "reviews for this service") impossible.

**Recommendation:** register **one shared `service_family` taxonomy** attached to `page`, `case-studies`, `portfolio` and `reviews`. This is the single highest-leverage structural decision remaining in this document.

Note that D-01 makes this *more* important, not less: because service pages are now ordinary Pages, the taxonomy is the **only** structural link between a service and its case studies, portfolio items and reviews. Without it there is nothing tying them together.

### 5.4 Reviews is a new build, on two taxonomy axes

The existing `reviews` CPT holds two items with **empty titles** (slugs are the raw IDs `38791` and `38792`) rendering as unstyled cards showing only a date and a button. There is no usable content and no usable UI, so Reviews is planned as a from-scratch build; the old registration is a remnant and both items are discarded.

On classification: `review-categories` currently classifies by *service*. The brief asks for classification by *source* — Google, Sortlist, Clutch. These are different axes, and both are useful: source drives trust display, service drives placement on service pages.

**Recommendation:** two taxonomies on the new `reviews` CPT — `review_source` (Google, Sortlist, Clutch, …) **and** the shared `service_family` from §5.3.

**Archive question:** the brief says Reviews needs no archive, but `/reviews/` exists today and is `index, follow`.

**Recommendation:** build a single designed *Avis clients* hub at `/reviews/` — a consolidated review page is a genuine ranking and conversion asset. If it is not wanted, `noindex` the URL and redirect it. The one option to rule out is leaving the current unstyled archive live.

### 5.5 Job Offers has no archive — the Recrutement hub carries the listing

Job Offers is registered **without an archive**: the open-positions list renders on the redesigned `/recrutement/` hub instead of at a separate CPT archive URL. This keeps one recruitment landing page rather than splitting authority and traffic across two.

Consequences for the build:

- Job Offers needs a **single template** and a **department taxonomy-term template** only — no archive template.
- The Recrutement hub template must render three things: employer-brand content, the open-positions list, and the spontaneous application form.
- `/recrutement/` is currently `noindex`. Once real job offers are published, the hub and the single job pages should be **indexable** — job listings attract qualified branded search and support `JobPosting` structured data.
- The empty `departements` CPT is deleted and re-created as the **department taxonomy** on Job Offers.

### 5.6 Legacy remnants to clean up

Beyond the CPT registrations in §2, these live-site artefacts need a cleanup pass. None blocks design work, but each must be scheduled.

| Remnant | Evidence | Action |
| :-- | :-- | :-- |
| `departements` archive returns **404** | `/departements/` → HTTP 404 despite `has_archive: true` | Rewrite rules were never flushed. Registration is deleted outright; per sandbox runbook rule 4, re-save Permalinks after any CPT change. |
| `portfolio_category` has **0 terms** | Taxonomy registered, no terms | Dead taxonomy. Replace with `service_family` (§5.3) or drop it. |
| Two titleless review items | Slugs are raw IDs `38791`, `38792` | Discard. Title becomes a required field in the new ACF model. |
| Duplicated blog categories | 42 terms, most with a `-default` twin; `non-classe-default` holds **87** posts | Polylang/import artefact. Taxonomy cleanup + recategorisation pass before wave 2. |

### 5.7 CPTs are not consistent across the four market sub-sites

rhillane.com is a subdirectory multisite with four markets. Registered business CPTs per site:

| Sub-site | Market | Business CPTs registered |
| :-- | :-- | :-- |
| `/` | Morocco (FR) | portfolio · reviews · case-studies · services · departements |
| `/fr-fr/` | France | portfolio |
| `/en-us/` | United States | portfolio |
| `/en-ae/` | UAE | portfolio · reviews |

CPTs are currently registered **per site through a plugin UI**, so every market has drifted. The rebuild plan already fixes this: registering CPTs in **child-theme code** makes them network-consistent by construction. This inconsistency is a concrete argument for that approach — and it means the UI templates must be built market-agnostic from day one.

D-01 reduces the exposure here: because services are Pages, and Pages exist on every sub-site by definition, the service template works across all four markets with no registration drift to fix.

### 5.8 CPT archives are indexable but absent from the XML sitemap

`/case-studies/`, `/reviews/` and `/services/` all return `index, follow`, yet `sitemap_index.xml` contains only post, page, portfolio and local sitemaps. These types are crawlable but never submitted.

**Recommendation:** align both signals per type as part of each wave's go-live — either indexable **and** in the sitemap, or `noindex` and excluded. Not the current in-between state. *(The `/services/` half of this is resolved by §5.1 either way.)*

### 5.9 ✅ DECIDED — the people layer: two separate content types (D-02, D-06)

**Decisions:** every author profile gets its own single page (D-02), and **Team Members and Author Profiles are two separate content types** (D-06). They are not merged into one object.

**Starting point.** Seven authors, all rendering a bare chronological post list with no bio, no avatar, no credentials, no social links — and all `noindex`. Nothing exists for team members at all. There is no people UI to redesign, so this whole layer is from-scratch. For an SEO agency publishing 193 articles, author authority is directly on-brand and currently unbuilt.

**Why the two are distinct.** They answer different questions, carry different fields and serve different audiences:

| | **Team Members** | **Author Profiles** |
| :-- | :-- | :-- |
| Question it answers | Who works here | Who wrote this |
| Primary audience | Prospects and candidates | Readers and search engines |
| Core fields | Photo, role, department, short bio | Photo, credentials, areas of expertise, bio, social links, published work |
| Purpose | Brand, trust, recruitment | E-E-A-T, topical authority |
| Membership | Staff — whether they write or not | Anyone with a byline — staff or external |
| Linked to a WP user | Not necessarily | Yes, via an ACF `user` field |

Merging them would force one field group to serve both jobs, and would make it impossible to publish an article by an external contributor without listing them as staff — or to list a non-writing team member without leaving an empty author profile behind.

**The problem D-02 solves.** Core WordPress has no "author profile" object. `/author/{slug}/` is an *archive* — a list of posts — with nowhere to store a photo, credentials or social links. A profile that is a real post is the only way to get a proper page per person.

**Architecture — four templates:**

| Surface | Template | URL | What it renders |
| :-- | :-- | :-- | :-- |
| **Team — archive** | `archive-team.php` | `/team/` | The team grid, optionally grouped by department. |
| **Team — single** | `single-team.php` | `/team/{name}/` | Role, department, photo, short bio. Links to the person's author profile if they write. |
| **Author — archive** | `archive-author-profile.php` | `/authors/` | Contributors index — a discoverable hub covering every byline on the site. |
| **Author — single** | `single-author-profile.php` | `/authors/{name}/` | The full E-E-A-T profile: photo, credentials, areas of expertise, bio, social links, and a grid of that person's published articles. |

**The link mechanism.** An ACF `user` field on each **author profile** post stores the WordPress user ID. That single field drives the article grid on the profile and lets every byline resolve to its profile URL. Team member posts carry an optional relationship field pointing at an author profile, for staff who also write.

**Retiring the core author archive.** `/author/{slug}/` is generated automatically by WordPress and currently renders a bare post list under `noindex`. It duplicates, badly, what the author profile single will do properly.

**Recommendation:** **301 `/author/{slug}/` → `/authors/{name}/`.** No template needed, no duplicate URL for the same person, and every article byline points at one canonical profile. Nothing is indexed at those URLs today, so the redirect costs nothing.

**People who are both.** A staff writer legitimately appears in both sets — once as a team member, once as an author. Cross-link the two: the team single links to the author profile, the author profile links back. This is not duplication; the two pages answer different questions and target different queries.

**Indexing.** The author profile single is the canonical indexable page for a person's expertise, and every byline should point at it. The team single is indexable as part of the About/team story. Both are thin if under-populated — **do not publish either until the content is real**, and do not lift any `noindex` before then.

**Slugs.** The existing CPTs use English slugs (`case-studies`, `portfolio`, `reviews`), so `team` and `authors` fit the convention better than `equipe` and `auteurs`. To confirm.

### 5.10 "Pages: archive yes, taxonomy yes" is not natively possible

WordPress Pages have neither an archive nor a taxonomy. The brief's request is therefore a **custom build**, not a configuration toggle.

The underlying need is real and D-01 sharpens it: 154 pages, of which 8 are now confirmed service pages and roughly 100 more are an SEO landing-page matrix (cities, industries, platforms — `agence-seo-{ville}`, `agence-seo-{secteur}`, `creation-site-web-{ville}`). That matrix has no systematic internal-linking structure today, and no structural relationship to the service pages it supports.

**Recommendation:** register a `page_cluster` taxonomy on `page` (city / industry / platform) alongside `service_family` from §5.3, and build a hub template that renders the matrix. Real SEO upside, but confirm it as **wave 2** scope — it is not a template port, it is new architecture.

### 5.11 ✅ DECIDED — page triage by click volume: the proving ground (D-05)

**Decision:** pages with zero or minimal clicks may have their UI changed early. They are the proving ground for the new design system, and may be converted to a CPT or served by a section library on the `page` post type.

**Why this matters.** The page layer is not uniform. 154 pages, of which 8 are the commercial core and roughly 100 form an SEO landing matrix built for long-tail capture. Those two groups carry completely different risk. Treating all 154 the same way — either all frozen or all redesigned — either wastes a safe testing opportunity or gambles the money pages. Triage separates them.

**Tiers, by Search Console clicks:**

| Tier | Pages | Treatment | Risk |
| :-- | :-- | :-- | :-- |
| **A — zero / minimal clicks** | To be determined from the export | **UI can change freely.** First target for the new page design system. Convert to a CPT, or serve via the page section library. | Negligible — nothing to lose |
| **B — moderate traffic** | To be determined | Port as-is in wave 2; redesign later, once tier A has validated the design system. | Low |
| **C — commercial core** | The 8 service pages, plus any high-traffic landing pages | Two-stage treatment with the observation gate (D-04). | High — see §5.1 |

**Hard prerequisite.** None of this triage can be executed without a **Search Console clicks-per-page export** covering the last 12 months, for all four market properties. That export is the input that assigns every page to a tier, and it should be pulled before wave 2 planning begins. It is also worth cross-referencing against the impressions column: a page with zero clicks but meaningful impressions is a tier B candidate, not tier A — it is ranking, just not yet converting.

**CPT vs section library — which of the two options.** The decision is not either/or across the board; it depends on whether a page group shares a repeatable structure:

| Option | When it fits | Caution |
| :-- | :-- | :-- |
| **Section library on `page`** *(default)* | Any page whose layout is bespoke. Keeps the URL, keeps the post type, requires no migration, and reuses the flexible-content blocks already built for the CPTs in wave 1. | None material — this is the low-friction path. |
| **Convert to a CPT** | Only for a group with genuinely repeatable structure and a shared taxonomy need. The city / industry SEO matrix is the one real candidate — it is templated by nature and would benefit from an archive and a `page_cluster` taxonomy (§5.10). | Changes the post type of indexed URLs. Only viable if the exact slugs are preserved via rewrite rules. Do not attempt on any tier B or C page. |

**Recommendation:** default to the section library. Reserve CPT conversion for the city/industry matrix, treat it as a separate scoped decision after tier A has proven the design system, and require URL preservation as a non-negotiable condition.

**The strategic value.** Tier A does more than clear low-value pages off the backlog — it de-risks everything downstream. By the time the service pages reach stage 3b, the new page design system will have been exercised on real production URLs, on the real multisite, under the real cache and CDN, with real crawler behaviour observed. That is a far stronger position than flipping the money pages onto a design system whose only proving ground was staging.

---

## 6. Recommended execution order

Sequenced by **ascending SEO risk** (D-03): prove the stack on content with nothing to lose, then move outward, and touch the money pages only once everything else is stable.

| Step | Wave | Content type | Why this position |
| :-- | :-- | :-- | :-- |
| 1 | 1 | **Case Studies** | Already the approved pilot in the rebuild plan (T4/T5). Lowest risk of anything on the site: 1 item, no taxonomy, nothing indexed to protect. Proves the ACF flexible-content model end to end. |
| 2 | 1 | **Portfolio** | Same card-grid and single-item pattern as Case Studies; 6 real items already exist. Highest design reuse for the least new work. |
| 3 | 1 | **Reviews** | First consumer of the shared `service_family` taxonomy, plus the new `review_source`. Feeds trust blocks into every other template. Blocked until §5.2 and §5.3 are decided. |
| 4 | 1 | **Job Offers + Recrutement hub** | Net-new, self-contained, zero SEO risk. Can run in parallel with steps 1–3 by a second person. |
| 5 | 1 | **About us + the people layer** | Brand and E-E-A-T layer — two separate CPTs, four templates (D-02, D-06) — once the component library from steps 1–4 is stable. Both singles reuse the card grid built in steps 1–2. |
| 6 | 2 | **Blog + Standard pages — as-is port** | Template-level port of the existing UI — no per-item assembly, no design change. All 154 pages move onto the new stack unchanged. |
| 7 | 2 | **Low-click pages — new UI** *(D-05)* | The proving ground. Tier A pages get the new design system on real production URLs, real multisite, real cache and CDN, with real crawler behaviour observed — the strongest possible rehearsal for step 9. Requires the Search Console triage export first (§5.11). **Must complete before step 8 opens.** |
| 8 | **3a** | **Service pages — structural port** *(D-04)* | The 8 service pages move to ACF flexible content with the **current UI reproduced exactly**. Nothing a visitor or crawler sees changes. |
| ⏸ | — | **Observation window** | Monitor rankings, impressions, clicks and CWV across all 8 pages. Exit criterion is stability across the full set, not elapsed time. **No further change until clear.** |
| 9 | **3b** | **Service pages — visual redesign** *(D-01, D-03, D-04)* | **Last, deliberately.** The riskiest surface in the programme — see the risk table in §5.1. By this point the component library, the ACF model, the flip procedure and the rollback runbook have been exercised on six lower-stakes content types, the design system has been proven on live tier A pages, and the stack change on these very pages has already been isolated and cleared. |

---

## 7. Open questions for sign-off

1. Service taxonomy: **6 terms or 8**? (§5.2)
2. Approve a **single shared `service_family` taxonomy** across `page`, Case Studies, Portfolio and Reviews? (§5.3)
3. `/services/` — build a services overview page, or 301 it? (§5.1)
4. Does the redesigned service page template also cover the **~100 SEO location/industry landing pages**, or do they wait for wave 2? (§5.1)
5. Reviews: build a designed `/reviews/` hub, or `noindex` + redirect it? (§5.4)
6. Job Offers taxonomies: department only, or department + contract type + location? (§5.5)
7. Should the Recrutement hub and single job pages become **indexable** once real offers are published? (§5.5)
8. Confirm the slugs for the two people CPTs — `team` and `authors`, or French equivalents? (§5.9)
9. Confirm **301'ing `/author/{slug}/` → `/authors/{name}/`**, so each person has one canonical profile URL and every byline points at it? (§5.9)
10. Is the `page_cluster` taxonomy and SEO-matrix hub in scope for wave 2? (§5.10)
11. Slug reuse confirmed for the legacy `case-studies` and `reviews` registrations? (§2)
12. **Who pulls the Search Console clicks-per-page export**, for all four market properties, and by when? Wave 2 planning is blocked on it. (§5.11)
13. What click threshold separates **tier A from tier B** — strictly zero clicks, or below a set floor over 12 months? (§5.11)
14. Confirm the **exit criterion for the observation window** in §5.1 — which metrics, whose sign-off, and what constitutes "stable"? (§5.1)

---

## 8. Verified data appendix

**Content volumes (23 July 2026)**

| Type | Published / indexed | Usable for the rebuild |
| :-- | --: | :-- |
| Blog posts | 193 | ✅ All — ported to wave 2 |
| Pages — service pages | 8 | ✅ Kept in place, redesigned (D-01) |
| Pages — standard | 146 | ✅ All — ported to wave 2 |
| Portfolio | 6 | ✅ Migrate |
| Reviews | 2 | ❌ Discard — no titles, no content |
| Case Studies | 1 | ✅ Migrate |
| Services (CPT) | 0 | — registration deleted (D-01) |
| Departments | 0 | — nothing exists |
| Authors | 7 | ✅ Profiles to be written |
| Blog categories | 42 | ⚠️ Cleanup required — duplicated terms |

**Archive resolution & robots**

| URL | HTTP | Robots | Renders |
| :-- | :-- | :-- | :-- |
| `/case-studies/` | 200 | index, follow | 1 item |
| `/portfolio/` | 200 | index, follow | Hero + 6-card grid + FAQ + trust badges; no filter, no pagination |
| `/reviews/` | 200 | index, follow | 2 unstyled cards — date + "En Savoir Plus" only |
| `/services/` | 200 | index, follow | "No posts found" — resolved by D-01 / §5.1 |
| `/departements/` | **404** | — | — |
| `/author/seo-manager/` | 200 | **noindex**, follow | Bare post list, no author data |
| `/recrutement/` | 200 | **noindex**, follow | Spontaneous application form; no job listings |

**Sitemap coverage** — `post-sitemap1` (100) · `post-sitemap2` (93) · `page-sitemap1` (99) · `page-sitemap2` (55) · `portfolio-sitemap` (7) · `local-sitemap`. No sitemap for case-studies, reviews or services.

---

*Companion documents: [[plan-draft-inplace-child-theme]] · [[sandbox-findings-uicore-child-theme]]*
