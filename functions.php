<?php

/**
 * Child Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package UiCore
 */
defined('ABSPATH') || exit;

add_action( 'wp_enqueue_scripts', 'ui_child_enqueue_styles' );
function ui_child_enqueue_styles() {
	if (!class_exists('\UiCore\Core')){
		wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	}
     wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css' );
}

/* YOU CAN START EDITING HERE! */
/*
 * a list of complete hooks and filters can be found here
 * https://help.uicore.co/docs/hooks-and-filters
 *
*/
