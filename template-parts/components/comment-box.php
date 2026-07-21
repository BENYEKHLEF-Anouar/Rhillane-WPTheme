<?php
/**
 * Component: bordered comment card.
 * $args: text (inline-HTML; blank line = new paragraph), class (extra classes).
 */
defined('ABSPATH') || exit;

$text = trim((string) ($args['text'] ?? ''));
if ('' === $text) {
	return;
}

$paragraphs = preg_split('/\n\s*\n/', $text);
?>
<div class="comment <?php echo esc_attr($args['class'] ?? ''); ?>">
	<?php foreach ($paragraphs as $p) : ?>
		<p><?php echo rmd_inline_html(trim($p)); ?></p>
	<?php endforeach; ?>
</div>
