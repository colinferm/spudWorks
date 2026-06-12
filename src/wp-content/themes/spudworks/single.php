<?php
if (has_post_thumbnail($post->ID, 'full')) {
	$image_id = get_post_thumbnail_id($post->ID);
	$image_thumb = wp_get_attachment_image_src($image_id, 'full', true);
}
?>
<?php get_header(); ?>
	<div class="grid-x">
		<?php get_sidebar(); ?>
		<div class="large-10 cell main-content-well grid-x">
			<div class="large-8 cell">
				<?php if (is_array($image_thumb) && strlen($image_thumb[0])) : ?>
					<div class="featured-image"><img src="<?php echo $image_thumb[0]; ?>"></div>
				<?php endif; ?>
				<div class="article-content">
					<h1><?php the_title(); ?></h1>
					<span class="publish-date"><?php echo get_the_date('m.d.Y'); ?></span>
					<?php the_content(); ?>
				</div>
			</div>
		</div>
	</div>
<?php get_footer(); ?>
