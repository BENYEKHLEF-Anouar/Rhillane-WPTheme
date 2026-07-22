<?php
/**
 * Small hardening tweaks. Anything bigger (file-edit lockdown, salts, 2FA)
 * belongs in wp-config.php / server config, not in the theme.
 */
defined('ABSPATH') || exit;

// Don't advertise the exact WordPress version.
remove_action('wp_head', 'wp_generator');

// No XML-RPC — nobody publishes through it, and it's a classic brute-force surface.
// (If Jetpack or a remote-publishing app is ever needed on a subsite, revisit.)
add_filter('xmlrpc_enabled', '__return_false');

// Old header cruft nobody uses anymore.
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

/**
 * SVG uploads — NETWORK super-admins ONLY (spec §16). We use inline stroke icons
 * and occasional vector logos in the case-study sections. WordPress core does not
 * sanitise SVG, and an SVG can carry <script>, so this is deliberately gated to
 * super-admins — NOT `manage_options`, because on this multisite every subsite
 * admin has that cap yet can't otherwise run code, which would make SVG a stored-
 * XSS surface. Never open it wider without a sanitiser (e.g. enshrined/svg-sanitizer).
 */
add_filter('upload_mimes', function ($mimes) {
	if (is_super_admin()) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
	}
	return $mimes;
});

// WP's real-mime sniffing rejects SVG (it has no binary signature); re-affirm the
// type for super-admins so the upload isn't blocked as "not allowed for security".
// Uses pathinfo() rather than str_ends_with() so this can't fatal on PHP < 8.0.
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
	if (!is_super_admin()) {
		return $data;
	}
	$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	if ('svg' === $ext || 'svgz' === $ext) {
		$data['ext']  = $ext;
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}, 10, 4);

// Render SVG thumbnails at a sane size in the Media Library grid + attachment UI.
add_action('admin_head', function () {
	echo '<style>.attachment .thumbnail img[src$=".svg"],.media-icon img[src$=".svg"]{width:100%!important;height:auto!important;}</style>';
});
