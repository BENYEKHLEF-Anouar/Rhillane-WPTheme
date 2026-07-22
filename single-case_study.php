<?php
/**
 * Single case study — the whole template: header → sections → footer.
 * Uses the RMD chrome (rmd_render_header/footer) — its own document shell with
 * wp_head()/wp_footer(), NOT parent Vault. Scoped to this CPT template so it
 * doesn't touch other pages (a global header.php would — that's W3).
 */
defined('ABSPATH') || exit;

rmd_render_header();
?>
<main class="rmd-case">
<?php
while (have_posts()) {
	the_post();
	rmd_render_sections();
}
?>
</main>
<?php
rmd_render_footer();
