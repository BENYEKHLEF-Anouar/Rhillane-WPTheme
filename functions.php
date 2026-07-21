<?php

/**
 * Vault Child — bootstrap.
 * This file only loads modules. All real code lives in /inc, one job per file.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

define('RMD_DIR', get_stylesheet_directory());
define('RMD_URI', get_stylesheet_directory_uri());
define('RMD_VERSION', '1.0.0');

$rmd_modules = array(
	'inc/setup.php',      // theme supports, textdomain, image sizes
	'inc/security.php',   // small hardening tweaks
	'inc/acf.php',        // ACF guards + Local JSON paths
	'inc/cpt.php',        // custom post types (W0: case_study)
	'inc/taxonomies.php', // taxonomies (case_study_cat)
	'inc/helpers.php',    // rmd_render_sections(), rmd_image()
	'inc/enqueue.php',    // CSS/JS loading (filemtime versioning)
	'inc/options.php',    // Site Settings options page
	'inc/seo.php',        // hreflang only — Rank Math owns the rest
	'inc/admin-ux.php',   // editor helpers (ported AMD preview/gallery)
);

foreach ($rmd_modules as $rmd_module) {
	require_once RMD_DIR . '/' . $rmd_module;
}
