<?php

defined( 'ABSPATH' ) || exit;
/**
 * @var $queue array queue object for shortcode
 * @var $store \Postqueue\Store the store object
 * @var $query_args array WP_Query arguments
 * @var $viewmode string optional viewmode attribute on shortcode
 * @var $offset number of posts to skip
 * @var $limit number of posts use
 */

echo '<ul class="postqueue__list">';

$query = new WP_Query( $query_args );
while ( $query->have_posts() ) {
	$query->the_post();
	printf(
		'<li class="%s"><a href="%s">%s</a></li>',
		esc_attr( trim( 'postqueue__list-item ' . $viewmode ) ),
		esc_url( get_the_permalink() ),
		wp_kses_post( get_the_title() )
	);
}

echo '</ul>';