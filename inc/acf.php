<?php
/**
 * ACF wiring: Local JSON paths + null-safe wrappers.
 * Field groups live in /acf-json — git is the source of truth (plan §1.2).
 */
defined('ABSPATH') || exit;

define('RMD_ACF_ACTIVE', function_exists('get_field'));

// WRITE field-group JSON here when saving in the admin…
add_filter('acf/settings/save_json', fn() => RMD_DIR . '/acf-json');

// …and LOAD from here at runtime.
add_filter('acf/settings/load_json', function ($paths) {
	$paths[] = RMD_DIR . '/acf-json';
	return $paths;
});

/**
 * Null-safe ACF wrappers — the site must never fatal if ACF is off.
 * Every template goes through these, never through get_field() directly.
 */
function rmd_get_field($name, $post_id = false, $format = true) {
	return RMD_ACF_ACTIVE ? get_field($name, $post_id, $format) : null;
}
function rmd_get_sub_field($name, $format = true) {
	return RMD_ACF_ACTIVE ? get_sub_field($name, $format) : null;
}
function rmd_have_rows($name, $post_id = false) {
	return RMD_ACF_ACTIVE ? have_rows($name, $post_id) : false;
}
function rmd_the_row() {
	return RMD_ACF_ACTIVE ? the_row() : false;
}
function rmd_get_row_layout() {
	return RMD_ACF_ACTIVE ? get_row_layout() : '';
}

// Loud admin notice when ACF Pro is missing (P1 blocker in the rebuild plan).
add_action('admin_notices', function () {
	if (!RMD_ACF_ACTIVE && current_user_can('activate_plugins')) {
		echo '<div class="notice notice-error"><p><strong>Vault Child:</strong> ACF Pro is not active — sections will not render.</p></div>';
	}
});
