<?php
/**
 * Card variant: text centered over the featured image with a darkening scrim.
 * When no thumbnail exists it falls back to a hazard-tinted panel so the card
 * never looks broken.
 *
 * The title link is "stretched" over the whole card via CSS so the card is
 * clickable without nesting anchors inside anchors (term chips stay separately
 * clickable above it).
 *
 * $args: postId (int), size (string crop, default 'spudmagSquare'),
 *        spanClass (string grid span), showByline (bool).
 */
$postId = isset($args['postId']) ? (int) $args['postId'] : get_the_ID();
$size = isset($args['size']) ? $args['size'] : 'spudmagSquare';
$spanClass = isset($args['spanClass']) ? $args['spanClass'] : 'span-3';
$showByline = isset($args['showByline']) ? (bool) $args['showByline'] : false;
$hasImage = has_post_thumbnail($postId);
?>
<article class="card card--overlay <?php echo esc_attr($spanClass); ?> <?php echo $hasImage ? '' : 'card--noimage'; ?>">
	<?php if ($hasImage) : ?>
		<div class="card__media" aria-hidden="true">
			<?php echo get_the_post_thumbnail($postId, $size, array('loading' => 'lazy', 'alt' => '')); ?>
		</div>
	<?php endif; ?>

	<div class="card__body card__body--centered">
		<?php get_template_part('templates/card-meta', '', array(
			'postId'      => $postId,
			'headingTag'  => 'h2',
			'showByline'  => $showByline,
			'stretchLink' => true
		)); ?>
	</div>
</article>
