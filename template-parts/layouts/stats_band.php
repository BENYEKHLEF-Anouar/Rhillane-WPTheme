<?php
/**
 * Section: stats_band — dark metric row.
 * style "strip" = full-bleed KPI band · style "card" = rounded statduo card.
 */
defined('ABSPATH') || exit;

$style = rmd_get_sub_field('style') ?: 'strip';
$items = rmd_get_sub_field('items');

if (!$items) {
	return;
}

if ('strip' === $style) : ?>
<div class="kpi-strip">
	<div class="mx-auto max-w-content px-6">
		<div class="kpi-grid" style="--kpi-cols:<?php echo (int) count($items); ?>">
			<?php foreach ($items as $item) :
				get_template_part('template-parts/components/stat-cell', null, array_merge($item, array('style' => 'strip')));
			endforeach; ?>
		</div>
	</div>
</div>
<?php else :
	rmd_section_open();
	get_template_part('template-parts/components/section-header', null, array(
		'eyebrow'    => rmd_get_sub_field('eyebrow'),
		'heading'    => rmd_get_sub_field('heading'),
		'subheading' => rmd_get_sub_field('subheading'),
	));
	?>
	<div class="statduo mt-9">
		<?php foreach ($items as $item) :
			get_template_part('template-parts/components/stat-cell', null, array_merge($item, array('style' => 'card')));
		endforeach; ?>
	</div>
	<?php
	rmd_section_close();
endif;
