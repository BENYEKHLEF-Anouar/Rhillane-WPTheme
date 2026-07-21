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
	acf_add_options_page(array(
		'page_title' => __('Site Settings', 'vault-child'),
		'menu_title' => __('Site Settings', 'vault-child'),
		'menu_slug'  => 'rmd-site-settings',
		'capability' => 'manage_options',
		'redirect'   => false,
	));
});
