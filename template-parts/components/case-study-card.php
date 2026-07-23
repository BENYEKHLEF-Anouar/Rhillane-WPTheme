<?php
/**
 * Component: case-study card — editorial style (category chip, title, excerpt,
 * "read the study" link). Featured image optional: without one, the media zone
 * shows a branded placeholder so the grid stays even and nothing looks broken.
 * Styled in assets/css/rmd-chrome.css (.rmd-cscard*).
 */
defined('ABSPATH') || exit;

$terms = get_the_terms(get_the_ID(), 'case_study_cat');
$chip  = (is_array($terms) && !empty($terms) && !is_wp_error($terms))
	? $terms[0]->name
	: rmd_ft('Étude de cas', 'Case study');
?>
<article <?php post_class('rmd-cscard'); ?>>
	<a class="rmd-cscard__link" href="<?php the_permalink(); ?>">
		<div class="rmd-cscard__media<?php echo has_post_thumbnail() ? '' : ' rmd-cscard__media--empty'; ?>">
			<?php if (has_post_thumbnail()) : ?>
				<?php the_post_thumbnail('medium_large', array(
					'class'    => 'rmd-cscard__image',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)); ?>
			<?php else : // no featured image → branded placeholder, keeps the grid even ?>
				<span class="rmd-cscard__placeholder" aria-hidden="true">
					<?php echo rmd_logo_img('header', 'rmd-cscard__placeholder-logo', false); ?>
				</span>
			<?php endif; ?>
		</div>
		<div class="rmd-cscard__body">
			<span class="rmd-cscard__chip"><?php echo esc_html($chip); ?></span>
			<h2 class="rmd-cscard__title"><?php the_title(); ?></h2>
			<?php if (has_excerpt()) : ?>
				<p class="rmd-cscard__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
			<?php endif; ?>
			<span class="rmd-cscard__more">
				<?php echo esc_html(rmd_ft("Lire l'étude", 'Read the study')); ?>
				<svg width="18" height="11" viewBox="0 0 30 18" fill="none" aria-hidden="true"><path d="M2 9h24m0 0-6-6m6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
		</div>
	</a>
</article>
