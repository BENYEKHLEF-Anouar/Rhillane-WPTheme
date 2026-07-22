<?php
/**
 * Editor helpers — ported from the AMD project and adapted to RMD.
 *
 * The "library of sections" experience on the case_study editor:
 *   1. Live section preview (AMD §8) — the "Add Section" popup becomes a card
 *      grid, each card + each existing row gets an eye that opens a scaled-iframe
 *      preview. Demo mode renders example content; row mode renders saved values.
 *   2. Duplicate-section warning (AMD §7.2).
 *   3. Field hints — placeholders/instructions injected at runtime (acf/load_field)
 *      so every field guides the editor without hardcoding content.
 *
 * RMD adaptation of the AMD original: layout key === template file name (1:1, NO
 * "_section" suffix stripping); the field is `sections`; everything is prefixed
 * `rmd_`; all ACF access goes through the null-safe wrappers in inc/acf.php.
 *
 * Security (endpoint): nonce + capability (edit_post/edit_posts) + sanitize_key
 * + layout allowlist (path never built from raw input) + wp_ajax_ only (no nopriv)
 * + noindex + nocache. Enqueued ONLY on post.php / post-new.php when ACF is on.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/* ─────────────────────────────────────────────────────────────────────────
 * 1. Layout registry — labels (from the ACF group), descriptions, allowlist.
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Friendly, NON-technical section names + descriptions, in the editor's language.
 * Bilingual FR/EN, picked from the admin user's locale (get_user_locale) — a
 * French admin sees French, an English admin sees English, no .mo files needed.
 */
function rmd_section_i18n() {
	$is_fr = rmd_is_fr();

	// Keep the ORIGINAL technical names (the team knows them); the description
	// below each carries the plain-language detail. Still bilingual so an English
	// admin gets the technical name in English too.
	$labels = $is_fr ? array(
		'hero'               => 'Hero',
		'stats_band'         => 'Bandeau de stats (navy)',
		'stat_cards'         => 'Cartes chiffres',
		'feature_cards'      => 'Insight (cartes + tuiles + preuve)',
		'numbered_steps'     => 'Méthode (étapes numérotées)',
		'screenshot_gallery' => 'Galerie de captures',
		'table_split'        => 'Tableau + colonne compagnon',
		'line_chart'         => 'Courbe (SVG)',
		'recap_band'         => 'Bandeau récap (dégradé)',
		'cta'                => 'CTA final',
	) : array(
		'hero'               => 'Hero',
		'stats_band'         => 'Stats band (navy)',
		'stat_cards'         => 'Number cards',
		'feature_cards'      => 'Insight (cards + tiles + proof)',
		'numbered_steps'     => 'Method (numbered steps)',
		'screenshot_gallery' => 'Screenshot gallery',
		'table_split'        => 'Table + companion column',
		'line_chart'         => 'Chart (SVG)',
		'recap_band'         => 'Recap band (gradient)',
		'cta'                => 'Final CTA',
	);

	$desc = $is_fr ? array(
		'hero'               => 'L’ouverture de l’étude : grand titre, étiquettes, badge, ligne contact et une carte de chiffres clés.',
		'stats_band'         => 'Une bande de chiffres clés sur fond sombre — en pleine largeur ou en carte arrondie.',
		'stat_cards'         => 'Des cartes de chiffres — en rouge pour le problème de départ, en vert pour les résultats.',
		'feature_cards'      => 'Votre approche : cartes d’explication, tuiles de chiffres et une phrase de preuve.',
		'numbered_steps'     => 'Votre méthode présentée en étapes numérotées (1, 2, 3, 4) avec des puces.',
		'screenshot_gallery' => 'Une galerie de captures d’écran, sur 1 ou 2 colonnes, agrandissables au clic.',
		'table_split'        => 'Un tableau de données avec, à côté, des chiffres ou une capture et un commentaire.',
		'line_chart'         => 'Un graphique en courbe tracé à partir de vos points de données (ex. une évolution).',
		'recap_band'         => 'Un bandeau de synthèse sur fond sombre avec une rangée d’étiquettes.',
		'cta'                => 'L’appel à l’action final, centré, avec un bouton et vos coordonnées.',
	) : array(
		'hero'               => 'The study’s opening: big title, tags, a badge, a contact line and a key-figures card.',
		'stats_band'         => 'A band of key figures on a dark background — full width or as a rounded card.',
		'stat_cards'         => 'Number cards — red for the starting problem, green for the results.',
		'feature_cards'      => 'Your approach: explainer cards, number tiles and a one-line proof.',
		'numbered_steps'     => 'Your method shown as numbered steps (1, 2, 3, 4) with bullet points.',
		'screenshot_gallery' => 'A gallery of screenshots, in 1 or 2 columns, click to enlarge.',
		'table_split'        => 'A data table with figures or a screenshot beside it, plus a comment.',
		'line_chart'         => 'A line chart drawn from your data points (e.g. growth over time).',
		'recap_band'         => 'A summary band on a dark background with a row of tags.',
		'cta'                => 'The final call-to-action, centered, with a button and your contact details.',
	);

	return array('labels' => $labels, 'desc' => $desc);
}

/** Descriptions shown on the picker cards + preview modal (localized FR/EN). */
function rmd_layout_descriptions() {
	$i18n = rmd_section_i18n();
	return $i18n['desc'];
}

/**
 * Rewrite the flexible-content layout LABELS to the friendly, localized names.
 * Runs on the `sections` field, so ACF's native picker AND our preview data
 * (rmd_preview_layouts reads the group via acf_get_fields, which applies this)
 * both show the detailed FR/EN names instead of the terse JSON labels.
 */
add_filter('acf/load_field/key=field_rmd_cs_sections', 'rmd_localize_section_labels');
function rmd_localize_section_labels($field) {
	if (!is_admin() || empty($field['layouts']) || !is_array($field['layouts'])) {
		return $field;
	}
	$labels = rmd_section_i18n();
	$labels = $labels['labels'];
	foreach ($field['layouts'] as $k => $layout) {
		$name = isset($layout['name']) ? $layout['name'] : '';
		if ('' !== $name && isset($labels[$name])) {
			$field['layouts'][$k]['label'] = $labels[$name];
		}
	}
	return $field;
}

/**
 * Runtime English for the French-authored field group (same "no .mo files"
 * pattern as the section names): acf-json stays the single FR source of truth,
 * and when the admin's profile language isn't French, every label /
 * instruction / select choice / "Ajouter…" button is swapped from these FR→EN
 * dictionaries at load_field time. Keyed by the FRENCH string, so repeated
 * labels ("Fond", "Ancre (id)"…) are covered once and new fields with known
 * labels translate automatically. Unknown strings pass through unchanged.
 */
