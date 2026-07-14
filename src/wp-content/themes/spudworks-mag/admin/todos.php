<?php
/**
 * spudWorks Magazine — per-post To-Do lists (core).
 *
 * A production checklist attached to each post, stored as a single serialized
 * array under one meta key (METATODOS). Array order *is* the row order. This
 * file owns the data layer, the reusable list UI, the editor meta box, and the
 * AJAX endpoint; the cross-post admin page and the dashboard widget live in
 * sibling files and build on the helpers here.
 *
 * Every item is an array: id, text, done, added (unix timestamp). Persistence
 * across all three surfaces (meta box, To Do page, dashboard actions) goes
 * through the one wp_ajax_spudmag_todo endpoint, so there is no save_post hook.
 */

/**
 * Coerce one stored/raw item into a well-formed To-Do, or null if unusable.
 */
function spudmagNormalizeTodo($raw) {
	if (!is_array($raw)) {
		return null;
	}
	$id = isset($raw['id']) ? sanitize_key($raw['id']) : '';
	if ($id === '') {
		return null;
	}
	return array(
		'id'    => $id,
		'text'  => isset($raw['text']) ? sanitize_text_field($raw['text']) : '',
		'done'  => !empty($raw['done']),
		'added' => isset($raw['added']) ? (int) $raw['added'] : 0
	);
}

/**
 * The post's To-Do items: a clean, ordered array (empty when unset/corrupt).
 */
function spudmagGetTodos($postId) {
	$stored = get_post_meta($postId, METATODOS, true);
	if (!is_array($stored)) {
		return array();
	}
	$items = array();
	foreach ($stored as $raw) {
		$item = spudmagNormalizeTodo($raw);
		if ($item !== null) {
			$items[] = $item;
		}
	}
	return $items;
}

/**
 * Persist a list, normalizing each item first. An empty list clears the meta
 * key entirely (keeps queries clean and posts off the To Do page/dashboard).
 * Returns the cleaned list that was saved.
 */
function spudmagSaveTodos($postId, $items) {
	$clean = array();
	foreach ($items as $raw) {
		$item = spudmagNormalizeTodo($raw);
		if ($item !== null) {
			$clean[] = $item;
		}
	}
	if (empty($clean)) {
		delete_post_meta($postId, METATODOS);
	} else {
		update_post_meta($postId, METATODOS, $clean);
	}
	return $clean;
}

/**
 * Completed/total counts for the "(X/Y)" headers.
 */
function spudmagTodoCounts($postId) {
	$items = spudmagGetTodos($postId);
	$done = 0;
	foreach ($items as $item) {
		if ($item['done']) {
			$done++;
		}
	}
	return array('done' => $done, 'total' => count($items));
}

/**
 * The "(X/Y)" label for a counts array.
 */
function spudmagTodoCountLabel($counts) {
	return sprintf('(%d/%d)', $counts['done'], $counts['total']);
}

/**
 * Whether a post still has at least one incomplete item.
 */
function spudmagTodoHasWork($postId) {
	$counts = spudmagTodoCounts($postId);
	return ($counts['total'] - $counts['done']) > 0;
}

/* -------------------------------------------------------------------------
 * Tags. Any "#word" typed into an item's text is a tag; it stays embedded in
 * the stored text but is surfaced as a pill. Three words are "priority" tags —
 * low / med|medium / high — that also drive the item's (and card's) accent.
 * ---------------------------------------------------------------------- */

/**
 * Rank a priority word so a higher one can win when several are present.
 * Unknown words rank 0.
 */
function spudmagTodoPriorityRank($word) {
	$ranks = array('high' => 3, 'med' => 2, 'medium' => 2, 'low' => 1);
	$word = strtolower($word);
	return isset($ranks[$word]) ? $ranks[$word] : 0;
}

/**
 * Parse an item's text into its display sentence (tags removed, whitespace
 * collapsed), the list of tag words as typed, and the winning priority slug
 * ('low' | 'med' | 'high' | ''). "medium" normalizes to "med". When multiple
 * priority tags appear the highest wins.
 */
