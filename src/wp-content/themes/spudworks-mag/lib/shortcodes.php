<?php
/**
 * spudWorks Magazine — editorial shortcodes.
 *
 *   [multipart prev-url=URL prev-link="Text" next-url=URL next-link="Text"]
 *       optional intro blurb
 *   [/multipart]
 *       A navigation box linking the parts of a multi-part post. Drop it at the
 *       top and/or bottom of a post. Every attribute is optional; the box only
 *       renders when at least one of prev-url / next-url is supplied, and each
 *       side shows only when its URL is present.
 *
 *   [author-note author="Optional Name"]Note text[/author-note]
 *       A styled aside headed "A note from the author, NAME". NAME defaults to
 *       the post author; the optional attribute overrides it (e.g. guest notes).
 */

/**
 * Inline arrow glyphs (kept as SVG so they inherit colour and scale cleanly).
 */
function spudmagArrowSvg($direction) {
	$points = ($direction === 'next') ? 'M5 12h14M13 6l6 6-6 6' : 'M19 12H5M11 18l-6-6 6-6';
	return '<svg class="multipart__arrow" width="22" height="22" viewBox="0 0 24 24" fill="none" '
		. 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
		. 'aria-hidden="true" focusable="false"><path d="' . $points . '"></path></svg>';
}

/**
 * [multipart] — previous / next navigation box for serialised posts.
 */
function spudmagMultipartShortcode($atts, $content = null) {
	$a = shortcode_atts(array(
		'prev-url'  => '',
		'prev-link' => __('Previous Post', 'spudworks-mag'),
		'next-url'  => '',
		'next-link' => __('Next Post', 'spudworks-mag')
	), $atts, 'multipart');

	$prevUrl = trim($a['prev-url']);
	$nextUrl = trim($a['next-url']);

	// Nothing to link -> render nothing.
	if ($prevUrl === '' && $nextUrl === '') {
		return '';
	}

	$intro = is_string($content) ? trim($content) : '';
	$introHtml = '';
	if ($intro !== '') {
		$introHtml = '<div class="multipart__intro">' . wpautop(wp_kses_post(do_shortcode($intro))) . '</div>';
	}

	$prevHtml = '';
	if ($prevUrl !== '') {
		$prevHtml = '<a class="multipart__link multipart__link--prev" href="' . esc_url($prevUrl) . '">'
			. spudmagArrowSvg('prev')
			. '<span class="multipart__text">'
				. '<span class="multipart__kicker">' . esc_html__('Previously', 'spudworks-mag') . '</span>'
				. '<span class="multipart__title">' . esc_html($a['prev-link']) . '</span>'
			. '</span></a>';
	}

	$nextHtml = '';
	if ($nextUrl !== '') {
		$nextHtml = '<a class="multipart__link multipart__link--next" href="' . esc_url($nextUrl) . '">'
			. '<span class="multipart__text">'
				. '<span class="multipart__kicker">' . esc_html__('Up next', 'spudworks-mag') . '</span>'
				. '<span class="multipart__title">' . esc_html($a['next-link']) . '</span>'
			. '</span>'
			. spudmagArrowSvg('next') . '</a>';
	}

	return '<nav class="multipart" aria-label="' . esc_attr__('Multi-part navigation', 'spudworks-mag') . '">'
		. $introHtml
		. '<div class="multipart__nav">' . $prevHtml . $nextHtml . '</div>'
		. '</nav>';
}
add_shortcode('multipart', 'spudmagMultipartShortcode');

/**
 * [author-note] — a styled note from the post's author.
 */
function spudmagAuthorNoteShortcode($atts, $content = null) {
	if (!is_string($content) || trim($content) === '') {
		return '';
	}

	$a = shortcode_atts(array(
		'author' => ''
	), $atts, 'author-note');

	$name = trim($a['author']);
	if ($name === '') {
		$authorId = (int) get_post_field('post_author', get_the_ID());
		$name = get_the_author_meta('display_name', $authorId);
	}

	$heading = $name !== ''
		? sprintf(__('A note from the author, %s', 'spudworks-mag'), $name)
		: __('A note from the author', 'spudworks-mag');

	$body = wpautop(wp_kses_post(do_shortcode(trim($content))));

	return '<aside class="author-note">'
		. '<p class="author-note__header">' . esc_html($heading) . '</p>'
		. '<div class="author-note__body">' . $body . '</div>'
		. '</aside>';
}
add_shortcode('author-note', 'spudmagAuthorNoteShortcode');

