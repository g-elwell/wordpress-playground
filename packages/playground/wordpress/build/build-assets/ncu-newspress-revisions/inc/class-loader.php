<?php

namespace NewsPress\Revisions;

/**
 * Losads for handling assets.
 */
class Loader {
	public const SCRIPT_NAME = 'ncu-newspress-revisions-script';
	public const STYLE_NAME  = 'ncu-newspress-revisions-style';

	/**
	 * Initialise the hooks and filters.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ], 1 );
	}

	/**
	 * Enqueue any required assets for the block editor.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		global $wp_version;

		$plugin_name = basename( NEWSPRESS_REVISIONS_DIR );

		wp_enqueue_script(
			self::SCRIPT_NAME,
			plugins_url( '/dist/scripts/' . NEWSPRESS_REVISIONS_EDITOR_JS, dirname( __FILE__ ) ),
			[ 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-plugins', 'wp-edit-post', 'lodash' ],
			(string) filemtime( NEWSPRESS_REVISIONS_DIR . '/dist/scripts/' . NEWSPRESS_REVISIONS_EDITOR_JS ),
			false
		);

		wp_localize_script( self::SCRIPT_NAME, 'revisions', [
			'wpVersion'       => $wp_version,
			'link'            => Revisions_View_Controller::get_post_revisions_link( get_the_ID() ),
			'handleAutosaves' => should_handle_autosave(),
		] );

		wp_enqueue_style(
			self::STYLE_NAME,
			plugins_url( '/dist/styles/' . NEWSPRESS_REVISIONS_EDITOR_CSS, dirname( __FILE__ ) ),
			[ 'wp-reset-editor-styles' ],
			(string) filemtime( NEWSPRESS_REVISIONS_DIR . '/dist/styles/' . NEWSPRESS_REVISIONS_EDITOR_CSS )
		);
	}
}
