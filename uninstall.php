<?php
/**
 * Remove NexForms data: form definitions and settings. (Submissions are never stored.)
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$nexforms_ids = get_posts(
	array(
		'post_type'      => 'nexform',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $nexforms_ids as $nexforms_id ) {
	wp_delete_post( $nexforms_id, true );
}
delete_option( 'nexforms_settings' );
delete_option( 'nexforms_version' );
delete_option( 'nexforms_fast_keep' );
$nexforms_mu = trailingslashit( WPMU_PLUGIN_DIR ) . 'nexforms-fast-submit.php';
if ( is_file( $nexforms_mu ) ) {
	wp_delete_file( $nexforms_mu );
}
