<?php
/**
 * Comment section, loaded by comments_template() from the default and showcase
 * content templates (never on fiction posts or pages). Renders any existing
 * thread followed by the comment form. The honeypot/timing fields and their
 * styling live in lib/comment-security.php and css/_comments.scss.
 *
 * Expects to run inside the loop.
 */

// Never expose comments on a password-protected post until it's unlocked.
if (post_password_required()) {
	return;
}
?>
<section id="comments" class="comments measure" aria-label="<?php esc_attr_e('Comments', 'spudworks-mag'); ?>">

	<?php if (have_comments()) : ?>
		<h2 class="comments__title">
			<?php
			$commentCount = get_comments_number();
			printf(
				/* translators: %s: comment count */
				esc_html(_n('%s Comment', '%s Comments', $commentCount, 'spudworks-mag')),
				esc_html(number_format_i18n($commentCount))
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(array(
				'callback'    => 'spudmagRenderComment',
				'style'       => 'ol',
				'avatar_size' => 64
			));
			?>
		</ol>

		<?php
		the_comments_pagination(array(
			'prev_text' => esc_html__('Older comments', 'spudworks-mag'),
			'next_text' => esc_html__('Newer comments', 'spudworks-mag')
		));
		?>

		<?php if (!comments_open()) : ?>
			<p class="comments__closed"><?php esc_html_e('Comments are closed.', 'spudworks-mag'); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	comment_form(array(
		'title_reply'         => __('Join the Conversation', 'spudworks-mag'),
		'title_reply_before'  => '<h3 id="reply-title" class="comment-form__title">',
		'title_reply_after'   => '</h3>',
		'class_form'          => 'comment-form',
		'class_submit'        => 'comment-submit',
		'comment_field'       =>
			'<p class="comment-form-comment">'
			. '<label for="comment">' . esc_html__('Comment', 'spudworks-mag') . '</label>'
			. '<textarea id="comment" name="comment" cols="45" rows="6" required></textarea>'
			. '</p>'
	));
	?>

</section>
