/**
 * spudWorks Magazine - comment form honeypot timer.
 *
 * Stamps the hidden #spudmag_rendered field with the moment the form rendered
 * for the reader. The server (see lib/comment-security.php) treats a submission
 * that arrives too soon after this stamp - or with no stamp at all - as a bot.
 */
(function () {
	'use strict';

	function stampRenderTime() {
		var field = document.getElementById('spudmag_rendered');
		if (field) {
			field.value = new Date().toISOString();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', stampRenderTime);
	} else {
		stampRenderTime();
	}
})();
