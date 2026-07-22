<?php
/**
 * Extended element options — the reusable "link + attributes" field set and its
 * single front-end renderer (spec §10).
 *
 * `rmd_render_link()` is the ONE place a link/CTA becomes an <a>, so behaviour
 * (rel/nofollow, auto noopener on _blank, data-*, escaping) never drifts between
 * sections. `group_rmd_link_options` is the clone-able ACF group; it is registered
 * in PHP (spec §3.3 fallback) only when no JSON/DB version exists yet, so a
 * staging export to acf-json/ wins later.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/**
 * Turn a link-options array into a safe <a>. Escapes every attribute; composes
 * rel from the editor's checkboxes plus an automatic noopener/noreferrer for
 * target="_blank"; renders nothing (no dead "#") when there is no destination.
 *
 * @param array  $opts       Link-options sub-fields (label, link_type, page, url, target, rel, …).
 * @param string $inner_html Already-escaped inner markup (falls back to the label).
 * @param string $base_class Base class(es) always applied.
 */
function rmd_render_link($opts, $inner_html = '', $base_class = '') {
	$opts = is_array($opts) ? $opts : array();
	$type = $opts['link_type'] ?? 'url';

	// Resolve the destination. ACF's page_link field returns a URL string already
	// (not a post ID), so use it directly — get_permalink() would (int)-cast a URL
	// to 0 and fail. Cast to string so a false/empty value fails the guard below.
	$href = '';
	if ('page' === $type) {
		$href = (string) ($opts['page'] ?? '');
	} elseif ('url' === $type) {
		$href = (string) ($opts['url'] ?? '');
	}
	if ('' === $href && 'none' !== $type) {
		return ''; // no destination → render nothing, never a dead "#"
	}

	if ('' === $inner_html) {
		$inner_html = esc_html((string) ($opts['label'] ?? ''));
	}

	// rel: editor checkboxes + auto noopener/noreferrer for a new tab.
	$rel    = (array) ($opts['rel'] ?? array());
	$target = ($opts['target'] ?? '_self');
	$target = ('_blank' === $target || true === $target || '1' === (string) $target) ? '_blank' : '_self';
	if ('_blank' === $target) {
		$rel[] = 'noopener';
		$rel[] = 'noreferrer';
	}
	$rel = array_values(array_unique(array_filter(array_map('strval', $rel))));

	$classes = trim($base_class . ' ' . (string) ($opts['css_classes'] ?? ''));

	// 'none' → a styled <span>, so a label with no link still renders its text.
	if ('none' === $type && '' === $href) {
		$cls = '' !== $classes ? ' class="' . esc_attr($classes) . '"' : '';
		return '<span' . $cls . '>' . $inner_html . '</span>';
	}

	$attrs = array(
		'href'       => esc_url($href),
		'class'      => '' !== $classes ? esc_attr($classes) : null,
		'target'     => '_blank' === $target ? '_blank' : null,
		'rel'        => $rel ? esc_attr(implode(' ', $rel)) : null,
		'id'         => !empty($opts['element_id']) ? esc_attr(sanitize_html_class($opts['element_id'])) : null,
		'title'      => !empty($opts['title_attr']) ? esc_attr($opts['title_attr']) : null,
		'aria-label' => !empty($opts['aria_label']) ? esc_attr($opts['aria_label']) : null,
		'download'   => !empty($opts['download']) ? '' : null, // boolean attribute
	);

	foreach ((array) ($opts['data_attrs'] ?? array()) as $row) {
		if (!empty($row['key'])) {
			$attrs['data-' . sanitize_html_class($row['key'])] = esc_attr((string) ($row['value'] ?? ''));
		}
	}

	$html = '<a';
	foreach ($attrs as $k => $v) {
		if (null === $v) {
			continue;
		}
		$html .= '' === $v ? ' ' . $k : ' ' . $k . '="' . $v . '"';
	}
	$html .= '>' . $inner_html . '</a>';
	return $html;
}

/**
 * Register the reusable link-options group in PHP — but only as a fallback, when
 * no JSON/DB version exists (spec §3.3). Location never matches a real screen:
 * the group exists to be pulled in via ACF's Clone field, not rendered standalone.
 */
