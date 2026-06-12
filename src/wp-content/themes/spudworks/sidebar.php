<?php
	global $wpdb;
	$imgNum = rand(1, 17);
	$select_categories = "
		SELECT t.term_id, t.name, t.slug, tt.description 
		FROM wp_terms t, wp_term_taxonomy tt, wp_term_relationships tr, wp_posts p 
		WHERE p.ID = tr.object_id 
		AND tr.term_taxonomy_id = tt.term_taxonomy_id 
		AND tt.term_id = t.term_id 
		AND p.post_status = 'publish' 
		AND t.slug IN ('elsewhere', 'misc', 'letters', 'fiction', 'news', 'blog') 
		GROUP BY t.term_id, t.name, t.slug, tt.description 
		ORDER BY CASE WHEN slug = 'blog' THEN 0 
		WHEN slug = 'news' THEN 1 
		WHEN slug = 'fiction' THEN 2 
		WHEN slug = 'essays' THEN 3 
		WHEN slug = 'misc' THEN 4 
		WHEN slug = 'letters' THEN 5 
		WHEN slug = 'elsewhere' THEN 6 END ASC
	";
	$cats = $wpdb->get_results($select_categories, OBJECT);
?>

<div class="large-2 cell left-nav">
	<img src="/wp-content/themes/spudworks/img/frontdoor<?php echo $imgNum?>.jpg" class="front-door">
	<ul class="site-categories">
	<?php foreach($cats as $c) : ?>
		<li>Recently in <a href="/category/<?php echo $c->slug; ?>/"><?php echo $c->name; ?></a> :</li>
		<?php $cat_items = get_items_for_cat($c->term_id, 5); ?>
		<ul class="category-stories">
			<?php foreach($cat_items as $item) : ?>
				<li><a href="<?php echo get_permalink($item->ID); ?>" title="<?php echo $item->post_title; ?>"><?php echo $item->post_title; ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
		<li class="meta-links"><a href="/about">About</a> spudWorks</li>
	</ul>
</div>