<?php get_header(); ?>
<div class="container">
	<div class="row">
		<div class="col-8">
			<?php get_template_part('templates/story-card', '', array()); ?>
			<?php get_template_part('templates/story-card', '', array()); ?>
			<?php get_template_part('templates/story-card', '', array()); ?>
			<?php get_template_part('templates/story-card', '', array()); ?>
			<?php get_template_part('templates/story-card', '', array()); ?>

			<nav>
				<ul class="pagination justify-content-end">
					<li class="page-item">
						<a class="page-link link-secondary" href="#" aria-label="Previous">
							<span aria-hidden="true">&laquo;</span>
						</a>
					</li>
					<li class="page-item"><a class="page-link link-secondary" href="#">1</a></li>
					<li class="page-item"><a class="page-link link-secondary" href="#">2</a></li>
					<li class="page-item"><a class="page-link link-secondary" href="#">3</a></li>
					<li class="page-item">
						<a class="page-link link-secondary" href="#" aria-label="Next">
							<span aria-hidden="true">&raquo;</span>
						</a>
					</li>
				</ul>
			</nav>
		</div>

		<div class="col-4 pt-3">
			<h4>Recent Items</h4>
			<?php get_template_part('templates/recent-item-card', '', array()); ?>
			<?php get_template_part('templates/recent-item-card', '', array()); ?>
			<?php get_template_part('templates/recent-item-card', '', array()); ?>
		</div>
	</div>
	<div class="row bg-dark text-white">
		<div class="col-4"></div>
		<div class="col-4 pt-4 pb-4">
			<p class="text-center">Copyright 2025 - Colin Andrew Ferm</p>
		</div>
		<div class="col-4"></div>
	</div>
</div>
<?php get_footer(); ?>