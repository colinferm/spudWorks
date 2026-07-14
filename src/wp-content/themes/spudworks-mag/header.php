<?php
/**
 * Theme header: document head, prominent site name, and the floating nav bar.
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<?php wp_head(); ?>
		<!-- MailerLite Universal -->
		<script>
			(function(w,d,e,u,f,l,n){w[f]=w[f]||function(){(w[f].q=w[f].q||[])
			.push(arguments);},l=d.createElement(e),l.async=1,l.src=u,
			n=d.getElementsByTagName(e)[0],n.parentNode.insertBefore(l,n);})
			(window,document,'script','https://assets.mailerlite.com/js/universal.js','ml');
			ml('account', '2509337');
		</script>
		<!-- End MailerLite Universal -->
	</head>
	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>
		<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'spudworks-mag'); ?></a>

		<header class="site-header">
			<div class="site-header__bar">
				<div class="site-header__brand">
					<a href="<?php echo esc_url(home_url('/')); ?>" rel="home" class="site-title">
						<?php bloginfo('name'); ?>
					</a>
					<?php $tagline = get_bloginfo('description', 'display'); ?>
					<?php if ($tagline) : ?>
						<span class="site-tagline"><?php echo esc_html($tagline); ?></span>
					<?php endif; ?>
				</div>

				<nav class="primary-nav" id="primary-nav" aria-label="<?php esc_attr_e('Primary', 'spudworks-mag'); ?>">
					<?php
					wp_nav_menu(array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'primary-nav__list',
						'fallback_cb'    => false,
						'depth'          => 2
					));
					?>
				</nav>

				<?php get_template_part('templates/search-box', '', array('class' => 'search-box--header')); ?>

				<button class="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="<?php esc_attr_e('Toggle menu', 'spudworks-mag'); ?>">
					<span class="nav-toggle__bars" aria-hidden="true"></span>
				</button>
			</div>
		</header>

		<main id="main" class="site-main">
