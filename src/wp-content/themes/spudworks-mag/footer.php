<?php
/**
 * Theme footer: closes the main region, renders the footer menu + colophon.
 */
?>
		</main><!-- #main -->

		<footer class="site-footer">
			<hr class="hazard-rule">
			<div class="site-footer__inner">
				<div class="site-footer__top">
					<?php if (has_nav_menu('footer')) : ?>
						<nav class="footer-nav" aria-label="<?php esc_attr_e('Footer', 'spudworks-mag'); ?>">
							<?php
							wp_nav_menu(array(
								'theme_location' => 'footer',
								'container'      => false,
								'menu_class'     => 'footer-nav__list',
								'fallback_cb'    => false,
								'depth'          => 1
							));
							?>
						</nav>
					<?php endif; ?>

					<?php get_template_part('templates/search-box', '', array('class' => 'search-box--footer')); ?>
				</div>

				<p class="site-footer__copy">
					&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>
				</p>
			</div>
		</footer>

		<?php wp_footer(); ?>
		<!-- Google tag (gtag.js) -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=G-WSGEZ2G4PP"></script>
		<script>
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());

			gtag('config', 'G-WSGEZ2G4PP');
		</script>
	</body>
</html>
