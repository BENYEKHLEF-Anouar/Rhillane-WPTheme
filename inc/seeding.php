<?php
/**
 * One-time content seeding. Currently: the Mariner Underwear case study —
 * creates the post with all 15 sections filled from the reference design
 * (new-design-ayoub/mariner/mariner-case-study-seo.html) and sideloads the
 * screenshots from the public GitHub repo into the Media Library.
 *
 * v3: full 15-section seed. Upgrades a post that only carries the hero-only
 * smoke seed (≤1 section) in place; never touches a post with real authored
 * content (≥2 sections). The gate option is per-site (get_option), so every
 * subsite seeds itself on its first wp-admin visit after deploy.
 *
 * Safe to keep deployed: exits instantly once seeded.
 */
defined('ABSPATH') || exit;

add_action('admin_init', 'rmd_seed_mariner_case_study');
function rmd_seed_mariner_case_study() {
	if ('1' === get_option('rmd_seed_mariner_full_v3')) {
		return;
	}
	if (!RMD_ACF_ACTIVE || !function_exists('update_field')) {
		return; // ACF required to write the sections — retry on a later visit.
	}
	if (!current_user_can('manage_options')) {
		return;
	}

	$existing = get_page_by_path('mariner-underwear', OBJECT, 'case_study');
	if ($existing) {
		// A post with 2+ sections has real authored content — never overwrite.
		// 0–1 sections = an empty shell or the hero-only smoke seed (identical
		// to section 1 below), so upgrading it in place loses nothing.
		$current_sections = get_post_meta($existing->ID, 'sections', true);
		if (is_array($current_sections) && count($current_sections) >= 2) {
			update_option('rmd_seed_mariner_full_v3', '1');
			return;
		}
		$post_id = $existing->ID;
	} else {
		$post_id = wp_insert_post(array(
			'post_type'   => 'case_study',
			'post_status' => 'publish',
			'post_title'  => 'Mariner Underwear : #1 sur Google face aux géants',
			'post_name'   => 'mariner-underwear',
			'post_excerpt' => "Une marque française de sous-vêtements hissée en première page de Google face à Amazon, Calvin Klein et Dim — 4,04M d'impressions, DR 25→55, trafic 100 % organique.",
		));
		if (!$post_id || is_wp_error($post_id)) {
			return; // retry next admin visit
		}
	}

	$img = static function ($file, $title) use ($post_id) {
		return rmd_seed_sideload('mariner/assets/' . $file, $title, $post_id);
	};

	// ── screenshots from the public repo ──
	$serp_homewear   = $img('serp-homewear.png', 'SERP homewear homme');
	$serp_slip       = $img('serp-slip-taille-haute.png', 'SERP slip taille haute homme');
	$serp_calecon    = $img('serp-calecon.png', 'SERP caleçon slip intérieur');
	$serp_shortys    = $img('serp-shortys.png', 'SERP shortys homme');
	$gsc_2024        = $img('gsc-2024.png', 'GSC clics et impressions 2024');
	$semrush_visib   = $img('semrush-visibility.png', 'Semrush visibilité organique');
	$semrush_distrib = $img('semrush-distribution.png', 'Semrush distribution des positions');
	$semrush_pos     = $img('semrush-positions.png', 'Semrush suivi de positions');
	$semrush_comp    = $img('semrush-competitors.png', 'Semrush classement concurrentiel');

	$svg_search  = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
	$svg_doc     = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>';
	$svg_link    = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
	$svg_trend   = '<svg viewBox="0 0 24 24" fill="none" stroke="#041135" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>';
	$svg_down    = '<svg viewBox="0 0 24 24" fill="none" stroke="#E4004D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>';
	$svg_shield  = '<svg viewBox="0 0 24 24" fill="none" stroke="#E4004D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';
	$svg_crown   = '<svg viewBox="0 0 24 24" fill="none" stroke="#E4004D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7Z"/><path d="M5 20h14"/></svg>';
	$svg_grid    = '<svg viewBox="0 0 24 24" fill="none" stroke="#3943FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>';
	$svg_doc_b   = str_replace('#041135', '#3943FF', $svg_doc);
	$svg_link_b  = str_replace('#041135', '#3943FF', $svg_link);

	$sections = array(

		// ═══ 1 · HERO ═══
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

		// ═══ 2 · KPI STRIP ═══
		array(
			'acf_fc_layout' => 'stats_band',
			'style'         => 'strip',
			'items'         => array(
				array('value' => '60,4K', 'label' => 'Clics organiques · GSC (13 mois)'),
				array('value' => '4,04M', 'label' => 'Impressions Google'),
				array('value' => '55', 'value_note' => '▲ vs 25', 'label' => 'Domain Rating (Ahrefs)'),
				array('value' => '157', 'label' => 'Mots-clés FR en Top 3'),
				array('value' => '58,9K€', 'label' => 'CA via Google organique (GA)', 'highlight' => 1),
			),
		),

		// ═══ 3 · CONTEXTE & DÉFI ═══
		array(
			'acf_fc_layout' => 'stat_cards',
			'eyebrow'       => 'Le contexte',
			'heading'       => 'Un marché dominé <span class="thin">par des marques milliardaires</span>',
			'subheading'    => "Mariner est une marque française historique de sous-vêtements et homewear masculins. Face à elle : Amazon, Calvin Klein, Dim, Hugo Boss, des acteurs aux budgets marketing massifs. Et au départ, un site quasi invisible dans Google : des mots-clés stratégiques enfouis en page 5, une autorité de domaine deux fois trop faible.",
			'accent'        => 'negative',
			'cards'         => array(
				array('icon' => $svg_down, 'value' => '#49', 'label' => 'Position Google au départ', 'body' => 'Sur « caleçon de nuit homme », un mot-clé produit stratégique, le site pointait en <b>position 49, page 5 de Google</b>. Autant dire invisible.'),
				array('icon' => $svg_shield, 'value' => '25', 'label' => 'Domain Rating au départ', 'body' => 'Une autorité de domaine à <b>25 (Ahrefs)</b> : trop faible pour que Google fasse confiance au site face aux marques établies.'),
				array('icon' => $svg_crown, 'value' => '4', 'label' => 'Géants en face', 'body' => 'Amazon, Calvin Klein, Dim, Hugo Boss. Sur chaque mot-clé stratégique, <b>impossible de rivaliser à coups de budget publicitaire</b>.'),
			),
			'background'    => 'light',
		),

		// ═══ 4 · L'INSIGHT ═══
		array(
			'acf_fc_layout' => 'feature_cards',
			'eyebrow'       => "L'insight RMD",
			'heading'       => '« Prendre les requêtes produits, <span class="thin">une par une »</span>',
			'subheading'    => 'Les acheteurs ne tapent pas « Calvin Klein » : ils tapent <b>« slip taille haute homme », « homewear homme », « caleçon de nuit »</b>. Sur ces requêtes précises, les géants restent génériques : une page collection parfaitement optimisée peut les battre.',
			'cards_kicker'  => 'Notre approche',
			'cards'         => array(
				array('icon' => $svg_grid, 'heading' => 'Une page collection par requête', 'body' => 'Chaque famille de produits a sa page ciblée : slip taille basse, caleçon de nuit, pyjama flanelle, boxer bambou.'),
				array('icon' => $svg_doc_b, 'heading' => 'On-page & contenu éditorial', 'body' => "Titres, maillage interne, textes qui répondent exactement à la recherche, plus vite et mieux que les géants."),
				array('icon' => $svg_link_b, 'heading' => 'Netlinking & autorité', 'body' => 'Des liens de qualité acquis dans la durée : le Domain Rating passe de 25 à 55 et chaque nouveau contenu se classe plus vite.'),
			),
			'tiles_kicker'  => "Ce que ça a donné, chiffres à l'appui",
			'tiles'         => array(
				array('value' => '#49 → #1', 'label' => "« caleçon de nuit homme »\naoût 2024 → juin 2026"),
				array('value' => '157', 'label' => "mots-clés en Top 3\nsur les 250 plus porteurs"),
				array('value' => '×2,2', 'label' => "autorité de domaine\nDR 25 → 55 (Ahrefs)"),
				array('value' => '99 %', 'label' => "des 250 mots-clés\nen première page"),
			),
			'highlight'     => 'Et à la clé : <b>58 884 € de CA via Google organique</b> sur 6 mois (Google Analytics), sans un euro de publicité sur ces requêtes.',
		),

		// ═══ 5 · MÉTHODE 4 PHASES ═══
		array(
			'acf_fc_layout' => 'numbered_steps',
			'eyebrow'       => 'Notre méthode',
			'heading'       => 'Une stratégie SEO <span class="thin">en 4 phases</span>',
			'steps'         => array(
				array('heading' => 'Audit technique & sémantique', 'items' => "Audit complet du site : crawl, indexation, vitesse\nÉtude des mots-clés du marché sous-vêtements & homewear\nBenchmark des géants présents sur chaque requête\nPriorisation des requêtes produits à fort potentiel"),
				array('heading' => 'On-page & pages collection', 'items' => "Une page collection optimisée par famille de produits\nMeta titles, descriptions et balisage optimisés\nMaillage interne entre collections et produits\nStructure claire pour Google et pour l'acheteur"),
				array('heading' => 'Contenu éditorial', 'items' => "Contenus ciblés sur les requêtes des acheteurs\nRéponses aux questions qui précèdent l'achat\nOptimisation continue des pages qui progressent\nCouverture des requêtes saisonnières (soldes, hiver)"),
				array('heading' => 'Netlinking & autorité', 'items' => "Acquisition régulière de backlinks de qualité\nDomain Rating : 25 → 55 en deux ans\nJusqu'à 978 domaines référents au pic\nSuivi mensuel : positions, clics, impressions"),
			),
			'background'    => 'light',
			'anchor'        => 'methode',
		),

		// ═══ 6 · SERP PROOF ═══
		array(
			'acf_fc_layout' => 'screenshot_gallery',
			'eyebrow'       => 'Résultats SEO',
			'heading'       => 'Première page <span class="thin">face à Amazon & Calvin Klein</span>',
			'subheading'    => 'Captures de résultats Google réels, la marque devant les géants sur ses mots-clés business.',
			'columns'       => '2',
			'items'         => array(
				array('image' => $serp_homewear, 'style' => 'browser', 'label' => '« homewear homme »', 'badge' => '#1', 'scrollable' => 1, 'scroll_height' => 380, 'zoomable' => 0),
				array('image' => $serp_slip, 'style' => 'browser', 'label' => '« slip taille haute homme »', 'badge' => '#1', 'scrollable' => 1, 'scroll_height' => 380, 'zoomable' => 0),
				array('image' => $serp_calecon, 'style' => 'browser', 'label' => "« caleçon slip d'intérieur »", 'badge' => '#1', 'scrollable' => 1, 'scroll_height' => 380, 'zoomable' => 0),
				array('image' => $serp_shortys, 'style' => 'browser', 'label' => '« shortys homme »', 'badge' => '#1', 'scrollable' => 1, 'scroll_height' => 380, 'zoomable' => 0),
			),
			'anchor'        => 'resultats',
		),

		// ═══ 7 · TRAFIC ORGANIQUE (cartes) ═══
		array(
			'acf_fc_layout' => 'stat_cards',
			'eyebrow'       => 'Trafic organique',
			'heading'       => 'La montée <span class="thin">dans Google</span>',
			'accent'        => 'positive',
			'cards'         => array(
				array('value' => '60 413', 'label' => 'Clics organiques', 'body' => 'Cumulés sur 13 mois (août 2024 → août 2025), <b>données Google Search Console</b>.'),
				array('value' => '4,04M', 'label' => 'Impressions Google', 'body' => "La marque s'affiche <b>4 millions de fois</b> dans les résultats de recherche sur la période."),
				array('value' => '7 943', 'label' => 'Clics · déc. 2024 (pic)', 'body' => 'Meilleur mois : <b>424 867 impressions</b>, position moyenne 12,8, en plein Q4 commercial.'),
			),
			'background'    => 'light',
		),

		// ═══ 8 · TRAFIC (captures) ═══
		array(
			'acf_fc_layout' => 'screenshot_gallery',
			'columns'       => '1',
			'items'         => array(
				array('image' => $gsc_2024, 'style' => 'plain', 'zoomable' => 1, 'caption' => '<b>Clics & impressions</b>, montée continue jusqu\'au pic de décembre 2024', 'source' => 'SEARCH CONSOLE'),
				array('image' => $semrush_visib, 'style' => 'plain', 'zoomable' => 1, 'caption' => '<b>Visibilité organique</b>, tendance long terme', 'source' => 'SEMRUSH'),
			),
			'background'    => 'light',
			'padding_top'   => 'flush',
		),

		// ═══ 9 · AUTORITÉ (statduo) ═══
		array(
			'acf_fc_layout' => 'stats_band',
			'style'         => 'card',
			'eyebrow'       => 'Autorité de domaine',
			'heading'       => 'Une autorité construite <span class="thin">dans la durée</span>',
			'subheading'    => "Le Domain Rating mesure la <b>force du profil de liens</b> d'un site. Passer de 25 à 55, c'est changer de catégorie : Google fait davantage confiance au site, et chaque nouveau contenu se positionne plus vite. Une autorité construite par un netlinking régulier et qualitatif.",
			'items'         => array(
				array('tag' => 'AUTORITÉ', 'value' => '25 → 55', 'label' => 'Domain Rating ×2,2 en 2 ans'),
				array('tag' => 'NETLINKING', 'value' => '978', 'label' => 'domaines référents au pic (oct. 2025)'),
				array('tag' => 'BACKLINKS', 'value' => '1 295', 'label' => 'liens actifs depuis 615 domaines'),
			),
		),

		// ═══ 10 · DR CHART ═══
		array(
			'acf_fc_layout' => 'line_chart',
			'chart_title'   => 'Domain Rating, juin 2024 → juin 2026',
			'chart_note'    => '(Ahrefs)',
			'value_prefix'  => 'DR',
			'points'        => rmd_seed_chart_points(
				array(25, 27, 26, 27, 35, 38, 40, 39, 39, 39, 33, 38, 40, 45, 47, 48, 49, 50, 49, 48, 50, 51, 53, 54, 55),
				'juin 2024',
				'juin 2026'
			),
			'padding_top'   => 'flush',
		),

		// ═══ 11 · MOTS-CLÉS (tableau) ═══
		array(
			'acf_fc_layout' => 'table_split',
			'eyebrow'       => 'Positions Google',
			'heading'       => 'Les mots-clés <span class="thin">qui ramènent le trafic</span>',
			'subheading'    => 'Sur les 250 mots-clés FR les plus porteurs de trafic : <b>157 en Top 3</b> et 90 en positions 4–10 (Ahrefs, juin 2026 vs août 2024).',
			'table_columns' => array(
				array('label' => 'Mot-clé'),
				array('label' => 'Volume'),
				array('label' => 'Août 2024'),
				array('label' => 'Juin 2026', 'highlight' => 1),
			),
			'table_rows'    => rmd_seed_table_rows(array(
				array('caleçon de nuit homme', '200', '#49', '#1'),
				array('underwear', '3 300', '#39', '#7'),
				array('pyjama en flanelle homme', '150', '#12', '#2'),
				array('pyjama velours femme', '350', '#11', '#3'),
				array('slip homme taille basse', '150', '#3', '#1'),
				array('shorty homme', '2 300', '#9', '#7'),
				array('xxxxl shirt', '2 400', 'n/a', '#1'),
				array('boxer bambou', '800', 'n/a', '#7'),
				array("pantalon d'intérieur homme", '70', 'n/a', '#1'),
				array('mariner', '1 800', '#3', '#1'),
			), 3),
			'side_stats'    => array(
				array('tag' => 'TOP 3', 'value' => '157', 'label' => 'mots-clés sur 250 en positions 1–3'),
				array('tag' => 'TOP 10', 'value' => '247', 'label' => 'soit 99 % des 250 mots-clés en 1re page'),
			),
			'comment'       => "<b>« caleçon de nuit homme » : de la position 49 à la position 1.</b> C'est le SEO qui transforme des pages collection en points d'entrée rentables, sans dépenser un euro de publicité sur ces requêtes.",
			'media_position' => 'none',
			'background'    => 'light',
		),

		// ═══ 12 · MOTS-CLÉS (captures) ═══
		array(
			'acf_fc_layout' => 'screenshot_gallery',
			'columns'       => '1',
			'items'         => array(
				array('image' => $semrush_distrib, 'style' => 'plain', 'zoomable' => 1, 'caption' => '<b>Distribution des positions</b>, Top 3 / Top 10 / Top 20 / Top 100', 'source' => 'SEMRUSH'),
				array('image' => $semrush_pos, 'style' => 'plain', 'scrollable' => 1, 'scroll_height' => 380, 'zoomable' => 1, 'caption' => '<b>Suivi de positions</b>, mots-clés suivis dans Semrush', 'source' => 'SEMRUSH'),
			),
			'background'    => 'light',
			'padding_top'   => 'flush',
		),

		// ═══ 13 · CONCURRENTS & PAYS ═══
		array(
			'acf_fc_layout' => 'table_split',
			'eyebrow'       => 'Face aux concurrents',
			'heading'       => 'Classement concurrentiel <span class="thin">et portée internationale</span>',
			'table_columns' => array(
				array('label' => 'Pays'),
				array('label' => 'Clics organiques (GSC)'),
			),
			'table_rows'    => array(
				array('cells' => array(array('content' => '🇫🇷 France'), array('content' => '37 408', 'is_win' => 1))),
				array('cells' => array(array('content' => '🇧🇪 Belgique'), array('content' => '4 230'))),
				array('cells' => array(array('content' => '🇩🇿 Algérie'), array('content' => '2 809'))),
				array('cells' => array(array('content' => '🇨🇩 RD Congo'), array('content' => '2 395'))),
				array('cells' => array(array('content' => '🇲🇦 Maroc'), array('content' => '1 724'))),
			),
			'comment'       => "<b>Le SEO en français voyage.</b> La France concentre l'essentiel des clics, mais la visibilité s'étend naturellement à la Belgique, au Maghreb et à l'Afrique francophone, un trafic additionnel gratuit, sans un euro de média.",
			'media_position' => 'left',
			'media_image'   => $semrush_comp,
			'media_caption' => '<b>Classement concurrentiel</b>',
			'media_source'  => 'SEMRUSH',
		),

		// ═══ 14 · RECAP BAND ═══
		array(
			'acf_fc_layout' => 'recap_band',
			'heading'       => 'Battre les géants, sans budget publicitaire.',
			'pills'         => array(
				array('text' => '<b>#1</b> face à Amazon & Calvin Klein'),
				array('text' => 'Autorité : <b>DR 25 → 55</b>'),
				array('text' => '<b>157</b> mots-clés en Top 3'),
				array('text' => '<b>4,04M</b> impressions Google'),
				array('text' => 'CA organique : <b>58,9K€</b>'),
			),
		),

		// ═══ 15 · CTA ═══
		array(
			'acf_fc_layout'  => 'cta',
			'eyebrow'        => 'Votre tour',
			'heading'        => 'Obtenez les',
			'heading_accent' => 'mêmes résultats',
			'heading_after'  => '.',
			'subheading'     => 'Votre marché aussi a ses géants. Parlons de votre visibilité Google, chiffres réels à l\'appui.',
			'button_label'   => 'Discuter de mon projet',
			'button_url'     => 'mailto:contact@rhillane.com',
			'contact_line'   => 'contact@rhillane.com · +212 663-091166',
			'background'     => 'light',
			'anchor'         => 'contact',
		),
	);

	update_field('field_rmd_cs_sections', $sections, $post_id);
	update_option('rmd_seed_mariner_full_v3', '1');

	// A stale (hero-only or empty) copy of the page may be cached — purge.
	do_action('litespeed_purge_all');
}

