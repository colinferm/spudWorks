<?php get_header(); ?>
	<div class="grid-x">
		<?php get_sidebar(); ?>
		<div class="large-10 cell main-content-well grid-x grid-padding-x grid-padding-y">
			<div class="large-8 cell">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<h1><a href="<?php echo the_permalink(); ?>" title="<?php the_title(); ?>"><?php the_title(); ?></a></h1>
				<span class="publish-date"><?php echo get_the_date('m.d.Y'); ?></span>
				<?php the_excerpt(); ?>
			<?php endwhile; endif; ?>

			<nav aria-label="Pagination">
				<ul class="pagination">
					<?php $prev = get_previous_posts_link('Previous'); ?>
					<?php if (strlen($prev)) : ?>
						<li class="pagination-previous"><?php echo $prev; ?>
					<?php endif; ?>
					<?php $next = get_next_posts_link('Next'); ?>
					<?php if (strlen($next)) : ?>
						<li class="pagination-next"><?php echo $next; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
<?php get_footer(); ?>