<?php
/**
 * Attachment page.
 *
 * Rarely linked, but search engines find these (the sitemap lists them), so
 * they get a minimal page: the title, the "large" image centered in the
 * content well (lightbox-enabled), and prev/next links through the parent
 * post's other images. No byline, date, sidebar, author box, or comments.
 *
 * Arrow keys and horizontal swipes follow the prev/next links - see
 * js/attachment-nav.js.
 */
get_header();

while (have_posts()) :
	the_post();
	$attachmentId = get_the_ID();
	$isImage = wp_attachment_is_image($attachmentId);
	$adjacent = $isImage ? spudmagAdjacentImages($attachmentId) : array('prev' => 0, 'next' => 0);
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class('article article--attachment'); ?>>

		<?php get_template_part('templates/article', 'header', array('is_page' => true)); ?>

		<div class="attachment-media measure" data-lightbox="content">
			<?php if ($isImage) : ?>
				<?php echo wp_get_attachment_image($attachmentId, 'large'); ?>
			<?php else : ?>
				<a href="<?php echo esc_url(wp_get_attachment_url($attachmentId)); ?>"><?php the_title(); ?></a>
			<?php endif; ?>
		</div>

		<?php if ($adjacent['prev'] || $adjacent['next']) : ?>
			<nav class="attachment-nav measure" aria-label="<?php esc_attr_e('Image navigation', 'spudworks-mag'); ?>">
				<?php if ($adjacent['prev']) : ?>
					<a class="attachment-nav__link attachment-nav__prev" rel="prev" href="<?php echo esc_url(get_attachment_link($adjacent['prev'])); ?>">
						<span aria-hidden="true">&larr;</span> <?php esc_html_e('Prev', 'spudworks-mag'); ?>
					</a>
				<?php endif; ?>
				<?php if ($adjacent['next']) : ?>
					<a class="attachment-nav__link attachment-nav__next" rel="next" href="<?php echo esc_url(get_attachment_link($adjacent['next'])); ?>">
						<?php esc_html_e('Next', 'spudworks-mag'); ?> <span aria-hidden="true">&rarr;</span>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

	</article>
	<?php
endwhile;

get_footer();
