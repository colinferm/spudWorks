/**
 * spudWorks Magazine - Quicktags buttons for the post Text editor.
 *
 * Adds [multipart] and [author-note] insert buttons. The high priority numbers
 * place them after the default Quicktags set (which ends with "Close Tags").
 */
(function () {
	'use strict';

	if (typeof QTags === 'undefined') {
		return;
	}

	QTags.addButton(
		'spudmag_multipart',
		'multipart',
		'[multipart prev-url="" prev-link="" next-url="" next-link=""]',
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
})();
