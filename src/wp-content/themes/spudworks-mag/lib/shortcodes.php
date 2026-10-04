<?php
/**
 * spudWorks Magazine — editorial shortcodes.
 *
 *   [multipart prev-id=ID prev-url=URL prev-link="Text" next-id=ID next-url=URL next-link="Text"]
 *       optional intro blurb
 *   [/multipart]
 *       A navigation box linking the parts of a multi-part post. Drop it at the
 *       top and/or bottom of a post. Every attribute is optional; the box only
 *       renders when at least one side resolves to a link. On each side, an id
 *       wins over a url; an id must point at a *published* post or that side is
 *       dropped (lets a series be wired up ahead of a scheduled release without
 *       a follow-up edit). Link text defaults to the target post's title when
 *       an id is used and no explicit prev-link/next-link is given.
 *
 *   [author-note author="Optional Name"]Note text[/author-note]
 *       A styled aside headed "A note from the author, NAME". NAME defaults to
 *       the post author; the optional attribute overrides it (e.g. guest notes).
 *
 *   [necro-card size="full|half" name="..." role="..." value="..." stat-*="..." wargear="..." skills="..."]
 *       [necro-weapon name="..." stat-sr="..." stat-lr="..." stat-s="..." stat-ap="..." stat-l="..." traits="..."/]
 *   [/necro-card]
 *       A Necromunda-style fighter stat card. name is the only required
 *       attribute; role/value show an em dash when omitted; wargear/skills and
 *       the weapons table are omitted entirely when there's nothing to show.
 *       Consecutive cards in a post auto-group into a centered, wrapping row.
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
 * Resolve one side of a [multipart] box to a url and a label.
 *
 * An id beats a url. The linked post must be published, so a part that is
 * still a draft or scheduled drops out of the box until it goes live —
 * that's the point: a series can be wired up ahead of time and left alone.
 * Returns an empty url when there is nothing to link.
 */
function spudmagMultipartLink($id, $url, $label, $fallbackLabel) {
	$postId = (int) $id;
	$label  = trim($label);

	if ($postId > 0) {
		if (get_post_status($postId) !== 'publish') {
			return array('url' => '', 'label' => '');
		}
		$permalink = get_permalink($postId);
		if (!$permalink) {
			return array('url' => '', 'label' => '');
		}
		return array(
			'url'   => $permalink,
			'label' => $label !== '' ? $label : (get_the_title($postId) ?: $fallbackLabel)
		);
	}

	$url = trim($url);
	return array(
		'url'   => $url,
		'label' => $label !== '' ? $label : $fallbackLabel
	);
}

/**
 * [multipart] — previous / next navigation box for serialised posts.
 */
function spudmagMultipartShortcode($atts, $content = null) {
	$a = shortcode_atts(array(
		'prev-id'   => '',
		'prev-url'  => '',
		'prev-link' => '',
		'next-id'   => '',
		'next-url'  => '',
		'next-link' => ''
	), $atts, 'multipart');

	$prev = spudmagMultipartLink($a['prev-id'], $a['prev-url'], $a['prev-link'], __('Previous Post', 'spudworks-mag'));
	$next = spudmagMultipartLink($a['next-id'], $a['next-url'], $a['next-link'], __('Next Post', 'spudworks-mag'));

	$prevUrl = $prev['url'];
	$nextUrl = $next['url'];

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
		// rel="prev" is the HTML link-relation built for exactly this: "the
		// previous item in a series" (the same vocabulary WP core emits for
		// archive pagination in <head>).
		$prevHtml = '<a class="multipart__link multipart__link--prev" href="' . esc_url($prevUrl) . '" rel="prev">'
			. spudmagArrowSvg('prev')
			. '<span class="multipart__text">'
				. '<span class="multipart__kicker">' . esc_html__('Previously', 'spudworks-mag') . '</span>'
				. '<span class="multipart__title">' . esc_html($prev['label']) . '</span>'
			. '</span></a>';
	}

	$nextHtml = '';
	if ($nextUrl !== '') {
		$nextHtml = '<a class="multipart__link multipart__link--next" href="' . esc_url($nextUrl) . '" rel="next">'
			. '<span class="multipart__text">'
				. '<span class="multipart__kicker">' . esc_html__('Up next', 'spudworks-mag') . '</span>'
				. '<span class="multipart__title">' . esc_html($next['label']) . '</span>'
			. '</span>'
			. spudmagArrowSvg('next') . '</a>';
	}

	// A post can carry more than one [multipart] (e.g. one at the top and one
	// at the bottom, as the Dark Matter series does) — number the landmark
	// label from the second instance on so a screen reader's landmark list
	// doesn't show two identically-named "Multi-part navigation" regions.
	static $instance = 0;
	$instance++;
	$label = $instance > 1
		? sprintf(__('Multi-part navigation %d', 'spudworks-mag'), $instance)
		: __('Multi-part navigation', 'spudworks-mag');

	return '<nav class="multipart" aria-label="' . esc_attr($label) . '">'
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

