<?php
/**
 * Front-end rendering: shortcode, assets, field markup.
 */

defined( 'ABSPATH' ) || exit;

final class NexForms_Render {

	private static int $instance = 0;
	private static bool $strings_printed = false;

	public static function register(): void {
		wp_register_style( 'nexforms', NEXFORMS_URL . 'assets/nexforms.css', array(), NEXFORMS_VERSION );
		wp_register_script( 'nexforms', NEXFORMS_URL . 'assets/nexforms.js', array(), NEXFORMS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_script(
			'nexforms-turnstile',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=nexformsTurnstileReady',
			array( 'nexforms' ),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- third-party URL must stay unversioned.
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);

		add_shortcode( 'nexform', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'nexforms', array( __CLASS__, 'shortcode' ) );

		// Load the stylesheet in <head> when the current post contains a form, to avoid a flash of unstyled fields.
		add_action(
			'wp_enqueue_scripts',
			static function () {
				$post = get_post();
				if ( is_singular() && $post && ( has_shortcode( $post->post_content, 'nexform' ) || has_shortcode( $post->post_content, 'nexforms' ) ) ) {
					wp_enqueue_style( 'nexforms' );
				}
			}
		);
	}

	public static function shortcode( $atts ): string {
		// [nexform name="contact"] or [nexform id="12"]; id also accepts a name.
		$atts = shortcode_atts( array( 'id' => '', 'name' => '' ), $atts, 'nexform' );
		$form = NexForms_Forms::find( (string) ( '' !== $atts['name'] ? $atts['name'] : $atts['id'] ) );
		if ( ! $form ) {
			return current_user_can( 'manage_options' ) ? '<p><em>' . esc_html__( 'NexForms: form not found.', 'nex-forms' ) . '</em></p>' : '';
		}
		if ( 'publish' !== $form['status'] ) {
			return current_user_can( 'manage_options' ) ? '<p><em>' . esc_html__( 'NexForms: this form is a draft and is hidden from visitors.', 'nex-forms' ) . '</em></p>' : '';
		}
		return self::form( $form );
	}

	public static function form( array $form ): string {
		self::$instance++;
		$settings  = NexForms_Settings::get();
		$turnstile = ! empty( $form['settings']['turnstile'] ) && NexForms_Settings::turnstile_ready();

		wp_enqueue_style( 'nexforms' );
		wp_enqueue_script( 'nexforms' );
		if ( $turnstile ) {
			wp_enqueue_script( 'nexforms-turnstile' );
		}
		self::print_strings();

		$uid    = 'nf' . (int) $form['id'] . '-' . self::$instance;
		$design = $form['design'];
		$style  = array();
		if ( ! empty( $design['accent'] ) ) {
			$style[] = '--nf-accent:' . $design['accent'];
		}
		if ( null !== $design['radius'] && '' !== $design['radius'] ) {
			$style[] = '--nf-radius:' . (int) $design['radius'] . 'px';
		}
		$classes = array(
			'nf',
			'nf--' . sanitize_html_class( $design['preset'] ),
			'nf--' . sanitize_html_class( $design['size'] ),
		);
		if ( ! empty( $design['button_full'] ) ) {
			$classes[] = 'nf--btn-full';
		}

		$config = array(
			'endpoint' => rest_url( 'nexforms/v1/submit/' . (int) $form['id'] ),
			'min'      => (int) $settings['min_seconds'],
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $style ? ' style="' . esc_attr( implode( ';', $style ) ) . '"' : ''; ?>>
			<form class="nf-form" id="<?php echo esc_attr( $uid ); ?>" method="post" action="<?php echo esc_url( $config['endpoint'] ); ?>" novalidate data-nf="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
				<div class="nf-grid">
					<?php
					foreach ( $form['fields'] as $field ) {
						echo self::field( $field, $uid, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
					}
					?>
				</div>
				<div class="nf-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'nex-forms' ); ?><input type="text" name="nf_hp" value="" tabindex="-1" autocomplete="off"></label>
				</div>
				<?php if ( $turnstile ) : ?>
					<div class="nf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>"></div>
				<?php endif; ?>
				<div class="nf-actions">
					<button type="submit" class="nf-submit"><span class="nf-submit-label"><?php echo esc_html( $form['settings']['submit_label'] ); ?></span></button>
				</div>
				<div class="nf-status" role="status" aria-live="polite" hidden></div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function print_strings(): void {
		if ( self::$strings_printed ) {
			return;
		}
		self::$strings_printed = true;
		$strings = array(
			'required'  => __( 'This field is required.', 'nex-forms' ),
			'email'     => __( 'Please enter a valid email address.', 'nex-forms' ),
			'url'       => __( 'Please enter a valid URL.', 'nex-forms' ),
			'phone'     => __( 'Please enter a valid phone number.', 'nex-forms' ),
			'number'    => __( 'Please enter a valid number.', 'nex-forms' ),
			'min'       => __( 'The value must be at least %s.', 'nex-forms' ),
			'max'       => __( 'The value must be at most %s.', 'nex-forms' ),
			'minlength' => __( 'Please enter at least %s characters.', 'nex-forms' ),
			'maxlength' => __( 'Please enter no more than %s characters.', 'nex-forms' ),
			'pattern'   => __( 'Please match the requested format.', 'nex-forms' ),
			'choose'    => __( 'Please select an option.', 'nex-forms' ),
			'fix'       => __( 'Please check the highlighted fields.', 'nex-forms' ),
			'sending'   => __( 'Sending…', 'nex-forms' ),
			'network'   => __( 'Could not reach the server. Please check your connection and try again.', 'nex-forms' ),
			'captcha'   => __( 'Please complete the security check.', 'nex-forms' ),
			'tooFast'   => __( 'That was quick! Please wait a moment and try again.', 'nex-forms' ),
		);
		wp_add_inline_script( 'nexforms', 'window.nexformsI18n=' . wp_json_encode( $strings ) . ';', 'before' );
	}

	private static function field( array $f, string $uid, array $settings ): string {
		$type = $f['type'];
		$name = $f['name'];
		$id   = $uid . '-' . $f['id'];

		if ( 'hidden' === $type ) {
			return sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $f['default'] ) );
		}

		$req       = ! empty( $f['required'] );
		$req_mark  = $req ? '<span class="nf-req" aria-hidden="true">*</span>' : '';
		$help_id   = $id . '-help';
		$err_id    = $id . '-err';
		$describe  = trim( ( '' !== $f['help'] ? $help_id . ' ' : '' ) . $err_id );
		$help      = '' !== $f['help'] ? '<p class="nf-help" id="' . esc_attr( $help_id ) . '">' . wp_kses( $f['help'], NexForms_Schema::inline_html() ) . '</p>' : '';
		$error     = '<p class="nf-error" id="' . esc_attr( $err_id ) . '" hidden></p>';
		$wrap_open = sprintf(
			'<div class="nf-field nf-field--%1$s nf-w-%2$s" data-name="%3$s" data-type="%1$s">',
			esc_attr( $type ),
			esc_attr( $f['width'] ),
			esc_attr( $name )
		);

		$common = sprintf(
			' id="%s" name="%s" aria-describedby="%s"%s',
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $describe ),
			$req ? ' required aria-required="true"' : ''
		);
		$ph     = '' !== $f['placeholder'] ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
		$len    = ( null !== $f['minlength'] ? ' minlength="' . (int) $f['minlength'] . '"' : '' )
			. ( null !== $f['maxlength'] ? ' maxlength="' . (int) $f['maxlength'] . '"' : '' );
		$pat    = '' !== $f['pattern'] ? ' pattern="' . esc_attr( $f['pattern'] ) . '"' . ( '' !== $f['pattern_message'] ? ' data-pattern-message="' . esc_attr( $f['pattern_message'] ) . '"' : '' ) : '';
		$ac     = '' !== $f['autocomplete'] ? ' autocomplete="' . esc_attr( $f['autocomplete'] ) . '"' : '';
		$label  = sprintf( '<label class="nf-label" for="%s">%s%s</label>', esc_attr( $id ), esc_html( $f['label'] ), $req_mark );
		$value  = '' !== $f['default'] ? ' value="' . esc_attr( $f['default'] ) . '"' : '';

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'url':
				$input_type = $type;
				if ( 'email' === $type && '' === $f['autocomplete'] ) {
					$ac = ' autocomplete="email"';
				}
				$control = sprintf( '<input class="nf-input" type="%s"%s%s%s%s%s%s>', $input_type, $common, $ph, $len, $pat, $ac, $value );
				break;

			case 'number':
				$range   = ( null !== $f['min'] ? ' min="' . esc_attr( (string) $f['min'] ) . '"' : '' ) . ( null !== $f['max'] ? ' max="' . esc_attr( (string) $f['max'] ) . '"' : '' );
				$control = sprintf( '<input class="nf-input" type="number" step="any" inputmode="decimal"%s%s%s%s>', $common, $ph, $range, $value );
				break;

			case 'date':
				$range   = ( null !== $f['min'] ? ' min="' . esc_attr( (string) $f['min'] ) . '"' : '' ) . ( null !== $f['max'] ? ' max="' . esc_attr( (string) $f['max'] ) . '"' : '' );
				$control = sprintf( '<input class="nf-input" type="date"%s%s%s>', $common, $range, $value );
				break;

			case 'textarea':
				$control = sprintf( '<textarea class="nf-input nf-textarea" rows="%d"%s%s%s>%s</textarea>', (int) $f['rows'], $common, $ph, $len, esc_textarea( $f['default'] ) );
				break;

			case 'phone':
				$control = self::phone( $f, $id, $describe, $req, $ph, $settings );
				break;

			case 'select':
				$opts = '<option value="">' . esc_html( '' !== $f['placeholder'] ? $f['placeholder'] : __( '— Select —', 'nex-forms' ) ) . '</option>';
				foreach ( $f['options'] as $o ) {
					$opts .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $o['value'] ), selected( $f['default'], $o['value'], false ), esc_html( $o['label'] ) );
				}
				$control = sprintf( '<span class="nf-select-wrap"><select class="nf-input nf-select"%s>%s</select></span>', $common, $opts );
				break;