add_action('acf/init', 'rmd_register_link_options_group');
function rmd_register_link_options_group() {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	// A synced JSON / DB group with this key wins — don't double-register.
	if (function_exists('acf_get_field_group') && acf_get_field_group('group_rmd_link_options')) {
		return;
	}

	acf_add_local_field_group(array(
		'key'    => 'group_rmd_link_options',
		'title'  => 'Options de lien (RMD)',
		'fields' => array(
			array('key' => 'field_rmd_link_label', 'label' => 'Texte du lien', 'name' => 'label', 'type' => 'text'),
			array(
				'key' => 'field_rmd_link_type', 'label' => 'Type de lien', 'name' => 'link_type', 'type' => 'select',
				'choices' => array('url' => 'URL', 'page' => 'Page du site', 'none' => 'Aucun lien'),
				'default_value' => 'url', 'return_format' => 'value',
			),
			array(
				'key' => 'field_rmd_link_page', 'label' => 'Page', 'name' => 'page', 'type' => 'page_link',
				'post_type' => array(), 'allow_null' => 1, 'allow_archives' => 0, 'multiple' => 0,
				'conditional_logic' => array(array(array('field' => 'field_rmd_link_type', 'operator' => '==', 'value' => 'page'))),
			),
			array(
				'key' => 'field_rmd_link_url', 'label' => 'URL', 'name' => 'url', 'type' => 'text',
				'instructions' => 'URL complète ou mailto: — champ texte volontairement (le type « url » refuse mailto).',
				'conditional_logic' => array(array(array('field' => 'field_rmd_link_type', 'operator' => '==', 'value' => 'url'))),
			),
			array(
				'key' => 'field_rmd_link_target', 'label' => 'Nouvel onglet', 'name' => 'target', 'type' => 'true_false',
				'ui' => 1, 'default_value' => 0, 'message' => 'Ouvrir dans un nouvel onglet (ajoute noopener/noreferrer).',
			),
			array(
				'key' => 'field_rmd_link_rel', 'label' => 'Attributs rel', 'name' => 'rel', 'type' => 'checkbox',
				'choices' => array('nofollow' => 'nofollow', 'sponsored' => 'sponsored', 'ugc' => 'ugc', 'noopener' => 'noopener', 'noreferrer' => 'noreferrer'),
				'return_format' => 'value',
			),
			array('key' => 'field_rmd_link_download', 'label' => 'Téléchargement', 'name' => 'download', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'message' => 'Ajoute l’attribut download (liens fichiers).'),
			array('key' => 'field_rmd_link_aria', 'label' => 'aria-label', 'name' => 'aria_label', 'type' => 'text', 'instructions' => 'Nom accessible quand le texte du lien n’est pas explicite.'),
			array('key' => 'field_rmd_link_title', 'label' => 'title (info-bulle)', 'name' => 'title_attr', 'type' => 'text'),
			array('key' => 'field_rmd_link_id', 'label' => 'id', 'name' => 'element_id', 'type' => 'text', 'instructions' => 'Identifiant (token sûr) pour ancres / analytics.'),
			array('key' => 'field_rmd_link_classes', 'label' => 'Classes CSS', 'name' => 'css_classes', 'type' => 'text'),
			array(
				'key' => 'field_rmd_link_data', 'label' => 'Attributs data-*', 'name' => 'data_attrs', 'type' => 'repeater',
				'layout' => 'table', 'button_label' => 'Ajouter un attribut',
				'sub_fields' => array(
					array('key' => 'field_rmd_link_data_key', 'label' => 'Clé', 'name' => 'key', 'type' => 'text'),
					array('key' => 'field_rmd_link_data_value', 'label' => 'Valeur', 'name' => 'value', 'type' => 'text'),
				),
			),
		),
		// Never matches a real edit screen — this group is a clone source only.
		'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'rmd_clone_source_only'))),
		'active'   => true,
		'description' => 'Groupe clonable — options de lien réutilisables (spec §10). Cloner via un champ Clone dans une section.',
	));
}