/**
 * [pullquote] — a phrase pulled from the post's own copy, set apart for emphasis.
 *
 *   [pullquote align="center|left|right" attribution="Optional"]Quote text[/pullquote]
 *       align defaults to "center" (a breakout wider than the text column);
 *       "left"/"right" float the quote so body text wraps around it. attribution,
 *       when given, appears right-aligned below the quote behind an em dash.
 */
function spudmagPullquoteShortcode($atts, $content = null) {
	if (!is_string($content) || trim($content) === '') {
		return '';
	}

	$a = shortcode_atts(array(
		'align'       => 'center',
		'attribution' => ''
	), $atts, 'pullquote');

	$align = in_array($a['align'], array('center', 'left', 'right'), true) ? $a['align'] : 'center';
	// "center" is the base treatment and carries no extra class.
	$class = $align === 'center' ? 'pullquote' : 'pullquote ' . $align;

	$body = wpautop(wp_kses_post(do_shortcode(trim($content))));

	$attribution = trim($a['attribution']);
	$citeHtml = '';
	if ($attribution !== '') {
		$citeHtml = '<cite class="pullquote__attribution">&#8212;&#160;' . esc_html($attribution) . '</cite>';
	}

	return '<div class="' . esc_attr($class) . '">' . $body . $citeHtml . '</div>';
}
add_shortcode('pullquote', 'spudmagPullquoteShortcode');

/**
 * [aside] — a short side note set beside the prose rather than in it.
 *
 *   [aside title="Optional heading"]Note text[/aside]
 *       Floats a small boxed note to the right of the column (a third of it on
 *       desktop, half on tablets) so the body text wraps alongside; on phones it
 *       becomes a full-width block. title defaults to "An Aside"; pass title=""
 *       to render the box with no heading at all.
 */
function spudmagAsideShortcode($atts, $content = null) {
	if (!is_string($content) || trim($content) === '') {
		return '';
	}

	$a = shortcode_atts(array(
		'title' => __('An Aside', 'spudworks-mag')
	), $atts, 'aside');

	$title     = trim($a['title']);
	$titleHtml = '';
	if ($title !== '') {
		$titleHtml = '<p class="aside__title">' . esc_html($title) . '</p>';
	}

	$body = wpautop(wp_kses_post(do_shortcode(trim($content))));

	return '<aside class="aside">'
		. $titleHtml
		. '<div class="aside__body">' . $body . '</div>'
		. '</aside>';
}
add_shortcode('aside', 'spudmagAsideShortcode');

/**
 * [toc] — an auto-generated table of contents for a long post.
 *
 *   [toc]Optional heading text[/toc]
 *       Drops a right-floated contents box wherever it's placed. The enclosed
 *       text becomes the box heading (defaults to "Contents"). The list itself is
 *       built later from the post's H2/H3 headings — see spudmagRenderToc().
 *
 * The shortcode only stashes the heading text and leaves a sentinel; the real
 * work happens in a late the_content filter, where the fully rendered headings
 * are in hand. That guarantees the ids injected into the headings and the links
 * in the list are generated in one pass from the same nodes, so they always match.
 */
