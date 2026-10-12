<?php
/**
 * Held email (HTML partial). Links to the post, not the comment — a held
 * comment isn't public yet.
 * Vars: $recipientName, $postTitle, $postUrl, $commentExcerpt.
 */
?>
<p style="margin:0 0 16px;">
	<?php
	/* translators: %s: commenter name */
	printf(esc_html__('Thanks, %s.', 'spudworks-mag'), esc_html($recipientName));
	?>
</p>
<p style="margin:0 0 16px;">
	<?php
	printf(
		/* translators: %s: linked post title */
		esc_html__('Your comment on %s is awaiting moderation. We\'ll email you again once it\'s approved.', 'spudworks-mag'),
		'<a href="' . esc_url($postUrl) . '" style="color:#16181d; font-weight:bold;">' . esc_html($postTitle) . '</a>'
	);
	?>
</p>
<blockquote style="margin:0; padding:12px 16px; border-left:4px solid #f4c20d; background:#f6f4ee; color:#3a3c42;">
	<?php echo esc_html($commentExcerpt); ?>
</blockquote>
