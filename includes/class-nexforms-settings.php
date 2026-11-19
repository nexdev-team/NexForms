<?php
/**
 * Global plugin settings (one autoloaded option).
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Settings {

	public const OPTION = 'nexforms_settings';

	public static function defaults(): array {
		return array(
			'turnstile_site_key'  => '',
			'turnstile_secret'    => '',
			'trust_cloudflare'    => true,
			'rate_limit'          => 5,
			'rate_window'         => 10,
			'min_seconds'         => 3,
			'background_mail'     => true,
			'fast_submit'         => true,
			'preferred_countries' => NexForms_Countries::EUROPE,
		);
	}

	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function turnstile_ready(): bool {
		$s = self::get();
		return '' !== $s['turnstile_site_key'] && '' !== $s['turnstile_secret'];
	}

	/** Settings safe to return to the admin UI (secret is never returned). */
	public static function public_view(): array {
		$s = self::get();
		$s['turnstile_secret_set'] = '' !== $s['turnstile_secret'];
		$s['fast_submit_status']   = NexForms_Fast::status();
		$s['background_supported'] = function_exists( 'litespeed_finish_request' ) || function_exists( 'fastcgi_finish_request' );
		unset( $s['turnstile_secret'] );
		return $s;
	}

	public static function update( array $in ): array {
		$s = self::get();
		if ( array_key_exists( 'turnstile_site_key', $in ) ) {
			$s['turnstile_site_key'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $in['turnstile_site_key'] );
		}
		// Empty secret keeps the stored one; clearing needs the explicit flag.
		if ( ! empty( $in['turnstile_secret'] ) ) {
			$s['turnstile_secret'] = preg_replace( '/[^A-Za-z0-9_\-.]/', '', (string) $in['turnstile_secret'] );
		}
		if ( ! empty( $in['turnstile_clear_secret'] ) ) {
			$s['turnstile_secret'] = '';
		}
		if ( array_key_exists( 'trust_cloudflare', $in ) ) {
			$s['trust_cloudflare'] = (bool) $in['trust_cloudflare'];
		}
		if ( isset( $in['rate_limit'] ) ) {
			$s['rate_limit'] = max( 1, min( 100, (int) $in['rate_limit'] ) );
		}
		if ( isset( $in['rate_window'] ) ) {
			$s['rate_window'] = max( 1, min( 1440, (int) $in['rate_window'] ) );
		}
		foreach ( array( 'background_mail', 'fast_submit' ) as $flag ) {
			if ( array_key_exists( $flag, $in ) ) {
				$s[ $flag ] = (bool) $in[ $flag ];
			}
		}
		if ( isset( $in['min_seconds'] ) ) {
			$s['min_seconds'] = max( 0, min( 30, (int) $in['min_seconds'] ) );
		}
		if ( isset( $in['preferred_countries'] ) ) {
			$list = is_array( $in['preferred_countries'] ) ? $in['preferred_countries'] : preg_split( '/[,\s]+/', (string) $in['preferred_countries'] );
			$s['preferred_countries'] = array_values(
				array_unique( array_filter( array_map( 'strtoupper', array_map( 'strval', $list ) ), array( 'NexForms_Countries', 'exists' ) ) )
			);
		}
		update_option( self::OPTION, $s, true );
		NexForms_Fast::sync();
		return self::public_view();
	}
}
