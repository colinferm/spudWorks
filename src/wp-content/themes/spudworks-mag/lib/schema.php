<?php
/**
 * schema.org structured data (JSON-LD) for the document head.
 *
 * Emits a single <script type="application/ld+json"> holding one connected
 * @graph whose nodes reference each other by @id. Every view gets the sitewide
 * Organization (publisher) and WebSite (with a Sitelinks SearchAction); on top
 * of that:
 *
 *   - posts        -> Article (or Review for the Reviews category) + WebPage +
 *                     author Person + primary ImageObject + BreadcrumbList
 *   - pages        -> WebPage (AboutPage for /about) + BreadcrumbList
 *   - front/blog   -> CollectionPage + ItemList of the home posts
 *   - archives     -> CollectionPage + ItemList + BreadcrumbList
 *   - author       -> ProfilePage + Person + ItemList
 *   - search       -> SearchResultsPage
 *
 * Multisite-safe: all URLs/options come from per-site WP APIs. Reuses the meta
 * helpers (spudmagMetaImage / spudmagMetaTrim) from lib/meta-tags.php so the
 * description and share image stay consistent with the social tags. Hooked to
 * wp_head at priority 6, right after the social/SEO meta block.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Stable @id anchors, all rooted at the per-site home URL.
 */
function spudmagSchemaId($fragment) {
	return home_url('/#' . ltrim($fragment, '#'));
}

/**
 * The Organization that publishes the site (the schema "publisher"). Logo is
 * the WP Site Icon when one is set, which Google uses for Article rich results.
 */
function spudmagSchemaOrganization() {
	$org = array(
		'@type' => 'Organization',
		'@id'   => spudmagSchemaId('organization'),
		'name'  => get_bloginfo('name'),
		'url'   => home_url('/')
	);

	$iconUrl = get_site_icon_url(512);
	if ($iconUrl) {
		$org['logo'] = array(
			'@type'  => 'ImageObject',
			'@id'    => spudmagSchemaId('logo'),
			'url'    => $iconUrl,
			'width'  => 512,
			'height' => 512,
			'caption' => get_bloginfo('name')
		);
		// image -> logo lets consumers treat the logo as the org's image too.
		$org['image'] = array('@id' => spudmagSchemaId('logo'));
	}

	return $org;
}

/**
 * The WebSite node, with a Sitelinks SearchAction pointing at the search query.
 */
function spudmagSchemaWebSite() {
	return array(
		'@type'       => 'WebSite',
		'@id'         => spudmagSchemaId('website'),
		'url'         => home_url('/'),
		'name'        => get_bloginfo('name'),
		'description' => get_bloginfo('description'),
		'publisher'   => array('@id' => spudmagSchemaId('organization')),
		'inLanguage'  => get_bloginfo('language'),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url('/?s={search_term_string}')
			),
			'query-input' => 'required name=search_term_string'
		)
	);
}

/**
 * A Person node for a post/site author. @id is keyed off the author archive so
 * the same author is one shared node across the site.
 */
function spudmagSchemaPerson($userId) {
	$person = array(
		'@type' => 'Person',
		'@id'   => get_author_posts_url($userId) . '#author',
		'name'  => get_the_author_meta('display_name', $userId),
		'url'   => get_author_posts_url($userId)
	);

	$bio = trim((string) get_the_author_meta('description', $userId));
	if ($bio !== '') {
		$person['description'] = spudmagMetaTrim($bio, 300);
	}

	$avatar = get_avatar_url($userId, array('size' => 192));
	if ($avatar) {
		$person['image'] = array(
			'@type' => 'ImageObject',
			'url'   => $avatar,
			'width' => 192,
			'height' => 192,
			'caption' => get_the_author_meta('display_name', $userId)
		);
	}

	return $person;
}

/**
 * An ImageObject for a post's featured image (using the spudmagMetaImage data),
 * or null when the post has no representative image.
 */
function spudmagSchemaPrimaryImage($postId, $anchorUrl) {
	$image = spudmagMetaImage($postId);
	if (!$image) {
		return null;
	}
	return array(
		'@type'   => 'ImageObject',
		'@id'     => $anchorUrl . '#primaryimage',
		'url'     => $image['url'],
		'width'   => $image['width'],
		'height'  => $image['height'],
		'caption' => $image['alt']
	);
}

/**
 * Build a BreadcrumbList from an ordered array of array('name'=>, 'url'=>).
 * The last item omits its URL (it's the current page).
 */
function spudmagSchemaBreadcrumb($anchorUrl, $crumbs) {
	$items = array();
	$count = count($crumbs);
	foreach ($crumbs as $i => $crumb) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb['name']
		);
		// All but the current (last) item carry their URL.
		if ($i < $count - 1 && !empty($crumb['url'])) {
			$item['item'] = $crumb['url'];
		}
		$items[] = $item;
	}
	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $anchorUrl . '#breadcrumb',
		'itemListElement' => $items
	);
}

/**
 * Turn a list of WP_Post objects into an ItemList of links (for collection and
 * profile pages).
 */
