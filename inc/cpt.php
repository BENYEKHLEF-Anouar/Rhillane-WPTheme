<?php
/**
 * Custom post types — registered in PHP (not the ACF UI) so they exist
 * even when ACF is off. W0 ships case_study; W2 adds service.
 */
defined('ABSPATH') || exit;

/**
 * case_study labels — adapt to the admin user's language (get_user_locale via
 * rmd_is_fr): French admin → French, English admin → English. No .mo files.
 * Applied to whichever registrar runs (ACF Pro's Post Types UI OR the PHP
 * fallback) through rmd_case_study_pin_args() below.
 */
function rmd_case_study_labels() {
	return rmd_is_fr() ? array(
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
}

/**
 * The behaviourally-critical case_study args — the single source of truth for
 * how the type behaves, kept in code so it can't drift. `supports` has no
 * 'editor' (content comes from the `sections` field).
 */
function rmd_case_study_core_args() {
	return array(
		'labels'       => rmd_case_study_labels(),
		'public'       => true,
		'show_in_rest' => true,
		'has_archive'  => true,
		// Confirmed 21/07: French slug — locked for SEO.
		'rewrite'      => array('slug' => 'etudes-de-cas', 'with_front' => false),
		'supports'     => array('title', 'excerpt', 'thumbnail', 'page-attributes'),
	);
}

/**
 * Pin the core definition onto WHOEVER registers case_study. register_post_type()
 * fires this filter for the theme's own fallback AND for a type registered via
 * ACF Pro's Post Types UI (ACF registers through register_post_type internally).
 * So the CPT can live in ACF Pro — visible in its UI, syncable through Local
 * JSON like the field groups — while code still guarantees the bilingual labels,
 * the /etudes-de-cas/ SEO slug, the archive, REST and field support. An ACF UI
 * change can tweak cosmetics (menu icon/position) but can never break the site.
 */
add_filter('register_post_type_args', 'rmd_case_study_pin_args', 20, 2);
function rmd_case_study_pin_args($args, $post_type) {
	if ('case_study' === $post_type) {
		$args = array_merge($args, rmd_case_study_core_args());
	}
	return $args;
}

/**
 * PHP registration is now a FALLBACK. When ACF Pro is active AND case_study is
 * defined in its Post Types UI, ACF registers the type; this then sees it exists
 * and defers. When ACF is absent — or the type isn't defined there yet — this
 * registers it, so the post type NEVER disappears (the "works even if ACF is
 * off" safeguard). No double registration, no gap. Priority 10 keeps it ahead of
 * the taxonomy association, exactly as before.
 */
add_action('init', 'rmd_register_post_types');
function rmd_register_post_types() {
	if (!post_type_exists('case_study')) {
		register_post_type('case_study', array_merge(
			rmd_case_study_core_args(),
			array('menu_icon' => 'dashicons-analytics', 'menu_position' => 21)
		));
	}

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
