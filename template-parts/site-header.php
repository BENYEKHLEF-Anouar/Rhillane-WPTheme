<?php
/**
 * Site header — opens the document (doctype → head → wp_head → body → header).
 * Rendered by rmd_render_header() from the case-study templates, NOT a global
 * header.php (that would override parent Vault site-wide — W3).
 *
 * Editable: logo / CTA / sticky from Site Settings; nav from the `rmd_header`
 * menu location (Appearance → Menus).
 */
defined('ABSPATH') || exit;

$logo      = rmd_get_field('rmd_header_logo', 'option');
$cta_label = rmd_get_field('rmd_header_cta_label', 'option') ?: 'Audit web gratuit';
$cta_url   = rmd_get_field('rmd_header_cta_url', 'option') ?: '#contact';
$sticky    = rmd_get_field('rmd_header_sticky', 'option');
$sticky    = (null === $sticky) ? true : (bool) $sticky; // default on

$has_menu = has_nav_menu('rmd_header');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('rmd-body'); ?>>
<?php wp_body_open(); ?>

<header id="site-header" class="rmd-site-header<?php echo $sticky ? ' is-sticky' : ''; ?>">
	<div class="rmd-header-inner">
		<a href="<?php echo esc_url(home_url('/')); ?>" class="rmd-logo" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
			<?php
			if ($logo) {
				echo rmd_image($logo, array('size' => 'medium', 'class' => 'rmd-logo-img', 'eager' => true));
			} else {
				echo '<span class="rmd-logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
			}
			?>
		</a>

		<nav class="rmd-nav" aria-label="<?php esc_attr_e('Navigation principale', 'vault-child'); ?>">
			<?php if ($has_menu) : ?>
				<?php wp_nav_menu(array(
					'theme_location' => 'rmd_header',
					'container'      => false,
					'menu_class'     => 'rmd-nav-list',
					'depth'          => 1,
					'fallback_cb'    => false,
				)); ?>
			<?php else : // Mariner-style default until a menu is assigned in Appearance → Menus ?>
				<ul class="rmd-nav-list">
					<li><a href="#resultats"><?php esc_html_e('Résultats', 'vault-child'); ?></a></li>
					<li><a href="#methode"><?php esc_html_e('Notre méthode', 'vault-child'); ?></a></li>
					<li><a href="#contact"><?php esc_html_e('Contact', 'vault-child'); ?></a></li>
				</ul>
			<?php endif; ?>
		</nav>

		<?php rmd_locale_switcher(); ?>

		<?php if ($cta_label && $cta_url) : ?>
			<a href="<?php echo esc_url($cta_url); ?>" class="rmd-cta"><?php echo esc_html($cta_label); ?></a>
		<?php endif; ?>

		<button id="mobile-menu-btn" type="button" class="rmd-burger" aria-label="<?php esc_attr_e('Ouvrir le menu', 'vault-child'); ?>" aria-controls="mobile-menu-panel" aria-expanded="false">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
		</button>
	</div>
</header>

<div id="mobile-menu-panel" class="rmd-mobile-panel" aria-hidden="true">
	<div class="rmd-mobile-inner">
		<div class="rmd-mobile-top">
			<?php if ($logo) : ?>
				<?php echo rmd_image($logo, array('size' => 'medium', 'class' => 'rmd-logo-img')); ?>
			<?php else : ?>
				<span class="rmd-mobile-brand"><?php echo esc_html(get_bloginfo('name')); ?></span>
			<?php endif; ?>
			<button id="mobile-menu-close" type="button" class="rmd-burger" aria-label="<?php esc_attr_e('Fermer le menu', 'vault-child'); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		</div>
		<nav aria-label="<?php esc_attr_e('Navigation mobile', 'vault-child'); ?>">
			<?php if ($has_menu) : ?>
				<?php wp_nav_menu(array(
					'theme_location' => 'rmd_header',
					'container'      => false,
					'menu_class'     => 'rmd-mobile-list',
					'depth'          => 1,
					'fallback_cb'    => false,
				)); ?>
			<?php else : ?>
				<ul class="rmd-mobile-list">
					<li><a href="#resultats"><?php esc_html_e('Résultats', 'vault-child'); ?></a></li>
					<li><a href="#methode"><?php esc_html_e('Notre méthode', 'vault-child'); ?></a></li>
					<li><a href="#contact"><?php esc_html_e('Contact', 'vault-child'); ?></a></li>
				</ul>
			<?php endif; ?>
		</nav>
		<?php if ($cta_label && $cta_url) : ?>
			<a href="<?php echo esc_url($cta_url); ?>" class="rmd-cta rmd-mobile-cta mobile-nav-link"><?php echo esc_html($cta_label); ?></a>
		<?php endif; ?>
	</div>
</div>

<?php if ($sticky) : // a fixed header needs a spacer; a static one sits in flow ?>
<div class="rmd-header-spacer" aria-hidden="true"></div>
<?php endif; ?>
