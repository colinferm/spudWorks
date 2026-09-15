<?php
/**
 * Static page router.
 *
 * Reads the per-page template choice (_spudmag_page_template) and dispatches
 * to the matching content template — mirrors single.php's post-side routing.
 *
 * 'default' (the fallback for every existing page) reuses the post content
 * template so pages share the article look (hero, readable column,
 * pullquotes, lightbox); term chips, the footer tags, and related stories
 * render nothing on pages (no tags/categories), so they fall away cleanly.
 *
 * 'landing' renders the Showcase layout as an evergreen topic hub instead —
 * see templates/content-landing.php.
 */
get_header();

while (have_posts()) :
	the_post();
	$template = spudmagGetPageTemplate(get_the_ID());
	if ($template === 'landing') {
		get_template_part('templates/content', 'landing');
	} else {
		get_template_part('templates/content', 'default', array('is_page' => true));
	}
endwhile;

get_footer();
