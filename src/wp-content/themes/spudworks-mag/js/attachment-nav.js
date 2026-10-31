/**
 * spudWorks Magazine - attachment page navigation (vanilla JS, no dependencies).
 *
 * The left/right arrow keys and horizontal swipes follow the page's Prev/Next
 * links, so stepping through a post's images feels like the lightbox even
 * though each image is its own page. Both stand down while the lightbox is
 * open, which has its own arrow-key and swipe handling.
 */
(function () {
	'use strict';

	var SWIPE_THRESHOLD = 50;

	function initAttachmentNav() {
		var prevLink = document.querySelector('.attachment-nav__prev');
		var nextLink = document.querySelector('.attachment-nav__next');
		if (!prevLink && !nextLink) {
			return;
		}

		function go(link) {
			if (link && !document.body.classList.contains('lightbox-open')) {
				window.location.href = link.href;
			}
		}

		document.addEventListener('keydown', function (event) {
			if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
				return;
			}
			// Leave the arrows alone while typing (e.g. the header search box).
			if (event.target.closest('input, textarea, select, [contenteditable]')) {
				return;
			}
			if (event.key === 'ArrowLeft') {
				go(prevLink);
			} else if (event.key === 'ArrowRight') {
				go(nextLink);
			}
		});

		// A mostly-horizontal swipe: left advances, right goes back (same as
		// the lightbox).
		var touchStartX = 0;
		var touchStartY = 0;

		document.addEventListener('touchstart', function (event) {
			var touch = event.changedTouches[0];
			touchStartX = touch.clientX;
			touchStartY = touch.clientY;
		}, { passive: true });

		document.addEventListener('touchend', function (event) {
			var touch = event.changedTouches[0];
			var dx = touch.clientX - touchStartX;
			var dy = touch.clientY - touchStartY;
			if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy) * 1.5) {
				go(dx < 0 ? nextLink : prevLink);
			}
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAttachmentNav);
	} else {
		initAttachmentNav();
	}
})();
