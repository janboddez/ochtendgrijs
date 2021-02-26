<?php

/**
 * Load parent styles.
 */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'twentytwenty-style', get_template_directory_uri() . '/style.css' ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
} );

/**
 * Enable i18n.
 */
add_action( 'after_setup_theme', function() {
	load_child_theme_textdomain( 'twentytwenty-child', get_stylesheet_directory() . '/languages' );
} );

/**
 * Add `h-feed` to archive pages.
 */
add_filter( 'body_class', function( $classes ) {
	if ( in_array( 'h-feed', $classes, true ) ) {
		return $classes;
	}

	if ( ! ( is_home() || is_archive() || is_search() ) ) {
		return $classes;
	}

	$classes[] = 'h-feed';

	return $classes;
}, 999 );

/**
 * Add `h-entry` to posts.
 */
add_filter( 'post_class', function( $classes ) {
	if ( in_array( 'h-entry', $classes, true ) ) {
		return $classes;
	}

	if ( is_admin() ) {
		return $classes;
	}

	$classes[] = 'h-entry';

	return $classes;
}, 999 );

/**
 * Wraps `the_content` in `e-content`.
 */
add_filter( 'the_content', function( $content ) {
	if ( is_feed() ) {
		return $content;
	}

	// Allow either single or double quotes. Technically, no quotes would be
	// allowed, too, but let's not go there.
	if ( ! preg_match( '~ class=("|\')([^"\']*?)e-content([^"\']*?)("|\')~', $content ) ) {
		return '<div class="e-content">' . $content . '</div>';
	}

	return $content;
}, 999 );

/**
 * Wraps `the_excerpt` in `p-summary`.
 */
add_filter( 'the_excerpt', function( $content ) {
	if ( is_feed() ) {
		return $content;
	}

	if ( ! preg_match( '~ class=("|\')([^"\']*?)p-summary([^"\']*?)("|\')~', $content ) ) {
		return '<div class="p-summary">' . $content . '</div>';
	}

	return $content;
}, 999 );

/**
 * Adds support for Fediverse social icons.
 */
if ( class_exists( 'Fediverse_Icons_Jetpack' ) ) :
	// Unhook the plugin's "default" callback.
	remove_filter( 'walker_nav_menu_start_el', array( Fediverse_Icons_Jetpack::get_instance(), 'apply_icon' ), 100 );

	// And add our own instead.
	add_filter( 'walker_nav_menu_start_el', function( $item_output, $item, $depth, $args ) {
		if ( ! class_exists( 'Jetpack' ) ) {
			// Jetpack not installed?
			return $item_output;
		}

		$social_icons = array(
			'Diaspora'   => 'diaspora',
			'Friendica'  => 'friendica',
			'GNU Social' => 'gnu-social',
			'Mastodon'   => 'mastodon',
			'PeerTube'   => 'peertube',
			'Pixelfed'   => 'pixelfed',
		);

		if ( 'social' === $args->theme_location ) {
			// Twenty Twenty's social menu.
			foreach ( $social_icons as $attr => $value ) {
				if ( false !== stripos( $item_output, $attr ) ) {
					// Only for above Fediverse platforms, replace the icon
					// previously added by Twenty Twenty.
					$item_output = preg_replace(
						'@<svg(.+?)</svg>@i',
						jetpack_social_menu_get_svg( array( 'icon' => esc_attr( $value ) ) ),
						$item_output
					);
				}
			}
		}

		return $item_output;
	}, 100, 4 );
endif;

/**
 * Removes comments counter (and link) from post meta.
 */
add_filter( 'twentytwenty_post_meta_location_single_top', function( $post_meta ) {
	$key = array_search( 'comments', $post_meta, true );

	if ( false !== $key ) {
		unset( $post_meta[ $key ] );
	}

	return $post_meta;
} );

/**
 * Adds syndication links to post meta.
 */
