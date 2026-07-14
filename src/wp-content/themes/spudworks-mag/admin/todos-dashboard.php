<?php
/**
 * spudWorks Magazine — "Posts To Do" dashboard widget.
 *
 * A read-only glance, styled after the core Activity widget: the five posts
 * closest to publication that still have outstanding items, grouped by
 * publication date (drafts fall under "Unscheduled"), each showing up to three
 * of its highest-ordered incomplete tasks. Every line links to the editor.
 */

define('SPUDMAG_TODO_DASH_POSTS', 5);
define('SPUDMAG_TODO_DASH_TASKS', 3);

/**
 * Register the widget.
 */
function spudmagTodosDashboardSetup() {
	if (!current_user_can('edit_posts')) {
		return;
	}
	wp_add_dashboard_widget(
		'spudmag_todos',
		__('Posts To Do', 'spudworks-mag'),
		'spudmagTodosDashboardRender'
	);
}
add_action('wp_dashboard_setup', 'spudmagTodosDashboardSetup');

/**
 * Render the widget body.
 */
function spudmagTodosDashboardRender() {
	$posts = spudmagTodoPosts(SPUDMAG_TODO_DASH_POSTS);

	if (empty($posts)) {
		echo '<p>' . esc_html__('Nothing outstanding — all clear.', 'spudworks-mag') . '</p>';
		return;
	}

	echo '<div class="spudmag-todos-dash">';
	$currentGroup = null;

	foreach ($posts as $post) {
		$group = spudmagTodoDashGroup($post);
		if ($group !== $currentGroup) {
			if ($currentGroup !== null) {
				echo '</ul>';
			}
			echo '<h3 class="spudmag-todos-dash-group">' . esc_html($group) . '</h3>';
			echo '<ul class="spudmag-todos-dash-list">';
			$currentGroup = $group;
		}

		$editLink = get_edit_post_link($post->ID);
		$title = get_the_title($post) ?: __('(no title)', 'spudworks-mag');

		echo '<li class="spudmag-todos-dash-post">';
		echo '<a class="spudmag-todos-dash-name" href="' . esc_url($editLink) . '">' . esc_html($title) . '</a>';

		$open = array_filter(spudmagGetTodos($post->ID), function($item) {
			return !$item['done'];
		});
		$open = array_slice($open, 0, SPUDMAG_TODO_DASH_TASKS);

		if (!empty($open)) {
			echo '<ul class="spudmag-todos-dash-tasks">';
			foreach ($open as $item) {
				// Glance view: no pills — strip all tags from the text and let the
				// item's priority (if any) color the line's left border.
				$parsed = spudmagTodoParseTags($item['text']);
				$liClass = ($parsed['priority'] !== '') ? ' class="is-prio-' . $parsed['priority'] . '"' : '';
				echo '<li' . $liClass . '><a href="' . esc_url($editLink) . '">' . esc_html($parsed['display']) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</li>';
	}

	echo '</ul>';
	echo '</div>';
}

/**
 * The grouping label for a post: its intended publication date (scheduled posts
 * and dated drafts alike), otherwise "Unscheduled" for a floating draft.
 */
function spudmagTodoDashGroup($post) {
	if (spudmagTodoHasDate($post)) {
		return get_the_time(get_option('date_format'), $post);
	}
	return __('Unscheduled', 'spudworks-mag');
}
