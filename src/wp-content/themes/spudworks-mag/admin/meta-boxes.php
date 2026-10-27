<?php
/**
 * spudWorks Magazine — post edit-screen meta boxes.
 *
 * Adds three controls to the post editor, stored as post meta and read at
 * display time:
 *   - Template selector  -> METATEMPLATE  (default | fiction | showcase)
 *   - Secondary headline -> METASUBHEAD   (free text shown under the headline)
 *   - Featured           -> core sticky   (promotes the post on the home page)
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
 * One review's fields (rating, item name, item type). The inputs are array
 * fields so any number of these groups can ride along in the same save; the
 * template clone in js/admin-reviews.js uses this same markup.
 */
function spudmagRenderReviewFields($review) {
	$reviewTypes = spudmagReviewTypes();
	?>
	<div class="spudmag-review">
		<p>
			<label><strong><?php esc_html_e('Review rating', 'spudworks-mag'); ?></strong> <em><?php esc_html_e('(Reviews only)', 'spudworks-mag'); ?></em></label><br>
			<input type="number" name="spudmag_rating[]" class="widefat" min="0.5" max="5" step="0.5" value="<?php echo $review['rating'] > 0 ? esc_attr($review['rating']) : ''; ?>" placeholder="0.5–5">
		</p>
		<p>
			<label><strong><?php esc_html_e('Item reviewed', 'spudworks-mag'); ?></strong></label><br>
			<input type="text" name="spudmag_review_item[]" class="widefat" value="<?php echo esc_attr($review['item']); ?>" placeholder="<?php esc_attr_e('Defaults to the post title', 'spudworks-mag'); ?>">
		</p>
		<p>
			<label><strong><?php esc_html_e('Item type', 'spudworks-mag'); ?></strong> <em><?php esc_html_e('(for the review snippet)', 'spudworks-mag'); ?></em></label><br>
			<select name="spudmag_review_type[]" class="widefat">
				<?php foreach ($reviewTypes as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($review['type'], $value); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="spudmag-review-remove-wrap"><button type="button" class="button-link button-link-delete spudmag-review-remove"><?php esc_html_e('Remove this review', 'spudworks-mag'); ?></button></p>
		<hr>
	</div>
	<?php
}

/**
 * Render the meta box fields.
 */
function spudmagRenderMetaBox($post) {
	wp_nonce_field('spudmag_save_meta', 'spudmag_meta_nonce');

	$template = spudmagGetTemplate($post->ID);
	$subhead = spudmagGetSubhead($post->ID);
	$sticky = is_sticky($post->ID);
	$reviews = spudmagGetReviews($post->ID);
	$blankReview = array('item' => '', 'type' => 'Product', 'rating' => 0.0);

	$templates = array(
		'showcase' => __('Showcase', 'spudworks-mag'),
		'fiction'  => __('Fiction', 'spudworks-mag'),
		'default'  => __('No Sidebar', 'spudworks-mag')
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
		<label for="spudmag-sticky">
			<input type="checkbox" name="spudmag_sticky" id="spudmag-sticky" value="1" <?php checked($sticky, true); ?>>
			<?php esc_html_e('Feature at the top of the home page', 'spudworks-mag'); ?>
		</label>
	</p>
	<hr>
	<div id="spudmag-reviews">
		<?php foreach ($reviews as $review) : ?>
			<?php spudmagRenderReviewFields($review); ?>
		<?php endforeach; ?>
	</div>
	<template id="spudmag-review-template">
		<?php spudmagRenderReviewFields($blankReview); ?>
	</template>
	<p><button type="button" class="button" id="spudmag-review-add"><?php esc_html_e('Add another review', 'spudworks-mag'); ?></button></p>
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

	// Sticky is applied in spudmagSaveSticky() below, on 'redirect_post_location'
	// rather than here: core's own edit_post() sets sticky status from its own
	// "Stick this post..." checkbox *after* save_post fires, so anything set
	// here would just be overwritten by core a moment later.

	// Reviews: parallel arrays of rating / item / type, one slot per review box.
	// Blank extra boxes (no item name and no rating) are dropped so they vanish
	// on refresh; the first box always survives as slot 0. One surviving review
	// stays scalar in the meta fields, several are stored as arrays.
	$ratings = isset($_POST['spudmag_rating']) ? (array) wp_unslash($_POST['spudmag_rating']) : array();
	$items   = isset($_POST['spudmag_review_item']) ? (array) wp_unslash($_POST['spudmag_review_item']) : array();
	$types   = isset($_POST['spudmag_review_type']) ? (array) wp_unslash($_POST['spudmag_review_type']) : array();
	$reviewTypes = spudmagReviewTypes();

	$savedItems = array();
	$savedTypes = array();
	$savedRatings = array();
	$slots = max(count($ratings), count($items), count($types));
	for ($i = 0; $i < $slots; $i++) {
		$rating = isset($ratings[$i]) && is_scalar($ratings[$i]) ? max(0.0, min(5.0, (float) $ratings[$i])) : 0.0;
		$item   = isset($items[$i]) && is_scalar($items[$i]) ? sanitize_text_field($items[$i]) : '';
		$type   = isset($types[$i]) && is_scalar($types[$i]) ? sanitize_text_field($types[$i]) : '';
		if ($i > 0 && $item === '' && $rating <= 0) {
			continue;
		}
		$savedItems[]   = $item;
		$savedTypes[]   = array_key_exists($type, $reviewTypes) ? $type : 'Product';
		$savedRatings[] = $rating;
	}

	if (count($savedItems) > 1) {
		update_post_meta($postId, METARATING, $savedRatings);
		update_post_meta($postId, METAREVIEWITEM, $savedItems);
		update_post_meta($postId, METAREVIEWTYPE, $savedTypes);
		return;
	}

	// Single review: scalar fields. A blank/zero rating clears it so the post
	// emits a plain Article rather than a Review with a 0 rating; the type is
	// only stored when valid (the getter otherwise defaults).
	$rating = $savedRatings ? $savedRatings[0] : 0.0;
	if ($rating > 0) {
		update_post_meta($postId, METARATING, $rating);
	} else {
		delete_post_meta($postId, METARATING);
	}

	$reviewItem = $savedItems ? $savedItems[0] : '';
	if ($reviewItem !== '') {
		update_post_meta($postId, METAREVIEWITEM, $reviewItem);
	} else {
		delete_post_meta($postId, METAREVIEWITEM);
	}

	$reviewType = $savedTypes ? $savedTypes[0] : '';
	if ($reviewType !== '') {
		update_post_meta($postId, METAREVIEWTYPE, $reviewType);
	} else {
		delete_post_meta($postId, METAREVIEWTYPE);
	}
}
add_action('save_post', 'spudmagSaveMeta');

/**
 * Apply the "Feature" checkbox as core sticky status.
 *
 * Can't do this from spudmagSaveMeta(): core's edit_post() applies its own
 * "Stick this post to the front page" checkbox via stick_post()/unstick_post()
 * *after* save_post fires, so it would immediately overwrite anything set
 * there. redirect_post_location fires last — right before the post-save
 * redirect, once edit_post() has already finished — so hooking it here gives
 * our field the final, authoritative word instead of core's own checkbox
 * (which stays in the UI, hidden, but has no effect).
 */
function spudmagSaveSticky($location, $postId) {
	if (!isset($_POST['spudmag_meta_nonce']) || !wp_verify_nonce($_POST['spudmag_meta_nonce'], 'spudmag_save_meta')) {
		return $location;
	}
	// Mirrors core's own capability gate for sticky (see edit_post() in
	// wp-admin/includes/post.php) — featuring a post promotes it site-wide,
	// so it takes the same higher bar as editing others' posts.
	if (get_post_type($postId) !== 'post' || !current_user_can('edit_others_posts') || !current_user_can('publish_posts')) {
		return $location;
	}

	if (!empty($_POST['spudmag_sticky'])) {
		stick_post($postId);
	} else {
		unstick_post($postId);
	}

	return $location;
}
add_filter('redirect_post_location', 'spudmagSaveSticky', 10, 2);
