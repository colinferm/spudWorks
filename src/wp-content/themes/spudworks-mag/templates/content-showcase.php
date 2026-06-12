<?php
/**
 * Showcase content template.
 *
 * Built for picture-heavy posts: long essays plus many in-line images and
 * galleries, with a WordPress widget sidebar alongside the content.
 */
$postId = get_the_ID();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('article article--showcase'); ?>>

	<?php if (has_post_thumbnail()) : ?>
		<figure class="article-hero">
			<?php the_post_thumbnail('spudmagHero', array('alt' => '')); ?>
		</figure>
	<?php endif; ?>

	<?php get_template_part('templates/article-header'); ?>

	<div class="showcase-layout container">
		<div class="showcase-main">
			<div class="article-content" data-lightbox="content">
				<?php the_content(); ?>
			</div>

			<footer class="article-footer">
				<?php wp_link_pages(array(
					'before'      => '<nav class="page-links" aria-label="' . esc_attr__('Article pages', 'spudworks-mag') . '"><span class="page-links__label">' . esc_html__('Pages:', 'spudworks-mag') . '</span>',
					'after'       => '</nav>',
					'link_before' => '<span class="page-links__num">',
					'link_after'  => '</span>'
				)); ?>
			</footer>
		</div>

		<?php get_sidebar(); ?>
	</div>

	<?php get_template_part('templates/author-box'); ?>
	<?php get_template_part('templates/article-tags'); ?>
	<?php get_template_part('templates/related-stories'); ?>

</article>
