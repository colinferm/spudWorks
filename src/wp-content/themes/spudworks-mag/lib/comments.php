<?php
/**
 * spudWorks Magazine — comment rendering.
 *
 * The wp_list_comments() callback used by comments.php. Mirrors the author box:
 * a circular avatar ringed in hazard yellow, a serif name, and sans body text.
 * WordPress opens the list item and appends the closing tag itself, so this
 * callback deliberately leaves its wrapper element open.
 */
function spudmagRenderComment($comment, $args, $depth) {
	$tag = ($args['style'] === 'div') ? 'div' : 'li';
	?>
	<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class('comment-item'); ?>>
		<article class="comment-body">
			<div class="comment-avatar">
				<?php echo get_avatar($comment, $args['avatar_size']); ?>
			</div>
			<div class="comment-main">
				<header class="comment-meta">
					<span class="comment-author"><?php comment_author_link(); ?></span>
					<time class="comment-date" datetime="<?php echo esc_attr(get_comment_time('c')); ?>">
						<?php
						/* translators: 1: comment date, 2: comment time */
						printf(
							esc_html__('%1$s at %2$s', 'spudworks-mag'),
							esc_html(get_comment_date()),
							esc_html(get_comment_time())
						);
						?>
					</time>
				</header>

				<?php if ($comment->comment_approved === '0') : ?>
					<p class="comment-awaiting-moderation"><?php esc_html_e('Your comment is awaiting moderation.', 'spudworks-mag'); ?></p>
				<?php endif; ?>

				<div class="comment-text"><?php comment_text(); ?></div>

				<?php
				comment_reply_link(array_merge($args, array(
					'depth'      => $depth,
					'max_depth'  => $args['max_depth'],
					'reply_text' => __('Reply', 'spudworks-mag'),
					'before'     => '<span class="comment-reply">',
					'after'      => '</span>'
				)));
				?>
			</div>
		</article>
	<?php
	// No closing tag: wp_list_comments() appends it.
}
