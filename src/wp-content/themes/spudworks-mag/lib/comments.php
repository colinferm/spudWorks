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

/**
 * Comment-count badge shown after a post's dateline: a speech-bubble icon
 * with the approved-comment count in a circle. Returns '' when there's
 * nothing to show — no comments yet, or a post that never renders a comment
 * section (pages, fiction, password-protected) — so callers can echo it
 * unconditionally.
 *
 * $linked makes it an anchor to the comment section; only the article header
 * uses that, since cards are already covered by their stretched title link.
 */
function spudmagCommentBadge($postId, $linked = false) {
	if (get_post_type($postId) !== 'post' || spudmagGetTemplate($postId) === 'fiction' || post_password_required($postId)) {
		return '';
	}

	$count = (int) get_comments_number($postId);
	if ($count < 1) {
		return '';
	}

	$label = sprintf(
		/* translators: %s: number of comments */
		_n('%s comment', '%s comments', $count, 'spudworks-mag'),
		number_format_i18n($count)
	);

	$inner = '<svg class="comment-badge__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
			. '<path d="M4 4h16v12H9l-5 4z"/>'
		. '</svg>'
		. '<span class="comment-badge__count" aria-hidden="true">' . esc_html(number_format_i18n($count)) . '</span>'
		. '<span class="screen-reader-text">' . esc_html($label) . '</span>';

	if ($linked) {
		return '<a class="comment-badge" href="#comments">' . $inner . '</a>';
	}
	return '<span class="comment-badge">' . $inner . '</span>';
}
