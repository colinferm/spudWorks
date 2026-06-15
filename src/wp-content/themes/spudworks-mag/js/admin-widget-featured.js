/**
 * spudWorks Magazine - Featured Post widget admin search.
 *
 * A debounced title typeahead for the widget form. Event-delegated on the
 * document so it works for widgets added dynamically (and re-rendered after a
 * save). Picking a result stores the post ID and marks the widget dirty.
 */
(function () {
	'use strict';

	if (typeof spudmagFeatured === 'undefined') {
		return;
	}

	var DEBOUNCE_MS = 250;
	var timers = new WeakMap();

	function widgetOf(el) {
		return el.closest('.spudmag-featured-widget');
	}

	function renderResults(list, items) {
		if (!items.length) {
			list.innerHTML = '';
			list.hidden = true;
			return;
		}
		list.innerHTML = items.map(function (item) {
			return '<li><button type="button" class="button-link spudmag-featured-pick" ' +
				'data-id="' + item.id + '" data-title="' + item.title.replace(/"/g, '&quot;') + '">' +
				item.title + '</button></li>';
		}).join('');
		list.hidden = false;
	}

	function search(input) {
		var widget = widgetOf(input);
		if (!widget) {
			return;
		}
		var list = widget.querySelector('.spudmag-featured-results');
		var query = input.value.trim();

		if (query === '') {
			renderResults(list, []);
			return;
		}

		var url = spudmagFeatured.ajaxUrl +
			'?action=spudmag_featured_search' +
			'&nonce=' + encodeURIComponent(spudmagFeatured.nonce) +
			'&q=' + encodeURIComponent(query);

		fetch(url, { credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (payload) {
				if (payload && payload.success) {
					renderResults(list, payload.data || []);
				}
			})
			.catch(function () { /* network error: leave results as-is */ });
	}

	function choose(button) {
		var widget = widgetOf(button);
		if (!widget) {
			return;
		}
		var idField = widget.querySelector('.spudmag-featured-id');
		var current = widget.querySelector('.spudmag-featured-current');
		var list = widget.querySelector('.spudmag-featured-results');
		var searchInput = widget.querySelector('.spudmag-featured-search');

		idField.value = button.dataset.id;
		// Mark the widget dirty so WordPress enables Save.
		idField.dispatchEvent(new Event('change', { bubbles: true }));

		if (current) {
			current.innerHTML = 'Selected: <strong>' + button.dataset.title + '</strong>';
		}
		if (searchInput) {
			searchInput.value = '';
		}
		renderResults(list, []);
	}

	// Debounced typing.
	document.addEventListener('input', function (event) {
		var input = event.target;
		if (!input.classList || !input.classList.contains('spudmag-featured-search')) {
			return;
		}
		clearTimeout(timers.get(input));
		timers.set(input, setTimeout(function () { search(input); }, DEBOUNCE_MS));
	});

	// Result selection.
	document.addEventListener('click', function (event) {
		var button = event.target.closest('.spudmag-featured-pick');
		if (button) {
			event.preventDefault();
			choose(button);
		}
	});
})();
