<?php
/**
 * Approved email (plain-text partial). Same vars as comment-approved.php.
 */
/* translators: %s: commenter name */
printf(__('Good news, %s.', 'spudworks-mag') . "\n\n", $recipientName);
printf(
	/* translators: %s: post title */
	__('Your comment on "%s" has been approved and is now live.', 'spudworks-mag') . "\n\n",
	$postTitle
);
echo '> ' . $commentExcerpt . "\n\n";
echo __('View your comment:', 'spudworks-mag') . "\n";
echo $commentUrl . "\n";
