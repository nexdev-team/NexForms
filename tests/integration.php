<?php
/**
 * Integration tests against a real WordPress install.
 *
 * Usage: php tests/integration.php /path/to/wordpress
 * Copy tests/mu-test-helpers.php into wp-content/mu-plugins first: it captures wp_mail
 * into wp-content/mail.log and fakes Turnstile siteverify (tokens: good, badhost, badaction; anything else fails).
 */

$nf_wp_path = $argv[1] ?? '';
if ( ! is_file( $nf_wp_path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/integration.php /path/to/wordpress\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST']   = 'localhost:8080';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
require $nf_wp_path . '/wp-load.php';

$pass = 0;
$fail = 0;
function ok( $cond, $name ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ✓ $name\n";
	} else {
		$fail++;
		echo "  ✗ $name\n";
	}
}
function req( $method, $route, $body = null, $form = false ) {
	$r = new WP_REST_Request( $method, $route );
	if ( null !== $body ) {
		if ( $form ) {
			$r->set_body_params( $body );
		} else {
			$r->set_header( 'content-type', 'application/json' );
			$r->set_body( wp_json_encode( $body ) );
		}
	}
	$res = rest_do_request( $r );
	return array( $res->get_status(), rest_get_server()->response_to_data( $res, false ) );
}
function mails() {
	$f = WP_CONTENT_DIR . '/mail.log';
	return is_file( $f ) ? array_map( fn( $l ) => json_decode( $l, true ), array_filter( explode( "\n", file_get_contents( $f ) ) ) ) : array();
}
function reset_mail() {
	@unlink( WP_CONTENT_DIR . '/mail.log' );
}
function clear_rate() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%nf\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_nf\\_rl\\_%'" );
	wp_cache_flush();
}

echo "Permissions\n";
wp_set_current_user( 0 );
list( $s ) = req( 'GET', '/nexforms/v1/forms' );
ok( 401 === $s, 'anonymous cannot list forms' );
list( $s ) = req( 'GET', '/nexforms/v1/settings' );
ok( 401 === $s, 'anonymous cannot read settings' );
$editor = wp_insert_user( array( 'user_login' => 'ed' . wp_rand(), 'user_pass' => 'x', 'role' => 'editor' ) );
wp_set_current_user( $editor );
list( $s ) = req( 'POST', '/nexforms/v1/forms', array() );
ok( 403 === $s, 'editor cannot create forms' );

wp_set_current_user( 1 );

echo "Form CRUD + sanitisation\n";
list( $s, $f ) = req( 'POST', '/nexforms/v1/forms', array() );
ok( 200 === $s && $f['id'] > 0, 'create default form' );
ok( 4 === count( $f['fields'] ) && 'phone' === $f['fields'][2]['type'], 'default form has name/email/phone/message' );
$id = $f['id'];

