<?php

/**
 * Return the homepage rather than an author page URL.
 */
add_filter( 'author_link', function( $link ) {
	// Single-author blog.
	return home_url( '/' );
} );

add_action( 'template_redirect', function() {
	if ( is_admin() ) {
		return;
	}

	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

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
			'uid'  => 'https://twitter.com/ochtendgrijs',
			'name' => 'Twitter',
		),
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

add_filter( 'micropub_query', function( $response, $input ) {
	if ( ! isset( $input['q'] ) || 'config' !== $input['q'] ) {
		return $response;
	}

	if ( isset( $response['post-types'] ) ) {
		return $response;
	}

	$response['post-types'] = array(
		array(
			'type' => 'article',
			'name' => 'Article',
		),
		array(
			'type' => 'bookmark',
			'name' => 'Bookmark',
		),
		array(
			'type' => 'like',
			'name' => 'Like',
		),
		array(
			'type' => 'note',
			'name' => 'Note',
		),
		array(
			'type' => 'read',
			'name' => 'Read',
		),
		array(
			'type' => 'reply',
			'name' => 'Reply',
		),
	);

	return $response;
}, 10, 2 );

/**
 * Micropub syndication callback.
 */
add_action( 'micropub_syndication', function( $post_id, $synd_requested ) {
	$post      = get_post( $post_id );
	$syndicate = false;

	if ( in_array( 'https://twitter.com/ochtendgrijs', $synd_requested, true ) ) {
		// Update sharing setting.
		update_post_meta( $post_id, '_share_on_twitter', '1' );

		// Exclude drafts and such.
		if ( 'publish' === $post->post_status ) {
			$syndicate = true;
		}
	}

	if ( in_array( 'https://geekcompass.com/@ochtendgrijs', $synd_requested, true ) ) {
		// Update sharing setting.
		update_post_meta( $post_id, '_share_on_mastodon', '1' );

		// Exclude drafts and such.
		if ( 'publish' === $post->post_status ) {
			$syndicate = true;
		}
	}

	if ( in_array( 'https://pixelfed.social/janboddez', $synd_requested, true ) ) {
		// Update sharing setting.
		update_post_meta( $post_id, '_share_on_pixelfed', '1' );

		// Exclude drafts and such.
		if ( 'publish' === $post->post_status ) {
			$syndicate = true;
		}
	}

	if ( $syndicate ) {
		// Re-run the `transition_post_status` hook, in order to trigger
		// syndication.
		wp_transition_post_status( 'publish', 'publish', $post );
	}
}, 10, 2 );


add_filter( 'iwcpt_ignore_bookmark_titles', '__return_false' );

/**
 * Overrides default Micropub post content.
 *
 * This also strips `e-content` and such from Micropub entries, or at least the
 * types dealt with below, which I deal with in my theme. (I don't use very many
 * other types.)
 *
 * Also, these aren't Gutenberg-flavored, yet, which is OK. I haven't enabled
 * Gutenberg for the CPTs I'm using.
 */
add_filter( 'micropub_post_content', function( $post_content, $input ) {

	if ( ! empty( $input['properties']['like-of'][0] ) ) {

		$post_content = '<i>Vindt <a class="u-like-of" href="' . esc_url( $input['properties']['like-of'][0] ) . '">' . esc_url( $input['properties']['like-of'][0] ) . '</a> leuk.</i>';

	} elseif ( ! empty( $input['properties']['bookmark-of'][0] ) ) {

		$post_content  = '<i>Heeft <a class="u-bookmark-of" href="' . esc_url( $input['properties']['bookmark-of'][0] ) . '">' . esc_url( $input['properties']['bookmark-of'][0] ) . '</a> gebookmarkt.</i>';
		$post_content .= "\n\n" . '<div class="e-content" markdown="1">' . wp_strip_all_tags( $input['properties']['content'][0] ) . '</div>';

	} elseif ( ! empty( $input['properties']['repost-of'][0] ) ) {

		// To do: add richer content for known sources (Twitter, Nitter).
		$post_content  = '<i>Heeft <a class="u-repost-of" href="' . esc_url( $input['properties']['repost-of'][0] ) . '">' . esc_url( $input['properties']['repost-of'][0] ) . '</a> herpost.</i>';
		$post_content .= "\n\n" . '<div class="e-content" markdown="1">' . wp_strip_all_tags( $input['properties']['content'][0] ) . '</div>';

	} elseif ( ! empty( $input['properties']['in-reply-to'][0] ) && false === strpos( $input['properties']['in-reply-to'][0], $post_content ) ) {

		// Reply, yet missing a backlink. Won't do anything if a backlink's already present, i.e., was manually added.
		$post_content  = '<i>Als antwoord op <a class="u-in-reply-to" href="' . esc_url( $input['properties']['in-reply-to'][0] ) . '">' . esc_url( $input['properties']['in-reply-to'][0] ) . '</a>.</i>';
		$post_content .= "\n\n" . '<div class="e-content" markdown="1">' . wp_strip_all_tags( $input['properties']['content'][0] ) . '</div>';

	} elseif ( ! empty( $input['properties']['content'][0] ) && empty( $input['post_title'] ) ) {

		// Note.
		$post_content = wp_strip_all_tags( $post_content );

	}

	return $post_content;

}, 10, 2 );

add_filter( 'share_on_mastodon_status', 'ochtendgrijs_status', 10, 2 );
add_filter( 'share_on_twitter_status', 'ochtendgrijs_status', 10, 2 );
/**
 * Overrides default "Share on Mastodon/Twitter" statuses.
 *
 * @param string  $status Status.
 * @param WP_Post $post   Post object.
 */
function ochtendgrijs_status( $status, $post ) {
	if ( 'iwcpt_note' === $post->post_type ) {
		// Fetch post content. Post types that use Markdown (through Jetpack)
		// have their raw content stored in `post_content_filtered`. Using this
		// to retain things like `>` to indicate blockquotes. (HTML tags are
		// stripped, after all.)
		if ( '1' === get_post_meta( $post->ID, '_wpcom_is_markdown', true ) ) {
			$content = $post->post_content_filtered;
		}

		if ( empty( $content ) ) {
			$content = get_the_content( null, false, $post );
		}

		// Apply `the_content` filters so as to have smart quotes and what not.
		$status = apply_filters( 'the_content', $content );

		// Strip tags, but leave hyperlinks in place. We'll need them to try to
		// correctly thread notes on Mastodon. Cf. the
		// `share_on_mastodon_toot_args` callback in this very file.
		$status = strip_tags( $status, '<a>' );

		// Remove leading spaces from each line.
		$status = preg_replace( '/^ +/m', '', $status );

		// Add an extra newline in between block-level elements.
		$status = str_replace( "\n", "\n\n", $status );

		// Avoid double-encoded entities.
		$status = html_entity_decode( $status, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$status = trim( $status );
	}

	// Remove bookmark introductions, and go with a simple link at the end.
	if ( preg_match( '#Heeft <a(?:.+?)href="(.+?)"(?:.*?)>(?:.+?)</a> gebookmarkt.#', $status, $matches ) ) {
		$status = str_replace( $matches[0], '', $status ) . "\n\n" . $matches[1];
	}

	$tags = get_the_terms( $post->ID, 'iwcpt_tag' );

	if ( $tags ) {
		$status .= "\n\n";

		foreach ( $tags as $tag ) {
			// Append tags as hashtags.
			$tag_name = $tag->name;

			if ( false !== preg_match( '/\s+/', $tag_name ) ) {
				// Try to CamelCase multi-word tags.
				$tag_name = preg_replace( '/\s+/', ' ', $tag->name );
				$tag_name = explode( ' ', $tag_name );
				$tag_name = implode( '', array_map( 'ucfirst', $tag_name ) );
			}

			$status .= '#' . $tag_name . ' ';
		}

		// Remove (leading and) trailing spaces.
		$status = trim( $status );
	}

	if ( false === strpos( $status, get_permalink( $post ) ) ) {
		// Append permalink. Might eventually make this a _permashortlink_.
		$status .= "\n\n" . '(' . get_permalink( $post ) . ')';
	}

	// That's it!
	return $status;
}

add_filter( 'share_on_mastodon_toot_args', 'ochtendgrijs_args' );
add_filter( 'share_on_twitter_tweet_args', 'ochtendgrijs_args' );
/**
 * Attempts to correctly thread replies on Mastodon/Twitter.
 *
 * @param array $args POST parameters.
 */
function ochtendgrijs_args( $args ) {
	$status = $args['status'];

	$post = null;

	// This is why we couldn't yet strip `a` tags from statuses.
	$pattern = '#<a(?:.+?)href="' . home_url( '/notes/' ) . '(.+?)"(?:.*?)>(?:.+?)</a>#'; // `notes`, without `front`, is what's definied as our notes archive's permalink slug.

	if ( preg_match( $pattern, $status, $matches ) ) {
		// Status contains a link to a note of our own. Try to fetch that note.
		$post = get_page_by_path( rtrim( $matches[1], '/' ), OBJECT, array( 'iwcpt_note' ) );
	} else {
		// Same thing, but for articles. Only one of these (or none) will
		// match, ever. Needs to be in an else block, as previous `$matches`
		// mustn't be overridden.
		$pattern = '#<a(?:.+?)href="' . home_url( '/blog/' ) . '(.+?)"(?:.*?)>(?:.+?)</a>#'; // The exact argument of `home_url()` would depend on your permalink `front`.

		if ( preg_match( $pattern, $status, $matches ) ) {
			// Status contains a link to an article of our own. Try to fetch it.
			$post = get_page_by_path( rtrim( $matches[1], '/' ), OBJECT, array( 'post' ) );
		}
	}

	if ( $post ) {
		if ( 'share_on_mastodon_toot_args' === current_filter() ) {
			// If it was posted to Mastodon before, we should be able to fetch
			// its toot ID.
			$toot_id = basename( get_post_meta( $post->ID, '_share_on_mastodon_url', true ) );

			if ( ! empty( $toot_id ) ) {
				// Add to existing thread.
				$args['in_reply_to_id'] = $toot_id;

				// Remove the (now redundant) introductory line.
				$status = str_replace( "Als antwoord op {$matches[0]}.", '', $status );
			}
		} elseif ( 'share_on_twitter_tweet_args' === current_filter() ) {
			$toot_id = basename( get_post_meta( $post->ID, '_share_on_twitter_url', true ) );

			if ( ! empty( $toot_id ) ) {
				$args['in_reply_to_status_id'] = $toot_id;

				$status = str_replace( "Als antwoord op {$matches[0]}.", '', $status );
				$status = '@ochtendgrijs ' . $status;
			}
		}
	} elseif ( isset( $matches[1] ) ) {
		error_log( "Could not convert URL to post ID for the note with slug {$matches[1]}." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	// Only now can we strip _all_ HTML tags.
	$args['status'] = trim( wp_strip_all_tags( $status ) );

	return $args;
}

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
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// Let WordPress take it from here. (This works, somehow.)
	);
} );
