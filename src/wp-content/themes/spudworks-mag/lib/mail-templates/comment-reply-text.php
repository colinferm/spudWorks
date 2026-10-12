<?php
/**
 * Reply email (plain-text partial). Same vars as comment-reply.php.
 */
/* translators: %s: commenter name */
printf(__('Hi %s,', 'spudworks-mag') . "\n\n", $recipientName);
printf(
	/* translators: 1: reply author name, 2: post title */
	__('%1$s replied to your comment on "%2$s".', 'spudworks-mag') . "\n\n",
	$replierName,
	$postTitle
);
echo __('You wrote:', 'spudworks-mag') . "\n";
echo '> ' . $commentExcerpt . "\n\n";
/* translators: %s: reply author name */
printf(__('%s replied:', 'spudworks-mag') . "\n", $replierName);
echo '> ' . $replyText . "\n\n";
echo __('Read the reply:', 'spudworks-mag') . "\n";
echo $replyUrl . "\n";
