<?php
/**
 * Component: section header — eyebrow pill + H2 + sub.
 * $args: eyebrow, heading (inline-HTML), subheading (inline-HTML), center (bool).
 */
defined('ABSPATH') || exit;

$eyebrow    = $args['eyebrow'] ?? '';
$heading    = $args['heading'] ?? '';
$subheading = $args['subheading'] ?? '';
$center     = !empty($args['center']);

if (!$eyebrow && !$heading && !$subheading) {
	return;
}
?>
<?php if ($eyebrow) : ?>
	<div class="eyebrow"<?php echo $center ? ' style="margin-left:auto;margin-right:auto;"' : ''; ?>><?php echo esc_html($eyebrow); ?></div>
<?php endif; ?>
<?php if ($heading) : ?>
	<h2 class="section-title"<?php echo $center ? ' style="text-align:center;"' : ''; ?>><?php echo rmd_inline_html($heading); ?></h2>
<?php endif; ?>
<?php if ($subheading) : ?>
	<p class="section-sub"<?php echo $center ? ' style="margin-left:auto;margin-right:auto;text-align:center;"' : ''; ?>><?php echo rmd_inline_html($subheading); ?></p>
<?php endif; ?>
