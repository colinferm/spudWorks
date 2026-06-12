<?php
/**
 * Related stories: 3-6 posts ranked tags-first (see spudmagRelatedStories).
 * Renders nothing when there are no related posts. Reuses the "under" card so
 * the related strip matches the home/archive card language.
 *
 * Expects to run inside the loop.
 */
$related = spudmagRelatedStories(get_the_ID(), 6);
if (empty($related)) {
	return;
}
?>
<section class="related-stories measure" aria-label="<?php esc_attr_e('Related stories', 'spudworks-mag'); ?>">
	<h2 class="related-stories__title"><?php esc_html_e('Keep Reading', 'spudworks-mag'); ?></h2>
	<span class="hazard-accent" aria-hidden="true"></span>

	<div class="related-grid">
		<?php foreach ($related as $relatedPost) : ?>
			<?php get_template_part('templates/card-under', '', array(
				'postId'     => $relatedPost->ID,
				'spanClass'  => '',
				'showByline' => false
			)); ?>
		<?php endforeach; ?>
	</div>
</section>
