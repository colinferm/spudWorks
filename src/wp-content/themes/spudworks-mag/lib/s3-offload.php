<?php
/**
 * S3 media offload.
 *
 * Every upload (and each generated size) is pushed to an S3 bucket. Once the
 * push succeeds the attachment is flagged with METAS3, its URLs are served from
 * the bucket (or a CNAME in front of it), any post content that hard-codes the
 * old local URLs is rewritten, and the local copies are deleted at the end of
 * the request.
 *
 * Credentials live in wp-config.php (not in the database):
 *   SPUDMAG_S3_KEY, SPUDMAG_S3_SECRET, SPUDMAG_S3_REGION      required
 *   SPUDMAG_S3_ENDPOINT                                       dev only (S3-compatible server the PHP side talks to)
 *   SPUDMAG_S3_PUBLIC_ENDPOINT                                dev only (browser-facing base URL for that server)
 * The bucket and optional CNAME are per-site options set in Settings → Media
 * (admin/media-s3-settings.php).
 *
 * Objects are keyed by their full URL path (wp-content/uploads/sites/2/2026/10/x.jpg)
 * so only the host changes when a URL is rewritten, and multisite sites can
 * share a bucket.
 *
 * Offload is skipped entirely until credentials, the SDK (composer install in
 * the theme) and a bucket are all present, and while the `spudmag_s3_bypass`
 * filter returns true (used by the test fixtures).
 */

/* ------------------------------------------------------------------ config */

function spudmagS3Credentials() {
	return defined('SPUDMAG_S3_KEY') && SPUDMAG_S3_KEY
		&& defined('SPUDMAG_S3_SECRET') && SPUDMAG_S3_SECRET
		&& defined('SPUDMAG_S3_REGION') && SPUDMAG_S3_REGION;
}

function spudmagS3Bucket() {
	return (string) get_option('spudmag_s3_bucket', '');
}

function spudmagS3Cname() {
	return (string) get_option('spudmag_s3_cname', '');
}

function spudmagS3SdkReady() {
	static $ready = null;
	if ($ready === null) {
		$ready = is_readable(THEMEDIR . '/vendor/autoload.php');
	}
	return $ready;
}

/**
 * Everything needed to talk to the bucket is present.
 */
function spudmagS3Configured() {
	return spudmagS3Credentials() && spudmagS3SdkReady() && spudmagS3Bucket() !== '';
}

/**
 * Configured, and not suppressed for this run. Gates the upload-time hooks.
 */
function spudmagS3Enabled() {
	return spudmagS3Configured() && !apply_filters('spudmag_s3_bypass', false);
}

/**
 * Lazy, memoized SDK client. Returns null when the SDK or credentials are
 * missing so callers can bail without a fatal.
 */
function spudmagS3Client() {
	static $client = null;
	if ($client !== null) {
		return $client;
	}
	if (!spudmagS3Credentials() || !spudmagS3SdkReady()) {
		return null;
	}

	require_once THEMEDIR . '/vendor/autoload.php';

	$args = array(
		'version'     => '2006-03-01',
		'region'      => SPUDMAG_S3_REGION,
		'credentials' => array('key' => SPUDMAG_S3_KEY, 'secret' => SPUDMAG_S3_SECRET)
	);
	if (defined('SPUDMAG_S3_ENDPOINT') && SPUDMAG_S3_ENDPOINT) {
		$args['endpoint'] = SPUDMAG_S3_ENDPOINT;
		$args['use_path_style_endpoint'] = true;
	}

	$client = new Aws\S3\S3Client($args);
	return $client;
}

/* -------------------------------------------------------------------- URLs */

function spudmagS3Scheme() {
	if (defined('SPUDMAG_S3_PUBLIC_ENDPOINT') && SPUDMAG_S3_PUBLIC_ENDPOINT) {
		$scheme = parse_url(SPUDMAG_S3_PUBLIC_ENDPOINT, PHP_URL_SCHEME);
		if ($scheme) {
			return $scheme;
		}
	}
	return 'https';
}

/**
 * The public base URL for a bucket, with no trailing slash. A CNAME wins when
 * given; otherwise the dev endpoint, otherwise the standard AWS host.
 */
