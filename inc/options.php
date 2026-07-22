<?php
/**
 * Site Settings options page — per-country phone, address, socials, footer…
 * The field group for it lives in /acf-json like everything else.
 * On the multisite, options are stored per-site (each subsite has its own values).
 */
defined('ABSPATH') || exit;

add_action('acf/init', function () {
	if (!function_exists('acf_add_options_page')) {
		return;
	}
	// Admin-language aware (rmd_is_fr pattern) — the hints/instructions across
	// the editor reference this page by name, so it must match in both languages.
	$rmd_title = (function_exists('rmd_is_fr') && rmd_is_fr()) ? 'Réglages du site' : 'Site Settings';
	acf_add_options_page(array(
		'page_title' => $rmd_title,
		'menu_title' => $rmd_title,
		'menu_slug'  => 'rmd-site-settings',
		'capability' => 'manage_options',
		'redirect'   => false,
	));
});
