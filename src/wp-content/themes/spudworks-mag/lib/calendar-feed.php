<?php
/**
 * spudWorks Magazine — subscribable editorial calendar (ICS feed).
 *
 * Publishes scheduled posts and dated drafts as a private iCalendar feed an
 * editor can subscribe to from Apple Calendar or similar. Each entry carries a
 * night-before reminder and the post's outstanding To-Do items, so the To-Do
 * data layer in admin/todos.php (normally admin-only) is pulled in on demand
 * here — never at file scope, since this file loads on every front-end
 * request.
 */

// Fixed local hour the night-before reminder fires at, easy to retune later.
define('SPUDMAG_CALENDAR_REMINDER_HOUR', 18);

/**
 * The feed's secret token, generating and storing one on first use.
 */
function spudmagCalendarToken() {
	$token = get_option('spudmag_calendar_token');
	if (!is_string($token) || $token === '') {
		$token = wp_generate_password(40, false);
		update_option('spudmag_calendar_token', $token);
	}
	return $token;
}

/**
 * Replace the stored token, invalidating any previously issued feed URL.
 */
function spudmagCalendarRegenerateToken() {
	$token = wp_generate_password(40, false);
	update_option('spudmag_calendar_token', $token);
	return $token;
}

/**
 * The subscribe URL, in either the plain https or webcal scheme (the latter
 * is what hands the URL to a calendar app instead of opening it as a page).
 */
function spudmagCalendarFeedUrl($scheme = 'https') {
	$url = add_query_arg('spudmag_calendar', spudmagCalendarToken(), home_url('/'));
	if ($scheme === 'webcal') {
		$url = preg_replace('#^https?://#', 'webcal://', $url);
	}
	return $url;
}

/**
 * Scheduled posts and dated drafts, soonest first. Unlike spudmagTodoPosts()
 * this is not filtered to posts with outstanding work — a fully-ready piece
 * still publishes and belongs on the calendar — and it excludes undated
 * drafts entirely, since those have no date to place a calendar entry on.
 *
 * @return WP_Post[]
 */
function spudmagCalendarPosts() {
	$posts = get_posts(array(
		'post_type'        => 'post',
		'post_status'      => array('draft', 'future'),
		'posts_per_page'   => -1,
		'suppress_filters' => false
	));

	$posts = array_values(array_filter($posts, 'spudmagTodoHasDate'));

	usort($posts, function($a, $b) {
		return strcmp($a->post_date, $b->post_date);
	});

	return $posts;
}

/**
 * Escape a value for use inside an ICS content line (RFC 5545 §3.3.11).
 */
function spudmagCalendarEscape($text) {
	$text = str_replace(array('\\', ';', ',', "\n"), array('\\\\', '\\;', '\\,', '\\n'), (string) $text);
	return $text;
}

/**
 * Fold a single unfolded content line to the RFC 5545 75-octet limit,
 * continuation lines led by a single space, and terminate with CRLF.
 */
function spudmagCalendarFoldLine($line) {
	$chunks = array();
	while (strlen($line) > 75) {
		$chunks[] = substr($line, 0, 75);
		$line = ' ' . substr($line, 75);
	}
	$chunks[] = $line;
	return implode("\r\n", $chunks) . "\r\n";
}

/**
 * One post's incomplete To-Do items as ICS description lines, each prefixed
 * with its priority marker when it has one. Empty string when the post has no
 * outstanding items.
 */
function spudmagCalendarTodoLines($postId) {
	$lines = array();
	foreach (spudmagGetTodos($postId) as $item) {
		if ($item['done']) {
			continue;
		}
		$parsed = spudmagTodoParseTags($item['text']);
		$prefix = ($parsed['priority'] !== '') ? '[' . $parsed['priority'] . '] ' : '';
		$lines[] = '- ' . $prefix . $parsed['display'];
	}
	return implode("\n", $lines);
}

/**
 * One post's VEVENT (+ its VALARM), fully folded and CRLF-terminated.
 */