function spudmagS3BaseUrl($bucket, $cname = '') {
	if ($cname !== '') {
		return spudmagS3Scheme() . '://' . $cname;
	}
	if (defined('SPUDMAG_S3_PUBLIC_ENDPOINT') && SPUDMAG_S3_PUBLIC_ENDPOINT) {
		return rtrim(SPUDMAG_S3_PUBLIC_ENDPOINT, '/') . '/' . $bucket;
	}
	// A dot in the bucket name breaks the wildcard TLS cert on the virtual host.
	if (strpos($bucket, '.') !== false) {
		return 'https://s3.' . SPUDMAG_S3_REGION . '.amazonaws.com/' . $bucket;
	}
	return 'https://' . $bucket . '.s3.' . SPUDMAG_S3_REGION . '.amazonaws.com';
}

/**
 * Base URL as currently configured for a bucket. The CNAME fronts the site's
 * configured bucket only, so attachments left behind in an older bucket keep
 * resolving through that bucket's own URL.
 */
function spudmagS3PublicBase($bucket) {
	$cname = ($bucket === spudmagS3Bucket()) ? spudmagS3Cname() : '';
	return spudmagS3BaseUrl($bucket, $cname);
}

/**
 * URL path of the uploads directory (e.g. wp-content/uploads/sites/2), which
 * is the key prefix. Cached per site.
 */
function spudmagS3Prefix() {
	static $cache = array();
	$blogId = get_current_blog_id();
	if (!isset($cache[$blogId])) {
		$uploads = wp_get_upload_dir();
		$cache[$blogId] = trim((string) parse_url($uploads['baseurl'], PHP_URL_PATH), '/');
	}
	return $cache[$blogId];
}

/**
 * Bucket key for a file path relative to the uploads directory.
 */
function spudmagS3Key($rel) {
	return spudmagS3Prefix() . '/' . ltrim($rel, '/');
}

function spudmagS3FileUrl($bucket, $rel) {
	return spudmagS3PublicBase($bucket) . '/' . spudmagS3Key($rel);
}

function spudmagS3LocalPath($rel) {
	$uploads = wp_get_upload_dir();
	return $uploads['basedir'] . '/' . ltrim($rel, '/');
}

/**
 * Scheme + host (+ port) of every origin that local upload URLs may have been
 * written with in post content. Both schemes are covered; add more (e.g. a www
 * variant) with the spudmag_s3_local_origins filter.
 */
function spudmagS3LocalOrigins() {
	$uploads = wp_get_upload_dir();
	$origins = array();
	foreach (array($uploads['baseurl'], home_url(), site_url()) as $url) {
		$parts = wp_parse_url($url);
		if (empty($parts['host'])) {
			continue;
		}
		$hostPort = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
		$origins[] = 'http://' . $hostPort;
		$origins[] = 'https://' . $hostPort;
	}
	return array_values(array_unique(apply_filters('spudmag_s3_local_origins', $origins)));
}

/* -------------------------------------------------------------- attachment */

function spudmagS3State($id) {
	$state = get_post_meta($id, METAS3, true);
	return (is_array($state) && !empty($state['bucket'])) ? $state : null;
}

/**
 * Every file that belongs to an attachment, as paths relative to the uploads
 * directory: the main file (possibly -scaled), the pre-scaling original, every
 * size (and its alternate-format sources), and the image-editor backups.
 */
function spudmagS3Files($id, $metadata = null) {
	$attached = get_post_meta($id, '_wp_attached_file', true);
	if (!$attached) {
		return array();
	}
	if ($metadata === null) {
		$metadata = wp_get_attachment_metadata($id);
	}

	$dir = dirname($attached);
	$dir = ($dir === '.') ? '' : $dir . '/';
	$files = array($attached);

	if (is_array($metadata)) {
		if (!empty($metadata['file'])) {
			$files[] = $metadata['file'];
		}
		if (!empty($metadata['original_image'])) {
			$files[] = $dir . $metadata['original_image'];
		}
		if (!empty($metadata['sources']) && is_array($metadata['sources'])) {
			foreach ($metadata['sources'] as $source) {
				if (!empty($source['file'])) {
					$files[] = $dir . $source['file'];
				}
			}
		}
		if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
			foreach ($metadata['sizes'] as $size) {
				if (!empty($size['file'])) {
					$files[] = $dir . $size['file'];
				}
				if (!empty($size['sources']) && is_array($size['sources'])) {
					foreach ($size['sources'] as $source) {
						if (!empty($source['file'])) {
							$files[] = $dir . $source['file'];
						}
					}
				}
			}
		}
	}

	$backups = get_post_meta($id, '_wp_attachment_backup_sizes', true);
	if (is_array($backups)) {
		foreach ($backups as $backup) {
			if (!empty($backup['file'])) {
				$files[] = $dir . $backup['file'];
			}
		}
	}

	return array_values(array_unique($files));
}

