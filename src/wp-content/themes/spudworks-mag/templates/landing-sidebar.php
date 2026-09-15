<?php
/**
 * Landing Page sidebar: this page's own two curated galleries plus a
 * hand-picked related-articles list — a per-page counterpart to the global
 * Showcase Sidebar widget area (sidebar.php), needed because a topic hub's
 * sidebar content is unique per page rather than shared site-wide.
 *
 * Reuses the Showcase Sidebar's own chrome (.showcase-sidebar/.widget/
 * .widget-title, styled in css/_single.scss) so it looks identical to the
 * widget-driven sidebar for free; core's classic gallery_shortcode() for the
 * gallery grid (inherits .gallery/.gallery-columns-N/.gallery-icon styling
 * and the lightbox with no new markup rules); and templates/card-overlay.php
 * for each related article, exactly as the Featured Post widget renders one.
 *
 * Expects to run inside the loop.
 */
$postId = get_the_ID();
$galleries = spudmagGetPageGalleries($postId);
$related = spudmagGetPageRelated($postId);

$hasGalleries = false;
foreach ($galleries as $gallery) {
	if (!empty($gallery['ids'])) {
		$hasGalleries = true;
		break;
	}
}

if (!$hasGalleries && empty($related)) {
	return;
}
?>
<aside class="showcase-sidebar widget-area" aria-label="<?php esc_attr_e('Page sidebar', 'spudworks-mag'); ?>">
	<?php 
		if (count($galleries) > 0) :
			$gallery = $galleries[0];

			if (!empty($gallery['ids'])) :
		?>
		<section class="widget widget--gallery" data-lightbox="sidebar">
			<?php if ($gallery['title'] !== '') : ?>
				<h3 class="widget-title"><?php echo esc_html($gallery['title']); ?></h3>
			<?php endif; ?>
			<?php echo gallery_shortcode(array(
				'ids'     => implode(',', $gallery['ids']),
				'columns' => $gallery['columns'],
				'size'    => 'thumbnail',
				'link'    => 'file'
			)); ?>
		</section>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (!empty($related)) : ?>
		<section class="widget widget--related">
			<h3 class="widget-title"><?php echo esc_html(spudmagGetPageRelatedTitle($postId)); ?></h3>
			<?php foreach ($related as $relatedId) : ?>
				<?php get_template_part('templates/card-overlay', '', array(
					'postId'     => $relatedId,
					'showByline' => false
				)); ?>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php 
		if (count($galleries) > 1) :
			$gallery = $galleries[1];

			if (!empty($gallery['ids'])) :
		?>
		<section class="widget widget--gallery" data-lightbox="sidebar">
			<?php if ($gallery['title'] !== '') : ?>
				<h3 class="widget-title"><?php echo esc_html($gallery['title']); ?></h3>
			<?php endif; ?>
			<?php echo gallery_shortcode(array(
				'ids'     => implode(',', $gallery['ids']),
				'columns' => $gallery['columns'],
				'size'    => 'thumbnail',
				'link'    => 'file'
			)); ?>
		</section>
		<?php endif; ?>
	<?php endif; ?>
</aside>
