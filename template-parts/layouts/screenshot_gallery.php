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

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));

if ('2' === $columns) : ?>
	<div class="shot-gallery">
		<?php foreach ($items as $item) :
			get_template_part('template-parts/components/screenshot-frame', null, $item);
		endforeach; ?>
	</div>
<?php else :
	foreach ($items as $item) {
		get_template_part('template-parts/components/screenshot-frame', null, $item);
	}
endif;

rmd_section_close();
