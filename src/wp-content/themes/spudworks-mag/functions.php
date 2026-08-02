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
define("METARATING", "_spudmag_rating");
define("METAREVIEWITEM", "_spudmag_review_item");
define("METAREVIEWTYPE", "_spudmag_review_type");
define("METATODOS", "_spudmag_todos");

// Theme directory + URI, resolved per-site so the theme is multisite-safe.
define("THEMEDIR", get_template_directory());
define("THEMEURI", get_template_directory_uri());

require_once THEMEDIR . '/lib/helpers.php';
require_once THEMEDIR . '/lib/meta-tags.php';
require_once THEMEDIR . '/lib/schema.php';
require_once THEMEDIR . '/lib/shortcodes.php';
require_once THEMEDIR . '/lib/widget-featured-post.php';
require_once THEMEDIR . '/lib/comments.php';
require_once THEMEDIR . '/lib/comment-security.php';
require_once THEMEDIR . '/lib/calendar-feed.php';

if (is_admin()) {
	require_once THEMEDIR . '/admin/meta-boxes.php';
	require_once THEMEDIR . '/admin/editor-tools.php';
	require_once THEMEDIR . '/admin/todos.php';
	require_once THEMEDIR . '/admin/todos-page.php';
	require_once THEMEDIR . '/admin/todos-dashboard.php';
	require_once THEMEDIR . '/admin/calendar-settings.php';
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
	// Shared widget chrome so every area marks up the same way.
	$chrome = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>'
	);

	register_sidebar(array(
		'name'        => __('Showcase Sidebar', 'spudworks-mag'),
		'id'          => 'showcase-sidebar',
		'description' => __('Appears beside content using the Showcase template.', 'spudworks-mag')
	) + $chrome);

	// Three footer columns. The right column is the home for a Search widget.
	$footerColumns = array(
		'footer-1' => __('Footer Column 1 (left)', 'spudworks-mag'),
		'footer-2' => __('Footer Column 2 (center)', 'spudworks-mag'),
		'footer-3' => __('Footer Column 3 (right)', 'spudworks-mag')
	);

	foreach ($footerColumns as $id => $name) {
		register_sidebar(array(
			'name'        => $name,
			'id'          => $id,
			'description' => __('Footer widget column.', 'spudworks-mag')
		) + $chrome);
	}

	register_widget('Spudmag_Featured_Post_Widget');
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
 * Render any core/search block (e.g. a Search widget in the sidebar or a footer
 * column) as the compact hazard-stripe search box used in the header/footer, so
 * every search field on the site shares one look. Returns the search-box
 * partial markup in place of the block's default wp-block-search output.
 */
function spudmagRenderSearchBlock($blockContent, $block) {
	ob_start();
	get_template_part('templates/search-box', '', array('class' => 'search-box--widget'));
	return ob_get_clean();
}
add_filter('render_block_core/search', 'spudmagRenderSearchBlock', 10, 2);

/**
 * Ensure rendered images carry the attachment's alt text. The card and hero
 * templates pass 'alt' => '' on their decorative crops; this filter runs after
 * that merge and refills the alt from the attachment meta when one exists
 * (helps accessibility + search), leaving it empty when none is set so
 * genuinely decorative images stay decorative. Also covers galleries and any
 * future image without per-template edits.
 */
function spudmagImageAlt($attr, $attachment, $size) {
	if (empty($attr['alt'])) {
		$alt = trim(wp_strip_all_tags(get_post_meta($attachment->ID, '_wp_attachment_image_alt', true)));
		if ($alt !== '') {
			$attr['alt'] = $alt;
		}
	}
	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'spudmagImageAlt', 10, 3);

/**
 * Give gallery thumbnails a correct `sizes` value. The gallery grid stretches
 * the 150px thumbnail crop to fill its (larger) cell, but WordPress sets
 * sizes="...150px" on eager-loaded images, so on standard-density screens the
 * browser fetches the tiny crop and upscales it (blurry) while lazy images get
 * sizes="auto" and pick the sharp larger source. Setting sizes here makes the
 * browser choose the larger srcset candidate for every thumbnail, lazy or not.
 */
function spudmagGalleryThumbSizes($attr, $attachment, $size) {
	if ($size === 'thumbnail' && !empty($attr['srcset'])) {
		$attr['sizes'] = '(max-width: 767px) 45vw, 30vw';
	}
	return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'spudmagGalleryThumbSizes', 10, 3);

/**
 * Drop the inline width WordPress bakes into [caption] wrappers. Core sizes the
 * `.wp-caption` div to the image width + 10px via an inline `style="width:Npx"`;
 * inside the narrower Showcase content column that fixed pixel width can exceed
 * the column and force the whole layout to overflow horizontally on mobile.
 * Returning 0 here suppresses the inline width entirely (it overrides any width
 * passed by the shortcode), leaving the figure and its image — already capped at
 * max-width:100% — free to size fluidly to the column.
 */
function spudmagCaptionWidth($width, $atts, $content) {
	return 0;
}
add_filter('img_caption_shortcode_width', 'spudmagCaptionWidth', 10, 3);

/**
 * Enqueue the compiled stylesheet and front-end scripts.
 */
function spudmagAssets() {
	wp_enqueue_style('spudmag-main', THEMEURI . '/css/spudworks-mag.css', array(), THEMEVERSION);

	wp_enqueue_script('spudmag-main', THEMEURI . '/js/main.js', array(), THEMEVERSION, true);
	wp_enqueue_script('spudmag-lightbox', THEMEURI . '/js/lightbox.js', array(), THEMEVERSION, true);

	// Google Analytics interaction events (comment/search/related clicks). The
	// gtag library itself is loaded in footer.php; this just reports to it.
	wp_enqueue_script('spudmag-analytics', THEMEURI . '/js/analytics.js', array(), THEMEVERSION, true);

	// Comment assets only where comments render: single posts with comments open,
	// excluding the fiction template (which has no comment section). The honeypot
	// timer is needed wherever the form shows; comment-reply powers native
	// threaded replies when threading is enabled.
	if (is_singular('post') && comments_open() && spudmagGetTemplate(get_the_ID()) !== 'fiction') {
		wp_enqueue_script('spudmag-comments', THEMEURI . '/js/comments.js', array(), THEMEVERSION, true);
		if (get_option('thread_comments')) {
			wp_enqueue_script('comment-reply');
		}
	}
}
add_action('wp_enqueue_scripts', 'spudmagAssets');

function spudmagRemoveProtectedPrefix() {
    return '%s';
}
add_filter( 'protected_title_format', 'spudmagRemoveProtectedPrefix' );
