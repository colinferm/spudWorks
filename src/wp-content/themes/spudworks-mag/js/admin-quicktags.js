/**
 * spudWorks Magazine - Quicktags buttons for the post Text editor.
 *
 * Adds insert buttons for all of the theme's editorial shortcodes. The high
 * priority numbers place them after the default Quicktags set (which ends
 * with "Close Tags").
 */
(function () {
	'use strict';

	if (typeof QTags === 'undefined') {
		return;
	}

	QTags.addButton(
		'spudmag_multipart',
		'multipart',
		'[multipart prev-id="" prev-link="" next-id="" next-link=""]',
		'[/multipart]',
		'',
		'Insert a multi-part navigation box',
		200
	);

	QTags.addButton(
		'spudmag_author_note',
		'author-note',
		'[author-note]',
		'[/author-note]',
		'',
		'Insert an author note',
		201
	);

	QTags.addButton(
		'spudmag_featured_comment',
		'featured-comment',
		'[featured-comment id=""]',
		'[/featured-comment]',
		'',
		'Insert a featured comment quote',
		202
	);

	QTags.addButton(
		'spudmag_notice',
		'notice',
		'[notice level="info"]',
		'[/notice]',
		'',
		'Insert a notice box',
		203
	);

	QTags.addButton(
		'spudmag_pullquote',
		'pullquote',
		'[pullquote align="center" attribution=""]',
		'[/pullquote]',
		'',
		'Insert a pullquote',
		204
	);

	QTags.addButton(
		'spudmag_toc',
		'toc',
		'[toc]',
		'[/toc]',
		'',
		'Insert a table of contents',
		205
	);

	// Unlike the buttons above, this one does not pre-fill title="" — the
	// default heading ("An Aside") is what's wanted most of the time, so a bare
	// [aside] needs no attribute deleted before use. Add title="…" (or title=""
	// for no heading) as an opt-in edit.
	QTags.addButton(
		'spudmag_aside',
		'aside',
		'[aside]',
		'[/aside]',
		'',
		'Insert a side note',
		206
	);

	QTags.addButton(
		'spudmag_necro_card',
		'necro-card',
		'[necro-card size="full" name="" role="" value="" stat-m="" stat-ws="" stat-bs="" stat-s="" stat-t="" stat-w="" stat-i="" stat-a="" stat-sv="" stat-ld="" stat-cl="" stat-wil="" stat-int="" stat-xp="" wargear="" skills=""]',
		'[/necro-card]',
		'',
		'Insert a Necromunda fighter card',
		207
	);

	// Self-closing, so there is no end tag to insert — unlike every button
	// above, this one only ever inserts its opening (and only) tag.
	QTags.addButton(
		'spudmag_necro_weapon',
		'necro-weapon',
		'[necro-weapon name="" stat-sr="" stat-lr="" stat-s="" stat-ap="" stat-l="" traits=""/]',
		'',
		'',
		'Insert a necro-card weapon line',
		208
	);

	QTags.addButton(
		'spudmag_archive_feature',
		'archive-feature',
		'[archive-feature link-id="" link-url="" link-text="" img-url="" img-id=""]',
		'[/archive-feature]',
		'',
		'Insert an archive feature box',
		209
	);
})();
