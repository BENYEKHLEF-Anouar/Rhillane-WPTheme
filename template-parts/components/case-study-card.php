<?php
/**
 * Component: case-study card — used by the archive (and later grids).
 */
defined('ABSPATH') || exit;
?>
<article <?php post_class('rmd-card rmd-card--case-study'); ?>>
	<a class="rmd-card__link" href="<?php the_permalink(); ?>">
		<div class="rmd-card__media">
			<?php if (has_post_thumbnail()) : ?>
				<?php the_post_thumbnail('medium_large', array(
					'class'    => 'rmd-card__image',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)); ?>
			<?php else : // no featured image → branded placeholder, grid stays even ?>
				<span class="rmd-card__media-fallback" aria-hidden="true">
					<?php echo rmd_logo_img('footer', 'rmd-card__media-logo'); ?>
				</span>
			<?php endif; ?>
		</div>
		<h2 class="rmd-card__title"><?php the_title(); ?></h2>
		<?php if (has_excerpt()) : ?>
			<p class="rmd-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
		<?php endif; ?>
	</a>
</article>
