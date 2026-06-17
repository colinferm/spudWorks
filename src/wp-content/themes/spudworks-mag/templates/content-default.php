<?php
/**
 * Default content template.
 *
 * A clean, single-column article: optional featured image, serif headline,
 * a readable 720px content column, pullquotes, paginated content, then the
 * author box and related stories.
 */
$postId = get_the_ID();
$isPage = isset($args['is_page']) ? (bool) $args['is_page'] : false;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('article article--default'); ?>>

	<?php if (has_post_thumbnail()) : ?>
		<figure class="article-hero">
			<?php the_post_thumbnail('spudmagHero', array('alt' => '')); ?>
		</figure>
	<?php endif; ?>

	<?php get_template_part('templates/article', 'header', array('is_page' => true)); ?>

	<div class="article-content measure" data-lightbox="content">
		<?php the_content(); ?>
	</div>

	<footer class="article-footer measure">
		<?php wp_link_pages(array(
			'before'      => '<nav class="page-links" aria-label="' . esc_attr__('Article pages', 'spudworks-mag') . '"><span class="page-links__label">' . esc_html__('Pages:', 'spudworks-mag') . '</span>',
			'after'       => '</nav>',
			'link_before' => '<span class="page-links__num">',
			'link_after'  => '</span>'
		)); ?>
	</footer>

	<?php if (!$isPage) : ?>
		<?php get_template_part('templates/author-box'); ?>
	<?php endif; ?>
	<?php get_template_part('templates/article-tags'); ?>
	<?php if (!$isPage) comments_template(); ?>
	<?php get_template_part('templates/related-stories'); ?>

</article>
