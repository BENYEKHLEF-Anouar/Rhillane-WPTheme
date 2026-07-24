<?php
/**
 * Asset loading. filemtime() versioning: the browser cache busts itself
 * on every deploy — no manual version bumps, ever.
 */
defined('ABSPATH') || exit;

/** File-modification-time version for cache busting (falls back to theme version). */
function rmd_asset_ver($relative_path) {
	$file = RMD_DIR . '/' . ltrim($relative_path, '/');
	return file_exists($file) ? (string) filemtime($file) : RMD_VERSION;
}

add_action('wp_enqueue_scripts', 'rmd_enqueue_assets');
function rmd_enqueue_assets() {
	// Parent style — only when UiCore core is not already handling it.
	if (!class_exists('\UiCore\Core')) {
		wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
	}
	wp_enqueue_style('vault-child', RMD_URI . '/style.css', array(), rmd_asset_ver('style.css'));

	// Poppins — the case-study design font. TODO: self-host woff2 in
	// assets/fonts/ later (GDPR-friendlier for the FR audience) and drop this.
	wp_enqueue_style('rmd-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', array(), null);

	// Compiled Tailwind + our CSS (committed to git — no server build step).
	if (file_exists(RMD_DIR . '/assets/css/main.css')) {
		wp_enqueue_style('rmd-main', RMD_URI . '/assets/css/main.css', array('vault-child'), rmd_asset_ver('assets/css/main.css'));
	}

	// Image loading effects (§12) — hand-written, loaded after main.css so it
	// needs no Tailwind rebuild. Gated behind the `rmd_image_effects` filter
	// (default off): nothing calls rmd_render_image() yet, so we don't ship the
	// effect CSS + head flag dead on every page. Flip the filter on when a section
	// adopts rmd_render_image(): add_filter('rmd_image_effects', '__return_true').
	if (apply_filters('rmd_image_effects', false) && file_exists(RMD_DIR . '/assets/css/rmd-media.css')) {
		wp_enqueue_style('rmd-media', RMD_URI . '/assets/css/rmd-media.css', array('vault-child'), rmd_asset_ver('assets/css/rmd-media.css'));
	}

	// One stylesheet per CPT — see rmd_enqueue_cpt_style().
	rmd_enqueue_cpt_style();

	// Front-end JS — vanilla, deferred.
	if (file_exists(RMD_DIR . '/assets/js/main.js')) {
		wp_enqueue_script('rmd-main', RMD_URI . '/assets/js/main.js', array(), rmd_asset_ver('assets/js/main.js'), array(
			'in_footer' => true,
			'strategy'  => 'defer',
		));
	}
}

/**
 * The post type whose stylesheet this request needs, or ''.
 *
 * Taxonomy archives resolve to the post type their taxonomy is attached to. That
 * branch pays nothing today — there is no taxonomy-case_study_cat.php, so a term
 * page falls back to parent Vault's archive and none of our (rmd_-prefixed,
 * collision-free) selectors match. It's there so adding that template later needs
 * no enqueue change.
 */
function rmd_current_cpt_style_slug() {
	if (is_singular()) {
		return (string) get_post_type();
	}
	if (is_post_type_archive()) {
		$queried = get_queried_object();
		return ($queried && isset($queried->name)) ? (string) $queried->name : '';
	}
	if (is_tax()) {
		$term = get_queried_object();
		if ($term && isset($term->taxonomy)) {
			$tax = get_taxonomy($term->taxonomy);
			if ($tax && !empty($tax->object_type)) {
				// Indexed, not reset(): no by-reference call on a live WP_Taxonomy
				// property, and no dependence on its internal array pointer.
				$types = array_values((array) $tax->object_type);
				return isset($types[0]) ? (string) $types[0] : '';
			}
		}
	}
	return '';
}