function rmd_admin_en_strings() {
	return array(
		'labels' => array(
			'Nom du client (rouge)'    => 'Client name (red)',
			'Titre — début'            => 'Heading — start',
			'Titre — partie dégradée'  => 'Heading — gradient part',
			'Titre — fin'              => 'Heading — end',
			'Sous-titre'               => 'Subheading',
			'Icône (SVG)'              => 'Icon (SVG)',
			'Libellé'                  => 'Label',
			'Badge rouge'              => 'Red badge',
			'Afficher la ligne contact' => 'Show the contact line',
			'Carte stats (bloc navy)'  => 'Stats card (navy block)',
			'Valeur'                   => 'Value',
			'Titre (H2)'               => 'Heading (H2)',
			'Titre'                    => 'Heading',
			'Mini-tag vert'            => 'Green mini-tag',
			'Note verte à côté'        => 'Green note beside it',
			'Libellé / delta'          => 'Label / delta',
			'Valeur en vert'           => 'Show value in green',
			'Fond'                     => 'Background',
			'Ancre (id)'               => 'Anchor (id)',
			'Espacement haut'          => 'Top spacing',
			'Couleur des chiffres'     => 'Number color',
			'Cartes'                   => 'Cards',
			'Chiffre'                  => 'Number',
			'Kicker (majuscules)'      => 'Kicker (uppercase)',
			'Texte'                    => 'Text',
			'Kicker des cartes'        => 'Cards kicker',
			'Cartes approche'          => 'Approach cards',
			'Kicker des tuiles'        => 'Tiles kicker',
			'Tuiles stats'             => 'Stat tiles',
			'Valeur (verte)'           => 'Value (green)',
			'Libellé (2 lignes ok)'    => 'Label (2 lines ok)',
			'Bandeau preuve (vert)'    => 'Proof band (green)',
			'Étapes'                   => 'Steps',
			'Puces — une par ligne'    => 'Bullets — one per line',
			'Colonnes'                 => 'Columns',
			'Captures'                 => 'Screenshots',
			'Capture'                  => 'Screenshot',
			'Cadre'                    => 'Frame',
			'Libellé (mot-clé)'        => 'Label (keyword)',
			'Badge position (#1)'      => 'Position badge (#1)',
			'Défilement interne'       => 'Internal scrolling',
			'Hauteur (px)'             => 'Height (px)',
			'Zoom (lightbox)'          => 'Zoom (lightbox)',
			'Légende'                  => 'Caption',
			'Source (pill verte)'      => 'Source (green pill)',
			'Colonnes du tableau'      => 'Table columns',
			'En-tête'                  => 'Header',
			'Colonne verte'            => 'Green column',
			'Lignes du tableau'        => 'Table rows',
			'Cellules'                 => 'Cells',
			'Contenu'                  => 'Content',
			'Vert/gras'                => 'Green/bold',
			'Stats latérales (navy)'   => 'Side stats (navy)',
			'Delta / libellé'          => 'Delta / label',
			'Commentaire (encadré)'    => 'Comment (boxed)',
			'Capture à gauche ?'       => 'Screenshot on the left?',
			'Légende de la capture'    => 'Screenshot caption',
			'Titre de la courbe'       => 'Chart title',
			'Note (source)'            => 'Note (source)',
			'Préfixe des valeurs'      => 'Value prefix',
			'Points de données'        => 'Data points',
			'Libellé (mois)'           => 'Label (month)',
			'Texte du bouton'          => 'Button text',
			'Lien du bouton'           => 'Button link',
			'Ligne contact'            => 'Contact line',
			// Site Settings — header/footer chrome (inc/chrome.php)
			'Logo (en-tête)'           => 'Logo (header)',
			'Bouton — texte'           => 'Button — text',
			'Bouton — lien'            => 'Button — link',
			'En-tête fixe (sticky)'    => 'Sticky header',
			'Logo (pied de page)'      => 'Logo (footer)',
			'Mentions / copyright'     => 'Notices / copyright',
			'Pied de page'             => 'Footer',
		),
		'instructions' => array(
			'Coller le code SVG inline (icône trait).' => 'Paste the inline SVG code (stroke icon).',
			'Email / téléphone / site depuis Réglages du site.' => 'Email / phone / website come from Site Settings.',
			'HTML léger autorisé : <span class="unit">→</span>.' => 'Light HTML allowed: <span class="unit">→</span>.',
			'HTML léger autorisé : <span class="thin">…</span>, <b>.' => 'Light HTML allowed: <span class="thin">…</span>, <b>.',
			'HTML léger autorisé : <b>, <span class="…">.' => 'Light HTML allowed: <b>, <span class="…">.',
			'HTML léger autorisé : <b>.' => 'Light HTML allowed: <b>.',
			'Ex. resultats, methode, contact — pour les liens #ancre.' => 'E.g. resultats, methode, contact — for #anchor links.',
			'URL ou mailto: — champ texte volontairement (le type url refuse mailto).' => 'URL or mailto: — deliberately a text field (the url type rejects mailto).',
			// Site Settings — header/footer chrome (inc/chrome.php)
			'Optionnel — remplace le logo du thème. Vide = logo par défaut (fond blanc).' => 'Optional — replaces the theme logo. Empty = the default logo (white background).',
			'Optionnel — remplace le logo du thème. Vide = logo par défaut (fond sombre).' => 'Optional — replaces the theme logo. Empty = the default logo (dark background).',
			'URL, ancre (#contact) ou mailto:.' => 'URL, anchor (#contact) or mailto:.',
			'HTML léger autorisé (<b>, <br>). Vide = © année + nom du site.' => 'Light HTML allowed (<b>, <br>). Empty = © year + site name.',
		),
		'messages' => array(
			'Reste en haut et rétrécit au défilement.' => 'Stays at the top and shrinks on scroll.',
		),
		// Placeholders authored in acf-json / chrome.php (example content included:
		// the user wants ZERO French in an English workspace).
		'placeholders' => array(
			'Stratégie SEO · 2024 → 2026'             => 'SEO strategy · 2024 → 2026',
			'AUTORITÉ'                                 => 'AUTHORITY',
			'Ce que ça a donné, chiffres à l’appui'    => 'What it delivered, numbers in hand',
			'Domain Rating, juin 2024 → juin 2026'     => 'Domain Rating, June 2024 → June 2026',
			'Audit web gratuit'                        => 'Free website audit',
		),
		'choices' => array(
			'Bandeau pleine largeur (KPI)'   => 'Full-width band (KPI)',
			'Carte arrondie (statduo)'       => 'Rounded card (statduo)',
			'Blanc'                          => 'White',
			'Gris clair'                     => 'Light grey',
			'Normal'                         => 'Normal',
			'Collé à la section précédente'  => 'Flush with the previous section',
			'Rouge (contexte/problème)'      => 'Red (context/problem)',
			'Vert (résultat)'                => 'Green (result)',
			'1 colonne'                      => '1 column',
			'2 colonnes'                     => '2 columns',
			'Simple'                         => 'Plain',
			'Navigateur (points + libellé)'  => 'Browser (dots + label)',
			'Pas de capture'                 => 'No screenshot',
			'Capture à gauche'               => 'Screenshot on the left',
		),
		'buttons' => array(
			'Ajouter une section'  => 'Add a section',
			'Ajouter un tag'       => 'Add a tag',
			'Ajouter une stat'     => 'Add a stat',
			'Ajouter une carte'    => 'Add a card',
			'Ajouter une tuile'    => 'Add a tile',
			'Ajouter une étape'    => 'Add a step',
			'Ajouter une capture'  => 'Add a screenshot',
			'Ajouter une colonne'  => 'Add a column',
			'Ajouter une ligne'    => 'Add a row',
			'Ajouter une cellule'  => 'Add a cell',
			'Ajouter un point'     => 'Add a point',
			'Ajouter une pill'     => 'Add a pill',
		),
	);
}

/** Swap FR strings for EN on every rmd_ field when the admin isn't French.
 *  Priority 20: runs AFTER the hints filter, so injected hints (already
 *  locale-aware) are never double-processed. */
