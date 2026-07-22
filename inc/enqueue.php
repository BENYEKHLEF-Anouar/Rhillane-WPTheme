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

	// Front-end JS — vanilla, deferred.
	if (file_exists(RMD_DIR . '/assets/js/main.js')) {
		wp_enqueue_script('rmd-main', RMD_URI . '/assets/js/main.js', array(), rmd_asset_ver('assets/js/main.js'), array(
			'in_footer' => true,
			'strategy'  => 'defer',
		));
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
