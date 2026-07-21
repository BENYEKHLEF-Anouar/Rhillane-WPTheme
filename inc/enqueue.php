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

	// Compiled Tailwind + our CSS (committed to git — no server build step).
	if (file_exists(RMD_DIR . '/assets/css/main.css')) {
		wp_enqueue_style('rmd-main', RMD_URI . '/assets/css/main.css', array('vault-child'), rmd_asset_ver('assets/css/main.css'));
	}

	// Front-end JS — vanilla, deferred.
	if (file_exists(RMD_DIR . '/assets/js/main.js')) {
		wp_enqueue_script('rmd-main', RMD_URI . '/assets/js/main.js', array(), rmd_asset_ver('assets/js/main.js'), array(
			'in_footer' => true,
			'strategy'  => 'defer',
		));
	}
}