/**
 * Download one repo asset into the Media Library. Returns attachment ID or 0.
 * Reuses an existing attachment with the same title (idempotent).
 */
function rmd_seed_sideload($repo_path, $title, $post_id) {
	$existing = get_posts(array(
		'post_type'      => 'attachment',
		'title'          => $title,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	));
	if ($existing) {
		return (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$url = 'https://raw.githubusercontent.com/BENYEKHLEF-Anouar/Rhillane-WPTheme/main/new-design-ayoub/' . $repo_path;
	$id  = media_sideload_image($url, $post_id, $title, 'id');

	return is_wp_error($id) ? 0 : (int) $id;
}

/** value list → line_chart points repeater (only first/last get a label). */
function rmd_seed_chart_points($values, $first_label, $last_label) {
	$points = array();
	$last   = count($values) - 1;
	foreach ($values as $i => $v) {
		$label    = 0 === $i ? $first_label : ($i === $last ? $last_label : '');
		$points[] = array('label' => $label, 'value' => $v);
	}
	return $points;
}

/** rows of plain cell values → table_rows repeater; $win_col cell index gets is_win. */
function rmd_seed_table_rows($rows, $win_col) {
	$out = array();
	foreach ($rows as $row) {
		$cells = array();
		foreach ($row as $i => $value) {
			$cell = array('content' => $value);
			if ($i === $win_col) {
				$cell['is_win'] = 1;
			}
			$cells[] = $cell;
		}
		$out[] = array('cells' => $cells);
	}
	return $out;
}
