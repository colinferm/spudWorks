<?php
/**
 * Settings → Media: the S3 offload bucket and optional CNAME, plus the URL
 * rewrite that runs when the CNAME is added, changed or removed.
 *
 * Credentials are deliberately not editable here; they live in wp-config.php
 * (see lib/s3-offload.php).
 */

function spudmagS3RegisterSettings() {
	register_setting('media', 'spudmag_s3_bucket', array(
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'spudmagS3SanitizeBucket'
	));
	register_setting('media', 'spudmag_s3_cname', array(
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'spudmagS3SanitizeCname'
	));

	add_settings_section('spudmag_s3', __('Amazon S3 Offload', 'spudworks-mag'), 'spudmagS3SectionIntro', 'media');
	add_settings_field('spudmag_s3_bucket', __('Bucket name', 'spudworks-mag'), 'spudmagS3BucketField', 'media', 'spudmag_s3');
	add_settings_field('spudmag_s3_cname', __('CNAME (optional)', 'spudworks-mag'), 'spudmagS3CnameField', 'media', 'spudmag_s3');
}
add_action('admin_init', 'spudmagS3RegisterSettings');

function spudmagS3SanitizeBucket($value) {
	$value = strtolower(trim((string) $value));
	if ($value === '') {
		return '';
	}
	if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $value)) {
		add_settings_error('spudmag_s3_bucket', 'spudmag_s3_bucket', __('That is not a valid S3 bucket name (3–63 characters: lowercase letters, numbers, dots and hyphens). The bucket was not changed.', 'spudworks-mag'));
		return spudmagS3Bucket();
	}
	return $value;
}

/**
 * Store the CNAME as a bare host (plus optional port): no scheme, no path.
 */
function spudmagS3SanitizeCname($value) {
	$value = strtolower(trim((string) $value));
	$value = preg_replace('#^https?://#', '', $value);
	$value = rtrim($value, '/');
	if ($value === '') {
		return '';
	}
	if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/', $value)) {
		add_settings_error('spudmag_s3_cname', 'spudmag_s3_cname', __('That is not a valid host name. Use something like media.example.com. The CNAME was not changed.', 'spudworks-mag'));
		return spudmagS3Cname();
	}
	return $value;
}

function spudmagS3SectionIntro() {
	global $wpdb;

	$credentials = spudmagS3Credentials();
	$sdk = spudmagS3SdkReady();
	$offloaded = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", METAS3));

	echo '<p>' . esc_html__('New uploads and all of their sizes are pushed to this bucket, their URLs point at it, and the copies on the web server are deleted. Existing media is moved with: wp sw images sync', 'spudworks-mag') . '</p>';

	if (!$credentials) {
		echo '<p><strong>' . esc_html__('Credentials missing.', 'spudworks-mag') . '</strong> ' . esc_html__('Define SPUDMAG_S3_KEY, SPUDMAG_S3_SECRET and SPUDMAG_S3_REGION in wp-config.php.', 'spudworks-mag') . '</p>';
	}
	if (!$sdk) {
		echo '<p><strong>' . esc_html__('AWS SDK missing.', 'spudworks-mag') . '</strong> ' . esc_html__('Run composer install in the theme directory.', 'spudworks-mag') . '</p>';
	}
	if (spudmagS3Configured()) {
		printf(
			'<p>%s <code>%s</code> &middot; %s</p>',
			esc_html__('Serving from', 'spudworks-mag'),
			esc_html(spudmagS3PublicBase(spudmagS3Bucket())),
			/* translators: %d: number of attachments */
			esc_html(sprintf(_n('%d attachment offloaded', '%d attachments offloaded', $offloaded, 'spudworks-mag'), $offloaded))
		);
	}
}

function spudmagS3BucketField() {
	printf('<input type="text" name="spudmag_s3_bucket" id="spudmag_s3_bucket" class="regular-text code" value="%s" autocomplete="off" spellcheck="false">', esc_attr(spudmagS3Bucket()));
	echo '<p class="description">' . esc_html__('Leave empty to turn offloading off. Changing the bucket does not move existing objects; attachments already offloaded keep using the bucket they were pushed to.', 'spudworks-mag') . '</p>';
}

function spudmagS3CnameField() {
	printf('<input type="text" name="spudmag_s3_cname" id="spudmag_s3_cname" class="regular-text code" value="%s" placeholder="media.example.com" autocomplete="off" spellcheck="false">', esc_attr(spudmagS3Cname()));
	echo '<p class="description">' . esc_html__('A host name that points at the bucket (for example through CloudFront), used in place of the standard S3 address. Adding, changing or removing it rewrites the image URLs saved in post content.', 'spudworks-mag') . '</p>';
}

/**
 * Point saved post content at the new base URL. $old and $new are bare hosts;
 * an empty one means "the standard bucket address".
 */
function spudmagS3CnameChanged($old, $new) {
	global $wpdb;

	$bucket = spudmagS3Bucket();
	if ($old === $new || $bucket === '' || !spudmagS3Credentials()) {
		return;
	}

	$contentPath = '/' . trim((string) parse_url(content_url(), PHP_URL_PATH), '/') . '/';
	$from = spudmagS3BaseUrl($bucket, $old) . $contentPath;
	$to = spudmagS3BaseUrl($bucket, $new) . $contentPath;

	$like = '%' . $wpdb->esc_like($from) . '%';
	$sql = "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s), post_excerpt = REPLACE(post_excerpt, %s, %s)"
		. ' WHERE post_content LIKE %s OR post_excerpt LIKE %s';
	$rows = (int) $wpdb->query($wpdb->prepare($sql, $from, $to, $from, $to, $like, $like));
	if ($rows) {
		wp_cache_flush_group('posts');
	}

	add_settings_error(
		'spudmag_s3_cname',
		'spudmag_s3_cname_rewrite',
		sprintf(
			/* translators: 1: number of posts, 2: new base URL */
			_n('Updated image URLs in %1$d post. Images now load from %2$s.', 'Updated image URLs in %1$d posts. Images now load from %2$s.', $rows, 'spudworks-mag'),
			$rows,
			spudmagS3BaseUrl($bucket, $new)
		),
		'updated'
	);
}

function spudmagS3OnCnameUpdated($old, $new) {
	spudmagS3CnameChanged((string) $old, (string) $new);
}
add_action('update_option_spudmag_s3_cname', 'spudmagS3OnCnameUpdated', 10, 2);

function spudmagS3OnCnameAdded($option, $value) {
	spudmagS3CnameChanged('', (string) $value);
}
add_action('add_option_spudmag_s3_cname', 'spudmagS3OnCnameAdded', 10, 2);

// The generic hook runs before the delete, while the old value still exists.
function spudmagS3OnOptionDeleted($option) {
	if ($option === 'spudmag_s3_cname') {
		spudmagS3CnameChanged(spudmagS3Cname(), '');
	}
}
add_action('delete_option', 'spudmagS3OnOptionDeleted');