/* ------------------------------------------------------------------- bucket */

function spudmagS3ContentType($rel) {
	$type = wp_check_filetype($rel);
	return $type['type'] ? $type['type'] : 'application/octet-stream';
}

/**
 * Upload one file. Returns true or a WP_Error.
 */
function spudmagS3Put($bucket, $rel) {
	$path = spudmagS3LocalPath($rel);
	$client = spudmagS3Client();
	if (!$client) {
		return new WP_Error('spudmag_s3_client', 'S3 client unavailable.');
	}
	if (!is_readable($path)) {
		return new WP_Error('spudmag_s3_missing', 'Local file not found: ' . $rel);
	}

	$args = array(
		'Bucket'       => $bucket,
		'Key'          => spudmagS3Key($rel),
		'SourceFile'   => $path,
		'ContentType'  => spudmagS3ContentType($rel),
		'CacheControl' => 'public, max-age=31536000'
	);
	// Return an empty value from this filter if the bucket has ACLs disabled.
	$acl = apply_filters('spudmag_s3_acl', 'public-read');
	if ($acl) {
		$args['ACL'] = $acl;
	}

	try {
		$client->putObject($args);
	} catch (Throwable $e) {
		return new WP_Error('spudmag_s3_put', $rel . ': ' . $e->getMessage());
	}
	return true;
}

/**
 * Remove objects from the bucket. $rels are uploads-relative paths.
 */
function spudmagS3DeleteRemote($bucket, $rels) {
	$client = spudmagS3Client();
	if (!$client || !$rels) {
		return false;
	}

	$objects = array();
	foreach ($rels as $rel) {
		$objects[] = array('Key' => spudmagS3Key($rel));
	}

	try {
		foreach (array_chunk($objects, 1000) as $chunk) {
			$client->deleteObjects(array(
				'Bucket' => $bucket,
				'Delete' => array('Objects' => $chunk, 'Quiet' => true)
			));
		}
	} catch (Throwable $e) {
		error_log('spudmag S3 delete failed: ' . $e->getMessage());
		return false;
	}
	return true;
}

/**
 * Every key in the bucket under this site's uploads prefix, as key => ETag
 * (quotes stripped; an MD5 for single-part uploads). One paginated listing
 * instead of a HEAD per file.
 */
function spudmagS3RemoteKeys($bucket) {
	$client = spudmagS3Client();
	$keys = array();
	if (!$client) {
		return $keys;
	}

	$pages = $client->getPaginator('ListObjectsV2', array(
		'Bucket' => $bucket,
		'Prefix' => spudmagS3Prefix() . '/'
	));
	foreach ($pages as $page) {
		$contents = isset($page['Contents']) ? $page['Contents'] : array();
		foreach ($contents as $object) {
			$keys[$object['Key']] = trim($object['ETag'], '"');
		}
	}
	return $keys;
}

/**
 * Download every file of an offloaded attachment back into the uploads
 * directory, for the image editor / thumbnail regeneration. Returns the number
 * of files fetched, or a WP_Error.
 */
function spudmagS3Restore($id, $queueCleanup = true) {
	$state = spudmagS3State($id);
	$client = spudmagS3Client();
	if (!$state || !$client) {
		return 0;
	}

	$fetched = 0;
	foreach ($state['files'] as $rel) {
		$path = spudmagS3LocalPath($rel);
		if (is_file($path)) {
			continue;
		}
		wp_mkdir_p(dirname($path));
		try {
			$client->getObject(array(
				'Bucket' => $state['bucket'],
				'Key'    => spudmagS3Key($rel),
				'SaveAs' => $path
			));
			$fetched++;
		} catch (Throwable $e) {
			return new WP_Error('spudmag_s3_get', $rel . ': ' . $e->getMessage());
		}
	}

	if ($queueCleanup) {
		spudmagS3QueueCleanup($id);
	}
	return $fetched;
}

