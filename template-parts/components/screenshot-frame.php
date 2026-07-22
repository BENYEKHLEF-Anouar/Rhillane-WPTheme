<?php
/**
 * Component: one screenshot frame (plain card or browser mockup).
 * $args: image (ACF array), style ('plain'|'browser'), label, badge,
 *        scrollable (bool), scroll_height (px), zoomable (bool),
 *        caption (inline-HTML), source, class (extra wrapper class),
 *        eager (bool — true only for a section-0 LCP image).
 */
defined('ABSPATH') || exit;

$image = $args['image'] ?? null;
// Render if we have an attachment ID or a bare URL (demo/preview placeholders).
if (empty($image['ID']) && empty($image['url'])) {
	return;
}

$style      = $args['style'] ?? 'plain';
$label      = $args['label'] ?? '';
$badge      = $args['badge'] ?? '';
$scrollable = !empty($args['scrollable']);
$height     = (int) ($args['scroll_height'] ?? 380) ?: 380;
$zoomable   = !empty($args['zoomable']);
$caption    = $args['caption'] ?? '';
$source     = $args['source'] ?? '';
$extra      = $args['class'] ?? '';
$eager      = !empty($args['eager']);

$zoom_attr = '';
if ($zoomable && !empty($image['ID'])) {
	$full = wp_get_attachment_image_url($image['ID'], 'full');
	$zoom_attr = ' data-rmd-zoom data-full="' . esc_url($full) . '"';
}

$img_html = rmd_image($image, array('size' => 'large', 'eager' => $eager));

$inner = $img_html;
if ($scrollable) {
	$inner = '<div class="shot-scroll' . ($zoomable ? '' : ' noclick') . '" style="height:' . $height . 'px" tabindex="0">' . $img_html . '</div>';
}

if ('browser' === $style) : ?>
<div class="shot-fr <?php echo esc_attr($extra); ?>"<?php echo $zoom_attr; ?>>
	<div class="bar">
		<span class="d d1"></span><span class="d d2"></span><span class="d d3"></span>
		<?php if ($label) : ?><span class="kwlabel"><?php echo esc_html($label); ?></span><?php endif; ?>
		<?php if ($badge) : ?><span class="pos"><?php echo esc_html($badge); ?></span><?php endif; ?>
	</div>
	<?php echo $inner; ?>
</div>
<?php else : ?>
<div class="shot <?php echo esc_attr($extra); ?>"<?php echo $zoom_attr; ?>>
	<?php echo $inner; ?>
	<?php if ($caption || $source) : ?>
	<div class="cap">
		<?php echo rmd_inline_html($caption); ?>
		<?php if ($source) : ?><span class="src-pill"><?php echo esc_html($source); ?></span><?php endif; ?>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>
