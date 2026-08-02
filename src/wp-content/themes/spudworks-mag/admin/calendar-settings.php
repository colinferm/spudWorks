<?php
/**
 * spudWorks Magazine — "Calendar Feed" admin page (Posts submenu).
 *
 * Shows the private subscribe URL for the ICS feed built in
 * lib/calendar-feed.php and lets the owner rotate its secret token. Kept at
 * manage_options (not edit_posts, like the To Do page) because the feed URL
 * itself is the credential — it exposes unpublished titles and TODOs to
 * whoever holds it.
 */

/**
 * Register the submenu under Posts.
 */
function spudmagCalendarSettingsMenu() {
	add_submenu_page(
		'edit.php',
		__('Calendar Feed', 'spudworks-mag'),
		__('Calendar Feed', 'spudworks-mag'),
		'manage_options',
		'spudmag-calendar',
		'spudmagCalendarSettingsRender'
	);
}
add_action('admin_menu', 'spudmagCalendarSettingsMenu');

/**
 * Handle the "Regenerate URL" form post, ahead of any output.
 */
function spudmagCalendarSettingsHandleRegenerate() {
	if (!isset($_POST['spudmag_calendar_regenerate']) || !current_user_can('manage_options')) {
		return;
	}
	check_admin_referer('spudmag_calendar_regenerate');
	spudmagCalendarRegenerateToken();
	add_action('admin_notices', function() {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Calendar URL regenerated — update it in Apple Calendar or wherever it was subscribed.', 'spudworks-mag') . '</p></div>';
	});
}
add_action('load-posts_page_spudmag-calendar', 'spudmagCalendarSettingsHandleRegenerate');

/**
 * Render the page: the current subscribe URL (https + webcal) and a
 * regenerate button.
 */
function spudmagCalendarSettingsRender() {
	if (!current_user_can('manage_options')) {
		return;
	}
	$httpsUrl = spudmagCalendarFeedUrl('https');
	$webcalUrl = spudmagCalendarFeedUrl('webcal');
	?>
	<div class="wrap spudmag-calendar-settings">
		<h1><?php esc_html_e('Calendar Feed', 'spudworks-mag'); ?></h1>
		<p class="description"><?php esc_html_e('A private, subscribable calendar of scheduled posts and dated drafts. Each entry reminds you the evening before and lists outstanding To-Dos. Subscribe to it from Apple Calendar (or any app that supports calendar subscriptions) using the URL below.', 'spudworks-mag'); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="spudmag-calendar-url-webcal"><?php esc_html_e('Subscribe URL', 'spudworks-mag'); ?></label></th>
				<td>
					<input type="text" id="spudmag-calendar-url-webcal" class="large-text code" readonly value="<?php echo esc_attr($webcalUrl); ?>">
					<p class="description"><?php esc_html_e('Use this one when adding a subscription directly in Apple Calendar.', 'spudworks-mag'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="spudmag-calendar-url-https"><?php esc_html_e('Plain URL', 'spudworks-mag'); ?></label></th>
				<td>
					<input type="text" id="spudmag-calendar-url-https" class="large-text code" readonly value="<?php echo esc_attr($httpsUrl); ?>">
					<p class="description"><?php esc_html_e('Same feed, for apps that want an https:// link instead of webcal://.', 'spudworks-mag'); ?></p>
				</td>
			</tr>
		</table>

		<p class="description"><strong><?php esc_html_e('Keep this URL private.', 'spudworks-mag'); ?></strong> <?php esc_html_e('Anyone with it can see unpublished titles and production notes.', 'spudworks-mag'); ?></p>

		<form method="post">
			<?php wp_nonce_field('spudmag_calendar_regenerate'); ?>
			<p><button type="submit" name="spudmag_calendar_regenerate" value="1" class="button" onclick="return confirm('<?php echo esc_js(__('Regenerate the calendar URL? The old link will stop working immediately.', 'spudworks-mag')); ?>');"><?php esc_html_e('Regenerate URL', 'spudworks-mag'); ?></button></p>
		</form>
	</div>
	<?php
}
