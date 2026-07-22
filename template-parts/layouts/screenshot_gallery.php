<?php
/**
 * Section: screenshot_gallery — grid or stack of screenshot frames.
 */
defined('ABSPATH') || exit;

$items   = rmd_get_sub_field('items');
$columns = rmd_get_sub_field('columns') ?: '1';

if (!$items) {
	return;
}

// Only the first image of section 0 is a plausible LCP candidate → eager.
$lcp = 0 === (int) ($args['index'] ?? -1);

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));

if ('2' === $columns) : ?>
	<div class="shot-gallery">
		<?php foreach ($items as $i => $item) :
			get_template_part('template-parts/components/screenshot-frame', null, array_merge($item, array('eager' => $lcp && 0 === $i)));
		endforeach; ?>
	</div>
<?php else :
	foreach ($items as $i => $item) {
		get_template_part('template-parts/components/screenshot-frame', null, array_merge($item, array('eager' => $lcp && 0 === $i)));
	}
endif;

rmd_section_close();
