<?php
/**
 * Generator for acf-json/group_rmd_case_study_sections.json
 * Run once: php gen-acf.php "<output path>"
 * Keys: group_rmd_* / field_rmd_cs_<layout>_<name> / layout_rmd_cs_<name>
 */

// CLI-only tool (and excluded from deploy). Never executable over HTTP.
if (PHP_SAPI !== 'cli') {
	exit;
}

function f_base($layout, $name, $label, $type, $extra = array()) {
	return array_merge(array(
		'key'               => "field_rmd_cs_{$layout}_{$name}",
		'label'             => $label,
		'name'              => $name,
		'type'              => $type,
		'instructions'      => '',
		'required'          => 0,
		'conditional_logic' => 0,
		'wrapper'           => array('width' => '', 'class' => '', 'id' => ''),
	), $extra);
}
function f_text($layout, $name, $label, $extra = array()) {
	return f_base($layout, $name, $label, 'text', array_merge(array('default_value' => '', 'placeholder' => ''), $extra));
}
function f_textarea($layout, $name, $label, $rows = 3, $extra = array()) {
	return f_base($layout, $name, $label, 'textarea', array_merge(array('default_value' => '', 'rows' => $rows, 'new_lines' => ''), $extra));
}
function f_select($layout, $name, $label, $choices, $default, $extra = array()) {
	return f_base($layout, $name, $label, 'select', array_merge(array(
		'choices' => $choices, 'default_value' => $default, 'return_format' => 'value',
		'multiple' => 0, 'allow_null' => 0, 'ui' => 0,
	), $extra));
}
function f_truefalse($layout, $name, $label, $default = 0, $extra = array()) {
	return f_base($layout, $name, $label, 'true_false', array_merge(array('ui' => 1, 'default_value' => $default, 'message' => ''), $extra));
}
function f_number($layout, $name, $label, $default = '', $extra = array()) {
	return f_base($layout, $name, $label, 'number', array_merge(array('default_value' => $default, 'min' => '', 'max' => '', 'step' => ''), $extra));
}
function f_image($layout, $name, $label, $extra = array()) {
	return f_base($layout, $name, $label, 'image', array_merge(array(
		'return_format' => 'array', 'preview_size' => 'medium', 'library' => 'all',
	), $extra));
}
function f_repeater($layout, $name, $label, $sub_fields, $rlayout = 'block', $button = 'Ajouter une ligne', $extra = array()) {
	$collapsed = isset($sub_fields[0]['key']) ? $sub_fields[0]['key'] : '';
	return f_base($layout, $name, $label, 'repeater', array_merge(array(
		'layout' => $rlayout, 'min' => 0, 'max' => 0, 'collapsed' => $collapsed,
		'button_label' => $button, 'sub_fields' => $sub_fields,
	), $extra));
}
function f_svg($layout, $name, $label = 'Icône (SVG)') {
	return f_textarea($layout, $name, $label, 2, array('instructions' => 'Coller le code SVG inline (icône trait).'));
}
/** Shared header trio (eyebrow / heading / subheading) — repeated per layout, unique keys. */
function header_trio($layout) {
	return array(
		f_text($layout, 'eyebrow', 'Eyebrow'),
		f_text($layout, 'heading', 'Titre (H2)', array('instructions' => 'HTML léger autorisé : <span class="thin">…</span>, <b>.')),
		f_textarea($layout, 'subheading', 'Sous-titre', 3, array('instructions' => 'HTML léger autorisé : <b>, <span class="…">.')),
	);
}
/** Shared section settings — repeated per layout, unique keys. */
function settings_trio($layout, $bg_default = 'white') {
	return array(
		f_select($layout, 'background', 'Fond', array('white' => 'Blanc', 'light' => 'Gris clair'), $bg_default),
		f_text($layout, 'anchor', 'Ancre (id)', array('instructions' => 'Ex. resultats, methode, contact — pour les liens #ancre.')),
		f_select($layout, 'padding_top', 'Espacement haut', array('default' => 'Normal', 'flush' => 'Collé à la section précédente'), 'default'),
	);
}
function layout_def($name, $label, $sub_fields) {
	return array(
		'key'        => "layout_rmd_cs_{$name}",
		'name'       => $name,
		'label'      => $label,
		'display'    => 'block',
		'min'        => '',
		'max'        => '',
		'sub_fields' => $sub_fields,
	);
}

$layouts = array();

