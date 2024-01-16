<?php
/**
 * Used for utility functions.
 */

namespace NewsPress\Revisions;

/**
 * Get currently enqueued scripts
 *
 * @return array
 */
function get_enqueued_scripts(): array {
	global $wp_scripts;
	return array_values( $wp_scripts->queue );
}

/**
 * Get currently enqueued styles
 *
 * @return array
 */
function get_enqueued_styles(): array {
	global $wp_styles;
	return array_values( $wp_styles->queue );
}

/**
 * Remove assets from revisions view
 */
function clean_assets_for_revisions() {
	$scripts = get_enqueued_scripts();
	$styles  = get_enqueued_styles();

	$scripts_to_remove = apply_filters( 'newspress_revisions_remove_scripts', [], $scripts );
	$styles_to_remove  = apply_filters( 'newspress_revisions_remove_styles', [], $styles );

	foreach ( $scripts_to_remove as $script ) {
		wp_dequeue_script( $script );
	}

	foreach ( $styles_to_remove as $style ) {
		wp_dequeue_style( $style );
	}
}

/**
 * Get's id of revision that represent the current post.
 *
 * @param integer $post_id post id to get the latest revision for.
 * @return int id of the revision 0 if non found.
 */
function get_latest_revision( int $post_id ) {
	$args = [
		'post_parent'         => $post_id,
		'name'                => "{$post_id}-revision-v1",
		'fields'              => 'ids',
		'post_type'           => 'revision',
		'post_status'         => 'inherit',
		'order'               => 'DESC',
		'orderby'             => 'date ID',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
	];

	$revision_query = new \WP_Query();
	$revisions      = $revision_query->query( $args );

	if ( ! $revisions ) {
		return 0;
	}

	return $revisions[0];
}

/**
 * Delete all autosaves for post that are older than current post modified date.
 *
 * @param integer $post_id id of post to delete old autosave.
 * @return void
 */
function delete_all_autosaves( int $post_id ) {
	$args = [
		'post_parent'         => $post_id,
		'name'                => "{$post_id}-autosave-v1",
		'post_type'           => 'revision',
		'post_status'         => 'inherit',
		'order'               => 'DESC',
		'orderby'             => 'date ID',
		'posts_per_page'      => -1,
		'ignore_sticky_posts' => true,
	];

	$revision_query = new \WP_Query();
	$autosaves      = $revision_query->query( $args );

	if ( 0 === $revision_query->found_posts ) {
		return;
	}

	$post = get_post( $post_id );

	if ( ! $post ) {
		return;
	}
	
	foreach ( $autosaves as $autosave ) {
		if ( mysql2date( 'U', $autosave->post_modified_gmt, false ) > mysql2date( 'U', $post->post_modified_gmt, false ) ) {
			continue;
		}

		// Autosave is older than current post so delete the autosave!
		wp_delete_post_revision( $autosave->ID );
	}
}

/**
 * Determines whether or not autosaves should be integrated into revisions view.
 * 
 * By default it's enabled, disabling autosaves will hide autosave notices and stop
 * autosaves appearing within the revisions view.
 *
 * @return boolean
 */
function should_handle_autosave() {
	return apply_filters( 'newspress_revisions_handle_autosaves', true );
}

if ( ! function_exists( 'rest_get_route_for_post_type_items' ) ) {
	/**
	 * Copy of rest_get_route_for_post_type_items from 5.9 WordPress core, so that ealier versions of WordPress
	 * will work.
	 *
	 * Gets the REST API route for a post type.
	 *
	 * @param string $post_type The name of a registered post type.
	 * @return string The route path with a leading slash for the given post type, or an empty string if there is not a route.
	 */
	function rest_get_route_for_post_type_items( $post_type ) {
		$post_type = get_post_type_object( $post_type );
		if ( ! $post_type ) {
			return '';
		}

		if ( ! $post_type->show_in_rest ) {
			return '';
		}

		$namespace = ! empty( $post_type->rest_namespace ) ? $post_type->rest_namespace : 'wp/v2';
		$rest_base = ! empty( $post_type->rest_base ) ? $post_type->rest_base : $post_type->name;
		$route     = sprintf( '/%s/%s', $namespace, $rest_base );

		/**
		 * Filters the REST API route for a post type.
		 *
		 * @since 5.9.0
		 *
		 * @param string       $route      The route path.
		 * @param WP_Post_Type $post_type  The post type object.
		 */
		return apply_filters( 'rest_route_for_post_type_items', $route, $post_type );
	}
}
