<?php

namespace NewsPress\Revisions;

/**
 * Runs the plugin setup sequence.
 *
 * @throws \Error if constants aren't defined.
 * @return void
 */
function setup(): void {
	if (
		! defined( 'NEWSPRESS_REVISIONS_EDITOR_JS' ) ||
		! defined( 'NEWSPRESS_REVISIONS_EDITOR_CSS' ) ||
		! defined( 'NEWSPRESS_REVISIONS_REVISIONS_VIEW_JS' ) ||
		! defined( 'NEWSPRESS_REVISIONS_REVISIONS_VIEW_CSS' )
	) {
		throw new \Error( 'Asset constants are not defined. You may need to run an asset build.' );
	}

	Strings::load();

	new Loader();
	new Meta();
	new Revisions_View_Controller();
	new Revision();
	new REST_API_Controller();
}
