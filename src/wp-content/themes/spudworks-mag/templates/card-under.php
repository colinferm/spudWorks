<?php
/**
 * Card variant: featured image on top, text left-aligned underneath.
 * The default workhorse card. Degrades gracefully with no thumbnail.
 *
 * $args: postId (int), size (string crop, default 'spudmagLandscape'),
 *        spanClass (string grid span), showByline (bool).
 */
$postId = isset($args['postId']) ? (int) $args['postId'] : get_the_ID();
$size = isset($args['size']) ? $args['size'] : 'spudmagLandscape';
$spanClass = isset($args['spanClass']) ? $args['spanClass'] : 'span-3';
$showByline = isset($args['showByline']) ? (bool) $args['showByline'] : true;
$permalink = get_permalink($postId);
?>
<article class="card card--under <?php echo esc_attr($spanClass); ?>">
	<?php if (has_post_thumbnail($postId)) : ?>
		<a class="card__media" href="<?php echo esc_url($permalink); ?>" aria-hidden="true" tabindex="-1">
			<?php echo get_the_post_thumbnail($postId, $size, array('loading' => 'lazy', 'alt' => '')); ?>
		</a>
	<?php else : ?>
		<a class="card__media card__media--hazard" href="<?php echo esc_url($permalink); ?>" aria-hidden="true" tabindex="-1"></a>
	<?php endif; ?>

	<div class="card__body">
		<?php get_template_part('templates/card-meta', '', array(
			'postId'     => $postId,
			'headingTag' => 'h2',
			'showByline' => $showByline
		)); ?>
	</div>
</article>