add_filter('acf/load_field', 'rmd_translate_field_for_admin', 20);
function rmd_translate_field_for_admin($field) {
	if (!is_admin() || rmd_is_fr()) {
		return $field;
	}
	if (empty($field['key']) || 0 !== strpos($field['key'], 'field_rmd_')) {
		return $field;
	}
	static $en = null;
	if (null === $en) {
		$en = rmd_admin_en_strings();
	}
	if (!empty($field['label']) && isset($en['labels'][$field['label']])) {
		$field['label'] = $en['labels'][$field['label']];
	}
	if (!empty($field['instructions']) && isset($en['instructions'][$field['instructions']])) {
		$field['instructions'] = $en['instructions'][$field['instructions']];
	}
	if (!empty($field['button_label']) && isset($en['buttons'][$field['button_label']])) {
		$field['button_label'] = $en['buttons'][$field['button_label']];
	}
	if (!empty($field['message']) && isset($en['messages'][$field['message']])) {
		$field['message'] = $en['messages'][$field['message']];
	}
	if (!empty($field['placeholder']) && isset($en['placeholders'][$field['placeholder']])) {
		$field['placeholder'] = $en['placeholders'][$field['placeholder']];
	}
	if (!empty($field['choices']) && is_array($field['choices'])) {
		foreach ($field['choices'] as $value => $choice_label) {
			if (isset($en['choices'][$choice_label])) {
				$field['choices'][$value] = $en['choices'][$choice_label];
			}
		}
	}
	return $field;
}

/** Metabox titles in the admin's language (both directions). */
add_filter('acf/load_field_group', 'rmd_localize_group_titles');
function rmd_localize_group_titles($group) {
	if (!is_admin() || !function_exists('rmd_is_fr') || empty($group['key'])) {
		return $group;
	}
	if ('group_rmd_case_study_sections' === $group['key']) {
		$group['title'] = rmd_is_fr() ? 'Étude de cas — Sections' : 'Case Study — Sections';
	} elseif ('group_rmd_chrome' === $group['key']) {
		$group['title'] = rmd_is_fr() ? 'En-tête & pied de page' : 'Header & footer';
	}
	return $group;
}

/**
 * layout => the ONE sub-field whose value makes two uses of the same layout
 * visually different sections (red problem cards vs green result cards, strip
 * vs card band…). Drives two things: the variant suffix on collapsed row titles
 * (rmd_section_layout_title) and the duplicate warning, which only fires for
 * same layout + same variant. Field keys match acf-json.
 */
function rmd_section_variant_fields() {
	return array(
		'stat_cards'         => array('name' => 'accent',         'key' => 'field_rmd_cs_stat_cards_accent'),
		'stats_band'         => array('name' => 'style',          'key' => 'field_rmd_cs_stats_band_style'),
		'screenshot_gallery' => array('name' => 'columns',        'key' => 'field_rmd_cs_screenshot_gallery_columns'),
		'table_split'        => array('name' => 'media_position', 'key' => 'field_rmd_cs_table_split_media_position'),
	);
}

/**
 * Collapsed row titles carry the variant so similar-but-different sections are
 * distinguishable at a glance: « Cartes chiffres — Vert (résultat) ». The suffix
 * text is the select choice's own label (source of truth: the field group), so
 * it never drifts from what the editor picked. Editor display only — the front
 * end never sees layout titles.
 */
add_filter('acf/fields/flexible_content/layout_title', 'rmd_section_layout_title', 10, 4);
function rmd_section_layout_title($title, $field, $layout, $i) {
	if (!isset($field['key']) || 'field_rmd_cs_sections' !== $field['key']) {
		return $title;
	}
	$map  = rmd_section_variant_fields();
	$name = isset($layout['name']) ? $layout['name'] : '';
	if (!isset($map[$name]) || !function_exists('get_sub_field')) {
		return $title;
	}
	$value = get_sub_field($map[$name]['name']);
	if (!$value || !is_scalar($value)) {
		return $title;
	}
	$suffix = (string) $value;
	foreach ((array) ($layout['sub_fields'] ?? array()) as $sub) {
		if (($sub['name'] ?? '') === $map[$name]['name'] && !empty($sub['choices'][$value])) {
			$suffix = $sub['choices'][$value];
			break;
		}
	}
	return $title . ' — ' . $suffix;
}

/**
 * layout name => { label, desc }. Labels read from the real ACF field group so
 * they never drift from the picker; falls back to a prettified name. Only layouts
 * with a real template file are exposed. Doubles as the security allowlist.
 */
function rmd_preview_layouts() {
	$desc   = rmd_layout_descriptions();
	$labels = array();

	if (function_exists('acf_get_field_group') && function_exists('acf_get_fields')) {
		$group = acf_get_field_group('group_rmd_case_study_sections');
		if ($group) {
			foreach ((array) acf_get_fields($group) as $field) {
				if (($field['name'] ?? '') === 'sections' && !empty($field['layouts'])) {
					foreach ((array) $field['layouts'] as $layout) {
						if (!empty($layout['name'])) {
							$labels[$layout['name']] = !empty($layout['label']) ? $layout['label'] : $layout['name'];
						}
					}
				}
			}
		}
	}

	$data  = array();
	$names = array_unique(array_merge(array_keys($desc), array_keys($labels)));
	foreach ($names as $name) {
		// 1:1 mapping — never build a path from raw input; must resolve to a file.
		if (!locate_template('template-parts/layouts/' . $name . '.php')) {
			continue;
		}
		$data[$name] = array(
			'label' => isset($labels[$name]) ? $labels[$name] : ucwords(str_replace('_', ' ', $name)),
			'desc'  => isset($desc[$name]) ? $desc[$name] : '',
		);
	}
	return $data;
}

/* ─────────────────────────────────────────────────────────────────────────
 * 2. Demo content — example values for the "Add Section" (demo) preview.
 *    Seeded into $GLOBALS['rmd_demo'], which rmd_get_sub_field() reads (inc/acf.php)
 *    so the REAL template renders a filled example. This is example/placeholder
 *    content, never a client's real data, and only ever runs in this endpoint.
 * ───────────────────────────────────────────────────────────────────────── */

/** A placeholder screenshot shaped like an ACF image array (url only, no ID). */
function rmd_demo_shot($alt = null) {
	if (null === $alt) {
		$alt = rmd_is_fr() ? 'Aperçu de capture' : 'Sample screenshot';
	}
	return array('ID' => 0, 'url' => RMD_URI . '/assets/admin/section-placeholder.svg', 'alt' => $alt);
}

/**
 * Example sub-field values keyed by sub-field name, per layout — in the admin
 * user's language (the editor never mixes languages, demo previews included).
 */
function rmd_section_demo($layout) {
	return rmd_is_fr() ? rmd_section_demo_fr($layout) : rmd_section_demo_en($layout);
}

