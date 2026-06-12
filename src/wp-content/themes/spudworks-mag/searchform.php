<?php
/**
 * Search form. Used by get_search_form() on the search and 404 templates.
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
	<label class="screen-reader-text" for="search-field"><?php esc_html_e('Search for:', 'spudworks-mag'); ?></label>
	<input type="search" id="search-field" class="search-form__field" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php esc_attr_e('Search the magazine&hellip;', 'spudworks-mag'); ?>">
	<button type="submit" class="search-form__submit"><?php esc_html_e('Search', 'spudworks-mag'); ?></button>
</form>
