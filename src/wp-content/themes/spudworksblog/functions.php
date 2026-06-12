<?php
//require_once './lib/data-import.php';
error_reporting(E_ERROR);
$theme_obj = wp_get_theme('spudworksblog');

define("THEMENAME", "spudWorks Blog");
define("SHORTNAME", "spudworksblog");
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
	//add_image_size( 'blog_ft', 726, 400, true );
}
?>