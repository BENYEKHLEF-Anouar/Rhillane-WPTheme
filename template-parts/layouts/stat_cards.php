<?php
/**
 * Section: stat_cards — white number-card grid (contexte / trafic).
 * accent "negative" = red numbers · "positive" = green numbers.
 */
defined('ABSPATH') || exit;

$accent = rmd_get_sub_field('accent') ?: 'negative';
$cards  = rmd_get_sub_field('cards');

if (!$cards) {
	return;
}

$num_color = 'negative' === $accent ? '#E4004D' : '#16B364';

rmd_section_open();
get_template_part('template-parts/components/section-header', null, array(
	'eyebrow'    => rmd_get_sub_field('eyebrow'),
	'heading'    => rmd_get_sub_field('heading'),
	'subheading' => rmd_get_sub_field('subheading'),
));
?>
<div class="meaning-grid" style="--mg-cols:<?php echo (int) count($cards); ?>">
	<?php foreach ($cards as $card) :
		$icon = $card['icon'] ?? '';
	?>
	<div class="meaning">
		<?php if ($icon) : ?>
		<div class="mrow">
			<div class="mico"><?php echo rmd_svg($icon); ?></div>
			<div class="mn" style="color:<?php echo esc_attr($num_color); ?>;"><?php echo esc_html($card['value'] ?? ''); ?></div>
		</div>
		<?php else : ?>
		<div class="mn" style="color:<?php echo esc_attr($num_color); ?>;"><?php echo esc_html($card['value'] ?? ''); ?></div>
		<?php endif; ?>
		<?php if (!empty($card['label'])) : ?><div class="mt"><?php echo esc_html($card['label']); ?></div><?php endif; ?>
		<?php if (!empty($card['body'])) : ?><div class="md"><?php echo rmd_inline_html($card['body']); ?></div><?php endif; ?>
	</div>
	<?php endforeach; ?>
</div>
<?php
rmd_section_close();
