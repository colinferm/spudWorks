<?php
/**
 * Social + SEO meta tags for the document head.
 *
 * Emits a sensible <meta name="description">, a canonical link, Open Graph tags
 * (Facebook, LinkedIn, Slack, Discord, iMessage previews, etc.), and Twitter/X
 * Card tags. The Twitter card type switches to summary_large_image when a
 * representative image is available and falls back to the compact summary card
 * when none is.
 *
 * Multisite-safe: every URL and option is resolved through per-site WP APIs
 * (no hardcoded paths or table names). Hooked into wp_head, so header.php stays
 * clean and the tags follow whatever the current query happens to be.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Print one <meta> tag with an escaped attribute value. Empty content is
 * skipped so optional fields simply fall away.
 */
function spudmagMetaTag($attr, $name, $content) {
	if ($content === '' || $content === null) {
		return;
	}
	printf("\t<meta %s=\"%s\" content=\"%s\">\n", $attr, esc_attr($name), esc_attr($content));
}

/**
 * Print one <meta> tag whose content is a URL (escaped with esc_url).
 */
function spudmagMetaUrlTag($attr, $name, $url) {
	if (!$url) {
		return;
	}
	printf("\t<meta %s=\"%s\" content=\"%s\">\n", $attr, esc_attr($name), esc_url($url));
}

/**
 * Collapse markup and whitespace, then clamp to a tidy meta-description length.
 */
function spudmagMetaTrim($text, $length = 200) {
	$text = wp_strip_all_tags(strip_shortcodes((string) $text));
	$text = trim(preg_replace('/\s+/', ' ', $text));

	if (mb_strlen($text) > $length) {
		$text = rtrim(mb_substr($text, 0, $length - 1));
		$text .= "\u{2026}";
	}

	return $text;
}

/**
 * Resolve a representative image for sharing: the post's featured image first,
 * then the site icon as a brand-mark fallback. Returns
 * array(url, width, height, alt) or null when nothing suitable exists.
 */
function spudmagMetaImage($postId = 0) {
	if ($postId && has_post_thumbnail($postId)) {
		$thumbId = get_post_thumbnail_id($postId);
		$src     = wp_get_attachment_image_src($thumbId, 'spudmagHero');

		if ($src) {
			$alt = trim((string) get_post_meta($thumbId, '_wp_attachment_image_alt', true));

			return array(
				'url'    => $src[0],
				'width'  => $src[1],
				'height' => $src[2],
				'alt'    => $alt !== '' ? $alt : get_the_title($postId)
			);
		}
	}

	// Site-wide fallback so shares still carry the brand mark when one is set.
	$iconUrl = get_site_icon_url(512);

	if ($iconUrl) {
		return array(
			'url'    => $iconUrl,
			'width'  => 512,
			'height' => 512,
			'alt'    => get_bloginfo('name')
		);
	}

	return null;
}

/**
 * Emit the full set of SEO + social tags, tailored to the current view.
 */
