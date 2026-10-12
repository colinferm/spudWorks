<?php
/**
 * spudWorks Magazine — "Send Approved Email" bulk action on Comments.
 *
 * Lets a moderator retroactively send the "Your comment is live" email
 * (lib/email-functions.php) to selected comments — e.g. ones approved before
 * commenter emails existed. Goes through spudmagSendApprovedEmail(), so the
 * usual rules hold: staff are never emailed, and a comment that already got
 * its approved email is skipped, so running it twice can't double-send.
 * Comments that aren't approved, or aren't mailable (pingbacks, no valid
 * address), are skipped too. A notice reports the tally afterwards.
 */

define('SPUDMAG_BULK_APPROVED_ACTION', 'spudmag_send_approved');

// Result counts carried through the redirect back to the comments list.
define('SPUDMAG_BULK_RESULT_ARGS', array(
	'spudmag_bulk_sent',
	'spudmag_bulk_already',
	'spudmag_bulk_skipped',
	'spudmag_bulk_failed'
));

/**
 * Add the action to the Bulk Actions dropdown.
 */
function spudmagRegisterApprovedBulkAction($actions) {
	if (current_user_can('moderate_comments')) {
		$actions[SPUDMAG_BULK_APPROVED_ACTION] = __('Send Approved Email', 'spudworks-mag');
	}
	return $actions;
}
add_filter('bulk_actions-edit-comments', 'spudmagRegisterApprovedBulkAction');

/**
 * Send to each selected comment. edit-comments.php has already verified the
 * bulk-comments nonce before this filter runs.
 */
function spudmagHandleApprovedBulkAction($redirectUrl, $action, $commentIds) {
	if ($action !== SPUDMAG_BULK_APPROVED_ACTION || !current_user_can('moderate_comments')) {
		return $redirectUrl;
	}

	$tally = array('sent' => 0, 'already' => 0, 'skipped' => 0, 'failed' => 0);

	foreach ($commentIds as $commentId) {
		$comment = get_comment($commentId);

		if (!$comment || $comment->comment_approved !== '1' || !current_user_can('edit_comment', $comment->comment_ID) || !spudmagIsMailableComment($comment)) {
			$tally['skipped']++;
			continue;
		}

		$status = spudmagSendApprovedEmail($comment);

		if ($status === 'exempt') {
			$tally['skipped']++;
		} else {
			$tally[$status]++;
		}
	}

	$redirectUrl = remove_query_arg(SPUDMAG_BULK_RESULT_ARGS, $redirectUrl);

	return add_query_arg(array(
		'spudmag_bulk_sent'    => $tally['sent'],
		'spudmag_bulk_already' => $tally['already'],
		'spudmag_bulk_skipped' => $tally['skipped'],
		'spudmag_bulk_failed'  => $tally['failed']
	), $redirectUrl);
}
add_filter('handle_bulk_actions-edit-comments', 'spudmagHandleApprovedBulkAction', 10, 3);

/**
 * Strip the result args from the address bar after the notice renders, so a
 * refresh doesn't repeat it.
 */
function spudmagRemovableBulkResultArgs($args) {
	return array_merge($args, SPUDMAG_BULK_RESULT_ARGS);
}
add_filter('removable_query_args', 'spudmagRemovableBulkResultArgs');

/**
 * Report the tally on the comments screen.
 */
function spudmagApprovedBulkNotice() {
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'edit-comments' || !isset($_GET['spudmag_bulk_sent'])) {
		return;
	}

	$sent    = absint($_GET['spudmag_bulk_sent']);
	$already = isset($_GET['spudmag_bulk_already']) ? absint($_GET['spudmag_bulk_already']) : 0;
	$skipped = isset($_GET['spudmag_bulk_skipped']) ? absint($_GET['spudmag_bulk_skipped']) : 0;
	$failed  = isset($_GET['spudmag_bulk_failed']) ? absint($_GET['spudmag_bulk_failed']) : 0;

	/* translators: %d: number of emails sent */
	$parts = array(sprintf(_n('%d approved email sent.', '%d approved emails sent.', $sent, 'spudworks-mag'), $sent));

	if ($already) {
		/* translators: %d: number of comments */
		$parts[] = sprintf(_n('%d already had one and was skipped.', '%d already had one and were skipped.', $already, 'spudworks-mag'), $already);
	}
	if ($skipped) {
		/* translators: %d: number of comments */
		$parts[] = sprintf(_n('%d skipped (not approved, staff, or no valid email).', '%d skipped (not approved, staff, or no valid email).', $skipped, 'spudworks-mag'), $skipped);
	}
	if ($failed) {
		/* translators: %d: number of emails */
		$parts[] = sprintf(_n('%d failed to send.', '%d failed to send.', $failed, 'spudworks-mag'), $failed);
	}

	$type = $failed ? 'warning' : ($sent ? 'success' : 'info');

	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		esc_attr($type),
		esc_html(implode(' ', $parts))
	);
}
add_action('admin_notices', 'spudmagApprovedBulkNotice');