/* ── hero ─────────────────────────────────────────────── */
$layouts['layout_rmd_cs_hero'] = layout_def('hero', 'Hero', array_merge(
	array(
		f_text('hero', 'eyebrow', 'Eyebrow', array('default_value' => 'Étude de cas client · SEO')),
		f_text('hero', 'kicker', 'Nom du client (rouge)'),
		f_text('hero', 'heading', 'Titre — début'),
		f_text('hero', 'heading_accent', 'Titre — partie dégradée'),
		f_text('hero', 'heading_after', 'Titre — fin', array('default_value' => '.')),
		f_textarea('hero', 'subheading', 'Sous-titre', 3),
		f_repeater('hero', 'tags', 'Tags (chips)', array(
			f_svg('hero_tags', 'icon'),
			f_text('hero_tags', 'label', 'Libellé'),
		), 'table', 'Ajouter un tag'),
		f_text('hero', 'badge', 'Badge rouge', array('placeholder' => 'Stratégie SEO · 2024 → 2026')),
		f_truefalse('hero', 'show_contact', 'Afficher la ligne contact', 1, array('instructions' => 'Email / téléphone / site depuis Réglages du site.')),
		f_repeater('hero', 'stats', 'Carte stats (bloc navy)', array(
			f_text('hero_stats', 'value', 'Valeur', array('instructions' => 'HTML léger autorisé : <span class="unit">→</span>.')),
			f_text('hero_stats', 'label', 'Libellé'),
		), 'block', 'Ajouter une stat', array('max' => 4)),
	)
));

/* ── stats_band ───────────────────────────────────────── */
$layouts['layout_rmd_cs_stats_band'] = layout_def('stats_band', 'Bandeau de stats (navy)', array_merge(
	array(f_select('stats_band', 'style', 'Style', array('strip' => 'Bandeau pleine largeur (KPI)', 'card' => 'Carte arrondie (statduo)'), 'strip')),
	header_trio('stats_band'),
	array(f_repeater('stats_band', 'items', 'Stats', array(
		f_text('stats_band_items', 'tag', 'Mini-tag vert', array('placeholder' => 'AUTORITÉ')),
		f_text('stats_band_items', 'value', 'Valeur'),
		f_text('stats_band_items', 'value_note', 'Note verte à côté', array('placeholder' => '▲ vs 25')),
		f_text('stats_band_items', 'label', 'Libellé / delta'),
		f_truefalse('stats_band_items', 'highlight', 'Valeur en vert', 0),
	), 'block', 'Ajouter une stat', array('min' => 1, 'max' => 6))),
	settings_trio('stats_band')
));

/* ── stat_cards ───────────────────────────────────────── */
$layouts['layout_rmd_cs_stat_cards'] = layout_def('stat_cards', 'Cartes chiffres', array_merge(
	header_trio('stat_cards'),
	array(
		f_select('stat_cards', 'accent', 'Couleur des chiffres', array('negative' => 'Rouge (contexte/problème)', 'positive' => 'Vert (résultat)'), 'negative'),
		f_repeater('stat_cards', 'cards', 'Cartes', array(
			f_svg('stat_cards_cards', 'icon'),
			f_text('stat_cards_cards', 'value', 'Chiffre'),
			f_text('stat_cards_cards', 'label', 'Kicker (majuscules)'),
			f_textarea('stat_cards_cards', 'body', 'Texte', 3, array('instructions' => 'HTML léger autorisé : <b>.')),
		), 'block', 'Ajouter une carte', array('min' => 1, 'max' => 4)),
	),
	settings_trio('stat_cards', 'light')
));

/* ── feature_cards ────────────────────────────────────── */
$layouts['layout_rmd_cs_feature_cards'] = layout_def('feature_cards', 'Insight (cartes + tuiles + preuve)', array_merge(
	header_trio('feature_cards'),
	array(
		f_text('feature_cards', 'cards_kicker', 'Kicker des cartes', array('default_value' => 'Notre approche')),
		f_repeater('feature_cards', 'cards', 'Cartes approche', array(
			f_svg('feature_cards_cards', 'icon'),
			f_text('feature_cards_cards', 'heading', 'Titre'),
			f_textarea('feature_cards_cards', 'body', 'Texte', 3),
		), 'block', 'Ajouter une carte', array('max' => 4)),
		f_text('feature_cards', 'tiles_kicker', 'Kicker des tuiles', array('placeholder' => "Ce que ça a donné, chiffres à l'appui")),
		f_repeater('feature_cards', 'tiles', 'Tuiles stats', array(
			f_text('feature_cards_tiles', 'value', 'Valeur (verte)'),
			f_textarea('feature_cards_tiles', 'label', 'Libellé (2 lignes ok)', 2),
		), 'block', 'Ajouter une tuile', array('max' => 8)),
		f_textarea('feature_cards', 'highlight', 'Bandeau preuve (vert)', 2, array('instructions' => 'HTML léger autorisé : <b>.')),
	),
	settings_trio('feature_cards')
));

