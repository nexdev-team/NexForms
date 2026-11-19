<?php
/**
 * Installs/removes the small must-use plugin that makes form submissions skip unrelated plugins.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Fast {

	private const FILE = 'nexforms-fast-submit.php';

	private static function target(): string {
		return trailingslashit( WPMU_PLUGIN_DIR ) . self::FILE;
	}

	private static function source(): string {
		return NEXFORMS_DIR . 'mu/' . self::FILE;
	}

	/** Make the installed file match the setting (and the bundled version). Safe to call often. */
	public static function sync(): void {
		$want = ! empty( NexForms_Settings::get()['fast_submit'] ) && ! is_multisite();
		$have = is_file( self::target() );
		if ( $want ) {
			if ( ! $have || md5_file( self::target() ) !== md5_file( self::source() ) ) {
				if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
					wp_mkdir_p( WPMU_PLUGIN_DIR );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions -- tiny file copy into mu-plugins.
				@copy( self::source(), self::target() );
			}
		} elseif ( $have ) {
			wp_delete_file( self::target() );
		}
	}

	public static function remove(): void {
		if ( is_file( self::target() ) ) {
			wp_delete_file( self::target() );
		}
	}

	/** 'on', 'off', or 'failed' (setting on but the file could not be written). */
	public static function status(): string {
		$want = ! empty( NexForms_Settings::get()['fast_submit'] );
		if ( ! $want ) {
			return 'off';
		}
		return is_file( self::target() ) ? 'on' : 'failed';
	}
}