			case 'radio':
			case 'checkboxes':
				$items = '';
				foreach ( $f['options'] as $i => $o ) {
					$oid    = $id . '-' . $i;
					$itype  = 'radio' === $type ? 'radio' : 'checkbox';
					$iname  = 'radio' === $type ? $name : $name . '[]';
					$items .= sprintf(
						'<label class="nf-choice" for="%1$s"><input type="%2$s" id="%1$s" name="%3$s" value="%4$s"%5$s%6$s><span>%7$s</span></label>',
						esc_attr( $oid ),
						$itype,
						esc_attr( $iname ),
						esc_attr( $o['value'] ),
						checked( $f['default'], $o['value'], false ),
						( $req && 'radio' === $type ) ? ' required' : '',
						esc_html( $o['label'] )
					);
				}
				return $wrap_open . sprintf(
					'<fieldset class="nf-fieldset"%s aria-describedby="%s"><legend class="nf-label">%s%s</legend><div class="nf-choices">%s</div></fieldset>%s%s</div>',
					$req ? ' data-required="1"' : '',
					esc_attr( $describe ),
					esc_html( $f['label'] ),
					$req_mark,
					$items,
					$help,
					$error
				);

			case 'consent':
				return $wrap_open . sprintf(
					'<label class="nf-choice nf-consent" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" aria-describedby="%3$s"%4$s><span>%5$s%6$s</span></label>%7$s%8$s</div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $describe ),
					$req ? ' required aria-required="true"' : '',
					wp_kses( $f['label'], NexForms_Schema::inline_html() ),
					$req_mark,
					$help,
					$error
				);

