<?php
/**
 * spudWorks Magazine — commenter email notifications.
 *
 * Out of the box WordPress only emails site staff about comments; the person
 * who wrote one never hears back. This library closes that loop with three
 * emails to the commenter:
 *
 *   1. Held     — their comment is awaiting moderation (sent on submission).
 *   2. Approved — a moderator approved it (unapproved -> approved, or a spam
 *                 false positive rescued; auto-approved comments get nothing,
 *                 the commenter already saw theirs go live).
 *   3. Reply    — someone replied directly to their comment. Opt-in only, via
 *                 a checkbox on the comment form stored per comment in
 *                 commentmeta (spudmag_notify_replies).
 *
 * Site admins and the post's author never receive these emails about their own
 * comments — but a reader IS emailed when an admin or the author replies.
 *
 * Every reply email carries a one-click unsubscribe link (an HMAC token, no
 * login needed) plus List-Unsubscribe headers so mail clients show their own
 * unsubscribe button. The held email goes out without an opt-in, so anyone
 * could type a stranger's address into the form; it is throttled per address.
 *
 * Templates live in lib/mail-templates/: layout.php wraps an HTML partial,
 * layout-text.php wraps its "-text" plain-text twin, and the two are sent as a
 * multipart/alternative message through plain wp_mail(). Admins can preview
 * them with sample data at /?spudmag_mail_preview (see the end of this file).
 */

// Per-comment opt-in flag for reply notifications.
define('SPUDMAG_NOTIFY_META', 'spudmag_notify_replies');

// Send-once guards: on the comment (approved) and on the reply (reply sent).
define('SPUDMAG_APPROVED_SENT_META', 'spudmag_approved_sent');
define('SPUDMAG_REPLY_SENT_META', 'spudmag_reply_sent');

// Minimum gap between two "held" emails to the same address.
define('SPUDMAG_HELD_THROTTLE_SECONDS', 10 * MINUTE_IN_SECONDS);

// Word caps for quoted comment text in emails.
define('SPUDMAG_MAIL_EXCERPT_WORDS', 55);
define('SPUDMAG_MAIL_REPLY_WORDS', 150);

/* -------------------------------------------------------------------------
 * Who gets emailed
 * ---------------------------------------------------------------------- */

/**
 * Is this comment's author a site admin or the post's author? Such people never
 * get commenter emails. Matches logged-in accounts by capability/ID, and guest
 * comments by email, which catches staff commenting while logged out.
 */
function spudmagIsExemptCommenter($comment) {
	$post   = get_post($comment->comment_post_ID);
	$userId = (int) $comment->user_id;

	if ($userId > 0) {
		if (user_can($userId, 'manage_options')) {
			return true;
		}
		if ($post && $userId === (int) $post->post_author) {
			return true;
		}
	}

	$staffEmails = array(strtolower(get_option('admin_email')));
	if ($post) {
		$postAuthor = get_userdata($post->post_author);
		if ($postAuthor) {
			$staffEmails[] = strtolower($postAuthor->user_email);
		}
	}

	return in_array(strtolower(trim($comment->comment_author_email)), $staffEmails, true);
}

/**
 * The same rule applied to whoever is viewing the comment form. Only logged-in
 * visitors can be recognised here; guests always see the checkbox.
 */
function spudmagIsExemptViewer($postId) {
	if (!is_user_logged_in()) {
		return false;
	}

	if (current_user_can('manage_options')) {
		return true;
	}

	return get_current_user_id() === (int) get_post_field('post_author', $postId);
}

/**
 * Only real, non-spam comments with a usable email address are mailable —
 * pingbacks, trackbacks and block-editor notes are ignored.
 */
function spudmagIsMailableComment($comment) {
	if (!$comment || !in_array($comment->comment_type, array('', 'comment'), true)) {
		return false;
	}

	if ($comment->comment_approved === 'spam' || $comment->comment_approved === 'trash') {
		return false;
	}

	return (bool) is_email($comment->comment_author_email);
}