/** French demo content (the Mariner story). */
function rmd_section_demo_fr($layout) {
	switch ($layout) {

		case 'hero':
			return array(
				'eyebrow'        => 'Étude de cas client · SEO',
				'kicker'         => 'Nom du client',
				'heading'        => 'Battre les géants en',
				'heading_accent' => 'première page de Google',
				'heading_after'  => '.',
				'subheading'     => 'Une phrase de contexte : le secteur, l’enjeu, et le résultat obtenu en une ligne.',
				'tags'           => array(
					array('icon' => '', 'label' => 'SEO'),
					array('icon' => '', 'label' => 'Contenu'),
					array('icon' => '', 'label' => 'Netlinking'),
					array('icon' => '', 'label' => 'SEO technique'),
				),
				'badge'          => 'Stratégie SEO · 2024 → 2026',
				'show_contact'   => true,
				'stats'          => array(
					array('value' => '#1', 'label' => 'sur votre requête cible'),
					array('value' => 'DR 25 <span class="unit">&rarr;</span> 55', 'label' => 'autorité de domaine ×2,2'),
					array('value' => '4,04M', 'label' => 'impressions Google · 13 mois'),
				),
			);

		case 'stats_band':
			return array(
				'style' => 'strip',
				'items' => array(
					array('value' => '60,4K', 'label' => 'Clics organiques (GSC)'),
					array('value' => '4,04M', 'label' => 'Impressions Google'),
					array('value' => '55', 'value_note' => '▲ vs 25', 'label' => 'Domain Rating'),
					array('value' => '157', 'label' => 'Mots-clés en Top 3'),
					array('value' => '58,9K€', 'label' => 'CA via Google organique', 'highlight' => true),
				),
			);

		case 'stat_cards':
			return array(
				'background' => 'light',
				'accent'     => 'negative',
				'eyebrow'    => 'Le contexte',
				'heading'    => 'Un marché dominé <span class="thin">par des géants</span>',
				'subheading' => 'Le point de départ : la difficulté, en une phrase.',
				'cards'      => array(
					array('icon' => '', 'value' => '#49', 'label' => 'Position au départ', 'body' => 'Sur une requête produit stratégique, le site était en <b>page 5 de Google</b>.'),
					array('icon' => '', 'value' => '25', 'label' => 'Domain Rating', 'body' => 'Une autorité de domaine <b>deux fois trop faible</b> pour rivaliser.'),
					array('icon' => '', 'value' => '4', 'label' => 'Géants en face', 'body' => 'Des concurrents aux <b>budgets marketing massifs</b> sur chaque requête.'),
				),
			);

		case 'feature_cards':
			return array(
				'eyebrow'      => 'L’insight',
				'heading'      => '« Prendre les requêtes <span class="thin">une par une »</span>',
				'subheading'   => 'L’angle stratégique qui a tout changé, expliqué simplement.',
				'cards_kicker' => 'Notre approche',
				'cards'        => array(
					array('icon' => '', 'heading' => 'Une page par requête', 'body' => 'Chaque famille de produits a sa page ciblée et optimisée.'),
					array('icon' => '', 'heading' => 'On-page & contenu', 'body' => 'Titres, maillage interne, textes qui répondent exactement à la recherche.'),
					array('icon' => '', 'heading' => 'Netlinking & autorité', 'body' => 'Des liens de qualité acquis dans la durée pour se classer plus vite.'),
				),
				'tiles_kicker' => 'Ce que ça a donné, chiffres à l’appui',
				'tiles'        => array(
					array('value' => '#49 → #1', 'label' => "requête cible\naoût 2024 → juin 2026"),
					array('value' => '157', 'label' => "mots-clés en Top 3\nsur les 250 plus porteurs"),
					array('value' => '×2,2', 'label' => "autorité de domaine\nDR 25 → 55"),
					array('value' => '99 %', 'label' => "des 250 mots-clés\nen première page"),
				),
				'highlight'    => 'Et à la clé : <b>58 884 € de CA via Google organique</b> sur 6 mois, sans publicité.',
			);

		case 'numbered_steps':
			return array(
				'background' => 'light',
				'eyebrow'    => 'Notre méthode',
				'heading'    => 'Une stratégie SEO <span class="thin">en 4 phases</span>',
				'steps'      => array(
					array('heading' => 'Audit technique & sémantique', 'items' => "Crawl, indexation, vitesse\nÉtude des mots-clés du marché\nBenchmark des concurrents\nPriorisation des requêtes"),
					array('heading' => 'On-page & pages collection', 'items' => "Une page par famille de produits\nMeta titles & descriptions\nMaillage interne\nStructure claire pour Google"),
					array('heading' => 'Contenu éditorial', 'items' => "Contenus ciblés sur les requêtes\nRéponses aux questions d’achat\nOptimisation continue\nRequêtes saisonnières"),
					array('heading' => 'Netlinking & autorité', 'items' => "Backlinks de qualité réguliers\nDomain Rating 25 → 55\nDomaines référents au pic\nSuivi mensuel des positions"),
				),
			);

		case 'screenshot_gallery':
			return array(
				'eyebrow'    => 'Résultats SEO',
				'heading'    => 'Première page <span class="thin">face aux géants</span>',
				'subheading' => 'Captures de résultats Google réels sur vos mots-clés business.',
				'columns'    => '2',
				'items'      => array(
					array('image' => rmd_demo_shot('SERP exemple'), 'style' => 'browser', 'label' => '« votre requête »', 'badge' => '#1', 'zoomable' => true),
					array('image' => rmd_demo_shot('SERP exemple'), 'style' => 'browser', 'label' => '« autre requête »', 'badge' => '#1', 'zoomable' => true),
				),
			);

		case 'table_split':
			return array(
				'background'    => 'light',
				'eyebrow'       => 'Positions Google',
				'heading'       => 'Les mots-clés <span class="thin">qui ramènent le trafic</span>',
				'subheading'    => 'Sur les 250 mots-clés les plus porteurs : 157 en Top 3.',
				'media_position' => 'none',
				'table_columns' => array(
					array('label' => 'Mot-clé', 'highlight' => false),
					array('label' => 'Volume', 'highlight' => false),
					array('label' => 'Avant', 'highlight' => false),
					array('label' => 'Après', 'highlight' => true),
				),
				'table_rows'    => array(
					array('cells' => array(array('content' => 'requête A'), array('content' => '200'), array('content' => '#49'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'requête B'), array('content' => '2 300'), array('content' => '#9'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'requête C'), array('content' => '800'), array('content' => 'n/a'), array('content' => '#7', 'is_win' => true))),
				),
				'side_stats'    => array(
					array('tag' => 'TOP 3', 'value' => '157', 'label' => 'mots-clés sur 250 en positions 1–3'),
					array('tag' => 'TOP 10', 'value' => '247', 'label' => 'soit 99 % en première page'),
				),
				'comment'       => '<b>De la page 5 à la position 1.</b> C’est le SEO qui transforme des pages en points d’entrée rentables.',
			);

		case 'line_chart':
			return array(
				'eyebrow'      => 'Autorité de domaine',
				'heading'      => 'Une autorité construite <span class="thin">dans la durée</span>',
				'chart_title'  => 'Domain Rating, juin 2024 → juin 2026',
				'chart_note'   => '(Ahrefs)',
				'value_prefix' => 'DR',
				'points'       => array(
					array('label' => 'juin 2024', 'value' => 25),
					array('label' => 'déc. 2024', 'value' => 34),
					array('label' => 'juin 2025', 'value' => 41),
					array('label' => 'déc. 2025', 'value' => 49),
					array('label' => 'juin 2026', 'value' => 55),
				),
			);

		case 'recap_band':
			return array(
				'heading' => 'Battre les géants, sans budget publicitaire.',
				'pills'   => array(
					array('text' => '<b>#1</b> sur la requête cible'),
					array('text' => 'Autorité : <b>DR 25 → 55</b>'),
					array('text' => '<b>157</b> mots-clés en Top 3'),
					array('text' => '<b>4,04M</b> impressions Google'),
				),
			);

		case 'cta':
			return array(
				'background'     => 'light',
				'eyebrow'        => 'Votre tour',
				'heading'        => 'Obtenez les',
				'heading_accent' => 'mêmes résultats',
				'heading_after'  => '.',
				'subheading'     => 'Votre marché aussi a ses géants. Parlons de votre visibilité Google.',
				'button_label'   => 'Discuter de mon projet',
				'button_url'     => '#',
				'contact_line'   => 'contact@exemple.com · +212 000-000000',
			);
	}
	return array();
}