$payload = array(
	'title'    => 'Επικοινωνία <b>x</b>',
	'status'   => 'publish',
	'fields'   => array(
		array( 'id' => 'a1', 'type' => 'text', 'label' => 'Όνομα', 'name' => 'name', 'required' => true, 'minlength' => 2 ),
		array( 'id' => 'a2', 'type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true ),
		array( 'id' => 'a3', 'type' => 'phone', 'label' => 'Τηλέφωνο', 'name' => 'phone', 'required' => true, 'country' => 'GR' ),
		array( 'id' => 'a4', 'type' => 'select', 'label' => 'Θέμα', 'name' => 'topic', 'required' => true, 'options' => array( array( 'label' => 'Μάσκες', 'value' => 'masks' ), array( 'label' => 'Συσκευές', 'value' => 'devices' ), array( 'label' => 'Dup', 'value' => 'masks' ) ) ),
		array( 'id' => 'a5', 'type' => 'checkboxes', 'label' => 'Ενδιαφέρον', 'name' => 'interest', 'options' => array( array( 'label' => 'A', 'value' => 'a' ), array( 'label' => 'B', 'value' => 'b' ) ) ),
		array( 'id' => 'a6', 'type' => 'textarea', 'label' => 'Μήνυμα', 'name' => 'message', 'required' => true, 'maxlength' => 50 ),
		array( 'id' => 'a7', 'type' => 'consent', 'label' => 'Συμφωνώ με την <a href="/privacy" onclick="x()">πολιτική</a><script>bad()</script>', 'name' => 'gdpr', 'required' => true ),
		array( 'id' => 'a8', 'type' => 'hidden', 'label' => 'Source', 'name' => 'source', 'default' => 'landing-a', 'required' => true ),
		array( 'id' => 'a9', 'type' => 'text', 'label' => 'ΑΦΜ', 'name' => 'vat', 'pattern' => '[0-9]{9}', 'pattern_message' => 'Το ΑΦΜ έχει 9 ψηφία' ),
		array( 'id' => 'b1', 'type' => 'number', 'label' => 'Qty', 'name' => 'qty', 'min' => 1, 'max' => 10 ),
		array( 'id' => 'b2', 'type' => 'text', 'label' => 'Bad pattern', 'name' => 'name', 'pattern' => '([a-z' ),
		array( 'id' => 'b3', 'type' => 'evil', 'label' => 'nope' ),
	),
	'settings' => array( 'email_to' => 'a@example.com, bad-address, b@example.com', 'email_subject' => 'Νέο μήνυμα: {topic} από {name}', 'turnstile' => false, 'success_message' => 'Ευχαριστούμε! <script>x</script>' ),
	'design'   => array( 'preset' => 'glossy', 'accent' => '#ff0000', 'radius' => 99, 'size' => 'xl' ),
);
list( $s, $f ) = req( 'PUT', '/nexforms/v1/forms/' . $id, $payload );
ok( 200 === $s, 'update form' );
ok( 'Επικοινωνία x' === $f['title'], 'title stripped of tags, Greek kept' );
ok( 11 === count( $f['fields'] ), 'unknown field type dropped' );
$byid = array_column( $f['fields'], null, 'id' );
ok( 2 === count( $byid['a4']['options'] ), 'duplicate option values removed' );
ok( ! str_contains( $byid['a7']['label'], '<script' ) && ! str_contains( $byid['a7']['label'], 'onclick' ) && str_contains( $byid['a7']['label'], '<a href="/privacy">' ), 'consent label keeps link, strips script/handlers' );
ok( 'name_2' === $byid['b2']['name'], 'duplicate field key made unique' );
ok( '' === $byid['b2']['pattern'], 'invalid regex dropped' );
ok( 'a@example.com, b@example.com' === $f['settings']['email_to'], 'invalid recipients dropped' );
ok( ! str_contains( $f['settings']['success_message'], '<script' ), 'success message kses' );
ok( 40 === $f['design']['radius'] && 'md' === $f['design']['size'] && 'glossy' === $f['design']['preset'], 'design clamped/validated' );
ok( false === $byid['a8']['required'], 'hidden fields cannot be required' );

list( $s, $list ) = req( 'GET', '/nexforms/v1/forms' );
ok( 200 === $s && in_array( $id, array_column( $list, 'id' ), true ), 'list includes form' );
list( $s, $dup ) = req( 'POST', '/nexforms/v1/forms/' . $id . '/duplicate' );
ok( 200 === $s && 'draft' === $dup['status'] && str_contains( $dup['title'], '(copy)' ), 'duplicate creates draft copy' );
list( $s ) = req( 'DELETE', '/nexforms/v1/forms/' . $dup['id'] );
ok( 200 === $s && null === NexForms_Forms::get( $dup['id'] ), 'delete form' );
list( $s ) = req( 'GET', '/nexforms/v1/forms/999999' );
ok( 404 === $s, '404 for missing form' );

echo "Form names (shortcode)\n";
wp_set_current_user( 1 );
list( , $a ) = req( 'POST', '/nexforms/v1/forms', array( 'title' => 'Контакт форма' ) );
ok( 'kontakt-forma' === $a['slug'], 'name made from Cyrillic title: ' . $a['slug'] );
list( , $b ) = req( 'POST', '/nexforms/v1/forms', array( 'title' => 'Φόρμα επικοινωνίας', 'slug' => 'Kontakt Forma' ) );
ok( 'kontakt-forma-2' === $b['slug'], 'typed name made unique: ' . $b['slug'] );
list( , $c ) = req( 'POST', '/nexforms/v1/forms', array( 'title' => '!!!', 'status' => 'draft' ) );
ok( 'form-' . $c['id'] === $c['slug'], 'unusable title falls back to form-<id> (draft too)' );
list( , $a2 ) = req( 'PUT', '/nexforms/v1/forms/' . $a['id'], array_merge( $a, array( 'slug' => 'contact-bg' ) ) );
ok( 'contact-bg' === $a2['slug'], 'name can be changed' );
wp_set_current_user( 0 );
ok( str_contains( do_shortcode( '[nexform name="contact-bg"]' ), 'nf' . $a['id'] . '-' ), '[nexform name="…"] renders the form' );
ok( str_contains( do_shortcode( '[nexform id="contact-bg"]' ), 'nf' . $a['id'] . '-' ), 'id="name" also works' );
ok( str_contains( do_shortcode( '[nexform id="' . $a['id'] . '"]' ), 'nf' . $a['id'] . '-' ), 'numeric id still works' );
ok( '' === do_shortcode( '[nexform name="no-such-form"]' ), 'unknown name renders nothing' );
wp_set_current_user( 1 );
list( , $l ) = req( 'GET', '/nexforms/v1/forms' );
ok( in_array( 'contact-bg', array_column( $l, 'slug' ), true ), 'list returns names' );
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_name' => '%ce%b5%cf%80' ), array( 'ID' => $b['id'] ) ); // a pre-0.1.3 Greek post_name
clean_post_cache( $b['id'] );
delete_option( 'nexforms_version' );
NexForms_Forms::maybe_upgrade();
ok( 'forma-epikoinonias' === NexForms_Forms::get( $b['id'] )['slug'], 'upgrade names existing forms: ' . NexForms_Forms::get( $b['id'] )['slug'] );
foreach ( array( $a['id'], $b['id'], $c['id'] ) as $x ) { NexForms_Forms::delete( $x ); }
wp_dequeue_script( 'nexforms' );
wp_dequeue_script( 'nexforms-turnstile' ); // Keep the later asset checks independent of these renders.
ok( 'GR' === NexForms_Schema::default_country(), 'phone default country on this en_US test site' );
$bg = fn() => 'bg_BG';
add_filter( 'locale', $bg );
ok( 'BG' === NexForms_Schema::field( 'phone' )['country'], 'bg_BG site → phone defaults to BG' );
remove_filter( 'locale', $bg );

echo "Settings\n";
ok( NexForms_Countries::EUROPE === NexForms_Settings::defaults()['preferred_countries'], 'default phone list = all of Europe' );
$ordered = array_column( NexForms_Countries::ordered( NexForms_Countries::EUROPE ), 'iso' );
ok( array_slice( $ordered, 0, 54 ) === NexForms_Countries::EUROPE && 'GR' === $ordered[0] && ! in_array( 'US', array_slice( $ordered, 0, 54 ), true ), 'European countries listed first, Greece on top' );
list( $s, $st ) = req( 'POST', '/nexforms/v1/settings', array( 'turnstile_site_key' => '0x4AAA-site', 'turnstile_secret' => '0x4AAA-secret', 'rate_limit' => 100, 'min_seconds' => 3, 'preferred_countries' => 'gr, cy, xx' ) );
ok( 200 === $s && ! isset( $st['turnstile_secret'] ) && true === $st['turnstile_secret_set'], 'secret saved but never returned' );
ok( array( 'GR', 'CY' ) === $st['preferred_countries'], 'unknown country codes dropped' );
list( , $st ) = req( 'POST', '/nexforms/v1/settings', array( 'turnstile_secret' => '' ) );
ok( true === $st['turnstile_secret_set'], 'empty secret keeps stored one' );
ok( NexForms_Settings::turnstile_ready(), 'turnstile ready' );

echo "Rendering\n";
$html = do_shortcode( '[nexform id="' . $id . '"]' );
ok( str_contains( $html, 'class="nf nf--glossy nf--md"' ) && str_contains( $html, '--nf-accent:#ff0000' ), 'preset + accent in markup' );
ok( str_contains( $html, 'name="phone[cc]"' ) && str_contains( $html, '🇬🇷 +30' ), 'phone picker defaults to Greece' );
ok( str_contains( $html, 'name="nf_hp"' ), 'honeypot present' );
ok( ! str_contains( $html, 'nf-turnstile' ), 'no turnstile when the form has it off' );
ok( str_contains( $html, 'pattern="[0-9]{9}"' ) && str_contains( $html, 'data-pattern-message="Το ΑΦΜ έχει 9 ψηφία"' ), 'pattern + message rendered' );
ok( str_contains( $html, 'type="hidden" name="source" value="landing-a"' ), 'hidden field rendered' );
ok( ! str_contains( $html, '<script>bad' ), 'no injected script' );
ok( wp_script_is( 'nexforms', 'enqueued' ) && ! wp_script_is( 'nexforms-turnstile', 'enqueued' ), 'assets enqueued only as needed' );
wp_set_current_user( 0 );
ok( '' === do_shortcode( '[nexform id="999999"]' ), 'missing form renders nothing for visitors' );
wp_set_current_user( 1 );

echo "Submission validation\n";
wp_set_current_user( 0 );
clear_rate();
reset_mail();
$good = array(
	'name'     => 'Μαρία',
	'email'    => 'maria@example.gr',
	'phone'    => array( 'cc' => 'GR', 'num' => '691 234 5678' ),
	'topic'    => 'masks',
	'interest' => array( 'a', 'b' ),
	'message'  => "Γεια σας,\nθέλω πληροφορίες.",
	'gdpr'     => '1',
	'source'   => 'tampered',
	'vat'      => '123456789',
	'qty'      => '3',
	'nf_t'     => '5000',
	'nf_page'  => 'http://localhost:8080/contact/',
);
list( $s, $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, $good, true );
ok( 200 === $s && true === $r['ok'], 'valid submission accepted' );
$m = mails();
ok( 1 === count( $m ), 'one email sent' );
ok( array( 'a@example.com', 'b@example.com' ) === $m[0]['to'], 'sent to configured recipients' );
ok( 'Νέο μήνυμα: Μάσκες από Μαρία' === $m[0]['subject'], 'subject tokens use labels/values' );
ok( in_array( 'Reply-To: Μαρία <maria@example.gr>', $m[0]['headers'], true ), 'Reply-To set to visitor (no quotes in name)' );
ok( ! preg_grep( '/^From:/i', $m[0]['headers'] ), 'From header left to FluentSMTP' );
ok( str_contains( $m[0]['message'], '+306912345678' ), 'phone normalised to E.164' );
ok( str_contains( $m[0]['message'], 'landing-a' ) && ! str_contains( $m[0]['message'], 'tampered' ), 'hidden value from schema, not request' );
ok( str_contains( $m[0]['message'], 'A, B' ), 'checkbox labels in email' );
ok( str_contains( $m[0]['message'], 'θέλω πληροφορίες' ) && str_contains( $m[0]['message'], '<br />' ), 'message with line breaks' );
global $wpdb;
ok( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%maria@example.gr%'" ), 'submission not stored in posts' );

$cases = array(
	'missing required'    => array( array( 'name' => '' ), 'name' ),
	'too short'           => array( array( 'name' => 'M' ), 'name' ),
	'bad email'           => array( array( 'email' => 'nope@' ), 'email' ),
	'bad phone'           => array( array( 'phone' => array( 'cc' => 'GR', 'num' => '12ab' ) ), 'phone' ),
	'short phone'         => array( array( 'phone' => array( 'cc' => 'GR', 'num' => '12' ) ), 'phone' ),
	'option not allowed'  => array( array( 'topic' => 'hack' ), 'topic' ),
	'checkbox not allowed'=> array( array( 'interest' => array( 'a', 'zzz' ) ), 'interest' ),
	'too long'            => array( array( 'message' => str_repeat( 'α', 51 ) ), 'message' ),
	'consent missing'     => array( array( 'gdpr' => '' ), 'gdpr' ),
	'pattern'             => array( array( 'vat' => '12345' ), 'vat' ),
	'number range'        => array( array( 'qty' => '11' ), 'qty' ),
	'not a number'        => array( array( 'qty' => 'abc' ), 'qty' ),
);
foreach ( $cases as $label => $c ) {
	list( $s, $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, $c[0] ), true );
	ok( 422 === $s && isset( $r['errors'][ $c[1] ] ), "rejects: $label" );
}
list( , $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'vat' => '1' ) ), true );
ok( 'Το ΑΦΜ έχει 9 ψηφία' === $r['errors']['vat'], 'custom pattern message returned' );
ok( 1 === count( mails() ), 'no email for invalid submissions' );

