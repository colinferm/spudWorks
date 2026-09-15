<?php
/**
 * spudWorks Magazine — display + query helpers.
 *
 * These are deliberately built on WP_Query and the core meta/term APIs rather
 * than raw SQL so the theme behaves correctly on any subsite of a multisite
 * network and degrades gracefully against arbitrary content.
 */

/**
 * One "beat" of the varied card rhythm used by the home grid and archives.
 * Cycling this across a list of posts yields an irregular, hand-composed
 * layout rather than a uniform matrix. Returns array('tpl' => ..., 'span' => ...).
 * Spans only take effect at the 6-column desktop breakpoint.
 */
function spudmagCardBeat($index) {
	$rhythm = array(
		array('tpl' => 'card-overlay', 'span' => 'span-3 row-2'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-overlay', 'span' => 'span-2'),
		array('tpl' => 'card-overlay',   'span' => 'span-2'),
		array('tpl' => 'card-overlay',   'span' => 'span-2'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-overlay', 'span' => 'span-3 row-2'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-overlay', 'span' => 'span-3'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-under',   'span' => 'span-3'),
		array('tpl' => 'card-overlay', 'span' => 'span-3')
	);
	return $rhythm[$index % count($rhythm)];
}

/**
 * The chosen content template for a post: 'default', 'fiction', or 'showcase'.
 */
function spudmagGetTemplate($postId) {
	$value = get_post_meta($postId, METATEMPLATE, true);
	$allowed = array('default', 'fiction', 'showcase');
	return in_array($value, $allowed, true) ? $value : 'showcase';
}

/**
 * The chosen page template for a page: 'default' or 'landing'. Unlike posts
 * (spudmagGetTemplate() defaults unset to 'showcase'), an unset page always
 * falls back to 'default' so every existing page keeps its current look.
 */
function spudmagGetPageTemplate($postId) {
	$value = get_post_meta($postId, METAPAGETEMPLATE, true);
	$allowed = array('default', 'landing');
	return in_array($value, $allowed, true) ? $value : 'default';
}

/**
 * The two Landing Page sidebar galleries. Always returns exactly two entries
 * — array('title' => string, 'ids' => int[], 'columns' => int 1-6) — so
 * callers never need to check array shape, only whether 'ids' is empty.
 */
function spudmagGetPageGalleries($postId) {
	$stored = get_post_meta($postId, METAGALLERIES, true);
	$stored = is_array($stored) ? $stored : array();

	$galleries = array();
	for ($i = 0; $i < 2; $i++) {
		$entry = isset($stored[$i]) && is_array($stored[$i]) ? $stored[$i] : array();
		$ids = isset($entry['ids']) && is_array($entry['ids']) ? array_map('intval', $entry['ids']) : array();
		$columns = isset($entry['columns']) ? (int) $entry['columns'] : 2;
		$galleries[] = array(
			'title'   => isset($entry['title']) ? (string) $entry['title'] : '',
			'ids'     => array_values(array_filter($ids)),
			'columns' => max(1, min(6, $columns ?: 2))
		);
	}
	return $galleries;
}

/**
 * The hand-picked "related articles" list for a Landing Page, in curator
 * order. Filters to posts that are still published, so a later unpublish or
 * delete quietly drops the entry instead of leaving a dead card.
 *
 * @return int[]
 */
function spudmagGetPageRelated($postId) {
	$stored = get_post_meta($postId, METARELATED, true);
	$stored = is_array($stored) ? $stored : array();

	$related = array();
	foreach ($stored as $id) {
		$id = (int) $id;
		if ($id > 0 && get_post_status($id) === 'publish' && !in_array($id, $related, true)) {
			$related[] = $id;
		}
	}
	return $related;
}

/**
 * The heading for a Landing Page's related-articles section. Falls back to a
 * sensible default when left blank.
 */
function spudmagGetPageRelatedTitle($postId) {
	$title = trim((string) get_post_meta($postId, METARELATEDTITLE, true));
	return $title !== '' ? $title : __('Related Articles', 'spudworks-mag');
}

/**
 * The optional secondary headline for a post (empty string if none).
 */
function spudmagGetSubhead($postId) {
	return trim((string) get_post_meta($postId, METASUBHEAD, true));
}

/**
 * Whether a post is pinned to the top of the home page.
 */
function spudmagIsPinned($postId) {
	return (bool) get_post_meta($postId, METAPINNED, true);
}

/**
 * The review rating for a post on a 0-5 scale (0.0 when unset/blank). Only
 * meaningful for posts in the Reviews category; used to emit a schema Review.
 */
function spudmagGetRating($postId) {
	$value = get_post_meta($postId, METARATING, true);
	if ($value === '' || $value === null) {
		return 0.0;
	}
	return max(0.0, min(5.0, (float) $value));
}

/**
 * The name of the thing a review is about (empty string if none).
 */
function spudmagGetReviewItem($postId) {
	return trim((string) get_post_meta($postId, METAREVIEWITEM, true));
}

/**
 * The schema.org @types Google accepts for a Review's itemReviewed (a subset of
 * the broader schema vocabulary — plain "Thing" is valid schema but Google
 * rejects it for review snippets). Key = the @type emitted in the JSON-LD;
 * value = the label shown in the editor. Ordered alphabetically by label.
 */
function spudmagReviewTypes() {
	return array(
		'SoftwareApplication' => __('App', 'spudworks-mag'),
		'Book'                => __('Book', 'spudworks-mag'),
		'BookSeries'          => __('Book Series', 'spudworks-mag'),
		'Course'              => __('Course List', 'spudworks-mag'),
		'Game'                => __('Game', 'spudworks-mag'),
		'Movie'               => __('Movie', 'spudworks-mag'),
		'MovieSeries'         => __('Movie Series', 'spudworks-mag'),
		'PodcastEpisode'      => __('Podcast Episode', 'spudworks-mag'),
		'PodcastSeason'       => __('Podcast Season', 'spudworks-mag'),
		'PodcastSeries'       => __('Podcast Series', 'spudworks-mag'),
		'Product'             => __('Product', 'spudworks-mag'),
		'TVEpisode'           => __('TV Episode', 'spudworks-mag'),
		'TVSeason'            => __('TV Season', 'spudworks-mag'),
		'TVSeries'            => __('TV Series', 'spudworks-mag'),
		'VideoGame'           => __('Video Game', 'spudworks-mag'),
		'VideoGameSeries'     => __('Video Game Series', 'spudworks-mag')
	);
}

/**
 * The chosen itemReviewed @type for a post, validated against the allowed list.
 * Falls back to the generic-but-Google-valid 'Product' when nothing valid is set.
 */
function spudmagGetReviewType($postId) {
	$value = (string) get_post_meta($postId, METAREVIEWTYPE, true);
	return array_key_exists($value, spudmagReviewTypes()) ? $value : 'Product';
}

/**
 * A post's primary category: the first real (non-"uncategorized") category,
 * which drives the breadcrumb trail and schema articleSection. Returns a
 * WP_Term or null when the post has only the default category.
 */
function spudmagPrimaryCategory($postId) {
	$cats = get_the_category($postId);
	if (!is_array($cats)) {
		return null;
	}
	foreach ($cats as $cat) {
		if ($cat->slug !== 'uncategorized') {
			return $cat;
		}
	}
	return null;
}

/**
 * Sort terms by popularity (post count, most first); ties break by name so the
 * order is stable wherever this runs.
 */
function spudmagTermCountSort($a, $b) {
	if ($a->count === $b->count) {
		return strcasecmp($a->name, $b->name);
	}
	return $b->count - $a->count;
}

/**
 * Output up to $max tag/category "chips" for a post:
 *   1. the post's single most popular category first (by post count, skipping
 *      the default "uncategorized");
 *   2. then its tags ordered by popularity (post count, most first).
 * So a 3-chip card shows the top category + the two most popular tags, and the
 * under-post list uses the same popularity order. Renders nothing with no terms.
 */
function spudmagTermChips($postId, $max = 3) {
	$chips = array();

	// Lead with the most popular real category.
	$cats = get_the_category($postId);
	if (is_array($cats)) {
		$cats = array_filter($cats, function($cat) {
			return $cat->slug !== 'uncategorized';
		});
		if (!empty($cats)) {
			usort($cats, 'spudmagTermCountSort');
			$chips[] = $cats[0];
		}
	}

	// Then tags, most popular first.
	$tags = get_the_tags($postId);
	if (is_array($tags)) {
		usort($tags, 'spudmagTermCountSort');
		$chips = array_merge($chips, $tags);
	}

	$chips = array_slice($chips, 0, $max);
	if (empty($chips)) {
		return;
	}

	echo '<ul class="term-chips">';
	foreach ($chips as $term) {
		$link = get_term_link($term);
		if (is_wp_error($link)) {
			continue;
		}
		echo '<li><a href="' . esc_url($link) . '">' . esc_html($term->name) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Whether a byline should be shown for this post. The design hides bylines
 * gracefully; an author with no public display name suppresses it.
 */
function spudmagHasByline($postId) {
	$author = (int) get_post_field('post_author', $postId);
	return $author > 0 && get_the_author_meta('display_name', $author) !== '';
}

/**
 * Posts for the home grid: the pinned post (if any) is returned first,
 * followed by the most recent posts, excluding the pinned one.
 *
 * Returns an array with keys 'pinned' (WP_Post|null) and 'items' (WP_Post[]).
 */
function spudmagHomeItems($count = 12) {
	$pinned = null;

	$pinnedQuery = new WP_Query(array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
		'meta_key'            => METAPINNED,
		'meta_value'          => '1',
		'no_found_rows'       => true
	));
	if ($pinnedQuery->have_posts()) {
		$pinned = $pinnedQuery->posts[0];
	}
	wp_reset_postdata();

	$exclude = $pinned ? array($pinned->ID) : array();

	$itemsQuery = new WP_Query(array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'post__not_in'        => $exclude,
		'no_found_rows'       => true
	));
	$items = $itemsQuery->posts;
	wp_reset_postdata();

	return array('pinned' => $pinned, 'items' => $items);
}

/**
 * Related stories for a post, ranked tags-first.
 *
 * Candidates are gathered by shared tags (then shared categories) and scored by
 * how many tags they share; ties break toward more recent posts. Falls back to
 * recent posts in the same categories when there is no tag overlap.
 *
 * @return WP_Post[]
 */
function spudmagRelatedStories($postId, $count = 4) {
	$tagIds = wp_get_post_tags($postId, array('fields' => 'ids'));
	$catIds = wp_get_post_categories($postId, array('fields' => 'ids'));

	$candidates = array();

	if (!empty($tagIds)) {
		$byTag = new WP_Query(array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 20,
			'ignore_sticky_posts' => true,
			'post__not_in'        => array($postId),
			'tag__in'             => $tagIds,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true
		));
		foreach ($byTag->posts as $candidate) {
			$shared = array_intersect($tagIds, wp_get_post_tags($candidate->ID, array('fields' => 'ids')));
			$candidates[$candidate->ID] = array('post' => $candidate, 'score' => count($shared));
		}
		wp_reset_postdata();
	}

	if (count($candidates) < $count && !empty($catIds)) {
		$byCat = new WP_Query(array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 20,
			'ignore_sticky_posts' => true,
			'post__not_in'        => array_merge(array($postId), array_keys($candidates)),
			'category__in'        => $catIds,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true
		));
		foreach ($byCat->posts as $candidate) {
			$candidates[$candidate->ID] = array('post' => $candidate, 'score' => 0);
		}
		wp_reset_postdata();
	}

	// Sort by tag-overlap score, then by recency.
	uasort($candidates, function($a, $b) {
		if ($a['score'] !== $b['score']) {
			return $b['score'] - $a['score'];
		}
		return strcmp($b['post']->post_date, $a['post']->post_date);
	});

	$related = array();
	foreach ($candidates as $entry) {
		$related[] = $entry['post'];
		if (count($related) >= $count) {
			break;
		}
	}
	return $related;
}
