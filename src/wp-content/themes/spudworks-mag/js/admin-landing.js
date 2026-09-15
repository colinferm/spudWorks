/**
 * spudWorks Magazine - Landing Page sidebar builder (page editor only).
 *
 * Vanilla, dependency-free except for wp.media (this theme's first use of the
 * media modal — deliberately scoped to the page editor so it never loads on a
 * post edit screen). Two independent jobs sharing one drag-reorder mechanism:
 *
 *   1. Gallery pickers: a plain wp.media multi-select frame per gallery. The
 *      frame itself doesn't support in-modal reordering, so order is instead
 *      set afterward by dragging the thumbnail strip below the picker button
 *      — the same native HTML5 DnD idiom as js/admin-todos.js.
 *   2. Related-articles list: a debounced title search against the existing
 *      spudmag_featured_search endpoint (shared with the Featured Post
 *      widget's admin-widget-featured.js), appending picks to a
 *      drag-reorderable list instead of replacing a single selection.
 *
 * Both lists are plain form fields (hidden inputs riding along inside each
 * draggable <li>) — reordering just moves DOM nodes; there's nothing to POST
 * until the page itself is saved.
 */
(function () {
	'use strict';

	if (typeof spudmagLanding === 'undefined') {
		return;
	}

	var DEBOUNCE_MS = 250;
	var timers = new WeakMap();

	function escAttr(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	// --- Template select: show/hide the gallery + related fields ---------------

	document.addEventListener('change', function (event) {
		if (event.target.id !== 'spudmag-page-template') {
			return;
		}
		var fields = document.querySelector('.spudmag-landing-fields');
		if (fields) {
			fields.classList.toggle('is-hidden', event.target.value !== 'landing');
		}
	});

	// --- Gallery pickers ---------------------------------------------------

	var galleryFrames = {};

	function galleryListOf(index) {
		return document.querySelector('.spudmag-gallery-list[data-gallery-index="' + index + '"]');
	}

	function currentGalleryIds(list) {
		return Array.prototype.map.call(list.querySelectorAll('.spudmag-gallery-item'), function (item) {
			return parseInt(item.dataset.id, 10);
		});
	}

	function galleryItemHtml(index, attachment) {
		var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.icon;
		return '<li class="spudmag-gallery-item" draggable="true" data-id="' + attachment.id + '">' +
			'<img src="' + escAttr(url) + '" alt="">' +
			'<input type="hidden" name="spudmag_gallery[' + index + '][ids][]" value="' + attachment.id + '">' +
			'<button type="button" class="spudmag-gallery-remove" aria-label="Remove image">&times;</button>' +
			'</li>';
	}

	function openGalleryPicker(button) {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var index = button.dataset.galleryIndex;
		var list = galleryListOf(index);
		if (!list) {
			return;
		}

		if (!galleryFrames[index]) {
			galleryFrames[index] = wp.media({
				title: spudmagLanding.galleryTitle,
				button: { text: spudmagLanding.galleryButton },
				multiple: true,
				library: { type: 'image' }
			});

			// Pre-select whatever is already in the strip so re-opening the
			// picker to add more images doesn't drop the existing ones.
			galleryFrames[index].on('open', function () {
				var selection = galleryFrames[index].state().get('selection');
				currentGalleryIds(list).forEach(function (id) {
					var attachment = wp.media.attachment(id);
					attachment.fetch();
					selection.add(attachment);
				});
			});

			galleryFrames[index].on('select', function () {
				var selection = galleryFrames[index].state().get('selection').toJSON();
				list.innerHTML = selection.map(function (attachment) {
					return galleryItemHtml(index, attachment);
				}).join('');
			});
		}

		galleryFrames[index].open();
	}

	// --- Related-articles search -------------------------------------------

	function relatedFieldOf(el) {
		return el.closest('.spudmag-related-field');
	}

	function renderRelatedResults(list, items) {
		if (!items.length) {
			list.innerHTML = '';
			list.hidden = true;
			return;
		}
		list.innerHTML = items.map(function (item) {
			return '<li><button type="button" class="button-link spudmag-related-pick" ' +
				'data-id="' + item.id + '" data-title="' + escAttr(item.title) + '">' +
				escAttr(item.title) + '</button></li>';
		}).join('');
		list.hidden = false;
	}

	function searchRelated(input) {
		var field = relatedFieldOf(input);
		if (!field) {
			return;
		}
		var list = field.querySelector('.spudmag-related-results');
		var query = input.value.trim();

		if (query === '') {
			renderRelatedResults(list, []);
			return;
		}

		var url = spudmagLanding.ajaxUrl +
			'?action=spudmag_featured_search' +
			'&nonce=' + encodeURIComponent(spudmagLanding.nonce) +
			'&q=' + encodeURIComponent(query);

		fetch(url, { credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (payload) {
				if (payload && payload.success) {
					renderRelatedResults(list, payload.data || []);
				}
			})
			.catch(function () { /* network error: leave results as-is */ });
	}

	function relatedItemHtml(id, title) {
		return '<li draggable="true" data-id="' + id + '">' +
			'<span class="spudmag-related-handle" aria-hidden="true">&#8942;&#8942;</span>' +
			'<span class="spudmag-related-title-text">' + escAttr(title) + '</span>' +
			'<input type="hidden" name="spudmag_related[]" value="' + id + '">' +
			'<button type="button" class="spudmag-related-remove" aria-label="Remove">&times;</button>' +
			'</li>';
	}

	function addRelated(button) {
		var field = relatedFieldOf(button);
		if (!field) {
			return;
		}
		var list = field.querySelector('.spudmag-related-list');
		var id = button.dataset.id;

		if (list.querySelector('li[data-id="' + id + '"]')) {
			return; // already added
		}
		list.insertAdjacentHTML('beforeend', relatedItemHtml(id, button.dataset.title));

		var searchInput = field.querySelector('.spudmag-related-search');
		if (searchInput) {
			searchInput.value = '';
		}
		renderRelatedResults(field.querySelector('.spudmag-related-results'), []);
	}

	// --- Delegated events ----------------------------------------------------

	document.addEventListener('click', function (event) {
		var galleryEdit = event.target.closest('.spudmag-gallery-edit');
		if (galleryEdit) {
			event.preventDefault();
			openGalleryPicker(galleryEdit);
			return;
		}

		var galleryRemove = event.target.closest('.spudmag-gallery-remove');
		if (galleryRemove) {
			var galleryItem = galleryRemove.closest('.spudmag-gallery-item');
			if (galleryItem) {
				galleryItem.remove();
			}
			return;
		}

		var relatedPick = event.target.closest('.spudmag-related-pick');
		if (relatedPick) {
			event.preventDefault();
			addRelated(relatedPick);
			return;
		}

		var relatedRemove = event.target.closest('.spudmag-related-remove');
		if (relatedRemove) {
			var relatedItem = relatedRemove.closest('li');
			if (relatedItem) {
				relatedItem.remove();
			}
		}
	});

	document.addEventListener('input', function (event) {
		var input = event.target;
		if (!input.classList || !input.classList.contains('spudmag-related-search')) {
			return;
		}
		clearTimeout(timers.get(input));
		timers.set(input, setTimeout(function () { searchRelated(input); }, DEBOUNCE_MS));
	});

	// --- Shared drag-reorder: any draggable <li> whose parent opts in via
	// data-reorder="1" (both the gallery thumbnail strips and the related
	// list). Same midpoint-insertBefore approach as admin-todos.js.

	var dragEl = null;

	document.addEventListener('dragstart', function (event) {
		var item = event.target.closest('[draggable="true"]');
		if (!item || !item.parentNode || item.parentNode.dataset.reorder !== '1') {
			return;
		}
		dragEl = item;
		event.dataTransfer.effectAllowed = 'move';
		item.classList.add('is-dragging');
	});

	document.addEventListener('dragover', function (event) {
		if (!dragEl) {
			return;
		}
		var list = dragEl.parentNode;
		var over = event.target.closest('[draggable="true"]');
		if (!over || over === dragEl || over.parentNode !== list) {
			return;
		}
		event.preventDefault();
		var rect = over.getBoundingClientRect();
		var after = (event.clientY - rect.top) > (rect.height / 2);
		list.insertBefore(dragEl, after ? over.nextSibling : over);
	});

	document.addEventListener('drop', function (event) {
		if (dragEl) {
			event.preventDefault();
		}
	});

	document.addEventListener('dragend', function () {
		if (dragEl) {
			dragEl.classList.remove('is-dragging');
		}
		dragEl = null;
	});
})();
