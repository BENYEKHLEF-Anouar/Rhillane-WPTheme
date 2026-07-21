<?php
/**
 * Section: feature_cards — insight panel (approach cards + stat tiles + proof strip).
 */
defined('ABSPATH') || exit;

$cards     = rmd_get_sub_field('cards');
$tiles     = rmd_get_sub_field('tiles');
$highlight = rmd_get_sub_field('highlight');
$heading   = rmd_get_sub_field('heading');

if (!$cards && !$tiles && !$highlight && !$heading) {
	return;
}

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => $heading,
	'subheading' => rmd_get_sub_field('subheading'),
));

$cards_kicker = rmd_get_sub_field('cards_kicker');
if ($cards) : ?>
	<?php if ($cards_kicker) : ?><div class="kicker" style="margin-top:40px;"><?php echo esc_html($cards_kicker); ?></div><?php endif; ?>
	<div class="appr-grid">
		<?php foreach ($cards as $card) : ?>
		<div class="appr">
			<div class="ahead">
				<?php if (!empty($card['icon'])) : ?><div class="ic"><?php echo rmd_svg($card['icon']); ?></div><?php endif; ?>
				<h4><?php echo esc_html($card['heading'] ?? ''); ?></h4>
			</div>
			<?php if (!empty($card['body'])) : ?><p><?php echo rmd_inline_html($card['body']); ?></p><?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
<?php endif;

$tiles_kicker = rmd_get_sub_field('tiles_kicker');
if ($tiles) : ?>
	<?php if ($tiles_kicker) : ?><div class="kicker" style="margin-top:40px;"><?php echo esc_html($tiles_kicker); ?></div><?php endif; ?>
	<div class="istat-grid">
		<?php foreach ($tiles as $tile) : ?>
		<div class="istat">
			<div class="v"><?php echo esc_html($tile['value'] ?? ''); ?></div>
			<?php if (!empty($tile['label'])) : ?><div class="l"><?php echo nl2br(esc_html($tile['label'])); ?></div><?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>
<?php endif;

if ($highlight) : ?>
	<div class="proof-strip"><?php echo rmd_inline_html($highlight); ?></div>
<?php endif;

rmd_section_close();
