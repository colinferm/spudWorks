<?php
/**
 * Compact search box used in the floating header and the footer.
 *
 * $args: class (string) extra modifier class, e.g. 'search-box--header'.
 *
 * Uses aria-label rather than a visible label so it can appear more than once
 * on a page without duplicate ids.
 */
$extra = isset($args['class']) ? ' ' . $args['class'] : '';
?>
<form role="search" method="get" class="search-box<?php echo esc_attr($extra); ?>" action="<?php echo esc_url(home_url('/')); ?>">
	<input
		type="search"
		class="search-box__field"
		name="s"
		value="<?php echo esc_attr(get_search_query()); ?>"
		placeholder="<?php esc_attr_e('search', 'spudworks-mag'); ?>"
		aria-label="<?php esc_attr_e('Search', 'spudworks-mag'); ?>">
</form>
