<?php
/**
 * The ONE definition of the "advanced link attributes" field set (spec §10.1):
 * target, rel (nofollow / sponsored / ugc / noopener / noreferrer), download,
 * aria-label, title, id, CSS classes and data-* rows — i.e. exactly the keys
 * rmd_render_link() reads in inc/links.php.
 *
 * Kept in its own file on purpose: src/gen-acf.php (CLI, no WordPress loaded)
 * requires it to build the same fields into the case-study JSON, so the group
 * the header uses and the group the sections use can never drift. Hence the
 * dual guard below — it must load under PHP-CLI, but never over HTTP outside
 * WordPress.
 *
 * @package VaultChild
 */
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
	exit;
}

/**
 * One ACF field array. Key order mirrors src/gen-acf.php's f_base() so the
 * generated JSON stays diff-stable whoever writes it.
 */
function rmd_link_field($key, $label, $name, $type, $extra = array()) {
	return array_merge(array(
		'key'               => $key,
		'label'             => $label,
		'name'              => $name,
		'type'              => $type,
		'instructions'      => '',
		'required'          => 0,
		'conditional_logic' => 0,
		'wrapper'           => array('width' => '', 'class' => '', 'id' => ''),
	), $extra);
}

/**
 * The attribute sub-fields, keyed off $key_prefix. Sub-field NAMES are frozen —
 * they are what rmd_render_link() reads, so a saved value can be passed to the
 * renderer as-is with no mapping layer.
 *
 * Labels are authored in French (like acf-json); inc/admin-ux.php swaps them for
 * English at acf/load_field when the admin user isn't French.
 *
 * @param string $key_prefix e.g. 'field_rmd_cs_cta_btn_adv' — must be unique.
 */
function rmd_link_advanced_subfields($key_prefix) {
	// Only shown under "Personnalisé": in every other mode the preset owns these.
	$when_custom = array(array(array('field' => $key_prefix . '_mode', 'operator' => '==', 'value' => 'custom')));

	return array(
		rmd_link_field($key_prefix . '_mode', 'Comportement du lien', 'mode', 'select', array(
			'instructions'  => '« Normal » convient à un CTA qui pointe vers vos propres pages. « Personnalisé » débloque les attributs rel et l’onglet ci-dessous.',
			'choices'       => array(
				'normal'    => 'Normal — lien interne',
				'external'  => 'Lien externe — nouvel onglet',
				'sponsored' => 'Sponsorisé / affilié',
				'custom'    => 'Personnalisé',
			),
			'default_value' => 'normal',
			'return_format' => 'value',
			'multiple'      => 0,
			'allow_null'    => 0,
			'ui'            => 0,
		)),
		rmd_link_field($key_prefix . '_target', 'Nouvel onglet', 'target', 'true_false', array(
			'conditional_logic' => $when_custom,
			'ui'            => 1,
			'default_value' => 0,
			'message'       => 'Ouvrir dans un nouvel onglet (ajoute noopener/noreferrer).',
		)),
		rmd_link_field($key_prefix . '_rel', 'Attributs rel', 'rel', 'checkbox', array(
			// The nofollow footgun: editors reach for it on their own CTAs, where it
			// only wastes internal linking. Say so where they read it.
			'instructions'  => 'Pour les liens SORTANTS : nofollow (non fiable), sponsored (payant/affilié), ugc. Un bouton vers vos propres pages ne doit PAS être en nofollow.',
			'conditional_logic' => $when_custom,
			'choices'       => array(
				'nofollow'   => 'nofollow',
				'sponsored'  => 'sponsored',
				'ugc'        => 'ugc',
				'noopener'   => 'noopener',
				'noreferrer' => 'noreferrer',
			),
			'default_value' => array(),
			'return_format' => 'value',
			'allow_custom'  => 0,
			'save_custom'   => 0,
			'layout'        => 'horizontal',
			'toggle'        => 0,
		)),
		rmd_link_field($key_prefix . '_download', 'Téléchargement', 'download', 'true_false', array(
			'ui'            => 1,
			'default_value' => 0,
			'message'       => 'Ajoute l’attribut download (liens fichiers).',
		)),
		rmd_link_field($key_prefix . '_aria_label', 'aria-label', 'aria_label', 'text', array(
			'instructions'  => 'Nom accessible quand le texte du bouton n’est pas explicite.',
			'default_value' => '',
			'placeholder'   => '',
		)),
		rmd_link_field($key_prefix . '_title_attr', 'title (info-bulle)', 'title_attr', 'text', array(
			'default_value' => '',
			'placeholder'   => '',
		)),
		rmd_link_field($key_prefix . '_element_id', 'id', 'element_id', 'text', array(
			'instructions'  => 'Identifiant HTML (lettres, chiffres, tirets) — ancres et analytics.',
			'default_value' => '',
			'placeholder'   => '',
		)),
		rmd_link_field($key_prefix . '_css_classes', 'Classes CSS', 'css_classes', 'text', array(
			'instructions'  => 'Classes ajoutées au bouton, séparées par des espaces.',
			'default_value' => '',
			'placeholder'   => '',
		)),
		rmd_link_field($key_prefix . '_data_attrs', 'Attributs data-*', 'data_attrs', 'repeater', array(
			'instructions' => 'Clé « gtm-id » → data-gtm-id="…". Pour le tracking analytics.',
			'layout'       => 'table',
			'min'          => 0,
			'max'          => 0,
			'collapsed'    => $key_prefix . '_data_attrs_key',
			'button_label' => 'Ajouter un attribut',
			'sub_fields'   => array(
				rmd_link_field($key_prefix . '_data_attrs_key', 'Clé', 'key', 'text', array(
					'default_value' => '',
					'placeholder'   => 'gtm-id',
				)),
				rmd_link_field($key_prefix . '_data_attrs_value', 'Valeur', 'value', 'text', array(
					'default_value' => '',
					'placeholder'   => '',
				)),
			),
		)),
	);
}

