<?php
/**
 * Static page template.
 *
 * Reuses the default content template so pages share the article look (hero,
 * readable column, pullquotes, lightbox). Term chips, the footer tags, and
 * related stories render nothing on pages (no tags/categories), so they fall
 * away cleanly without any page-specific markup.
 */
get_header();

while (have_posts()) :
	the_post();
	get_template_part('templates/content', 'default', array('is_page' => true));
endwhile;

get_footer();
