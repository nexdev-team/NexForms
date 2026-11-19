<?php
/**
 * Admin menu and builder bootstrap (React from WordPress core, no build step).
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Admin {

	private static array $hooks = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NEXFORMS_FILE ), array( __CLASS__, 'action_links' ) );
		// The menu icon is a PNG (NexDev mark); WordPress would show it at full size without this.
		add_action(
			'admin_head',
			static function () {
				echo '<style>#adminmenu .toplevel_page_nexforms .wp-menu-image img{width:20px;height:20px;padding:7px 0 0;opacity:1;border-radius:50%}</style>';
			}
		);
	}

	public static function menu(): void {
		$icon          = NEXFORMS_URL . 'assets/nexdev-mark.png';
		self::$hooks[] = add_menu_page( __( 'NexForms', 'nex-forms' ), __( 'NexForms', 'nex-forms' ), 'manage_options', 'nexforms', array( __CLASS__, 'page' ), $icon, 58 );
		self::$hooks[] = add_submenu_page( 'nexforms', __( 'Forms', 'nex-forms' ), __( 'Forms', 'nex-forms' ), 'manage_options', 'nexforms', array( __CLASS__, 'page' ) );
		self::$hooks[] = add_submenu_page( 'nexforms', __( 'Settings', 'nex-forms' ), __( 'Settings', 'nex-forms' ), 'manage_options', 'nexforms-settings', array( __CLASS__, 'page' ) );
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=nexforms' ) ) . '">' . esc_html__( 'Forms', 'nex-forms' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=nexforms-settings' ) ) . '">' . esc_html__( 'Settings', 'nex-forms' ) . '</a>'
		);
		return $links;
	}

	public static function page(): void {
		$view = ( isset( $_GET['page'] ) && 'nexforms-settings' === $_GET['page'] ) ? 'settings' : 'forms'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap nfb-wrap"><div id="nexforms-app" data-view="' . esc_attr( $view ) . '"><p class="nfb-loading">' . esc_html__( 'Loading…', 'nex-forms' ) . '</p></div></div>';
	}

	public static function assets( string $hook ): void {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'nexforms', NEXFORMS_URL . 'assets/nexforms.css', array(), NEXFORMS_VERSION );
		wp_enqueue_style( 'nexforms-builder', NEXFORMS_URL . 'assets/admin/builder.css', array( 'wp-components', 'nexforms' ), NEXFORMS_VERSION );
		wp_enqueue_script(
			'nexforms-builder',
			NEXFORMS_URL . 'assets/admin/builder.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			NEXFORMS_VERSION,
			true
		);
		wp_set_script_translations( 'nexforms-builder', 'nex-forms', NEXFORMS_DIR . 'languages' );

		$settings  = NexForms_Settings::get();
		$countries = array_map(
			static fn( $c ) => array( $c['iso'], $c['name'], $c['flag'], $c['dial'] ),
			NexForms_Countries::ordered( $settings['preferred_countries'] )
		);
		$boot      = array(
			'adminUrl'       => admin_url( 'admin.php' ),
			'adminEmail'     => get_option( 'admin_email' ),
			'siteName'       => get_bloginfo( 'name' ),
			'turnstileReady' => NexForms_Settings::turnstile_ready(),
			'countries'      => $countries,
			'preferred'      => $settings['preferred_countries'],
			'europe'         => NexForms_Countries::EUROPE,
			'newField'       => array_combine(
				NexForms_Schema::FIELD_TYPES,
				array_map( static fn( $t ) => NexForms_Schema::field( $t ), NexForms_Schema::FIELD_TYPES )
			),
			'blankForm'      => NexForms_Schema::default_form(),
			'version'        => NEXFORMS_VERSION,
			'logoUrl'        => NEXFORMS_URL . 'assets/nexdev-mark.png',
		);
		wp_add_inline_script( 'nexforms-builder', 'window.nexformsBoot=' . wp_json_encode( $boot ) . ';', 'before' );
	}
}
