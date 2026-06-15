<?php
/**
 * Search form, used by every get_search_form() call.
 *
 * Two variants, chosen via $args (WP 5.2+ passes get_search_form()'s args here):
 *   - 'full'  : the big labelled form with a visible submit button. The 404 and
 *               search-results pages pass array('variant' => 'full').
 *   - default : the compact hazard-stripe box (same partial the header/footer and
 *               the styled core/search block use), so a legacy Search widget
 *               matches everything else. WP_Widget_Search calls get_search_form()
 *               with no args, so it lands here automatically.
 */
$variant = isset($args['variant']) ? $args['variant'] : 'compact';

if ($variant === 'full') :
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
	<label class="screen-reader-text" for="search-field"><?php esc_html_e('Search for:', 'spudworks-mag'); ?></label>
	<input type="search" id="search-field" class="search-form__field" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php esc_attr_e('Search the magazine&hellip;', 'spudworks-mag'); ?>">
	<button type="submit" class="search-form__submit"><?php esc_html_e('Search', 'spudworks-mag'); ?></button>
</form>
<?php else :
	get_template_part('templates/search-box', '', array('class' => 'search-box--widget'));
endif; ?>