/* ── numbered_steps ───────────────────────────────────── */
$layouts['layout_rmd_cs_numbered_steps'] = layout_def('numbered_steps', 'Méthode (étapes numérotées)', array_merge(
	header_trio('numbered_steps'),
	array(f_repeater('numbered_steps', 'steps', 'Étapes', array(
		f_text('numbered_steps_steps', 'heading', 'Titre'),
		f_textarea('numbered_steps_steps', 'items', 'Puces — une par ligne', 5),
	), 'block', 'Ajouter une étape', array('min' => 1, 'max' => 8))),
	settings_trio('numbered_steps', 'light')
));

/* ── screenshot_gallery ───────────────────────────────── */
$gal_item = array(
	f_image('screenshot_gallery_items', 'image', 'Capture'),
	f_select('screenshot_gallery_items', 'style', 'Cadre', array('plain' => 'Simple', 'browser' => 'Navigateur (points + libellé)'), 'plain'),
	f_text('screenshot_gallery_items', 'label', 'Libellé (mot-clé)', array('conditional_logic' => array(array(array(
		'field' => 'field_rmd_cs_screenshot_gallery_items_style', 'operator' => '==', 'value' => 'browser',
	))))),
	f_text('screenshot_gallery_items', 'badge', 'Badge position (#1)', array('conditional_logic' => array(array(array(
		'field' => 'field_rmd_cs_screenshot_gallery_items_style', 'operator' => '==', 'value' => 'browser',
	))))),
	f_truefalse('screenshot_gallery_items', 'scrollable', 'Défilement interne', 0),
	f_number('screenshot_gallery_items', 'scroll_height', 'Hauteur (px)', 380, array('conditional_logic' => array(array(array(
		'field' => 'field_rmd_cs_screenshot_gallery_items_scrollable', 'operator' => '==', 'value' => '1',
	))))),
	f_truefalse('screenshot_gallery_items', 'zoomable', 'Zoom (lightbox)', 1),
	f_text('screenshot_gallery_items', 'caption', 'Légende', array('instructions' => 'HTML léger autorisé : <b>.')),
	f_text('screenshot_gallery_items', 'source', 'Source (pill verte)', array('placeholder' => 'SEMRUSH')),
);
$layouts['layout_rmd_cs_screenshot_gallery'] = layout_def('screenshot_gallery', 'Galerie de captures', array_merge(
	header_trio('screenshot_gallery'),
	array(
		f_select('screenshot_gallery', 'columns', 'Colonnes', array('1' => '1 colonne', '2' => '2 colonnes'), '1'),
		f_repeater('screenshot_gallery', 'items', 'Captures', $gal_item, 'block', 'Ajouter une capture', array('min' => 1)),
	),
	settings_trio('screenshot_gallery')
));

/* ── table_split ──────────────────────────────────────── */
$layouts['layout_rmd_cs_table_split'] = layout_def('table_split', 'Tableau + colonne compagnon', array_merge(
	header_trio('table_split'),
	array(
		f_repeater('table_split', 'table_columns', 'Colonnes du tableau', array(
			f_text('table_split_table_columns', 'label', 'En-tête'),
			f_truefalse('table_split_table_columns', 'highlight', 'Colonne verte', 0),
		), 'table', 'Ajouter une colonne'),
		f_repeater('table_split', 'table_rows', 'Lignes du tableau', array(
			f_repeater('table_split_table_rows', 'cells', 'Cellules', array(
				f_text('table_split_table_rows_cells', 'content', 'Contenu'),
				f_truefalse('table_split_table_rows_cells', 'is_win', 'Vert/gras', 0),
			), 'table', 'Ajouter une cellule'),
		), 'block', 'Ajouter une ligne'),
		f_repeater('table_split', 'side_stats', 'Stats latérales (navy)', array(
			f_text('table_split_side_stats', 'tag', 'Mini-tag'),
			f_text('table_split_side_stats', 'value', 'Valeur'),
			f_text('table_split_side_stats', 'label', 'Delta / libellé'),
		), 'block', 'Ajouter une stat', array('max' => 3)),
		f_textarea('table_split', 'comment', 'Commentaire (encadré)', 4, array('instructions' => 'HTML léger autorisé : <b>.')),
		f_select('table_split', 'media_position', 'Capture à gauche ?', array('none' => 'Pas de capture', 'left' => 'Capture à gauche'), 'none'),
		f_image('table_split', 'media_image', 'Capture', array('conditional_logic' => array(array(array(
			'field' => 'field_rmd_cs_table_split_media_position', 'operator' => '==', 'value' => 'left',
		))))),
		f_text('table_split', 'media_caption', 'Légende de la capture', array('conditional_logic' => array(array(array(
			'field' => 'field_rmd_cs_table_split_media_position', 'operator' => '==', 'value' => 'left',
		))))),
		f_text('table_split', 'media_source', 'Source (pill verte)', array('conditional_logic' => array(array(array(
			'field' => 'field_rmd_cs_table_split_media_position', 'operator' => '==', 'value' => 'left',
		))))),
	),
	settings_trio('table_split', 'light')
));

