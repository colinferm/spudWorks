<?php
/**
 * Card variant: featured strip item. Larger than a grid card, with the text
 * sitting over the lower portion of the image behind a gradient scrim.
 *
 * $args: postId (int), showByline (bool, default true).
 */
$postId = isset($args['postId']) ? (int) $args['postId'] : get_the_ID();
$showByline = isset($args['showByline']) ? (bool) $args['showByline'] : true;
$hasImage = has_post_thumbnail($postId);
?>
<article class="card card--featured <?php echo $hasImage ? '' : 'card--noimage'; ?>">
	<?php if ($hasImage) : ?>
		<div class="card__media" aria-hidden="true">
			<?php echo get_the_post_thumbnail($postId, 'spudmagLandscape', array('loading' => 'lazy', 'alt' => '')); ?>
		</div>
	<?php endif; ?>

	<div class="card__body">
		<?php get_template_part('templates/card-meta', '', array(
			'postId'      => $postId,
			'headingTag'  => 'h2',
			'showByline'  => $showByline,
			'stretchLink' => true
		)); ?>
	</div>
</article>