/** English demo content — same Mariner story, EN number formatting. */
function rmd_section_demo_en($layout) {
	switch ($layout) {

		case 'hero':
			return array(
				'eyebrow'        => 'Client case study · SEO',
				'kicker'         => 'Client name',
				'heading'        => 'Beating the giants to',
				'heading_accent' => 'Google’s first page',
				'heading_after'  => '.',
				'subheading'     => 'One sentence of context: the sector, the stakes, and the result in one line.',
				'tags'           => array(
					array('icon' => '', 'label' => 'SEO'),
					array('icon' => '', 'label' => 'Content'),
					array('icon' => '', 'label' => 'Link building'),
					array('icon' => '', 'label' => 'Technical SEO'),
				),
				'badge'          => 'SEO strategy · 2024 → 2026',
				'show_contact'   => true,
				'stats'          => array(
					array('value' => '#1', 'label' => 'on your target query'),
					array('value' => 'DR 25 <span class="unit">&rarr;</span> 55', 'label' => 'domain authority ×2.2'),
					array('value' => '4.04M', 'label' => 'Google impressions · 13 months'),
				),
			);

		case 'stats_band':
			return array(
				'style' => 'strip',
				'items' => array(
					array('value' => '60.4K', 'label' => 'Organic clicks (GSC)'),
					array('value' => '4.04M', 'label' => 'Google impressions'),
					array('value' => '55', 'value_note' => '▲ vs 25', 'label' => 'Domain Rating'),
					array('value' => '157', 'label' => 'Keywords in the Top 3'),
					array('value' => '€58.9K', 'label' => 'Revenue from organic Google', 'highlight' => true),
				),
			);

		case 'stat_cards':
			return array(
				'background' => 'light',
				'accent'     => 'negative',
				'eyebrow'    => 'The context',
				'heading'    => 'A market dominated <span class="thin">by giants</span>',
				'subheading' => 'The starting point: the difficulty, in one sentence.',
				'cards'      => array(
					array('icon' => '', 'value' => '#49', 'label' => 'Starting position', 'body' => 'On a strategic product query, the site sat on <b>page 5 of Google</b>.'),
					array('icon' => '', 'value' => '25', 'label' => 'Domain Rating', 'body' => 'A domain authority <b>half of what it takes</b> to compete.'),
					array('icon' => '', 'value' => '4', 'label' => 'Giants to face', 'body' => 'Competitors with <b>massive marketing budgets</b> on every query.'),
				),
			);

		case 'feature_cards':
			return array(
				'eyebrow'      => 'The insight',
				'heading'      => '“Take the queries <span class="thin">one by one”</span>',
				'subheading'   => 'The strategic angle that changed everything, explained simply.',
				'cards_kicker' => 'Our approach',
				'cards'        => array(
					array('icon' => '', 'heading' => 'One page per query', 'body' => 'Each product family gets its own targeted, optimised page.'),
					array('icon' => '', 'heading' => 'On-page & content', 'body' => 'Titles, internal linking, copy that answers the exact search.'),
					array('icon' => '', 'heading' => 'Link building & authority', 'body' => 'Quality links earned over time to rank faster.'),
				),
				'tiles_kicker' => 'What it delivered, numbers in hand',
				'tiles'        => array(
					array('value' => '#49 → #1', 'label' => "target query\nAug 2024 → Jun 2026"),
					array('value' => '157', 'label' => "keywords in the Top 3\nof the 250 highest-value"),
					array('value' => '×2.2', 'label' => "domain authority\nDR 25 → 55"),
					array('value' => '99%', 'label' => "of the 250 keywords\non page one"),
				),
				'highlight'    => 'And the payoff: <b>€58,884 of revenue from organic Google</b> in 6 months, without ads.',
			);

		case 'numbered_steps':
			return array(
				'background' => 'light',
				'eyebrow'    => 'Our method',
				'heading'    => 'An SEO strategy <span class="thin">in 4 phases</span>',
				'steps'      => array(
					array('heading' => 'Technical & semantic audit', 'items' => "Crawl, indexing, speed\nMarket keyword research\nCompetitor benchmark\nQuery prioritisation"),
					array('heading' => 'On-page & collection pages', 'items' => "One page per product family\nMeta titles & descriptions\nInternal linking\nA clear structure for Google"),
					array('heading' => 'Editorial content', 'items' => "Content targeted at the queries\nAnswers to buying questions\nContinuous optimisation\nSeasonal queries"),
					array('heading' => 'Link building & authority', 'items' => "Regular quality backlinks\nDomain Rating 25 → 55\nReferring domains at their peak\nMonthly position tracking"),
				),
			);

		case 'screenshot_gallery':
			return array(
				'eyebrow'    => 'SEO results',
				'heading'    => 'Page one <span class="thin">against the giants</span>',
				'subheading' => 'Screenshots of real Google results on your business keywords.',
				'columns'    => '2',
				'items'      => array(
					array('image' => rmd_demo_shot('Sample SERP'), 'style' => 'browser', 'label' => '“your query”', 'badge' => '#1', 'zoomable' => true),
					array('image' => rmd_demo_shot('Sample SERP'), 'style' => 'browser', 'label' => '“another query”', 'badge' => '#1', 'zoomable' => true),
				),
			);

		case 'table_split':
			return array(
				'background'    => 'light',
				'eyebrow'       => 'Google positions',
				'heading'       => 'The keywords <span class="thin">that bring the traffic</span>',
				'subheading'    => 'Of the 250 highest-value keywords: 157 in the Top 3.',
				'media_position' => 'none',
				'table_columns' => array(
					array('label' => 'Keyword', 'highlight' => false),
					array('label' => 'Volume', 'highlight' => false),
					array('label' => 'Before', 'highlight' => false),
					array('label' => 'After', 'highlight' => true),
				),
				'table_rows'    => array(
					array('cells' => array(array('content' => 'query A'), array('content' => '200'), array('content' => '#49'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'query B'), array('content' => '2,300'), array('content' => '#9'), array('content' => '#1', 'is_win' => true))),
					array('cells' => array(array('content' => 'query C'), array('content' => '800'), array('content' => 'n/a'), array('content' => '#7', 'is_win' => true))),
				),
				'side_stats'    => array(
					array('tag' => 'TOP 3', 'value' => '157', 'label' => 'keywords out of 250 in positions 1–3'),
					array('tag' => 'TOP 10', 'value' => '247', 'label' => 'i.e. 99% on page one'),
				),
				'comment'       => '<b>From page 5 to position 1.</b> That’s SEO turning pages into profitable entry points.',
			);

		case 'line_chart':
			return array(
				'eyebrow'      => 'Domain authority',
				'heading'      => 'Authority built <span class="thin">over time</span>',
				'chart_title'  => 'Domain Rating, June 2024 → June 2026',
				'chart_note'   => '(Ahrefs)',
				'value_prefix' => 'DR',
				'points'       => array(
					array('label' => 'June 2024', 'value' => 25),
					array('label' => 'Dec 2024', 'value' => 34),
					array('label' => 'June 2025', 'value' => 41),
					array('label' => 'Dec 2025', 'value' => 49),
					array('label' => 'June 2026', 'value' => 55),
				),
			);

		case 'recap_band':
			return array(
				'heading' => 'Beating the giants, without an ad budget.',
				'pills'   => array(
					array('text' => '<b>#1</b> on the target query'),
					array('text' => 'Authority: <b>DR 25 → 55</b>'),
					array('text' => '<b>157</b> keywords in the Top 3'),
					array('text' => '<b>4.04M</b> Google impressions'),
				),
			);

		case 'cta':
			return array(
				'background'     => 'light',
				'eyebrow'        => 'Your turn',
				'heading'        => 'Get the',
				'heading_accent' => 'same results',
				'heading_after'  => '.',
				'subheading'     => 'Your market has its giants too. Let’s talk about your Google visibility.',
				'button_label'   => 'Discuss my project',
				'button_url'     => '#',
				'contact_line'   => 'contact@example.com · +212 000-000000',
			);
	}
	return array();
}

/* ─────────────────────────────────────────────────────────────────────────
 * 3. Rendering — one saved row, or a demo, into a standalone document.
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Render an existing `sections` row with its SAVED values. Walks the flexible
 * rows to $row_index and renders inside that row's ACF context so the layout's
 * sub-field reads resolve. Guards get_row_layout() === $layout so a reordered-
 * but-unsaved field renders nothing (→ caller shows "save first") instead of the
 * wrong section. Returns '' when the row can't be resolved.
 */