			default:
				return '';
		}

		return $wrap_open . $label . $control . $help . $error . '</div>';
	}

	private static function phone( array $f, string $id, string $describe, bool $req, string $ph, array $settings ): string {
		$default   = $f['country'];
		$preferred = array_values( array_unique( array_merge( array( $default ), (array) $settings['preferred_countries'] ) ) );
		$countries = self::countries_cached( $preferred );
		$opts      = '';
		$face      = '';
		$split     = count( array_intersect( $preferred, array_column( $countries, 'iso' ) ) );
		foreach ( $countries as $i => $c ) {
			if ( $i === $split && $split > 0 ) {
				$opts .= '<option disabled>──────────</option>';
			}
			$is_default = $c['iso'] === $default;
			if ( $is_default ) {
				$face = $c['flag'] . ' +' . $c['dial'];
			}
			$opts .= sprintf(
				'<option value="%s" data-face="%s"%s>%s %s (+%s)</option>',
				esc_attr( $c['iso'] ),
				esc_attr( $c['flag'] . ' +' . $c['dial'] ),
				$is_default ? ' selected' : '',
				esc_html( $c['flag'] ),
				esc_html( $c['name'] ),
				esc_html( $c['dial'] )
			);
		}
		return sprintf(
			'<div class="nf-phone"><span class="nf-cc"><span class="nf-cc-face" aria-hidden="true">%1$s</span><select class="nf-cc-select" name="%2$s[cc]" aria-label="%3$s">%4$s</select></span><input class="nf-input nf-phone-num" type="tel" inputmode="tel" autocomplete="tel-national" id="%5$s" name="%2$s[num]" aria-describedby="%6$s"%7$s%8$s></div>',
			esc_html( $face ),
			esc_attr( $f['name'] ),
			esc_attr__( 'Country calling code', 'nex-forms' ),
			$opts,
			esc_attr( $id ),
			esc_attr( $describe ),
			$req ? ' required aria-required="true"' : '',
			$ph
		);
	}

	/** Country list is identical for every phone field on a request; build it once. */
	private static function countries_cached( array $preferred ): array {
		static $cache = array();
		$key = implode( ',', $preferred ) . '|' . determine_locale();
		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = NexForms_Countries::ordered( $preferred );
		}
		return $cache[ $key ];
	}
}
