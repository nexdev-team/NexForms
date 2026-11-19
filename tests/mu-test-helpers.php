<?php
// Test-only: capture outgoing mail and fake Turnstile verification.
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	if ( get_option( 'nf_test_mail_fail' ) ) return false;
	file_put_contents( WP_CONTENT_DIR . '/mail.log', wp_json_encode( $atts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND );
	return true;
}, 10, 2 );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( str_contains( $url, 'turnstile/v0/siteverify' ) ) {
		$tok = $args['body']['response'] ?? '';
		$map = array(
			'good'     => array( 'success' => true, 'hostname' => 'localhost', 'action' => 'nexforms' ),
			'badhost'  => array( 'success' => true, 'hostname' => 'evil.com', 'action' => 'nexforms' ),
			'badaction'=> array( 'success' => true, 'hostname' => 'localhost', 'action' => 'other' ),
		);
		$body = $map[ $tok ] ?? array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) );
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	}
	return $pre;
}, 10, 3 );
