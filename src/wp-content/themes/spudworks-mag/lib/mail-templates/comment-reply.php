<?php
/**
 * Reply email (HTML partial).
 * Vars: $recipientName, $postTitle, $postUrl, $commentExcerpt (theirs),
 *       $replierName, $replyText, $replyUrl.
 */
?>
<p style="margin:0 0 16px;">
	<?php
	/* translators: %s: commenter name */
	printf(esc_html__('Hi %s,', 'spudworks-mag'), esc_html($recipientName));
	?>
</p>
<p style="margin:0 0 16px;">
	<?php
	printf(
		/* translators: 1: reply author name, 2: linked post title */
		esc_html__('%1$s replied to your comment on %2$s.', 'spudworks-mag'),
		'<strong>' . esc_html($replierName) . '</strong>',
		'<a href="' . esc_url($postUrl) . '" style="color:#16181d; font-weight:bold;">' . esc_html($postTitle) . '</a>'
	);
	?>
</p>
<p style="margin:0 0 6px; font-size:12px; font-weight:bold; letter-spacing:0.1em; text-transform:uppercase; color:#6b6a64;"><?php esc_html_e('You wrote', 'spudworks-mag'); ?></p>
<blockquote style="margin:0 0 20px; padding:12px 16px; border-left:4px solid #d8d4c8; background:#f6f4ee; color:#6b6a64;">
	<?php echo esc_html($commentExcerpt); ?>
</blockquote>
<p style="margin:0 0 6px; font-size:12px; font-weight:bold; letter-spacing:0.1em; text-transform:uppercase; color:#6b6a64;">
	<?php
	/* translators: %s: reply author name */
	printf(esc_html__('%s replied', 'spudworks-mag'), esc_html($replierName));
	?>
</p>
<blockquote style="margin:0 0 24px; padding:12px 16px; border-left:4px solid #f4c20d; background:#f6f4ee; color:#16181d;">
	<?php echo esc_html($replyText); ?>
</blockquote>
<a href="<?php echo esc_url($replyUrl); ?>" style="display:inline-block; padding:11px 22px; background:#f4c20d; color:#0a0a0a; border-radius:3px; font-size:14px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; text-decoration:none;"><?php esc_html_e('Read the reply', 'spudworks-mag'); ?></a>
