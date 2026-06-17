<?php
/**
 * Card variant: the pinned hero. Stands alone full-width at the very top of the
 * home page, with a large featured image and centered text over a scrim. Falls
 * back to a solid hazard-edged panel when no image is set.
 *
 * $args: postId (int).
 */
$postId = isset($args['postId']) ? (int) $args['postId'] : get_the_ID();
$hasImage = has_post_thumbnail($postId);
?>
<article class="card card--pinned <?php echo $hasImage ? '' : 'card--noimage'; ?>">
	<?php if ($hasImage) : ?>
		<div class="card__media" aria-hidden="true">
			<?php echo get_the_post_thumbnail($postId, 'spudmagHero', array('loading' => 'eager', 'alt' => '')); ?>
		</div>
	<?php endif; ?>

	<div class="card__body card__body--centered">
		<span class="card-flag">Featured</span>
		<?php get_template_part('templates/card-meta', '', array(
			'postId'      => $postId,
			'headingTag'  => 'h1',
			'showByline'  => true,
			'stretchLink' => true
		)); ?>
	</div>
</article>
