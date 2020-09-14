<?php

/**
 * Forces hidden custom fields to be shown.
 */
add_filter( 'is_protected_meta', '__return_false', 999 );

/**
 * Readability: widens the 'slug' field on Edit Post screens in WP Admin.
 */
add_action( 'admin_head', function() {
	?>
	<style rel="stylesheet" type="text/css" media="all">
	input#post_name {
		width: 100%;
	}
	</style>
	<?php
} );

/**
 * Somewhat declutter our `head`.
 */
add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'the_generator', function() {
	return '';
} );

add_action( 'wp_head', function() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
}, 1 );

/**
 * Add (Jetpack) Markdown support to our IndieWeb CPTs.
 */
add_action('init', function() {
	add_post_type_support( 'iwcpt_note', 'wpcom-markdown' );
	add_post_type_support( 'iwcpt_like', 'wpcom-markdown' );
} );

/**
 * Post types that support outgoing webmentions.
 */
add_filter( 'webmention_comments_post_types', function( $supported_post_types ) {
	return array( 'post', 'iwcpt_like', 'iwcpt_note' );
} );

/**
 * Allow <small> inside comments.
 */
add_filter( 'pre_comment_content', function( $comment_content ) {
	global $allowedtags;
	$allowedtags['small'] = array( 'class' => array() );
	return $comment_content;
} );