function spudmagTodoParseTags($text) {
	$tags = array();
	if (preg_match_all('/#([\p{L}\p{N}_-]+)/u', $text, $matches)) {
		$tags = $matches[1];
	}

	$topRank = 0;
	$priority = '';
	foreach ($tags as $tag) {
		$rank = spudmagTodoPriorityRank($tag);
		if ($rank > $topRank) {
			$topRank = $rank;
			$priority = ($rank === 2) ? 'med' : strtolower($tag);
		}
	}

	$display = preg_replace('/#[\p{L}\p{N}_-]+/u', '', $text);
	$display = trim(preg_replace('/\s+/u', ' ', $display));

	return array('display' => $display, 'tags' => $tags, 'priority' => $priority);
}

/**
 * The highest priority among a post's incomplete items ('' if none), used for
 * the aggregate card accent on the To Do page.
 */
function spudmagTodoTopPriority($postId) {
	$topRank = 0;
	$priority = '';
	foreach (spudmagGetTodos($postId) as $item) {
		if ($item['done']) {
			continue;
		}
		$parsed = spudmagTodoParseTags($item['text']);
		$rank = spudmagTodoPriorityRank($parsed['priority']);
		if ($rank > $topRank) {
			$topRank = $rank;
			$priority = $parsed['priority'];
		}
	}
	return $priority;
}

/* -------------------------------------------------------------------------
 * Mutations. Each returns the freshly-saved list.
 * ---------------------------------------------------------------------- */

function spudmagTodoAdd($postId, $text = '') {
	$items = spudmagGetTodos($postId);
	$items[] = array(
		'id'    => uniqid(),
		'text'  => $text,
		'done'  => false,
		'added' => time()
	);
	return spudmagSaveTodos($postId, $items);
}

function spudmagTodoToggle($postId, $id, $done) {
	$items = spudmagGetTodos($postId);
	foreach ($items as &$item) {
		if ($item['id'] === $id) {
			$item['done'] = (bool) $done;
			break;
		}
	}
	unset($item);
	return spudmagSaveTodos($postId, $items);
}

function spudmagTodoEditText($postId, $id, $text) {
	$items = spudmagGetTodos($postId);
	foreach ($items as &$item) {
		if ($item['id'] === $id) {
			$item['text'] = $text;
			break;
		}
	}
	unset($item);
	return spudmagSaveTodos($postId, $items);
}

function spudmagTodoRemove($postId, $id) {
	$items = spudmagGetTodos($postId);
	$items = array_filter($items, function($item) use ($id) {
		return $item['id'] !== $id;
	});
	return spudmagSaveTodos($postId, array_values($items));
}

/**
 * Reorder to match a list of ids. Ids not in the list are appended in their
 * existing order so a stale/partial payload can never drop items.
 */
function spudmagTodoReorder($postId, $orderedIds) {
	$byId = array();
	foreach (spudmagGetTodos($postId) as $item) {
		$byId[$item['id']] = $item;
	}
	$reordered = array();
	foreach ($orderedIds as $id) {
		if (isset($byId[$id])) {
			$reordered[] = $byId[$id];
			unset($byId[$id]);
		}
	}
	foreach ($byId as $item) {
		$reordered[] = $item;
	}
	return spudmagSaveTodos($postId, $reordered);
}

/* -------------------------------------------------------------------------
 * Shared query: unpublished posts that still have work, in worklist order.
 * ---------------------------------------------------------------------- */

/**
 * Whether a post carries an intended publication date. Scheduled posts always
 * do; a draft does once its date has been explicitly set (WordPress leaves
 * post_date_gmt at the zero sentinel for a "floating"/undated draft). Lets a
 * dated draft be planned on the calendar just like a scheduled post.
 */
function spudmagTodoHasDate($post) {
	return $post->post_date_gmt !== '0000-00-00 00:00:00'
		&& $post->post_date !== '0000-00-00 00:00:00';
}

/**
 * Draft + scheduled posts with at least one incomplete item, ordered by their
 * intended publication date (soonest first) regardless of draft/scheduled
 * status, so a dated draft slots into the timeline alongside scheduled posts.
 * Undated drafts sort to the very end, newest-modified first. Used by both the
 * To Do page and the dashboard widget.
 *
 * @param int $limit Max posts to return (0 = all).
 * @return WP_Post[]
 */
