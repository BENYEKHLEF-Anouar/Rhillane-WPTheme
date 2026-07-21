<?php
/**
 * The engine: one renderer loops the Flexible Content field and includes
 * the matching template-part. Layout key === file name, 1:1, no transform.
 */
defined('ABSPATH') || exit;

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
	if (!$id) {
		return '';
	}

	return wp_get_attachment_image($id, $args['size'], false, array(
		'class'         => $args['class'],
		'loading'       => $args['eager'] ? 'eager' : 'lazy',
		'decoding'      => 'async',
		'fetchpriority' => $args['eager'] ? 'high' : 'auto',
	));
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
	$id      = $anchor ? ' id="' . esc_attr(sanitize_title($anchor)) . '"' : '';
	$pad     = $flush ? 'pt-0 pb-16 md:pb-24' : 'py-16 md:py-24';

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
