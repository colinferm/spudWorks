/**
 * spudWorks Magazine - per-post To-Do lists (admin UI).
 *
 * Vanilla, dependency-free. Event-delegated on the document so it covers the
 * editor meta box and the Posts > To Do page (many lists at once). Every
 * mutation POSTs to the one spudmag_todo endpoint and gets back the fresh
 * {items, counts}; the "(X/Y)" counters are updated from that. Rows are
 * drag-reorderable wherever the wrap opts in (data-reorder="1").
 *
 * The row markup here mirrors spudmagTodoRenderRow() in admin/todos.php; keep
 * the two in step.
 */
(function () {
	'use strict';

	if (typeof spudmagTodos === 'undefined') {
		return;
	}

	var DEBOUNCE_MS = 400;
	var timers = new WeakMap();
	var dragEl = null;

	function wrapOf(el) {
		return el.closest('.spudmag-todos-wrap');
	}

	function escAttr(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	// Tag parsing — mirrors spudmagTodoParseTags()/PriorityRank() in todos.php.
	function priorityRank(word) {
		var ranks = { high: 3, med: 2, medium: 2, low: 1 };
		return ranks[String(word).toLowerCase()] || 0;
	}

	function parseTags(text) {
		var tags = [];
		var re = /#([\p{L}\p{N}_-]+)/gu;
		var match;
		while ((match = re.exec(text)) !== null) {
			tags.push(match[1]);
		}
		var topRank = 0;
		var priority = '';
		tags.forEach(function (tag) {
			var rank = priorityRank(tag);
			if (rank > topRank) {
				topRank = rank;
				priority = rank === 2 ? 'med' : tag.toLowerCase();
			}
		});
		var display = text.replace(/#[\p{L}\p{N}_-]+/gu, '').replace(/\s+/gu, ' ').trim();
		return { display: display, tags: tags, priority: priority };
	}

	// The locked-row body: tag-stripped sentence + a pill per tag. Mirrors
	// spudmagTodoRenderDisplay() in todos.php.
	function displayHtml(parsed) {
		var html = '<span class="spudmag-todo-sentence">' + escAttr(parsed.display) + '</span>';
		if (parsed.tags.length) {
			html += '<span class="spudmag-todo-pills">';
			parsed.tags.forEach(function (tag) {
				var rank = priorityRank(tag);
				var cls = 'spudmag-todo-pill';
				if (rank > 0) {
					cls += ' is-prio-' + (rank === 2 ? 'med' : tag.toLowerCase());
				}
				html += '<span class="' + cls + '">#' + escAttr(tag) + '</span>';
			});
			html += '</span>';
		}
		return html;
	}

	// Rebuild a row's visible display + priority class from its raw input text.
	function renderRowContent(row) {
		var input = row.querySelector('.spudmag-todo-text');
		var display = row.querySelector('.spudmag-todo-display');
		if (!input || !display) {
			return;
		}
		var parsed = parseTags(input.value);
		display.innerHTML = displayHtml(parsed);
		row.classList.remove('is-prio-low', 'is-prio-med', 'is-prio-high');
		if (parsed.priority) {
			row.classList.add('is-prio-' + parsed.priority);
		}
	}

	function rowHtml(item, reorder) {
		var parsed = parseTags(item.text);
		var cls = 'spudmag-todo' + (item.done ? ' is-done' : '');
		if (parsed.priority) {
			cls += ' is-prio-' + parsed.priority;
		}
		// Saved rows render locked (read-only, bold); double-click unlocks one.
		// The raw text (with #tags) stays in the hidden input; .display shows the
		// stripped sentence + pills.
		return '<li class="' + cls + '" data-id="' + escAttr(item.id) + '"' +
			(reorder ? ' draggable="true"' : '') + '>' +
			(reorder ? '<span class="spudmag-todo-handle" aria-hidden="true">⋮⋮</span>' : '') +
			'<input type="checkbox" class="spudmag-todo-check"' + (item.done ? ' checked' : '') + '>' +
			'<input type="text" class="spudmag-todo-text is-locked" value="' + escAttr(item.text) + '" placeholder="To do…" readonly>' +
			'<div class="spudmag-todo-display">' + displayHtml(parsed) + '</div>' +
			'<button type="button" class="spudmag-todo-remove" aria-label="Remove item">×</button>' +
			'</li>';
	}

	function lock(input) {
		input.readOnly = true;
		input.classList.add('is-locked');
		var row = input.closest('.spudmag-todo');
		if (row) {
			row.classList.remove('is-editing');
			renderRowContent(row); // refresh sentence/pills/priority from raw text
		}
	}

	function unlock(input) {
		input.readOnly = false;
		input.classList.remove('is-locked');
		var row = input.closest('.spudmag-todo');
		if (row) {
			row.classList.add('is-editing'); // reveal the raw input for editing
		}
	}

	// Append a fresh, empty row and open it for typing. Returns the request
	// promise so callers can chain (e.g. Enter, which adds after saving).
	function addItem(wrap) {
		return request({ op: 'add', post_id: wrap.dataset.postId, text: '' }).then(function (payload) {
			if (!payload || !payload.success) {
				return;
			}
			var items = payload.data.items;
			var newItem = items[items.length - 1];
			var reorder = wrap.dataset.reorder === '1';
			var list = wrap.querySelector('.spudmag-todos');
			// Append only the new row so any row being edited keeps its state.
			list.insertAdjacentHTML('beforeend', rowHtml(newItem, reorder));
			var fresh = list.lastElementChild.querySelector('.spudmag-todo-text');
			unlock(fresh);
			fresh.focus();
			updateCounts(wrap.dataset.postId, payload.data.counts);
		});
	}

	// Drop a row and its (empty) item — used when a new/edited row is left blank.
	function discardRow(wrap, row) {
		row.remove();
		request({ op: 'remove', post_id: wrap.dataset.postId, id: row.dataset.id }).then(function (payload) {
			if (payload && payload.success) {
				updateCounts(wrap.dataset.postId, payload.data.counts);
			}
		});
	}

	function updateCounts(postId, counts) {
		var label = '(' + counts.done + '/' + counts.total + ')';
		var nodes = document.querySelectorAll('[data-count-for="' + postId + '"]');
		Array.prototype.forEach.call(nodes, function (node) {
			node.textContent = label;
		});
	}

	function request(params) {
		var body = new URLSearchParams();
		body.set('action', 'spudmag_todo');
		body.set('nonce', spudmagTodos.nonce);
		Object.keys(params).forEach(function (key) {
			var value = params[key];
			if (Array.isArray(value)) {
				value.forEach(function (entry) { body.append(key + '[]', entry); });
			} else {
				body.append(key, value);
			}
		});
		return fetch(spudmagTodos.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (res) { return res.json(); });
	}

	// Add / remove (event-delegated clicks).
	document.addEventListener('click', function (event) {
		var addBtn = event.target.closest('.spudmag-todo-add');
		if (addBtn) {
			var addWrap = wrapOf(addBtn);
			if (addWrap) {
				addItem(addWrap);
			}
			return;
		}

		var removeBtn = event.target.closest('.spudmag-todo-remove');
		if (removeBtn) {
			var removeWrap = wrapOf(removeBtn);
			var removeRow = removeBtn.closest('.spudmag-todo');
			if (!removeWrap || !removeRow) {
				return;
			}
			request({ op: 'remove', post_id: removeWrap.dataset.postId, id: removeRow.dataset.id }).then(function (payload) {
				if (payload && payload.success) {
					removeRow.remove();
					updateCounts(removeWrap.dataset.postId, payload.data.counts);
				}
			});
		}
	});

	// Toggle complete.
	document.addEventListener('change', function (event) {
		var check = event.target;
		if (!check.classList || !check.classList.contains('spudmag-todo-check')) {
			return;
		}
		var wrap = wrapOf(check);
		var row = check.closest('.spudmag-todo');
		if (!wrap || !row) {
			return;
		}
		row.classList.toggle('is-done', check.checked);
		request({ op: 'toggle', post_id: wrap.dataset.postId, id: row.dataset.id, done: check.checked ? '1' : '' })
			.then(function (payload) {
				if (payload && payload.success) {
					updateCounts(wrap.dataset.postId, payload.data.counts);
				}
			});
	});

	// Edit text: debounced while typing, flushed on blur.
	function saveText(input) {
		var wrap = wrapOf(input);
		var row = input.closest('.spudmag-todo');
		if (!wrap || !row) {
			return;
		}
		request({ op: 'edit', post_id: wrap.dataset.postId, id: row.dataset.id, text: input.value });
	}

	document.addEventListener('input', function (event) {
		var input = event.target;
		if (!input.classList || !input.classList.contains('spudmag-todo-text')) {
			return;
		}
		clearTimeout(timers.get(input));
		timers.set(input, setTimeout(function () { saveText(input); }, DEBOUNCE_MS));
	});

	// Clicking away commits an editing row: save + lock, or discard if left blank.
	document.addEventListener('blur', function (event) {
		var input = event.target;
		if (!input.classList || !input.classList.contains('spudmag-todo-text')) {
			return;
		}
		if (input.readOnly) {
			return; // already committed (locked) — nothing to do.
		}
		clearTimeout(timers.get(input));
		var wrap = wrapOf(input);
		var row = input.closest('.spudmag-todo');
		if (!wrap || !row) {
			return;
		}
		if (input.value.trim() === '') {
			discardRow(wrap, row);
		} else {
			saveText(input);
			lock(input); // committing an edit returns the row to its read-only state.
		}
	}, true); // capture: blur does not bubble.

	// Enter commits the current row. On the last row it also spawns a fresh one
	// so several to-dos can be typed back-to-back; an empty row is discarded.
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter') {
			return;
		}
		var input = event.target;
		if (!input.classList || !input.classList.contains('spudmag-todo-text')) {
			return;
		}
		event.preventDefault();
		var wrap = wrapOf(input);
		var row = input.closest('.spudmag-todo');
		if (!wrap || !row) {
			return;
		}
		clearTimeout(timers.get(input));

		if (input.value.trim() === '') {
			discardRow(wrap, row);
			return;
		}

		var isLast = (row === row.parentNode.lastElementChild);
		lock(input);
		var saved = request({ op: 'edit', post_id: wrap.dataset.postId, id: row.dataset.id, text: input.value });
		if (isLast) {
			// Chain the add after the save so it can't race the edit server-side.
			saved.then(function () { addItem(wrap); });
		} else {
			input.blur(); // locked already, so blur is a no-op beyond moving focus.
		}
	});

	// Double-click a locked row's display to edit its raw text again. The input
	// is hidden while locked, so target the display and reach its sibling input.
	document.addEventListener('dblclick', function (event) {
		var display = event.target.closest('.spudmag-todo-display');
		if (!display) {
			return;
		}
		var row = display.closest('.spudmag-todo');
		var input = row && row.querySelector('.spudmag-todo-text');
		if (!input) {
			return;
		}
		unlock(input);
		input.focus();
		input.setSelectionRange(input.value.length, input.value.length);
	});

	// Drag-to-reorder (only where rows are draggable).
	document.addEventListener('dragstart', function (event) {
		var row = event.target.closest('.spudmag-todo');
		if (!row || row.getAttribute('draggable') !== 'true') {
			return;
		}
		dragEl = row;
		event.dataTransfer.effectAllowed = 'move';
		row.classList.add('is-dragging');
	});

	document.addEventListener('dragover', function (event) {
		if (!dragEl) {
			return;
		}
		var list = dragEl.parentNode;
		var over = event.target.closest('.spudmag-todo');
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
		if (!dragEl) {
			return;
		}
		var wrap = wrapOf(dragEl);
		dragEl.classList.remove('is-dragging');
		dragEl = null;
		if (!wrap) {
			return;
		}
		var ids = Array.prototype.map.call(wrap.querySelectorAll('.spudmag-todo'), function (row) {
			return row.dataset.id;
		});
		request({ op: 'reorder', post_id: wrap.dataset.postId, ids: ids });
	});
})();
