<?php
/**
 * spudWorks Magazine — "To Do" admin page (Posts submenu).
 *
 * A cross-post worklist: every unpublished post (Draft or Scheduled) that still
 * has incomplete items, in publication order, with its list inline for ticking
 * off or removing tasks (and adding new ones). Reuses the data layer and list
 * UI from admin/todos.php; the only difference from the editor meta box is that
 * rows are not drag-reorderable here.
 */

/**
 * Register the submenu under Posts, stashing the returned hook suffix so the
 * asset enqueue can match this exact screen without hardcoding it.
 */
function spudmagTodosPageMenu() {
	$GLOBALS['spudmagTodosPageHook'] = add_submenu_page(
		'edit.php',
		__('To Do', 'spudworks-mag'),
		__('To Do', 'spudworks-mag'),
		'edit_posts',
		'spudmag-todos',
		'spudmagTodosPageRender'
	);
}
add_action('admin_menu', 'spudmagTodosPageMenu');

/**
 * The To Do page's admin hook suffix (empty until admin_menu has run).
 */
function spudmagTodosPageHook() {
	return isset($GLOBALS['spudmagTodosPageHook']) ? $GLOBALS['spudmagTodosPageHook'] : '';
}

/**
 * Render the page: a section per post, each headed by its (linked) title and a
 * live "(X/Y)" counter, followed by its editable list.
 */
function spudmagTodosPageRender() {
	if (!current_user_can('edit_posts')) {
		return;
	}
	$posts = spudmagTodoPosts();
	?>
	<div class="wrap spudmag-todos-page">
		<h1><?php esc_html_e('To Do', 'spudworks-mag'); ?></h1>
		<p class="description"><?php esc_html_e('Unpublished posts with outstanding tasks, ordered by publication. A post drops off once all its items are checked.', 'spudworks-mag'); ?></p>

		<?php if (empty($posts)) : ?>
			<p><?php esc_html_e('Nothing to do — every draft and scheduled post is clear.', 'spudworks-mag'); ?></p>
		<?php else : ?>
			<?php foreach ($posts as $post) :
				$counts = spudmagTodoCounts($post->ID);
				$editLink = get_edit_post_link($post->ID);
				$title = get_the_title($post) ?: __('(no title)', 'spudworks-mag');

				// The post heading; the shared renderer drops the "Add item"
				// button onto this same line, aligned right.
				$todoStatus = spudmagTodoStatusLabel($post);
				$header = '<h2 class="spudmag-todos-post-title">'
					. '<a href="' . esc_url($editLink) . '">' . esc_html($title) . '</a> '
					. '<span class="spudmag-todo-count" data-count-for="' . esc_attr($post->ID) . '">' . esc_html(spudmagTodoCountLabel($counts)) . '</span> '
					. '<span class="spudmag-todos-status">' . esc_html($todoStatus) . '</span>'
					. '</h2>';

					// Aggregate accent: the card's left border follows its highest
					// open-item priority (neutral gray when none).
					$topPriority = spudmagTodoTopPriority($post->ID);
					$postClasses = 'spudmag-todos-post ' . strtolower($todoStatus);
					if ($topPriority !== '') {
						$postClasses .= ' is-prio-' . $topPriority;
					}
				?>
				<div class="<?php echo esc_attr($postClasses); ?>">
					<?php spudmagTodoRenderList($post->ID, array('reorder' => true, 'header' => $header)); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * A post's worklist header label: "<status> – <date>", e.g. "Scheduled – Jul 31,
 * 2026" or "Draft – Jul 21, 2026". Undated drafts read "<status> – Unscheduled".
 */
function spudmagTodoStatusLabel($post) {
	$status = ($post->post_status === 'future')
		? __('Scheduled', 'spudworks-mag')
		: __('Draft', 'spudworks-mag');

	$when = spudmagTodoHasDate($post)
		? get_the_time(get_option('date_format'), $post)
		: __('Unscheduled', 'spudworks-mag');

	return $status . ' – ' . $when;
}
