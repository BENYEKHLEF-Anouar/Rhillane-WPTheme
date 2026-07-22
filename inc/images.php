<?php
/**
 * Image lazy-loading & loading effects (spec §12).
 *
 * `rmd_render_image()` is the single renderer for a "managed" image: it reserves
 * space via aspect-ratio (zero CLS), sets lazy/eager + fetchpriority, and emits
 * the fade / skeleton / blur-up markup that assets/css/rmd-media.css and the
 * image-loading block in assets/js/main.js upgrade. The baseline (lazy+async+
 * dimensions everywhere, eager+high only for the LCP image) is already enforced
 * by rmd_image() in inc/helpers.php; this adds the per-field options on top.
 *
 * `group_rmd_image_options` is the clone-able ACF group (PHP fallback, §3.3).
 *
 * @package VaultChild
 */
defined('ABSPATH') || exit;

/**
 * Render a managed <img>, wrapped for the loading effect, from an image-options
 * array. Renders nothing when there is no image (never a broken box).
 *
 * @param array  $opts       { image (ACF array|id), alt, loading, priority, effect, aspect_ratio, focal_point, link }
 * @param string $size       Registered image size (default 'large').
 * @param string $base_class Extra class(es) on the wrapper.
 */
function rmd_render_image($opts, $size = 'large', $base_class = '') {
	$opts = is_array($opts) ? $opts : array('image' => $opts);
	$img  = $opts['image'] ?? null;
	if (empty($img)) {
		return '';
	}

	// Resolve source data from an ACF image array, or from an attachment ID.
	if (is_array($img)) {
		$url = $img['sizes'][$size] ?? ($img['url'] ?? '');
		$w   = $img['sizes'][$size . '-width'] ?? ($img['width'] ?? '');
		$h   = $img['sizes'][$size . '-height'] ?? ($img['height'] ?? '');
		$att_alt = $img['alt'] ?? '';
		$lqip_src = $img['sizes']['thumbnail'] ?? '';
		$id  = (int) ($img['ID'] ?? 0);
	} else {
		$id  = (int) $img;
		$src = $id ? wp_get_attachment_image_src($id, $size) : false;
		$url = $src ? $src[0] : '';
		$w   = $src ? $src[1] : '';
		$h   = $src ? $src[2] : '';
		$att_alt  = $id ? (string) get_post_meta($id, '_wp_attachment_image_alt', true) : '';
		$lqip     = $id ? wp_get_attachment_image_src($id, 'thumbnail') : false;
		$lqip_src = $lqip ? $lqip[0] : '';
	}
	if ('' === $url) {
		return '';
	}

	$alt    = '' !== (string) ($opts['alt'] ?? '') ? $opts['alt'] : $att_alt;
	$eager  = 'eager' === ($opts['loading'] ?? 'lazy');
	$effect = $opts['effect'] ?? 'fade';
	$ratio  = (string) ($opts['aspect_ratio'] ?? '');
	$focal  = (string) ($opts['focal_point'] ?? '');
	$lqip   = ('blur' === $effect) ? $lqip_src : '';

	// The ratio is a free-text field — accept only a clean "W/H" (or "auto") so a
	// trusted editor can't slip extra CSS declarations into the inline style.
	if ('' !== $ratio && 'auto' !== $ratio && !preg_match('#^\d+(\.\d+)?\s*/\s*\d+(\.\d+)?$#', $ratio)) {
		$ratio = '';
	}

	// Reserve space (CLS): explicit aspect-ratio, or derive from dimensions.
	$wrap_style = '';
	if ($ratio && 'auto' !== $ratio) {
		$wrap_style = 'aspect-ratio:' . $ratio . ';';
	} elseif ($w && $h) {
		$wrap_style = 'aspect-ratio:' . (int) $w . '/' . (int) $h . ';';
	}
	$obj_pos = $focal && 'center' !== $focal ? 'object-position:' . $focal . ';' : '';

	$wrap_class = trim('img-wrap img-effect--' . sanitize_html_class($effect) . ' ' . $base_class);

	ob_start(); ?>
	<span class="<?php echo esc_attr($wrap_class); ?>"<?php echo $wrap_style ? ' style="' . esc_attr($wrap_style) . '"' : ''; ?>>
		<?php if ($lqip) : ?><img class="img-lqip" src="<?php echo esc_url($lqip); ?>" alt="" aria-hidden="true"><?php endif; ?>
		<img class="img-main"
			src="<?php echo esc_url($url); ?>"
			<?php echo $w ? 'width="' . esc_attr($w) . '" ' : ''; ?><?php echo $h ? 'height="' . esc_attr($h) . '" ' : ''; ?>
			alt="<?php echo esc_attr($alt); ?>"
			loading="<?php echo $eager ? 'eager' : 'lazy'; ?>"
			decoding="async"
			<?php echo !empty($opts['priority']) ? 'fetchpriority="high" ' : ''; ?><?php echo $obj_pos ? 'style="' . esc_attr($obj_pos) . '" ' : ''; ?>
			onload="var w=this.closest('.img-wrap');w&&w.classList.add('is-loaded')"
			onerror="var w=this.closest('.img-wrap');w&&w.classList.add('is-loaded')">
	</span>
	<?php
	$html = ob_get_clean();

	// Optional: make the image a link with the full §10 attribute set.
	if (!empty($opts['link']) && is_array($opts['link'])) {
		$linked = rmd_render_link($opts['link'], $html, 'img-link');
		if ('' !== $linked) {
			return $linked;
		}
	}
	return $html;
}