function spudmagTocShortcode($atts, $content = null) {
	$GLOBALS['spudmag_toc_intro'] = is_string($content) ? trim($content) : '';
	return '%%SPUDMAG_TOC%%';
}
add_shortcode('toc', 'spudmagTocShortcode');

/**
 * Build the contents box markup from a list of headings collected by
 * spudmagRenderToc(). Each heading is array('level' => 'h2'|'h3', 'id' => slug,
 * 'label' => inner HTML). Inline formatting in the label is preserved (matching
 * the headings) while nested links are stripped so we never nest anchors.
 */
function spudmagBuildTocHtml($headings) {
	$intro = isset($GLOBALS['spudmag_toc_intro']) ? trim((string) $GLOBALS['spudmag_toc_intro']) : '';
	if ($intro === '') {
		$intro = __('Contents', 'spudworks-mag');
	}

	$allowed = array(
		'em'     => array(),
		'i'      => array(),
		'strong' => array(),
		'b'      => array(),
		'span'   => array(),
		'code'   => array()
	);

	$items = '';
	foreach ($headings as $h) {
		$label = wp_kses($h['label'], $allowed);
		$items .= '<li class="toc__item toc__item--' . esc_attr($h['level']) . '">'
			. '<a class="toc__link" href="#' . esc_attr($h['id']) . '">' . $label . '</a>'
			. '</li>';
	}

	return '<nav class="toc" aria-label="' . esc_attr__('Table of contents', 'spudworks-mag') . '">'
		. '<p class="toc__title">' . wp_kses_post(do_shortcode($intro)) . '</p>'
		. '<ul class="toc__list">' . $items . '</ul>'
		. '</nav>';
}

/**
 * Replace the [toc] sentinel with the rendered contents box, injecting matching
 * id anchors into the post's H2/H3 headings along the way. Runs after both
 * wpautop (10) and do_shortcode (11) so the headings are final HTML.
 */
function spudmagRenderToc($content) {
	if (strpos($content, '%%SPUDMAG_TOC%%') === false) {
		return $content;
	}

	$headings = array();
	$used = array();

	$content = preg_replace_callback('/<(h[23])\b([^>]*)>(.*?)<\/\1>/is', function ($m) use (&$headings, &$used) {
		$tag   = strtolower($m[1]);
		$attrs = $m[2];
		$inner = $m[3];

		// Honour an existing id; otherwise slugify the heading text and ensure it
		// is unique within the document.
		if (preg_match('/\bid=["\']([^"\']+)["\']/i', $attrs, $idMatch)) {
			$id = $idMatch[1];
		} else {
			$base = sanitize_title(wp_strip_all_tags($inner));
			if ($base === '') {
				$base = 'section';
			}
			$id = $base;
			$n = 2;
			while (isset($used[$id])) {
				$id = $base . '-' . $n;
				$n++;
			}
			$attrs .= ' id="' . esc_attr($id) . '"';
		}

		$used[$id] = true;
		$headings[] = array('level' => $tag, 'id' => $id, 'label' => $inner);

		return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
	}, $content);

	$toc = empty($headings) ? '' : spudmagBuildTocHtml($headings);

	// Drop the wpautop <p> wrapper around the sentinel so we don't nest a block
	// box inside a paragraph.
	$content = str_replace(
		array('<p>%%SPUDMAG_TOC%%</p>', '%%SPUDMAG_TOC%%'),
		$toc,
		$content
	);

	unset($GLOBALS['spudmag_toc_intro']);

	return $content;
}
add_filter('the_content', 'spudmagRenderToc', 99);

/**
 * [necro-weapon] — one weapon line inside a [necro-card]. Self-closing; only
 * meaningful nested inside [necro-card], which is the only thing that ever
 * assembles its <tr> fragments into a table.
 *
 *   [necro-weapon name="Stub gun" stat-sr="6" stat-lr="12" stat-s="3" stat-ap="-" stat-l="1" traits="Light"/]
 *       Every attribute is optional and left blank when omitted. stat-sr/stat-lr
 *       get a trailing " only when the value given is a plain whole number (a
 *       melee weapon's "E" range, for instance, is left bare).
 */
