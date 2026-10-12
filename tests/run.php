<?php

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

function __( $message, $domain = null ) {
	return $message;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

require_once __DIR__ . '/../includes/class-login-url.php';
require_once __DIR__ . '/../includes/class-rate-limiter.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

check( '' === \SazaLogin\Login_Url::validate_slug( 'my-secure-login' ), 'accepts a valid login slug' );
check( '' !== \SazaLogin\Login_Url::validate_slug( 'wp-admin' ), 'rejects a reserved login slug' );
check( '' !== \SazaLogin\Login_Url::validate_slug( '../login' ), 'rejects path traversal and multi-segment slugs' );

$default_ip = \SazaLogin\Rate_Limiter::get_client_ip(
	array(
		'REMOTE_ADDR' => '203.0.113.8',
		'HTTP_X_FORWARDED_FOR' => '198.51.100.44',
	),
	array( 'proxy_header' => 'none', 'trusted_proxies' => array() )
);
check( '203.0.113.8' === $default_ip, 'ignores untrusted forwarding headers' );

$cloudflare_ip = \SazaLogin\Rate_Limiter::get_client_ip(
	array(
		'REMOTE_ADDR' => '192.0.2.12',
		'HTTP_CF_CONNECTING_IP' => '198.51.100.22',
	),
	array( 'proxy_header' => 'cf-connecting-ip', 'trusted_proxies' => array( '192.0.2.12' ) )
);
check( '198.51.100.22' === $cloudflare_ip, 'uses a Cloudflare address only behind a trusted proxy' );

$untrusted_cloudflare_header = \SazaLogin\Rate_Limiter::get_client_ip(
	array(
		'REMOTE_ADDR' => '203.0.113.8',
		'HTTP_CF_CONNECTING_IP' => '198.51.100.22',
	),
	array( 'proxy_header' => 'cf-connecting-ip', 'trusted_proxies' => array( '192.0.2.12' ) )
);
check( '203.0.113.8' === $untrusted_cloudflare_header, 'ignores proxy headers from an untrusted connection' );

$xff_ip = \SazaLogin\Rate_Limiter::get_client_ip(
	array(
		'REMOTE_ADDR' => '10.0.0.2',
		'HTTP_X_FORWARDED_FOR' => '198.51.100.9, 10.0.0.1',
	),
	array( 'proxy_header' => 'x-forwarded-for', 'trusted_proxies' => array( '10.0.0.0/8' ) )
);
check( '198.51.100.9' === $xff_ip, 'walks X-Forwarded-For from the trusted end' );

check( \SazaLogin\Rate_Limiter::is_valid_proxy_cidr( '2001:db8::/32' ), 'accepts a valid IPv6 CIDR' );
check( ! \SazaLogin\Rate_Limiter::is_valid_proxy_cidr( '2001:db8::/129' ), 'rejects an out-of-range IPv6 prefix' );
check( ! \SazaLogin\Rate_Limiter::is_valid_proxy_cidr( '0.0.0.0/0' ), 'rejects a proxy range that trusts every IPv4 address' );
check( \SazaLogin\Rate_Limiter::ip_in_cidr( '2001:db8::25', '2001:db8::/32' ), 'matches an IPv6 address against a CIDR range' );

$now = 1800000000;
$first_lock = \SazaLogin\Rate_Limiter::next_state(
	array( 'attempts' => 4, 'window_started' => $now - 300, 'blocked_until' => 0, 'penalty_level' => 0, 'updated_at' => $now - 300 ),
	5,
	900,
	900,
	$now
);
check( $now + 900 === $first_lock['blocked_until'] && 1 === $first_lock['penalty_level'], 'applies the initial lock at the configured threshold' );

$repeat_lock = \SazaLogin\Rate_Limiter::next_state(
	array( 'attempts' => 4, 'window_started' => $now - 300, 'blocked_until' => 0, 'penalty_level' => 1, 'updated_at' => $now - 300 ),
	5,
	900,
	900,
	$now
);
check( $now + 1800 === $repeat_lock['blocked_until'] && 2 === $repeat_lock['penalty_level'], 'extends a repeated lockout progressively' );

$expired_window = \SazaLogin\Rate_Limiter::next_state(
	array( 'attempts' => 5, 'window_started' => $now - 1200, 'blocked_until' => 0, 'penalty_level' => 0, 'updated_at' => $now - 1200 ),
	5,
	900,
	900,
	$now
);
check( 1 === $expired_window['attempts'] && 0 === $expired_window['blocked_until'], 'starts a fresh count after the window expires' );

$in_flight = \SazaLogin\Rate_Limiter::next_state(
	array( 'attempts' => 5, 'window_started' => $now - 300, 'blocked_until' => $now + 600, 'penalty_level' => 1, 'updated_at' => $now - 100 ),
	5,
	900,
	900,
	$now
);
check( $now + 600 === $in_flight['blocked_until'], 'does not extend an active lock from an in-flight failure' );

echo "All helper checks passed.\n";

