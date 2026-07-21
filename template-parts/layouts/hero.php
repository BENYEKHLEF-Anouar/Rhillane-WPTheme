<?php
/**
 * Section: hero — layout key "hero" (keys match file names 1:1).
 * $args['index'] = position on the page; 0 = above the fold → eager media.
 */
defined('ABSPATH') || exit;

$index   = $args['index'] ?? 0;
$heading = rmd_get_sub_field('heading');
$sub     = rmd_get_sub_field('subheading');
$media   = rmd_get_sub_field('media');

if (!$heading && !$sub && !$media) {
	return; // an empty section renders nothing — never a broken box
}
?>
<section class="rmd-section rmd-hero">
	<?php if ($media) : ?>
		<?php echo rmd_image($media, array('eager' => 0 === $index, 'class' => 'rmd-hero__media')); ?>
	<?php endif; ?>
	<?php if ($heading) : ?>
		<h1 class="rmd-hero__heading"><?php echo esc_html($heading); ?></h1>
	<?php endif; ?>
	<?php if ($sub) : ?>
		<p class="rmd-hero__sub"><?php echo esc_html($sub); ?></p>
	<?php endif; ?>
</section>
