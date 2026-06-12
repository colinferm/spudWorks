<?php
/**
 * Search results: query echoed at the top, results in the varied card rhythm.
 */
get_header();
?>
<header class="archive-header container">
	<p class="archive-kicker"><?php esc_html_e('Search results for', 'spudworks-mag'); ?></p>
	<h1 class="archive-title">&ldquo;<?php echo esc_html(get_search_query()); ?>&rdquo;</h1>
	<span class="hazard-accent" aria-hidden="true"></span>
</header>

<div class="container">
	<?php if (have_posts()) : ?>
		<div class="card-grid">
			<?php $i = 0; ?>
			<?php while (have_posts()) : the_post(); ?>
				<?php $beat = spudmagCardBeat($i); ?>
				<?php get_template_part('templates/' . $beat['tpl'], '', array(
					'postId'    => get_the_ID(),
					'spanClass' => $beat['span']
				)); ?>
				<?php $i++; ?>
			<?php endwhile; ?>
		</div>

		<?php the_posts_pagination(array(
			'mid_size'  => 2,
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;'
		)); ?>
	<?php else : ?>
		<div class="no-results measure">
			<p><?php esc_html_e('No stories matched that search. Try another term:', 'spudworks-mag'); ?></p>
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>
</div>

<?php get_footer();
