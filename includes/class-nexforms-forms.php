<?php
/**
 * Form storage: a private post type holding the form title, with the JSON config in post meta.
 * Submissions are never stored.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Forms {

	public const POST_TYPE = 'nexform';
	public const META_KEY  = '_nexform_config';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array( 'name' => __( 'NexForms', 'nex-forms' ) ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'manage_options' ),
				'map_meta_cap'    => true,
			)
		);
	}

	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		$config = json_decode( (string) get_post_meta( $id, self::META_KEY, true ), true );
		$config = is_array( $config ) ? $config : array();
		$form   = array_merge( NexForms_Schema::default_form(), $config );

		$form['settings'] = array_merge( NexForms_Schema::default_settings(), (array) ( $config['settings'] ?? array() ) );
		$form['design']   = array_merge( NexForms_Schema::default_design(), (array) ( $config['design'] ?? array() ) );
		$form['fields']   = isset( $config['fields'] ) ? (array) $config['fields'] : $form['fields'];
		$form['id']       = $id;
		$form['title']    = $post->post_title;
		$form['status']   = 'publish' === $post->post_status ? 'publish' : 'draft';
		$form['modified'] = get_post_modified_time( 'c', true, $post );
		return $form;
	}

	/** @return array<int, array> Summaries for the list screen. */
	public static function list(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 500,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$config = json_decode( (string) get_post_meta( $p->ID, self::META_KEY, true ), true );
			$out[]  = array(
				'id'       => $p->ID,
				'title'    => $p->post_title,
				'status'   => 'publish' === $p->post_status ? 'publish' : 'draft',
				'fields'   => is_array( $config['fields'] ?? null ) ? count( $config['fields'] ) : 0,
				'modified' => get_post_modified_time( 'c', true, $p ),
			);
		}
		return $out;
	}

	/**
	 * Create or update. $data must already be sanitised.
	 *
	 * @return int|WP_Error Post ID.
	 */
	public static function save( array $data, int $id = 0 ) {
		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $data['title'],
			'post_status' => $data['status'],
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$config = array(
			'v'        => 1,
			'fields'   => $data['fields'],
			'settings' => $data['settings'],
			'design'   => $data['design'],
		);
		update_post_meta( (int) $result, self::META_KEY, wp_slash( wp_json_encode( $config, JSON_UNESCAPED_UNICODE ) ) );
		return (int) $result;
	}

	public static function delete( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $id, true );
	}
}
