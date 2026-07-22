<?php
/**
 * Custom post types — registered in PHP (not the ACF UI) so they exist
 * even when ACF is off. W0 ships case_study; W2 adds service.
 */
defined('ABSPATH') || exit;

add_action('init', 'rmd_register_post_types');
function rmd_register_post_types() {

	register_post_type('case_study', array(
		'labels' => array(
			'name'          => __('Case Studies', 'vault-child'),
			'singular_name' => __('Case Study', 'vault-child'),
			'add_new_item'  => __('Add New Case Study', 'vault-child'),
			'edit_item'     => __('Edit Case Study', 'vault-child'),
		),
		'public'        => true,
		'has_archive'   => true,
		'menu_icon'     => 'dashicons-analytics',
		'menu_position' => 21,
		'show_in_rest'  => true,
		// Confirmed 21/07: French slug. Flush permalinks after deploy.
		'rewrite'       => array('slug' => 'etudes-de-cas', 'with_front' => false),
		// No 'editor': all content comes from the sections field.
		'supports'      => array('title', 'excerpt', 'thumbnail', 'page-attributes'),
	));

	// W2 — register the `service` CPT here (six services confirmed).
	// Not earlier: every public CPT adds admin UI + rewrite rules network-wide.
}

/**
 * One-time rewrite flush per version. The admin "Enregistrer les permaliens"
 * flush can be defeated by object caching (seen on Hostinger/LiteSpeed:
 * /etudes-de-cas/* 404s while ?case_study=slug works). Bump the version
 * string whenever a CPT/taxonomy slug changes — the next request after
 * deploy rebuilds the rules and purges LiteSpeed, no admin action needed.
 */
add_action('init', 'rmd_maybe_flush_rewrites', 99);
function rmd_maybe_flush_rewrites() {
	$version = '2'; // 2 = case_study slug etudes-de-cas
	if (get_option('rmd_rewrite_version') !== $version) {
		flush_rewrite_rules();
		update_option('rmd_rewrite_version', $version);
		// Drop any cached 404s for the new URLs (no-op if LiteSpeed absent).
		do_action('litespeed_purge_all');
	}
}
