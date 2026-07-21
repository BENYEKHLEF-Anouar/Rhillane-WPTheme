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
