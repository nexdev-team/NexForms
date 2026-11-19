<?php
/**
 * Submission handling: anti-spam, validation, email. Nothing is written to the database
 * apart from short-lived rate-limit transients.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Submit {

	private const MAX_BODY = 65536;

	/** Cloudflare edge ranges (https://www.cloudflare.com/ips/). Filterable via `nexforms_cloudflare_ranges`. */
	private const CF_RANGES = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
		'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);

	public static function handle( WP_REST_Request $request ) {
		$form = NexForms_Forms::get( (int) $request['id'] );
		if ( ! $form || 'publish' !== $form['status'] ) {
			return self::fail( __( 'This form is not available.', 'nex-forms' ), 404 );
		}

		$length = (int) $request->get_header( 'content_length' );
		if ( $length > self::MAX_BODY ) {
			return self::fail( __( 'The submission is too large.', 'nex-forms' ), 413 );
		}

		$settings = NexForms_Settings::get();
		$params   = $request->get_body_params();
		if ( empty( $params ) ) {
			$params = (array) $request->get_json_params();
		}

		// Honeypot: answer like a success so bots learn nothing.
		if ( ! empty( $params['nf_hp'] ) ) {
			return self::ok( $form );
		}

		// Time trap (elapsed milliseconds measured by the browser from first render).
		$elapsed = isset( $params['nf_t'] ) ? (int) $params['nf_t'] : 0;
		if ( $settings['min_seconds'] > 0 && $elapsed < $settings['min_seconds'] * 1000 ) {
			return self::fail( __( 'That was quick! Please wait a moment and try again.', 'nex-forms' ), 429, array( 'code' => 'too_fast' ) );
		}

		$ip = self::client_ip( $settings );
		if ( ! self::rate_ok( $ip, $settings ) ) {
			return self::fail( __( 'Too many submissions. Please try again later.', 'nex-forms' ), 429 );
		}

		if ( ! empty( $form['settings']['turnstile'] ) && NexForms_Settings::turnstile_ready() ) {
			$token = (string) ( $params['cf-turnstile-response'] ?? '' );
			if ( ! self::turnstile_ok( $token, $ip, $settings ) ) {
				return self::fail( __( 'The security check failed. Please try again.', 'nex-forms' ), 400, array( 'code' => 'captcha' ) );
			}
		}

		list( $values, $errors ) = self::validate( $form, $params );
		if ( $errors ) {
			return self::fail( __( 'Please check the highlighted fields.', 'nex-forms' ), 422, array( 'errors' => $errors ) );
		}

		$context = array(
			'page_url' => esc_url_raw( (string) ( $params['nf_page'] ?? '' ) ),
			'ip'       => $ip,
		);

		/**
		 * Short-circuit or replace sending. Return true/false to skip the built-in email.
		 */
		$sent = apply_filters( 'nexforms_pre_send', null, $form, $values, $context );
		if ( null === $sent ) {
			$sent = self::send( $form, $values, $context );
		}
		if ( ! $sent ) {
			return self::fail( __( 'Your message could not be sent right now. Please try again later or contact us directly.', 'nex-forms' ), 500, array( 'code' => 'mail' ) );
		}

		do_action( 'nexforms_submitted', $form, $values, $context );
		return self::ok( $form );
	}

	/**
	 * @return array{0: array<string, array{label:string, value:string, raw:mixed, type:string}>, 1: array<string,string>}
	 */
	public static function validate( array $form, array $params ): array {
		$values = array();
		$errors = array();

		foreach ( $form['fields'] as $f ) {
			$name = $f['name'];
			$type = $f['type'];
			$raw  = $params[ $name ] ?? null;
			$err  = null;
			$out  = '';

			switch ( $type ) {
				case 'hidden':
					$out = (string) $f['default']; // Always from the schema, never the request.
					break;

				case 'phone':
					$raw = is_array( $raw ) ? $raw : array( 'num' => is_string( $raw ) ? $raw : '' );
					$cc  = strtoupper( sanitize_text_field( (string) ( $raw['cc'] ?? $f['country'] ) ) );
					$cc  = NexForms_Countries::exists( $cc ) ? $cc : $f['country'];
					$num = trim( sanitize_text_field( (string) ( $raw['num'] ?? '' ) ) );
					if ( '' === $num ) {
						$err = $f['required'] ? 'required' : null;
					} else {
						$e164 = NexForms_Countries::to_e164( $cc, $num );
						if ( null === $e164 ) {
							$err = 'phone';
						} else {
							$out = $e164;
						}
					}
					break;

				case 'checkboxes':
					$picked  = array_map( 'strval', is_array( $raw ) ? $raw : array() );
					$allowed = array_column( $f['options'], 'label', 'value' );
					$labels  = array();
					foreach ( $picked as $p ) {
						// Body params are already unslashed by the REST server.
						if ( ! isset( $allowed[ $p ] ) ) {
							$err = 'choose';
							break;
						}
						$labels[] = $allowed[ $p ];
					}
					if ( ! $err && $f['required'] && ! $labels ) {
						$err = 'required';
					}
					$out = implode( ', ', array_unique( $labels ) );
					break;

				case 'consent':
					$checked = ! empty( $raw ) && '0' !== $raw;
					if ( $f['required'] && ! $checked ) {
						$err = 'required';
					}
					$out = $checked ? __( 'Yes', 'nex-forms' ) : __( 'No', 'nex-forms' );
					break;

				case 'select':
				case 'radio':
					$v = is_string( $raw ) ? $raw : '';
					if ( '' === $v ) {
						$err = $f['required'] ? 'required' : null;
					} else {
						$allowed = array_column( $f['options'], 'label', 'value' );
						if ( ! isset( $allowed[ $v ] ) ) {
							$err = 'choose';
						} else {
							$out = $allowed[ $v ];
						}
					}
					break;

				default:
					$v = is_string( $raw ) ? $raw : '';
					$v = 'textarea' === $type ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
					if ( '' === $v ) {
						$err = $f['required'] ? 'required' : null;
						break;
					}
					$err = self::check_value( $f, $v );
					$out = $v;
			}

			if ( $err ) {
				$errors[ $name ] = self::message( $err, $f );
				continue;
			}
			$values[ $name ] = array(
				'label' => wp_strip_all_tags( $f['label'] ) ?: $name,
				'value' => $out,
				'type'  => $type,
			);
		}
		return array( $values, $errors );
	}

	private static function check_value( array $f, string $v ): ?string {
		$type = $f['type'];
		$len  = mb_strlen( $v );
		$max  = $f['maxlength'] ?? ( 'textarea' === $type ? 10000 : 500 );
		if ( $len > $max ) {
			return 'maxlength';
		}
		if ( null !== $f['minlength'] && $len < $f['minlength'] ) {
			return 'minlength';
		}
		switch ( $type ) {
			case 'email':
				if ( ! is_email( $v ) ) {
					return 'email';
				}
				break;
			case 'url':
				if ( ! wp_http_validate_url( $v ) && ! filter_var( $v, FILTER_VALIDATE_URL ) ) {
					return 'url';
				}
				break;
			case 'number':
				if ( ! is_numeric( $v ) ) {
					return 'number';
				}
				if ( null !== $f['min'] && $v + 0 < $f['min'] ) {
					return 'min';
				}
				if ( null !== $f['max'] && $v + 0 > $f['max'] ) {
					return 'max';
				}
				break;
			case 'date':
				if ( ! NexForms_Schema::valid_date( $v ) ) {
					return 'date';
				}
				if ( null !== $f['min'] && $v < $f['min'] ) {
					return 'min';
				}
				if ( null !== $f['max'] && $v > $f['max'] ) {
					return 'max';
				}
				break;
		}
		if ( '' !== $f['pattern'] && ! NexForms_Schema::pattern_match( $f['pattern'], $v ) ) {
			return 'pattern';
		}
		return null;
	}

	private static function message( string $code, array $f ): string {
		switch ( $code ) {
			case 'required':
				return __( 'This field is required.', 'nex-forms' );
			case 'email':
				return __( 'Please enter a valid email address.', 'nex-forms' );
			case 'url':
				return __( 'Please enter a valid URL.', 'nex-forms' );
			case 'phone':
				return __( 'Please enter a valid phone number.', 'nex-forms' );
			case 'number':
				return __( 'Please enter a valid number.', 'nex-forms' );
			case 'date':
				return __( 'Please enter a valid date.', 'nex-forms' );
			case 'choose':
				return __( 'Please select an option.', 'nex-forms' );
			/* translators: %s: minimum value */
			case 'min':
				return sprintf( __( 'The value must be at least %s.', 'nex-forms' ), $f['min'] );
			/* translators: %s: maximum value */
			case 'max':
				return sprintf( __( 'The value must be at most %s.', 'nex-forms' ), $f['max'] );
			/* translators: %s: number of characters */
			case 'minlength':
				return sprintf( __( 'Please enter at least %s characters.', 'nex-forms' ), $f['minlength'] );
			/* translators: %s: number of characters */
			case 'maxlength':
				return sprintf( __( 'Please enter no more than %s characters.', 'nex-forms' ), $f['maxlength'] ?? ( 'textarea' === $f['type'] ? 10000 : 500 ) );
			case 'pattern':
				return '' !== $f['pattern_message'] ? $f['pattern_message'] : __( 'Please match the requested format.', 'nex-forms' );
		}
		return __( 'Invalid value.', 'nex-forms' );
	}

	/** Build and send the notification through wp_mail (FluentSMTP or any other mailer hooks in there). */
	public static function send( array $form, array $values, array $context ): bool {
		$to = '' !== $form['settings']['email_to'] ? $form['settings']['email_to'] : get_option( 'admin_email' );
		$to = array_map( 'trim', explode( ',', $to ) );

		$subject = self::tokens( $form['settings']['email_subject'], $form, $values );
		$subject = wp_strip_all_tags( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply   = self::reply_to( $form, $values );
		if ( $reply ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		$rows = '';
		foreach ( $values as $v ) {
			if ( '' === $v['value'] ) {
				continue;
			}
			$val = esc_html( $v['value'] );
			if ( 'email' === $v['type'] ) {
				$val = '<a href="mailto:' . esc_attr( $v['value'] ) . '">' . $val . '</a>';
			} elseif ( 'phone' === $v['type'] ) {
				$val = '<a href="tel:' . esc_attr( $v['value'] ) . '">' . $val . '</a>';
			} else {
				$val = nl2br( $val );
			}
			$rows .= '<tr><th style="text-align:left;vertical-align:top;padding:10px 14px;background:#f6f7f9;border-bottom:1px solid #e6e8eb;width:34%;font-weight:600">' . esc_html( $v['label'] ) . '</th>'
				. '<td style="padding:10px 14px;border-bottom:1px solid #e6e8eb">' . $val . '</td></tr>';
		}

		$meta = esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
		if ( '' !== $context['page_url'] ) {
			$meta .= ' · <a href="' . esc_url( $context['page_url'] ) . '">' . esc_html( $context['page_url'] ) . '</a>';
		}

		$body = '<!doctype html><html><body style="margin:0;padding:24px;background:#ffffff;font:14px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1d2327">'
			. '<h2 style="margin:0 0 4px;font-size:18px">' . esc_html( $form['title'] ) . '</h2>'
			. '<p style="margin:0 0 16px;color:#646970;font-size:12px">' . $meta . '</p>'
			. '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;max-width:680px;border-collapse:collapse;border:1px solid #e6e8eb">' . $rows . '</table>'
			. '</body></html>';

		$mail = apply_filters(
			'nexforms_mail',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $body,
				'headers' => $headers,
			),
			$form,
			$values,
			$context
		);

		return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}

	private static function reply_to( array $form, array $values ): string {
		$field = $form['settings']['reply_to_field'];
		$email = '';
		if ( '' !== $field && isset( $values[ $field ] ) && 'email' === $values[ $field ]['type'] ) {
			$email = $values[ $field ]['value'];
		} else {
			foreach ( $values as $v ) {
				if ( 'email' === $v['type'] && '' !== $v['value'] ) {
					$email = $v['value'];
					break;
				}
			}
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return '';
		}
		// Use a name field if one exists; strip anything that could break the header.
		$name = '';
		foreach ( array( 'name', 'full_name', 'fullname', 'onoma' ) as $key ) {
			if ( isset( $values[ $key ] ) && '' !== $values[ $key ]['value'] ) {
				$name = $values[ $key ]['value'];
				break;
			}
		}
		$name = trim( preg_replace( '/[\r\n"<>,;]+/', ' ', $name ) );
		return '' !== $name ? sprintf( '"%s" <%s>', $name, $email ) : $email;
	}

	public static function tokens( string $text, array $form, array $values ): string {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			static function ( $m ) use ( $form, $values ) {
				switch ( $m[1] ) {
					case 'site_name':
						return get_bloginfo( 'name' );
					case 'form_title':
						return $form['title'];
				}
				return isset( $values[ $m[1] ] ) ? $values[ $m[1] ]['value'] : $m[0];
			},
			$text
		);
	}

	private static function turnstile_ok( string $token, string $ip, array $settings ): bool {
		if ( '' === $token || strlen( $token ) > 2048 ) {
			return false;
		}
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $settings['turnstile_secret'],
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) ) {
			return false;
		}
		// Cloudflare's dummy testing keys answer with hostname "example.com" and no action. Accept them only
		// outside production so local/staging sites can test the widget; production keeps the strict checks.
		$testing = ! empty( $data['metadata']['result_with_testing_key'] ) && 'production' !== wp_get_environment_type();
		if ( $testing ) {
			return true;
		}
		if ( isset( $data['action'] ) && 'nexforms' !== $data['action'] ) {
			return false;
		}
		// Hostname must belong to this site (www and bare domain both accepted).
		if ( ! empty( $data['hostname'] ) ) {
			$site = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', strtolower( (string) $data['hostname'] ) );
			$allowed = (array) apply_filters( 'nexforms_turnstile_hostnames', array( $site ) );
			if ( ! in_array( $host, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function rate_ok( string $ip, array $settings ): bool {
		$key   = 'nf_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= (int) $settings['rate_limit'] ) {
			return false;
		}
		// Keep the original window: only set the expiry on the first hit.
		if ( 0 === $count ) {
			set_transient( $key, 1, (int) $settings['rate_window'] * MINUTE_IN_SECONDS );
		} else {
			$timeout = (int) get_option( '_transient_timeout_' . $key );
			$left    = $timeout ? max( 1, $timeout - time() ) : (int) $settings['rate_window'] * MINUTE_IN_SECONDS;
			set_transient( $key, $count + 1, $left );
		}
		return true;
	}

	public static function client_ip( array $settings ): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			$remote = '0.0.0.0';
		}
		if ( ! empty( $settings['trust_cloudflare'] ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				$ranges = (array) apply_filters( 'nexforms_cloudflare_ranges', self::CF_RANGES );
				foreach ( $ranges as $range ) {
					if ( self::ip_in_range( $remote, $range ) ) {
						return $cf;
					}
				}
			}
		}
		return $remote;
	}

	public static function ip_in_range( string $ip, string $cidr ): bool {
		list( $subnet, $bits ) = array_pad( explode( '/', $cidr, 2 ), 2, null );
		$ip_bin  = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$net_bin = @inet_pton( (string) $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}
		$bits  = null === $bits ? strlen( $ip_bin ) * 8 : (int) $bits;
		$bytes = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
			return false;
		}
		$rem = $bits % 8;
		if ( 0 === $rem ) {
			return true;
		}
		$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );
		return ( $ip_bin[ $bytes ] & $mask ) === ( $net_bin[ $bytes ] & $mask );
	}

	private static function ok( array $form ): WP_REST_Response {
		$res = new WP_REST_Response(
			array(
				'ok'       => true,
				'message'  => $form['settings']['success_message'],
				'redirect' => $form['settings']['redirect_url'],
			),
			200
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	private static function fail( string $message, int $status, array $extra = array() ): WP_REST_Response {
		$res = new WP_REST_Response( array_merge( array( 'ok' => false, 'message' => $message ), $extra ), $status );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}
}
