<?php
/**
 * Section: table_split — data table + companion column.
 * media_position "left": screenshot left, table + comment right (§ concurrents).
 * media_position "none": table left, side stats + comment right (§ mots-clés).
 */
defined('ABSPATH') || exit;

$columns    = rmd_get_sub_field('table_columns');
$rows       = rmd_get_sub_field('table_rows');
$side_stats = rmd_get_sub_field('side_stats');
$comment    = rmd_get_sub_field('comment');
$media_pos  = rmd_get_sub_field('media_position') ?: 'none';

if (!$rows && !$side_stats && !$comment) {
	return;
}

$lcp = 0 === (int) ($args['index'] ?? -1);

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));
?>
<div class="grid-2 mt-9 items-start">
	<?php if ('left' === $media_pos) : ?>
		<?php get_template_part('template-parts/components/screenshot-frame', null, array(
			'image'    => rmd_get_sub_field('media_image'),
			'style'    => 'plain',
			'zoomable' => true,
			'caption'  => rmd_get_sub_field('media_caption'),
			'source'   => rmd_get_sub_field('media_source'),
			'class'    => 'mt-0',
			'eager'    => $lcp,
		)); ?>
		<div>
			<?php get_template_part('template-parts/components/data-table', null, array('columns' => $columns, 'rows' => $rows)); ?>
			<?php if ($side_stats) : ?>
			<div class="statduo mt-6">
				<?php foreach ($side_stats as $stat) :
					get_template_part('template-parts/components/stat-cell', null, array_merge($stat, array('style' => 'card')));
				endforeach; ?>
			</div>
			<?php endif; ?>
			<?php get_template_part('template-parts/components/comment-box', null, array('text' => $comment, 'class' => 'mt-6')); ?>
		</div>
	<?php else : ?>
		<?php get_template_part('template-parts/components/data-table', null, array('columns' => $columns, 'rows' => $rows)); ?>
		<div>
			<?php if ($side_stats) : ?>
			<div class="statduo">
				<?php foreach ($side_stats as $stat) :
					get_template_part('template-parts/components/stat-cell', null, array_merge($stat, array('style' => 'card')));
				endforeach; ?>
			</div>
			<?php endif; ?>
			<?php get_template_part('template-parts/components/comment-box', null, array('text' => $comment, 'class' => 'mt-6')); ?>
		</div>
	<?php endif; ?>
</div>
<?php
rmd_section_close();
