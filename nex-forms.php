<?php
/**
 * Plugin Name:       NexForms
 * Description:       Lightweight contact forms: visual builder, validation, phone country picker, Cloudflare Turnstile and themeable designs. Sends through wp_mail (FluentSMTP), stores no submissions.
 * Version:           0.1.6
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            NexDev
 * License:           GPL-2.0-or-later
 * Text Domain:       nex-forms
 * Domain Path:       /languages
 * Update URI:        https://github.com/nexdev-team/NexForms
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXFORMS_VERSION', '0.1.6' );
define( 'NEXFORMS_FILE', __FILE__ );
define( 'NEXFORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXFORMS_URL', plugin_dir_url( __FILE__ ) );

require_once NEXFORMS_DIR . 'includes/class-nexforms-countries.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-schema.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-forms.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-settings.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-render.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-submit.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-rest.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-admin.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-updater.php';
require_once NEXFORMS_DIR . 'includes/class-nexforms-fast.php';

NexForms_Updater::init();

register_activation_hook( __FILE__, array( 'NexForms_Fast', 'sync' ) );
register_deactivation_hook( __FILE__, array( 'NexForms_Fast', 'remove' ) );

add_action( 'init', static function () {
	load_plugin_textdomain( 'nex-forms', false, dirname( plugin_basename( NEXFORMS_FILE ) ) . '/languages' );
	NexForms_Forms::register_post_type();
	NexForms_Forms::maybe_upgrade();
	NexForms_Render::register();
} );

add_action( 'rest_api_init', array( 'NexForms_REST', 'register_routes' ) );

if ( is_admin() ) {
	NexForms_Admin::init();
}
