<?php
/**
 * Archive template (category, tag, date, author, etc.).
 *
 * Shows the term/archive name at the top, then the posts in the same varied
 * card rhythm as the home grid (a tasteful mix of overlay + under cards). Also
 * serves category and tag archives via the template hierarchy.
 */
get_header();
?>
<header class="archive-header container">
	<h1 class="archive-title"><?php the_archive_title(); ?></h1>
	<span class="hazard-accent" aria-hidden="true"></span>
	<?php $description = get_the_archive_description(); ?>
	<?php if ($description) : ?>
		<div class="archive-description"><?php echo wp_kses_post($description); ?></div>
	<?php endif; ?>
</header>

<div class="container">
	<?php if (have_posts()) : ?>
		<div class="card-grid">
			<?php $i = 0; ?>
			<?php while (have_posts()) : the_post(); ?>
				<?php $beat = spudmagCardBeat($i); ?>
				<?php get_template_part('templates/' . $beat['tpl'], '', array(
					'postId'    => get_the_ID(),
					'spanClass' => $beat['span'],
					'showByline' => false
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
		<p class="no-results"><?php esc_html_e('Nothing filed here yet.', 'spudworks-mag'); ?></p>
	<?php endif; ?>
</div>

<?php get_footer();
