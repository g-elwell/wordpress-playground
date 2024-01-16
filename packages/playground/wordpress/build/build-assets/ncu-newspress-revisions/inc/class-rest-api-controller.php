<?php

namespace NewsPress\Revisions;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;

/**
 * Rest API Controller
 */
class REST_API_Controller {
	use RevisionFields;

	/**
	 * API Namespace
	 *
	 * @var string
	 */
	protected string $namespace = 'revisions/v';

	/**
	 * API Version
	 *
	 * @var string
	 */
	protected string $version = '1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
	}

	/**
	 * Return an array post types that support revisions
	 *
	 * @return array
	 */
	private function get_revisions_supporting_types(): array {
		$types = array_values( get_post_types( [ 'show_in_rest' => true ] ) );

		return array_filter( $types, function ( $type ) {
			return post_type_supports( $type, 'revisions' );
		} );
	}

	/**
	 * Register revision related rest fields.
	 *
	 * @return void
	 */
	public function register_rest_fields(): void {
		$types = $this->get_revisions_supporting_types();

		register_rest_field(
			$types,
			'authorData',
			[
				'get_callback' => [ $this, 'get_author_meta' ],
				'schema'       => null,
			]
		);

		register_rest_field(
			$types,
			'newspress_status',
			[
				'get_callback' => [ $this, 'get_newspress_status' ],
			]
		);

		register_rest_field(
			$types,
			'newspress_count',
			[
				'get_callback' => [ $this, 'get_newspress_count' ],
			]
		);

		register_rest_field(
			$types,
			'saveData',
			[
				'get_callback' => [ $this, 'get_save_data_field' ],
			]
		);
	}

	/**
	 * Get revisions save data from post array. This is used by register_rest_field
	 *
	 * @param array $post - post data
	 * @return array
	 */
	public function get_save_data_field( array $post ): array {
		if ( empty( $post['id'] ) ) {
			return [];
		}

		return $this->get_save_data( $post['id'] );
	}

	/**
	 * Register API Routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$namespace = sprintf( '%s%s', $this->namespace, $this->version );

		register_rest_route(
			$namespace,
			'restore(/(?P<revision_id>\d+))?',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_restore_revision' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$namespace,
			'check-status(/(?P<post_id>\d+))?',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_check_status' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$namespace,
			'post(/(?P<post_id>\d+))?',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_get_post' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);

		register_rest_route(
			$namespace,
			'autosave(/(?P<autosave_id>\d+))?',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_autosave' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			]
		);
	}

	/**
	 * Return an array of booleans to indicate the status/permission of an article.
	 *
	 * @param WP_REST_Request $request - the current request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_check_status( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_param', '$post_id is missing', [
				'code' => 400,
			] );
		}

		return rest_ensure_response( [
			'canRestore' => ! ( Article::can_restore_revision( $post_id ) instanceof WP_Error ),
			'isLocked'   => Article::is_locked( $post_id ),
		] );
	}

	/**
	 * Restore a revision by revision ID
	 *
	 * @param WP_REST_Request $request - current request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_restore_revision( WP_REST_Request $request ) {
		$revision_id = (int) $request->get_param( 'revision_id' );

		if ( ! $revision_id ) {
			return new WP_Error( 'missing_param', '$revision_id is missing', [
				'code' => 400,
			] );
		}

		if ( ! defined( 'NEWSPRESS_RESTORING_REVISION' ) ) {
			define( 'NEWSPRESS_RESTORING_REVISION', true );
		}

		$response = Article::restore( $revision_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( [
			'status'  => 'success',
			'message' => 'Success! The revision has been successfully restored.',
			'data'    => [
				'postId' => $response,
			],
		] );
	}

	/**
	 * Return article response data.
	 *
	 * @param WP_REST_Request $request - request data.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_post( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_param', '$post_id is missing', [
				'code' => 400,
			] );
		}

		$article = Article::get( $post_id );

		if ( ! $article->exists() ) {
			return rest_ensure_response( [
				'postId' => $post_id,
				'post'   => false,
				'status' => [
					'canRestore' => false,
					'isLocked'   => true,
				],
			] );
		}

		return rest_ensure_response( [
			'postId'    => $post_id,
			'post'      => $article->post(),
			'editLink'  => get_edit_post_link( $post_id ),
			'revisions' => $article->revisions(),
			'maxPages'  => $article->max_pages(),
			'status'    => [
				'canRestore' => Article::can_restore_revision( $post_id, false ),
				'isLocked'   => Article::is_locked( $post_id ),
			],
		] );
	}

	/**
	 * Add/update autosave metadata.
	 *
	 * @param WP_REST_Request $request - request data.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_autosave( WP_REST_Request $request ) {
		$autosave_id = (int) $request->get_param( 'autosave_id' );

		if ( ! $autosave_id ) {
			return new WP_Error( 'missing_param', '$autosave_id is missing', [
				'code' => 400,
			] );
		}

		if ( ! isset( $request['post_type'] ) ) {
			return new WP_Error( 'missing_param', '$post_type is missing', [
				'code' => 400,
			] );
		}

		if ( ! isset( $request['meta'] ) ) {
			return true;
		}

		/**
		 * Update revision meta that enhances information we can display to users.
		 * - newsperss_status
		 * - newspress_saved_by
		 * etc
		 */
		update_metadata( 'post', $autosave_id, Meta::STATUS_META_KEY, 'autosave' );
		update_metadata( 'post', $autosave_id, Meta::SAVED_BY_META_KEY, get_current_user_id() );

		/**
		 * Initiate core meta fields class using post type of current post (not'revision' post type)
		 * so we can use function core to update meta.
		 */
		$meta = new \WP_REST_Post_Meta_Fields( $request['post_type'] );

		// Get array keys to ignore when saving data to autosave!
		$meta_to_ignore = apply_filters( Revision::META_TO_IGNORE_HOOK, [
			Meta::STATUS_META_KEY,
			Meta::COUNT_META_KEY,
			Meta::LAST_STATUS_META_KEY,
			Meta::REVERTED_FROM_META_KEY,
			Meta::TRANSITION_META_KEY,
			Meta::SAVED_BY_META_KEY,
		], 0, 0 );

		// Filtered meta, excluding any key/value pairs where the key falls into the above array.
		$new_meta = array_filter( 
			$request['meta'], 
			function( $key ) use ( $meta_to_ignore ) {
				return ! in_array( $key, $meta_to_ignore, true );
			},
			ARRAY_FILTER_USE_KEY 
		);
		 
		/**
		 * Update autosave meta the using core WordPress rest method, this could be one of the quickest
		 * and safest ways to apply meta of current post to the autosave revision because will handle
		 * all the data types WordPress allows as it's the same method core uses to save meta against
		 * a post.
		 */
		$meta_update = $meta->update_value( $new_meta, $autosave_id );

		// If wp error, return it.
		if ( is_wp_error( $meta_update ) ) {
			return $meta_update;
		}

		return true;
	}

	/**
	 * Return whether current user is logged in.
	 *
	 * @return boolean
	 */
	public function permission_callback(): bool {
		return is_user_logged_in();
	}
}
