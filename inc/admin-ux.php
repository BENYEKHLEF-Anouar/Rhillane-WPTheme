<?php
/**
 * Editor helpers — ported from the AMD project.
 * (Decision 21/07: no ACF Extended. ACF Pro is the only plugin; the editor
 * UX is our own code, carried over from AMD.)
 *
 * To port here in W0.5:
 *   1. Live section preview (AMD spec §8) — admin-ajax endpoint + scaled
 *      iframe modal, demo mode + saved-row mode.
 *      ⚠ Adapt the layout→file transform: RMD layout keys map 1:1 to
 *        template-parts/layouts/<key>.php — no "_section" suffix stripping.
 *   2. In-field gallery viewer (AMD spec §9) — thumbnail strip + lightbox.
 *   3. Duplicate-section warning (AMD spec §7.2).
 *
 * Rules: enqueue admin assets ONLY on post.php / post-new.php and only when
 * ACF is active; all decorators idempotent; endpoint logged-in + nonce +
 * capability + layout allowlist (never build a path from raw input).
 */
defined('ABSPATH') || exit;
