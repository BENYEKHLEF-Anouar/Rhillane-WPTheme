<?php
/**
 * Editor UX for the advanced link attributes (spec §10.1): the long rel/target/
 * aria/data-* group is folded behind a "⚙ Options avancées" button that opens a
 * popup. Assets only — the fields themselves come from inc/link-fields.php.
 *
 * Loaded on the two screens that actually own a CTA: the case-study editor (the
 * `cta` section) and Site Settings (the header button). Nowhere else — other
 * plugins' ACF screens are none of our business.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/**
 * ACF field NAMES the popup takes over. A name that doesn't exist on screen is
 * simply never matched, so listing both screens' fields here is harmless.
 */
function rmd_link_options_field_names() {
	return array(
		'button_advanced',          // cta section (acf-json)
		'rmd_header_cta_advanced',  // Site Settings header CTA (inc/chrome.php)
	);
}

/** True on the ACF Site Settings options page (inc/options.php). */
function rmd_is_site_settings_screen($hook) {
	return is_string($hook) && false !== strpos($hook, 'rmd-site-settings');
}

add_action('admin_enqueue_scripts', 'rmd_link_options_assets');
function rmd_link_options_assets($hook) {
	$is_sections = function_exists('rmd_is_section_edit_screen') && rmd_is_section_edit_screen();
	if (!$is_sections && !rmd_is_site_settings_screen($hook)) {
		return;
	}

	$css = RMD_DIR . '/assets/admin/link-options.css';
	$js  = RMD_DIR . '/assets/admin/link-options.js';
	if (!file_exists($css) || !file_exists($js)) {
		return; // no assets → the group just renders inline. Degraded, not broken.
	}

	wp_enqueue_style('rmd-link-options', RMD_URI . '/assets/admin/link-options.css', array('dashicons'), filemtime($css));
	wp_enqueue_script('rmd-link-options', RMD_URI . '/assets/admin/link-options.js', array(), filemtime($js), true);

	// Editor-UI strings follow the admin user's language (rmd_is_fr pattern used
	// across the editor), not the site locale.
	$fr = function_exists('rmd_is_fr') && rmd_is_fr();

	wp_localize_script('rmd-link-options', 'rmdLinkOptions', array(
		'fields' => rmd_link_options_field_names(),
		'i18n'   => $fr ? array(
			'trigger'      => 'Options avancées (SEO)',
			'triggerEmpty' => 'rel/nofollow, nouvel onglet, aria-label, id, data-*…',
			'title'        => 'Options avancées du lien',
			'note'         => 'nofollow / sponsored / ugc concernent les liens sortants. « Nouvel onglet » ajoute noopener noreferrer automatiquement.',
			'done'         => 'Terminé',
			'close'        => 'Fermer',
		) : array(
			'trigger'      => 'Advanced options (SEO)',
			'triggerEmpty' => 'rel/nofollow, new tab, aria-label, id, data-*…',
			'title'        => 'Advanced link options',
			'note'         => 'nofollow / sponsored / ugc are for outbound links. "New tab" adds noopener noreferrer automatically.',
			'done'         => 'Done',
			'close'        => 'Close',
		),
	));
}