/**
 * [featured-comment] — pull a reader comment into the body of a post as a
 * quoted callout, wrapped in oversized typographic quote marks.
 *
 *   [featured-comment id=42]Optional preface[/featured-comment]
 *       Fetches approved comment #42 and shows its text + author avatar.
 *
 *   [featured-comment author="Jane Doe" text="A great read."]Optional preface[/featured-comment]
 *       Uses the supplied author/text when no id is given (no avatar).
 */
function spudmagFeaturedCommentShortcode($atts, $content = null) {
	$a = shortcode_atts(array(
		'id'     => '',
		'author' => '',
		'text'   => ''
	), $atts, 'featured-comment');

	$quote   = '';
	$author  = trim($a['author']);
	$avatar  = '';

	$id = (int) $a['id'];
	if ($id > 0) {
		$comment = get_comment($id);
		// Only surface a real, approved comment.
		if ($comment && $comment->comment_approved === '1') {
			$quote  = wpautop(wp_kses_post(get_comment_text($comment)));
			$author = get_comment_author($comment);
			$avatar = get_avatar($comment, 64);
		}
	}

	// Fall back to the manual author/text attributes when there's no usable id.
	if ($quote === '' && trim($a['text']) !== '') {
		$quote = wpautop(wp_kses_post($a['text']));
	}

	// Nothing to quote -> render nothing.
	if ($quote === '') {
		return '';
	}

	$intro = is_string($content) ? trim($content) : '';
	$introHtml = '';
	if ($intro !== '') {
		$introHtml = '<div class="featured-comment__preface">' . wpautop(wp_kses_post(do_shortcode($intro))) . '</div>';
	}

	$citeHtml = '';
	if ($author !== '' || $avatar !== '') {
		// An em dash introduces the name only when there's no avatar to do it.
		$authorPrefix = ($author !== '' && $avatar === '') ? '&#8212;&#160;' : '';
		$citeHtml = '<figcaption class="featured-comment__cite">'
			. ($avatar !== '' ? '<span class="featured-comment__avatar">' . $avatar . '</span>' : '')
			. ($author !== '' ? '<span class="featured-comment__author">' . $authorPrefix . esc_html($author) . '</span>' : '')
			. '</figcaption>';
	}

	return '<figure class="featured-comment">'
		. '<p class="featured-comment__label">' . esc_html__('Featured Reader Comment', 'spudworks-mag') . '</p>'
		. $introHtml
		. '<blockquote class="featured-comment__quote">' . $quote . '</blockquote>'
		. $citeHtml
		. '</figure>';
}
add_shortcode('featured-comment', 'spudmagFeaturedCommentShortcode');

/**
 * Inline status icons for the [notice] box (SVG so they inherit colour/scale).
 */
function spudmagNoticeIcon($level) {
	$open = '<svg class="notice__svg" width="22" height="22" viewBox="0 0 24 24" fill="none" '
		. 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
		. 'aria-hidden="true" focusable="false">';

	if ($level === 'warn') {
		// Warning triangle.
		$paths = '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path>'
			. '<line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>';
	} elseif ($level === 'danger') {
		// Octagon with exclamation.
		$paths = '<polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon>'
			. '<line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>';
	} else {
		// Info circle.
		$paths = '<circle cx="12" cy="12" r="10"></circle>'
			. '<line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>';
	}

	return $open . $paths . '</svg>';
}

/**
 * [notice] — a full-width callout box that flags something the reader should
 * not miss. The level sets the icon and accent colour.
 *
 *   [notice level="info|warn|danger"]Text in the notice[/notice]
 *       level defaults to "info".
 */
function spudmagNoticeShortcode($atts, $content = null) {
	if (!is_string($content) || trim($content) === '') {
		return '';
	}

	$a = shortcode_atts(array(
		'level' => 'info'
	), $atts, 'notice');

	$level = in_array($a['level'], array('info', 'warn', 'danger'), true) ? $a['level'] : 'info';
	$body  = wpautop(wp_kses_post(do_shortcode(trim($content))));

	return '<div class="notice notice--' . esc_attr($level) . '" role="note">'
		. '<span class="notice__icon">' . spudmagNoticeIcon($level) . '</span>'
		. '<div class="notice__body">' . $body . '</div>'
		. '</div>';
}
add_shortcode('notice', 'spudmagNoticeShortcode');
