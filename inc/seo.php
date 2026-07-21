<?php
/**
 * SEO — hreflang ONLY.
 *
 * Rank Math owns titles, meta, schema, canonicals and the XML sitemap
 * (locked decision, 20/07 meeting). The theme must NOT emit its own —
 * duplicating them is exactly the parity risk the rebuild plan avoids.
 *
 * The one gap Rank Math does not cover out of the box: hreflang pairs
 * between the language subsites of the multisite network. That mapping
 * lives here, and nothing else does.
 */
defined('ABSPATH') || exit;

// TODO (W3+): output <link rel="alternate" hreflang="…"> pairs between the
// subsites once the language→site mapping is confirmed. Until then this
// file intentionally does nothing.
