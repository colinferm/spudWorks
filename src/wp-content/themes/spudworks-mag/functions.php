<?php
/**
 * spudWorks Magazine — theme functions.
 *
 * Conventions (per theme spec): tabs for indentation, camelCase function and
 * variable names, ragged multi-line assignments. The theme is multisite-aware:
 * no hardcoded table names or single-site assumptions.
 */

error_reporting(E_ERROR);

$themeObj = wp_get_theme('spudworks-mag');

define("THEMENAME", "spudWorks Magazine");
define("SHORTNAME", "spudmag");
define("THEMEVERSION", $themeObj['Version']);
define("THEMEDATEFORMAT", get_option('date_format'));

// Post-meta keys, defined once so templates and admin agree on them.
define("METATEMPLATE", "_spudmag_template");
define("METASUBHEAD", "_spudmag_subhead");
define("METAPINNED", "_spudmag_pinned");

// Theme directory + URI, resolved per-site so the theme is multisite-safe.
define("THEMEDIR", get_template_directory());
define("THEMEURI", get_template_directory_uri());

require_once THEMEDIR . '/lib/helpers.php';
require_once THEMEDIR . '/lib/meta-tags.php';

if (is_admin()) {
	require_once THEMEDIR . '/admin/meta-boxes.php';
}

/**
 * Register theme supports, menus, and image sizes.
 */
function spudmagSetup() {
	add_theme_support('post-thumbnails');
	add_theme_support('title-tag');
	add_theme_support('automatic-feed-links');
	add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));
	add_theme_support('responsive-embeds');

	register_nav_menus(array(
		'primary' => __('Primary Menu', 'spudworks-mag'),
		'footer'  => __('Footer Menu', 'spudworks-mag')
	));

	// Card and hero crops. Cropped sizes give the home grid predictable shapes.
	add_image_size('spudmagHero', 1600, 720, true);
	add_image_size('spudmagLandscape', 800, 500, true);
	add_image_size('spudmagSquare', 700, 700, true);
	add_image_size('spudmagPortrait', 700, 940, true);
}
add_action('after_setup_theme', 'spudmagSetup');

/**
 * Register the Showcase-template widget area.
 */
function spudmagWidgets() {
	register_sidebar(array(
		'name'          => __('Showcase Sidebar', 'spudworks-mag'),
		'id'            => 'showcase-sidebar',
		'description'   => __('Appears beside content using the Showcase template.', 'spudworks-mag'),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>'
	));
}
add_action('widgets_init', 'spudmagWidgets');

/**
 * Show just the term/archive name in archive headings (drop the default
 * "Category:" / "Tag:" / "Archives:" prefixes) so the name stands alone.
 */
function spudmagArchiveTitle($title) {
	if (is_category() || is_tag() || is_tax()) {
		$title = single_term_title('', false);
	} elseif (is_author()) {
		$title = get_the_author();
	} elseif (is_post_type_archive()) {
		$title = post_type_archive_title('', false);
	}
	return $title;
}
add_filter('get_the_archive_title', 'spudmagArchiveTitle');

/**
 * Enqueue the compiled stylesheet and front-end scripts.
 */
function spudmagAssets() {
	wp_enqueue_style('spudmag-main', THEMEURI . '/css/spudworks-mag.css', array(), THEMEVERSION);

	wp_enqueue_script('spudmag-main', THEMEURI . '/js/main.js', array(), THEMEVERSION, true);
	wp_enqueue_script('spudmag-lightbox', THEMEURI . '/js/lightbox.js', array(), THEMEVERSION, true);
}
add_action('wp_enqueue_scripts', 'spudmagAssets');