function spudmagSchemaItemList($anchorUrl, $posts) {
	$items = array();
	foreach ($posts as $i => $post) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => get_permalink($post),
			'name'     => get_the_title($post)
		);
	}
	return array(
		'@type'           => 'ItemList',
		'@id'             => $anchorUrl . '#itemlist',
		'itemListElement' => $items
	);
}

/**
 * Breadcrumb trail for a singular post: Home -> primary category -> post.
 */
function spudmagSchemaPostCrumbs($postId) {
	$crumbs = array(array('name' => __('Home', 'spudworks-mag'), 'url' => home_url('/')));

	$cat = spudmagPrimaryCategory($postId);
	if ($cat) {
		$link = get_term_link($cat);
		if (!is_wp_error($link)) {
			$crumbs[] = array('name' => $cat->name, 'url' => $link);
		}
	}

	$crumbs[] = array('name' => get_the_title($postId), 'url' => get_permalink($postId));
	return $crumbs;
}

/**
 * The Article (or Review) node for a singular post, wired to the WebPage,
 * author, publisher, and primary image already placed in the graph.
 */
function spudmagSchemaArticle($postId, $anchorUrl, $hasImage) {
	$authorId = (int) get_post_field('post_author', $postId);
	$isReview = has_category('reviews', $postId);

	$node = array(
		'@type'            => $isReview ? 'Review' : 'Article',
		'@id'              => $anchorUrl . '#article',
		'isPartOf'         => array('@id' => $anchorUrl . '#webpage'),
		'mainEntityOfPage' => array('@id' => $anchorUrl . '#webpage'),
		'headline'         => get_the_title($postId),
		'datePublished'    => get_the_date('c', $postId),
		'dateModified'     => get_the_modified_date('c', $postId),
		'author'           => array('@id' => get_author_posts_url($authorId) . '#author'),
		'publisher'        => array('@id' => spudmagSchemaId('organization')),
		'inLanguage'       => get_bloginfo('language'),
		'url'              => $anchorUrl
	);

	$subhead = spudmagGetSubhead($postId);
	if ($subhead !== '') {
		$node['alternativeHeadline'] = wp_strip_all_tags($subhead);
	}

	$description = spudmagMetaTrim(get_the_excerpt($postId));
	if ($description !== '') {
		$node['description'] = $description;
	}

	if ($hasImage) {
		$node['image'] = array('@id' => $anchorUrl . '#primaryimage');
	}

	$cat = spudmagPrimaryCategory($postId);
	if ($cat) {
		$node['articleSection'] = $cat->name;
	}

	$tags = get_the_tags($postId);
	if (!empty($tags) && !is_wp_error($tags)) {
		$node['keywords'] = wp_list_pluck($tags, 'name');
	}

	// Fiction posts (by template or category) are creative writing.
	if (spudmagGetTemplate($postId) === 'fiction' || has_category('fiction', $postId)) {
		$node['genre'] = 'Fiction';
	}

	$wordCount = str_word_count(wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $postId))));
	if ($wordCount > 0) {
		$node['wordCount'] = $wordCount;
	}

	$comments = (int) get_comments_number($postId);
	if ($comments > 0) {
		$node['commentCount'] = $comments;
	}

	// Reviews carry a rating + the thing reviewed when the editor filled them in.
	if ($isReview) {
		$rating = spudmagGetRating($postId);
		if ($rating > 0) {
			$node['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 0
			);
		}
		$item = spudmagGetReviewItem($postId);
		$node['itemReviewed'] = array(
			// A Google-accepted itemReviewed @type (editor-chosen), not bare "Thing".
			'@type' => spudmagGetReviewType($postId),
			'name'  => $item !== '' ? $item : get_the_title($postId)
		);
	}

	return $node;
}

/**
 * Assemble and print the JSON-LD @graph for the current view.
 */
