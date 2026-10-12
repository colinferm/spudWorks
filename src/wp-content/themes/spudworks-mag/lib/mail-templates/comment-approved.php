<?php
/**
 * Approved email (HTML partial).
 * Vars: $recipientName, $postTitle, $postUrl, $commentExcerpt, $commentUrl.
 */
?>
<p style="margin:0 0 16px;">
	<?php
	/* translators: %s: commenter name */
	printf(esc_html__('Good news, %s.', 'spudworks-mag'), esc_html($recipientName));
	?>
</p>
<p style="margin:0 0 16px;">
	<?php
	printf(
		/* translators: %s: linked post title */
		esc_html__('Your comment on %s has been approved and is now live.', 'spudworks-mag'),
		'<a href="' . esc_url($postUrl) . '" style="color:#16181d; font-weight:bold;">' . esc_html($postTitle) . '</a>'
	);
	?>
</p>
<blockquote style="margin:0 0 24px; padding:12px 16px; border-left:4px solid #f4c20d; background:#f6f4ee; color:#3a3c42;">
	<?php echo esc_html($commentExcerpt); ?>
</blockquote>
<a href="<?php echo esc_url($commentUrl); ?>" style="display:inline-block; padding:11px 22px; background:#f4c20d; color:#0a0a0a; border-radius:3px; font-size:14px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; text-decoration:none;"><?php esc_html_e('View your comment', 'spudworks-mag'); ?></a>
