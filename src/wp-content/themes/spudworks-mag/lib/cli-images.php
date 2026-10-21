<?php
/**
 * WP-CLI: wp sw images sync | restore
 *
 * Loaded only under WP-CLI. For a multisite install pass --url=<site> to pick
 * the site, since the bucket, CNAME and attachments are all per-site.
 */

/**
 * spudWorks tools.
 */
class Spudmag_Cli_Namespace extends WP_CLI_Command {
}

/**
 * Manage the S3 offload of media library images.
 */
class Spudmag_Cli_Images extends WP_CLI_Command {

	/**
	 * Push existing media to the bucket, rewrite its URLs, and delete the local copies.
	 *
	 * For every attachment: files missing from the bucket are uploaded; if post
	 * content still points at local URLs it is rewritten; and only once the URLs
	 * are rewritten are the web server's copies deleted. Safe to re-run.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would happen without uploading, rewriting or deleting anything.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated attachment IDs. Defaults to every attachment.
	 *
	 * [--limit=<number>]
	 * : Stop after this many attachments.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sw images sync --dry-run
	 *     wp sw images sync --url=spudworks.com
	 *     wp sw images sync --ids=12,15
	 *
	 * @when after_wp_load
	 */
	public function sync($args, $assocArgs) {
		global $wpdb;

		if (!spudmagS3Configured()) {
			WP_CLI::error('S3 is not configured: check the SPUDMAG_S3_* constants, composer install, and the bucket in Settings → Media.');
		}

		$dryRun = WP_CLI\Utils\get_flag_value($assocArgs, 'dry-run', false);
		$bucket = spudmagS3Bucket();

		if (!empty($assocArgs['ids'])) {
			$ids = array_map('intval', explode(',', $assocArgs['ids']));
		} else {
			$ids = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID"));
		}
		if (!empty($assocArgs['limit'])) {
			$ids = array_slice($ids, 0, (int) $assocArgs['limit']);
		}

		WP_CLI::log(sprintf('Listing bucket "%s" under %s/ ...', $bucket, spudmagS3Prefix()));
		$remote = spudmagS3RemoteKeys($bucket);
		WP_CLI::log(sprintf('%d objects in the bucket, %d attachments to check%s.', count($remote), count($ids), $dryRun ? ' (dry run)' : ''));

		$tally = array(
			'already offloaded' => 0,
			'files uploaded'    => 0,
			'urls rewritten'    => 0,
			'local deleted'     => 0,
			'missing files'     => 0,
			'conflicts'         => 0,
			'other bucket'      => 0,
			'errors'            => 0
		);

		$progress = WP_CLI\Utils\make_progress_bar('Syncing attachments', count($ids));
		foreach ($ids as $id) {
			$this->syncOne($id, $bucket, $remote, $dryRun, $tally);
			$progress->tick();
		}
		$progress->finish();

		$rows = array();
		foreach ($tally as $label => $count) {
			$rows[] = array('result' => $label, 'count' => $count);
		}
		WP_CLI\Utils\format_items('table', $rows, array('result', 'count'));

		if ($tally['errors']) {
			WP_CLI::warning('Some attachments failed; their local files were kept. Re-run to retry.');
		} else {
			WP_CLI::success($dryRun ? 'Dry run complete.' : 'Sync complete.');
		}
	}

	/**
	 * Download an offloaded attachment's files back to the web server.
	 *
	 * Needed before `wp media regenerate`, which reads the local original. The
	 * files are left in place; run `wp sw images sync` afterwards to push any
	 * changes and clean up.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more attachment IDs.
	 *
	 * @when after_wp_load
	 */
	public function restore($args, $assocArgs) {
		if (!spudmagS3Configured()) {
			WP_CLI::error('S3 is not configured.');
		}
		foreach ($args as $id) {
			$result = spudmagS3Restore((int) $id, false);
			if (is_wp_error($result)) {
				WP_CLI::warning('#' . $id . ': ' . $result->get_error_message());
			} else {
				WP_CLI::log(sprintf('#%d: restored %d file(s).', $id, $result));
			}
		}
	}

	private function syncOne($id, $bucket, $remote, $dryRun, &$tally) {
		$state = spudmagS3State($id);
		if ($state && $state['bucket'] !== $bucket) {
			$tally['other bucket']++;
			return;
		}

		$files = spudmagS3Files($id);
		if (!$files) {
			return;
		}

		// Sort each file into: already in the bucket, needs uploading, or gone.
		$inBucket = array();
		$toPush = array();
		$gone = array();
		$conflicts = array();
		foreach ($files as $rel) {
			$key = spudmagS3Key($rel);
			if (isset($remote[$key])) {
				// A local copy that differs from the object is a different file
				// that happens to share the name; never treat it as uploaded.
				$etag = $remote[$key];
				$path = spudmagS3LocalPath($rel);
				if (is_file($path) && strpos($etag, '-') === false && $etag !== md5_file($path)) {
					$conflicts[] = $rel;
					continue;
				}
				$inBucket[] = $rel;
			} elseif (is_file(spudmagS3LocalPath($rel))) {
				$toPush[] = $rel;
			} else {
				$gone[] = $rel;
			}
		}
		if ($conflicts) {
			$tally['conflicts']++;
			WP_CLI::warning(sprintf('#%d: %s differs from the object already in the bucket under the same name; nothing was changed for this attachment.', $id, $conflicts[0]));
			return;
		}
		if ($gone) {
			$tally['missing files'] += count($gone);
			WP_CLI::warning(sprintf('#%d: %d file(s) neither local nor in the bucket (e.g. %s).', $id, count($gone), $gone[0]));
		}
		$all = array_merge($inBucket, $toPush);
		if (!in_array($files[0], $all, true)) {
			return;
		}

		$needsMeta = !$state || array_diff($all, $state['files']);
		$needsRewrite = spudmagS3HasLocalUrls($files);
		$localLeft = array_filter($all, function ($rel) {
			return is_file(spudmagS3LocalPath($rel));
		});

		if (!$toPush && !$needsMeta && !$needsRewrite && !$localLeft) {
			$tally['already offloaded']++;
			return;
		}

		if ($dryRun) {
			WP_CLI::log(sprintf(
				'#%d: would upload %d, %s, delete %d local.',
				$id,
				count($toPush),
				$needsRewrite ? 'rewrite URLs' : 'URLs already rewritten',
				count($localLeft)
			));
			return;
		}

		foreach ($toPush as $rel) {
			$result = spudmagS3Put($bucket, $rel);
			if (is_wp_error($result)) {
				WP_CLI::warning('#' . $id . ': ' . $result->get_error_message());
				$tally['errors']++;
				return;
			}
			$tally['files uploaded']++;
		}

		update_post_meta($id, METAS3, array('bucket' => $bucket, 'files' => array_values(array_unique($all))));

		if ($needsRewrite) {
			$tally['urls rewritten'] += spudmagS3RewriteContent($files, $bucket);
		}
		// Re-check rather than assume: deletion only follows confirmed rewrites.
		$tally['local deleted'] += spudmagS3DeleteLocal($id);
	}
}

WP_CLI::add_command('sw', 'Spudmag_Cli_Namespace', array('shortdesc' => 'spudWorks tools.'));
WP_CLI::add_command('sw images', 'Spudmag_Cli_Images');
