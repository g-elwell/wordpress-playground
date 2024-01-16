<?php

namespace NewsPress\Revisions;

/**
 * Helper class to store all plugin strings
 */
class Strings {
	/**
	 * Filter name for developers to override Revision strings
	 *
	 * @param string
	 */
	public const FILTER_NAME = 'newspress_revision_strings';

	/**
	 * Array of plugin strings
	 *
	 * @var array
	 */
	private static array $strings = [];

	/**
	 * Load all revision strings and run through self::FILTER_NAME filter.
	 *
	 * @return void
	 */
	public static function load(): void {
		/**
		 * Set revision plugin strings.
		 *
		 * Override the revision plugin strings.
		 *
		 * @since 2.0.0
		 *
		 * @param array $strings {
		 *    All plugin strings in a multi dimensional array
		 *    @type (array|string)[] Key/Value pair of strings/Arrays
		 *    @type string string value
		 * }
		 *
		 * @return (array|string)[]
		 */
		self::$strings = apply_filters( self::FILTER_NAME, [
			'admin'  => [
				'page_title'  => __( 'Revisions', 'newspress' ),
				'menu_title'  => __( 'Revisions', 'newspress' ),
				'post_action' => __( 'Revisions', 'newspress' ),
			],
			'editor' => [
				'messages' => [
					'initialising' => __( 'Initialising revisions...', 'newspress' ),
					'postNotFound' => __( "The post you are trying to view either doesn't exist or you do not have permission to access it.", 'newspress' ),
					'noRevisions'  => __( 'There are no revisions for this post.', 'newspress' ),
					'noBlocks'     => __( 'There are no blocks to show', 'newspress' ),
					'loading'      => __( 'Loading...', 'newspress' ),
					'postLock'     => __( 'The post is currently being edited by another user, restoring revisions is disabled', 'newspress' ),
				],
				'revision' => [
					'status_messages' => [
						'all'          => __( 'All', 'newspress' ),
						'publish'      => __( 'Published', 'newspress' ),
						'draft'        => __( 'Draft', 'newspress' ),
						'updated'      => __( 'Updated', 'newspress' ),
						'reverted'     => __( 'Reverted', 'newspress' ),
						'future'       => __( 'Scheduled', 'newspress' ),
						'saved'        => __( 'Saved', 'newspress' ),
						'autosave'     => __( 'Autosave', 'newspress' ),
						'filter_label' => __( 'Event Status', 'newspress' ),
					],
				],
				'blocks'   => [
					'no_content'   => __( 'There is no content to process.', 'newspress' ),
					'no_revisions' => __( 'There are no revisions for this post.', 'newspress' ),
				],
			],
		] );
	}

	/**
	 * Return an array of strings or single string if it exists, else return $default_value
	 *
	 * @param string            $key - String key in format `parentKey.childKey`
	 * @param array|string|null $default_value - Default return value if the key does not exist
	 * @return ?string|array
	 */
	public static function get( string $key, $default_value = null ) {
		$identifiers = explode( '.', $key );

		$value = self::$strings;
		$hook  = sprintf( '%s_%s', self::FILTER_NAME, $key );

		foreach ( $identifiers as $identifier ) {
			if ( ! isset( $value[ $identifier ] ) ) {
				/**
				 * Alter single value returned from strings array
				 *
				 * Override the revision plugin strings.
				 *
				 * @since 2.0.0
				 *
				 * @param string|array $value The current value to be returned
				 * @param array $strings {
				 *    All plugin strings in a multi dimensional array
				 *    @type (array|string)[]|string Key/Value pair of strings/Arrays
				 * }
				 *
				 * @return (array|string)[]|string
				 */
				return apply_filters( $hook, $default_value, self::$strings );
			}

			$value = $value[ $identifier ];
		}

		/**
		 * Alter single value returned from strings array
		 *
		 * Override the revision plugin strings.
		 *
		 * @since 2.0.0
		 *
		 * @param string|array $value The current value to be returned
		 * @param array $strings {
		 *    All plugin strings in a multi dimensional array
		 *    @type @type (array|string)[]|string Key/Value pair of strings/Arrays Key/Value pair of strings/Arrays
		 *    @type string string value
		 * }
		 *
		 * @return (array|string)[]|string
		 */
		return apply_filters( $hook, $value, self::$strings );
	}

	/**
	 * Return all strings as an array
	 *
	 * @return array
	 */
	public static function all(): array {
		return self::$strings;
	}
}
