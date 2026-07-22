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

	// Nav menu locations for the RMD chrome (managed in Appearance → Menus).
	// The header nav pulls from these, so editors assign links without code.
	register_nav_menus(array(
		'rmd_header' => __('RMD — Menu d’en-tête', 'vault-child'),
		'rmd_footer' => __('RMD — Menu de pied de page', 'vault-child'),
	));

	// Image sizes for the section library — tune in W0.5 alongside rmd_image().
	// add_image_size('rmd-card', 640, 400, true);
}
