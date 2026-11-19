<?php
/**
 * REST routes. Admin routes require manage_options (cookie auth + nonce via apiFetch).
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_REST {

	public const NS = 'nexforms/v1';

	public static function register_routes(): void {
		$admin = static fn() => current_user_can( 'manage_options' );
		$id    = array( 'id' => array( 'validate_callback' => static fn( $v ) => is_numeric( $v ) ) );

		register_rest_route(
			self::NS,
			'/submit/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( 'NexForms_Submit', 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => $id,
			)
		);

		register_rest_route(
			self::NS,
			'/forms',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static fn() => rest_ensure_response( NexForms_Forms::list() ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'read' ),
					'permission_callback' => $admin,
					'args'                => $id,
				),
				array(
					'methods'             => 'PUT, PATCH, POST',
					'callback'            => array( __CLASS__, 'update' ),
					'permission_callback' => $admin,
					'args'                => $id,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete' ),
					'permission_callback' => $admin,
					'args'                => $id,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/forms/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'duplicate' ),
				'permission_callback' => $admin,
				'args'                => $id,
			)
		);

		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static fn() => rest_ensure_response( NexForms_Settings::public_view() ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'PUT, PATCH, POST',
					'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( NexForms_Settings::update( (array) $r->get_json_params() ) ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/test-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'test_email' ),
				'permission_callback' => $admin,
			)
		);
	}

	public static function create( WP_REST_Request $r ) {
		$body = (array) $r->get_json_params();
		$data = $body ? NexForms_Schema::sanitize_form( array_merge( NexForms_Schema::default_form(), $body ) ) : NexForms_Schema::sanitize_form( NexForms_Schema::default_form() );
		$id   = NexForms_Forms::save( $data );
		return is_wp_error( $id ) ? $id : rest_ensure_response( NexForms_Forms::get( $id ) );
	}

	public static function read( WP_REST_Request $r ) {
		$form = NexForms_Forms::get( (int) $r['id'] );
		return $form ? rest_ensure_response( $form ) : new WP_Error( 'nexforms_not_found', __( 'Form not found.', 'nex-forms' ), array( 'status' => 404 ) );
	}

	public static function update( WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( ! NexForms_Forms::get( $id ) ) {
			return new WP_Error( 'nexforms_not_found', __( 'Form not found.', 'nex-forms' ), array( 'status' => 404 ) );
		}
		$saved = NexForms_Forms::save( NexForms_Schema::sanitize_form( (array) $r->get_json_params() ), $id );
		return is_wp_error( $saved ) ? $saved : rest_ensure_response( NexForms_Forms::get( $id ) );
	}

	public static function delete( WP_REST_Request $r ) {
		return NexForms_Forms::delete( (int) $r['id'] )
			? rest_ensure_response( array( 'deleted' => true ) )
			: new WP_Error( 'nexforms_not_found', __( 'Form not found.', 'nex-forms' ), array( 'status' => 404 ) );
	}

	public static function duplicate( WP_REST_Request $r ) {
		$form = NexForms_Forms::get( (int) $r['id'] );
		if ( ! $form ) {
			return new WP_Error( 'nexforms_not_found', __( 'Form not found.', 'nex-forms' ), array( 'status' => 404 ) );
		}
		/* translators: %s: form title */
		$form['title']  = sprintf( __( '%s (copy)', 'nex-forms' ), $form['title'] );
		$form['status'] = 'draft';
		$id             = NexForms_Forms::save( NexForms_Schema::sanitize_form( $form ) );
		return is_wp_error( $id ) ? $id : rest_ensure_response( NexForms_Forms::get( $id ) );
	}

	public static function test_email( WP_REST_Request $r ) {
		$to = NexForms_Schema::sanitize_emails( (string) ( $r->get_json_params()['to'] ?? '' ) );
		$to = '' !== $to ? $to : get_option( 'admin_email' );
		$ok = wp_mail(
			array_map( 'trim', explode( ',', $to ) ),
			/* translators: %s: site name */
			sprintf( __( '[%s] NexForms test email', 'nex-forms' ), get_bloginfo( 'name' ) ),
			__( 'This is a test message from NexForms. If you received it, form notifications will be delivered through your current mail setup.', 'nex-forms' )
		);
		return rest_ensure_response( array( 'sent' => (bool) $ok, 'to' => $to ) );
	}
}
