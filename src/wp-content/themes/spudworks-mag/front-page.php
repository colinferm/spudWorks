<?php
/**
 * Home page (front-page.php).
 *
 * Layout, top to bottom:
 *   1. Prominent masthead with the site name.
 *   2. A featured (sticky) post standing alone full-width (only when one exists).
 *   3. A strip of up to 3 pieces: the next-newest sticky posts, backfilled
 *      with the latest posts if there aren't 3 others pinned.
 *   4. A grid of 10-12 items in varied box shapes for visual interest.
 *
 * All repeated units are pulled from templates/ and fed real post data.
 */
get_header();

$home = spudmagHomeItems(16);
$heroId = $home['featured'];
$rowIds = $home['row'];

// Capped at 13 regardless of how the row was filled, matching the layout's
// intended "10-12 items" size even if a row sticky falls outside the pool
// spudmagHomeItems() fetched for the grid.
$grid = array_slice($home['items'], 0, 13);
?>

<section class="home-masthead container">
	<!-- <h1 class="home-masthead__title"><?php bloginfo('name'); ?></h1> -->
	<!-- <?php $tagline = get_bloginfo('description', 'display'); ?> -->
	<?php if ($tagline) : ?>
		<!-- <p class="home-masthead__tagline"><?php echo esc_html($tagline); ?></p> -->
	<?php endif; ?>
	<span class="hazard-accent" aria-hidden="true"></span>
</section>

<?php if ($heroId) : ?>
	<section class="home-pinned container">
		<?php get_template_part('templates/card-pinned', '', array('postId' => $heroId)); ?>
	</section>
<?php endif; ?>

<?php if (!empty($rowIds)) : ?>
	<section class="home-featured container">
		<div class="featured-grid">
			<?php foreach ($rowIds as $rowPostId) : ?>
				<?php get_template_part('templates/card-featured', '', array('postId' => $rowPostId, 'showByline' => false)); ?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php if (!empty($grid)) : ?>
	<section class="home-grid container">
		<div class="card-grid">
			<?php foreach ($grid as $index => $gridPost) : ?>
				<?php $beat = spudmagCardBeat($index); ?>
				<?php get_template_part('templates/' . $beat['tpl'], '', array(
					'postId'    => $gridPost->ID,
					'spanClass' => $beat['span'],
					'showByline' => false
				)); ?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php get_footer();