function spudmagCalendarEvent($post) {
	$host = wp_parse_url(home_url(), PHP_URL_HOST);
	$uid = 'spudmag-post-' . $post->ID . '@' . $host;

	$categories = get_the_category($post->ID);
	$category = !empty($categories) ? $categories[0]->name : '';
	// get_the_title() runs wptexturize, leaving curly quotes etc. as literal
	// HTML entities (e.g. &#8217;) meant for HTML output; decode back to plain
	// text since ICS fields are not HTML.
	$title = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
	if ($title === '') {
		$title = __('(no title)', 'spudworks-mag');
	}
	$summary = ($category !== '') ? $category . ': ' . $title : $title;

	$publishDay = substr($post->post_date, 0, 10); // Y-m-d
	$dtstart = str_replace('-', '', $publishDay);

	$timezone = wp_timezone();
	$reminder = new DateTime($publishDay . ' -1 day', $timezone);
	$reminder->setTime(SPUDMAG_CALENDAR_REMINDER_HOUR, 0, 0);
	$reminder->setTimezone(new DateTimeZone('UTC'));
	$trigger = $reminder->format('Ymd\THis\Z');

	$status = ($post->post_status === 'future') ? 'CONFIRMED' : 'TENTATIVE';
	$description = spudmagCalendarTodoLines($post->ID);

	$lines = array();
	$lines[] = 'BEGIN:VEVENT';
	$lines[] = 'UID:' . $uid;
	$lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
	$lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;
	$lines[] = 'SUMMARY:' . spudmagCalendarEscape($summary);
	$lines[] = 'STATUS:' . $status;
	if ($category !== '') {
		$lines[] = 'CATEGORIES:' . spudmagCalendarEscape($category);
	}
	if ($description !== '') {
		$lines[] = 'DESCRIPTION:' . spudmagCalendarEscape($description);
	}
	$lines[] = 'URL:' . admin_url('post.php?post=' . $post->ID . '&action=edit');
	$lines[] = 'BEGIN:VALARM';
	$lines[] = 'ACTION:DISPLAY';
	$lines[] = 'DESCRIPTION:' . spudmagCalendarEscape($summary . ' publishes tomorrow');
	$lines[] = 'TRIGGER;VALUE=DATE-TIME:' . $trigger;
	$lines[] = 'END:VALARM';
	$lines[] = 'END:VEVENT';

	$out = '';
	foreach ($lines as $line) {
		$out .= spudmagCalendarFoldLine($line);
	}
	return $out;
}

/**
 * The full ICS document: calendar-level headers plus one VEVENT per post.
 */
function spudmagCalendarDocument() {
	// The To-Do data layer normally only loads under is_admin(); pull it in
	// for the duration of building this document.
	require_once THEMEDIR . '/admin/todos.php';

	$lines = array();
	$lines[] = 'BEGIN:VCALENDAR';
	$lines[] = 'VERSION:2.0';
	$lines[] = 'PRODID:-//spudWorks Magazine//Editorial Calendar//EN';
	$lines[] = 'CALSCALE:GREGORIAN';
	$lines[] = 'METHOD:PUBLISH';
	$lines[] = 'X-WR-CALNAME:' . get_bloginfo('name') . ' Editorial';
	$lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
	$lines[] = 'X-PUBLISHED-TTL:PT1H';

	$out = '';
	foreach ($lines as $line) {
		$out .= spudmagCalendarFoldLine($line);
	}
	foreach (spudmagCalendarPosts() as $post) {
		$out .= spudmagCalendarEvent($post);
	}
	$out .= spudmagCalendarFoldLine('END:VCALENDAR');

	return $out;
}

/**
 * Serve the feed when the secret token is present and matches, then exit.
 * Any other outcome (missing/wrong token) falls through and does nothing,
 * leaving the request to resolve normally.
 */
function spudmagCalendarServe() {
	if (!isset($_GET['spudmag_calendar'])) {
		return;
	}

	$supplied = sanitize_text_field(wp_unslash($_GET['spudmag_calendar']));
	if (!hash_equals(spudmagCalendarToken(), $supplied)) {
		status_header(403);
		nocache_headers();
		exit;
	}

	nocache_headers();
	header('Content-Type: text/calendar; charset=utf-8');
	header('Content-Disposition: inline; filename="spudworks-editorial.ics"');
	echo spudmagCalendarDocument();
	exit;
}
add_action('init', 'spudmagCalendarServe');
