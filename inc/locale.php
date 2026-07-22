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
 * Resolve where a country link goes on a given subsite, keeping the visitor in
 * place across languages:
 *   'single'  → the case study with the SAME slug on that subsite (so the FR
 *               link on a case study lands on that case study in French).
 *               Falls back to the subsite's home if it isn't published there.
 *   'archive' → that subsite's case-study archive (fallback: home).
 *   'home'    → that subsite's home.
 * Uses switch_to_blog so the slug lookup and permalink resolve on the TARGET
 * site's database, not the current one.
 */
function rmd_locale_target_url($blog_id, $context, $slug) {
	switch_to_blog($blog_id);
	$url = home_url('/');
	if ('single' === $context && $slug) {
		$post = get_page_by_path($slug, OBJECT, 'case_study');
		if ($post && 'publish' === $post->post_status) {
			$url = get_permalink($post->ID);
		}
	} elseif ('archive' === $context) {
		$archive = get_post_type_archive_link('case_study');
		if ($archive) {
			$url = $archive;
		}
	}
	restore_current_blog();
	return $url;
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

	// Work out what each country link points AT. On a single case study we send
	// the visitor to the SAME case study on the target subsite (matched by slug),
	// falling back to that subsite's home if it isn't published there. On the
	// case-study archive we point at that subsite's archive; anywhere else, home.
	$context = 'home';
	$slug    = '';
	if (is_singular('case_study')) {
		$context = 'single';
		$slug    = get_post_field('post_name', get_queried_object_id());
	} elseif (is_post_type_archive('case_study')) {
		$context = 'archive';
	}

	// Build the visible entries: every live subsite that's in the map. We do NOT
	// filter by the "public" flag — staging subsites are often marked non-public,
	// and the curated map already decides what shows.
	$entries = array();
	$sites   = get_sites(array(
		'number'   => 50,
		'archived' => 0,
		'deleted'  => 0,
		'spam'     => 0,
	));
	foreach ($sites as $site) {
		$path = untrailingslashit($site->path) . '/'; // normalise: always one trailing slash
		if (!isset($map[$path])) {
			continue; // a subsite we don't expose in the switcher
		}
		$entries[] = array(
			'url'    => rmd_locale_target_url((int) $site->blog_id, $context, $slug),
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
