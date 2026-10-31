<?php
/**
 * spudWorks Magazine — daily plain-link cleanup (WP-Cron).
 *
 * The Insert/Edit Link search (admin/editor-tools.php) hands drafts a plain
 * ?p=ID link, since a draft's slug isn't settled yet. Once that draft goes
 * live the link still works (WordPress redirects it), but every post holding
 * it should point straight at the real permalink. Each morning this job checks
 * only the posts still in motion — scheduled ones, plus those published in the
 * last 48 hours (a wide window in case a run was missed) — and rewrites any
 * ?p=ID link in them whose target is now published. Older published posts are
 * never scanned, so the cost tracks the publishing queue, not the archive.
 *
 * Scheduled as a single event re-armed on init rather than a 'daily'
 * recurrence, so it stays pinned to local wall-clock time across DST changes.
 * Loads on every request because WP-Cron runs off front-end hits.
 */

define('SPUDMAG_LINK_FIXER_HOOK', 'spudmag_fix_plain_links');

// Local time the cleanup runs, a few minutes after the usual 7:00 publish slot.
define('SPUDMAG_LINK_FIXER_TIME', '07:05');

/**
 * Arm the next run if none is pending.
 */
function spudmagLinkFixerSchedule() {
	if (wp_next_scheduled(SPUDMAG_LINK_FIXER_HOOK)) {
		return;
	}

	$next = new DateTimeImmutable('today ' . SPUDMAG_LINK_FIXER_TIME, wp_timezone());
	if ($next->getTimestamp() <= time()) {
		$next = $next->modify('+1 day');
	}
	wp_schedule_single_event($next->getTimestamp(), SPUDMAG_LINK_FIXER_HOOK);
}
add_action('init', 'spudmagLinkFixerSchedule');

/**
 * Drop the pending run when the theme is switched away from.
 */
function spudmagLinkFixerUnschedule() {
	wp_clear_scheduled_hook(SPUDMAG_LINK_FIXER_HOOK);
}
add_action('switch_theme', 'spudmagLinkFixerUnschedule');

/**
 * Rewrite plain ?p=ID links to published posts inside the in-flight posts.
 *
 * Two queries regardless of archive size: one for the candidate posts, one to
 * learn which of the IDs they link to are published. Links to targets still
 * unpublished are left for a later run.
 *
 * Writes post_content directly rather than through wp_update_post(), so the
 * fix doesn't spawn revisions, bump modified dates or re-fire save_post
 * handlers for what is a mechanical URL swap.
 *
 * @return int Number of posts whose content changed.
 */
function spudmagFixPlainLinks() {
	global $wpdb;

	$since = gmdate('Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS);
	$candidates = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts}
		 WHERE post_type <> 'revision'
		   AND (post_status = 'future' OR (post_status = 'publish' AND post_date_gmt >= %s))
		   AND post_content LIKE %s",
		$since,
		'%' . $wpdb->esc_like('?p=') . '%'
	));
	if (!$candidates) {
		return 0;
	}

	// The home URL with either scheme (or none), an optional slash, then ?p=ID.
	$host = preg_quote(preg_replace('#^https?:#', '', untrailingslashit(home_url())), '#');
	$pattern = '#(?:https?:)?' . $host . '/?\?p=(\d+)(?!\d)#';

	$linkedIds = array();
	foreach ($candidates as $candidate) {
		if (preg_match_all($pattern, $candidate->post_content, $matches)) {
			$linkedIds = array_merge($linkedIds, $matches[1]);
		}
	}
	$linkedIds = array_unique(array_map('intval', $linkedIds));
	if (!$linkedIds) {
		return 0;
	}

	$placeholders = implode(',', array_fill(0, count($linkedIds), '%d'));
	$publishedIds = $wpdb->get_col($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID IN ($placeholders)",
		$linkedIds
	));
	if (!$publishedIds) {
		return 0;
	}
	$publishedIds = array_flip(array_map('intval', $publishedIds));

	$changed = 0;
	foreach ($candidates as $candidate) {
		$content = preg_replace_callback($pattern, function ($match) use ($publishedIds) {
			return isset($publishedIds[(int) $match[1]]) ? get_permalink((int) $match[1]) : $match[0];
		}, $candidate->post_content);
		if ($content === null || $content === $candidate->post_content) {
			continue;
		}

		$wpdb->update($wpdb->posts, array('post_content' => $content), array('ID' => $candidate->ID));
		clean_post_cache($candidate->ID);
		$changed++;
	}

	return $changed;
}
add_action(SPUDMAG_LINK_FIXER_HOOK, 'spudmagFixPlainLinks');