function rmd_render_saved_section_row($post_id, $row_index, $layout) {
	if (!$post_id || !defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return '';
	}

	$html  = '';
	$index = 0;

	if (rmd_have_rows('sections', $post_id)) {
		while (rmd_have_rows('sections', $post_id)) {
			rmd_the_row();
			if ($index === $row_index) {
				if (rmd_get_row_layout() === $layout) {
					ob_start();
					// Pass the real row index so the preview's eager/lazy loading
					// matches the live page (a section-0 preview loads eager).
					get_template_part('template-parts/layouts/' . $layout, null, array('index' => $row_index));
					$html = trim(ob_get_clean());
				}
				break;
			}
			$index++;
		}
	}

	if (function_exists('reset_rows')) {
		reset_rows();
	}
	return $html;
}

/**
 * AJAX (logged-in only): output a standalone HTML document rendering one section
 * for the preview iframe.
 */
function rmd_render_section_preview() {
	check_ajax_referer('rmd_section_preview');

	$layout    = isset($_GET['layout']) ? sanitize_key(wp_unslash($_GET['layout'])) : '';
	$post_id   = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
	$row_index = isset($_GET['row']) && $_GET['row'] !== '' ? (int) $_GET['row'] : -1;

	$allowed = $post_id ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
	if (!$allowed) {
		status_header(403);
		wp_die(esc_html(rmd_is_fr() ? 'Accès refusé.' : 'Access denied.'), '', array('response' => 403));
	}

	// Allowlist — the template path is never built from raw input.
	$layouts = rmd_preview_layouts();
	if (!isset($layouts[$layout])) {
		status_header(400);
		wp_die(esc_html(rmd_is_fr() ? 'Section inconnue.' : 'Unknown section.'), '', array('response' => 400));
	}

	// Post context: hero/cta read per-site contact options; some may use the ID.
	if ($post_id) {
		$preview_post = get_post($post_id);
		if ($preview_post) {
			global $post;
			$post = $preview_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata($post);
		}
	}

	if ($row_index >= 0) {
		$section_html = rmd_render_saved_section_row($post_id, $row_index, $layout);
	} else {
		// Demo: no row exists yet — seed example content through the sub-field
		// wrapper so the real template renders a filled example.
		$GLOBALS['rmd_demo'] = rmd_section_demo($layout);
		ob_start();
		get_template_part('template-parts/layouts/' . $layout, null, array('index' => 0));
		$section_html = trim(ob_get_clean());
		unset($GLOBALS['rmd_demo']);
	}

	wp_reset_postdata();

	// A layout that renders nothing still emits whitespace; probe for real content.
	$probe = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', '', (string) $section_html);
	$probe = preg_replace('#<!--.*?-->#s', '', (string) $probe);
	$probe = trim(strip_tags((string) $probe, '<img><svg><iframe><input><button><video>'));
	$has_visible = ('' !== $probe);

	$main_css = RMD_DIR . '/assets/css/main.css';
	$main_js  = RMD_DIR . '/assets/js/main.js';
	$css_ver  = file_exists($main_css) ? filemtime($main_css) : RMD_VERSION;
	$js_ver   = file_exists($main_js) ? filemtime($main_js) : RMD_VERSION;

	nocache_headers();
	header('Content-Type: text/html; charset=utf-8');
	header('X-Frame-Options: SAMEORIGIN');
	header('X-Robots-Tag: noindex, nofollow');
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
	<?php if (file_exists($main_css)) : ?>
	<link rel="stylesheet" href="<?php echo esc_url(RMD_URI . '/assets/css/main.css?ver=' . $css_ver); ?>">
	<?php endif; ?>
	<style>
		html, body { margin: 0; padding: 0; background: #fff; }
		/* An isolated section has nothing above it; cancel a leading negative top
		   margin so it can't render off the top of the frame. The 1px padding also
		   blocks a child's top margin from collapsing through <body> (from main). */
		body > * > *:first-child { margin-top: 0 !important; }
		body { padding-top: 1px; }
		/* Nothing in a preview should navigate. */
		a { pointer-events: none; }
		.rmd-preview-empty { font-family: Poppins, system-ui, sans-serif; color: #596980; font-size: 14px; text-align: center; padding: 80px 24px; line-height: 1.6; }
		.rmd-preview-empty strong { display: block; color: #041135; margin-bottom: 6px; font-size: 15px; }
	</style>
</head>
<body>
<?php $fr = rmd_is_fr(); ?>
<?php if ($has_visible) : ?>
	<main class="rmd-case"><?php echo $section_html; // phpcs:ignore WordPress.Security.EscapeOutput — theme template output ?></main>
<?php elseif ($row_index >= 0) : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html($fr ? 'Section non encore enregistrée' : 'Section not saved yet'); ?></strong>
		<?php echo esc_html($fr ? 'L’aperçu affiche le contenu enregistré. Cliquez sur « Mettre à jour » puis rouvrez l’aperçu.' : 'The preview shows saved content. Click "Update", then reopen the preview.'); ?>
	</div>
<?php else : ?>
	<div class="rmd-preview-empty">
		<strong><?php echo esc_html($fr ? 'Rien à afficher' : 'Nothing to show'); ?></strong>
		<?php echo esc_html($fr ? 'Cette section n’affiche du contenu qu’une fois ses champs remplis.' : 'This section only shows content once its fields are filled.'); ?>
	</div>
<?php endif; ?>
	<?php if (file_exists($main_js)) : ?>
	<script src="<?php echo esc_url(RMD_URI . '/assets/js/main.js?ver=' . $js_ver); ?>" defer></script>
	<?php endif; ?>
</body>
</html>
	<?php
	exit;
}
add_action('wp_ajax_rmd_section_preview', 'rmd_render_section_preview');

/* ─────────────────────────────────────────────────────────────────────────
 * 4. Enqueue the preview UI — post.php / post-new.php only, ACF active.
 * ───────────────────────────────────────────────────────────────────────── */
/**
 * Calm first paint: ACF renders every flexible-content row EXPANDED and only
 * collapses the saved ones once all JS has loaded — on a heavy edit screen the
 * fields visibly flash open then snap shut. This class hides row bodies from
 * the first frame (CSS in section-preview.css); acf-collapse-guard.js lifts it
 * right after ACF restores the real state, with a timer failsafe.
 */
add_filter('admin_body_class', 'rmd_precollapse_body_class');
function rmd_precollapse_body_class($classes) {
	global $pagenow;
	if (('post.php' === $pagenow || 'post-new.php' === $pagenow)
		&& defined('RMD_ACF_ACTIVE') && RMD_ACF_ACTIVE) {
		$classes .= ' rmd-precollapse';
	}
	return $classes;
}

function rmd_section_preview_assets($hook) {
	if ('post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}
	if (!defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return;
	}

	$css   = RMD_DIR . '/assets/admin/section-preview.css';
	$js    = RMD_DIR . '/assets/admin/section-preview.js';
	$guard = RMD_DIR . '/assets/admin/acf-collapse-guard.js';

	wp_enqueue_style('rmd-section-preview', RMD_URI . '/assets/admin/section-preview.css', array('dashicons'), file_exists($css) ? filemtime($css) : RMD_VERSION);
	wp_enqueue_script('rmd-section-preview', RMD_URI . '/assets/admin/section-preview.js', array(), file_exists($js) ? filemtime($js) : RMD_VERSION, true);

	// Keeps ACF's row collapse/expand from crashing if another plugin's JS error
	// interrupts ACF init (see assets/admin/acf-collapse-guard.js). Standalone and
	// dependency-free so a bug in the preview script can't take the guard down.
	wp_enqueue_script('rmd-acf-collapse-guard', RMD_URI . '/assets/admin/acf-collapse-guard.js', array(), file_exists($guard) ? filemtime($guard) : RMD_VERSION, true);

	$preview_post_id = get_the_ID();
	if (!$preview_post_id && isset($_GET['post'])) {
		$preview_post_id = absint($_GET['post']);
	}

	// Editor-UI strings follow the admin user's language (rmd_is_fr pattern,
	// like the section names) — not the site locale and no .mo files.
	$fr = rmd_is_fr();

	wp_localize_script('rmd-section-preview', 'rmdSectionPreview', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('rmd_section_preview'),
		'postId'  => $preview_post_id ? (int) $preview_post_id : 0,
		'layouts' => rmd_preview_layouts(),
		'i18n'    => $fr ? array(
			'previewTitle' => 'Aperçu de la section',
			'insert'       => 'Insérer',
			'close'        => 'Fermer',
			'refresh'      => 'Rafraîchir',
			'loading'      => 'Chargement de l’aperçu…',
			'error'        => 'Impossible de charger l’aperçu.',
			'demoNotice'   => 'Aperçu de démonstration — le contenu réel dépendra de vos réglages.',
			'rowPreview'   => 'Aperçu de cette section avec son contenu enregistré.',
			'dirtyHint'    => 'Modifications non enregistrées : l’aperçu montre la dernière version enregistrée. Cliquez sur « Mettre à jour ».',
			'newRowHint'   => 'Cette section n’a jamais été enregistrée. Cliquez sur « Mettre à jour » puis rouvrez l’aperçu.',
			'addSection'   => 'Ajouter une section',
		) : array(
			'previewTitle' => 'Section preview',
			'insert'       => 'Insert',
			'close'        => 'Close',
			'refresh'      => 'Refresh',
			'loading'      => 'Loading the preview…',
			'error'        => 'Could not load the preview.',
			'demoNotice'   => 'Demo preview — the real content will depend on your settings.',
			'rowPreview'   => 'Preview of this section with its saved content.',
			'dirtyHint'    => 'Unsaved changes: the preview shows the last saved version. Click "Update".',
			'newRowHint'   => 'This section has never been saved. Click "Update", then reopen the preview.',
			'addSection'   => 'Add a section',
		),
	));
}
add_action('admin_enqueue_scripts', 'rmd_section_preview_assets');

/* ─────────────────────────────────────────────────────────────────────────
 * 5. Duplicate-section warning (AMD §7.2) — non-blocking, dismissible.
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_section_duplicate_notice() {
	// layout name => variant field key: two rows of the same layout only count
	// as duplicates when their variant matches (red cards vs green cards are
	// DIFFERENT sections, not a repeat).
	$rmd_variants = array();
	foreach (rmd_section_variant_fields() as $rmd_layout_name => $rmd_variant) {
		$rmd_variants[$rmd_layout_name] = $rmd_variant['key'];
	}
	$rmd_dup_i18n = rmd_is_fr() ? array(
		'text'    => '⚠ La section « %s » est déjà utilisée sur cette page avec le même style. Vous pouvez continuer, mais vérifiez que c’est intentionnel.',
		'dismiss' => 'Ignorer',
	) : array(
		'text'    => '⚠ The “%s” section is already used on this page with the same style. You can continue, but make sure it’s intentional.',
		'dismiss' => 'Dismiss',
	);
	?>
	<script>
	(function ($) {
		if (typeof acf === 'undefined') return;

		var variants = <?php echo wp_json_encode($rmd_variants); ?>;
		var dupI18n = <?php echo wp_json_encode($rmd_dup_i18n); ?>;

		/** The row's variant value ('' when the layout has no variant field). */
		function variantOf($row) {
			var fkey = variants[$row.attr('data-layout')];
			if (!fkey) return '';
			var $f = $row.find('.acf-field[data-key="' + fkey + '"]').first();
			if (!$f.length) return '';
			var v = $f.find('select').first().val();
			if (v == null) v = $f.find('input:checked').first().val();
			return v == null ? '' : String(v);
		}

		function scan(field) {
			var $rows = field.$el.find('.acf-flexible-content .values > .layout')
				.not('[data-id="acfcloneindex"]').not('.acf-clone');

			field.$el.find('.rmd-dup-note').remove();

			var seen = {};
			$rows.each(function () {
				var $row = $(this);
				var name = $row.attr('data-layout');
				if (!name) return;
				var sig = name + '|' + variantOf($row);
				if (!seen[sig]) { seen[sig] = true; return; }
				if ($row.attr('data-rmd-dup-dismissed') === '1') return;

				var label = $row.attr('data-label') || name;
				var $note = $('<div class="rmd-dup-note" style="display:flex;align-items:flex-start;gap:10px;margin:8px 12px;padding:9px 12px;border-radius:6px;background:#fef3c7;border:1px solid #fde68a;color:#92400e;font-size:12.5px;font-weight:600;line-height:1.5;">' +
					'<span style="flex:1;"></span>' +
					'<button type="button" class="rmd-dup-dismiss" aria-label="' + dupI18n.dismiss + '" style="border:0;background:none;color:#92400e;cursor:pointer;font-size:15px;line-height:1;padding:0 2px;">&times;</button>' +
					'</div>');
				$note.children('span').text(dupI18n.text.replace('%s', label));

				$note.find('.rmd-dup-dismiss').on('click', function () {
					$row.attr('data-rmd-dup-dismissed', '1');
					$note.remove();
				});

				var $handle = $row.children('.acf-fc-layout-handle');
				if ($handle.length) { $handle.after($note); } else { $row.prepend($note); }
			});
		}

		function bind(field) {
			field.on('change', function () { scan(field); });
			scan(field);
		}

		acf.addAction('ready_field/key=field_rmd_cs_sections', bind);
		acf.addAction('append_field/key=field_rmd_cs_sections', bind);
	})(jQuery);
	</script>
	<?php
}
add_action('acf/input/admin_footer', 'rmd_section_duplicate_notice');

