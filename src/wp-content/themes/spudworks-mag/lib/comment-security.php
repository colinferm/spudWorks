<?php
/**
 * spudWorks Magazine — comment anti-spam (JS-timed honeypot).
 *
 * Open WordPress comments are a magnet for bots. Rather than a captcha, we use
 * two complementary traps that cost a human nothing:
 *
 *   1. Honeypot fields — two text inputs (aeryn_info, crichton_info) that are
 *      rendered (not display:none) but shoved far off-screen with CSS, so a
 *      person never sees them while a naive bot dutifully fills them in. Any
 *      content in either field marks the comment as spam.
 *
 *   2. A timing trap — a hidden field stamped by JavaScript with the moment the
 *      form rendered for the reader. A submission that arrives less than a
 *      minute later (or with no/invalid stamp, e.g. a scripted POST) is treated
 *      as a bot and marked spam.
 *
 * Logged-in users are trusted and skip the timing trap. Flagged comments are
 * marked as spam (saved, hidden, reviewable) rather than hard-rejected.
 */

// Seconds a genuine reader is assumed to need before submitting.
define('SPUDMAG_COMMENT_MIN_SECONDS', 60);

/**
 * Inject the honeypot + timing fields into the comment form. Hooked to
 * comment_form_after_fields, which fires for logged-out commenters (after the
 * name/email/url fields) — exactly the population we screen.
 */
function spudmagHoneypotFields() {
	// aeryn_info / crichton_info: real, labelled inputs kept off-screen by CSS
	// (see .comment-honeypot). tabindex="-1" + autocomplete="off" keep keyboard
	// users and browser autofill away from them.
	echo '<p class="comment-honeypot" aria-hidden="true">'
		. '<label for="aeryn_info">' . esc_html__('Leave this field empty', 'spudworks-mag') . '</label>'
		. '<input type="text" name="aeryn_info" id="aeryn_info" value="" tabindex="-1" autocomplete="off">'
		. '</p>';
	echo '<p class="comment-honeypot" aria-hidden="true">'
		. '<label for="crichton_info">' . esc_html__('Leave this field empty too', 'spudworks-mag') . '</label>'
		. '<input type="text" name="crichton_info" id="crichton_info" value="" tabindex="-1" autocomplete="off">'
		. '</p>';

	// Timing stamp: empty on render, filled by js/comments.js on load.
	echo '<input type="hidden" name="spudmag_rendered" id="spudmag_rendered" value="">';
}
add_action('comment_form_after_fields', 'spudmagHoneypotFields');

/**
 * Force the comment currently being processed to spam. Registered from inside
 * the preprocess_comment filter so the detection logic stays in one place while
 * the status is set on the correct hook (pre_comment_approved expects the
 * approval value, not the comment data).
 */
function spudmagForceCommentSpam() {
	add_filter('pre_comment_approved', 'spudmagReturnSpam', 99, 2);
}
function spudmagReturnSpam($approved, $commentdata) {
	return 'spam';
}

/**
 * Screen an incoming comment. Order is deliberate:
 *   1. Honeypot filled (any visitor)            -> spam.
 *   2. Logged-in user                            -> trust, skip remaining checks.
 *   3. Missing/invalid stamp, or submitted in
 *      under SPUDMAG_COMMENT_MIN_SECONDS         -> spam.
 *   4. Otherwise                                 -> untouched (normal moderation).
 */
function spudmagCommentSpamCheck($commentdata) {
	$aeryn    = isset($_POST['aeryn_info']) ? trim(wp_unslash($_POST['aeryn_info'])) : '';
	$crichton = isset($_POST['crichton_info']) ? trim(wp_unslash($_POST['crichton_info'])) : '';

	if ($aeryn !== '' || $crichton !== '') {
		spudmagForceCommentSpam();
		return $commentdata;
	}

	// Trusted users skip the timing trap (their form has no stamp field).
	if (is_user_logged_in()) {
		return $commentdata;
	}

	$rendered   = isset($_POST['spudmag_rendered']) ? trim(wp_unslash($_POST['spudmag_rendered'])) : '';
	$renderedTs = $rendered !== '' ? strtotime($rendered) : 0;

	// No usable stamp (JS never ran / scripted POST) or suspiciously fast.
	if ($renderedTs <= 0 || (time() - $renderedTs) < SPUDMAG_COMMENT_MIN_SECONDS) {
		spudmagForceCommentSpam();
	}

	return $commentdata;
}
add_filter('preprocess_comment', 'spudmagCommentSpamCheck');
