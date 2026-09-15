<?php
/**
 * Landing Page content template.
 *
 * The Showcase layout (hero, headline, content column + sidebar) used as an
 * evergreen topic hub rather than a dated article: no byline/date (is_page
 * suppresses it in templates/article-header.php, same mechanism the default
 * page template already uses), no author box, no comments, and no auto
 * "Keep Reading" strip — that ranks by shared tags/categories, which pages
 * don't have, so templates/related-stories.php would render nothing anyway.
 *
 * The sidebar is this page's own curated galleries + related-articles list
 * (templates/landing-sidebar.php), not the global Showcase Sidebar widget
 * area that posts using the Showcase template share.
 */
$postId = get_the_ID();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('article article--landing'); ?>>

	<?php if (has_post_thumbnail()) : ?>
		<figure class="article-hero">
			<?php the_post_thumbnail('spudmagHero', array('alt' => '')); ?>
		</figure>
	<?php endif; ?>

	<?php get_template_part('templates/article', 'header', array('is_page' => true)); ?>

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

		<?php get_template_part('templates/landing-sidebar'); ?>
	</div>

</article>
