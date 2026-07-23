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
	// Preview demo mode (§ inc/admin-ux.php): when the section preview renders a
	// layout with no ACF row (the "Add Row" demo), it seeds $GLOBALS['rmd_demo']
	// with example sub-field values so the template renders a filled example.
	// Only ever set inside the admin preview endpoint — the front end never sets
	// it, so this branch is inert on every real page render.
	if (isset($GLOBALS['rmd_demo']) && is_array($GLOBALS['rmd_demo']) && array_key_exists($name, $GLOBALS['rmd_demo'])) {
		return $GLOBALS['rmd_demo'][$name];
	}

	$value = RMD_ACF_ACTIVE ? get_sub_field($name, $format) : null;

	// Preview EDIT mode (§ inc/admin-visual-edit.php): the saved-row preview with
	// edit=1 marks whitelisted values so they become editable in the iframe. Like
	// rmd_demo, this global is only ever set inside the admin preview endpoint.
	if (isset($GLOBALS['rmd_edit_map']) && is_array($GLOBALS['rmd_edit_map']) && function_exists('rmd_edit_mark_value')) {
		return rmd_edit_mark_value($value, (string) $name, $GLOBALS['rmd_edit_map']);
	}
	return $value;
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

/**
 * Never-set-vs-cleared helper (spec §7.3). Returns the saved value, or $default
 * ONLY when the field is unset (null/false). An explicitly emptied field ("", [],
 * 0) passes through unchanged — so a saved-but-cleared repeater stays hidden while
 * a never-touched one can fall back to demo/default content.
 */
function rmd_field_default($name, $default = '', $is_sub_field = true) {
	$value = $is_sub_field ? rmd_get_sub_field($name) : rmd_get_field($name);
	if (null === $value || false === $value) {
		return $default;
	}
	return $value;
}

// Loud admin notice when ACF Pro is missing (P1 blocker in the rebuild plan).
add_action('admin_notices', function () {
	if (!RMD_ACF_ACTIVE && current_user_can('activate_plugins')) {
		echo '<div class="notice notice-error"><p><strong>Vault Child:</strong> ACF Pro is not active — sections will not render.</p></div>';
	}
});
