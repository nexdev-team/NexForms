<?php
/**
 * Updater test: fakes the GitHub API and asset download, then runs a real plugin upgrade.
 * Usage: php tests/updater.php /path/to/wordpress /path/to/release.zip NEW_VERSION
 * WARNING: upgrades the NexForms copy installed in that WordPress (use a throwaway install, not a symlink).
 */
$nf_wp_path = $argv[1];
$nf_zip     = $argv[2];
$nf_new     = $argv[3];
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require $nf_wp_path . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
wp_set_current_user( 1 );

$pass = 0; $fail = 0;
function ok( $c, $n ) { global $pass, $fail; $c ? $pass++ : $fail++; echo ( $c ? '  ✓ ' : '  ✗ ' ) . $n . "\n"; }

$GLOBALS['nf_digest']  = hash_file( 'sha256', $nf_zip );
$GLOBALS['nf_seen']    = array();
$GLOBALS['nf_private'] = false;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $nf_zip, $nf_new ) {
	$GLOBALS['nf_seen'][] = array( $url, $args );
	if ( str_contains( $url, 'api.wordpress.org/plugins/update-check' ) ) {
		$GLOBALS['nf_wporg_body'] = $args['body']['plugins'] ?? '';
		return array( 'headers' => array(), 'body' => wp_json_encode( array( 'plugins' => array(), 'translations' => array(), 'no_update' => array() ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	}
	if ( str_contains( $url, 'api.github.com/repos/nexdev-team/NexForms/releases/latest' ) ) {
		$body = array(
			'tag_name' => 'v' . $nf_new, 'draft' => false, 'prerelease' => false, 'html_url' => 'https://github.com/nexdev-team/NexForms/releases/tag/v' . $nf_new,
			'body' => "- Better things\n- <script>x</script>", 'published_at' => '2026-11-19T10:00:00Z',
			'assets' => array(
				array( 'name' => 'nex-forms.zip.sha256', 'url' => 'https://api.github.com/repos/nexdev-team/NexForms/releases/assets/2', 'browser_download_url' => 'https://github.com/x/sha' ),
				array( 'name' => 'nex-forms.zip', 'url' => 'https://api.github.com/repos/nexdev-team/NexForms/releases/assets/1', 'browser_download_url' => 'https://github.com/nexdev-team/NexForms/releases/download/v' . $nf_new . '/nex-forms.zip', 'digest' => 'sha256:' . $GLOBALS['nf_digest'] ),
			),
		);
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	}
	if ( str_contains( $url, '/nex-forms.zip' ) || str_contains( $url, 'releases/assets/1' ) ) {
		if ( ! empty( $args['filename'] ) ) copy( $nf_zip, $args['filename'] );
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $args['filename'] ?? null );
	}
	return $pre;
}, 10, 3 );

echo "Update detection\n";
delete_site_transient( 'nexforms_release' );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$t    = get_site_transient( 'update_plugins' );
$base = 'nex-forms/nex-forms.php';
ok( isset( $t->response[ $base ] ) && $nf_new === $t->response[ $base ]->new_version, "update to $nf_new offered" );
ok( str_ends_with( $t->response[ $base ]->package ?? '', '/v' . $nf_new . '/nex-forms.zip' ), 'public download URL used' );
ok( isset( $GLOBALS['nf_wporg_body'] ) && ! str_contains( $GLOBALS['nf_wporg_body'], 'nex-forms/nex-forms.php' ), 'not sent to wordpress.org (no slug collision)' );

$info = plugins_api( 'plugin_information', array( 'slug' => 'nex-forms' ) );
ok( ! is_wp_error( $info ) && $nf_new === $info->version && ! str_contains( $info->sections['changelog'], '<script>' ), 'details popup with escaped changelog' );

echo "Checksum\n";
$GLOBALS['nf_digest_real'] = $GLOBALS['nf_digest'];
$GLOBALS['nf_digest'] = str_repeat( '0', 64 );
delete_site_transient( 'nexforms_release' );
$r = NexForms_Updater::verified_download( false, NexForms_Updater::latest( true )['package'], null );
ok( is_wp_error( $r ) && 'nexforms_checksum' === $r->get_error_code(), 'bad checksum rejected' );
$GLOBALS['nf_digest'] = $GLOBALS['nf_digest_real'];
delete_site_transient( 'nexforms_release' );
NexForms_Updater::latest( true );

echo "Private repo token\n";
$args = NexForms_Updater::auth_headers( array(), 'https://api.github.com/repos/nexdev-team/NexForms/releases/assets/1' );
ok( 'application/octet-stream' === $args['headers']['Accept'], 'asset download asks for binary' );
$args = NexForms_Updater::auth_headers( array(), 'https://api.github.com/repos/someone/else/releases/assets/1' );
ok( empty( $args['headers'] ), 'other repos untouched' );

echo "Upgrade (as a background auto-update)\n";
add_filter( 'wp_doing_cron', '__return_true' );
$skin     = new Automatic_Upgrader_Skin();
$upgrader = new Plugin_Upgrader( $skin );
$result   = $upgrader->upgrade( $base );
ok( true === $result, 'upgrade ran: ' . ( is_wp_error( $result ) ? $result->get_error_message() : var_export( $result, true ) ) . ' ' . implode( ' | ', (array) $skin->get_upgrade_messages() ) );
$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $base, false, false );
ok( $nf_new === $data['Version'], 'installed version is now ' . $data['Version'] );
ok( is_dir( WP_PLUGIN_DIR . '/nex-forms' ) && ! is_dir( WP_PLUGIN_DIR . '/nex-forms-1' ), 'same folder name kept' );
ok( ! file_exists( WP_PLUGIN_DIR . '/nex-forms/tests' ), 'release zip has no tests' );
ok( is_plugin_active( $base ), 'plugin stays active during auto-update' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
