<?php
/**
 * Single post router.
 *
 * Reads the per-post template choice (_spudmag_template) and dispatches to the
 * matching content template. Falls back to the default content template for any
 * unknown value or post without a choice.
 */
get_header();

while (have_posts()) :
	the_post();
	$template = spudmagGetTemplate(get_the_ID());
	get_template_part('templates/content', $template);
endwhile;

get_footer();
