<?php
/**
 * Plugin Name: Popular WordPress Posts
 * Plugin URI: https://github.com/cleancoded/popular-wordpress-posts
 * Description: Keeps track of your most popular WordPress posts for display within the site 
 * Version: 1.1.1
 * Author: CLEANCODED
 * Author URI: https://cleancoded.com
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation.  You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

class cc_Stats {
	var $instance;

	function __construct() {
		$this->instance =& $this;
		add_action( 'init', array( $this, 'get_popular_posts' ) );
		add_filter( 'display_posts_shortcode_args', array( $this, 'display_posts' ), 10, 2 );
	}

	/**
	 * Get Popular Posts
	 *
	 * On a logged in user's pageload, checks to see if stats data is available. If it isn't,
	 * it has expired (24 hours) so fetches new data, filters the list to just posts, deletes
	 * current stats post meta on all posts, and updates stats post meta based on new data.
	 *
	 * Access current stat data through post meta. Ex:
	 * $args = array(
	 *		'meta_key'       => 'cc_stats',
	 * 		'posts_per_page' => 10,
	 * 		'orderby'        => 'meta_value_num',
	 * 		'order'          => 'ASC',
	 * );
	 * $loop = new WP_Query( $args );
	 *
	 * Filters:
	 * `cc_stats_args` - arguments passed to WordPress.com Stats API.
	 * Default: array( 'days' => 30, 'limit' => 100 )
	 *
	 * `cc_stats_update` - Conditional for determining if stats data should be saved
	 * Default: 'post' == get_post_type()
	 *
	 */
	function get_popular_posts() {

		// Make sure the function we need from Jetpack is available
		if( !function_exists( 'stats_get_csv' ) )
			return;

		// Only update for logged in page views
		if( !is_user_logged_in() )
			return;

		$post_view_ids = get_transient( 'cc_stats' );
		if( false === $post_view_ids ) {

			$post_view_posts = stats_get_csv( 'postviews', apply_filters( 'cc_stats_args', array( 'days' => 30, 'limit' => 100 ) ) );
			if ( !$post_view_posts ) {
				$post_view_ids = array();
			}

			$post_view_ids = array_filter( wp_list_pluck( $post_view_posts, 'post_id' ) );
			if ( !$post_view_ids ) {
				$post_view_ids = array();
			}

			set_transient( 'cc_stats', $post_view_ids, 60*60*24 );

			// Delete old popular post list
			$args = array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'meta_key'       => 'cc_stats',
			);
			$loop = new WP_Query( $args );
			if( $loop->have_posts() ): while( $loop->have_posts() ): $loop->the_post(); global $post;
				delete_post_meta( $post->ID, 'cc_stats' );
			endwhile; endif; wp_reset_postdata();

			// Update new popular post list
			$count = 1;
			foreach( $post_view_ids as $id ) {
				if( apply_filters( 'cc_stats_update', 'post' == get_post_type( $id ), $id ) ) {
					update_post_meta( $id, 'cc_stats', $count );
					$count++;
				}

			}
		}

	}

	/**
	 * Display Posts Integration
	 *
	 * If you have the Display Posts Shortcode plugin installed, you can query based on popularity. Ex:
	 * [display-posts orderby="popular"]
	 *
	 * @param array $args, WP Query Arguments
	 * @param array $original_atts, Shortcode Attributes
	 * @return array $args
	 */
	function display_posts( $args, $original_atts ) {
		if( ! isset( $original_atts['orderby'] ) || 'popular' !== $original_atts['orderby'] )
			return $args;

		$args['orderby'] = 'meta_value_num';
		$args['meta_key'] = 'cc_stats';
		$args['order'] = 'ASC';
		return $args;
	}

}
new cc_Stats;
