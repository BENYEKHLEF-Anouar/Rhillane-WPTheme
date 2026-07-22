<?php
/**
 * Custom post types — registered in PHP (not the ACF UI) so they exist
 * even when ACF is off. W0 ships case_study; W2 adds service.
 */
defined('ABSPATH') || exit;

add_action('init', 'rmd_register_post_types');
function rmd_register_post_types() {

	// Labels adapt to the admin user's language (get_user_locale via rmd_is_fr):
	// French admin → French, English admin → English. No .mo files needed.
	$labels = rmd_is_fr() ? array(
		'name'               => 'Études de cas',
		'singular_name'      => 'Étude de cas',
		'menu_name'          => 'Études de cas',
		'name_admin_bar'     => 'Étude de cas',
		'all_items'          => 'Toutes les études de cas',
		'add_new'            => 'Ajouter',
		'add_new_item'       => 'Ajouter une étude de cas',
		'new_item'           => 'Nouvelle étude de cas',
		'edit_item'          => 'Modifier l’étude de cas',
		'view_item'          => 'Voir l’étude de cas',
		'view_items'         => 'Voir les études de cas',
		'search_items'       => 'Rechercher une étude de cas',
		'not_found'          => 'Aucune étude de cas trouvée',
		'not_found_in_trash' => 'Aucune étude de cas dans la corbeille',
		'archives'           => 'Archives des études de cas',
		'featured_image'     => 'Image mise en avant',
		'set_featured_image' => 'Définir l’image mise en avant',
		'item_published'     => 'Étude de cas publiée.',
		'item_updated'       => 'Étude de cas mise à jour.',
	) : array(
		'name'               => 'Case Studies',
		'singular_name'      => 'Case Study',
		'menu_name'          => 'Case Studies',
		'name_admin_bar'     => 'Case Study',
		'all_items'          => 'All Case Studies',
		'add_new'            => 'Add New',
		'add_new_item'       => 'Add New Case Study',
		'new_item'           => 'New Case Study',
		'edit_item'          => 'Edit Case Study',
		'view_item'          => 'View Case Study',
		'view_items'         => 'View Case Studies',
		'search_items'       => 'Search Case Studies',
		'not_found'          => 'No case studies found',
		'not_found_in_trash' => 'No case studies found in Trash',
		'archives'           => 'Case Study Archives',
		'featured_image'     => 'Featured image',
		'set_featured_image' => 'Set featured image',
		'item_published'     => 'Case study published.',
		'item_updated'       => 'Case study updated.',
	);

	register_post_type('case_study', array(
		'labels'        => $labels,
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