function spudmagTodoPosts($limit = 0) {
	$posts = get_posts(array(
		'post_type'        => 'post',
		'post_status'      => array('draft', 'future'),
		'posts_per_page'   => -1,
		'suppress_filters' => false
	));

	$posts = array_values(array_filter($posts, function($post) {
		return spudmagTodoHasWork($post->ID);
	}));

	usort($posts, function($a, $b) {
		$aDated = spudmagTodoHasDate($a);
		$bDated = spudmagTodoHasDate($b);
		if ($aDated !== $bDated) {
			// Anything with a date outranks the undated tail.
			return $aDated ? -1 : 1;
		}
		if ($aDated) {
			// Dated posts: by intended publication date, soonest first.
			return strcmp($a->post_date, $b->post_date);
		}
		// Undated drafts: most recently modified first.
		return strcmp($b->post_modified, $a->post_modified);
	});

	if ($limit > 0) {
		$posts = array_slice($posts, 0, $limit);
	}
	return $posts;
}

/* -------------------------------------------------------------------------
 * Reusable list UI (shared by the meta box and the To Do page).
 * ---------------------------------------------------------------------- */

/**
 * Render the editable list for a post: the rows, plus a borderless "Add item"
 * button, wrapped in a container the JS binds to. When $args['reorder'] is true
 * each row is draggable (edit screen only); the To Do page passes false.
 *
 * $args['header'] (optional, pre-escaped HTML) puts the add button on the same
 * line as that header, aligned right (the To Do page passes the post heading);
 * without it the button sits below the list, right-aligned (the meta box).
 */
