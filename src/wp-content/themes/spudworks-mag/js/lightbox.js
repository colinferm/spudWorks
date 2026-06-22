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

	// Friendly labels for each lightbox region (the [data-lightbox] value).
	var SOURCE_LABELS = { content: 'content_well', sidebar: 'sidebar_widget' };

	// Fire a GA4 event if analytics is present; a blocked/absent gtag is a no-op
	// so the lightbox keeps working regardless.
	function track(name, params) {
		if (typeof window.gtag === 'function') {
			window.gtag('event', name, params || {});
		}
	}

	// Running count of every image shown in the lightbox this browsing session.
	function bumpSessionViews() {
		var count = 1;
		try {
			count = (parseInt(window.sessionStorage.getItem('spudmag_lb_views'), 10) || 0) + 1;
			window.sessionStorage.setItem('spudmag_lb_views', count);
		} catch (e) {
			// sessionStorage can throw in private mode; fall back to a soft count.
		}
		return count;
	}

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

	function captionFor(img) {
		// The lightbox caption is the image's real WordPress Caption (the
		// figcaption / .wp-caption-text) - never the alt text, which is
		// auto-filled for accessibility and would otherwise show on every image.
		var fig = img.closest('figure, .wp-caption');
		if (fig) {
			var cap = fig.querySelector('figcaption, .wp-caption-text');
			if (cap) {
				return cap.textContent.trim();
			}
		}
		return '';
	}

	function originalSrcFor(url) {
		// WordPress derivatives are named "name-WxH.ext"; drop that suffix to get
		// the uncropped original (e.g. a square 700x700 gallery crop -> the full
		// 1500x1125 upload). If the original is missing we fall back at render
		// time, so this is safe to attempt.
		return url.replace(/-\d+x\d+(\.[a-z0-9]+)(\?.*)?$/i, '$1$2');
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
		var groupSources = []; // friendly source label per group, parallel to groups
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
			var regionName = region.getAttribute('data-lightbox') || '';
			groupSources[groupIndex] = SOURCE_LABELS[regionName] || regionName || 'content_well';
			var sources = imgs.map(function (img, i) {
				img.classList.add('lightbox-trigger');
				img.dataset.lbGroup = groupIndex;
				img.dataset.lbIndex = i;
				var link = img.closest('a');
				if (link && IMAGE_HREF.test(link.getAttribute('href') || '')) {
					// Intercept the media-file link so it opens in the lightbox.
					link.classList.add('lightbox-linked');
				}
				var best = fullSrcFor(img);
				return {
					src: originalSrcFor(best),
					fallback: best,
					alt: img.getAttribute('alt') || '',
					caption: captionFor(img)
				};
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
		var current = { group: 0, index: 0, imagesViewed: 0 };

		function currentSource() {
			return groupSources[current.group];
		}

		function currentItem() {
			return groups[current.group][current.index];
		}

		// Count an image being shown: bump the per-open and per-session tallies,
		// then emit the per-view event. Returns the running session count.
		function recordView() {
			current.imagesViewed += 1;
			var sessionCount = bumpSessionViews();
			var item = currentItem();
			track('lightbox_image_view', {
				image_source: currentSource(),
				image_url: item.src,
				session_image_count: sessionCount
			});
			return sessionCount;
		}

		function render() {
			var item = groups[current.group][current.index];
			// If the derived original is missing, fall back to the known-good size.
			imgEl.onerror = function () {
				imgEl.onerror = null;
				if (item.fallback && imgEl.getAttribute('src') !== item.fallback) {
					imgEl.src = item.fallback;
				}
			};
			imgEl.src = item.src;
			imgEl.alt = item.alt;
			// Show the caption only when the image has a real WordPress Caption.
			captionEl.textContent = item.caption;
			captionEl.style.display = item.caption ? '' : 'none';
			var multiple = groups[current.group].length > 1;
			prevBtn.hidden = !multiple;
			nextBtn.hidden = !multiple;
		}

		function open(group, index) {
			current.group = group;
			current.index = index;
			current.imagesViewed = 0;
			render();
			overlay.classList.add('is-open');
			overlay.setAttribute('aria-hidden', 'false');
			document.body.classList.add('lightbox-open');
			var item = currentItem();
			track('lightbox_open', {
				image_source: currentSource(),
				image_url: item.src,
				image_position: current.index + 1,
				gallery_size: groups[current.group].length,
				session_image_count: recordView()
			});
		}

		function close(method) {
			overlay.classList.remove('is-open');
			overlay.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('lightbox-open');
			imgEl.src = '';
			track('lightbox_close', {
				method: method || 'button',
				image_source: currentSource(),
				images_viewed: current.imagesViewed
			});
		}

		function step(delta, method) {
			var group = groups[current.group];
			current.index = (current.index + delta + group.length) % group.length;
			render();
			track('lightbox_navigate', {
				direction: delta > 0 ? 'next' : 'prev',
				method: method || 'button',
				image_source: currentSource()
			});
			recordView();
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
				close('backdrop');
			}
		});
		overlay.querySelector('.lightbox__close').addEventListener('click', function () { close('button'); });
		prevBtn.addEventListener('click', function () { step(-1, 'button'); });
		nextBtn.addEventListener('click', function () { step(1, 'button'); });

		document.addEventListener('keydown', function (event) {
			if (!overlay.classList.contains('is-open')) {
				return;
			}
			if (event.key === 'Escape') {
				close('keyboard');
			} else if (event.key === 'ArrowLeft') {
				step(-1, 'keyboard');
			} else if (event.key === 'ArrowRight') {
				step(1, 'keyboard');
			}
		});

		// Touch swipe (mobile): a mostly-horizontal drag steps through the group
		// (swipe left advances, right goes back); a clear downward drag dismisses
		// the lightbox, the way an iOS sheet does. The dismiss threshold is larger
		// so an incidental vertical wobble while paging doesn't close the viewer.
		var SWIPE_THRESHOLD = 40;
		var DISMISS_THRESHOLD = 80;
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
				step(dx < 0 ? 1 : -1, 'swipe');
			} else if (dy > DISMISS_THRESHOLD && dy > Math.abs(dx)) {
				close('swipe_down');
			}
		}, { passive: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initLightbox);
	} else {
		initLightbox();
	}
})();