echo "Phone normalisation\n";
ok( '+306912345678' === NexForms_Countries::to_e164( 'GR', '6912345678' ), 'GR mobile' );
ok( '+447911123456' === NexForms_Countries::to_e164( 'GB', '07911 123456' ), 'GB trunk 0 removed' );
ok( '+390612345678' === NexForms_Countries::to_e164( 'IT', '06 1234 5678' ), 'IT keeps leading 0' );
ok( '+35799123456' === NexForms_Countries::to_e164( 'GR', '+357 99 123456' ), 'typed international overrides picker' );
ok( '+35799123456' === NexForms_Countries::to_e164( 'GR', '00357 99 123456' ), '00 prefix treated as international' );
ok( null === NexForms_Countries::to_e164( 'GR', '+0123456789' ), 'invalid international rejected' );

echo "Anti-spam\n";
reset_mail();
list( $s, $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'nf_hp' => 'http://spam' ) ), true );
ok( 200 === $s && true === $r['ok'] && 0 === count( mails() ), 'honeypot: fake success, no email' );
list( $s, $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'nf_t' => '800' ) ), true );
ok( 429 === $s && 'too_fast' === $r['code'], 'too fast rejected' );
$noT = $good;
unset( $noT['nf_t'] );
list( $s ) = req( 'POST', '/nexforms/v1/submit/' . $id, $noT, true );
ok( 429 === $s, 'missing timer (no JS) rejected' );