/**
 * Load assets/css/cpt/<post_type>.css when we're on that post type's pages.
 *
 * THE CONVENTION: one file per CPT, named exactly as the post type is registered
 * (snake_case, no transform — the same 1:1 rule as section layout keys). Drop
 * assets/css/cpt/service.css in and it loads on `service` singles + archive.
 * No code to add here, no Tailwind rebuild, and no chance of touching another
 * CPT's styles.
 *
 * Depends on 'rmd-main': the per-CPT files re-open Tailwind's `components`
 * layer, which only sits in the right place if main.css was parsed first.
 * basename() guards the path even though the slug comes from WP, not the URL.
 */
function rmd_enqueue_cpt_style() {
	$slug     = rmd_current_cpt_style_slug();
	$relative = rmd_cpt_style_rel($slug);
	if ('' === $relative) {
		return;
	}

	// Depend on rmd-main so main.css is parsed FIRST: it declares the cascade-layer
	// order, and if `components` were created by this file instead, Tailwind's own
	// `base` preflight would end up outranking these component rules.
	// But WP silently refuses to print a style whose dependency isn't registered —
	// so on a checkout where main.css was never built, an unconditional dep would
	// make this sheet vanish without a trace. Ask, don't assume.
	$deps = wp_style_is('rmd-main', 'registered') ? array('rmd-main') : array();

	wp_enqueue_style('rmd-cpt-' . $slug, RMD_URI . '/' . $relative, $deps, rmd_asset_ver($relative));
}

/**
 * Theme-relative path of a post type's stylesheet, or '' when it has none (a CPT
 * with no design yet, or a core type that keeps using the shared sheet).
 * basename() guards the path even though the slug comes from WP, not the URL.
 */
function rmd_cpt_style_rel($post_type) {
	$post_type = (string) $post_type;
	if ('' === $post_type || 'post' === $post_type || 'page' === $post_type) {
		return '';
	}
	$relative = 'assets/css/cpt/' . basename($post_type) . '.css';
	return file_exists(RMD_DIR . '/' . $relative) ? $relative : '';
}

/**
 * Same stylesheet as a ready-to-print URL. For contexts that build their own
 * <head> instead of running wp_enqueue_scripts — the admin section preview
 * (inc/admin-ux.php) above all, which would otherwise render unstyled the moment
 * main.css stops carrying this CPT's rules.
 */
function rmd_cpt_style_url($post_type) {
	$relative = rmd_cpt_style_rel($post_type);
	return '' === $relative ? '' : RMD_URI . '/' . $relative . '?ver=' . rmd_asset_ver($relative);
}

/**
 * Case-study pages render our own document shell — no UiCore components at all.
 * Parent UiCore's global sheet (uploads/uicore-global.css) styles BARE elements:
 * h1–h6 get the Elementor site's DARK design values (--uicore-headline-color:#FFF
 * → white headings on our white page), plus p margins and link colors. Being
 * unlayered, it beats every @layer rule in our compiled Tailwind no matter the
 * specificity. Nothing on these pages needs it — drop it. (Checked the rest of
 * the stack: bdt-uikit, Elementor, EA, jet-engine are all class-scoped, harmless.)
 */
add_action('wp_enqueue_scripts', 'rmd_drop_uicore_global_on_case_pages', 100);
function rmd_drop_uicore_global_on_case_pages() {
	if (is_singular('case_study') || is_post_type_archive('case_study')) {
		wp_dequeue_style('uicore_global');
	}
}

/**
 * Tiny render-blocking flag in <head> (priority 0, before anything paints): marks
 * that JS is available so the image-effect CSS (rmd-media.css) only hides images
 * it can later reveal. With JS off the class is absent and images render fully —
 * progressive enhancement, no flash of hidden content.
 */
add_action('wp_head', function () {
	if (!apply_filters('rmd_image_effects', false)) {
		return; // only needed when the §12 effect CSS is enqueued
	}
	echo "<script>document.documentElement.className+=' rmd-js';</script>\n";
}, 0);
