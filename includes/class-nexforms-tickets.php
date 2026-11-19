<?php
/**
 * Ticket numbers: one counter per prefix (e.g. SUP-00042, RET-00007). Only the counters are stored,
 * never the submissions.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Tickets {

	private const OPTION_PREFIX = 'nexforms_ticket_seq_';
	private const DIGITS        = 5;

	/** Prefix for this submission: from the chosen field's option, else the form's prefix. */
	public static function prefix_for( array $form, array $params ): string {
		$s     = $form['settings'];
		$field = (string) $s['ticket_field'];
		if ( '' !== $field && ! empty( $s['ticket_map'] ) ) {
			$picked = $params[ $field ] ?? '';
			if ( is_string( $picked ) && isset( $s['ticket_map'][ $picked ] ) ) {
				return (string) $s['ticket_map'][ $picked ];
			}
		}
		return (string) $s['ticket_prefix'];
	}

	/** Next code for a prefix, e.g. "SUP-00042". Atomic on MySQL/MariaDB. */
	public static function next( string $prefix ): string {
		$prefix = NexForms_Schema::ticket_prefix( $prefix ) ?: 'SUP';
		return $prefix . '-' . str_pad( (string) self::increment( $prefix ), self::DIGITS, '0', STR_PAD_LEFT );
	}

	private static function increment( string $prefix ): int {
		global $wpdb;
		$name = self::OPTION_PREFIX . strtolower( $prefix );
		$quiet = $wpdb->suppress_errors( true );

		for ( $try = 0; $try < 3; $try++ ) {
			// MySQL/MariaDB: add 1 and read the new value in one atomic step (LAST_INSERT_ID(expr) is per connection).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1) WHERE option_name = %s", $name ) );
			if ( false === $updated ) {
				break; // No LAST_INSERT_ID(expr) on this database: use the fallback below.
			}
			if ( $updated > 0 ) {
				$n = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::flush( $name );
				$wpdb->suppress_errors( $quiet );
				return $n;
			}
			// First ticket for this prefix. INSERT IGNORE: if another request created it at the same moment, retry the update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off')", $name ) ) ) {
				self::flush( $name );
				$wpdb->suppress_errors( $quiet );
				return 1;
			}
		}

		// Fallback (e.g. SQLite in tests): plain increment, then read.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s", $name ) ) ) {
			add_option( $name, 1, '', false );
		}
		self::flush( $name );
		$wpdb->suppress_errors( $quiet );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private static function flush( string $name ): void {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/** Current counters, for the admin: [ 'SUP' => 42, 'RET' => 7 ]. */
	public static function counters(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::OPTION_PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ strtoupper( substr( $r->option_name, strlen( self::OPTION_PREFIX ) ) ) ] = (int) $r->option_value;
		}
		ksort( $out );
		return $out;
	}
}
