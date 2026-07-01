<?php
/**
 * Home page (front-page.php).
 *
 * Layout, top to bottom:
 *   1. Prominent masthead with the site name.
 *   2. A pinned post standing alone full-width (only when one is pinned).
 *   3. A strip of 2-3 featured pieces.
 *   4. A grid of 10-12 items in varied box shapes for visual interest.
 *
 * All repeated units are pulled from templates/ and fed real post data.
 */
get_header();

$home = spudmagHomeItems(16);
$pinned = $home['pinned'];
$items = $home['items'];

// First three items make up the featured strip; the rest fill the varied grid.
$featured = array_slice($items, 0, 3);
$grid = array_slice($items, 3, 13);
?>

<section class="home-masthead container">
	<!-- <h1 class="home-masthead__title"><?php bloginfo('name'); ?></h1> -->
	<!-- <?php $tagline = get_bloginfo('description', 'display'); ?> -->
	<?php if ($tagline) : ?>
		<!-- <p class="home-masthead__tagline"><?php echo esc_html($tagline); ?></p> -->
	<?php endif; ?>
	<span class="hazard-accent" aria-hidden="true"></span>
</section>

<?php if ($pinned) : ?>
	<section class="home-pinned container">
		<?php get_template_part('templates/card-pinned', '', array('postId' => $pinned->ID)); ?>
	</section>
<?php endif; ?>

<?php if (!empty($featured)) : ?>
	<section class="home-featured container">
		<div class="featured-grid">
			<?php foreach ($featured as $featuredPost) : ?>
				<?php get_template_part('templates/card-featured', '', array('postId' => $featuredPost->ID)); ?>
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
					'spanClass' => $beat['span']
				)); ?>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php get_footer();
