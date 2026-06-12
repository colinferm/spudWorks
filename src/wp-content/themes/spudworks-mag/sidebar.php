<?php
/**
 * Showcase widget sidebar. Renders the registered "showcase-sidebar" area.
 * Outputs nothing if the area has no widgets.
 */
if (!is_active_sidebar('showcase-sidebar')) {
	return;
}
?>
<aside class="showcase-sidebar widget-area" aria-label="<?php esc_attr_e('Showcase sidebar', 'spudworks-mag'); ?>">
	<?php dynamic_sidebar('showcase-sidebar'); ?>
</aside>
