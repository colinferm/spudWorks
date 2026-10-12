<?php
/**
 * Commenter email — HTML wrapper.
 *
 * Table-based with inline styles, because email clients ignore stylesheets and
 * CSS variables; colours mirror css/_variables.scss. Rendered by
 * spudmagSendCommentEmail() with:
 *   $siteName, $siteUrl, $preheader, $content (rendered partial),
 *   $postTitle, $postUrl, $unsubscribeUrl ('' when not opted in).
 */
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_bloginfo('language')); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($siteName); ?></title>
</head>
<body style="margin:0; padding:0; background:#e9e5db;">
<span style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;"><?php echo esc_html($preheader); ?></span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#e9e5db;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background:#ffffff; border-radius:4px; overflow:hidden;">
				<tr>
					<td style="background:#0e0f12; padding:22px 28px; font-family:Georgia, 'Times New Roman', serif; font-size:22px; font-weight:bold; color:#e9e5db;">
						<a href="<?php echo esc_url($siteUrl); ?>" style="color:#e9e5db; text-decoration:none;"><?php echo esc_html($siteName); ?></a>
					</td>
				</tr>
				<tr>
					<td style="background:#f4c20d; height:5px; line-height:5px; font-size:0;">&nbsp;</td>
				</tr>
				<tr>
					<td style="padding:28px; font-family:-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:16px; line-height:1.55; color:#16181d;">
						<?php echo $content; // Rendered partial; escaped inside. ?>
					</td>
				</tr>
				<tr>
					<td style="padding:18px 28px; border-top:1px solid #e9e5db; font-family:-apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:13px; line-height:1.5; color:#6b6a64;">
						<?php
						printf(
							/* translators: %s: linked post title */
							esc_html__('You\'re receiving this because you commented on %s.', 'spudworks-mag'),
							'<a href="' . esc_url($postUrl) . '" style="color:#6b6a64;">' . esc_html($postTitle) . '</a>'
						);
						?>
						<?php if ($unsubscribeUrl) : ?>
							<br><a href="<?php echo esc_url($unsubscribeUrl); ?>" style="color:#6b6a64;"><?php esc_html_e('Stop emailing me about replies to this comment', 'spudworks-mag'); ?></a>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