add_action( 'twentytwenty_end_of_post_meta_list', function( $post_id, $post_meta, $location ) {
	if ( 'single-top' !== $location ) {
		return;
	}

	$syndication_links = array(
		'Mastodon' => get_post_meta( $post_id, '_share_on_mastodon_url', true ),
		'Twitter'  => get_post_meta( $post_id, '_share_on_twitter_url', true ),
	);

	// Remove empty values.
	$syndication_links = array_filter( $syndication_links );

	if ( ! empty( $syndication_links ) ) {
		?>
		<li class="post-sticky meta-wrapper">
			<span class="meta-text">
				<?php
				$output = '';

				foreach ( $syndication_links as $name => $url ) {
					$output .= '<a class="u-syndication" href="' . esc_url( $url ) . '">' . $name . '</a>, '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}

				$output = substr( $output, 0, -2 );

				/* translators: syndication links */
				printf( __( 'Also on %s', 'twentytwenty-child' ), $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</span>
		</li>
		<?php
	}
}, 10, 3 );

/**
 * Merely displays the result of `iw_get_post_meta()`.
 *
 * @param int    $post_id  Post ID.
 * @param string $location Which post meta location to output.
 */
function iw_the_post_meta( $post_id = null, $location = 'single-top' ) {
	echo iw_get_post_meta( $post_id, $location ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in twentytwenty_get_post_meta().
}

/**
 * Adds `h-card` info to author meta, and a `u-url` to permalinks and so on.
 *
 * Too big a function, pretty much lifted from Twenty Twenty.
 *
 * @param int    $post_id  Post ID.
 * @param string $location Which post meta location to output.
 */
function iw_get_post_meta( $post_id = null, $location = 'single-top' ) {
	// @codingStandardsIgnoreStart
	// Require post ID.
	if ( ! $post_id ) {
		return;
	}

	/**
	 * Filters post types array.
	 *
	 * This filter can be used to hide post meta information of post, page or custom post type
	 * registered by child themes or plugins.
	 *
	 * @since Twenty Twenty 1.0
	 *
	 * @param array Array of post types
	 */
	$disallowed_post_types = apply_filters( 'twentytwenty_disallowed_post_types_for_meta_output', array( 'page' ) );

	// Check whether the post type is allowed to output post meta.
	if ( in_array( get_post_type( $post_id ), $disallowed_post_types, true ) ) {
		return;
	}

	$post_meta_wrapper_classes = '';
	$post_meta_classes         = '';

	// Get the post meta settings for the location specified.
	if ( 'single-top' === $location ) {
		/**
		 * Filters post meta info visibility.
		 *
		 * Use this filter to hide post meta information like Author, Post date, Comments, Is sticky status.
		 *
		 * @since Twenty Twenty 1.0
		 *
		 * @param array $args {
		 *  @type string 'author'
		 *  @type string 'post-date'
		 *  @type string 'comments'
		 *  @type string 'sticky'
		 * }
		 */
		$post_meta = apply_filters(
			'twentytwenty_post_meta_location_single_top',
			array(
				'author',
				'post-date',
				'comments',
				'sticky',
			)
		);

		$post_meta_wrapper_classes = ' post-meta-single post-meta-single-top';
	} elseif ( 'single-bottom' === $location ) {
		/**
		 * Filters post tags visibility.
		 *
		 * Use this filter to hide post tags.
		 *
		 * @since Twenty Twenty 1.0
		 *
		 * @param array $args {
		 *   @type string 'tags'
		 * }
		 */
		$post_meta = apply_filters(
			'twentytwenty_post_meta_location_single_bottom',
			array(
				'tags',
			)
		);

		$post_meta_wrapper_classes = ' post-meta-single post-meta-single-bottom';
	}

	// If the post meta setting has the value 'empty', it's explicitly empty and the default post meta shouldn't be output.
	if ( $post_meta && ! in_array( 'empty', $post_meta, true ) ) {
		// Make sure we don't output an empty container.
		$has_meta = false;

		global $post;
		$the_post = get_post( $post_id );
		setup_postdata( $the_post );

		ob_start();
		?>

		<div class="post-meta-wrapper<?php echo esc_attr( $post_meta_wrapper_classes ); ?>">
			<ul class="post-meta<?php echo esc_attr( $post_meta_classes ); ?>">
				<?php
				/**
				 * Fires before post meta HTML display.
				 *
				 * Allow output of additional post meta info to be added by child themes and plugins.
				 *
				 * @since Twenty Twenty 1.0
				 * @since Twenty Twenty 1.1 Added the `$post_meta` and `$location` parameters.
				 *
				 * @param int    $post_id   Post ID.
				 * @param array  $post_meta An array of post meta information.
				 * @param string $location  The location where the meta is shown.
				 *                          Accepts 'single-top' or 'single-bottom'.
				 */
				do_action( 'twentytwenty_start_of_post_meta_list', $post_id, $post_meta, $location );

				// Author.
				if ( post_type_supports( get_post_type( $post_id ), 'author' ) && in_array( 'author', $post_meta, true ) ) {
					$has_meta = true;
					?>
					<li class="post-author meta-wrapper screen-reader-text">
						<span class="meta-icon">
							<span class="screen-reader-text"><?php _e( 'Post author', 'twentytwenty' ); ?></span>
							<?php twentytwenty_the_theme_svg( 'user' ); ?>
						</span>
						<span class="meta-text">
							<?php
							printf(
								/* translators: %s: Author name. */
								__( 'By %s', 'twentytwenty' ),
								'<span class="p-author h-card"><a class="u-url" rel="me" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '"><span class="p-name">' . esc_html( get_the_author_meta( 'display_name' ) ) . '</span></a></span>'
							);
							?>
						</span>
					</li>
					<?php
				}

				// Post date.
				if ( in_array( 'post-date', $post_meta, true ) ) {
					$has_meta = true;
					?>
					<li class="post-date meta-wrapper">
						<span class="meta-icon">
							<span class="screen-reader-text"><?php _e( 'Post date', 'twentytwenty' ); ?></span>
							<?php twentytwenty_the_theme_svg( 'calendar' ); ?>
						</span>
						<span class="meta-text">
							<a rel="bookmark" class="u-url" href="<?php the_permalink(); ?>"><time class="dt-published" datetime="<?php the_time( 'c' ); ?>"><?php the_time( get_option( 'date_format' ) ); ?></time></a>
						</span>
					</li>
					<?php
				}

				// Categories.
				if ( in_array( 'categories', $post_meta, true ) && has_category() ) {
					$has_meta = true;
					?>
					<li class="post-categories meta-wrapper">
						<span class="meta-icon">
							<span class="screen-reader-text"><?php _e( 'Categories', 'twentytwenty' ); ?></span>
							<?php twentytwenty_the_theme_svg( 'folder' ); ?>
						</span>
						<span class="meta-text">
							<?php _ex( 'In', 'A string that is output before one or more categories', 'twentytwenty' ); ?> <?php the_category( ', ' ); ?>
						</span>
					</li>
					<?php

				}

				// Tags.
				if ( in_array( 'tags', $post_meta, true ) && has_tag() ) {
					$has_meta = true;
					?>
					<li class="post-tags meta-wrapper">
						<span class="meta-icon">
							<span class="screen-reader-text"><?php _e( 'Tags', 'twentytwenty' ); ?></span>
							<?php twentytwenty_the_theme_svg( 'tag' ); ?>
						</span>
						<span class="meta-text">
							<?php the_tags( '', ', ', '' ); ?>
						</span>
					</li>
					<?php
				}

				// Comments link.
				if ( in_array( 'comments', $post_meta, true ) && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
					$has_meta = true;
					?>
					<li class="post-comment-link meta-wrapper">
						<span class="meta-icon">
							<?php twentytwenty_the_theme_svg( 'comment' ); ?>
						</span>
						<span class="meta-text">
							<?php comments_popup_link(); ?>
						</span>
					</li>
					<?php
				}

				// Sticky.
				if ( in_array( 'sticky', $post_meta, true ) && is_sticky() ) {
					$has_meta = true;
					?>
					<li class="post-sticky meta-wrapper">
						<span class="meta-icon">
							<?php twentytwenty_the_theme_svg( 'bookmark' ); ?>
						</span>
						<span class="meta-text">
							<?php _e( 'Sticky post', 'twentytwenty' ); ?>
						</span>
					</li>
					<?php
				}

				/**
				 * Fires after post meta HTML display.
				 *
				 * Allow output of additional post meta info to be added by child themes and plugins.
				 *
				 * @since Twenty Twenty 1.0
				 * @since Twenty Twenty 1.1 Added the `$post_meta` and `$location` parameters.
				 *
				 * @param int    $post_id   Post ID.
				 * @param array  $post_meta An array of post meta information.
				 * @param string $location  The location where the meta is shown.
				 *                          Accepts 'single-top' or 'single-bottom'.
				 */
				do_action( 'twentytwenty_end_of_post_meta_list', $post_id, $post_meta, $location );
				?>
			</ul><!-- .post-meta -->
		</div><!-- .post-meta-wrapper -->

		<?php
		wp_reset_postdata();
		$meta_output = ob_get_clean();

		// If there is meta to output, return it.
		if ( $has_meta && $meta_output ) {
			return $meta_output;
		}
	}
	// @codingStandardsIgnoreEnd
}
