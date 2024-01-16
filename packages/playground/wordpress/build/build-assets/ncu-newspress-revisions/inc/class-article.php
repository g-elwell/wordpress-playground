<?php

namespace NewsPress\Revisions;

use WP_Error;

/**
 * Article handler.
 */
class Article {
	/**
	 * The max number of revisions to return per page.
	 *
	 * @param integer
	 */
	public const MAX_REVISIONS_PER_PAGE = 50;

	/**
	 * Store article options
	 * 
	 * @var integer
	 */
	public $post_id;

	/**
	 * Store max_pages for revisions
	 * 
	 * @var integer
	 */
	public $max_pages;

	/**
	 * Store article id
	 * 
	 * @var array
	 */
	public $options;

	/**
	 * Store article revisions
	 * 
	 * @var array
	 */
	public $revisions;

	/**
	 * Store article
	 * 
	 * @var array
	 */
	public $post;

	/**
	 * Article constructor.
	 *
	 * @param integer $post_id - Post ID
	 * @param array   $options - Array of options.
	 */
	public function __construct( int $post_id, array $options = [] ) {
		$this->post_id = $post_id;
		$this->options = $options;

		$this->fetch_post();

		if ( ! $this->exists() ) {
			return;
		}

		if ( ! ( $options['ignore_revisions'] ?? false ) ) {
			$this->fetch_revisions();
		}
	}

	/**
	 * Get post data using $this->post_id.
	 *
	 * @return void
	 */
	private function fetch_post(): void {
		$current_post = get_post( $this->post_id );

		if ( ! $current_post ) {
			$this->post = null;
			return;
		}

		if ( ! did_action( 'rest_api_init' ) ) {
			$controller = new REST_API_Controller();
			$controller->register_rest_fields();
		}

		$request = new \WP_REST_Request();
		$request->set_param( 'id', $this->post_id );
		$request->set_param( 'context', 'edit' );

		$controller = new \WP_REST_Posts_Controller( $current_post->post_type );
		$response   = $controller->prepare_item_for_response( $current_post, $request );

		if ( $response instanceof WP_Error ) {
			$this->post = null;
		}

		$this->post = (array) $response->data;
	}

	/**
	 * Get revisions for the current post in the wp REST format.
	 *
	 * @return void
	 */
	private function fetch_revisions(): void {
		$request = new \WP_REST_Request();
		$request->set_param( 'parent', $this->post_id );
		$request->set_param( 'per_page', apply_filters( 'newspress_revisions_per_page', self::MAX_REVISIONS_PER_PAGE ) );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'exclude', [ get_latest_revision( $this->post_id ) ] );

		$controller = new \WP_REST_Revisions_Controller( $this->post_type() );
		$response   = $controller->get_items( $request );

		if ( is_wp_error( $response ) ) {
			$this->revisions = [];
			return;
		}

		$this->revisions = $response->data;

		$this->max_pages = $response->headers['X-WP-TotalPages'];
	}

	/**
	 * Static constructor that calls new Article using passed parameters.
	 *
	 * @param integer $post_id - Post ID
	 * @param array   $options - Array of options.
	 * @return Article
	 */
	public static function get( int $post_id, array $options = [] ): Article {
		return new Article( $post_id, $options );
	}

	/**
	 * Getter to return post revisions.
	 *
	 * @return array
	 */
	public function revisions(): array {
		return $this->revisions ?? [];
	}

	/**
	 * Return maximum number of pages of revisions.
	 *
	 * @return int
	 */
	public function max_pages(): int {
		return $this->max_pages ?? 1;
	}

	/**
	 * Getter to return post data.
	 *
	 * @return array
	 */
	public function post(): array {
		return $this->post;
	}

	/**
	 * Get article post type
	 *
	 * @return string
	 */
	public function post_type(): string {
		$post_type = get_post_type( $this->post_id );

		if ( ! $post_type ) {
			return '';
		}

		return $post_type;
	}

	/**
	 * Return true if post exists.
	 *
	 * @return boolean
	 */
	public function exists(): bool {
		return ! ! $this->post;
	}

	/**
	 * Return a bool indicating if the post is locked or not.
	 *
	 * @param integer $post_id - post id to check.
	 * @return boolean
	 */
	public static function is_locked( int $post_id ): bool {
		require_once ABSPATH . '/wp-admin/includes/post.php';

		return ! ! wp_check_post_lock( $post_id );
	}

	/**
	 * Return true or WP_Error dependant if user can restore a revision on a specific post.
	 *
	 * @param integer $post_id - post id to check.
	 * @param boolean $return_error - should the function return WP_Error or a bool on fail.
	 * @return boolean
	 */
	public static function can_restore_revision( int $post_id, $return_error = true ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $return_error ? new WP_Error(
				'rest_forbidden',
				'Sorry, you are not allowed to do that.',
				[ 'code' => 401 ]
			) : false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $return_error ? new WP_Error(
				'rest_not_found',
				"Parent post can't be found.",
				[ 'code' => 404 ]
			) : false;
		}

		if ( self::is_locked( $post->ID ) ) {
			return $return_error ? new WP_Error(
				'rest_post_locked',
				'The current post is currently being edited, therefore cannot be reverted',
				[ 'code' => 400 ]
			) : false;
		}

		return true;
	}

	/**
	 * Restore a post to a specific revision id.
	 *
	 * @param integer $revision_id - Revision to restore.
	 * @return WP_Error|int
	 */
	public static function restore( int $revision_id ) {
		$revision = wp_get_post_revision( $revision_id );

		if ( ! $revision ) {
			return new WP_Error( 'rest_revision_invalid_id', 'Invalid revision ID.', [
				'code' => 404,
			] );
		}

		$can_restore = self::can_restore_revision( $revision->post_parent );

		if ( ( $can_restore instanceof WP_Error ) || ! $can_restore ) {
			return $can_restore;
		}

		require_once ABSPATH . '/wp-includes/revision.php';

		$post_id          = wp_restore_post_revision( $revision_id );
		$newspress_status = get_post_meta( $revision_id, Meta::STATUS_META_KEY, true );
		$is_autosave      = wp_is_post_autosave( $revision_id );
		
		/**
		 * If restoring from a post which has newspress_status reverted, get post id from
		 * Meta::REVERTED_FROM_META_KEY field. This prevents timeline showing Reverted from
		 * Reverted and instead will show Reverteed from Draft 1.
		 */
		if ( 'reverted' === $newspress_status ) {
			$reverted_from = get_post_meta( $revision_id, Meta::REVERTED_FROM_META_KEY, true );

			$revision_id = (int) $reverted_from;
		}

		// Setting 0 for newspress_reverted_from will be used to indicate that the restore was from a revision.
		update_metadata( 'post', $post_id, Meta::REVERTED_FROM_META_KEY, $is_autosave ? 0 : $revision_id );

		if ( $post_id ) {
			$revisions = wp_get_post_revisions( $post_id );
			$rev       = array_shift( $revisions );

			// Setting 0 for newspress_reverted_from will be used to indicate that the restore was from a revision.
			update_metadata( 'post', $rev->ID, Meta::REVERTED_FROM_META_KEY, $is_autosave ? 0 : $revision_id );
			update_metadata( 'post', $rev->ID, Meta::STATUS_META_KEY, 'reverted' );
		}

		// Post restored, all autosaves are now stale (in the past), so we need to delete them!
		delete_all_autosaves( $post_id );

		return $revision->post_parent;
	}
}
