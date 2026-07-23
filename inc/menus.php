<?php
/**
 * Default header menus — seeded once, then entirely user-owned.
 *
 * Creates two ready-made menus in Appearance → Menus on each site of the
 * network — « RMD — En-tête (Français) » and « RMD — Header (English) » —
 * and assigns the one matching the SITE language to the `rmd_header`
 * location, so the case-study header is menu-driven from day one. The items
 * mirror the old hardcoded fallback (#resultats / #methode / #contact
 * anchors, which sections expose via their « Ancre (id) » field) and are
 * styled by rmd-chrome.css exactly like that fallback was.
 *
 * Seeding runs ONCE per site (option flag) on an admin visit and never
 * touches the menus again: rename them, add/edit/delete items, reassign the
 * location — everything sticks. A menu that already exists under the same
 * name is reused, and an already-assigned location is never overwritten.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

add_action('admin_init', 'rmd_seed_header_menus');
function rmd_seed_header_menus() {
	if (get_option('rmd_header_menus_seeded') || !current_user_can('edit_theme_options')) {
		return;
	}

	$fr = rmd_ensure_menu('RMD — En-tête (Français)', array(
		array('title' => 'Résultats',     'url' => '#resultats'),
		array('title' => 'Notre méthode', 'url' => '#methode'),
		array('title' => 'Contact',       'url' => '#contact'),
	));
	$en = rmd_ensure_menu('RMD — Header (English)', array(
		array('title' => 'Results',    'url' => '#resultats'),
		array('title' => 'Our method', 'url' => '#methode'),
		array('title' => 'Contact',    'url' => '#contact'),
	));

	// Hook the site-language menu into the header location — only while the
	// location is empty (never steal an assignment the user made themselves).
	$locations = get_theme_mod('nav_menu_locations');
	$locations = is_array($locations) ? $locations : array();
	if (empty($locations['rmd_header'])) {
		// Assign by the SITE's language (locale map), not get_locale(), so an
		// English subsite gets the English menu even if its WP Site Language
		// setting was never switched from French.
		$menu_id = (function_exists('rmd_site_is_fr') && rmd_site_is_fr()) ? $fr : $en;
		if ($menu_id) {
			$locations['rmd_header'] = $menu_id;
			set_theme_mod('nav_menu_locations', $locations);
		}
	}

	update_option('rmd_header_menus_seeded', 1);
}

/**
 * Create a menu with its items unless one with that name already exists.
 * Returns the menu ID (0 on failure) — existing menus are reused untouched.
 */
function rmd_ensure_menu($name, $items) {
	$existing = wp_get_nav_menu_object($name);
	if ($existing) {
		return (int) $existing->term_id;
	}
	$menu_id = wp_create_nav_menu($name);
	if (is_wp_error($menu_id)) {
		return 0;
	}
	$position = 1;
	foreach ($items as $item) {
		wp_update_nav_menu_item($menu_id, 0, array(
			'menu-item-title'    => $item['title'],
			'menu-item-url'      => $item['url'],
			'menu-item-type'     => 'custom',
			'menu-item-status'   => 'publish',
			'menu-item-position' => $position++,
		));
	}
	return (int) $menu_id;
}
