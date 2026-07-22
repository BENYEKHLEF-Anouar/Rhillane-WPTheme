<?php
/**
 * Country / language switcher for the case-study header chrome.
 *
 * Multisite-aware: lists the network's subsites (one per country/language),
 * builds each link from get_home_url() so the URLs are correct on staging AND
 * production (no hardcoded domain), and marks the current subsite active
 * server-side (no client-side flash). JS only opens/closes the dropdown.
 *
 * Rendered from template-parts/site-header.php, so it only appears where the
 * RMD chrome does (case-study single + archive) — nothing on the parent theme.
 *
 * The path→flag/label map below overlaps conceptually with the hreflang mapping
 * stubbed in inc/seo.php; if that gets built, share one source of truth.
 */
defined('ABSPATH') || exit;

/**
 * Path → { flag, label } for each language subsite. Filterable so the mapping
 * can change without touching code. Keys are site paths as WP stores them
 * (leading + trailing slash; the main site is '/').
 */
function rmd_locale_map() {
	return apply_filters('rmd_locale_map', array(
		'/'        => array('flag' => '🇲🇦', 'label' => 'Morocco'),
		'/fr-fr/'  => array('flag' => '🇫🇷', 'label' => 'France'),
		'/en-us/'  => array('flag' => '🇺🇸', 'label' => 'US'),
		'/en-ae/'  => array('flag' => '🇦🇪', 'label' => 'UAE'),
	));
}

/**
 * Echo the switcher. Renders nothing on single-site installs or when fewer
 * than two mapped subsites exist (a one-country switcher is pointless).
 */
function rmd_locale_switcher() {
	if (!is_multisite() || !function_exists('get_sites')) {
		return;
	}

	$map     = rmd_locale_map();
	$current = get_current_blog_id();

	// Build the visible entries: only public, live subsites that are in the map.
	$entries = array();
	$sites   = get_sites(array(
		'number'   => 50,
		'archived' => 0,
		'deleted'  => 0,
		'spam'     => 0,
		'public'   => 1,
	));
	foreach ($sites as $site) {
		$path = $site->path;
		if (!isset($map[$path])) {
			continue; // a subsite we don't expose in the switcher
		}
		$entries[] = array(
			'url'    => get_home_url($site->blog_id, '/'),
			'flag'   => $map[$path]['flag'],
			'label'  => $map[$path]['label'],
			'active' => ((int) $site->blog_id === (int) $current),
		);
	}

	if (count($entries) < 2) {
		return; // nothing meaningful to switch between
	}

	// Flag shown on the button = the current site's (globe fallback).
	$current_flag = '🌐';
	foreach ($entries as $e) {
		if ($e['active']) {
			$current_flag = $e['flag'];
			break;
		}
	}
	?>
	<div class="rmd-locale-switch">
		<button class="rmd-locale-btn" type="button" aria-haspopup="true" aria-expanded="false">
			<span class="rmd-locale-current" aria-hidden="true"><?php echo esc_html($current_flag); ?></span>
			<span class="rmd-locale-arrow" aria-hidden="true">
				<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 4.5L6 7.5L9 4.5" stroke="#041135" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</span>
			<span class="rmd-sr-only"><?php esc_html_e('Changer de pays', 'vault-child'); ?></span>
		</button>
		<div class="rmd-locale-menu" role="menu">
			<?php foreach ($entries as $e) : ?>
				<a href="<?php echo esc_url($e['url']); ?>"<?php echo $e['active'] ? ' class="active" aria-current="true"' : ''; ?> role="menuitem" aria-label="<?php echo esc_attr($e['label']); ?>">
					<span class="rmd-locale-flag" aria-hidden="true"><?php echo esc_html($e['flag']); ?></span>
					<span class="rmd-locale-label"><?php echo esc_html($e['label']); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}
