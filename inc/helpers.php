<?php
/**
 * The engine: one renderer loops the Flexible Content field and includes
 * the matching template-part. Layout key === file name, 1:1, no transform.
 */
defined('ABSPATH') || exit;

/**
 * Is the current admin user's language French? Used to pick FR/EN for admin-only
 * UI strings (CPT + taxonomy labels, section names, the header-menu location)
 * without needing .mo files. On the front end get_user_locale() falls back to the
 * site locale — fine, since those admin labels aren't shown there.
 */
function rmd_is_fr() {
	$locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();
	return 0 === strpos((string) $locale, 'fr');
}

/**
 * Front-end language of the CURRENT SITE (not the viewer). Public page text must
 * follow the SITE, so a logged-in admin's own profile language never flips what
 * visitors read — unlike rmd_is_fr(), which is admin-user-locale for editor UI.
 *
 * Source of truth: the multisite locale map (inc/locale.php), which tags each
 * subsite with a `lang`. Morocco (/) + France are French; the English subsites
 * (US /en-us/, UAE /en-ae/) are English. Robust even if a subsite's WordPress
 * "Site Language" setting was never configured. Off-network, or for an unmapped
 * subsite, falls back to the WP locale.
 */
function rmd_site_is_fr() {
	if (is_multisite() && function_exists('get_blog_details') && function_exists('rmd_locale_map')) {
		$details = get_blog_details();
		if ($details) {
			$path = untrailingslashit((string) $details->path) . '/';
			$map  = rmd_locale_map();
			if (isset($map[$path]['lang'])) {
				return 'fr' === $map[$path]['lang'];
			}
		}
	}
	return 0 === strpos((string) get_locale(), 'fr');
}

/**
 * Pick a front-end string by site language: rmd_ft('Résultats', 'Results').
 * Returns the English variant on every non-French site. Not escaped — the caller
 * still escapes for its context (esc_html / esc_attr).
 */
function rmd_ft($fr, $en) {
	return rmd_site_is_fr() ? $fr : $en;
}

/**
 * Render all sections of a post.
 * Each template-part receives its index via $args — section 0 is above
 * the fold, so its imagery loads eager; everything below loads lazy.
 */
function rmd_render_sections($field = 'sections', $post_id = null) {
	if (!rmd_have_rows($field, $post_id)) {
		return;
	}
	$index = 0;
	while (rmd_have_rows($field, $post_id)) {
		rmd_the_row();
		get_template_part(
			'template-parts/layouts/' . rmd_get_row_layout(),
			null,
			array('index' => $index)
		);
		$index++;
	}
}

/**
 * Single image renderer — every section image goes through here so the
 * §1.5 media rules are enforced by code, not editor discipline.
 *
 * Already handled: width/height + srcset/sizes (via wp_get_attachment_image),
 * lazy/eager switch, fetchpriority. Still to add in W0.5: LQIP blur-up,
 * per-block `sizes` attribute, mobile-image <picture> variant.
 *
 * @param array|int $image ACF image array (return_format: array) or attachment ID.
 * @param array     $args  size | eager (true only for section-0 imagery) | class.
 */
function rmd_image($image, $args = array()) {
	if (empty($image)) {
		return '';
	}
	$args = wp_parse_args($args, array(
		'size'  => 'large',
		'eager' => false,
		'class' => '',
	));

	$id = is_array($image) ? (int) ($image['ID'] ?? 0) : (int) $image;
	if ($id) {
		return wp_get_attachment_image($id, $args['size'], false, array(
			'class'         => $args['class'],
			'loading'       => $args['eager'] ? 'eager' : 'lazy',
			'decoding'      => 'async',
			'fetchpriority' => $args['eager'] ? 'high' : 'auto',
		));
	}

	// No attachment ID: fall back to a bare URL if one is present (a demo/preview
	// placeholder, or an image array that somehow lost its ID). Real section
	// images always carry an ID and take the branch above.
	$url = is_array($image) ? ($image['url'] ?? '') : (is_string($image) ? $image : '');
	if ('' === $url) {
		return '';
	}
	$alt = is_array($image) ? ($image['alt'] ?? '') : '';
	return sprintf(
		'<img src="%s" alt="%s" class="%s" loading="%s" decoding="async" fetchpriority="%s">',
		esc_url($url),
		esc_attr($alt),
		esc_attr($args['class']),
		$args['eager'] ? 'eager' : 'lazy',
		$args['eager'] ? 'high' : 'auto'
	);
}

/**
 * Open a standard section wrapper reading the shared "section settings"
 * sub-fields (background / anchor / padding_top) of the current row.
 * Every layout with the settings trio calls this + rmd_section_close().
 */
function rmd_section_open($extra_class = '') {
	$background = rmd_get_sub_field('background') ?: 'white';
	$anchor     = rmd_get_sub_field('anchor');
	$flush      = 'flush' === rmd_get_sub_field('padding_top');

	$classes = trim('rmd-sec ' . ('light' === $background ? 'bg-nuk ' : '') . $extra_class);

	// Per-instance scoping hook (spec §11.1): a class unique to this section
	// instance, so styling/JS/analytics can target one instance without leaking
	// to the same layout elsewhere. Not a styled class — a hook only.
	$layout = rmd_get_row_layout();
	if ($layout) {
		static $rmd_sec_counts = array();
		$layout = sanitize_html_class($layout);
		$rmd_sec_counts[$layout] = ($rmd_sec_counts[$layout] ?? 0) + 1;
		$classes .= ' rmd-sec--' . $layout . ' rmd-sec--' . $layout . '-' . $rmd_sec_counts[$layout];
	}

	$id  = $anchor ? ' id="' . esc_attr(sanitize_title($anchor)) . '"' : '';
	$pad = $flush ? 'pt-0 pb-16 md:pb-24' : 'py-16 md:py-24';

	echo '<section class="' . esc_attr($classes) . '"' . $id . '>';
	echo '<div class="mx-auto max-w-content px-6 ' . $pad . '">';
}

function rmd_section_close() {
	echo '</div></section>';
}

/**
 * Small inline-HTML allowlist for headings/copy fields: editors may use
 * <b>, <strong>, <br> and <span class="…"> (for .thin / .grad-attention
 * accents) — nothing else survives.
 */
function rmd_inline_html($text) {
	return wp_kses((string) $text, array(
		'b'      => array(),
		'strong' => array(),
		'br'     => array(),
		'span'   => array('class' => true),
	));
}

/**
 * Sanitize a pasted inline SVG icon (stroke icons from the design).
 * Anything outside this allowlist (script, foreignObject, handlers) is stripped.
 */
function rmd_svg($svg) {
	$common = array(
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
	);
	return wp_kses((string) $svg, array(
		'svg'      => $common + array('viewbox' => true, 'width' => true, 'height' => true, 'xmlns' => true, 'aria-hidden' => true, 'role' => true, 'class' => true),
		'path'     => $common + array('d' => true),
		'line'     => $common + array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true),
		'circle'   => $common + array('cx' => true, 'cy' => true, 'r' => true),
		'polyline' => $common + array('points' => true),
		'polygon'  => $common + array('points' => true),
		'rect'     => $common + array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true),
		'g'        => $common,
	));
}
