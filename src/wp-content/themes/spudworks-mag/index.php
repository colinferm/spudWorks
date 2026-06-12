<?php
/**
 * Fallback template. Used when no more specific template matches. Renders the
 * standard loop with the non-featured "under" card so the site is never blank.
 */
get_header();
?>
	<div class="archive-header container">
		<h1 class="archive-title"><?php is_home() ? bloginfo('name') : the_archive_title(); ?></h1>
	</div>

	<div class="card-grid container">
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : the_post(); ?>
				<?php get_template_part('templates/card', 'under', array('variant' => 'under')); ?>
			<?php endwhile; ?>
		<?php else : ?>
			<p class="no-results"><?php esc_html_e('Nothing here yet.', 'spudworks-mag'); ?></p>
		<?php endif; ?>
	</div>

	<?php the_posts_pagination(array('mid_size' => 2)); ?>
<?php
get_footer();
