<?php
/**
 * 404: a themed dead-end with a hazard motif, a search form, and a way home.
 */
get_header();
?>
<section class="error-404 measure">
	<hr class="hazard-rule">
	<p class="error-404__code">404</p>
	<h1 class="error-404__title"><?php esc_html_e('This corridor is a dead end.', 'spudworks-mag'); ?></h1>
	<p class="error-404__text"><?php esc_html_e('The page you were looking for has been scavenged, sealed off, or never existed. Try a search, or head back to the front.', 'spudworks-mag'); ?></p>

	<?php get_search_form(); ?>

	<p class="error-404__home">
		<a class="button-link" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Back to the front page', 'spudworks-mag'); ?></a>
	</p>
	<hr class="hazard-rule">
</section>

<?php get_footer();
