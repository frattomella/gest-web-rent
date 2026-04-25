<?php
/**
 * Single vehicle template.
 *
 * @package GestWebRent
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	echo GWR_Frontend::vehicle_detail_markup( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
endwhile;

get_footer();
