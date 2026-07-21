<?php
/**
 * Section: recap_band — dark rounded band with pill row.
 */
defined('ABSPATH') || exit;

$heading = rmd_get_sub_field('heading');
$pills   = rmd_get_sub_field('pills');

if (!$heading && !$pills) {
	return;
}
?>
<section class="rmd-sec">
	<div class="mx-auto max-w-content px-6 py-10 md:py-16">
		<div class="band">
			<div class="blob2"></div>
			<?php if ($heading) : ?><h2><?php echo esc_html($heading); ?></h2><?php endif; ?>
			<?php if ($pills) : ?>
			<div class="pillrow">
				<?php foreach ($pills as $pill) : ?>
					<span class="pill"><?php echo rmd_inline_html($pill['text'] ?? ''); ?></span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>
