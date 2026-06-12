<?php
/**
 * Author box: photo, name (linked to the author archive), and any bio text.
 * Renders at the bottom of every content template. Always shows the photo +
 * name; the bio paragraph appears only when the author has filled one in.
 *
 * Expects to run inside the loop.
 */
$authorId = (int) get_the_author_meta('ID');
if ($authorId <= 0) {
	return;
}
$bio = get_the_author_meta('description');
$name = get_the_author_meta('display_name');
$archive = get_author_posts_url($authorId);
?>
<aside class="author-box measure">
	<div class="author-box__photo">
		<?php echo get_avatar($authorId, 96, '', $name); ?>
	</div>
	<div class="author-box__body">
		<p class="author-box__kicker"><?php esc_html_e('Written by', 'spudworks-mag'); ?></p>
		<h3 class="author-box__name">
			<a href="<?php echo esc_url($archive); ?>"><?php echo esc_html($name); ?></a>
		</h3>
		<?php if ($bio !== '') : ?>
			<p class="author-box__bio"><?php echo wp_kses_post($bio); ?></p>
		<?php endif; ?>
	</div>
</aside>
