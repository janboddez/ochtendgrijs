<?php

/**
 * Forces hidden custom fields to be shown.
 */
add_filter( 'is_protected_meta', '__return_false', 999 );

/**
 * Readability: widens the 'slug' field on Edit Post screens in WP Admin.
 */
add_action( 'admin_head', function() {
	// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
	<style rel="stylesheet" type="text/css" media="all">
	input#post_name {
		width: 100%;
	}

	#post-type-display {
		font-weight: 600;
	}
	</style>
	<?php
} );

/**
 * Somewhat declutter our `head`.
 */
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_generator' );

// Disable feeds, except RSS2, for which we're using a modified template.
add_action( 'template_redirect', function() {
	if ( is_feed() && ! is_feed( 'rss2' ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		// Note: the above isn't enough if we want to display a (HTML) 404 page,
		// too.
		header( 'Content-Type: text/html' );
		locate_template( '404.php', true, true );
		exit;
	}
} );

// Remove the comment feeds link.
add_filter( 'feed_links_show_comments_feed', '__return_false' );

// And, I believe, the feed generator tag.
add_filter( 'the_generator', function() {
	return '';
} );

// Note: Leave XML-RPC enabled for tests involving the official WP Android app.
add_filter( 'xmlrpc_enabled', '__return_false' );
remove_action( 'wp_head', 'rsd_link' );

/**
 * Add (Jetpack) Markdown support to our IndieWeb CPTs.
 */
add_action( 'init', function() {
	add_post_type_support( 'iwcpt_note', 'wpcom-markdown' );
} );

/**
 * Include microblog posts in search results.
 */
add_filter( 'pre_get_posts', function( $query ) {
	if ( $query->is_search ) {
		$query->set( 'post_type', array( 'post', 'page', 'iwcpt_note' ) );
	}

	return $query;
} );

/**
 * Include microblog posts in the site's main RSS feed.
 */
add_filter( 'request', function( $request ) {
	// Leave dedicated CPT feeds alone.
	if ( isset( $request['feed'] ) && ! isset( $request['post_type'] ) ) {
		$request['post_type'] = array( 'post', 'iwcpt_note' );
	}

	return $request;
}, 9 );

/**
 * Define a new feed URL for just articles (i.e., WordPress's default "posts").
 *
 * After first adding this rule, visit Settings > Permalinks.
 */
add_filter( 'generate_rewrite_rules', function( $wp_rewrite ) {
	if ( empty( $wp_rewrite->front ) ) {
		return $wp_rewrite;
	}

	$feed_rules = array(
		trim( $wp_rewrite->front, '/' ) . '/feed/?$' => 'index.php?post_type=post&feed=rss2',
	);

	$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;

	return $wp_rewrite;
} );

/**
 * Replaces "ugly" GUIDs with the post's permalink (which obviously shouldn't
 * change).
 */
add_filter( 'get_the_guid', function( $guid, $post_id ) {
	return esc_url( get_permalink( $post_id ) );
}, 10, 2 );

/**
 * Post types that support outgoing webmentions.
 */
add_filter( 'webmention_comments_post_types', function( $supported_post_types ) {
	return array( 'post', 'iwcpt_like', 'iwcpt_note' );
} );

/**
 * Allow `<small>` inside comments.
 */
add_filter( 'pre_comment_content', function( $comment_content ) {
	global $allowedtags;
	$allowedtags['small'] = array( 'class' => array() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	return $comment_content;
} );