/* ── line_chart ───────────────────────────────────────── */
$layouts['layout_rmd_cs_line_chart'] = layout_def('line_chart', 'Courbe (SVG)', array_merge(
	header_trio('line_chart'),
	array(
		f_text('line_chart', 'chart_title', 'Titre de la courbe', array('placeholder' => 'Domain Rating, juin 2024 → juin 2026')),
		f_text('line_chart', 'chart_note', 'Note (source)', array('placeholder' => '(Ahrefs)')),
		f_text('line_chart', 'value_prefix', 'Préfixe des valeurs', array('default_value' => 'DR')),
		f_repeater('line_chart', 'points', 'Points de données', array(
			f_text('line_chart_points', 'label', 'Libellé (mois)'),
			f_number('line_chart_points', 'value', 'Valeur'),
		), 'table', 'Ajouter un point', array('min' => 2)),
	),
	settings_trio('line_chart')
));

/* ── recap_band ───────────────────────────────────────── */
$layouts['layout_rmd_cs_recap_band'] = layout_def('recap_band', 'Bandeau récap (dégradé)', array(
	f_text('recap_band', 'heading', 'Titre'),
	f_repeater('recap_band', 'pills', 'Pills', array(
		f_text('recap_band_pills', 'text', 'Texte', array('instructions' => 'HTML léger autorisé : <b>.')),
	), 'table', 'Ajouter une pill', array('max' => 8)),
));

/* ── cta ──────────────────────────────────────────────── */
$layouts['layout_rmd_cs_cta'] = layout_def('cta', 'CTA final', array_merge(
	array(
		f_text('cta', 'eyebrow', 'Eyebrow', array('default_value' => 'Votre tour')),
		f_text('cta', 'heading', 'Titre — début'),
		f_text('cta', 'heading_accent', 'Titre — partie dégradée'),
		f_text('cta', 'heading_after', 'Titre — fin', array('default_value' => '.')),
		f_textarea('cta', 'subheading', 'Sous-titre', 2),
		f_text('cta', 'button_label', 'Texte du bouton', array('default_value' => 'Discuter de mon projet')),
		f_text('cta', 'button_url', 'Lien du bouton', array('default_value' => 'mailto:contact@rhillane.com', 'instructions' => 'URL ou mailto: — champ texte volontairement (le type url refuse mailto).')),
		f_text('cta', 'contact_line', 'Ligne contact', array('placeholder' => 'contact@rhillane.com · +212 663-091166')),
	),
	settings_trio('cta', 'light')
));

/* ── group ────────────────────────────────────────────── */
$group = array(
	'key'                   => 'group_rmd_case_study_sections',
	'title'                 => 'Case Study — Sections',
	'fields'                => array(array(
		'key'               => 'field_rmd_cs_sections',
		'label'             => 'Sections',
		'name'              => 'sections',
		'type'              => 'flexible_content',
		'instructions'      => '',
		'required'          => 0,
		'conditional_logic' => 0,
		'wrapper'           => array('width' => '', 'class' => '', 'id' => ''),
		'layouts'           => $layouts,
		'button_label'      => 'Ajouter une section',
		'min'               => '',
		'max'               => '',
	)),
	'location'              => array(array(array(
		'param'    => 'post_type',
		'operator' => '==',
		'value'    => 'case_study',
	))),
	'menu_order'            => 0,
	'position'              => 'normal',
	'style'                 => 'default',
	'label_placement'       => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen'        => array(),
	'active'                => true,
	'description'           => 'Sections des études de cas — généré par src/gen-acf.php — modifier le générateur, pas ce fichier. Clés field_rmd_cs_*.',
	'show_in_rest'          => 0,
	'modified'              => time(),
);

/* Duplicate-key safety net before writing. */
$json = json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
preg_match_all('/"key": "(field|layout|group)_[a-z0-9_]+"/', $json, $m);
$dupes = array_diff_assoc($m[0], array_unique($m[0]));
if ($dupes) {
	fwrite(STDERR, "DUPLICATE KEYS:\n" . implode("\n", array_unique($dupes)) . "\n");
	exit(1);
}

$out = $argv[1] ?? 'group_rmd_case_study_sections.json';
file_put_contents($out, $json . "\n");
echo 'OK ' . $out . ' — ' . count($layouts) . " layouts, " . count($m[0]) . " unique keys\n";
