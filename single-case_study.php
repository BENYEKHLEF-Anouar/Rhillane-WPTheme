<?php
/**
 * Single case study — the whole template: header → sections → footer.
 * Header/footer are sitewide (parent Vault chrome until W3).
 */
defined('ABSPATH') || exit;

get_header();
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
get_footer();
