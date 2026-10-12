<?php
/**
 * Commenter email — plain-text wrapper (the multipart AltBody). Same variables
 * as layout.php; nothing is HTML-escaped since this is never rendered as HTML.
 */
echo $siteName . "\n";
echo str_repeat('=', strlen($siteName)) . "\n\n";
echo trim($content) . "\n\n";
echo "--\n";
printf(
	/* translators: %s: post title */
	__('You\'re receiving this because you commented on "%s".', 'spudworks-mag') . "\n",
	$postTitle
);
echo $postUrl . "\n";
if ($unsubscribeUrl) {
	echo "\n" . __('Stop emailing me about replies to this comment:', 'spudworks-mag') . "\n";
	echo $unsubscribeUrl . "\n";
}
