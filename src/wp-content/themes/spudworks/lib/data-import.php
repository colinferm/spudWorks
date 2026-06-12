<?php
if (defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command('sw-migrate', 'sw_migrate_db');
}


function sw_migrate_db() {
	global $wpdb;

	$truncate_posts = "TRUNCATE wp_posts";
	$truncate_terms = "TRUNCATE wp_terms";
	$truncate_tax = "TRUNCATE wp_term_taxonomy";
	$truncate_rel = "TRUNCATE wp_term_relationships";
	$post_query = "INSERT INTO wp_posts (ID, post_date, post_date_gmt, post_content, post_excerpt, post_title, post_type, post_status, to_ping, pinged, post_content_filtered) SELECT id, news_date, news_date, long_blurb, blurb, title, 'post', CASE WHEN `state` = 3 THEN 'publish' ELSE 'draft' END , '', '', '' FROM spud_news";
	$cat_query = "INSERT INTO wp_terms (term_id, name, slug, term_group) SELECT id, category, REPLACE(LOWER(category), ' ', '-') AS slug, '0' FROM spud_news_category ORDER BY id";
	$tax_query = "INSERT INTO wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent, count) SELECT id, id, 'category', CASE WHEN body IS NULL THEN '' ELSE body END, '0', '0' FROM spud_news_category ORDER BY id" ;
	$rel_query = "INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order) SELECT news_id, category_id, '0' FROM spud_news_map ORDER BY news_id"
}
?>