// Turnstile on.
$form                          = NexForms_Forms::get( $id );
$form['settings']['turnstile'] = true;
wp_set_current_user( 1 );
NexForms_Forms::save( NexForms_Schema::sanitize_form( $form ), $id );
wp_set_current_user( 0 );
$html = do_shortcode( '[nexform id="' . $id . '"]' );
ok( str_contains( $html, 'class="nf-turnstile" data-sitekey="0x4AAA-site"' ), 'turnstile widget rendered' );
ok( wp_script_is( 'nexforms-turnstile', 'enqueued' ), 'turnstile api enqueued' );
// This test install is 'production': Cloudflare testing-key answers must be rejected there.
foreach ( array( '' => 400, 'wrong' => 400, 'badhost' => 400, 'badaction' => 400, 'testkey' => 400, 'good' => 200 ) as $tok => $want ) {
	list( $s ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'cf-turnstile-response' => $tok ) ), true );
	ok( $want === $s, "turnstile token '" . ( $tok ?: 'empty' ) . "' → $want" );
}

echo "Mail failure\n";
update_option( 'nf_test_mail_fail', 1 );
list( $s, $r ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'cf-turnstile-response' => 'good' ) ), true );
ok( 500 === $s && 'mail' === $r['code'], 'mail failure reported to visitor' );
delete_option( 'nf_test_mail_fail' );

