<?php

namespace NewsPress\Revisions;

use WP_Post;
use WP_REST_Response;

/**
 * Meta class
 */
class Meta {
	use RevisionFields;

	/**
	 * Status meta key
	 *
	 * @param string
	 */
	public const STATUS_META_KEY = 'newspress_status';

	/**
	 * Count meta key
	 *
	 * @param int
	 */
	public const COUNT_META_KEY = 'newspress_count';


	/**
	 * Last Status meta key
	 *
	 * @param string
	 */
	public const LAST_STATUS_META_KEY = 'revision_last_post_status';


	/**
	 * Saved by meta key
	 *
	 * @param string
	 */
	public const SAVED_BY_META_KEY = 'newspress_saved_by';

	/**
	 * Transition meta key
	 *
	 * @param string
	 */
	public const TRANSITION_META_KEY = 'newspress_status_transitioned';

	/**
	 * Reverted from meta key
	 *
	 * @param string
	 */
	public const REVERTED_FROM_META_KEY = 'newspress_reverted_from';

	/**
	 * Meta exclusions filter
	 *
	 * @param string
	 */
	public const META_EXCLUSIONS_FILTER = 'newspress.revisions.metaExclusions';

	/**
	 * Rest Meta filter
	 *
	 * @param string
	 */
	public const REST_ITEM_META_FILTER = 'newspress.revisions.restItemMeta';

	/**
	 * Post status filter
	 *
	 * @param string
	 */
	public const GET_POST_STATUS_FILTER = 'newspress.revisions.getPostStatus';

	/**
	 * Post status change action
	 *
	 * @param string
	 */
	public const POST_STATUS_CHANGE_ACTION = 'newspress.revisions.postStatusChange';

