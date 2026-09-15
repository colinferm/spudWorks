<?php
/**
 * spudWorks Magazine — page edit-screen meta box.
 *
 * Adds one control to the page editor, mirroring the post editor's Content
 * template selector (admin/meta-boxes.php): a Page template dropdown, plus —
 * only meaningful once "Landing Page" is chosen — the two sidebar galleries
 * and the hand-picked related-articles list a Landing Page renders in
 * templates/landing-sidebar.php.
 *
 * Storage:
 *   Page template  -> METAPAGETEMPLATE  (default | landing)
 *   Galleries      -> METAGALLERIES     (array of 2: title, ids[], columns)
 *   Related list   -> METARELATED       (int[] post IDs, curator order)
 *   Related title  -> METARELATEDTITLE  (free text section heading)
 */

/**
 * Register the meta box on the page edit screen. 'normal'/'high' (rather than
 * the post meta box's 'side') because the galleries + related list need the
 * full editor width.
 */
function spudmagAddPageMetaBox() {
	add_meta_box(
		'spudmag-page-options',
		__('spudWorks Magazine — Page Template', 'spudworks-mag'),
		'spudmagRenderPageMetaBox',
		'page',
		'normal',
		'high'
	);
}
add_action('add_meta_boxes', 'spudmagAddPageMetaBox');

/**
 * Render one gallery's picker: title, column count, and a reorderable
 * thumbnail strip backed by per-image hidden inputs (dragging an <li> moves
 * its hidden input with it, so submitted order == visual order).
 */
