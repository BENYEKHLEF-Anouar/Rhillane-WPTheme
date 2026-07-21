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
		// ⚠ Confirm the public slug before the first publish (French site →
		// maybe "realisations" / "etudes-de-cas"). Changing it later = 301s.
		'rewrite'       => array('slug' => 'case-study', 'with_front' => false),
		// No 'editor': all content comes from the sections field.
		'supports'      => array('title', 'excerpt', 'thumbnail', 'page-attributes'),
	));

	// W2 — register the `service` CPT here (six services confirmed).
	// Not earlier: every public CPT adds admin UI + rewrite rules network-wide.
}