/**
 * The same set wrapped in ONE ACF group field — what sections and Site Settings
 * embed. A group returns a clean assoc array (target, rel, …) that drops straight
 * into rmd_render_link() with no remapping.
 *
 * The editor never shows this group inline: assets/admin/link-options.js hides it
 * and puts a "⚙ Options avancées" button in its place (inc/admin-link-options.php).
 * If that JS never loads, the group simply renders inline — degraded, not broken.
 */
function rmd_link_advanced_field($key, $name = 'button_advanced', $label = 'Options avancées du bouton') {
	return rmd_link_field($key, $label, $name, 'group', array(
		'layout'     => 'block',
		'sub_fields' => rmd_link_advanced_subfields($key),
	));
}

/**
 * What each `mode` preset resolves to. The editor picks a behaviour instead of
 * hand-assembling rel flags; "custom" means "use the fields as saved".
 * ('external' relies on rmd_render_link() adding noopener/noreferrer for _blank,
 * but spells them out so the intent survives a future change to that helper.)
 */
function rmd_link_mode_presets() {
	return array(
		'normal'    => array('target' => 0, 'rel' => array()),
		'external'  => array('target' => 1, 'rel' => array('noopener', 'noreferrer')),
		'sponsored' => array('target' => 1, 'rel' => array('sponsored', 'nofollow')),
	);
}

/**
 * Merge a saved advanced-options array onto a base link-options array, ready for
 * rmd_render_link(). Base keys (link_type/url/label) always win; a missing or
 * malformed group value degrades to "no advanced options" instead of fataling.
 *
 * A preset OVERWRITES target/rel, because those fields are hidden while it is
 * active — otherwise values left behind by an earlier "Personnalisé" session
 * would keep rendering invisibly. An empty mode means the row predates the
 * preset, so whatever it already had is honoured untouched.
 *
 * @param array      $base Link options resolved by the caller (link_type, url, label…).
 * @param array|null $adv  Whatever rmd_get_field()/rmd_get_sub_field() returned.
 */
function rmd_link_with_advanced($base, $adv) {
	if (!is_array($adv)) {
		return (array) $base;
	}

	$mode    = isset($adv['mode']) ? (string) $adv['mode'] : '';
	$presets = rmd_link_mode_presets();
	if ('' !== $mode && isset($presets[$mode])) {
		$adv = array_merge($adv, $presets[$mode]);
	}
	unset($adv['mode']);

	return (array) $base + $adv;
}
