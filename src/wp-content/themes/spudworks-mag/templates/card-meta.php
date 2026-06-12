<?php
/**
 * Shared card meta block: term chips, headline, optional secondary headline,
 * and an optional byline. Reused by every card variant.
 *
 * Expected $args:
 *   postId      (int)    required - the post to render
 *   headingTag  (string) heading element for the title (default 'h2')
 *   showByline  (bool)   render the byline area (default true)
 *   maxChips    (int)    number of term chips (default 3)
 *   stretchLink (bool)   make the title link cover the whole card (default false)
 */
$postId = isset($args['postId']) ? (int) $args['postId'] : get_the_ID();
$headingTag = isset($args['headingTag']) ? $args['headingTag'] : 'h2';
$showByline = isset($args['showByline']) ? (bool) $args['showByline'] : true;
$maxChips = isset($args['maxChips']) ? (int) $args['maxChips'] : 3;
$stretchLink = isset($args['stretchLink']) ? (bool) $args['stretchLink'] : false;
$linkClass = $stretchLink ? ' class="stretched-link"' : '';

$subhead = spudmagGetSubhead($postId);
$permalink = get_permalink($postId);
?>
<div class="card-meta">
	<?php spudmagTermChips($postId, $maxChips); ?>

	<<?php echo esc_attr($headingTag); ?> class="card-title">
		<a<?php echo $linkClass; ?> href="<?php echo esc_url($permalink); ?>"><?php echo wp_kses_post(get_the_title($postId)); ?></a>
	</<?php echo esc_attr($headingTag); ?>>

	<?php if ($subhead !== '') : ?>
		<p class="card-subhead"><?php echo wp_kses_post($subhead); ?></p>
	<?php endif; ?>

	<?php if ($showByline && spudmagHasByline($postId)) : ?>
		<p class="card-byline">
			<span class="card-byline__author"><?php echo esc_html(get_the_author_meta('display_name', get_post_field('post_author', $postId))); ?></span>
			<span class="card-byline__sep" aria-hidden="true">&middot;</span>
			<time datetime="<?php echo esc_attr(get_the_date('c', $postId)); ?>"><?php echo esc_html(get_the_date('', $postId)); ?></time>
		</p>
	<?php endif; ?>
</div>