function spudmagNecroWeaponRange($value) {
	$value = trim($value);
	if ($value === '') {
		return '';
	}
	return ctype_digit($value) ? $value . '"' : $value;
}

function spudmagNecroWeaponShortcode($atts) {
	$a = shortcode_atts(array(
		'name'    => '',
		'stat-sr' => '',
		'stat-lr' => '',
		'stat-s'  => '',
		'stat-ap' => '',
		'stat-l'  => '',
		'traits'  => ''
	), $atts, 'necro-weapon');

	return '<tr class="necro-card__weapon-row">'
		. '<td class="necro-card__weapon-name">' . esc_html($a['name']) . '</td>'
		. '<td>' . esc_html(spudmagNecroWeaponRange($a['stat-sr'])) . '</td>'
		. '<td>' . esc_html(spudmagNecroWeaponRange($a['stat-lr'])) . '</td>'
		. '<td>' . esc_html($a['stat-s']) . '</td>'
		. '<td>' . esc_html($a['stat-ap']) . '</td>'
		. '<td>' . esc_html($a['stat-l']) . '</td>'
		. '<td class="necro-card__weapon-traits">' . esc_html($a['traits']) . '</td>'
		. '</tr>';
}
add_shortcode('necro-weapon', 'spudmagNecroWeaponShortcode');

/**
 * One <td> in a [necro-card] stat row. Blank when the attribute is omitted —
 * only an explicit value (including a literal "-") ever prints.
 */
function spudmagNecroStatCell($value) {
	return '<td>' . esc_html(trim($value)) . '</td>';
}

/**
 * [necro-card] — a Necromunda-style fighter stat card.
 *
 *   [necro-card size="full|half" name="Trevor" role="Forge Boss (Champion)"
 *       value="160" stat-m="4" stat-ws="3+" stat-bs="3+" stat-s="3" stat-t="4"
 *       stat-w="2" stat-i="3" stat-a="2" stat-sv="5+" stat-ld="7" stat-cl="8"
 *       stat-wil="6" stat-int="6" stat-xp="37" wargear="Respirator"
 *       skills="Inspiring, Nerves of Steel"]
 *       [necro-weapon .../]
 *       [necro-weapon .../]
 *   [/necro-card]
 *       size defaults to "full"; name is the only required attribute. role/value
 *       show an em dash when omitted. wargear/skills lines are omitted entirely
 *       when blank, and the weapons table only appears when at least one
 *       [necro-weapon] is nested inside. Consecutive [necro-card]s in a post
 *       are automatically grouped into a centered, wrapping row — see
 *       spudmagRenderNecroCardRows().
 */
