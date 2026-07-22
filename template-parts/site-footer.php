<?php
/**
 * Site footer — closes the document (footer → wp_footer → </body></html>).
 * Rendered by rmd_render_footer(). Editable: logo + copyright from Site Settings.
 */
defined('ABSPATH') || exit;

$flogo = rmd_get_field('rmd_footer_logo', 'option');
$copy  = rmd_get_field('rmd_footer_copyright', 'option');
?>
<footer class="rmd-site-footer">
	<div class="rmd-footer-inner">
		<?php if ($flogo) : ?>
			<?php echo rmd_image($flogo, array('size' => 'medium', 'class' => 'rmd-footer-logo')); ?>
		<?php endif; ?>
		<p class="rmd-footer-copy">
			<?php
			if ($copy) {
				echo rmd_inline_html($copy);
			} else {
				printf(
					/* translators: 1: year, 2: site name */
					esc_html__('© %1$s %2$s. Tous droits réservés.', 'vault-child'),
					esc_html(date_i18n('Y')),
					esc_html(get_bloginfo('name'))
				);
			}
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
