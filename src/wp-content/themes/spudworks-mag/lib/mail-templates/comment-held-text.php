<?php
/**
 * Held email (plain-text partial). Same vars as comment-held.php.
 */
/* translators: %s: commenter name */
printf(__('Thanks, %s.', 'spudworks-mag') . "\n\n", $recipientName);
printf(
	/* translators: %s: post title */
	__('Your comment on "%s" is awaiting moderation. We\'ll email you again once it\'s approved.', 'spudworks-mag') . "\n\n",
	$postTitle
);
echo '> ' . $commentExcerpt . "\n";