function spudmagNecroCardShortcode($atts, $content = null) {
	$a = shortcode_atts(array(
		'size'     => 'full',
		'name'     => '',
		'role'     => '',
		'value'    => '',
		'stat-m'   => '',
		'stat-ws'  => '',
		'stat-bs'  => '',
		'stat-s'   => '',
		'stat-t'   => '',
		'stat-w'   => '',
		'stat-i'   => '',
		'stat-a'   => '',
		'stat-sv'  => '',
		'stat-ld'  => '',
		'stat-cl'  => '',
		'stat-wil' => '',
		'stat-int' => '',
		'stat-xp'  => '',
		'wargear'  => '',
		'skills'   => ''
	), $atts, 'necro-card');

	$name = trim($a['name']);
	if ($name === '') {
		return '';
	}

	$size = in_array($a['size'], array('full', 'half'), true) ? $a['size'] : 'full';

	$role  = trim($a['role']);
	$value = trim($a['value']);

	$headerHtml = '<div class="necro-card__band"></div>'
		. '<div class="necro-card__header">'
			. '<div class="necro-card__identity">'
				// A heading (rather than a plain <p>) so a screen-reader user
				// can jump card-to-card by heading when a post strings
				// several of these together — h4 sits safely under whatever
				// h2/h3 the surrounding article uses, and [toc] only scans
				// h2/h3 so this can't leak into anyone's table of contents.
				. '<h4 class="necro-card__name">' . esc_html($name) . '</h4>'
				. '<p class="necro-card__role">' . esc_html($role !== '' ? $role : '—') . '</p>'
			. '</div>'
			. '<div class="necro-card__value">'
				. '<span class="necro-card__value-number">' . esc_html($value !== '' ? $value : '—') . '</span>'
				. '<span class="necro-card__value-label">' . esc_html__('Credits', 'spudworks-mag') . '</span>'
			. '</div>'
		. '</div>';

	$primaryLabels   = array('M', 'WS', 'BS', 'S', 'T', 'W', 'I', 'A', 'Sv');
	$primaryKeys     = array('stat-m', 'stat-ws', 'stat-bs', 'stat-s', 'stat-t', 'stat-w', 'stat-i', 'stat-a', 'stat-sv');
	$secondaryLabels = array('Ld', 'Cl', 'Wil', 'Int', 'XP');
	$secondaryKeys   = array('stat-ld', 'stat-cl', 'stat-wil', 'stat-int', 'stat-xp');

	// Two independent tables, not one 14-column row: on a narrow card the
	// secondary group needs to drop to a line of its own rather than force a
	// horizontal scrollbar, and a table row can't reflow its own columns.
	$primaryHeadHtml = '';
	$primaryBodyHtml = '';
	foreach ($primaryLabels as $label) {
		$primaryHeadHtml .= '<th scope="col">' . esc_html($label) . '</th>';
	}
	foreach ($primaryKeys as $key) {
		$primaryBodyHtml .= spudmagNecroStatCell($a[$key]);
	}

	$secondaryHeadHtml = '';
	$secondaryBodyHtml = '';
	foreach ($secondaryLabels as $label) {
		$secondaryHeadHtml .= '<th scope="col">' . esc_html($label) . '</th>';
	}
	foreach ($secondaryKeys as $key) {
		$secondaryBodyHtml .= spudmagNecroStatCell($a[$key]);
	}

	// Split into two <table>s purely for layout (so the secondary group can
	// drop to its own line on a narrow card) — role="group" + aria-label ties
	// them back together as one logical characteristics block, so a screen
	// reader user doesn't land on two small, seemingly-unrelated tables.
	$statsHtml = '<div class="necro-card__stat-groups" role="group" aria-label="'
			. esc_attr(sprintf(__('%s characteristics', 'spudworks-mag'), $name)) . '">'
		. '<table class="necro-card__stat-group necro-card__stat-group--primary">'
			. '<thead><tr>' . $primaryHeadHtml . '</tr></thead>'
			. '<tbody><tr>' . $primaryBodyHtml . '</tr></tbody>'
		. '</table>'
		. '<table class="necro-card__stat-group necro-card__stat-group--secondary">'
			. '<thead><tr>' . $secondaryHeadHtml . '</tr></thead>'
			. '<tbody><tr>' . $secondaryBodyHtml . '</tr></tbody>'
		. '</table>'
	. '</div>';

	// The raw content should only ever be whitespace and nested [necro-weapon]
	// tags, but wpautop (which runs before do_shortcode) inserts <br> between
	// them since they sit on consecutive lines inside one paragraph-shaped
	// block. Left in, those <br> tags land as direct children of our <tbody>,
	// which browsers "foster parent" out in front of the table. Stripping tags
	// first is safe: shortcode brackets aren't HTML tags, so this can't touch
	// the [necro-weapon] occurrences themselves.
	$rawContent = is_string($content) ? $content : '';
	$weaponsHtml = '';
	if (strpos($rawContent, '[necro-weapon') !== false) {
		$rows = do_shortcode(wp_strip_all_tags($rawContent));
		$weaponsHtml = '<div class="necro-card__weapons">'
			. '<table class="necro-card__weapon-table" aria-label="'
					. esc_attr(sprintf(__('%s weapons', 'spudworks-mag'), $name)) . '">'
				. '<thead><tr>'
					. '<th class="necro-card__weapon-name" scope="col">' . esc_html__('Weapon', 'spudworks-mag') . '</th>'
					. '<th scope="col">' . esc_html__('SR', 'spudworks-mag') . '</th>'
					. '<th scope="col">' . esc_html__('LR', 'spudworks-mag') . '</th>'
					. '<th scope="col">' . esc_html__('Str', 'spudworks-mag') . '</th>'
					. '<th scope="col">' . esc_html__('AP', 'spudworks-mag') . '</th>'
					. '<th scope="col">' . esc_html__('L', 'spudworks-mag') . '</th>'
					. '<th class="necro-card__weapon-traits" scope="col">' . esc_html__('Traits', 'spudworks-mag') . '</th>'
				. '</tr></thead>'
				. '<tbody>' . $rows . '</tbody>'
			. '</table>'
		. '</div>';
	}

	// Wargear/Skills are name/value pairs, which is exactly what <dl> is for
	// — more correct than reusing <p><span> for something that isn't really
	// prose, and it costs nothing visually (still styled as label + value).
	$wargear = trim($a['wargear']);
	$skills  = trim($a['skills']);
	$metaHtml = '';
	if ($wargear !== '' || $skills !== '') {
		$metaHtml .= '<dl class="necro-card__meta">';
		if ($wargear !== '') {
			$metaHtml .= '<dt class="necro-card__meta-label">' . esc_html__('Wargear', 'spudworks-mag') . '</dt>'
				. '<dd class="necro-card__meta-value">' . esc_html($wargear) . '</dd>';
		}
		if ($skills !== '') {
			$metaHtml .= '<dt class="necro-card__meta-label">' . esc_html__('Skills', 'spudworks-mag') . '</dt>'
				. '<dd class="necro-card__meta-value">' . esc_html($skills) . '</dd>';
		}
		$metaHtml .= '</dl>';
	}

	// Wrapped in sentinel comments so spudmagRenderNecroCardRows() can find and
	// group consecutive cards after the fact without having to count nested
	// <article>s to locate this card's own closing tag.
	//
	// <article>, not <div>: a fighter card is exactly the kind of
	// self-contained, independently-meaningful unit that element is for,
	// and it gives a screen-reader user a landmark to jump straight to when
	// a post strings several cards together.
	return '<!--necro-card-->'
		. '<article class="necro-card necro-card--' . esc_attr($size) . '" aria-label="'
				. esc_attr(sprintf(__('%s, Necromunda fighter card', 'spudworks-mag'), $name)) . '">'
			. $headerHtml
			. '<div class="necro-card__stats">' . $statsHtml . '</div>'
			. $weaponsHtml
			. $metaHtml
		. '</article>'
		. '<!--/necro-card-->';
}
add_shortcode('necro-card', 'spudmagNecroCardShortcode');

