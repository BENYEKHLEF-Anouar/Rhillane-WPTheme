<?php
/**
 * One-time content seeding. Current scope: HERO-ONLY smoke test — creates the
 * Mariner Underwear case study with just the hero section filled, so the whole
 * pipeline (CPT → ACF sections → render → chrome → per-site URL) can be
 * verified across the multisite before authoring the full 15-section page.
 *
 * The gate option is per-site (get_option), so each subsite seeds its own
 * hero post on the first wp-admin visit — exactly the per-subsite smoke test.
 * (To seed only the main site instead, add: if (!is_main_site()) return;)
 *
 * Safe to keep deployed: exits instantly once seeded, and refuses to touch an
 * existing mariner-underwear post.
 */
defined('ABSPATH') || exit;

add_action('admin_init', 'rmd_seed_mariner_case_study');
function rmd_seed_mariner_case_study() {
	if ('1' === get_option('rmd_seed_mariner_hero_v1')) {
		return;
	}
	if (!RMD_ACF_ACTIVE || !function_exists('update_field')) {
		return; // ACF required to write the sections — retry on a later visit.
	}
	if (!current_user_can('manage_options')) {
		return;
	}
	// Never overwrite: if the post exists (manually created), just close the gate.
	if (get_page_by_path('mariner-underwear', OBJECT, 'case_study')) {
		update_option('rmd_seed_mariner_hero_v1', '1');
		return;
	}

	$post_id = wp_insert_post(array(
		'post_type'    => 'case_study',
		'post_status'  => 'publish',
		'post_title'   => 'Mariner Underwear : #1 sur Google face aux géants',
		'post_name'    => 'mariner-underwear',
		'post_excerpt' => "Une marque française de sous-vêtements hissée en première page de Google face à Amazon, Calvin Klein et Dim — 4,04M d'impressions, DR 25→55, trafic 100 % organique.",
	));
	if (!$post_id || is_wp_error($post_id)) {
		return; // retry next admin visit
	}

	$svg_search = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
	$svg_doc    = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	$svg_link   = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
	$svg_trend  = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>';

	$sections = array(

		// ═══ HERO — the only section for the smoke test. The remaining 14
		//     Mariner sections get seeded/authored once this proves the
		//     pipeline works across the multisite. ═══
		array(
			'acf_fc_layout'  => 'hero',
			'eyebrow'        => 'Étude de cas client · SEO',
			'kicker'         => 'Mariner Underwear',
			'heading'        => 'Battre les marques milliardaires en',
			'heading_accent' => 'première page de Google',
			'heading_after'  => '.',
			'subheading'     => "Sous-vêtements & homewear masculins. Une stratégie SEO qui a hissé une marque française face à Amazon, Calvin Klein et Dim, en trafic 100 % organique.",
			'tags'           => array(
				array('icon' => $svg_search, 'label' => 'SEO'),
				array('icon' => $svg_doc, 'label' => 'Contenu'),
				array('icon' => $svg_link, 'label' => 'Netlinking'),
				array('icon' => $svg_trend, 'label' => 'SEO technique'),
			),
			'badge'          => 'Stratégie SEO · 2024 → 2026',
			'show_contact'   => 1,
			'stats'          => array(
				array('value' => '#1', 'label' => 'sur « homewear homme » · 33 100 rech./mois'),
				array('value' => 'DR 25 <span class="unit">→</span> 55', 'label' => 'autorité de domaine ×2,2 (Ahrefs)'),
				array('value' => '4,04M', 'label' => 'impressions Google · 13 mois (GSC)'),
			),
		),
	);

	update_field('field_rmd_cs_sections', $sections, $post_id);
	update_option('rmd_seed_mariner_hero_v1', '1');
}
