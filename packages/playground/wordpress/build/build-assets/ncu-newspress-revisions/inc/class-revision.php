<?php

namespace NewsPress\Revisions;

/**
 * Class Revision
 */
class Revision {
	/**
	 * WP revision restore hook
	 *
	 * @var string
	 */
	public const RESTORE_REVISION_HOOK = 'wp_restore_post_revision';

	public const META_TO_IGNORE_HOOK = 'newspress.revisions.metaToIgnore';

	/**
	 * Revision Constructor
	 */
	public function __construct() {
		add_action( self::RESTORE_REVISION_HOOK, [ $this, 'replace_post_meta' ], 10, 2 );
		add_filter( 'rest_revision_query', [ $this, 'modify_revision_query' ], 10, 1 );
		add_action( 'future_to_publish', [ $this, 'create_revision_for_scheduled' ], 10, 1 );
	}

	/**
	 * A revision does not get created when a scheduled post is transitioned from future to
	 * publish because the post_updated action is not called and therefore wp_save_post_revision
	 * is never called after the change. This ensures that the post is updated in a way a revision
	 * will get created when the post status is transitioned from future to publish.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function create_revision_for_scheduled( $post ) {
		wp_update_post( $post );
	}

	/**
	 * Get a posts autosave ID's
	 *
	 * @param int  $parent - post id.
	 * @param bool $force_update - should we force update the meta.
	 * @return array
	 */
	public static function get_autosave_ids( $parent, $force_update = false ) {
		if ( $parent < 1 ) {
			return [];
		}

		$autosaves = get_post_meta( $parent, 'revision_autosave_ids', true );

		if ( ! ! $autosaves && ! $force_update ) {
			return $autosaves;
		}

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = %d AND post_name LIKE %s",
			$parent,
			'%autosave%'
		);

		$results = $wpdb->get_col( $query, 0 ); // phpcs:ignore

		update_post_meta( $parent, 'revision_autosave_ids', $results );

