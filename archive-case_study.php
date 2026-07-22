<?php
/**
 * Case-study archive — editorial card grid, styled to match the case-study
 * design (tokens/fonts in assets/css/rmd-chrome.css, loaded on this template).
 */
defined('ABSPATH') || exit;

rmd_render_header();
?>
<main class="rmd-archive rmd-archive--case-study">
	<header class="rmd-archive__head">
		<span class="rmd-arch-eyebrow"><?php esc_html_e('SEO · Résultats clients', 'vault-child'); ?></span>
		<h1 class="rmd-arch-title"><?php post_type_archive_title(); ?></h1>
		<p class="rmd-arch-sub"><?php esc_html_e("Des marques hissées en première page de Google — résultats réels, chiffres à l'appui.", 'vault-child'); ?></p>
	</header>

	<?php if (have_posts()) : ?>
		<div class="rmd-archive__grid">
			<?php
			while (have_posts()) {
				the_post();
				get_template_part('template-parts/components/case-study-card');
			}
			?>
		</div>
		<?php the_posts_pagination(array(
			'mid_size'  => 1,
			'prev_text' => '←',
			'next_text' => '→',
		)); ?>
	<?php else : ?>
		<p class="rmd-arch-empty"><?php esc_html_e('Aucune étude de cas pour le moment.', 'vault-child'); ?></p>
	<?php endif; ?>
</main>
<?php
rmd_render_footer();