function spudmagTodoRenderList($postId, $args = array()) {
	$args = array_merge(array('reorder' => false, 'header' => ''), $args);
	$items = spudmagGetTodos($postId);

	ob_start();
	foreach ($items as $item) {
		spudmagTodoRenderRow($item, $args['reorder']);
	}
	$rows = ob_get_clean();

	$addButton = '<button type="button" class="spudmag-todo-add">'
		. esc_html__('+ Add item', 'spudworks-mag') . '</button>';
	?>
	<div class="spudmag-todos-wrap" data-post-id="<?php echo esc_attr($postId); ?>" data-reorder="<?php echo $args['reorder'] ? '1' : '0'; ?>">
		<?php if ($args['header'] !== '') : ?>
			<div class="spudmag-todos-head">
				<?php echo $args['header']; // caller-escaped ?>
				<?php echo $addButton; ?>
			</div>
			<ul class="spudmag-todos"><?php echo $rows; ?></ul>
		<?php else : ?>
			<ul class="spudmag-todos"><?php echo $rows; ?></ul>
			<div class="spudmag-todos-foot"><?php echo $addButton; ?></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render a single To-Do row. Saved items render read-only: the raw text (with
 * any #tags) lives in a hidden, locked input, while the visible ".display"
 * shows the sentence with tags stripped plus a pill per tag. A double-click
 * unlocks the input for editing (JS toggles the row's is-editing class); the
 * item's winning priority sets an is-prio-* class that colors the left border.
 * The JS mirrors this markup when re-rendering from AJAX — keep the two in step.
 */
function spudmagTodoRenderRow($item, $reorder) {
	$parsed = spudmagTodoParseTags($item['text']);
	$classes = 'spudmag-todo' . ($item['done'] ? ' is-done' : '');
	if ($parsed['priority'] !== '') {
		$classes .= ' is-prio-' . $parsed['priority'];
	}
	?>
	<li class="<?php echo esc_attr($classes); ?>" data-id="<?php echo esc_attr($item['id']); ?>"<?php echo $reorder ? ' draggable="true"' : ''; ?>>
		<?php if ($reorder) : ?>
			<span class="spudmag-todo-handle" aria-hidden="true">&#8942;&#8942;</span>
		<?php endif; ?>
		<input type="checkbox" class="spudmag-todo-check"<?php checked($item['done']); ?>>
		<input type="text" class="spudmag-todo-text is-locked" value="<?php echo esc_attr($item['text']); ?>" placeholder="<?php esc_attr_e('To do&hellip;', 'spudworks-mag'); ?>" readonly>
		<div class="spudmag-todo-display"><?php spudmagTodoRenderDisplay($parsed); ?></div>
		<button type="button" class="spudmag-todo-remove" aria-label="<?php esc_attr_e('Remove item', 'spudworks-mag'); ?>">&times;</button>
	</li>
	<?php
}

/**
 * The locked-row body: the tag-stripped sentence, then a pill per tag. Priority
 * tags carry an is-prio-* modifier so the pill is color-coded. Mirrored by
 * renderRowContent() in js/admin-todos.js.
 */
function spudmagTodoRenderDisplay($parsed) {
	echo '<span class="spudmag-todo-sentence">' . esc_html($parsed['display']) . '</span>';
	if (empty($parsed['tags'])) {
		return;
	}
	echo '<span class="spudmag-todo-pills">';
	foreach ($parsed['tags'] as $tag) {
		$rank = spudmagTodoPriorityRank($tag);
		$pillClass = 'spudmag-todo-pill';
		if ($rank > 0) {
			$pillClass .= ' is-prio-' . (($rank === 2) ? 'med' : strtolower($tag));
		}
		echo '<span class="' . esc_attr($pillClass) . '">#' . esc_html($tag) . '</span>';
	}
	echo '</span>';
}

/* -------------------------------------------------------------------------
 * Editor meta box.
 * ---------------------------------------------------------------------- */

/**
 * Register the To-Do meta box on the post editor. The title carries a live
 * "(X/Y)" counter span (HTML in a meta box title is allowed by core and passed
 * through unescaped); the JS keeps it in sync. As a normal meta box it appears
 * in Screen Options automatically, giving the requested on/off toggle for free.
 */
function spudmagTodosMetaBox($postType, $post) {
	if ($postType !== 'post') {
		return;
	}
	$counts = spudmagTodoCounts($post->ID);
	$title = esc_html__('Item To Dos', 'spudworks-mag')
		. ' <span class="spudmag-todo-count" data-count-for="' . esc_attr($post->ID) . '">'
		. esc_html(spudmagTodoCountLabel($counts)) . '</span>';

	add_meta_box('spudmag-todos', $title, 'spudmagTodosRenderMetaBox', 'post', 'normal', 'default');
}
add_action('add_meta_boxes', 'spudmagTodosMetaBox', 10, 2);

/**
 * Meta box body: the reusable list with drag-to-reorder enabled.
 */
function spudmagTodosRenderMetaBox($post) {
	spudmagTodoRenderList($post->ID, array('reorder' => true));
}

/* -------------------------------------------------------------------------
 * AJAX: one endpoint, dispatched on an "op" parameter. Every response carries
 * the fresh item list + counts so the client re-renders from a single source.
 * ---------------------------------------------------------------------- */

function spudmagTodoAjax() {
	check_ajax_referer('spudmag_todo', 'nonce');

	$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	if (!$postId || !current_user_can('edit_post', $postId)) {
		wp_send_json_error(array('message' => 'forbidden'), 403);
	}

	$op = isset($_POST['op']) ? sanitize_key($_POST['op']) : '';
	$id = isset($_POST['id']) ? sanitize_key($_POST['id']) : '';

	switch ($op) {
		case 'add':
			$text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
			spudmagTodoAdd($postId, $text);
			break;
		case 'toggle':
			spudmagTodoToggle($postId, $id, !empty($_POST['done']));
			break;
		case 'edit':
			$text = isset($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
			spudmagTodoEditText($postId, $id, $text);
			break;
		case 'remove':
			spudmagTodoRemove($postId, $id);
			break;
		case 'reorder':
			$ids = (isset($_POST['ids']) && is_array($_POST['ids']))
				? array_map('sanitize_key', wp_unslash($_POST['ids']))
				: array();
			spudmagTodoReorder($postId, $ids);
			break;
		default:
			wp_send_json_error(array('message' => 'bad op'), 400);
	}

	wp_send_json_success(array(
		'items'  => spudmagGetTodos($postId),
		'counts' => spudmagTodoCounts($postId)
	));
}
add_action('wp_ajax_spudmag_todo', 'spudmagTodoAjax');
