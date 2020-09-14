<?php

/**
 * Overrides default Micropub post content.
 *
 * This also strips `e-content` and such from Micropub entries, or at least the
 * types dealt with below, which I deal with in my theme. (I don't use very
 * many other types.)
 *
 * Also, these aren't Gutenberg-flavored, yet (they'd show up as a "Classic"
 * block), which is OK. I haven't enabled Gutenberg for the CPTs I'm using.
 * Wouldn't make sense, really, for such short, mostly plain-text entries.
 */
add_filter( 'micropub_post_content', function( $post_content, $input ) {
	if ( ! empty( $input['properties']['like-of'][0] ) ) {
		$post_content = '<i>Vindt <a class="u-like-of" href="' . esc_url( $input['properties']['like-of'][0] ) . '">' . esc_url( $input['properties']['like-of'][0] ) . '</a> leuk.</i>';
	}

	if ( ! empty( $input['properties']['bookmark-of'][0] ) ) {
		$post_content  = '<i>Heeft <a class="u-bookmark-of" href="' . esc_url( $input['properties']['bookmark-of'][0] ) . '">' . esc_url( $input['properties']['bookmark-of'][0] ) . '</a> gebookmarkt.</i>';
		$post_content .= "\n\n" . wp_strip_all_tags( $input['properties']['content'][0] );
	}

	if ( ! empty( $input['properties']['repost-of'][0] ) ) {
		// To do: add richer content for known sources (Twitter, Nitter).
		$post_content  = '<i>Heeft <a class="u-repost-of" href="' . esc_url( $input['properties']['repost-of'][0] ) . '">' . esc_url( $input['properties']['repost-of'][0] ) . '</a> herpost.</i>';
		$post_content .= "\n\n" . wp_strip_all_tags( $input['properties']['content'][0] );
	}

	if ( ! empty( $input['properties']['in-reply-to'][0] ) && false === stripos( $input['properties']['in-reply-to'][0], $post_content ) ) {
		// Reply, yet missing a backlink. Won't do anything if a backlink's already present, i.e., was manually added.
		$post_content  = '<i>Als antwoord op <a class="u-in-reply-to" href="' . esc_url( $input['properties']['in-reply-to'][0] ) . '">' . esc_url( $input['properties']['in-reply-to'][0] ) . '.</a></i>';
		$post_content .= "\n\n" . wp_strip_all_tags( $input['properties']['content'][0] );
	}

	return $post_content;
}, 10, 2 );

/**
 * My Microsub feed.
 */
add_action( 'wp_head', function() {
	?>
	<link rel="microsub" href="https://aperture.fstop.cloud/microsub/2">
	<?php
} );

/**
 * My Micropub config.
 */
add_filter( 'micropub_syndicate-to', function( $syndicate_to, $user_id ) {
	return array(
		array(
			'uid'  => 'https://geekcompass.com/@ochtendgrijs',
			'name' => 'Mastodon',
		),
		array(
			'uid'  => 'https://pixelfed.social/janboddez',
			'name' => 'Pixelfed',
		),
	);
}, 10, 2 );

/**
 * Have Notes and Likes appear right under Posts in WP Admin's main menu.
 */
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', function( $menu_ord ) {
	return array(
		'index.php', // Dashboard.
		'edit.php', // Posts.
		'edit.php?post_type=iwcpt_note', // Notes.
		'edit.php?post_type=iwcpt_like', // Likes.
		// Let WordPress take it from here. (This works, somehow.)
	);
} );
