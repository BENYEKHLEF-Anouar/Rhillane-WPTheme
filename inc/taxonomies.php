<?php
/**
 * Taxonomies. W0: case_study_cat.
 *
 * Mirrors the CPT (inc/cpt.php): the taxonomy can be owned by ACF Pro's
 * Taxonomies UI (Local JSON — acf-json/taxonomy_case_study_cat.json) OR by the
 * PHP fallback below when ACF is off. rmd_case_study_cat_pin_args() forces the
 * bilingual labels + the case-study-category slug onto whichever registrar runs.
 */
defined('ABSPATH') || exit;

/**
 * case_study_cat labels — adapt to the admin user's language (rmd_is_fr), like
 * the CPT. Applied to whichever registrar runs via rmd_case_study_cat_pin_args().
 */
function rmd_case_study_cat_labels() {
	return rmd_is_fr() ? array(
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
}

/**
 * The behaviourally-critical case_study_cat args — kept in code so an ACF UI
 * change can't drift the bilingual labels or the case-study-category slug.
 */
function rmd_case_study_cat_core_args() {
	return array(
		'labels'            => rmd_case_study_cat_labels(),
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array('slug' => 'case-study-category', 'with_front' => false),
	);
}

/**
 * Pin the core definition onto WHOEVER registers case_study_cat — the theme's
 * fallback OR a taxonomy defined in ACF Pro's UI (ACF registers through
 * register_taxonomy internally, so this filter fires either way). The
 * object-type association (→ case_study) comes from the registrar: the ACF JSON
 * carries object_type, and the fallback passes it directly below.
 */
add_filter('register_taxonomy_args', 'rmd_case_study_cat_pin_args', 20, 2);
function rmd_case_study_cat_pin_args($args, $taxonomy) {
	if ('case_study_cat' === $taxonomy) {
		$args = array_merge($args, rmd_case_study_cat_core_args());
	}
	return $args;
}

/**
 * PHP registration — a guarded fallback. When ACF Pro owns the taxonomy (defined
 * in its UI / Local JSON) it registers first and this defers; when ACF is absent
 * this registers it, so the taxonomy never disappears. No double registration.
 * Priority 10 (default), after the CPT (also 10, loaded first), so the object
 * association resolves cleanly.
 */
add_action('init', 'rmd_register_taxonomies');
function rmd_register_taxonomies() {
	if (!taxonomy_exists('case_study_cat')) {
		register_taxonomy('case_study_cat', array('case_study'), rmd_case_study_cat_core_args());
	}
}
