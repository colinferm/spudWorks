<?php
/**
 * spudWorks Magazine — editor authoring helpers (admin only).
 *
 *   - Quicktags buttons in the post Text editor for the [multipart] and
 *     [author-note] shortcodes.
 *   - A contextual Help tab on the post screen documenting their syntax.
 *   - The live post-search script (+ AJAX nonce) for the Featured Post widget.
 */

/**
 * Enqueue admin scripts on the screens that need them.
 */
function spudmagAdminAssets($hook) {
	// Quicktags buttons: only on the post add/edit screens.
	if ($hook === 'post.php' || $hook === 'post-new.php') {
		wp_enqueue_script(
			'spudmag-quicktags',
			THEMEURI . '/js/admin-quicktags.js',
			array('quicktags'),
			THEMEVERSION,
			true
		);
	}

	// Featured Post widget search: the widgets screen and the customizer.
	if ($hook === 'widgets.php' || $hook === 'customize.php') {
		wp_enqueue_script(
			'spudmag-widget-featured',
			THEMEURI . '/js/admin-widget-featured.js',
			array(),
			THEMEVERSION,
			true
		);
		wp_localize_script('spudmag-widget-featured', 'spudmagFeatured', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('spudmag_featured_search')
		));
	}
}
add_action('admin_enqueue_scripts', 'spudmagAdminAssets');

/**
 * Add a Help tab documenting the theme shortcodes on the post edit screen.
 */
function spudmagEditorHelpTab() {
	$screen = get_current_screen();
	if (!$screen || $screen->base !== 'post') {
		return;
	}

	$help = '<p>' . esc_html__('spudWorks Magazine adds these shortcodes you can use in the post Text editor (the buttons sit after "Close Tags"):', 'spudworks-mag') . '</p>'
		. '<h3>[multipart]</h3>'
		. '<p>' . esc_html__('Links the parts of a multi-part post. Place it at the top and/or bottom of the post. Every attribute is optional; the box only appears when at least one URL is given, and each side shows only when its URL is present.', 'spudworks-mag') . '</p>'
		. '<pre>[multipart prev-url="https://&hellip;" prev-link="The opening chapter" next-url="https://&hellip;" next-link="The next chapter"]Why you might want to start at the previous part.[/multipart]</pre>'
		. '<ul>'
		. '<li><code>prev-url</code> / <code>next-url</code> — ' . esc_html__('the linked URLs (optional).', 'spudworks-mag') . '</li>'
		. '<li><code>prev-link</code> / <code>next-link</code> — ' . esc_html__('the link text. Default "Previous Post" / "Next Post".', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Text between the tags is an optional intro shown above the links.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[author-note]</h3>'
		. '<p>' . esc_html__('A styled aside headed "A note from the author, NAME". NAME is the post author by default; add author="Name" to override.', 'spudworks-mag') . '</p>'
		. '<pre>[author-note]Your note to the reader goes here.[/author-note]</pre>'
		. '<h3>[featured-comment]</h3>'
		. '<p>' . esc_html__('Pulls a reader comment into the post as a quoted callout wrapped in large quote marks. Give an id to fetch a real approved comment (with its author avatar), or supply author/text manually.', 'spudworks-mag') . '</p>'
		. '<pre>[featured-comment id="42"]Optional preface[/featured-comment]
[featured-comment author="Jane Doe" text="A great read."]Optional preface[/featured-comment]</pre>'
		. '<ul>'
		. '<li><code>id</code> — ' . esc_html__('a comment ID. When present and approved, its text + avatar are shown.', 'spudworks-mag') . '</li>'
		. '<li><code>author</code> / <code>text</code> — ' . esc_html__('used when no id is given (no avatar in this mode).', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Text between the tags is an optional preface shown above the quote.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[notice]</h3>'
		. '<p>' . esc_html__('A full-width callout box flagging something important, with an icon set by the level. Level defaults to "info".', 'spudworks-mag') . '</p>'
		. '<pre>[notice level="warn"]Heads up — this changes in the next release.[/notice]</pre>'
		. '<ul>'
		. '<li><code>level</code> — ' . esc_html__('info, warn, or danger (default info). Sets the icon and accent colour.', 'spudworks-mag') . '</li>'
		. '</ul>';

	$screen->add_help_tab(array(
		'id'      => 'spudmag-shortcodes',
		'title'   => __('spudWorks Shortcodes', 'spudworks-mag'),
		'content' => $help
	));
}
add_action('current_screen', 'spudmagEditorHelpTab');
