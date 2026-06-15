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
