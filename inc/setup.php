<?php
/**
 * Theme setup — supports, textdomain, image sizes.
 * The parent Vault theme handles most supports; only child-specific bits live here.
 */
defined('ABSPATH') || exit;

add_action('after_setup_theme', 'rmd_setup');
function rmd_setup() {
	// Translations for our own strings (native i18n — no Polylang).
	load_child_theme_textdomain('vault-child', RMD_DIR . '/languages');

	// The RMD chrome renders its own <head> (not parent Vault's), so the child
	// must guarantee these itself: <title> output (Rank Math hooks it) and
	// featured-image support. Idempotent if the parent already declared them.
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');

	// Image sizes for the section library — tune in W0.5 alongside rmd_image().
	// add_image_size('rmd-card', 640, 400, true);
}

/**
 * Header nav menu location (managed in Appearance → Menus). Registered on `init`,
 * not after_setup_theme, so get_user_locale() is reliable here — the location's
 * DISPLAY name adapts to the admin user's language (FR/EN), like the section names.
 * One location only: the footer has no nav (logo + copyright, like Mariner).
 */
add_action('init', 'rmd_register_menus');
function rmd_register_menus() {
	register_nav_menus(array(
		'rmd_header' => rmd_is_fr() ? 'RMD — Menu d’en-tête' : 'RMD — Header menu',
	));
}