/* ----------------------------------------------------------- post content */

/**
 * Search terms (uploads-relative path minus extension and size/edit suffixes)
 * that identify any post referencing this attachment's files. Used only to
 * narrow the UPDATE/SELECT; exactness comes from the REPLACE needles.
 */
function spudmagS3SearchTerms($files) {
	$terms = array();
	foreach ($files as $rel) {
		$dir = dirname($rel);
		$name = pathinfo($rel, PATHINFO_FILENAME);
		$name = preg_replace('/(-scaled|-e\d+|-\d+x\d+)$/', '', $name);
		$terms[] = spudmagS3Key(($dir === '.' ? '' : $dir . '/') . $name);
	}
	return array_values(array_unique($terms));
}

/**
 * Do any posts still contain a local URL for these files?
 */
function spudmagS3HasLocalUrls($files) {
	global $wpdb;

	$clauses = array();
	$args = array();
	foreach (spudmagS3SearchTerms($files) as $term) {
		foreach (spudmagS3LocalOrigins() as $origin) {
			$clauses[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
			$like = '%' . $wpdb->esc_like($origin . '/' . $term) . '%';
			$args[] = $like;
			$args[] = $like;
		}
	}
	if (!$clauses) {
		return false;
	}

	$sql = "SELECT ID FROM {$wpdb->posts} WHERE " . implode(' OR ', $clauses) . ' LIMIT 1';
	return (bool) $wpdb->get_var($wpdb->prepare($sql, $args));
}

/**
 * Nest one REPLACE() per [needle, replacement] pair around a column, appending
 * the placeholder arguments to $args in textual order.
 */
function spudmagS3ReplaceExpr($column, $pairs, &$args) {
	$expr = $column;
	foreach ($pairs as $pair) {
		$expr = "REPLACE({$expr}, %s, %s)";
		$args[] = $pair[0];
		$args[] = $pair[1];
	}
	return $expr;
}

/**
 * Swap local upload URLs for bucket URLs in post content and excerpts (every
 * post type, revisions included). Returns the number of rows changed.
 */
function spudmagS3RewriteContent($files, $bucket) {
	global $wpdb;

	$pairs = array();
	foreach ($files as $rel) {
		$remote = spudmagS3FileUrl($bucket, $rel);
		$key = spudmagS3Key($rel);
		foreach (spudmagS3LocalOrigins() as $origin) {
			$pairs[] = array($origin . '/' . $key, $remote);
		}
	}

	$terms = spudmagS3SearchTerms($files);
	if (!$pairs || !$terms) {
		return 0;
	}

	$args = array();
	$contentExpr = spudmagS3ReplaceExpr('post_content', $pairs, $args);
	$excerptExpr = spudmagS3ReplaceExpr('post_excerpt', $pairs, $args);

	$where = array();
	foreach ($terms as $term) {
		$like = '%' . $wpdb->esc_like($term) . '%';
		$where[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
		$args[] = $like;
		$args[] = $like;
	}

	$sql = "UPDATE {$wpdb->posts} SET post_content = {$contentExpr}, post_excerpt = {$excerptExpr} WHERE " . implode(' OR ', $where);
	$rows = $wpdb->query($wpdb->prepare($sql, $args));
	if ($rows) {
		wp_cache_flush_group('posts');
	}
	return (int) $rows;
}

/**
 * Offloaded, and no post still points at a local URL.
 */
function spudmagS3IsRewritten($id) {
	$state = spudmagS3State($id);
	if (!$state) {
		return false;
	}
	return !spudmagS3HasLocalUrls(spudmagS3Files($id));
}

/* ---------------------------------------------------------- local deletion */

function spudmagS3QueueCleanup($id) {
	static $queue = array();
	static $hooked = false;
	$queue[$id] = true;
	if (!$hooked) {
		$hooked = true;
		// Wait for shutdown so later hooks in this request still see the files, and
		// so every piece of attachment meta (e.g. the image editor's backup sizes,
		// written after the metadata update) has landed before we reconcile.
		add_action('shutdown', function () use (&$queue) {
			foreach (array_keys($queue) as $id) {
				if (spudmagS3Enabled()) {
					spudmagS3Offload($id, null, true);
				}
				spudmagS3DeleteLocal($id);
			}
		});
	}
}

/**
 * Delete the web server's copies of an offloaded attachment, but only once its
 * URLs are confirmed rewritten. Returns the number of files removed.
 */
function spudmagS3DeleteLocal($id) {
	$state = spudmagS3State($id);
	if (!$state || !spudmagS3IsRewritten($id)) {
		return 0;
	}

	$deleted = 0;
	foreach ($state['files'] as $rel) {
		$path = spudmagS3LocalPath($rel);
		if (is_file($path) && unlink($path)) {
			$deleted++;
		}
	}
	return $deleted;
}

/* ------------------------------------------------------------------ upload */

/**
 * Push an attachment's local files, record the result, rewrite content, and
 * queue the local copies for deletion. All-or-nothing: the METAS3 flag (which
 * switches URLs to the bucket) is only written when every push succeeded.
 *
 * Two passes use this. The upload hook ($settle = false) pushes every local
 * file, since anything present is new, restored for editing, or regenerated.
 * The shutdown pass ($settle = true) runs once all attachment meta is written:
 * it pushes only files not yet recorded and removes objects the attachment no
 * longer owns. Pruning waits for that pass because the metadata update can
 * arrive before the backup-sizes meta that still lists the pre-edit files.
 *
 * Returns array(pushed, rows) or a WP_Error.
 */
function spudmagS3Offload($id, $metadata = null, $settle = false) {
	$state = spudmagS3State($id);
	// An attachment stays in the bucket it was first pushed to.
	$bucket = $state ? $state['bucket'] : spudmagS3Bucket();
	$known = $state ? $state['files'] : array();
	$files = spudmagS3Files($id, $metadata);
	if (!$files) {
		return array('pushed' => 0, 'rows' => 0);
	}

	$pushed = array();
	foreach ($files as $rel) {
		if (!is_file(spudmagS3LocalPath($rel)) || ($settle && in_array($rel, $known, true))) {
			continue;
		}
		$result = spudmagS3Put($bucket, $rel);
		if (is_wp_error($result)) {
			return $result;
		}
		$pushed[] = $rel;
	}

	$orphans = $settle ? array_diff($known, $files) : array();
	if ($orphans) {
		spudmagS3DeleteRemote($bucket, $orphans);
	}

	$recorded = array_values(array_unique(array_merge(array_diff($known, $orphans), $pushed)));
	if ($recorded !== $known || !$state) {
		update_post_meta($id, METAS3, array('bucket' => $bucket, 'files' => $recorded));
	}

	$rows = $pushed ? spudmagS3RewriteContent($files, $bucket) : 0;
	spudmagS3QueueCleanup($id);

	return array('pushed' => count($pushed), 'rows' => $rows);
}

/**
 * wp_update_attachment_metadata fires for new uploads, image edits and
 * thumbnail regenerations alike, so one hook covers all three.
 */
function spudmagS3OnMetadata($data, $id) {
	if (!spudmagS3Enabled()) {
		return $data;
	}

	$result = spudmagS3Offload($id, is_array($data) ? $data : null);
	if (is_wp_error($result)) {
		error_log('spudmag S3 offload failed for attachment ' . $id . ': ' . $result->get_error_message());
	}
	return $data;
}
add_filter('wp_update_attachment_metadata', 'spudmagS3OnMetadata', 99, 2);

/**
 * With local copies gone, wp_unique_filename() can't see earlier uploads and
 * would hand out the same name twice, overwriting the first object in the
 * bucket. Feed it the bucket's listing for the target directory as well.
 */
function spudmagS3UniqueFileList($files, $dir, $filename) {
	if (!spudmagS3Enabled()) {
		return $files;
	}

	$uploads = wp_get_upload_dir();
	if (strpos($dir, $uploads['basedir']) !== 0) {
		return $files;
	}
	$client = spudmagS3Client();
	if (!$client) {
		return $files;
	}

	$rel = trim(substr($dir, strlen($uploads['basedir'])), '/');
	$prefix = spudmagS3Key($rel === '' ? '' : $rel . '/');
	$names = is_dir($dir) ? array_diff(scandir($dir), array('.', '..')) : array();

	try {
		$pages = $client->getPaginator('ListObjectsV2', array('Bucket' => spudmagS3Bucket(), 'Prefix' => $prefix, 'Delimiter' => '/'));
		foreach ($pages as $page) {
			$contents = isset($page['Contents']) ? $page['Contents'] : array();
			foreach ($contents as $object) {
				$names[] = basename($object['Key']);
			}
		}
	} catch (Throwable $e) {
		error_log('spudmag S3 listing failed: ' . $e->getMessage());
		spudmagS3ListingFailed(true);
	}
	return array_values(array_unique($names));
}
add_filter('pre_wp_unique_filename_file_list', 'spudmagS3UniqueFileList', 10, 3);

/**
 * Whether the last bucket listing for a filename check failed.
 */
function spudmagS3ListingFailed($set = null) {
	static $failed = false;
	if ($set !== null) {
		$failed = $set;
	}
	return $failed;
}

/**
 * If the bucket couldn't be listed (S3 unreachable), we can't tell whether the
 * name is taken there, and a later sync would mistake the new file for the
 * existing object. Make the name unique with a random suffix instead.
 */
function spudmagS3BlindUniqueName($filename, $ext, $dir) {
	if (!spudmagS3ListingFailed()) {
		return $filename;
	}
	spudmagS3ListingFailed(false);
	$suffix = '-' . strtolower(wp_generate_password(6, false));
	return $ext !== '' ? basename($filename, $ext) . $suffix . $ext : $filename . $suffix;
}
add_filter('wp_unique_filename', 'spudmagS3BlindUniqueName', 99, 3);

/* --------------------------------------------------------------------- URLs */

function spudmagS3AttachmentUrl($url, $id) {
	$state = spudmagS3State($id);
	$attached = $state ? get_post_meta($id, '_wp_attached_file', true) : '';
	if (!$attached) {
		return $url;
	}
	return spudmagS3FileUrl($state['bucket'], $attached);
}
add_filter('wp_get_attachment_url', 'spudmagS3AttachmentUrl', 10, 2);

function spudmagS3Srcset($sources, $sizeArray, $imageSrc, $imageMeta, $id) {
	$state = spudmagS3State($id);
	if (!$state || !is_array($sources)) {
		return $sources;
	}

	$uploads = wp_get_upload_dir();
	$remoteBase = spudmagS3PublicBase($state['bucket']) . '/' . spudmagS3Prefix();
	foreach ($sources as $width => $source) {
		$sources[$width]['url'] = str_replace($uploads['baseurl'], $remoteBase, $source['url']);
	}
	return $sources;
}
add_filter('wp_calculate_image_srcset', 'spudmagS3Srcset', 10, 5);

/* ------------------------------------------------------- pull back / delete */

/**
 * Restore an attachment's files before WordPress edits them: the image editor
 * (ajax) and the Regenerate Thumbnails REST route. The upload hook then pushes
 * the result and the local copies are cleaned up again.
 */
function spudmagS3RestoreForEditor() {
	if (!wp_doing_ajax() || !spudmagS3Configured()) {
		return;
	}
	$action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
	if (!in_array($action, array('image-editor', 'imgedit-preview', 'crop-image'), true)) {
		return;
	}

	$id = isset($_REQUEST['postid']) ? (int) $_REQUEST['postid'] : (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);
	if ($id && current_user_can('edit_post', $id)) {
		spudmagS3Restore($id);
	}
}
add_action('admin_init', 'spudmagS3RestoreForEditor');

function spudmagS3RestoreForRest($response, $handler, $request) {
	if (spudmagS3Configured() && preg_match('#^/regenerate-thumbnails/v1/regenerate/(\d+)$#', $request->get_route(), $match)) {
		$id = (int) $match[1];
		if (current_user_can('manage_options')) {
			spudmagS3Restore($id);
		}
	}
	return $response;
}
add_filter('rest_request_before_callbacks', 'spudmagS3RestoreForRest', 10, 3);

/**
 * Deleting an attachment removes its objects from the bucket too.
 */
function spudmagS3OnDelete($id) {
	$state = spudmagS3State($id);
	if ($state) {
		spudmagS3DeleteRemote($state['bucket'], $state['files']);
	}
}
add_action('delete_attachment', 'spudmagS3OnDelete');
