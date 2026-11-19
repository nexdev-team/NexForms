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
