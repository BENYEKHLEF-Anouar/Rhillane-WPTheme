<?php
/**
 * Section: cta — centered final call-to-action.
 */
defined('ABSPATH') || exit;

$heading        = rmd_get_sub_field('heading');
$heading_accent = rmd_get_sub_field('heading_accent');
$button_label   = rmd_get_sub_field('button_label');

if (!$heading && !$button_label) {
	return;
}

$eyebrow       = rmd_get_sub_field('eyebrow');
$heading_after = rmd_get_sub_field('heading_after');
$subheading    = rmd_get_sub_field('subheading');
$button_url    = rmd_get_sub_field('button_url');
$contact_line  = rmd_get_sub_field('contact_line');

if (!$contact_line) {
	$email        = rmd_get_field('rmd_contact_email', 'option') ?: 'contact@rhillane.com';
	$phone        = rmd_get_field('rmd_contact_phone', 'option') ?: '+212 663-091166';
	$contact_line = $email . ' · ' . $phone;
}

rmd_section_open('text-center');
?>
<?php if ($eyebrow) : ?><div class="eyebrow" style="margin-left:auto;margin-right:auto;"><?php echo esc_html($eyebrow); ?></div><?php endif; ?>
<?php if ($heading || $heading_accent) : ?>
<h2 class="section-title" style="text-align:center;"><?php
	echo esc_html($heading);
	if ($heading_accent) {
		echo ' <span class="grad-attention">' . esc_html($heading_accent) . '</span>';
	}
	echo esc_html($heading_after);
?></h2>
<?php endif; ?>
<?php if ($subheading) : ?><p class="section-sub" style="margin-left:auto;margin-right:auto;text-align:center;"><?php echo rmd_inline_html($subheading); ?></p><?php endif; ?>
<?php if ($button_label && $button_url) : ?>
<div class="mt-9 flex flex-wrap items-center justify-center gap-4">
	<?php
	// Routed through the single link renderer (§10.3) so escaping / rel / target
	// stay consistent across every section. Output matches the previous inline <a>.
	echo rmd_render_link(
		array('link_type' => 'url', 'url' => $button_url),
		esc_html($button_label),
		'btn-cta inline-flex items-center gap-2.5 rounded-lg px-8 py-4 text-base font-medium text-white shadow-card hover:opacity-95'
	);
	?>
	<?php if ($contact_line) : ?><span class="text-sm text-slight"><?php echo esc_html($contact_line); ?></span><?php endif; ?>
</div>
<?php endif; ?>
<?php
rmd_section_close();
