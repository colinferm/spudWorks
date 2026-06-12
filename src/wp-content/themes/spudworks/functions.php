<?php
//require_once './lib/data-import.php';
error_reporting(E_ERROR);
$theme_obj = wp_get_theme('spudworks');

define("THEMENAME", "spudWorks Reborn");
define("SHORTNAME", "spud");
define("THEMEVERSION", $theme_obj['Version']);
define("THEMEDATEFORMAT", get_option('date_format'));

//Get default WP uploads folder
$wp_upload_arr = wp_upload_dir();
define("THEMEUPLOAD", $wp_upload_arr['basedir']."/".strtolower(THEMENAME)."/");
define("THEMEUPLOADURL", $wp_upload_arr['baseurl']."/".strtolower(THEMENAME)."/");

if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support('post-thumbnails');
	add_theme_support('widgets');
}

if ( function_exists( 'add_image_size' ) ) { 
	add_image_size( 'blog_ft', 726, 400, true );
	add_image_size( 'blog_single_full', 960, 430, true );
	add_image_size( 'blog_square', 300, 300, true );
	add_image_size( 'home_square', 600, 600, true );
	add_image_size( 'home_featured_thumb', 550, 450, true );
	add_image_size( 'slider_main_ft', 660, 420, true );
}

function get_home_items() {
	global $wpdb;
	$custom_front_door_query = "
		SELECT p.ID, p.post_date, p.post_content, p.post_title, p.post_excerpt, p.post_status 
		FROM {$wpdb->posts} p, {$wpdb->term_relationships} tr, {$wpdb->term_taxonomy} tt, {$wpdb->terms} t 
		WHERE p.ID = tr.object_id 
		AND tr.term_taxonomy_id = tt.term_taxonomy_id 
		AND tt.term_id = t.term_id 
		AND p.post_status = 'publish' 
		AND t.slug IN ('fiction', 'blog', 'news') 
		ORDER BY p.post_date DESC LIMIT 10
	";
	$items = $wpdb->get_results($custom_front_door_query, OBJECT);
	return $items;
}

function get_items_for_cat($cat_id, $limit = 10) {
	global $wpdb;
	$cat_item_query = "
		SELECT p.ID, p.post_date, p.post_content, p.post_title, p.post_excerpt, p.post_status 
		FROM {$wpdb->posts} p, {$wpdb->term_relationships} tr, {$wpdb->term_taxonomy} tt, {$wpdb->terms} t 
		WHERE p.ID = tr.object_id 
		AND tr.term_taxonomy_id = tt.term_taxonomy_id 
		AND tt.term_id = t.term_id 
		AND p.post_status = 'publish' 
		AND t.term_id = {$cat_id} 
		ORDER BY p.post_date DESC LIMIT {$limit}
	";
	$items = $wpdb->get_results($cat_item_query, OBJECT);
	return $items;
}

?>