		return $results;
	}

	/**
	 * Modify revisions query to remove autosaves on revision page
	 *
	 * @param array $query_args - current query arguments
	 * @return array
	 */
	public function modify_revision_query( $query_args ) {
		// No need to modiy the query to remove autosaves, if were letting the plugin handle (display) them!
		if ( should_handle_autosave() ) {
			return $query_args;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return $query_args;
		}

		$screen = get_current_screen();

		if ( Revisions_View_Controller::PAGE_HOOK !== $screen->id ) {
			return $query_args;
		}

		$autosaves = self::get_autosave_ids( (int) $query_args['post_parent'] );

		if ( ! is_array( $autosaves ) || 0 === count( $autosaves ) ) {
			return $query_args;
		}

		$args = [
			'post__not_in' => array_merge( $query_args['post__not_in'] ?: [], $autosaves ),
		];

		return array_merge( $query_args, $args );
	}

	/**
	 * Process a multi dimensional
	 *
	 * @param int   $post_id - Post id.
	 * @param array $meta - post meta
	 * @return array
	 */
	public function process_meta( int $post_id, $meta = [] ) {
		$meta = array_map( function ( $item ) {
			$count = count( $item );

			if ( $count > 0 ) {
				return $item[0];
			}

			if ( 0 === $count ) {
				return null;
			}

			return $item;
		}, $meta );

		$exclusions = apply_filters( Meta::META_EXCLUSIONS_FILTER, [ '_edit_lock', Meta::TRANSITION_META_KEY, Meta::STATUS_META_KEY ], $post_id );

		return array_filter( $meta, function ( $key ) use ( $exclusions ) {
			return ! in_array( $key, $exclusions );
		}, ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Replace post metadata with revision metadata
	 *
	 * @param int $post_id - Post ID that is being updated
	 * @param int $revision_id - Revision ID that is being used.
	 */
	public function replace_post_meta( int $post_id, int $revision_id ) {
		// If restoring autosave & autosaves not integrated bypass custom meta handling.
		if ( ! should_handle_autosave() && wp_is_post_autosave( $revision_id ) ) {
			return;
		}

		$revision_meta = $this->process_meta( $revision_id, get_post_meta( $revision_id ) );
		$post_meta     = $this->process_meta( $post_id, get_post_meta( $post_id ) );

		$meta_to_remove = array_reduce( array_keys( $post_meta ), function ( $carry, $current ) use ( $revision_meta ) {
			if ( ! isset( $revision_meta[ $current ] ) ) {
				$carry[] = $current;
			}

			return $carry;
		}, [] );

		$meta_to_ignore = apply_filters( self::META_TO_IGNORE_HOOK, [
			Meta::STATUS_META_KEY,
			Meta::COUNT_META_KEY,
			Meta::LAST_STATUS_META_KEY,
			Meta::REVERTED_FROM_META_KEY,
			Meta::TRANSITION_META_KEY,
			Meta::SAVED_BY_META_KEY,
		], $post_id, $revision_id );

		foreach ( $meta_to_remove as $key ) {
			if ( ! in_array( $key, $meta_to_ignore, true ) ) {
				delete_post_meta( $post_id, $key );
			}
		}

		update_post_meta( $post_id, Meta::STATUS_META_KEY, 'reverted' );

		$revisions       = wp_get_post_revisions( $post_id );
		$latest_revision = array_shift( $revisions );

		foreach ( $revision_meta as $key => $value ) {
			$new_value = maybe_unserialize( $value );

			// If we have a new revision (we should) then update the meta on that revision.
			if ( $latest_revision ) {
				update_metadata( 'post', $latest_revision->ID, $key, $new_value );
			}

			if ( isset( $post_meta[ $key ] ) && $post_meta[ $key ] === $value ) {
				continue;
			}

			if ( ! in_array( $key, $meta_to_ignore, true ) ) {
				update_post_meta( $post_id, $key, $new_value );
			}
		}
	}

	/**
	 * Get the number of drafts the post has.
	 *
	 * @param int    $post_id - post id.
	 * @param bool   $force_update - Force refresh draft count.
	 * @param string $post_status - post status.
	 * @return int
	 */
	public static function get_revisions_count( int $post_id, $force_update = false, $post_status = '' ): int {
		global $wpdb;

		$count = get_post_meta( $post_id, Meta::COUNT_META_KEY, true );

		if ( $count && ! $force_update ) {
			return $count;
		}

		$query = "SELECT COUNT(DISTINCT({$wpdb->postmeta}.post_id)) as status_count FROM {$wpdb->postmeta} "
			. "INNER JOIN {$wpdb->posts} "
			. "ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID "
			. "WHERE {$wpdb->posts}.post_type = 'revision' "
			. "AND {$wpdb->posts}.post_parent = %d "
			. "AND {$wpdb->posts}.post_name NOT LIKE %s "
			. "AND {$wpdb->postmeta}.meta_key = 'newspress_status' "
			. "AND {$wpdb->postmeta}.meta_value = %s ";

		$result = $wpdb->get_row( $wpdb->prepare( $query, $post_id, '%autosave%', $post_status ) ); // phpcs:ignore

		$count = (int) $result->status_count;

		return $count;
	}

	/**
	 * Returns lastest scheduled revision or false if there's no scheduled revisions.
	 *
	 * @param int $post_id post id to get latest scheduled revision for.
	 * @return WP_Post|false returns post object for revision or false.
	 */
	public static function get_last_scheduled( int $post_id ) {
		$revisions = wp_get_post_revisions( $post_id, [
			'meta_key'   => Meta::STATUS_META_KEY,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => 'future',
		] );

		if ( ! $revisions ) {
			return false;
		}

		$latest_scheduled_revision = array_pop( array_reverse( $revisions ) );

		return $latest_scheduled_revision;
	}
}