/* -------------------------------------------------------------------------
 * Comment form: the reply-notification checkbox
 * ---------------------------------------------------------------------- */

/**
 * Prepend the opt-in checkbox to the submit row. comment_form_submit_field runs
 * for guests and logged-in users alike (unlike comment_form_after_fields).
 */
function spudmagNotifyCheckboxField($submitField, $args) {
	if (spudmagIsExemptViewer(get_the_ID())) {
		return $submitField;
	}

	$checkbox = '<p class="comment-form-notify">'
		. '<input type="checkbox" name="' . SPUDMAG_NOTIFY_META . '" id="' . SPUDMAG_NOTIFY_META . '" value="1">'
		. '<label for="' . SPUDMAG_NOTIFY_META . '">' . esc_html__('Email me when someone replies to my comment.', 'spudworks-mag') . '</label>'
		. '</p>';

	return $checkbox . $submitField;
}
add_filter('comment_form_submit_field', 'spudmagNotifyCheckboxField', 10, 2);

/* -------------------------------------------------------------------------
 * Triggers
 * ---------------------------------------------------------------------- */

/**
 * A new comment was saved. By now comment-security.php has had its say, so
 * $approved is final: 1, 0, 'spam' or 'trash'.
 */
function spudmagOnCommentPost($commentId, $approved, $commentdata) {
	// Saved even for spam: a false positive rescued later keeps its opt-in.
	if (!empty($_POST[SPUDMAG_NOTIFY_META])) {
		update_comment_meta($commentId, SPUDMAG_NOTIFY_META, 1);
	}

	$comment = get_comment($commentId);
	if (!spudmagIsMailableComment($comment)) {
		return;
	}

	if ((string) $approved === '0') {
		spudmagSendHeldEmail($comment);
	} elseif ((string) $approved === '1') {
		spudmagMaybeNotifyParent($comment);
	}
}
add_action('comment_post', 'spudmagOnCommentPost', 10, 3);

/**
 * A moderator approved a held comment, or rescued one from spam (e.g. a guest
 * without JavaScript, caught by the timing trap).
 */
function spudmagOnCommentApproved($comment) {
	if (!spudmagIsMailableComment($comment)) {
		return;
	}

	spudmagSendApprovedEmail($comment);
	spudmagMaybeNotifyParent($comment);
}
add_action('comment_unapproved_to_approved', 'spudmagOnCommentApproved');
add_action('comment_spam_to_approved', 'spudmagOnCommentApproved');

/* -------------------------------------------------------------------------
 * The three emails
 * ---------------------------------------------------------------------- */

/**
 * "Your comment is awaiting moderation." Throttled per address, since it goes
 * to whatever email was typed into the form without any opt-in.
 */
function spudmagSendHeldEmail($comment) {
	if (spudmagIsExemptCommenter($comment)) {
		return;
	}

	$throttleKey = 'spudmag_held_' . md5(strtolower(trim($comment->comment_author_email)));
	if (get_transient($throttleKey)) {
		return;
	}

	$vars = spudmagCommentMailVars($comment);
	$vars['preheader'] = sprintf(
		/* translators: %s: post title */
		__('Your comment on "%s" is awaiting moderation.', 'spudworks-mag'),
		$vars['postTitle']
	);

	$sent = spudmagSendCommentEmail(
		$comment->comment_author_email,
		__('Your comment is awaiting moderation', 'spudworks-mag'),
		'comment-held',
		$vars
	);

	if ($sent) {
		set_transient($throttleKey, 1, SPUDMAG_HELD_THROTTLE_SECONDS);
	}
}

/**
 * "Your comment is live." Sent once per comment, however many times it is
 * unapproved and re-approved.
 */
