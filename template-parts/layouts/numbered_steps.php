<?php
/**
 * Section: numbered_steps — 01–04 method cards, bullets one-per-line.
 */
defined('ABSPATH') || exit;

$steps = rmd_get_sub_field('steps');

if (!$steps) {
	return;
}

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));
?>
<div class="real-grid mt-9">
	<?php foreach ($steps as $i => $step) :
		$bullets = array_filter(array_map('trim', explode("\n", (string) ($step['items'] ?? ''))));
	?>
	<div class="real">
		<div class="num"><?php echo esc_html(sprintf('%02d', $i + 1)); ?></div>
		<?php if (!empty($step['heading'])) : ?><h4><?php echo esc_html($step['heading']); ?></h4><?php endif; ?>
		<?php if ($bullets) : ?>
		<ul>
			<?php foreach ($bullets as $bullet) : ?>
				<li><?php echo esc_html($bullet); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
</div>
<?php
rmd_section_close();
