<?php
/**
 * spudWorks Magazine — post edit-screen meta boxes.
 *
 * Adds three controls to the post editor, stored as post meta and read at
 * display time:
 *   - Template selector  -> METATEMPLATE  (default | fiction | showcase)
 *   - Secondary headline -> METASUBHEAD   (free text shown under the headline)
 *   - Pinned             -> METAPINNED    (promotes the post on the home page)
 */

/**
 * Register the meta box on the post edit screen.
 */
function spudmagAddMetaBox() {
	add_meta_box(
		'spudmag-options',
		__('spudWorks Magazine', 'spudworks-mag'),
		'spudmagRenderMetaBox',
		'post',
		'side',
		'high'
	);
}
add_action('add_meta_boxes', 'spudmagAddMetaBox');

/**
 * Render the meta box fields.
 */
function spudmagRenderMetaBox($post) {
	wp_nonce_field('spudmag_save_meta', 'spudmag_meta_nonce');

	$template = spudmagGetTemplate($post->ID);
	$subhead = spudmagGetSubhead($post->ID);
	$pinned = spudmagIsPinned($post->ID);
	$rating = spudmagGetRating($post->ID);
	$reviewItem = spudmagGetReviewItem($post->ID);
	$reviewType = spudmagGetReviewType($post->ID);

	$templates = array(
		'default'  => __('Default', 'spudworks-mag'),
		'fiction'  => __('Fiction', 'spudworks-mag'),
		'showcase' => __('Showcase', 'spudworks-mag')
	);
	?>
	<p>
		<label for="spudmag-template"><strong><?php esc_html_e('Content template', 'spudworks-mag'); ?></strong></label><br>
		<select name="spudmag_template" id="spudmag-template" class="widefat">
			<?php foreach ($templates as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($template, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="spudmag-subhead"><strong><?php esc_html_e('Secondary headline', 'spudworks-mag'); ?></strong></label><br>
		<input type="text" name="spudmag_subhead" id="spudmag-subhead" class="widefat" value="<?php echo esc_attr($subhead); ?>">
	</p>
	<p>
		<label for="spudmag-pinned">
			<input type="checkbox" name="spudmag_pinned" id="spudmag-pinned" value="1" <?php checked($pinned, true); ?>>
			<?php esc_html_e('Pin to top of home page', 'spudworks-mag'); ?>
		</label>
	</p>
	<hr>
	<p>
		<label for="spudmag-rating"><strong><?php esc_html_e('Review rating', 'spudworks-mag'); ?></strong> <em><?php esc_html_e('(Reviews only)', 'spudworks-mag'); ?></em></label><br>
		<input type="number" name="spudmag_rating" id="spudmag-rating" class="widefat" min="0" max="5" step="0.5" value="<?php echo $rating > 0 ? esc_attr($rating) : ''; ?>" placeholder="0–5">
	</p>
	<p>
		<label for="spudmag-review-item"><strong><?php esc_html_e('Item reviewed', 'spudworks-mag'); ?></strong></label><br>
		<input type="text" name="spudmag_review_item" id="spudmag-review-item" class="widefat" value="<?php echo esc_attr($reviewItem); ?>" placeholder="<?php esc_attr_e('Defaults to the post title', 'spudworks-mag'); ?>">
	</p>
	<p>
		<label for="spudmag-review-type"><strong><?php esc_html_e('Item type', 'spudworks-mag'); ?></strong> <em><?php esc_html_e('(for the review snippet)', 'spudworks-mag'); ?></em></label><br>
		<select name="spudmag_review_type" id="spudmag-review-type" class="widefat">
			<?php foreach (spudmagReviewTypes() as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($reviewType, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * Persist the meta box values, with nonce + capability + sanitization checks.
 */
function spudmagSaveMeta($postId) {
	if (!isset($_POST['spudmag_meta_nonce']) || !wp_verify_nonce($_POST['spudmag_meta_nonce'], 'spudmag_save_meta')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $postId)) {
		return;
	}

	$allowed = array('default', 'fiction', 'showcase');
	$template = isset($_POST['spudmag_template']) ? sanitize_key($_POST['spudmag_template']) : 'default';
	if (!in_array($template, $allowed, true)) {
		$template = 'default';
	}
	update_post_meta($postId, METATEMPLATE, $template);

	// Allow inline HTML in the secondary headline (matches the main headline);
	// wp_kses_post permits formatting tags while stripping anything dangerous.
	$subhead = isset($_POST['spudmag_subhead']) ? wp_kses_post(wp_unslash($_POST['spudmag_subhead'])) : '';
	update_post_meta($postId, METASUBHEAD, $subhead);

	// A single pinned post at a time keeps the home page deterministic: setting
	// one clears any previously pinned post on this site.
	if (!empty($_POST['spudmag_pinned'])) {
		spudmagClearPinned($postId);
		update_post_meta($postId, METAPINNED, '1');
	} else {
		delete_post_meta($postId, METAPINNED);
	}

	// Review rating (0-5, half steps). A blank/zero value clears it so the post
	// emits a plain Article rather than a Review with a 0 rating.
	$rating = isset($_POST['spudmag_rating']) ? (float) $_POST['spudmag_rating'] : 0.0;
	$rating = max(0.0, min(5.0, $rating));
	if ($rating > 0) {
		update_post_meta($postId, METARATING, $rating);
	} else {
		delete_post_meta($postId, METARATING);
	}

	$reviewItem = isset($_POST['spudmag_review_item']) ? sanitize_text_field(wp_unslash($_POST['spudmag_review_item'])) : '';
	if ($reviewItem !== '') {
		update_post_meta($postId, METAREVIEWITEM, $reviewItem);
	} else {
		delete_post_meta($postId, METAREVIEWITEM);
	}

	// Item type for the review's itemReviewed @type; only stored when it's one
	// of the Google-accepted types, otherwise cleared (the getter then defaults).
	$reviewType = isset($_POST['spudmag_review_type']) ? sanitize_text_field(wp_unslash($_POST['spudmag_review_type'])) : '';
	if (array_key_exists($reviewType, spudmagReviewTypes())) {
		update_post_meta($postId, METAREVIEWTYPE, $reviewType);
	} else {
		delete_post_meta($postId, METAREVIEWTYPE);
	}
}
add_action('save_post', 'spudmagSaveMeta');

/**
 * Remove the pinned flag from every post except $keepId.
 */
function spudmagClearPinned($keepId) {
	$pinned = get_posts(array(
		'post_type'           => 'post',
		'post_status'         => 'any',
		'posts_per_page'      => -1,
		'fields'              => 'ids',
		'ignore_sticky_posts' => true,
		'meta_key'            => METAPINNED,
		'meta_value'          => '1'
	));
	foreach ($pinned as $id) {
		if ((int) $id !== (int) $keepId) {
			delete_post_meta($id, METAPINNED);
		}
	}
}