function spudmagSendApprovedEmail($comment) {
	if (spudmagIsExemptCommenter($comment) || get_comment_meta($comment->comment_ID, SPUDMAG_APPROVED_SENT_META, true)) {
		return;
	}

	$vars = spudmagCommentMailVars($comment);
	$vars['commentUrl'] = get_comment_link($comment);
	$vars['preheader']  = sprintf(
		/* translators: %s: post title */
		__('Your comment on "%s" has been approved.', 'spudworks-mag'),
		$vars['postTitle']
	);

	$sent = spudmagSendCommentEmail(
		$comment->comment_author_email,
		__('Your comment is live', 'spudworks-mag'),
		'comment-approved',
		$vars
	);

	if ($sent) {
		update_comment_meta($comment->comment_ID, SPUDMAG_APPROVED_SENT_META, 1);
	}
}

/**
 * Tell the parent comment's author about an approved direct reply — only if
 * they opted in, aren't staff, and aren't replying to themselves.
 */
function spudmagMaybeNotifyParent($reply) {
	if (!$reply->comment_parent || get_comment_meta($reply->comment_ID, SPUDMAG_REPLY_SENT_META, true)) {
		return;
	}

	$parent = get_comment($reply->comment_parent);
	if (!$parent || $parent->comment_approved !== '1' || !spudmagIsMailableComment($parent)) {
		return;
	}

	if (!get_comment_meta($parent->comment_ID, SPUDMAG_NOTIFY_META, true) || spudmagIsExemptCommenter($parent)) {
		return;
	}

	if (strtolower(trim($parent->comment_author_email)) === strtolower(trim($reply->comment_author_email))) {
		return;
	}

	$unsubscribeUrl = spudmagUnsubscribeUrl($parent);

	$vars = spudmagCommentMailVars($parent);
	$vars['replierName']    = spudmagMailText($reply->comment_author);
	$vars['replyText']      = wp_trim_words(spudmagMailText($reply->comment_content), SPUDMAG_MAIL_REPLY_WORDS);
	$vars['replyUrl']       = get_comment_link($reply);
	$vars['unsubscribeUrl'] = $unsubscribeUrl;
	$vars['preheader']      = sprintf(
		/* translators: 1: reply author name, 2: post title */
		__('%1$s replied to your comment on "%2$s".', 'spudworks-mag'),
		$vars['replierName'],
		$vars['postTitle']
	);

	$sent = spudmagSendCommentEmail(
		$parent->comment_author_email,
		sprintf(
			/* translators: %s: reply author name */
			__('%s replied to your comment', 'spudworks-mag'),
			$vars['replierName']
		),
		'comment-reply',
		$vars,
		array(
			'List-Unsubscribe: <' . $unsubscribeUrl . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click'
		)
	);

	if ($sent) {
		update_comment_meta($reply->comment_ID, SPUDMAG_REPLY_SENT_META, 1);
	}
}

/* -------------------------------------------------------------------------
 * Unsubscribe
 * ---------------------------------------------------------------------- */

/**
 * The token binds the comment ID to its author's email, signed with the site's
 * auth salt — nothing to store, valid until the salts are rotated.
 */
