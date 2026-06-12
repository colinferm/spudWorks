<?php
/**
 * Article footer tags: the same term-chip styling used at the top of the
 * article, repeated below the author box. Renders nothing when the post has
 * no terms.
 *
 * Expects to run inside the loop.
 */
ob_start();
spudmagTermChips(get_the_ID(), 6);
$chips = ob_get_clean();

if (trim($chips) === '') {
	return;
}
?>
<div class="article-tags measure">
	<?php echo $chips; ?>
</div>