function spudmagMetaTags() {
	// Feeds and oEmbed iframes don't want this markup.
	if (is_feed() || is_embed()) {
		return;
	}

	$siteName = get_bloginfo('name');
	$locale   = get_locale();

	$title       = '';
	$description = '';
	$url         = '';
	$ogType      = 'website';
	$postId      = 0;

	if (is_singular()) {
		$postId      = get_queried_object_id();
		$title       = get_the_title($postId);
		$url         = get_permalink($postId);
		$ogType      = is_singular('post') ? 'article' : 'website';
		$description = spudmagMetaTrim(spudmagGetSubhead($postId));
	} elseif (is_front_page() || is_home()) {
		$title       = $siteName;
		$url         = home_url('/');
		$description = spudmagMetaTrim(get_bloginfo('description'));
	} elseif (is_category() || is_tag() || is_tax()) {
		$term        = get_queried_object();
		$termUrl     = $term ? get_term_link($term) : '';
		$title       = single_term_title('', false);
		$url         = (!$termUrl || is_wp_error($termUrl)) ? home_url('/') : $termUrl;
		$description = spudmagMetaTrim(
			($term && $term->description) ? $term->description : sprintf(__('Stories filed under %s.', 'spudworks-mag'), $title)
		);
	} elseif (is_author()) {
		$author      = get_queried_object();
		$title       = $author ? $author->display_name : '';
		$url         = $author ? get_author_posts_url($author->ID) : home_url('/');
		$description = spudmagMetaTrim($author ? get_the_author_meta('description', $author->ID) : '');
	} elseif (is_search()) {
		$title       = sprintf(__('Search results for "%s"', 'spudworks-mag'), get_search_query());
		$url         = home_url('/?s=' . rawurlencode(get_search_query()));
	} else {
		// Date archives and other edge views: title only, no canonical/og:url so
		// we never assert a wrong canonical URL.
		$title       = wp_get_document_title();
	}

	if ($description === '') {
		$description = spudmagMetaTrim(get_bloginfo('description'));
	}

	$image = spudmagMetaImage($postId);

	echo "\n\t<!-- spudWorks Magazine: social + SEO meta -->\n";

	// --- Standard SEO ---
	spudmagMetaTag('name', 'description', $description);
	if ($url) {
		printf("\t<link rel=\"canonical\" href=\"%s\">\n", esc_url($url));
	}

	// --- Open Graph ---
	spudmagMetaTag('property', 'og:locale', $locale);
	spudmagMetaTag('property', 'og:site_name', $siteName);
	spudmagMetaTag('property', 'og:type', $ogType);
	spudmagMetaTag('property', 'og:title', $title);
	spudmagMetaTag('property', 'og:description', $description);
	spudmagMetaUrlTag('property', 'og:url', $url);

	if ($image) {
		spudmagMetaUrlTag('property', 'og:image', $image['url']);
		spudmagMetaUrlTag('property', 'og:image:secure_url', $image['url']);
		spudmagMetaTag('property', 'og:image:width', $image['width']);
		spudmagMetaTag('property', 'og:image:height', $image['height']);
		spudmagMetaTag('property', 'og:image:alt', $image['alt']);
	}

	// Article-specific Open Graph (posts only).
	if ($ogType === 'article' && $postId) {
		spudmagMetaTag('property', 'article:published_time', get_the_date('c', $postId));
		spudmagMetaTag('property', 'article:modified_time', get_the_modified_date('c', $postId));

		$authorId = (int) get_post_field('post_author', $postId);
		if ($authorId) {
			spudmagMetaUrlTag('property', 'article:author', get_author_posts_url($authorId));
		}

		$cats = get_the_category($postId);
		if (!empty($cats)) {
			spudmagMetaTag('property', 'article:section', $cats[0]->name);
		}

		$tags = get_the_tags($postId);
		if (!empty($tags) && !is_wp_error($tags)) {
			foreach ($tags as $tag) {
				spudmagMetaTag('property', 'article:tag', $tag->name);
			}
		}
	}

	// --- Twitter / X Cards ---
	// summary_large_image when an image is present; the compact summary card
	// (no large image area) when there isn't one.
	spudmagMetaTag('name', 'twitter:card', $image ? 'summary_large_image' : 'summary');

	// Optional @handles - filterable so they can be set without editing here.
	spudmagMetaTag('name', 'twitter:site', apply_filters('spudmagTwitterSite', ''));
	spudmagMetaTag('name', 'twitter:creator', apply_filters('spudmagTwitterCreator', ''));

	spudmagMetaTag('name', 'twitter:title', $title);
	spudmagMetaTag('name', 'twitter:description', $description);

	if ($image) {
		spudmagMetaUrlTag('name', 'twitter:image', $image['url']);
		spudmagMetaTag('name', 'twitter:image:alt', $image['alt']);
	}

	echo "\n";
}
add_action('wp_head', 'spudmagMetaTags', 5);

// We print our own canonical (and on more view types than core does), so drop
// core's singular-only rel=canonical to avoid emitting two of them.
remove_action('wp_head', 'rel_canonical');