/* ─────────────────────────────────────────────────────────────────────────
 * 6. Field hints — placeholders/instructions injected at runtime.
 *    Fills ONLY empty hints (never overrides what the JSON already carries), so
 *    the JSON stays the source of truth and this just enriches the editor UX
 *    without a regenerate. Keyed by the field keys in acf-json (field_rmd_cs_*).
 * ───────────────────────────────────────────────────────────────────────── */
function rmd_field_hints() {
	// key => array('placeholder' => …, 'instructions' => …)
	$hints = array(
		// The sections field itself — ownership note (spec §11.1).
		'field_rmd_cs_sections' => array('instructions' => 'Composez la page en ajoutant des sections. Ces sections appartiennent à cette étude de cas uniquement — les modifier ici n’affecte aucune autre page.'),
		// hero
		'field_rmd_cs_hero_kicker'          => array('placeholder' => 'Nom du client (affiché en rouge)'),
		'field_rmd_cs_hero_heading'         => array('placeholder' => 'Battre les géants en'),
		'field_rmd_cs_hero_heading_accent'  => array('placeholder' => 'première page de Google', 'instructions' => 'Partie du titre en dégradé (rouge → orange).'),
		'field_rmd_cs_hero_subheading'      => array('placeholder' => 'Le secteur, l’enjeu et le résultat, en une phrase.'),
		'field_rmd_cs_hero_stats_value'     => array('placeholder' => '#1', 'instructions' => 'HTML léger : <span class="unit">→</span> pour une flèche verte.'),
		'field_rmd_cs_hero_stats_label'     => array('placeholder' => 'sur « votre requête cible »'),
		// stats_band
		'field_rmd_cs_stats_band_items_value'      => array('placeholder' => '4,04M'),
		'field_rmd_cs_stats_band_items_label'      => array('placeholder' => 'Impressions Google (GSC)'),
		// stat_cards
		'field_rmd_cs_stat_cards_cards_value' => array('placeholder' => '#49'),
		'field_rmd_cs_stat_cards_cards_label' => array('placeholder' => 'POSITION AU DÉPART'),
		'field_rmd_cs_stat_cards_cards_body'  => array('placeholder' => 'Une phrase de contexte sur ce chiffre.'),
		// feature_cards
		'field_rmd_cs_feature_cards_cards_heading' => array('placeholder' => 'Une page par requête'),
		'field_rmd_cs_feature_cards_tiles_value'   => array('placeholder' => '#49 → #1'),
		'field_rmd_cs_feature_cards_highlight'     => array('placeholder' => 'Le résultat business en une phrase (bandeau vert).'),
		// numbered_steps
		'field_rmd_cs_numbered_steps_steps_heading' => array('placeholder' => 'Audit technique & sémantique'),
		'field_rmd_cs_numbered_steps_steps_items'   => array('instructions' => 'Une puce par ligne.'),
		// screenshot_gallery
		'field_rmd_cs_screenshot_gallery_items_image'   => array('instructions' => 'Capture PNG/JPG — SERP, Search Console, Ahrefs, Semrush…'),
		'field_rmd_cs_screenshot_gallery_items_label'   => array('placeholder' => '« votre requête »'),
		'field_rmd_cs_screenshot_gallery_items_caption' => array('placeholder' => 'Ce que montre la capture, en une ligne.'),
		// table_split
		'field_rmd_cs_table_split_table_columns_label'   => array('placeholder' => 'Mot-clé'),
		'field_rmd_cs_table_split_table_rows_cells_content' => array('placeholder' => '#1'),
		'field_rmd_cs_table_split_comment'               => array('placeholder' => 'Le commentaire qui interprète le tableau.'),
		'field_rmd_cs_table_split_media_image'           => array('instructions' => 'Capture affichée à gauche du tableau.'),
		// line_chart
		'field_rmd_cs_line_chart_points_label' => array('placeholder' => 'juin 2024'),
		'field_rmd_cs_line_chart_points_value' => array('placeholder' => '25'),
		// recap_band
		'field_rmd_cs_recap_band_heading'    => array('placeholder' => 'La promesse tenue, en une phrase.'),
		'field_rmd_cs_recap_band_pills_text' => array('placeholder' => '<b>#1</b> sur la requête cible'),
		// cta
		'field_rmd_cs_cta_heading'        => array('placeholder' => 'Obtenez les'),
		'field_rmd_cs_cta_heading_accent' => array('placeholder' => 'mêmes résultats'),
		'field_rmd_cs_cta_subheading'     => array('placeholder' => 'Une phrase qui invite à la prise de contact.'),
	);

	if (rmd_is_fr()) {
		return $hints;
	}

	// English admin: EVERY hint in English, example content included — the
	// workspace must never mix languages (owner's call). Numbers keep EN
	// formatting ('4.04M', '2,300').
	return array(
		'field_rmd_cs_sections' => array('instructions' => 'Compose the page by adding sections. These sections belong to this case study only — editing them here affects no other page.'),
		// hero
		'field_rmd_cs_hero_kicker'          => array('placeholder' => 'Client name (shown in red)'),
		'field_rmd_cs_hero_heading'         => array('placeholder' => 'Beating the giants to'),
		'field_rmd_cs_hero_heading_accent'  => array('placeholder' => 'Google’s first page', 'instructions' => 'The gradient part of the heading (red → orange).'),
		'field_rmd_cs_hero_subheading'      => array('placeholder' => 'The sector, the stakes and the result, in one sentence.'),
		'field_rmd_cs_hero_stats_value'     => array('placeholder' => '#1', 'instructions' => 'Light HTML: <span class="unit">→</span> for a green arrow.'),
		'field_rmd_cs_hero_stats_label'     => array('placeholder' => 'on “your target query”'),
		// stats_band
		'field_rmd_cs_stats_band_items_value'      => array('placeholder' => '4.04M'),
		'field_rmd_cs_stats_band_items_label'      => array('placeholder' => 'Google impressions (GSC)'),
		// stat_cards
		'field_rmd_cs_stat_cards_cards_value' => array('placeholder' => '#49'),
		'field_rmd_cs_stat_cards_cards_label' => array('placeholder' => 'STARTING POSITION'),
		'field_rmd_cs_stat_cards_cards_body'  => array('placeholder' => 'One sentence of context for this number.'),
		// feature_cards
		'field_rmd_cs_feature_cards_cards_heading' => array('placeholder' => 'One page per query'),
		'field_rmd_cs_feature_cards_tiles_value'   => array('placeholder' => '#49 → #1'),
		'field_rmd_cs_feature_cards_highlight'     => array('placeholder' => 'The business result in one sentence (green band).'),
		// numbered_steps
		'field_rmd_cs_numbered_steps_steps_heading' => array('placeholder' => 'Technical & semantic audit'),
		'field_rmd_cs_numbered_steps_steps_items'   => array('instructions' => 'One bullet per line.'),
		// screenshot_gallery
		'field_rmd_cs_screenshot_gallery_items_image'   => array('instructions' => 'PNG/JPG screenshot — SERP, Search Console, Ahrefs, Semrush…'),
		'field_rmd_cs_screenshot_gallery_items_label'   => array('placeholder' => '“your query”'),
		'field_rmd_cs_screenshot_gallery_items_caption' => array('placeholder' => 'What the screenshot shows, in one line.'),
		// table_split
		'field_rmd_cs_table_split_table_columns_label'      => array('placeholder' => 'Keyword'),
		'field_rmd_cs_table_split_table_rows_cells_content' => array('placeholder' => '#1'),
		'field_rmd_cs_table_split_comment'                  => array('placeholder' => 'The comment that interprets the table.'),
		'field_rmd_cs_table_split_media_image'              => array('instructions' => 'The screenshot shown to the left of the table.'),
		// line_chart
		'field_rmd_cs_line_chart_points_label' => array('placeholder' => 'June 2024'),
		'field_rmd_cs_line_chart_points_value' => array('placeholder' => '25'),
		// recap_band
		'field_rmd_cs_recap_band_heading'    => array('placeholder' => 'The promise kept, in one sentence.'),
		'field_rmd_cs_recap_band_pills_text' => array('placeholder' => '<b>#1</b> on the target query'),
		// cta
		'field_rmd_cs_cta_heading'        => array('placeholder' => 'Get the'),
		'field_rmd_cs_cta_heading_accent' => array('placeholder' => 'same results'),
		'field_rmd_cs_cta_subheading'     => array('placeholder' => 'One sentence inviting the reader to get in touch.'),
	);
}

add_filter('acf/load_field', function ($field) {
	// Placeholders/instructions are editor-only — skip the work on the front end.
	if (!is_admin() || empty($field['key'])) {
		return $field;
	}
	// acf/load_field fires for every field on every load — cache the map once.
	static $hints = null;
	if (null === $hints) {
		$hints = rmd_field_hints();
	}
	if (!isset($hints[$field['key']])) {
		return $field;
	}
	$hint = $hints[$field['key']];
	// Fill only what the JSON left empty — never override authored hints.
	if (!empty($hint['placeholder']) && empty($field['placeholder'])) {
		$field['placeholder'] = $hint['placeholder'];
	}
	if (!empty($hint['instructions']) && empty($field['instructions'])) {
		$field['instructions'] = $hint['instructions'];
	}
	return $field;
});