	/**
	 * REST Revision Item action
	 *
	 * @param string
	 */
	public const REST_REVISION_ITEM_ACTION = 'newspress.revisions.restRevisionItem';

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		add_filter( 'rest_prepare_revision', [ $this, 'rest_revision' ], 10, 1 );
		add_filter( 'wp_save_post_revision_post_has_changed', [ $this, 'should_save_revision' ], 10, 3 );
		add_action( '_wp_put_post_revision', [ $this, 'on_revision_created' ] );
		add_filter( 'wp_insert_post_empty_content', [ $this, 'is_post_empty' ], PHP_INT_MAX, 2 );
		add_action( 'wp_after_insert_post', [ $this, 'maybe_create_revision_after_insert_post' ], 10, 4 );
	}

	/**
	 * Maybe create a revision after a post is inserted, this is to handle the case where a
	 * post's meta has been changed.
	 *
	 * @param integer      $post_id - post ID.
	 * @param WP_Post      $post - current post object.
	 * @param boolean      $update - is the current post an update.
	 * @param WP_Post|null $old_post - the old post object.
	 * @return void
	 */
	public function maybe_create_revision_after_insert_post( int $post_id, WP_Post $post, bool $update, ?WP_Post $old_post ): void {
		if ( ! $update || ! post_type_supports( $post->post_type, 'revisions' ) || 'revisions' === $post->post_type ) {
			return;
		}

		// if the old post was an auto draft then we need to apply the meta data to the last revision
		// This is because we cant intercept the first revision created.
		if ( 'auto-draft' === $old_post->post_status ) {
			$revisions     = wp_get_post_revisions( $post_id );
			$last_revision = false;

			if ( ! empty( $revisions ) ) {
				// Grab the last revision, that isn't an autosave.
				foreach ( $revisions as $revision ) {
					if ( false !== strpos( $revision->post_name, "{$revision->post_parent}-revision" ) ) {
						$last_revision = $revision;
						break;
					}
				}
			}

			if ( $last_revision ) {
				$this->handle_update_revisions_meta( $last_revision );
			}
			return;
		}

		if ( ! defined( 'NEWSPRESS_REVISIONS_CREATE_REVISION' ) ) {
			define( 'NEWSPRESS_REVISIONS_CREATE_REVISION', true );
		}

		wp_save_post_revision( $post_id );
	}

	/**
	 * If enabled then make sure to insert the post even post content, post title or post excerpt is empty.
	 *
	 * @param boolean $maybe_empty - the current is_empty value
	 * @param array   $post_arr - the post array to insert
	 * @return boolean
	 */
	public function is_post_empty( bool $maybe_empty, array $post_arr ): bool {
		/**
		 * Should revisions store empty post.
		 *
		 * Should WordPress potentially store empty values for revisions and overwrite some protections from other plugins
		 *
		 * @since 2.0.2
		 *
		 * @param boolean $should_store_empty Should revisions store empty post.
		 */
		if ( ! apply_filters( 'ncu_newspress_revisions_should_store_potentially_empty_post', true ) ) {
			return $maybe_empty;
		}

		if ( 'revision' === $post_arr['post_type'] ) {
			/**
			 * Current revision data is empty
			 *
			 * Return true if the revisions data should be considered empty and not store the revision
			 *
			 * @since 2.0.2
			 *
			 * @param boolean $is_empty Should revisions store empty post.
			 * @param array $post_arr Array of post data.
			 */
			return apply_filters(
				'ncu_newspress_revisions_revision_is_empty',
				empty( $post_arr['post_content'] ) && empty( $post_arr['post_title'] ) && empty( $post_arr['post_excerpt'] ),
				$post_arr
			);
		}

		return $maybe_empty;
	}

	/**
	 * Ensure wordpress creates a revision when only post status changes
	 *
	 * @param bool    $should_save - current should save value
	 * @param WP_Post $last_revision - Last post revision
	 * @param WP_Post $post - current post object
	 * @return bool
	 */
	public function should_save_revision( $should_save, $last_revision, $post ): bool {
		if ( ! defined( 'NEWSPRESS_REVISIONS_CREATE_REVISION' ) || ! NEWSPRESS_REVISIONS_CREATE_REVISION ) {
			return false;
		}

		if ( defined( 'NEWSPRESS_RESTORING_REVISION' ) && NEWSPRESS_RESTORING_REVISION ) {
			return true;
		}

		if ( $should_save ) {
			return $should_save;
		}

		$meta_to_compare = apply_filters( 'newspress_revisions_meta_compare', [] );

		foreach ( $meta_to_compare as $field ) {
			if ( ! is_array( $field ) ) {
				$field = [ $field, [] ];
			}

			$key     = $field[0];
			$options = array_merge(
				[
					'single' => true,
				],
				$field[1],
			);

			$old_value = get_metadata( 'post', $last_revision->ID, $key, $options['single'] );

			// if the old value is a string and empty try get the default value.
			if ( is_string( $old_value ) && empty( $old_value ) ) {
				$old_value = get_metadata_default( 'post', $post->ID, $key, $options['single'] );
			}

			$old_value = wp_json_encode( $old_value );
			$new_value = wp_json_encode( get_metadata( 'post', $post->ID, $key, $options['single'] ) );

			if ( $old_value !== $new_value ) {
				return true;
			}
		}

		return get_metadata( 'post', $last_revision->ID, 'revision_last_post_status', true ) !== $post->post_status;
	}

	/**
	 * Update revisions meta data based on its parent.
	 *
	 * @param WP_Post $post - revision post object
	 * @return void
	 */
	private function handle_update_revisions_meta( WP_Post $post ): void {
		update_metadata(
			'post',
			$post->ID,
			self::TRANSITION_META_KEY,
			get_post_meta( $post->post_parent, self::TRANSITION_META_KEY, true )
		);

		update_metadata(
			'post',
			$post->ID,
			'revision_last_post_status',
			get_post_status( $post->post_parent )
		);

		update_metadata(
			'post',
			$post->ID,
			self::STATUS_META_KEY,
			get_post_meta( $post->post_parent, self::STATUS_META_KEY, true )
		);

		update_metadata(
			'post',
			$post->ID,
			self::COUNT_META_KEY,
			get_post_meta( $post->post_parent, self::COUNT_META_KEY, true )
		);

		$post_meta = get_post_meta( $post->post_parent );

		if ( ! $post_meta ) {
			return;
		}

		foreach ( $post_meta as $name => $meta_items ) {
			foreach ( $meta_items as $data ) {
				add_metadata( 'post', $post->ID, $name, maybe_unserialize( $data ) );
			}
		}
	}

	/**
	 * Handle the updating of metadata when a revision is created.
	 *
	 * @param int $revision_id - latest revision id.
	 */
	public function on_revision_created( int $revision_id ) {
		$revision = get_post( $revision_id );

		if ( ! $revision ) {
			return;
		}

		// Bail early if it is an autosave.
		if ( false !== strpos( $revision->post_name, "{$revision->post_parent}-autosave" ) ) {
			// Add autosave to list.
			Revision::get_autosave_ids( $revision->post_parent, true );
			return;
		}

		$post = get_post( $revision->post_parent );

		$old_status = get_post_meta( $post->ID, 'revision_last_post_status', true );
		$new_status = $post->post_status;

		update_metadata( 'post', $revision_id, 'revision_last_post_status', $new_status );
		update_metadata( 'post', $post->ID, 'revision_last_post_status', $new_status );

		$status     = 'draft' === $new_status || 'auto-draft' === $new_status ? 'draft' : $new_status;
		$transition = 0;

		if ( 'publish' === $new_status && $old_status !== $new_status ) {
			$status     = 'publish';
			$transition = 1;
		}

		if ( 'publish' === $new_status && $old_status === $new_status ) {
			$status = 'update';
		}

		/**
		 * If transitioning from future -> publish, get_current_user_id returns 0 possibly
		 * because it's occuring due to cron. This gets the latest scheduled revision and uses the
		 * saved by value from that revision.
		 */
		$saved_by_id = get_current_user_id();

		if ( 'future' === $old_status && 'publish' === $new_status ) {
			$scheduled_revision = Revision::get_last_scheduled( $post->ID );

			$schedule_by = get_post_meta( $scheduled_revision->ID, self::SAVED_BY_META_KEY, true );

			if ( $schedule_by ) {
				$saved_by_id = $schedule_by;
			}
		}

		update_metadata( 'post', $post->ID, self::SAVED_BY_META_KEY, $saved_by_id );
		update_metadata( 'post', $post->ID, self::TRANSITION_META_KEY, $transition );

		$status = apply_filters( self::GET_POST_STATUS_FILTER, $status, $post, $revision, $old_status );

		update_metadata( 'post', $post->ID, self::STATUS_META_KEY, $status );
		do_action( self::POST_STATUS_CHANGE_ACTION, $post, $new_status, $old_status );

		$count = Revision::get_revisions_count( $post->ID, true, $status );
		update_metadata( 'post', $post->ID, self::COUNT_META_KEY, ++$count );

		$this->handle_update_revisions_meta( $revision );
	}

	/**
	 * Add meta to post revision response
	 *
	 * @param WP_REST_Response $revision - Current revision response
	 * @return WP_REST_Response
	 */
	public function rest_revision( WP_REST_Response $revision ): WP_REST_Response {
		$post_status = get_metadata( 'post', $revision->data['id'], self::STATUS_META_KEY, true );
		$post_count  = get_metadata( 'post', $revision->data['id'], self::COUNT_META_KEY, true );

		if ( ! $post_status ) {
			$post_status = 'unknown';
		}

		$revision->data['newspress_status'] = $post_status;
		$revision->data['newspress_count']  = (int) $post_count;

		if ( 'publish' === $post_status ) {
			$revision->data['status'] = $post_status;
		}

		$meta      = new \WP_REST_Post_Meta_Fields( get_post_type( (int) $revision->data['parent'] ) );
		$post_meta = $meta->get_value( $revision->data['id'], $revision );

		$revision->data['meta'] = apply_filters( self::REST_ITEM_META_FILTER, (array) $post_meta, $revision->data['id'] );

		$revision->data['saveData'] = $this->get_save_data( $revision->data['id'] );

		$revision->data['meta']['newspress_edit_link'] = wp_specialchars_decode( get_edit_post_link( $revision->data['id'] ) );
		$revision->data['authorData']                  = $this->get_author_meta( $revision->data );

		add_action( self::REST_REVISION_ITEM_ACTION, $revision );

		return $revision;
	}
}