function spudmagRenderGalleryField($index, $gallery) {
	?>
	<div class="spudmag-gallery-field">
		<p>
			<label for="spudmag-gallery-<?php echo esc_attr($index); ?>-title">
				<strong><?php echo esc_html(sprintf(__('Gallery %d title', 'spudworks-mag'), $index + 1)); ?></strong>
			</label><br>
			<input type="text" class="widefat"
				id="spudmag-gallery-<?php echo esc_attr($index); ?>-title"
				name="spudmag_gallery[<?php echo esc_attr($index); ?>][title]"
				value="<?php echo esc_attr($gallery['title']); ?>"
				placeholder="<?php esc_attr_e('Optional heading shown above the gallery', 'spudworks-mag'); ?>">
		</p>
		<p>
			<label for="spudmag-gallery-<?php echo esc_attr($index); ?>-columns">
				<?php esc_html_e('Columns', 'spudworks-mag'); ?>
			</label>
			<select id="spudmag-gallery-<?php echo esc_attr($index); ?>-columns" name="spudmag_gallery[<?php echo esc_attr($index); ?>][columns]">
				<?php for ($n = 1; $n <= 6; $n++) : ?>
					<option value="<?php echo esc_attr($n); ?>" <?php selected($gallery['columns'], $n); ?>><?php echo esc_html($n); ?></option>
				<?php endfor; ?>
			</select>
			<button type="button" class="button spudmag-gallery-edit" data-gallery-index="<?php echo esc_attr($index); ?>">
				<?php esc_html_e('Add / Edit images', 'spudworks-mag'); ?>
			</button>
		</p>
		<ul class="spudmag-gallery-list" data-gallery-index="<?php echo esc_attr($index); ?>" data-reorder="1">
			<?php foreach ($gallery['ids'] as $id) :
				$thumb = wp_get_attachment_image($id, 'thumbnail');
				if ($thumb === '') {
					continue;
				}
				?>
				<li class="spudmag-gallery-item" draggable="true" data-id="<?php echo esc_attr($id); ?>">
					<?php echo $thumb; ?>
					<input type="hidden" name="spudmag_gallery[<?php echo esc_attr($index); ?>][ids][]" value="<?php echo esc_attr($id); ?>">
					<button type="button" class="spudmag-gallery-remove" aria-label="<?php esc_attr_e('Remove image', 'spudworks-mag'); ?>">&times;</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/**
 * Render the meta box fields.
 */
function spudmagRenderPageMetaBox($post) {
	wp_nonce_field('spudmag_save_page_meta', 'spudmag_page_meta_nonce');

	$template = spudmagGetPageTemplate($post->ID);
	$galleries = spudmagGetPageGalleries($post->ID);
	$related = spudmagGetPageRelated($post->ID);
	$relatedTitle = get_post_meta($post->ID, METARELATEDTITLE, true);

	$templates = array(
		'default' => __('Default', 'spudworks-mag'),
		'landing' => __('Landing Page', 'spudworks-mag')
	);
	?>
	<p>
		<label for="spudmag-page-template"><strong><?php esc_html_e('Page template', 'spudworks-mag'); ?></strong></label><br>
		<select name="spudmag_page_template" id="spudmag-page-template">
			<?php foreach ($templates as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($template, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Landing Page uses the Showcase article layout (no byline, date, author box, or comments) with the sidebar built below instead of the site-wide Showcase Sidebar.', 'spudworks-mag'); ?></p>
	</p>

	<div class="spudmag-landing-fields<?php echo $template === 'landing' ? '' : ' is-hidden'; ?>">
		<hr>
		<h3><?php esc_html_e('Sidebar galleries', 'spudworks-mag'); ?></h3>
		<?php foreach ($galleries as $index => $gallery) : ?>
			<?php spudmagRenderGalleryField($index, $gallery); ?>
		<?php endforeach; ?>

		<hr>
		<h3><?php esc_html_e('Related articles', 'spudworks-mag'); ?></h3>
		<div class="spudmag-related-field">
			<p>
				<label for="spudmag-related-title"><strong><?php esc_html_e('Section title', 'spudworks-mag'); ?></strong></label><br>
				<input type="text" class="widefat" id="spudmag-related-title" name="spudmag_related_title"
					value="<?php echo esc_attr($relatedTitle); ?>"
					placeholder="<?php echo esc_attr__('Related Articles', 'spudworks-mag'); ?>">
			</p>
			<p>
				<label for="spudmag-related-search"><strong><?php esc_html_e('Add an article', 'spudworks-mag'); ?></strong></label><br>
				<input type="search" class="widefat spudmag-related-search" autocomplete="off" id="spudmag-related-search"
					placeholder="<?php esc_attr_e('Search posts by title…', 'spudworks-mag'); ?>">
			</p>
			<ul class="spudmag-related-results" hidden></ul>
			<ul class="spudmag-related-list" data-reorder="1">
				<?php foreach ($related as $id) : ?>
					<li draggable="true" data-id="<?php echo esc_attr($id); ?>">
						<span class="spudmag-related-handle" aria-hidden="true">&#8942;&#8942;</span>
						<span class="spudmag-related-title-text"><?php echo esc_html(get_the_title($id)); ?></span>
						<input type="hidden" name="spudmag_related[]" value="<?php echo esc_attr($id); ?>">
						<button type="button" class="spudmag-related-remove" aria-label="<?php esc_attr_e('Remove', 'spudworks-mag'); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php
}

/**
 * Persist the meta box values, with nonce + capability + sanitization checks.
 * Mirrors spudmagSaveMeta() (admin/meta-boxes.php) but for the 'page' post type.
 */
function spudmagSavePageMeta($postId) {
	if (!isset($_POST['spudmag_page_meta_nonce']) || !wp_verify_nonce($_POST['spudmag_page_meta_nonce'], 'spudmag_save_page_meta')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $postId)) {
		return;
	}
	if (get_post_type($postId) !== 'page') {
		return;
	}

	$allowedTemplates = array('default', 'landing');
	$template = isset($_POST['spudmag_page_template']) ? sanitize_key($_POST['spudmag_page_template']) : 'default';
	if (!in_array($template, $allowedTemplates, true)) {
		$template = 'default';
	}
	update_post_meta($postId, METAPAGETEMPLATE, $template);

	// Galleries: two entries, each sanitized independently regardless of how
	// many were actually posted (a missing index just yields an empty one).
	$postedGalleries = isset($_POST['spudmag_gallery']) && is_array($_POST['spudmag_gallery']) ? $_POST['spudmag_gallery'] : array();
	$galleries = array();
	for ($i = 0; $i < 2; $i++) {
		$entry = isset($postedGalleries[$i]) && is_array($postedGalleries[$i]) ? $postedGalleries[$i] : array();

		$ids = isset($entry['ids']) && is_array($entry['ids']) ? array_map('intval', $entry['ids']) : array();
		$ids = array_values(array_unique(array_filter($ids, function($id) {
			return $id > 0 && get_post_type($id) === 'attachment' && wp_attachment_is_image($id);
		})));

		$columns = isset($entry['columns']) ? (int) $entry['columns'] : 2;
		$columns = max(1, min(6, $columns ?: 2));

		$galleries[] = array(
			'title'   => isset($entry['title']) ? sanitize_text_field(wp_unslash($entry['title'])) : '',
			'ids'     => $ids,
			'columns' => $columns
		);
	}
	update_post_meta($postId, METAGALLERIES, $galleries);

	// Related articles: curator order is the posted array order; de-duplicated
	// and filtered to real posts (spudmagGetPageRelated() also filters to
	// 'publish' at render time, so an unpublished pick just fails to show).
	$postedRelated = isset($_POST['spudmag_related']) && is_array($_POST['spudmag_related']) ? $_POST['spudmag_related'] : array();
	$related = array();
	foreach ($postedRelated as $id) {
		$id = (int) $id;
		if ($id > 0 && get_post_type($id) === 'post' && !in_array($id, $related, true)) {
			$related[] = $id;
		}
	}
	update_post_meta($postId, METARELATED, $related);

	$relatedTitle = isset($_POST['spudmag_related_title']) ? sanitize_text_field(wp_unslash($_POST['spudmag_related_title'])) : '';
	if ($relatedTitle !== '') {
		update_post_meta($postId, METARELATEDTITLE, $relatedTitle);
	} else {
		delete_post_meta($postId, METARELATEDTITLE);
	}
}
add_action('save_post', 'spudmagSavePageMeta');
