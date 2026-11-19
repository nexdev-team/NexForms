<?php
/**
 * Updates from GitHub Releases.
 *
 * The plugin header has `Update URI: https://github.com/nexdev-team/NexForms`, so WordPress never offers the
 * unrelated wordpress.org plugin with the same slug and instead asks the `update_plugins_github.com` filter.
 * Each release must carry an asset named `nex-forms.zip` (built by .github/workflows/release.yml).
 *
 * Private repository: add `define( 'NEXFORMS_GITHUB_TOKEN', 'github_pat_…' );` to wp-config.php
 * (fine-grained token, read-only "Contents" on this repository).
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Updater {

	public const REPO      = 'nexdev-team/NexForms';
	public const ASSET     = 'nex-forms.zip';
	private const CACHE    = 'nexforms_release';
	private const CACHE_OK = 6 * HOUR_IN_SECONDS;
	private const CACHE_KO = 30 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_filter( 'http_request_args', array( __CLASS__, 'auth_headers' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verified_download' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_nexforms_check_updates', array( __CLASS__, 'force_check' ) );
		add_action( 'upgrader_process_complete', static fn() => delete_site_transient( self::CACHE ) );
	}

	private static function repo(): string {
		return (string) apply_filters( 'nexforms_update_repo', self::REPO );
	}

	private static function token(): string {
		return defined( 'NEXFORMS_GITHUB_TOKEN' ) ? (string) NEXFORMS_GITHUB_TOKEN : '';
	}

	/**
	 * Latest release data, cached.
	 *
	 * @return array{version:string, package:string, digest:string, url:string, notes:string, date:string}|null
	 */
	public static function latest( bool $force = false ): ?array {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) ) {
				return $cached['version'] ? $cached : null;
			}
		}

		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( self::token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array(
				'timeout'    => 10,
				'headers'    => $headers,
				'user-agent' => 'NexForms/' . NEXFORMS_VERSION . '; ' . home_url(),
			)
		);
		$data = is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response )
			? null
			: json_decode( wp_remote_retrieve_body( $response ), true );

		$release = null;
		if ( is_array( $data ) && empty( $data['draft'] ) && empty( $data['prerelease'] ) ) {
			$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
			foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
				if ( self::ASSET !== ( $asset['name'] ?? '' ) || ! preg_match( '/^\d+(\.\d+){1,3}$/', $version ) ) {
					continue;
				}
				$release = array(
					'version' => $version,
					// Private repos must download through the API URL with a token; public ones use the plain link.
					'package' => self::token() ? (string) $asset['url'] : (string) $asset['browser_download_url'],
					'digest'  => str_starts_with( (string) ( $asset['digest'] ?? '' ), 'sha256:' ) ? substr( $asset['digest'], 7 ) : '',
					'url'     => (string) ( $data['html_url'] ?? 'https://github.com/' . self::repo() ),
					'notes'   => (string) ( $data['body'] ?? '' ),
					'date'    => (string) ( $data['published_at'] ?? '' ),
				);
				break;
			}
		}

		// Cache failures briefly as an empty marker so a broken network does not slow every admin page.
		set_site_transient( self::CACHE, $release ?? array( 'version' => '' ), $release ? self::CACHE_OK : self::CACHE_KO );
		return $release;
	}

	/** WordPress asks this for plugins whose Update URI host is github.com. */
	public static function check( $update, array $plugin_data, string $plugin_file ) {
		if ( plugin_basename( NEXFORMS_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'           => $plugin_data['UpdateURI'],
			'slug'         => 'nex-forms',
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => $plugin_data['RequiresWP'] ?? '',
			'requires_php' => $plugin_data['RequiresPHP'] ?? '',
			'icons'        => array(),
		);
	}

	/** "View details" popup. Only answers for this plugin (another plugin on wordpress.org uses the same slug). */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== 'nex-forms' ) {
			return $result;
		}
		$installed = get_plugins();
		if ( ! isset( $installed[ plugin_basename( NEXFORMS_FILE ) ] ) ) {
			return $result;
		}
		$release = self::latest();
		$notes   = $release && '' !== trim( $release['notes'] ) ? $release['notes'] : __( 'No release notes.', 'nex-forms' );
		return (object) array(
			'name'          => 'NexForms',
			'slug'          => 'nex-forms',
			'version'       => $release['version'] ?? NEXFORMS_VERSION,
			'author'        => 'NexDev',
			'homepage'      => 'https://github.com/' . self::repo(),
			'requires'      => '6.5',
			'requires_php'  => '8.0',
			'last_updated'  => $release['date'] ?? '',
			'download_link' => $release['package'] ?? '',
			'sections'      => array(
				'description' => esc_html__( 'Lightweight contact forms: visual builder, validation, phone country picker, Cloudflare Turnstile and themeable designs. Sends through wp_mail (FluentSMTP), stores no submissions.', 'nex-forms' ),
				'changelog'   => '<pre style="white-space:pre-wrap;font:inherit">' . esc_html( $notes ) . '</pre>',
			),
		);
	}

	/** Add the token (private repos) and the binary Accept header for asset downloads from this repo. */
	public static function auth_headers( array $args, string $url ): array {
		$prefix = 'https://api.github.com/repos/' . self::repo() . '/releases/assets/';
		if ( str_starts_with( $url, $prefix ) ) {
			$args['headers']           = (array) ( $args['headers'] ?? array() );
			$args['headers']['Accept'] = 'application/octet-stream';
			if ( self::token() ) {
				$args['headers']['Authorization'] = 'Bearer ' . self::token();
			}
		}
		return $args;
	}

	/** Download the package ourselves so the SHA-256 published by GitHub can be checked before installing. */
	public static function verified_download( $reply, $package, $upgrader ) {
		$release = self::latest();
		if ( false !== $reply || ! $release || $package !== $release['package'] || '' === $release['digest'] ) {
			return $reply;
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! hash_equals( $release['digest'], (string) hash_file( 'sha256', $file ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'nexforms_checksum', __( 'The NexForms update failed its checksum and was not installed.', 'nex-forms' ) );
		}
		return $file;
	}

	/** Make sure the extracted folder is always nex-forms/, whatever the archive used. */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( ( $hook_extra['plugin'] ?? '' ) !== plugin_basename( NEXFORMS_FILE ) || ! $wp_filesystem ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . 'nex-forms/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		return $wp_filesystem->move( $source, $wanted, true ) ? $wanted : new WP_Error( 'nexforms_folder', __( 'Could not prepare the NexForms update folder.', 'nex-forms' ) );
	}

	public static function row_meta( array $links, string $file ): array {
		if ( plugin_basename( NEXFORMS_FILE ) === $file && current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nexforms_check_updates' ), 'nexforms_check_updates' ) ) . '">' . esc_html__( 'Check for updates', 'nex-forms' ) . '</a>';
		}
		return $links;
	}

	public static function force_check(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update plugins.', 'nex-forms' ) );
		}
		check_admin_referer( 'nexforms_check_updates' );
		delete_site_transient( self::CACHE );
		delete_site_transient( 'update_plugins' );
		self::latest( true );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
}
