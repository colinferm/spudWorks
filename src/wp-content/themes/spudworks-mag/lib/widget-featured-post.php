<?php
/**
 * spudWorks Magazine — "Featured Post" sidebar widget.
 *
 * Highlights a single hand-picked post using the featured-card format. The
 * admin form offers a live (AJAX) title search to choose the post; the chosen
 * post ID is stored on the widget instance.
 */

class Spudmag_Featured_Post_Widget extends WP_Widget {

	function __construct() {
		parent::__construct(
			'spudmag_featured_post',
			__('spudWorks: Featured Post', 'spudworks-mag'),
			array('description' => __('Highlight a single post using the featured-card style.', 'spudworks-mag'))
		);
	}

	/**
	 * Front-end output: optional title, then the featured card for the post.
	 */
	function widget($args, $instance) {
		$postId = isset($instance['post_id']) ? (int) $instance['post_id'] : 0;

		if (!$postId || get_post_status($postId) !== 'publish') {
			return;
		}

		echo $args['before_widget'];

		$title = isset($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
		if ($title !== '') {
			echo $args['before_title'] . esc_html($title) . $args['after_title'];
		}

		// Layout: 'under' (image on top, text beneath — the default) or 'overlay'
		// (text centered over the image), for a little sidebar differentiation.
		$layout = (isset($instance['layout']) && $instance['layout'] === 'under') ? 'under' : 'overlay';
		$template = ($layout == 'under') ? 'templates/card-under' : 'templates/card-overlay';

		get_template_part($template, '', array(
			'postId'     => $postId,
			'showByline' => false
		));

		echo $args['after_widget'];
	}

	/**
	 * Admin form: title, a searchable post picker, and the stored selection.
	 */
	function form($instance) {
		$title = isset($instance['title']) ? $instance['title'] : '';
		$postId = isset($instance['post_id']) ? (int) $instance['post_id'] : 0;
		$currentLabel = $postId ? get_the_title($postId) : '';
		$layout = (isset($instance['layout']) && $instance['layout'] === 'overlay') ? 'overlay' : 'under';
		?>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title (optional):', 'spudworks-mag'); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr($this->get_field_id('title')); ?>"
				name="<?php echo esc_attr($this->get_field_name('title')); ?>"
				value="<?php echo esc_attr($title); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr($this->get_field_id('layout')); ?>"><?php esc_html_e('Layout:', 'spudworks-mag'); ?></label>
			<select class="widefat"
				id="<?php echo esc_attr($this->get_field_id('layout')); ?>"
				name="<?php echo esc_attr($this->get_field_name('layout')); ?>">
				<option value="overlay" <?php selected($layout, 'overlay'); ?>><?php esc_html_e('Overlay — text over the image', 'spudworks-mag'); ?></option>
				<option value="under" <?php selected($layout, 'under'); ?>><?php esc_html_e('Under — image on top, text beneath', 'spudworks-mag'); ?></option>
			</select>
		</p>
		<div class="spudmag-featured-widget">
			<p>
				<label for="<?php echo esc_attr($this->get_field_id('search')); ?>"><strong><?php esc_html_e('Featured post', 'spudworks-mag'); ?></strong></label>
				<input class="widefat spudmag-featured-search" type="search" autocomplete="off"
					id="<?php echo esc_attr($this->get_field_id('search')); ?>"
					placeholder="<?php esc_attr_e('Search posts by title&hellip;', 'spudworks-mag'); ?>">
			</p>
			<ul class="spudmag-featured-results" hidden></ul>
			<p class="spudmag-featured-current" data-empty="<?php esc_attr_e('Nothing selected yet.', 'spudworks-mag'); ?>">
				<?php if ($currentLabel !== '') : ?>
					<?php esc_html_e('Selected:', 'spudworks-mag'); ?> <strong><?php echo esc_html($currentLabel); ?></strong>
				<?php else : ?>
					<?php esc_html_e('Nothing selected yet.', 'spudworks-mag'); ?>
				<?php endif; ?>
			</p>
			<input class="spudmag-featured-id" type="hidden"
				name="<?php echo esc_attr($this->get_field_name('post_id')); ?>"
				value="<?php echo esc_attr($postId); ?>">
		</div>
		<?php
	}

	/**
	 * Persist: sanitize the title and cast the chosen post ID.
	 */
	function update($new_instance, $old_instance) {
		$instance = array();
		$instance['title'] = isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
		$instance['post_id'] = isset($new_instance['post_id']) ? (int) $new_instance['post_id'] : 0;
		$instance['layout'] = (isset($new_instance['layout']) && $new_instance['layout'] === 'overlay') ? 'overlay' : 'under';
		return $instance;
	}
}

/**
 * AJAX: return up to 10 published posts matching the typed query, by title OR
 * tag name — so typing "escher" surfaces posts tagged Escher even when the
 * word isn't in the title, which core's 's' search (title/excerpt/content
 * only) can't do. A raw query rather than get_posts()/WP_Query: matching tag
 * names needs a join get_posts() has no argument for, and the picker only
 * ever needs an id + a title, not a fully hydrated WP_Post per row.
 *
 * Also backs the Landing Page related-articles picker (admin/page-meta.php),
 * so the capability check is 'edit_posts' rather than the widgets-only
 * 'edit_theme_options' — any Editor/Author building a page needs this, not
 * just an Administrator on the Widgets screen.
 */
function spudmagFeaturedSearch() {
	global $wpdb;

	check_ajax_referer('spudmag_featured_search', 'nonce');

	if (!current_user_can('edit_posts')) {
		wp_send_json_error(array(), 403);
	}

	$query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
	$results = array();

	if ($query !== '') {
		$like = '%' . $wpdb->esc_like($query) . '%';

		// LEFT JOIN to post_tag term relationships so a post with no matching
		// tag still matches on title alone; GROUP BY collapses a post with
		// several matching tags back to one row. Title matches are ranked
		// above tag-only matches, then by recency.
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT p.ID, p.post_title
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_tag'
			LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE p.post_type = 'post'
				AND p.post_status = 'publish'
				AND (p.post_title LIKE %s OR t.name LIKE %s)
			GROUP BY p.ID, p.post_title
			ORDER BY (p.post_title LIKE %s) DESC, p.post_date DESC
			LIMIT 10",
			$like, $like, $like
		));

		foreach ($rows as $row) {
			$results[] = array(
				'id'    => (int) $row->ID,
				'title' => $row->post_title
			);
		}
	}

	wp_send_json_success($results);
}
add_action('wp_ajax_spudmag_featured_search', 'spudmagFeaturedSearch');
