<?php
/**
 * Section: hero — split hero with navy stat card.
 */
defined('ABSPATH') || exit;

$eyebrow        = rmd_get_sub_field('eyebrow');
$kicker         = rmd_get_sub_field('kicker');
$heading        = rmd_get_sub_field('heading');
$heading_accent = rmd_get_sub_field('heading_accent');
$heading_after  = rmd_get_sub_field('heading_after');
$subheading     = rmd_get_sub_field('subheading');
$tags           = rmd_get_sub_field('tags');
$badge          = rmd_get_sub_field('badge');
$show_contact   = rmd_get_sub_field('show_contact');
$stats          = rmd_get_sub_field('stats');

if (!$heading && !$kicker && !$stats) {
	return;
}

// Contact line — per-site values from Site Settings, sensible fallbacks.
$contact_email = rmd_get_field('rmd_contact_email', 'option') ?: 'contact@rhillane.com';
$contact_phone = rmd_get_field('rmd_contact_phone', 'option') ?: '+212 663-091166';
$contact_site  = rmd_get_field('rmd_contact_site', 'option') ?: 'www.rhillane.com';
?>
<section class="relative overflow-hidden bg-[radial-gradient(1100px_600px_at_88%_-8%,rgba(228,0,77,0.08),transparent_60%),radial-gradient(800px_500px_at_-5%_110%,rgba(57,67,255,0.05),transparent_55%)]">
	<div class="mx-auto grid max-w-content items-center gap-10 px-6 py-16 md:py-24 lg:grid-cols-[1.15fr_0.85fr]">
		<div>
			<?php if ($eyebrow) : ?><div class="eyebrow"><?php echo esc_html($eyebrow); ?></div><?php endif; ?>
			<?php if ($kicker) : ?><div class="text-[20px] font-medium text-redder"><?php echo esc_html($kicker); ?></div><?php endif; ?>
			<?php if ($heading || $heading_accent) : ?>
			<h1 class="mt-3 text-[40px] md:text-[56px] font-light leading-[1.05] tracking-tighter2 text-ink"><?php
				echo esc_html($heading);
				if ($heading_accent) {
					echo ' <b class="grad-attention font-semibold">' . esc_html($heading_accent) . '</b>';
				}
				echo esc_html($heading_after);
			?></h1>
			<?php endif; ?>
			<?php if ($subheading) : ?><p class="section-sub mt-6"><?php echo rmd_inline_html($subheading); ?></p><?php endif; ?>

			<?php if ($tags) : ?>
			<div class="mt-7 flex flex-wrap items-center gap-2.5">
				<?php foreach ($tags as $tag) : ?>
					<span class="tag"><?php echo rmd_svg($tag['icon'] ?? ''); ?><?php echo esc_html($tag['label'] ?? ''); ?></span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ($badge) : ?>
			<span class="mt-7 inline-block rounded-md bg-redder px-5 py-2.5 text-sm font-semibold text-white shadow-growth"><?php echo esc_html($badge); ?></span>
			<?php endif; ?>

			<?php if ($show_contact) : ?>
			<div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-2 border-l-[3px] border-redder pl-4 text-sm text-slight">
				<a href="mailto:<?php echo esc_attr($contact_email); ?>" class="inline-flex items-center gap-2 hover:text-ink"><?php echo esc_html($contact_email); ?></a>
				<a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $contact_phone)); ?>" class="inline-flex items-center gap-2 hover:text-ink"><?php echo esc_html($contact_phone); ?></a>
				<span class="inline-flex items-center gap-2"><?php echo esc_html($contact_site); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<?php if ($stats) : ?>
		<div class="flex justify-end">
			<div class="hero-badge w-full max-w-[360px]">
				<?php foreach ($stats as $i => $stat) : ?>
					<?php if ($i > 0) : ?><hr><?php endif; ?>
					<div class="big"><?php echo rmd_inline_html($stat['value'] ?? ''); ?></div>
					<?php if (!empty($stat['label'])) : ?><div class="lbl"><?php echo esc_html($stat['label']); ?></div><?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>
</section>
