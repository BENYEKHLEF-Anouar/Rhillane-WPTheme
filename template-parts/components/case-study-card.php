<?php
/**
 * Component: case-study card — used by the archive (and later grids).
 */
defined('ABSPATH') || exit;
?>
<article <?php post_class('rmd-card rmd-card--case-study'); ?>>
	<a class="rmd-card__link" href="<?php the_permalink(); ?>">
		<?php if (has_post_thumbnail()) : ?>
			<?php the_post_thumbnail('medium_large', array(
				'class'    => 'rmd-card__image',
				'loading'  => 'lazy',
				'decoding' => 'async',
			)); ?>
		<?php endif; ?>
		<h2 class="rmd-card__title"><?php the_title(); ?></h2>
		<?php if (has_excerpt()) : ?>
			<p class="rmd-card__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
		<?php endif; ?>
	</a>
</article>
