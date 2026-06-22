<?php
/**
 * Theme footer: closes the main region, renders the three widget columns
 * and the colophon.
 */
?>
		</main><!-- #main -->

		<footer class="site-footer">
			<hr class="hazard-rule">
			<div class="site-footer__inner">
				<div class="site-footer__columns">
					<?php foreach (array('footer-1', 'footer-2', 'footer-3') as $footerColumn) : ?>
						<div class="site-footer__col">
							<?php if (is_active_sidebar($footerColumn)) {
								dynamic_sidebar($footerColumn);
							} ?>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="site-footer__copy">
					&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>
				</p>
			</div>
		</footer>

		<?php wp_footer(); ?>
		<!-- Google tag (gtag.js) -->
		 <?php if (is_preview()) : ?>
		<script async src="https://www.googletagmanager.com/gtag/js?id=G-WSGEZ2G4PP"></script>
		<?php endif; ?>
		<script>
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());

			gtag('config', 'G-WSGEZ2G4PP');
		</script>
	</body>
</html>
