/**
 * spudWorks Magazine - image lightbox (vanilla JS, no dependencies).
 *
 * Any image inside a [data-lightbox] region (the article content) becomes
 * clickable. Clicking opens it at the largest size the viewport allows over a
 * dimmed backdrop. Hovering the open image reveals subtle prev/next buttons
 * that cycle through the images within that same content section; the arrow
 * keys do the same, and Escape closes.
 */
(function () {
	'use strict';

	var IMAGE_HREF = /\.(jpe?g|png|gif|webp|avif|bmp|svg)(\?.*)?$/i;

	function largestSrcsetUrl(img) {
		// Pick the widest candidate declared in srcset. Galleries link to the
		// attachment page (not a file), so without this the lightbox would fall
		// back to img.currentSrc - the small thumbnail the browser chose for the
		// grid - instead of the full-size image.
		var srcset = img.getAttribute('srcset') || '';
		var best = '';
		var bestWidth = 0;
		srcset.split(',').forEach(function (candidate) {
			var parts = candidate.trim().split(/\s+/);
			if (!parts[0]) {
				return;
			}
			var width = (parts[1] && /^\d+w$/.test(parts[1])) ? parseInt(parts[1], 10) : 0;
			if (width > bestWidth) {
				bestWidth = width;
				best = parts[0];
			}
		});
		return bestWidth > 0 ? best : '';
	}

	function fullSrcFor(img) {
		// Prefer an explicit link to a media file, then a data-full hint, then the
		// largest source in the srcset, and finally whatever the browser is
		// currently showing.
		var link = img.closest('a');
		if (link && IMAGE_HREF.test(link.getAttribute('href') || '')) {
			return link.getAttribute('href');
		}
		if (img.dataset && img.dataset.full) {
			return img.dataset.full;
		}
		var largest = largestSrcsetUrl(img);
		if (largest) {
			return largest;
		}
		return img.currentSrc || img.src;
	}

	function buildOverlay() {
		var overlay = document.createElement('div');
		overlay.className = 'lightbox';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-hidden', 'true');
		overlay.innerHTML =
			'<div class="lightbox__backdrop" data-close="1"></div>' +
			'<button class="lightbox__close" type="button" aria-label="Close">&times;</button>' +
			'<button class="lightbox__nav lightbox__prev" type="button" aria-label="Previous image">&#8249;</button>' +
			'<figure class="lightbox__stage" data-close="1">' +
				'<img class="lightbox__img" alt="">' +
				'<figcaption class="lightbox__caption"></figcaption>' +
			'</figure>' +
			'<button class="lightbox__nav lightbox__next" type="button" aria-label="Next image">&#8250;</button>';
		document.body.appendChild(overlay);
		return overlay;
	}

	function initLightbox() {
		var regions = document.querySelectorAll('[data-lightbox]');
		if (!regions.length) {
			return;
		}

		// Build the group of images for each region; tag each with its group.
		var groups = [];
		regions.forEach(function (region) {
			var imgs = Array.prototype.slice.call(region.querySelectorAll('img'));
			imgs = imgs.filter(function (img) {
				// Skip decorative / tiny images (e.g. inline icons).
				return img.naturalWidth === 0 || img.naturalWidth >= 80;
			});
			if (!imgs.length) {
				return;
			}
			var groupIndex = groups.length;
			var sources = imgs.map(function (img, i) {
				img.classList.add('lightbox-trigger');
				img.dataset.lbGroup = groupIndex;
				img.dataset.lbIndex = i;
				var link = img.closest('a');
				if (link && IMAGE_HREF.test(link.getAttribute('href') || '')) {
					// Intercept the media-file link so it opens in the lightbox.
					link.classList.add('lightbox-linked');
				}
				return { src: fullSrcFor(img), alt: img.getAttribute('alt') || '' };
			});
			groups.push(sources);
		});

		if (!groups.length) {
			return;
		}

		var overlay = buildOverlay();
		var imgEl = overlay.querySelector('.lightbox__img');
		var captionEl = overlay.querySelector('.lightbox__caption');
		var prevBtn = overlay.querySelector('.lightbox__prev');
		var nextBtn = overlay.querySelector('.lightbox__next');
		var current = { group: 0, index: 0 };

		function render() {
			var item = groups[current.group][current.index];
			imgEl.src = item.src;
			imgEl.alt = item.alt;
			captionEl.textContent = item.alt;
			captionEl.style.display = item.alt ? '' : 'none';
			var multiple = groups[current.group].length > 1;
			prevBtn.hidden = !multiple;
			nextBtn.hidden = !multiple;
		}

		function open(group, index) {
			current.group = group;
			current.index = index;
			render();
			overlay.classList.add('is-open');
			overlay.setAttribute('aria-hidden', 'false');
			document.body.classList.add('lightbox-open');
		}

		function close() {
			overlay.classList.remove('is-open');
			overlay.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('lightbox-open');
			imgEl.src = '';
		}

		function step(delta) {
			var group = groups[current.group];
			current.index = (current.index + delta + group.length) % group.length;
			render();
		}

		// Open on click of any trigger image (or its media-file link).
		document.addEventListener('click', function (event) {
			var img = event.target.closest('.lightbox-trigger');
			if (!img) {
				return;
			}
			event.preventDefault();
			open(parseInt(img.dataset.lbGroup, 10), parseInt(img.dataset.lbIndex, 10));
		});

		overlay.addEventListener('click', function (event) {
			if (event.target.dataset.close) {
				close();
			}
		});
		overlay.querySelector('.lightbox__close').addEventListener('click', close);
		prevBtn.addEventListener('click', function () { step(-1); });
		nextBtn.addEventListener('click', function () { step(1); });

		document.addEventListener('keydown', function (event) {
			if (!overlay.classList.contains('is-open')) {
				return;
			}
			if (event.key === 'Escape') {
				close();
			} else if (event.key === 'ArrowLeft') {
				step(-1);
			} else if (event.key === 'ArrowRight') {
				step(1);
			}
		});

		// Touch swipe (mobile): a mostly-horizontal drag past the threshold steps
		// through the group. Swipe left advances, swipe right goes back.
		var SWIPE_THRESHOLD = 40;
		var touchStartX = 0;
		var touchStartY = 0;

		overlay.addEventListener('touchstart', function (event) {
			var touch = event.changedTouches[0];
			touchStartX = touch.clientX;
			touchStartY = touch.clientY;
		}, { passive: true });

		overlay.addEventListener('touchend', function (event) {
			var touch = event.changedTouches[0];
			var dx = touch.clientX - touchStartX;
			var dy = touch.clientY - touchStartY;
			if (Math.abs(dx) > SWIPE_THRESHOLD && Math.abs(dx) > Math.abs(dy)) {
				step(dx < 0 ? 1 : -1);
			}
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initLightbox);
	} else {
		initLightbox();
	}
})();
