<?php

namespace NewsPress\Revisions;

use WP_Post;

/**
 * Revisions Viewer Class
 */
class Revisions_View_Controller {

	/**
	 * Script Name
	 *
	 * @param string
	 */
	public const SCRIPT_NAME = 'ncu-newspress-revisions-view-script';

	/**
	 * Style name
	 *
	 * @param string
	 */
	public const STYLE_NAME = 'ncu-newspress-revisions-view-style';

	/**
	 * Page Slug
	 *
	 * @param string
	 */
	public const PAGE_SLUG = 'revisions-view';

	/**
	 * Page Hook Name
	 *
	 * @param string
	 */
	public const PAGE_HOOK = 'posts_page_revisions-view';
	/**
	 * Revisions_View_Controller constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], PHP_INT_MAX );
		add_action( 'post_row_actions', [ $this, 'register_post_actions' ], 10, 2 );
		add_filter( 'should_load_block_editor_scripts_and_styles', [ $this, 'should_render_admin_assets' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
	}

	/**
	 * Register Revisions View page.
	 *
	 * @return void
	 */
	public function register_page(): void {
		\add_submenu_page(
			'', // slug
			Strings::get( 'admin.page_title' ), // page title
			Strings::get( 'admin.menu_title' ), // menu title
			'edit_posts', // capabilities
			self::PAGE_SLUG, // menu_slug
			[ $this, 'render_page' ], // callable
		);
	}

	/**
	 * Add editor body classes to editor.
	 *
	 * @param string $classes - admin page classes
	 * @return string
	 */
	public function add_body_classes( string $classes ): string {
		$screen = get_current_screen();

		if ( self::PAGE_HOOK !== $screen->id ) {
			return $classes;
		}

		$classes .= ' block-editor-page wp-embed-responsive';

		return $classes;
	}

	/**
	 * Render Revisions View Page
	 *
	 * @return void
	 */
	public function render_page(): void {
		require_once __DIR__ . '/views/revisions.php';
	}

	/**
	 * Should render admin assets on revisions view
	 *
	 * @param bool $should_render - current should render value
	 * @return bool
	 */
	public function should_render_admin_assets( $should_render ): bool {
		$screen = get_current_screen();

		if ( self::PAGE_HOOK !== $screen->id ) {
			return $should_render;
		}

		return true;
	}

	/**
	 * Enqueue Revision View Assets
	 *
	 * @param string $hook - current page hook as provided by `admin_enqueue_scripts`
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( self::PAGE_HOOK !== $hook ) {
			return;
		}

		$plugin_name = basename( NEWSPRESS_REVISIONS_DIR );

		do_action( 'enqueue_block_editor_assets' );
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-block-library' );
		wp_enqueue_style( 'wp-editor-classic-layout-styles' );
		wp_enqueue_style( 'editor-buttons' );
		wp_enqueue_style( 'wp-block-editor' );
		wp_enqueue_style( 'wp-block-library-theme' );
		wp_enqueue_style( 'wp-edit-blocks' );
		wp_enqueue_style( 'wp-editor' );
		wp_enqueue_style( 'wp-reset-editor-styles' );
		wp_enqueue_style( 'wp-reusable-blocks' );

		clean_assets_for_revisions();

		// Preload server-registered block schemas.
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
		);

		wp_enqueue_script(
			self::SCRIPT_NAME,
			plugins_url( '/dist/scripts/' . NEWSPRESS_REVISIONS_REVISIONS_VIEW_JS, dirname( __FILE__ ) ),
			[ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-plugins', 'wp-edit-post', 'wp-keyboard-shortcuts' ],
			(string) filemtime( NEWSPRESS_REVISIONS_DIR . '/dist/scripts/' . NEWSPRESS_REVISIONS_REVISIONS_VIEW_JS ),
			false
		);

		wp_localize_script(
			self::SCRIPT_NAME,
			'RevisionsView',
			[
				'strings'         => Strings::all(),
				'data'            => $this->get_revisions_data(),
				'settings'        => [
					'root'               => esc_url_raw( rest_url() ),
					'nonce'              => wp_create_nonce( 'wp_rest' ),
					'revisions_per_page' => apply_filters( 'newspress_revisions_per_page', Article::MAX_REVISIONS_PER_PAGE ),
				],
				'editor_settings' => $this->get_editor_settings(),
			]
		);

		wp_enqueue_style(
			self::STYLE_NAME,
			plugins_url( '/dist/styles/' . NEWSPRESS_REVISIONS_REVISIONS_VIEW_CSS, dirname( __FILE__ ) ),
			[],
			(string) filemtime( NEWSPRESS_REVISIONS_DIR . '/dist/styles/' . NEWSPRESS_REVISIONS_REVISIONS_VIEW_CSS )
		);
	}

	/**
	 * Get editor settings
	 *
	 * @return array
	 */
	public function get_editor_settings(): array {
		$post_id = (int) filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

		if ( ! $post_id ) {
			return [];
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return [];
		}

		$block_editor_context = new \WP_Block_Editor_Context( [ 'post' => $post ] );

		return get_block_editor_settings( [], $block_editor_context );
	}

	/**
	 * Return the post revisions link
	 *
	 * @param int $post_id - Post id to get revisions link
	 *
	 * @return string
	 */
	public static function get_post_revisions_link( int $post_id ): string {
		$url = get_admin_url(
			null,
			sprintf( 'edit.php?page=%s', self::PAGE_SLUG )
		);

		$url = sprintf(
			'%s&post=%s',
			$url,
			$post_id
		);

		return apply_filters(
			'newspress_revisions_post_link',
			$url,
			$post_id
		);
	}

	/**
	 * Register revisions post action
	 *
	 * @param array   $actions - current post actions.
	 * @param WP_Post $post - current post.
	 * @return array
	 */
	public function register_post_actions( array $actions, WP_Post $post ): array {
		if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
			return $actions;
		}

		$admin_url = self::get_post_revisions_link( $post->ID );

		$actions['revisions'] = sprintf(
			'<a href="%s">%s</a>',
			$admin_url,
			Strings::get( 'admin.post_action' )
		);

		return $actions;
	}

	/**
	 * Return an array of the post, post id and array of revisions.
	 *
	 * @return array
	 */
	public function get_revisions_data(): array {
		$post_id  = (int) filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$readonly = filter_input( INPUT_GET, 'readonly', FILTER_VALIDATE_BOOLEAN );

		if ( ! $post_id ) {
			return [];
		}

		// delete all autosaves that are older than the current post save.
		delete_all_autosaves( $post_id );

		$article = Article::get( $post_id );

		if ( ! $article->exists() ) {
			return [
				'postId' => $post_id,
				'post'   => false,
				'status' => [
					'readonly'   => $readonly,
					'canRestore' => false,
					'isLocked'   => true,
				],
			];
		}

		return [
			'postId'    => $post_id,
			'post'      => $article->post(),
			'editLink'  => get_edit_post_link( $post_id ),
			'restUrl'   => rest_get_route_for_post_type_items( get_post_type( $post_id ) ),
			'revisions' => $article->revisions(),
			'autosaves' => Revision::get_autosave_ids( $post_id ),
			'maxPages'  => $article->max_pages(),
			'status'    => [
				'readonly'   => $readonly,
				'canRestore' => Article::can_restore_revision( $post_id, false ),
				'isLocked'   => Article::is_locked( $post_id ),
			],
		];
	}
}
