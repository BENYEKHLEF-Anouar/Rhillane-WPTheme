<?php
/**
 * Taxonomies. W0: case_study_cat.
 */
defined('ABSPATH') || exit;

add_action('init', 'rmd_register_taxonomies');
function rmd_register_taxonomies() {

	register_taxonomy('case_study_cat', array('case_study'), array(
		'labels' => array(
			'name'          => __('Case Study Categories', 'vault-child'),
			'singular_name' => __('Case Study Category', 'vault-child'),
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array('slug' => 'case-study-category', 'with_front' => false),
	));
}
