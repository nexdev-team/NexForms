<?php
/**
 * Form schema: field types, defaults and sanitisation of builder input.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Schema {

	public const FIELD_TYPES = array( 'text', 'email', 'phone', 'textarea', 'select', 'radio', 'checkboxes', 'consent', 'number', 'url', 'date', 'hidden' );
	public const PRESETS     = array( 'theme', 'clean', 'rounded', 'glossy', 'outline', 'underline' );
	public const SIZES       = array( 'sm', 'md', 'lg' );
	public const WIDTHS      = array( 'full', 'half', 'third' );

	public static function default_form(): array {
		return array(
			'title'    => __( 'Contact form', 'nex-forms' ),
			'status'   => 'publish',
			'fields'   => array(
				self::field( 'text', array( 'label' => __( 'Name', 'nex-forms' ), 'name' => 'name', 'required' => true, 'width' => 'half', 'autocomplete' => 'name' ) ),
				self::field( 'email', array( 'label' => __( 'Email', 'nex-forms' ), 'name' => 'email', 'required' => true, 'width' => 'half' ) ),
				self::field( 'phone', array( 'label' => __( 'Phone', 'nex-forms' ), 'name' => 'phone' ) ),
				self::field( 'textarea', array( 'label' => __( 'Message', 'nex-forms' ), 'name' => 'message', 'required' => true ) ),
			),
			'settings' => self::default_settings(),
			'design'   => self::default_design(),
		);
	}

	/** Phone default from the site language: bg_BG → BG, el → GR, de_AT → AT; otherwise the first preferred country. */
	public static function default_country(): string {
		$locale = function_exists( 'get_locale' ) ? get_locale() : '';
		if ( 'el' === $locale ) {
			return 'GR';
		}
		$parts = explode( '_', $locale );
		$region = strtoupper( $parts[1] ?? '' );
		if ( in_array( $region, NexForms_Countries::EUROPE, true ) ) {
			return $region;
		}
		$preferred = class_exists( 'NexForms_Settings' ) ? (array) ( get_option( NexForms_Settings::OPTION, array() )['preferred_countries'] ?? array() ) : array();
		return $preferred && NexForms_Countries::exists( (string) $preferred[0] ) ? (string) $preferred[0] : 'GR';
	}

	public static function default_settings(): array {
		return array(
			'submit_label'    => __( 'Send message', 'nex-forms' ),
			'success_message' => __( 'Thank you! Your message has been sent.', 'nex-forms' ),
			'redirect_url'    => '',
			'turnstile'       => true,
			'email_to'        => '',
			'email_subject'   => '[{site_name}] {form_title}',
			'reply_to_field'  => '',
		);
	}

	public static function default_design(): array {
		return array(
			'preset'     => 'clean',
			'accent'     => '',
			'radius'     => null,
			'size'       => 'md',
			'button_full'=> false,
		);
	}

	public static function field( string $type, array $props = array() ): array {
		$base = array(
			'id'              => 'f' . substr( md5( uniqid( '', true ) ), 0, 8 ),
			'type'            => $type,
			'label'           => '',
			'name'            => '',
			'placeholder'     => '',
			'help'            => '',
			'required'        => false,
			'width'           => 'full',
			'default'         => '',
			'autocomplete'    => '',
			'options'         => array(),
			'minlength'       => null,
			'maxlength'       => null,
			'min'             => null,
			'max'             => null,
			'pattern'         => '',
			'pattern_message' => '',
			'rows'            => 5,
			'country'         => self::default_country(),
		);
		if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) && empty( $props['options'] ) ) {
			$base['options'] = array(
				array( 'label' => __( 'Option 1', 'nex-forms' ), 'value' => 'option-1' ),
				array( 'label' => __( 'Option 2', 'nex-forms' ), 'value' => 'option-2' ),
			);
		}
		return array_merge( $base, $props );
	}

	/**
	 * Sanitise a full form payload from the builder. Throws on unrecoverable input.
	 */
	public static function sanitize_form( array $in ): array {
		$out           = array();
		$out['title']  = sanitize_text_field( (string) ( $in['title'] ?? '' ) );
		$out['title']  = '' === $out['title'] ? __( 'Untitled form', 'nex-forms' ) : $out['title'];
		$out['status'] = ( $in['status'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish';
		// Shortcode name: typed by the user, or made from the title. Uniqueness is enforced on save.
		$out['slug'] = self::slugify( (string) ( $in['slug'] ?? '' ) );
		if ( '' === $out['slug'] ) {
			$out['slug'] = self::slugify( $out['title'] );
		}

		$fields = array();
		$names  = array();
		$ids    = array();
		foreach ( array_slice( (array) ( $in['fields'] ?? array() ), 0, 60 ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$f = self::sanitize_field( $raw );
			if ( null === $f ) {
				continue;
			}
			// Unique id.
			while ( isset( $ids[ $f['id'] ] ) ) {
				$f['id'] = 'f' . substr( md5( uniqid( '', true ) ), 0, 8 );
			}
			$ids[ $f['id'] ] = true;
			// Unique, non-empty name.
			$base = '' !== $f['name'] ? $f['name'] : ( sanitize_key( remove_accents( wp_strip_all_tags( $f['label'] ) ) ) ?: $f['type'] );
			$base = substr( str_replace( '-', '_', $base ), 0, 40 );
			$name = $base;
			$i    = 2;
			while ( isset( $names[ $name ] ) || in_array( $name, array( 'nf_hp', 'nf_t', 'nf_page' ), true ) ) {
				$name = $base . '_' . $i++;
			}
			$names[ $name ] = true;
			$f['name']      = $name;
			$fields[]       = $f;
		}
		$out['fields'] = $fields;

		$s                  = (array) ( $in['settings'] ?? array() );
		$d                  = self::default_settings();
		$out['settings']    = array(
			'submit_label'    => sanitize_text_field( (string) ( $s['submit_label'] ?? $d['submit_label'] ) ) ?: $d['submit_label'],
			'success_message' => wp_kses_post( (string) ( $s['success_message'] ?? $d['success_message'] ) ),
			'redirect_url'    => esc_url_raw( (string) ( $s['redirect_url'] ?? '' ) ),
			'turnstile'       => ! empty( $s['turnstile'] ),
			'email_to'        => self::sanitize_emails( (string) ( $s['email_to'] ?? '' ) ),
			'email_subject'   => sanitize_text_field( (string) ( $s['email_subject'] ?? $d['email_subject'] ) ) ?: $d['email_subject'],
			'reply_to_field'  => sanitize_key( (string) ( $s['reply_to_field'] ?? '' ) ),
		);

		$g             = (array) ( $in['design'] ?? array() );
		$radius        = $g['radius'] ?? null;
		$out['design'] = array(
			'preset'      => in_array( $g['preset'] ?? '', self::PRESETS, true ) ? $g['preset'] : 'clean',
			'accent'      => sanitize_hex_color( (string) ( $g['accent'] ?? '' ) ) ?? '',
			'radius'      => ( null === $radius || '' === $radius ) ? null : max( 0, min( 40, (int) $radius ) ),
			'size'        => in_array( $g['size'] ?? '', self::SIZES, true ) ? $g['size'] : 'md',
			'button_full' => ! empty( $g['button_full'] ),
		);
		return $out;
	}

	public static function sanitize_field( array $raw ): ?array {
		$type = (string) ( $raw['type'] ?? '' );
		if ( ! in_array( $type, self::FIELD_TYPES, true ) ) {
			return null;
		}
		$f                = self::field( $type, array( 'options' => array( 'x' ) ) );
		$f['options']     = array();
		$id               = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $raw['id'] ?? '' ) ) );
		$f['id']          = '' !== $id ? substr( $id, 0, 20 ) : $f['id'];
		$f['label']       = 'consent' === $type
			? wp_kses( (string) ( $raw['label'] ?? '' ), self::inline_html() )
			: sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
		$f['name']        = substr( str_replace( '-', '_', sanitize_key( (string) ( $raw['name'] ?? '' ) ) ), 0, 40 );
		$f['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
		$f['help']        = wp_kses( (string) ( $raw['help'] ?? '' ), self::inline_html() );
		$f['required']    = ! empty( $raw['required'] ) && 'hidden' !== $type;
		$f['width']       = in_array( $raw['width'] ?? '', self::WIDTHS, true ) ? $raw['width'] : 'full';
		$f['default']     = sanitize_text_field( (string) ( $raw['default'] ?? '' ) );
		$f['autocomplete'] = preg_replace( '/[^a-z0-9 \-]/', '', strtolower( (string) ( $raw['autocomplete'] ?? '' ) ) );
		foreach ( array( 'minlength', 'maxlength' ) as $k ) {
			$v       = $raw[ $k ] ?? null;
			$f[ $k ] = ( null === $v || '' === $v ) ? null : max( 0, min( 20000, (int) $v ) );
		}
		foreach ( array( 'min', 'max' ) as $k ) {
			$v = $raw[ $k ] ?? null;
			if ( null === $v || '' === $v ) {
				$f[ $k ] = null;
			} elseif ( 'date' === $type ) {
				$f[ $k ] = self::valid_date( (string) $v ) ? (string) $v : null;
			} else {
				$f[ $k ] = is_numeric( $v ) ? $v + 0 : null;
			}
		}
		$pattern = (string) ( $raw['pattern'] ?? '' );
		$f['pattern']         = ( '' !== $pattern && strlen( $pattern ) <= 300 && self::pattern_ok( $pattern ) ) ? $pattern : '';
		$f['pattern_message'] = sanitize_text_field( (string) ( $raw['pattern_message'] ?? '' ) );
		$f['rows']            = max( 2, min( 20, (int) ( $raw['rows'] ?? 5 ) ) );
		$country              = strtoupper( (string) ( $raw['country'] ?? '' ) );
		$f['country']         = NexForms_Countries::exists( $country ) ? $country : self::default_country();

		if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
			$seen = array();
			foreach ( array_slice( (array) ( $raw['options'] ?? array() ), 0, 200 ) as $o ) {
				$label = sanitize_text_field( (string) ( is_array( $o ) ? ( $o['label'] ?? '' ) : $o ) );
				$value = sanitize_text_field( (string) ( is_array( $o ) ? ( $o['value'] ?? '' ) : '' ) );
				$value = '' === $value ? $label : $value;
				if ( '' === $label || isset( $seen[ $value ] ) ) {
					continue;
				}
				$seen[ $value ]  = true;
				$f['options'][] = array( 'label' => $label, 'value' => $value );
			}
		}
		return $f;
	}

	public static function pattern_ok( string $pattern ): bool {
		// Compatible with both PHP (PCRE) and the browser's `pattern` attribute (JS RegExp with v/u flag).
		if ( str_contains( $pattern, '#' ) && ! str_contains( $pattern, '\#' ) ) {
			$pattern = str_replace( '#', '\#', $pattern );
		}
		return false !== @preg_match( '#^(?:' . $pattern . ')$#u', '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	public static function pattern_match( string $pattern, string $value ): bool {
		$pattern = str_replace( array( '\#', '#' ), array( '#', '\#' ), $pattern );
		return 1 === @preg_match( '#^(?:' . $pattern . ')$#u', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	private const TRANSLIT = array(
		// Greek.
		'α' => 'a', 'ά' => 'a', 'β' => 'v', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'έ' => 'e', 'ζ' => 'z', 'η' => 'i', 'ή' => 'i',
		'θ' => 'th', 'ι' => 'i', 'ί' => 'i', 'ϊ' => 'i', 'ΐ' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x',
		'ο' => 'o', 'ό' => 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's', 'τ' => 't', 'υ' => 'y', 'ύ' => 'y', 'ϋ' => 'y',
		'ΰ' => 'y', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o', 'ώ' => 'o',
		// Bulgarian / Russian Cyrillic.
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
		'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
		'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sht', 'ъ' => 'a', 'ы' => 'y', 'ь' => 'y',
		'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
	);

	/** Latin-only slug (a-z, 0-9, hyphen) from any title, transliterating Greek and Cyrillic. */
	public static function slugify( string $text ): string {
		$text = mb_strtolower( wp_strip_all_tags( $text ), 'UTF-8' );
		$text = strtr( $text, self::TRANSLIT );
		$text = strtolower( remove_accents( $text ) );
		$text = preg_replace( '/[^a-z0-9]+/', '-', $text );
		return substr( trim( $text, '-' ), 0, 60 );
	}

	public static function valid_date( string $v ): bool {
		$d = DateTime::createFromFormat( '!Y-m-d', $v );
		return $d && $d->format( 'Y-m-d' ) === $v;
	}

	public static function sanitize_emails( string $list ): string {
		$ok = array();
		foreach ( preg_split( '/[,;\s]+/', $list ) as $e ) {
			$e = sanitize_email( $e );
			if ( $e && is_email( $e ) ) {
				$ok[] = $e;
			}
		}
		return implode( ', ', array_unique( array_slice( $ok, 0, 10 ) ) );
	}

	public static function inline_html(): array {
		return array(
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'br'     => array(),
		);
	}
}
