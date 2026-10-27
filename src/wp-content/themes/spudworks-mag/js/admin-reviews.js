/**
 * spudWorks Magazine - repeatable review boxes (post editor meta box).
 *
 * Vanilla, dependency-free. "Add another review" clones the <template> that
 * spudmagRenderReviewFields() (admin/meta-boxes.php) prints; the inputs are
 * array fields, so each clone simply rides along in the normal post save.
 * The first box can't be removed; extras can, and blank extras are dropped
 * server-side on save.
 */
(function () {
	'use strict';

	var list = document.getElementById('spudmag-reviews');
	var template = document.getElementById('spudmag-review-template');
	var addButton = document.getElementById('spudmag-review-add');

	if (!list || !template || !addButton) {
		return;
	}

	function syncRemoveButtons() {
		var boxes = list.querySelectorAll('.spudmag-review');
		boxes.forEach(function (box, index) {
			var wrap = box.querySelector('.spudmag-review-remove-wrap');
			if (wrap) {
				wrap.hidden = index === 0;
			}
		});
	}

	addButton.addEventListener('click', function () {
		list.appendChild(template.content.cloneNode(true));
		syncRemoveButtons();
		var boxes = list.querySelectorAll('.spudmag-review');
		var field = boxes[boxes.length - 1].querySelector('input');
		if (field) {
			field.focus();
		}
	});

	list.addEventListener('click', function (event) {
		var button = event.target.closest('.spudmag-review-remove');
		if (!button) {
			return;
		}
		button.closest('.spudmag-review').remove();
		syncRemoveButtons();
	});

	syncRemoveButtons();
})();
