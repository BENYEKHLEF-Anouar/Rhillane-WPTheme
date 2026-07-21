<?php
/**
 * Case-study archive — card grid.
 */
defined('ABSPATH') || exit;

get_header();
?>
<main class="rmd-archive rmd-archive--case-study">
	<header class="rmd-archive__head">
		<h1><?php post_type_archive_title(); ?></h1>
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
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e('No case studies yet.', 'vault-child'); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
