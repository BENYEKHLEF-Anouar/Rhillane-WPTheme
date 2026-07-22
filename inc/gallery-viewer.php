<?php
/**
 * In-field image gallery viewer (spec §9).
 *
 * A read-first way to see/browse the images inside a flexible-content row without
 * opening the media library one item at a time. For every section row that holds
 * images (an ACF Gallery field, or a repeater whose rows contain image sub-fields
 * — e.g. `screenshot_gallery` items), the admin JS adds a thumbnail strip + count
 * to the row header and a "Voir la galerie" button that opens a lightbox
 * (grid → full view, prev/next, keyboard, Esc, "Modifier dans la médiathèque").
 *
 * Purely additive, editor-only: enqueued on post.php / post-new.php when ACF is
 * active, same gate as the section preview. No front-end output.
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'rmd_gallery_viewer_assets');
function rmd_gallery_viewer_assets($hook) {
	if ('post.php' !== $hook && 'post-new.php' !== $hook) {
		return;
	}
	if (!defined('RMD_ACF_ACTIVE') || !RMD_ACF_ACTIVE) {
		return;
	}

	$css = RMD_DIR . '/assets/admin/gallery-viewer.css';
	$js  = RMD_DIR . '/assets/admin/gallery-viewer.js';

	wp_enqueue_style('rmd-gallery-viewer', RMD_URI . '/assets/admin/gallery-viewer.css', array('dashicons'), file_exists($css) ? filemtime($css) : RMD_VERSION);

	// wp.media powers the "Modifier dans la médiathèque" jump.
	if (function_exists('wp_enqueue_media')) {
		wp_enqueue_media();
	}

	wp_enqueue_script('rmd-gallery-viewer', RMD_URI . '/assets/admin/gallery-viewer.js', array('jquery'), file_exists($js) ? filemtime($js) : RMD_VERSION, true);

	wp_localize_script('rmd-gallery-viewer', 'rmdGalleryViewer', array(
		'i18n' => array(
			'view'    => __('Voir la galerie', 'vault-child'),
			'images'  => __('images', 'vault-child'),
			'image'   => __('image', 'vault-child'),
			'close'   => __('Fermer', 'vault-child'),
			'prev'    => __('Précédente', 'vault-child'),
			'next'    => __('Suivante', 'vault-child'),
			'edit'    => __('Modifier dans la médiathèque', 'vault-child'),
			'title'   => __('Galerie de la section', 'vault-child'),
		),
	));
}
