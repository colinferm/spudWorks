<?php
/**
 * Shared article header: term chips, serif headline (HTML allowed), optional
 * secondary headline, and an optional byline. Used by every content template.
 *
 * Expects to run inside the loop (uses the current post).
 */
$postId = get_the_ID();
$subhead = spudmagGetSubhead($postId);
$isPage = isset($args['is_page']) ? (bool) $args['is_page'] : false;
?>
<header class="article-header measure">
	<?php spudmagTermChips($postId, 4	); ?>

	<h1 class="article-title"><?php the_title(); ?></h1>

	<?php if ($subhead !== '') : ?>
		<p class="article-subhead"><?php echo wp_kses_post($subhead); ?></p>
	<?php endif; ?>

	<?php if (!$isPage && spudmagHasByline($postId)) : ?>
		<p class="article-byline">
			<span class="article-byline__by"><?php esc_html_e('By', 'spudworks-mag'); ?></span>
			<span class="article-byline__author"><?php the_author(); ?></span>
			<span class="article-byline__sep" aria-hidden="true">&middot;</span>
			<time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
			<?php echo spudmagCommentBadge($postId, true); ?>
		</p>
	<?php endif; ?>
</header>
