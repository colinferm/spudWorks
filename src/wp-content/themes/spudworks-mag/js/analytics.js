/**
 * spudWorks Magazine - Google Analytics interaction events.
 *
 * Reports non-pageview engagement to GA4 (gtag is loaded in footer.php). The
 * lightbox emits its own events from js/lightbox.js; this file covers the rest
 * via event delegation so it works no matter where the elements appear:
 *
 *   comment_submit        - the comment form is submitted
 *   comment_reply_click   - a "Reply" link on a comment is clicked
 *   search                - a header/footer search box is submitted (GA4's
 *                           standard search event -> Site Search reports)
 *   related_click         - a "Keep Reading" related-post link is clicked
 *
 * A blocked or absent gtag makes track() a no-op, so analytics never interferes
 * with the page behaving normally.
 */
(function () {
	'use strict';

	function track(name, params) {
		if (typeof window.gtag === 'function') {
			window.gtag('event', name, params || {});
		}
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.tagName) {
			return;
		}

		if (form.id === 'commentform') {
			var postField = form.querySelector('#comment_post_ID');
			var parentField = form.querySelector('#comment_parent');
			track('comment_submit', {
				post_id: postField ? postField.value : '',
				is_reply: !!(parentField && parentField.value && parentField.value !== '0')
			});
			return;
		}

		if (form.classList.contains('search-box')) {
			var field = form.querySelector('input[name="s"]');
			track('search', {
				search_term: field ? field.value.trim() : ''
			});
		}
	}, true);

	document.addEventListener('click', function (event) {
		var reply = event.target.closest('.comment-reply-link');
		if (reply) {
			track('comment_reply_click', {
				comment_id: reply.getAttribute('data-commentid') || ''
			});
			return;
		}

		var related = event.target.closest('.related-stories a');
		if (related) {
			track('related_click', {
				link_url: related.href,
				link_text: (related.textContent || '').trim()
			});
		}
	});
})();
