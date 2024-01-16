<?php

namespace NewsPress\Revisions;

trait RevisionFields {

	/**
	 * Add newspress status to response
	 *
	 * @param array $post - current post data
	 * @return string
	 */
	public function get_newspress_status( array $post ): string {
		if ( empty( $post['id'] ) ) {
			return 'draft';
		}

		$newspress_status = get_post_meta( $post['id'], Meta::STATUS_META_KEY, true );

		if ( 'auto-draft' === $newspress_status ) {
			$newspress_status = 'draft';
		}

		return $newspress_status ? $newspress_status : 'draft';
	}

	/**
	 * Add newspress count to response
	 *
	 * @param array $post - current post data
	 * @return string
	 */
	public function get_newspress_count( array $post ): string {
		if ( empty( $post['id'] ) ) {
			return 0;
		}

		$newspress_count = get_post_meta( $post['id'], Meta::COUNT_META_KEY, true );

		return $newspress_count;
	}

	/**
	 * Add author meta to response
	 *
	 * @param array $post - current post data.
	 * @return array
	 */
	public function get_author_meta( array $post ): array {
		if ( empty( $post['id'] ) ) {
			return [];
		}

		$author_id = get_post_meta( $post['id'], Meta::SAVED_BY_META_KEY, true );

		if ( $author_id ) {
			return $this->get_author_data( (int) $author_id );
		}

		if ( ! isset( $post['author'] ) ) {
			return [];
		}

		$author_id = $post['author'];

		return $this->get_author_data( (int) $author_id );
	}

	/**
	 * Get author data
	 *
	 * @param int $author_id - author id.
	 * @return array
	 */
	public function get_author_data( int $author_id ): array {
		$author = [
			'id'   => $author_id,
			'name' => get_the_author_meta( 'display_name', $author_id ),
		];

		return $author;
	}

	/**
	 * Get relevant revisions meta as an array.
	 *
	 * @param int $post_id - current post id
	 * @return array
	 */
	public function get_save_data( int $post_id ): array {
		$reverted_from_id = get_metadata( 'post', $post_id, Meta::REVERTED_FROM_META_KEY, true );

		$data = [
			'saved_by_id'       => (int) get_metadata( 'post', $post_id, Meta::SAVED_BY_META_KEY, true ),
			'save_transitioned' => get_metadata( 'post', $post_id, Meta::TRANSITION_META_KEY, true ) === '1',
			'last_post_status'  => get_metadata( 'post', $post_id, Meta::LAST_STATUS_META_KEY, true ),
		];

		/**
		 * If reverted from autosave, set reverted_from to autosave to use client side.
		 */
		if ( '0' === $reverted_from_id ) {
			$data['reverted_from'] = 'autosave';

			return $data;
		}

		$data['reverted_from'] = [
			'id'                => $reverted_from_id,
			'newspress_status'  => get_metadata( 'post', $reverted_from_id, Meta::STATUS_META_KEY, true ),
			'newspress_count'   => get_metadata( 'post', $reverted_from_id, Meta::COUNT_META_KEY, true ),
			'save_transitioned' => get_metadata( 'post', $reverted_from_id, Meta::TRANSITION_META_KEY, true ),
		];

		return $data;
	}
}