function spudmagSchemaGraph() {
	if (is_feed() || is_embed()) {
		return;
	}

	$graph = array(
		spudmagSchemaOrganization(),
		spudmagSchemaWebSite()
	);
	$orgRef     = array('@id' => spudmagSchemaId('organization'));
	$websiteRef = array('@id' => spudmagSchemaId('website'));
	$language   = get_bloginfo('language');

	if (is_singular('post')) {
		$postId    = get_queried_object_id();
		$anchorUrl = get_permalink($postId);
		$authorId  = (int) get_post_field('post_author', $postId);

		$primaryImage = spudmagSchemaPrimaryImage($postId, $anchorUrl);
		$hasImage     = (bool) $primaryImage;

		$webpage = array(
			'@type'      => 'WebPage',
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => get_the_title($postId),
			'isPartOf'   => $websiteRef,
			'breadcrumb' => array('@id' => $anchorUrl . '#breadcrumb'),
			'datePublished' => get_the_date('c', $postId),
			'dateModified'  => get_the_modified_date('c', $postId),
			'inLanguage'    => $language
		);
		if ($hasImage) {
			$webpage['primaryImageOfPage'] = array('@id' => $anchorUrl . '#primaryimage');
		}

		$graph[] = $webpage;
		$graph[] = spudmagSchemaBreadcrumb($anchorUrl, spudmagSchemaPostCrumbs($postId));
		if ($hasImage) {
			$graph[] = $primaryImage;
		}
		if ($authorId) {
			$graph[] = spudmagSchemaPerson($authorId);
		}
		$graph[] = spudmagSchemaArticle($postId, $anchorUrl, $hasImage);

	} elseif (is_page()) {
		$postId    = get_queried_object_id();
		$anchorUrl = get_permalink($postId);
		$post      = get_post($postId);
		$type      = ($post && $post->post_name === 'about') ? 'AboutPage' : 'WebPage';

		$graph[] = array(
			'@type'      => $type,
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => get_the_title($postId),
			'isPartOf'   => $websiteRef,
			'breadcrumb' => array('@id' => $anchorUrl . '#breadcrumb'),
			'inLanguage' => $language
		);
		$graph[] = spudmagSchemaBreadcrumb($anchorUrl, array(
			array('name' => __('Home', 'spudworks-mag'), 'url' => home_url('/')),
			array('name' => get_the_title($postId), 'url' => $anchorUrl)
		));

	} elseif (is_front_page() || is_home()) {
		$anchorUrl = home_url('/');
		$home      = spudmagHomeItems(12);
		$posts     = array_filter(array_merge(array($home['pinned']), $home['items']));

		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => get_bloginfo('name'),
			'description' => get_bloginfo('description'),
			'isPartOf'   => $websiteRef,
			'inLanguage' => $language,
			'mainEntity' => array('@id' => $anchorUrl . '#itemlist')
		);
		$graph[] = spudmagSchemaItemList($anchorUrl, $posts);

	} elseif (is_category() || is_tag() || is_tax() || is_date()) {
		$term  = get_queried_object();
		$title = wp_strip_all_tags(wp_get_document_title());
		$posts = isset($GLOBALS['wp_query']->posts) ? $GLOBALS['wp_query']->posts : array();

		// Canonical URL for the archive: the term link, or the date-archive link.
		if ($term instanceof WP_Term) {
			$termLink  = get_term_link($term);
			$anchorUrl = is_wp_error($termLink) ? home_url('/') : $termLink;
		} elseif (is_year()) {
			$anchorUrl = get_year_link(get_query_var('year'));
		} elseif (is_month()) {
			$anchorUrl = get_month_link(get_query_var('year'), get_query_var('monthnum'));
		} elseif (is_day()) {
			$anchorUrl = get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
		} else {
			$anchorUrl = home_url('/');
		}

		$collection = array(
			'@type'      => 'CollectionPage',
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => $title,
			'isPartOf'   => $websiteRef,
			'inLanguage' => $language,
			'breadcrumb' => array('@id' => $anchorUrl . '#breadcrumb')
		);
		if (!empty($posts)) {
			$collection['mainEntity'] = array('@id' => $anchorUrl . '#itemlist');
		}
		$graph[] = $collection;

		// Breadcrumb: Home -> (this archive).
		$crumbs = array(array('name' => __('Home', 'spudworks-mag'), 'url' => home_url('/')));
		if ($term && !empty($term->name)) {
			$crumbs[] = array('name' => $term->name, 'url' => $anchorUrl);
		} else {
			$crumbs[] = array('name' => $title, 'url' => $anchorUrl);
		}
		$graph[] = spudmagSchemaBreadcrumb($anchorUrl, $crumbs);

		if (!empty($posts)) {
			$graph[] = spudmagSchemaItemList($anchorUrl, $posts);
		}

	} elseif (is_author()) {
		$author    = get_queried_object();
		$authorId  = $author ? (int) $author->ID : 0;
		$anchorUrl = $authorId ? get_author_posts_url($authorId) : home_url('/');
		$posts     = isset($GLOBALS['wp_query']->posts) ? $GLOBALS['wp_query']->posts : array();

		if ($authorId) {
			$graph[] = spudmagSchemaPerson($authorId);
		}
		$profile = array(
			'@type'      => 'ProfilePage',
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => $author ? $author->display_name : '',
			'isPartOf'   => $websiteRef,
			'inLanguage' => $language
		);
		if ($authorId) {
			$profile['mainEntity'] = array('@id' => get_author_posts_url($authorId) . '#author');
		}
		if (!empty($posts)) {
			$profile['hasPart'] = array('@id' => $anchorUrl . '#itemlist');
			$graph[] = $profile;
			$graph[] = spudmagSchemaItemList($anchorUrl, $posts);
		} else {
			$graph[] = $profile;
		}

	} elseif (is_search()) {
		$anchorUrl = home_url('/?s=' . rawurlencode(get_search_query()));
		$graph[] = array(
			'@type'      => 'SearchResultsPage',
			'@id'        => $anchorUrl . '#webpage',
			'url'        => $anchorUrl,
			'name'       => wp_strip_all_tags(wp_get_document_title()),
			'isPartOf'   => $websiteRef,
			'inLanguage' => $language
		);
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph
	);

	// wp_json_encode keeps forward slashes escaped by default, so a stray
	// "</script>" inside any value can't break out of the script element.
	echo "\n<script type=\"application/ld+json\">\n"
		. wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		. "\n</script>\n";
}
add_action('wp_head', 'spudmagSchemaGraph', 6);
