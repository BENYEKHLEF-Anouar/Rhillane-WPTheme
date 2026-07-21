<?php
/**
 * Component: one dark stat cell.
 * $args: style ('strip'|'card'), tag, value (inline-HTML), value_note, label, highlight (bool).
 */
defined('ABSPATH') || exit;

$style     = $args['style'] ?? 'strip';
$tag       = $args['tag'] ?? '';
$value     = $args['value'] ?? '';
$note      = $args['value_note'] ?? '';
$label     = $args['label'] ?? '';
$highlight = !empty($args['highlight']);

if ('' === $value && '' === $label) {
	return;
}

if ('card' === $style) : ?>
<div class="cell">
	<?php if ($tag) : ?><span class="tagmini"><?php echo esc_html($tag); ?></span><?php endif; ?>
	<div class="num"<?php echo $highlight ? ' style="color:#16B364;"' : ''; ?>><?php echo rmd_inline_html($value); ?></div>
	<?php if ($label) : ?><div class="delta"><?php echo esc_html($label); ?></div><?php endif; ?>
</div>
<?php else : ?>
<div class="kpi">
	<div class="v"<?php echo $highlight ? ' style="color:#16B364;"' : ''; ?>><?php echo rmd_inline_html($value); ?><?php if ($note) : ?> <span class="up"><?php echo esc_html($note); ?></span><?php endif; ?></div>
	<?php if ($label) : ?><div class="l"><?php echo esc_html($label); ?></div><?php endif; ?>
</div>
<?php endif; ?>