/**
 * Register the reusable image-options group in PHP (fallback only; §3.3). Clone
 * source only — its location never matches a real edit screen.
 */
add_action('acf/init', 'rmd_register_image_options_group');
function rmd_register_image_options_group() {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	if (function_exists('acf_get_field_group') && acf_get_field_group('group_rmd_image_options')) {
		return;
	}

	acf_add_local_field_group(array(
		'key'    => 'group_rmd_image_options',
		'title'  => 'Options d’image (RMD)',
		'fields' => array(
			array('key' => 'field_rmd_img_image', 'label' => 'Image', 'name' => 'image', 'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium', 'library' => 'all'),
			array('key' => 'field_rmd_img_alt', 'label' => 'Texte alternatif', 'name' => 'alt', 'type' => 'text', 'instructions' => 'Remplace l’alt du média (sinon celui de la médiathèque).'),
			array(
				'key' => 'field_rmd_img_loading', 'label' => 'Chargement', 'name' => 'loading', 'type' => 'select',
				'choices' => array('lazy' => 'Lazy (par défaut)', 'eager' => 'Eager (image LCP uniquement)'),
				'default_value' => 'lazy', 'return_format' => 'value',
			),
			array('key' => 'field_rmd_img_priority', 'label' => 'Priorité haute', 'name' => 'priority', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'message' => 'fetchpriority="high" — image LCP uniquement.'),
			array(
				'key' => 'field_rmd_img_effect', 'label' => 'Effet de chargement', 'name' => 'effect', 'type' => 'select',
				'choices' => array('fade' => 'Fondu', 'skeleton' => 'Squelette', 'blur' => 'Flou (LQIP)', 'none' => 'Aucun'),
				'default_value' => 'fade', 'return_format' => 'value',
			),
			array('key' => 'field_rmd_img_ratio', 'label' => 'Ratio', 'name' => 'aspect_ratio', 'type' => 'text', 'placeholder' => '16/9, 1/1, 4/3, auto', 'instructions' => 'Réserve l’espace (anti-CLS). Vide = déduit des dimensions.'),
			array(
				'key' => 'field_rmd_img_focal', 'label' => 'Cadrage', 'name' => 'focal_point', 'type' => 'select',
				'choices' => array('center' => 'Centre', 'top' => 'Haut', 'bottom' => 'Bas', 'left' => 'Gauche', 'right' => 'Droite'),
				'default_value' => 'center', 'return_format' => 'value',
			),
		),
		'location' => array(array(array('param' => 'post_type', 'operator' => '==', 'value' => 'rmd_clone_source_only'))),
		'active'   => true,
		'description' => 'Groupe clonable — options d’image (lazy/effet/ratio), spec §12.',
	));
}