/**
 * Group consecutive rendered [necro-card]s into a centered, wrapping row so a
 * writer gets a grid just by placing cards back to back — no wrapper shortcode
 * needed. Runs after do_shortcode (11); the sentinel comments each card emits
 * let this find a run's true start/end regardless of the tables and rows
 * nested inside, sidestepping having to count nested <div>s.
 */
function spudmagRenderNecroCardRows($content) {
	if (strpos($content, '<!--necro-card-->') === false) {
		return $content;
	}

	return preg_replace_callback(
		'/(?:\s*<!--necro-card-->.*?<!--\/necro-card-->\s*)+/s',
		function ($m) {
			$cards = str_replace(array('<!--necro-card-->', '<!--/necro-card-->'), '', $m[0]);
			return '<div class="necro-card-row">' . trim($cards) . '</div>';
		},
		$content
	);
}
add_filter('the_content', 'spudmagRenderNecroCardRows', 100);

/**
 * [archive-feature] — a large graphical link box for hand-built archive
 * Pages, reusing the theme's existing .card/.card--under visual system (the
 * same one the homepage grid and sidebar widgets use) rather than a new
 * component.
 *
 *   [archive-feature link-id="123" link-url="https://…" link-text="…"
 *       img-url="https://…" img-id="456"]Optional intro text[/archive-feature]
 *       Every attribute is optional. With link-id, the box's url/text/image/
 *       date all default from that post — any of the other attributes
 *       override just that one piece. link-id must point at a *published*
 *       post or the whole box renders nothing (same hard gate as
 *       [multipart]'s prev-id/next-id, so a run of these can be pre-built
 *       ahead of a schedule and light up on their own as each piece
 *       publishes). Without link-id there's no post to pull a date from, so
 *       the date line is simply omitted. Image source priority: img-url (raw,
 *       unprocessed) > img-id (a real attachment, proper responsive sizing)
 *       > link-id's own featured image > a hazard-panel placeholder.
 */