echo "Rate limit\n";
clear_rate();
update_option( 'nexforms_settings', array_merge( NexForms_Settings::get(), array( 'rate_limit' => 3 ) ) );
$codes = array();
for ( $i = 0; $i < 4; $i++ ) {
	list( $s ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'cf-turnstile-response' => 'good' ) ), true );
	$codes[] = $s;
}
ok( array( 200, 200, 200, 429 ) === $codes, '4th submission within window blocked' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
list( $s ) = req( 'POST', '/nexforms/v1/submit/' . $id, array_merge( $good, array( 'cf-turnstile-response' => 'good' ) ), true );
ok( 200 === $s, 'other IP not affected' );

echo "Client IP / Cloudflare\n";
$_SERVER['REMOTE_ADDR']           = '172.68.10.5';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.7';
ok( '198.51.100.7' === NexForms_Submit::client_ip( array( 'trust_cloudflare' => true ) ), 'CF header trusted from Cloudflare edge' );
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
ok( '8.8.8.8' === NexForms_Submit::client_ip( array( 'trust_cloudflare' => true ) ), 'CF header ignored from non-Cloudflare IP' );
$_SERVER['REMOTE_ADDR'] = '2606:4700:10::6816:1';
ok( '198.51.100.7' === NexForms_Submit::client_ip( array( 'trust_cloudflare' => true ) ), 'IPv6 Cloudflare range' );
ok( '2606:4700:10::6816:1' === NexForms_Submit::client_ip( array( 'trust_cloudflare' => false ) ), 'trust off ignores header' );
unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );

echo "Draft forms\n";
$form           = NexForms_Forms::get( $id );
$form['status'] = 'draft';
wp_set_current_user( 1 );
NexForms_Forms::save( NexForms_Schema::sanitize_form( $form ), $id );
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
list( $s ) = req( 'POST', '/nexforms/v1/submit/' . $id, $good, true );
ok( 404 === $s, 'draft form rejects submissions' );
ok( '' === do_shortcode( '[nexform id="' . $id . '"]' ), 'draft form hidden from visitors' );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