function spudmagUnsubscribeToken($comment) {
	$payload = $comment->comment_ID . '|' . strtolower(trim($comment->comment_author_email));
	return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function spudmagUnsubscribeUrl($comment) {
	return add_query_arg(array(
		'spudmag_unsub' => $comment->comment_ID,
		't'             => spudmagUnsubscribeToken($comment)
	), home_url('/'));
}

/**
 * Handle the unsubscribe link: a GET from the email body (shows a confirmation
 * page) or an RFC 8058 one-click POST from the mail client (bare 200). Clearing
 * the flag is idempotent, so link scanners prefetching the URL do no harm.
 */
function spudmagHandleUnsubscribe() {
	if (!isset($_GET['spudmag_unsub'])) {
		return;
	}

	$comment = get_comment(absint($_GET['spudmag_unsub']));
	$token   = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
	$isPost  = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

	if (!$comment || $token === '' || !hash_equals(spudmagUnsubscribeToken($comment), $token)) {
		if ($isPost) {
			status_header(400);
			exit;
		}
		wp_die(
			esc_html__('This unsubscribe link is invalid or has expired.', 'spudworks-mag'),
			esc_html__('Unsubscribe', 'spudworks-mag'),
			array('response' => 400, 'link_url' => home_url('/'), 'link_text' => get_bloginfo('name'))
		);
	}

	delete_comment_meta($comment->comment_ID, SPUDMAG_NOTIFY_META);

	if ($isPost) {
		status_header(200);
		exit;
	}

	$postTitle = spudmagMailText(get_the_title($comment->comment_post_ID));
	wp_die(
		esc_html(sprintf(
			/* translators: %s: post title */
			__('You\'re unsubscribed. You won\'t get any more reply notifications for your comment on "%s".', 'spudworks-mag'),
			$postTitle
		)),
		esc_html__('Unsubscribed', 'spudworks-mag'),
		array(
			'response'  => 200,
			'link_url'  => get_permalink($comment->comment_post_ID),
			'link_text' => sprintf(
				/* translators: %s: post title */
				__('Back to "%s"', 'spudworks-mag'),
				$postTitle
			)
		)
	);
}
add_action('template_redirect', 'spudmagHandleUnsubscribe');

/* -------------------------------------------------------------------------
 * Rendering + sending
 * ---------------------------------------------------------------------- */

/**
 * Strip markup and decode entities, for text that is escaped again at output
 * (HTML templates) or sent as-is (plain-text templates).
 */
function spudmagMailText($value) {
	return trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES, 'UTF-8'));
}

function spudmagMailSiteName() {
	return spudmagMailText(get_bloginfo('name'));
}

/**
 * Template variables shared by all three emails, describing the comment whose
 * author is being written to.
 */
function spudmagCommentMailVars($comment) {
	$vars = array(
		'recipientName'  => spudmagMailText($comment->comment_author),
		'postTitle'      => spudmagMailText(get_the_title($comment->comment_post_ID)),
		'postUrl'        => get_permalink($comment->comment_post_ID),
		'commentExcerpt' => wp_trim_words(spudmagMailText($comment->comment_content), SPUDMAG_MAIL_EXCERPT_WORDS),
		'unsubscribeUrl' => ''
	);

	// An opted-in commenter can unsubscribe from any email about that comment.
	if (get_comment_meta($comment->comment_ID, SPUDMAG_NOTIFY_META, true)) {
		$vars['unsubscribeUrl'] = spudmagUnsubscribeUrl($comment);
	}

	return $vars;
}

/**
 * Render lib/mail-templates/<template>.php with $vars as local variables.
 */
function spudmagRenderMailTemplate($template, $vars) {
	extract($vars, EXTR_SKIP);
	ob_start();
	include __DIR__ . '/mail-templates/' . $template . '.php';
	return ob_get_clean();
}

/**
 * Render one commenter email both ways: the HTML partial inside layout.php,
 * and its "-text" twin inside layout-text.php.
 */
function spudmagRenderCommentEmail($template, $vars) {
	$vars = array_merge(array(
		'siteName'       => spudmagMailSiteName(),
		'siteUrl'        => home_url('/'),
		'preheader'      => '',
		'unsubscribeUrl' => ''
	), $vars);

	$vars['content'] = spudmagRenderMailTemplate($template, $vars);
	$html = spudmagRenderMailTemplate('layout', $vars);

	$vars['content'] = spudmagRenderMailTemplate($template . '-text', $vars);
	$text = spudmagRenderMailTemplate('layout-text', $vars);

	return array('html' => $html, 'text' => $text, 'siteName' => $vars['siteName']);
}

