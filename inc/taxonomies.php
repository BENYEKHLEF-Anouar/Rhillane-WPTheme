<?php
/**
 * Taxonomies. W0: case_study_cat.
 */
defined('ABSPATH') || exit;

add_action('init', 'rmd_register_taxonomies');
function rmd_register_taxonomies() {

	// Labels adapt to the admin user's language (rmd_is_fr), like the CPT.
	$labels = rmd_is_fr() ? array(
		'name'              => 'Catégories d’études de cas',
		'singular_name'     => 'Catégorie d’étude de cas',
		'menu_name'         => 'Catégories',
		'all_items'         => 'Toutes les catégories',
		'edit_item'         => 'Modifier la catégorie',
		'view_item'         => 'Voir la catégorie',
		'update_item'       => 'Mettre à jour la catégorie',
		'add_new_item'      => 'Ajouter une catégorie',
		'new_item_name'     => 'Nom de la nouvelle catégorie',
		'search_items'      => 'Rechercher une catégorie',
		'not_found'         => 'Aucune catégorie trouvée',
		'parent_item'       => 'Catégorie parente',
		'parent_item_colon' => 'Catégorie parente :',
	) : array(
		'name'              => 'Case Study Categories',
		'singular_name'     => 'Case Study Category',
		'menu_name'         => 'Categories',
		'all_items'         => 'All Categories',
		'edit_item'         => 'Edit Category',
		'view_item'         => 'View Category',
		'update_item'       => 'Update Category',
		'add_new_item'      => 'Add New Category',
		'new_item_name'     => 'New Category Name',
		'search_items'      => 'Search Categories',
		'not_found'         => 'No categories found',
		'parent_item'       => 'Parent Category',
		'parent_item_colon' => 'Parent Category:',
	);

	register_taxonomy('case_study_cat', array('case_study'), array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array('slug' => 'case-study-category', 'with_front' => false),
	));
}
