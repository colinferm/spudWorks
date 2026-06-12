/**
 * spudWorks Magazine — front-end behaviour (vanilla JS, no dependencies).
 * Currently: the floating-nav mobile toggle.
 */
(function () {
	'use strict';

	function initNavToggle() {
		var toggle = document.querySelector('.nav-toggle');
		var nav = document.getElementById('primary-nav');
		if (!toggle || !nav) {
			return;
		}

		toggle.addEventListener('click', function () {
			var isOpen = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		});

		// Close the panel when a link is chosen (small-screen menu).
		nav.addEventListener('click', function (event) {
			if (event.target.closest('a')) {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initNavToggle);
	} else {
		initNavToggle();
	}
})();