/**
 * Send one commenter email as multipart/alternative, with the plain-text
 * rendering as the AltBody. The AltBody and From-name hooks are added and
 * removed around this one wp_mail() call so they never touch other site mail.
 */
function spudmagSendCommentEmail($to, $subject, $template, $vars, $extraHeaders = array()) {
	$rendered = spudmagRenderCommentEmail($template, $vars);
	$text     = $rendered['text'];
	$siteName = $rendered['siteName'];

	$setAltBody = function ($phpmailer) use ($text) {
		$phpmailer->AltBody = $text;
	};
	$fromName = function () use ($siteName) {
		return $siteName;
	};

	add_action('phpmailer_init', $setAltBody);
	add_filter('wp_mail_from_name', $fromName);

	$headers = array_merge(array('Content-Type: text/html; charset=UTF-8'), $extraHeaders);
	$sent    = wp_mail($to, sprintf('[%s] %s', $siteName, $subject), $rendered['html'], $headers);

	remove_action('phpmailer_init', $setAltBody);
	remove_filter('wp_mail_from_name', $fromName);

	return $sent;
}

/* -------------------------------------------------------------------------
 * Design preview (admins only)
 * ---------------------------------------------------------------------- */

/**
 * Render a commenter email in the browser with sample data, nothing sent:
 *   /?spudmag_mail_preview            -> index of links
 *   /?spudmag_mail_preview=reply      -> HTML (held | approved | reply)
 *   /?spudmag_mail_preview=reply&format=text -> the plain-text part
 * Uses the newest published post so links and titles look real.
 */
function spudmagMailPreview() {
	if (!isset($_GET['spudmag_mail_preview']) || !current_user_can('manage_options')) {
		return;
	}

	$emails = array('held', 'approved', 'reply');
	$email  = sanitize_key(wp_unslash($_GET['spudmag_mail_preview']));

	if (!in_array($email, $emails, true)) {
		$links = '';
		foreach ($emails as $name) {
			$htmlUrl = add_query_arg('spudmag_mail_preview', $name, home_url('/'));
			$textUrl = add_query_arg('format', 'text', $htmlUrl);
			$links  .= '<li><a href="' . esc_url($htmlUrl) . '">' . esc_html(ucfirst($name)) . '</a>'
				. ' (<a href="' . esc_url($textUrl) . '">' . esc_html__('plain text', 'spudworks-mag') . '</a>)</li>';
		}
		wp_die('<h1>' . esc_html__('Comment email previews', 'spudworks-mag') . '</h1><ul>' . $links . '</ul>', esc_html__('Email previews', 'spudworks-mag'), array('response' => 200));
	}

	global $wpdb;
	$postId  = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1");
	$postUrl = get_permalink($postId);

	$vars = array(
		'recipientName'  => 'Alex Reader',
		'postTitle'      => spudmagMailText(get_the_title($postId)),
		'postUrl'        => $postUrl,
		'commentExcerpt' => 'Really enjoyed this one. The bit about thinning paints over a wet palette finally made it click for me — going to try it on the next squad this weekend.',
		'commentUrl'     => $postUrl . '#comments',
		'replierName'    => 'Sam Hobbyist',
		'replyText'      => 'Same here! One tip: a drop of flow improver in the wet palette keeps the paint from skinning over during a long session. Post pictures when the squad is done.',
		'replyUrl'       => $postUrl . '#comments',
		'unsubscribeUrl' => $email === 'reply' ? add_query_arg('spudmag_unsub', 0, home_url('/')) : ''
	);

	$rendered = spudmagRenderCommentEmail('comment-' . $email, $vars);

	if (isset($_GET['format']) && $_GET['format'] === 'text') {
		header('Content-Type: text/plain; charset=UTF-8');
		echo $rendered['text'];
	} else {
		header('Content-Type: text/html; charset=UTF-8');
		echo $rendered['html'];
	}
	exit;
}
add_action('template_redirect', 'spudmagMailPreview');
