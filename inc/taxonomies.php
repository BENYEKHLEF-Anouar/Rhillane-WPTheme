<?php
/**
 * Taxonomies. W0: case_study_cat.
 */
defined('ABSPATH') || exit;

add_action('init', 'rmd_register_taxonomies');
function rmd_register_taxonomies() {

	register_taxonomy('case_study_cat', array('case_study'), array(
		// French source strings (translatable to EN/AR later via .mo).
		'labels' => array(
			'name'              => __('Catégories d’études de cas', 'vault-child'),
			'singular_name'     => __('Catégorie d’étude de cas', 'vault-child'),
			'menu_name'         => __('Catégories', 'vault-child'),
			'all_items'         => __('Toutes les catégories', 'vault-child'),
			'edit_item'         => __('Modifier la catégorie', 'vault-child'),
			'view_item'         => __('Voir la catégorie', 'vault-child'),
			'update_item'       => __('Mettre à jour la catégorie', 'vault-child'),
			'add_new_item'      => __('Ajouter une catégorie', 'vault-child'),
			'new_item_name'     => __('Nom de la nouvelle catégorie', 'vault-child'),
			'search_items'      => __('Rechercher une catégorie', 'vault-child'),
			'not_found'         => __('Aucune catégorie trouvée', 'vault-child'),
			'parent_item'       => __('Catégorie parente', 'vault-child'),
			'parent_item_colon' => __('Catégorie parente :', 'vault-child'),
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array('slug' => 'case-study-category', 'with_front' => false),
	));
}