function spudmagArchiveFeatureShortcode($atts, $content = null) {
	$a = shortcode_atts(array(
		'link-id'   => '',
		'link-url'  => '',
		'link-text' => '',
		'img-id'    => '',
		'img-url'   => ''
	), $atts, 'archive-feature');

	$linkId = (int) $a['link-id'];
	$postResolved = false;

	if ($linkId > 0) {
		// An id wins over a url — and must be published, or the whole box is
		// suppressed rather than degrading to the manual attributes. Same
		// hard gate as spudmagMultipartLink(), for the same reason: it's what
		// lets a run of these be pre-built ahead of a publish schedule.
		if (get_post_status($linkId) !== 'publish') {
			return '';
		}
		$permalink = get_permalink($linkId);
		if (!$permalink) {
			return '';
		}
		$postResolved = true;
	}

	$url = trim($a['link-url']);
	if ($url === '' && $postResolved) {
		$url = $permalink;
	}

	$text = trim($a['link-text']);
	if ($text === '' && $postResolved) {
		$text = get_the_title($linkId);
	}

	// Nothing to link, or nothing to call it -> render nothing.
	if ($url === '' || $text === '') {
		return '';
	}

	// Image: img-url (raw, unprocessed) > img-id (a real attachment) >
	// link-id's own featured image > no image at all.
	$imgUrl = trim($a['img-url']);
	$imgId  = (int) $a['img-id'];
	$mediaInner = '';
	$hasImage = false;

	if ($imgUrl !== '') {
		$mediaInner = '<img src="' . esc_url($imgUrl) . '" alt="" loading="lazy">';
		$hasImage = true;
	} elseif ($imgId > 0) {
		$mediaInner = wp_get_attachment_image($imgId, 'spudmagLandscape', false, array('loading' => 'lazy', 'alt' => ''));
		$hasImage = $mediaInner !== '';
	} elseif ($postResolved && has_post_thumbnail($linkId)) {
		$mediaInner = get_the_post_thumbnail($linkId, 'spudmagLandscape', array('loading' => 'lazy', 'alt' => ''));
		$hasImage = true;
	}

	$mediaClass = $hasImage ? 'card__media' : 'card__media card__media--hazard';
	$mediaHtml = '<div class="' . esc_attr($mediaClass) . '" aria-hidden="true">' . $mediaInner . '</div>';

	// Date: only when there's an actual post behind the box to pull one from.
	$dateHtml = '';
	if ($postResolved) {
		$dateHtml = '<p class="card-byline"><time datetime="' . esc_attr(get_the_date('c', $linkId)) . '">'
			. esc_html(get_the_date('', $linkId)) . '</time></p>';
	}

	$intro = is_string($content) ? trim($content) : '';
	$introHtml = '';
	if ($intro !== '') {
		// A div, not a <p> — wpautop() already wraps the intro in its own <p>
		// tag(s), same reason multipart's intro/featured-comment's preface
		// both use a div wrapper rather than double up on paragraph tags.
		$introHtml = '<div class="card-subhead">' . wpautop(wp_kses_post(do_shortcode($intro))) . '</div>';
	}

	return '<article class="card card--under archive-feature-card" aria-label="' . esc_attr($text) . '">'
		. $mediaHtml
		. '<div class="card__body">'
			. '<div class="card-meta">'
				. '<h2 class="card-title"><a class="stretched-link" href="' . esc_url($url) . '">' . esc_html($text) . '</a></h2>'
				. $dateHtml
				. $introHtml
			. '</div>'
		. '</div>'
	. '</article>';
}
add_shortcode('archive-feature', 'spudmagArchiveFeatureShortcode');
