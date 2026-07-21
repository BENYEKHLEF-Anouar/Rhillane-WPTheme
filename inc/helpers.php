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
