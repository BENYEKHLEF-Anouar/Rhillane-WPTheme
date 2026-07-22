<?php
/**
 * Site chrome — a dynamic, editable header + footer (the RMD case-study design).
 *
 * NOT a child header.php/footer.php (that would override parent Vault site-wide —
 * a W3 trap). Instead, `rmd_render_header()` / `rmd_render_footer()` output the
 * full document shell (with wp_head()/wp_footer(), so Rank Math meta + assets still
 * load) and are called by the CPT templates I control (single-case_study.php,
 * archive-case_study.php). When W3 makes a global override safe, header.php/
 * footer.php become one-liners that call these same helpers.
 *
 * Editable, conventional (no CPT): the nav is a WordPress menu (Appearance →
 * Menus, location `rmd_header`); logo / CTA / copyright are Site Settings fields
 * (registered in PHP as a fallback, exportable to acf-json on staging).
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/** Output the header (opens the document). Mirrors get_header(). */
function rmd_render_header() {
	get_template_part('template-parts/site-header');
}

/** Output the footer (closes the document). Mirrors get_footer(). */
function rmd_render_footer() {
	get_template_part('template-parts/site-footer');
}

/**
 * The chrome CSS is only needed where the RMD header/footer render (the case-study
 * templates today). Enqueued after main.css; hand-written, so no Tailwind rebuild.
 */
add_action('wp_enqueue_scripts', 'rmd_chrome_assets');
function rmd_chrome_assets() {
	if (!is_singular('case_study') && !is_post_type_archive('case_study')) {
		return;
	}
	$css = RMD_DIR . '/assets/css/rmd-chrome.css';
	if (file_exists($css)) {
		wp_enqueue_style('rmd-chrome', RMD_URI . '/assets/css/rmd-chrome.css', array('vault-child'), rmd_asset_ver('assets/css/rmd-chrome.css'));
	}
}

/**
 * Site Settings fields for the header/footer. Registered in PHP only when no
 * JSON/DB group exists yet (spec §3.3), bound to the existing options page.
 */
add_action('acf/init', 'rmd_register_chrome_fields');
function rmd_register_chrome_fields() {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	if (function_exists('acf_get_field_group') && acf_get_field_group('group_rmd_chrome')) {
		return;
	}

	acf_add_local_field_group(array(
		'key'    => 'group_rmd_chrome',
		'title'  => 'En-tête & pied de page',
		'fields' => array(
			array('key' => 'field_rmd_chrome_tab_header', 'label' => 'En-tête', 'name' => '', 'type' => 'tab'),
			array('key' => 'field_rmd_header_logo', 'label' => 'Logo (en-tête)', 'name' => 'rmd_header_logo', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium', 'instructions' => 'Logo clair — sur fond blanc. Vide = nom du site en texte.'),
			array('key' => 'field_rmd_header_cta_label', 'label' => 'Bouton — texte', 'name' => 'rmd_header_cta_label', 'type' => 'text', 'placeholder' => 'Audit web gratuit'),
			array('key' => 'field_rmd_header_cta_url', 'label' => 'Bouton — lien', 'name' => 'rmd_header_cta_url', 'type' => 'text', 'placeholder' => '#contact', 'instructions' => 'URL, ancre (#contact) ou mailto:.'),
			array('key' => 'field_rmd_header_sticky', 'label' => 'En-tête fixe (sticky)', 'name' => 'rmd_header_sticky', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1, 'message' => 'Reste en haut et rétrécit au défilement.'),
			array('key' => 'field_rmd_chrome_tab_footer', 'label' => 'Pied de page', 'name' => '', 'type' => 'tab'),
			array('key' => 'field_rmd_footer_logo', 'label' => 'Logo (pied de page)', 'name' => 'rmd_footer_logo', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium', 'instructions' => 'Logo clair — sur fond sombre.'),
			array('key' => 'field_rmd_footer_copyright', 'label' => 'Mentions / copyright', 'name' => 'rmd_footer_copyright', 'type' => 'textarea', 'rows' => 3, 'instructions' => 'HTML léger autorisé (<b>, <br>). Vide = © année + nom du site.'),
		),
		'location' => array(array(array('param' => 'options_page', 'operator' => '==', 'value' => 'rmd-site-settings'))),
		'active'   => true,
		'description' => 'En-tête & pied de page RMD (spec chrome). Éditable dans Réglages du site.',
	));
}
