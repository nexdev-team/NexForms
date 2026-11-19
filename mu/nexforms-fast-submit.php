<?php
/**
 * Plugin Name: NexForms fast submit
 * Description: Installed by NexForms. For form submissions only, loads just NexForms and the mail plugin (e.g. FluentSMTP) instead of every plugin, so sending is fast. Every other request is untouched. Turn it off in NexForms → Settings.
 * Version:     1
 */

defined( 'ABSPATH' ) || exit;

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	$nexforms_route = isset( $_GET['rest_route'] ) ? (string) $_GET['rest_route'] : (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security
	if ( preg_match( '#/nexforms/v1/submit/\d+/?$#', rawurldecode( $nexforms_route ) ) && is_file( WP_PLUGIN_DIR . '/nex-forms/nex-forms.php' ) ) {
		add_filter(
			'option_active_plugins',
			static function ( $plugins ) {
				$keep = (array) get_option( 'nexforms_fast_keep', array() );
				return array_values(
					array_filter(
						(array) $plugins,
						static function ( $p ) use ( $keep ) {
							// NexForms itself, SMTP/mail-delivery plugins, and anything listed in nexforms_fast_keep.
							return 0 === strpos( $p, 'nex-forms/' ) || preg_match( '#smtp#i', $p ) || in_array( $p, $keep, true );
						}
					)
				);
			},
			1
		);
	}
}
