<?php

defined( 'ABSPATH' ) || exit;

/**
 * @var \Postqueue\Blocks\Postqueue $this
 * @var object $content
 * @var array $attributes
 */


$query = new WP_Query($content->args);
if($query->have_posts()):
	echo "<ul>";
	while($query->have_posts()){
		$query->the_post();
		echo "<li>" . wp_kses_post( get_the_title() ) . "</li>";
	}
	echo "</ul>";
endif;
wp_reset_postdata();


