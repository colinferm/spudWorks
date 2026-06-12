<?php
/**
 * Fiction content template.
 *
 * Heavily focused on the written word: no sidebar, a centered literary header,
 * clean distinctive sub-headings, and any image embedded in the content
 * stretches across the full viewport. Pagination sits cleanly at the bottom,
 * above the author box.
 */
$postId = get_the_ID();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('article article--fiction'); ?>>

	<?php if (has_post_thumbnail()) : ?>
		<figure class="article-hero article-hero--full">
			<?php the_post_thumbnail('spudmagHero', array('alt' => '')); ?>
		</figure>
	<?php endif; ?>

	<?php get_template_part('templates/article-header'); ?>

	<div class="article-content measure" data-lightbox="content">
		<?php the_content(); ?>
	</div>

	<footer class="article-footer measure">
		<?php wp_link_pages(array(
			'before'      => '<nav class="page-links" aria-label="' . esc_attr__('Story pages', 'spudworks-mag') . '"><span class="page-links__label">' . esc_html__('Page', 'spudworks-mag') . '</span>',
			'after'       => '</nav>',
			'link_before' => '<span class="page-links__num">',
			'link_after'  => '</span>'
		)); ?>
	</footer>

	<?php get_template_part('templates/author-box'); ?>
	<?php get_template_part('templates/article-tags'); ?>
	<?php get_template_part('templates/related-stories'); ?>

</article>
