<?php
/**
 * spudWorks Magazine - development/demo data cleanup.
 *
 * Removes the sample data added while building & reviewing the theme so the
 * site can be reset toward its pre-demo state. Safe to run more than once.
 *
 * Run it with WP-CLI:
 *     wp eval-file build/cleanup-dev-data.php
 *
 * Or, with the project's Docker stack, use the wrapper:
 *     build/cleanup-dev-data.sh
 *
 * What it removes:
 *   - All spudWorks Magazine post meta (template choice, secondary headline).
 *   - The demo featured images / gallery attachments (test-* and frontdoor* imports),
 *     and any _thumbnail_id references to them.
 *   - The image/gallery/pullquote/page-break blocks injected into the demo posts.
 *   - Tag assignments added to the demo posts (and the now-empty demo tags).
 *   - Widgets placed in the Showcase sidebar.
 *   - The demo author bio (only if it still matches the text we set).
 *
 * Post content edited by hand is matched by the demo upload path; if you have
 * since added real images under that path, review before running.
 */

if (!defined('ABSPATH')) {
	fwrite(STDERR, "This script must be run through WP-CLI (wp eval-file).\n");
	return;
}

$demoPostIds = array(253, 254, 255, 256, 257, 258);
$demoTagSlugs = array('necromunda', 'escher', 'miniatures');
$demoUploadMarker = '/uploads/'; // combined with the test- / frontdoor- match below
$demoBio = 'Colin writes fiction and paints small plastic gangers for the Necromunda underhive.';

// 1) Remove all spudWorks Magazine post meta.
delete_post_meta_by_key('_spudmag_template');
delete_post_meta_by_key('_spudmag_subhead');
echo "Removed spudmag post meta (template / subhead).\n";

// 2) Delete demo attachments and clear thumbnail references to them.
$attachments = get_posts(array(
	'post_type'      => 'attachment',
	'post_status'    => 'any',
	'numberposts'    => -1,
	'fields'         => 'ids'
));
$deletedAttachments = 0;
foreach ($attachments as $attId) {
	$file = (string) get_post_meta($attId, '_wp_attached_file', true);
	if (!preg_match('#(test-[a-f]|frontdoor\d+)#i', $file)) {
		continue;
	}

	// Clear any post using this attachment as its featured image.
	$users = get_posts(array(
		'post_type'   => 'any',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_thumbnail_id',
		'meta_value'  => $attId
	));
	foreach ($users as $pid) {
		delete_post_meta($pid, '_thumbnail_id');
	}

	wp_delete_attachment($attId, true);
	$deletedAttachments++;
}
echo "Deleted {$deletedAttachments} demo attachment(s) and cleared their thumbnails.\n";

// 3) Strip injected content (images / gallery / pullquote / page break) from
//    the demo posts. The injected block was appended after the original body
//    and is the only content referencing the demo upload images, so we cut from
//    the first such block to the end.
$strippedPosts = 0;
foreach ($demoPostIds as $pid) {
	$post = get_post($pid);
	if (!$post) {
		continue;
	}
	$content = $post->post_content;
	if (strpos($content, $demoUploadMarker) === false || !preg_match('#(test-[a-f]|frontdoor\d+)#i', $content)) {
		continue;
	}
	// Cut from the first <figure> (the originals contained no inline figures).
	$cut = strpos($content, '<figure');
	if ($cut === false) {
		continue;
	}
	$newContent = rtrim(substr($content, 0, $cut));
	if ($newContent !== $content) {
		wp_update_post(array('ID' => $pid, 'post_content' => $newContent));
		$strippedPosts++;
	}
}
echo "Stripped injected content from {$strippedPosts} demo post(s).\n";

// 4) Remove tag assignments from the demo posts, then delete now-empty demo tags.
foreach ($demoPostIds as $pid) {
	if (get_post($pid)) {
		wp_set_post_terms($pid, array(), 'post_tag');
	}
}
$removedTags = 0;
foreach ($demoTagSlugs as $slug) {
	$term = get_term_by('slug', $slug, 'post_tag');
	if ($term && (int) $term->count === 0) {
		wp_delete_term($term->term_id, 'post_tag');
		$removedTags++;
	}
}
echo "Cleared demo tag assignments and removed {$removedTags} empty demo tag(s).\n";

// 5) Empty the Showcase widget sidebar.
$sidebars = wp_get_sidebars_widgets();
if (!empty($sidebars['showcase-sidebar'])) {
	$sidebars['showcase-sidebar'] = array();
	wp_set_sidebars_widgets($sidebars);
	echo "Emptied the Showcase sidebar widget area.\n";
} else {
	echo "Showcase sidebar already empty.\n";
}

// 6) Clear the demo author bio (only if it still matches what we set).
$users = get_users(array('number' => -1, 'fields' => array('ID')));
foreach ($users as $user) {
	$desc = get_user_meta($user->ID, 'description', true);
	if (strpos((string) $desc, $demoBio) !== false) {
		update_user_meta($user->ID, 'description', '');
		echo "Cleared demo bio for user {$user->ID}.\n";
	}
}

echo "Done. Development/demo data removed.\n";
