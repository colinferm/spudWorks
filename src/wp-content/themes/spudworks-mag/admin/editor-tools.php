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
	// Theme admin stylesheet, loaded on every admin screen so rules can target
	// any dashboard or plugin page (e.g. Editorial Calendar). Authored in
	// css/admin.scss and compiled to css/admin.css by the Gulp task.
	wp_enqueue_style(
		'spudmag-admin',
		THEMEURI . '/css/admin.css',
		array(),
		THEMEVERSION
	);

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

	// Per-post To-Do lists: the post editor, the Posts > To Do page, and the
	// dashboard (where the widget links out). All mutations go through the one
	// admin-ajax endpoint, so every screen gets the same nonce + ajax URL.
	if ($hook === 'post.php' || $hook === 'post-new.php'
		|| $hook === spudmagTodosPageHook() || $hook === 'index.php') {
		wp_enqueue_script(
			'spudmag-todos',
			THEMEURI . '/js/admin-todos.js',
			array(),
			THEMEVERSION,
			true
		);
		wp_localize_script('spudmag-todos', 'spudmagTodos', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('spudmag_todo')
		));
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

	// Landing Page sidebar builder: the page editor only, never posts, so the
	// media modal — this theme's first use of it — stays off every other
	// screen. Reuses the same spudmag_featured_search endpoint (+ nonce
	// action) as the Featured Post widget for the related-articles search.
	if ($hook === 'post.php' || $hook === 'post-new.php') {
		$screen = get_current_screen();
		if ($screen && $screen->post_type === 'page') {
			wp_enqueue_media();
			wp_enqueue_script(
				'spudmag-landing',
				THEMEURI . '/js/admin-landing.js',
				array('media-editor'),
				THEMEVERSION,
				true
			);
			wp_localize_script('spudmag-landing', 'spudmagLanding', array(
				'ajaxUrl'       => admin_url('admin-ajax.php'),
				'nonce'         => wp_create_nonce('spudmag_featured_search'),
				'galleryTitle'  => __('Select Gallery Images', 'spudworks-mag'),
				'galleryButton' => __('Use these images', 'spudworks-mag')
			));
		}
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
		. '<p>' . esc_html__('Links the parts of a multi-part post. Place it at the top and/or bottom of the post. Every attribute is optional; the box only appears when at least one side resolves to a link.', 'spudworks-mag') . '</p>'
		. '<pre>[multipart prev-id="123" next-id="456"]Why you might want to start at the previous part.[/multipart]</pre>'
		. '<ul>'
		. '<li><code>prev-id</code> / <code>next-id</code> — ' . esc_html__('the post ID to link. On each side an id wins over a url. The target must be published — a draft or scheduled post keeps that side hidden until it goes live, so a series can be linked up ahead of time.', 'spudworks-mag') . '</li>'
		. '<li><code>prev-url</code> / <code>next-url</code> — ' . esc_html__('a linked URL (optional), used only when no id is given — e.g. for a link off-site.', 'spudworks-mag') . '</li>'
		. '<li><code>prev-link</code> / <code>next-link</code> — ' . esc_html__('the link text. With an id, defaults to that post\'s title; with a url, defaults to "Previous Post" / "Next Post".', 'spudworks-mag') . '</li>'
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
		. '</ul>'
		. '<h3>[pullquote]</h3>'
		. '<p>' . esc_html__('Pulls a phrase from your own copy into an oversized callout. By default it is centred and breaks out wider than the text column; align="left" or "right" floats it so the surrounding text wraps alongside (on wide screens — it recentres on smaller ones).', 'spudworks-mag') . '</p>'
		. '<pre>[pullquote]A line worth shouting about.[/pullquote]
[pullquote align="right" attribution="Jane Doe"]A line worth shouting about.[/pullquote]</pre>'
		. '<ul>'
		. '<li><code>align</code> — ' . esc_html__('center (default), left, or right.', 'spudworks-mag') . '</li>'
		. '<li><code>attribution</code> — ' . esc_html__('optional; shown right-aligned below the quote behind an em dash.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[toc]</h3>'
		. '<p>' . esc_html__('Builds a linked table of contents from the post\'s H2 and H3 headings, in a right-floated box the text wraps around (it becomes a full-width inset block on phones). The text between the tags is the box heading; leave it empty to default to "Contents". Place it near the top of the post.', 'spudworks-mag') . '</p>'
		. '<pre>[toc]In this collection[/toc]</pre>'
		. '<ul>'
		. '<li>' . esc_html__('The list is generated automatically; add H2/H3 headings in the post body and they appear as entries (sub-entries for H3), keeping any italics.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[aside]</h3>'
		. '<p>' . esc_html__('A short side note in a small box floated to the right of the column, which the body text wraps around (a third of the column on desktop, half on tablets, full width on phones). The type is smaller and dimmer than body copy so readers can skip it.', 'spudworks-mag') . '</p>'
		. '<pre>[aside]A quick note off to the side.[/aside]
[aside title="On the Mk II"]A quick note off to the side.[/aside]</pre>'
		. '<ul>'
		. '<li><code>title</code> — ' . esc_html__('the box heading. Defaults to "An Aside"; use title="" for no heading.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[necro-card]</h3>'
		. '<p>' . esc_html__('A Necromunda-style fighter stat card. name is the only required attribute; role and value show an em dash when left out. The wargear/skills lines and the weapons table are omitted entirely when there\'s nothing to show. Place cards back to back and they auto-group into a centred, wrapping row — no extra markup needed.', 'spudworks-mag') . '</p>'
		. '<pre>[necro-card size="full" name="Trevor" role="Forge Boss (Champion)" value="160"
    stat-m="4" stat-ws="3+" stat-bs="3+" stat-s="3" stat-t="4" stat-w="2" stat-i="3" stat-a="2" stat-sv="5+"
    stat-ld="7" stat-cl="8" stat-wil="6" stat-int="6" stat-xp="37"
    wargear="Respirator" skills="Inspiring, Nerves of Steel"]
[necro-weapon name="Power hammer" stat-sr="E" stat-lr="-" stat-s="S" stat-ap="-2" stat-l="2" traits="Breaching (6+), Concussive (6+), Melee"/]
[necro-weapon name="Stub gun" stat-sr="6" stat-lr="12" stat-s="3" stat-ap="-" stat-l="1" traits="Light"/]
[/necro-card]</pre>'
		. '<ul>'
		. '<li><code>size</code> — ' . esc_html__('full (default) or half, of the content column. Half collapses to full on narrow screens.', 'spudworks-mag') . '</li>'
		. '<li><code>name</code> / <code>role</code> / <code>value</code> — ' . esc_html__('the fighter\'s name (required), role, and credit cost.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Primary row: stat-m, stat-ws, stat-bs, stat-s, stat-t, stat-w, stat-i, stat-a, stat-sv.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Secondary row: stat-ld, stat-cl, stat-wil, stat-int, stat-xp.', 'spudworks-mag') . '</li>'
		. '<li><code>wargear</code> / <code>skills</code> — ' . esc_html__('optional comma-separated lists.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[necro-weapon]</h3>'
		. '<p>' . esc_html__('One weapon line, nested inside [necro-card] — it only does anything there. Self-closing; every attribute is optional and left blank when omitted.', 'spudworks-mag') . '</p>'
		. '<pre>[necro-weapon name="Stub gun" stat-sr="6" stat-lr="12" stat-s="3" stat-ap="-" stat-l="1" traits="Light"/]</pre>'
		. '<ul>'
		. '<li><code>stat-sr</code> / <code>stat-lr</code> — ' . esc_html__('short/long range. A plain whole number gets a trailing " (e.g. 6 becomes 6"); anything else (e.g. "E" for engage) is left as-is.', 'spudworks-mag') . '</li>'
		. '<li><code>stat-s</code> / <code>stat-ap</code> / <code>stat-l</code> — ' . esc_html__('strength, armour piercing, and load/ammo.', 'spudworks-mag') . '</li>'
		. '<li><code>traits</code> — ' . esc_html__('optional comma-separated list.', 'spudworks-mag') . '</li>'
		. '</ul>'
		. '<h3>[archive-feature]</h3>'
		. '<p>' . esc_html__('A large graphical link box for a hand-built archive page — image, headline, date, and optional intro text. Every attribute is optional. With link-id, the url/headline/date/image all default from that post; any other attribute overrides just that one piece. link-id must point at a published post or the whole box renders nothing, the same rule [multipart]\'s prev-id/next-id uses — so a run of these can be built ahead of a publish schedule and light up on their own. Without link-id there\'s no post to pull a date from, so the date line is simply left out.', 'spudworks-mag') . '</p>'
		. '<pre>[archive-feature link-id="123"]A line introducing this piece.[/archive-feature]
[archive-feature link-url="https://…" link-text="An external piece" img-url="https://…"]No post behind this one.[/archive-feature]</pre>'
		. '<ul>'
		. '<li><code>link-id</code> — ' . esc_html__('a post ID. Wins over link-url/link-text; must be published.', 'spudworks-mag') . '</li>'
		. '<li><code>link-url</code> / <code>link-text</code> — ' . esc_html__('the URL and headline, used directly when there\'s no link-id, or as an override when there is.', 'spudworks-mag') . '</li>'
		. '<li><code>img-url</code> — ' . esc_html__('shows that image as-is (no thumbnail processing). Takes priority over img-id.', 'spudworks-mag') . '</li>'
		. '<li><code>img-id</code> — ' . esc_html__('a media library attachment ID, shown at the proper cropped thumbnail size.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('With neither img-url nor img-id, link-id\'s own featured image is used; with no image at all, a hazard-striped placeholder fills the slot.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Text between the tags is an optional line introducing the piece.', 'spudworks-mag') . '</li>'
		. '</ul>';

	$screen->add_help_tab(array(
		'id'      => 'spudmag-shortcodes',
		'title'   => __('spudWorks Shortcodes', 'spudworks-mag'),
		'content' => $help
	));

	$separators = '<p>' . esc_html__('A Separator block normally renders as the "sW" hazard divider. Two opt-in animated alternatives are also available, one per fictional universe.', 'spudworks-mag') . '</p>'
		. '<h3>' . esc_html__('Orbit separator', 'spudworks-mag') . '</h3>'
		. '<p>' . esc_html__('An animated gas giant with a moon crossing it. Add a Separator block, open Advanced in the block sidebar, and put "orbit" in Additional CSS class(es). In the Text editor, write it directly:', 'spudworks-mag') . '</p>'
		. '<pre>&lt;hr class="orbit"&gt;</pre>'
		. '<h3>' . esc_html__('URS separator', 'spudworks-mag') . '</h3>'
		. '<p>' . esc_html__('A rocket streaking past the Unified Republic of Stars name over a starfield. Two sizes — use "urs" for the full name, or "urs-micro" for a smaller, quieter mark that just reads "URS":', 'spudworks-mag') . '</p>'
		. '<pre>&lt;hr class="urs"&gt;
&lt;hr class="urs-micro"&gt;</pre>'
		. '<ul>'
		. '<li>' . esc_html__('Opt-in per separator — every other <hr> keeps the sW divider, including inside fiction posts.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Works in any post, not just fiction ones.', 'spudworks-mag') . '</li>'
		. '<li>' . esc_html__('Readers who ask their system for reduced motion get a still version instead.', 'spudworks-mag') . '</li>'
		. '</ul>';

	$screen->add_help_tab(array(
		'id'      => 'spudmag-separators',
		'title'   => __('spudWorks Separators', 'spudworks-mag'),
		'content' => $separators
	));
}
add_action('current_screen', 'spudmagEditorHelpTab');
