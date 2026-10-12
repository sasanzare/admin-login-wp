<?php
/**
 * Database-backed rate limiting for WordPress authentication.
 *
 * @package SazaLogin
 */

namespace SazaLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rate_Limiter {
	const CLEANUP_HOOK = 'saza_login_daily_cleanup';

	private static $auth_context = null;
	private static $blocked_auth_error = null;
	private static $application_password_context = null;
	private static $blocked_application_password_error = null;
	private static $application_password_failure_counted = false;
	private static $table_available = null;

	public function register() {
		add_filter( 'authenticate', array( $this, 'pre_authenticate' ), 1, 3 );
		add_filter( 'authenticate', array( $this, 'post_authenticate' ), 1000, 3 );
		add_filter( 'determine_current_user', array( $this, 'precheck_application_password' ), 19 );
		add_filter( 'application_password_is_api_request', array( $this, 'filter_application_password_api' ), 100 );
		add_action( 'application_password_failed_authentication', array( $this, 'application_password_failed' ), 10, 1 );
		add_action( 'application_password_did_authenticate', array( $this, 'application_password_succeeded' ), 10, 2 );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ), 100 );
		add_filter( 'xmlrpc_enabled', array( $this, 'xmlrpc_enabled' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_expired' ) );
	}

	public static function table_name() {
		global $wpdb;
		$prefix = isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : $wpdb->prefix;
		return $prefix . 'saza_login_attempts';
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			site_id bigint(20) unsigned NOT NULL,
			bucket_hash char(64) NOT NULL,
			attempts int(10) unsigned NOT NULL DEFAULT 0,
			window_started int(10) unsigned NOT NULL DEFAULT 0,
			blocked_until int(10) unsigned NOT NULL DEFAULT 0,
			penalty_level tinyint(3) unsigned NOT NULL DEFAULT 0,
			updated_at int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (site_id,bucket_hash),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $sql );
		$ready = self::table_exists();
		update_option( 'saza_login_db_ready', $ready ? 1 : 0, false );
		update_option( 'saza_login_db_version', '1', false );
		return $ready;
	}

	public static function table_exists() {
		global $wpdb;
		$table = self::table_name();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $table === $found;
	}

	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function clear_site_limits() {
		global $wpdb;
		$table = self::table_name();
		if ( self::table_exists() ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE site_id = %d", get_current_blog_id() ) );
		}
	}

	public static function clear_temporary_limits() {
		global $wpdb;
		$table = self::table_name();
		if ( ! self::table_exists() ) {
			return;
		}

		$network_plugins = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network_active  = is_array( $network_plugins ) && isset( $network_plugins[ plugin_basename( SAZA_LOGIN_FILE ) ] );
		if ( $network_active ) {
			$wpdb->query( "DELETE FROM {$table}" );
			return;
		}

		self::clear_site_limits();
	}

	public static function cleanup_expired() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}

		$table  = self::table_name();
		$cutoff = time() - ( 2 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE updated_at < %d LIMIT 10000", $cutoff ) );
	}

	public static function delete_site_data() {
		global $wpdb;
		self::clear_site_limits();
		if ( ! is_multisite() ) {
			$table = self::table_name();
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
		delete_option( 'saza_login_db_ready' );
		delete_option( 'saza_login_db_version' );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public function pre_authenticate( $user, $username, $password ) {
		if ( ! Settings::get( 'brute_force_enabled' ) || $user instanceof \WP_User || ! is_string( $username ) || ! is_string( $password ) || '' === $username || '' === $password || self::$auth_context ) {
			return $user;
		}

		if ( ! self::table_exists() ) {
			return $user;
		}

		$context = $this->make_context( $username );
		if ( ! $context ) {
			return $user;
		}

		$wait = $this->blocked_seconds( $context['buckets'] );
		if ( $wait > 0 ) {
			self::$blocked_auth_error = $this->rate_limit_error( $wait );
			$this->send_retry_headers( $wait );
			return self::$blocked_auth_error;
		}

		self::$auth_context = $context;
		return $user;
	}

	public function post_authenticate( $user, $username, $password ) {
		if ( self::$blocked_auth_error instanceof \WP_Error ) {
			$error                       = self::$blocked_auth_error;
			self::$blocked_auth_error = null;
			return $error;
		}

		if ( ! self::$auth_context ) {
			return $user;
		}

		$context = self::$auth_context;
		self::$auth_context = null;

		if ( $user instanceof \WP_User ) {
			$this->delete_bucket( $context['site_id'], $context['buckets']['pair']['hash'] );
			return $user;
		}

		if ( is_wp_error( $user ) || null === $user || false === $user ) {
			if ( ! is_wp_error( $user ) || ! in_array( 'saza_login_rate_limited', $user->get_error_codes(), true ) ) {
				$this->record_failure( $context );
			}

			if ( $context['interactive'] && is_wp_error( $user ) && $this->is_core_credential_error( $user ) ) {
				return new \WP_Error( 'saza_login_invalid_credentials', __( 'Invalid username or password. Please try again.', 'saza-login' ) );
			}
		}

		return $user;
	}

	public function precheck_application_password( $user_id ) {
		if ( $user_id || empty( $_SERVER['PHP_AUTH_USER'] ) || empty( $_SERVER['PHP_AUTH_PW'] ) || ! is_string( $_SERVER['PHP_AUTH_USER'] ) || ! is_string( $_SERVER['PHP_AUTH_PW'] ) ) {
			return $user_id;
		}

		if ( ! Settings::get( 'brute_force_enabled' ) ) {
			return $user_id;
		}

		if ( ! self::table_exists() ) {
			return $user_id;
		}

		$context = $this->make_context( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
		if ( ! $context ) {
			return $user_id;
		}

		self::$application_password_context = $context;
		$wait = $this->blocked_seconds( $context['buckets'] );
		if ( $wait > 0 ) {
			self::$blocked_application_password_error = $this->rate_limit_error( $wait );
			$this->send_retry_headers( $wait );
		}

		return $user_id;
	}

	public function filter_application_password_api( $is_api_request ) {
		if ( self::$blocked_application_password_error instanceof \WP_Error ) {
			return false;
		}

		return $is_api_request;
	}

	public function application_password_failed( $error ) {
		if ( self::$auth_context ) {
			self::$application_password_context = null;
			return;
		}

		if ( self::$application_password_failure_counted || ! Settings::get( 'brute_force_enabled' ) || ! self::table_exists() ) {
			return;
		}

		$username = isset( $_SERVER['PHP_AUTH_USER'] ) && is_string( $_SERVER['PHP_AUTH_USER'] ) ? wp_unslash( $_SERVER['PHP_AUTH_USER'] ) : '';
		if ( '' === $username ) {
			return;
		}

		$context = self::$application_password_context ? self::$application_password_context : $this->make_context( $username );
		if ( $context ) {
			$this->record_failure( $context );
			self::$application_password_failure_counted = true;
		}
		self::$application_password_context = null;
	}

	public function application_password_succeeded( $user, $item ) {
		if ( self::$auth_context ) {
			self::$application_password_context = null;
			return;
		}
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$context = self::$application_password_context ? self::$application_password_context : $this->make_context( $user->user_login );
		if ( $context ) {
			$this->delete_bucket( $context['site_id'], $context['buckets']['pair']['hash'] );
		}
		self::$application_password_context = null;
	}

	public function rest_authentication_errors( $result ) {
		if ( self::$blocked_application_password_error instanceof \WP_Error ) {
			return self::$blocked_application_password_error;
		}

		return $result;
	}

	public function xmlrpc_enabled( $enabled ) {
		return Settings::get( 'xmlrpc_auth_disabled' ) ? false : $enabled;
	}

	private function make_context( $username ) {
		$ip = self::get_client_ip();
		if ( '' === $ip ) {
			return null;
		}

		$secret = wp_salt( 'auth' );
		$login  = trim( $username );
		if ( function_exists( 'mb_strtolower' ) ) {
			$login = mb_strtolower( $login, 'UTF-8' );
		} else {
			$login = strtolower( $login );
		}

		$ip_hash   = hash_hmac( 'sha256', 'ip|' . $ip, $secret );
		$pair_hash = hash_hmac( 'sha256', 'pair|' . $ip . '|' . $login, $secret );
		$settings  = Settings::get();
		return array(
			'site_id' => get_current_blog_id(),
			'interactive' => $this->is_interactive_login_request(),
			'buckets' => array(
				'pair' => array(
					'hash' => $pair_hash,
					'limit' => (int) $settings['max_attempts'],
					'window' => (int) $settings['window_minutes'] * MINUTE_IN_SECONDS,
					'lock' => (int) $settings['lockout_minutes'] * MINUTE_IN_SECONDS,
				),
				'ip' => array(
					'hash' => $ip_hash,
					'limit' => (int) $settings['ip_max_attempts'],
					'window' => (int) $settings['window_minutes'] * MINUTE_IN_SECONDS,
					'lock' => (int) $settings['lockout_minutes'] * MINUTE_IN_SECONDS,
				),
			),
		);
	}

	private function is_interactive_login_request() {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( '' !== $action && 'login' !== $action ) {
			return false;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		return Login_Url::is_enabled() && is_string( $path ) && basename( untrailingslashit( $path ) ) === (string) Settings::get( 'login_slug' )
			|| is_string( $path ) && 'wp-login.php' === basename( untrailingslashit( $path ) );
	}

	private function is_core_credential_error( $error ) {
		$core_codes = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo' );
		return (bool) array_intersect( $core_codes, $error->get_error_codes() );
	}

	private function blocked_seconds( $buckets ) {
		global $wpdb;
		$table = self::table_name();
		$site  = get_current_blog_id();
		$hash1 = $buckets['pair']['hash'];
		$hash2 = $buckets['ip']['hash'];
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT bucket_hash, blocked_until FROM {$table} WHERE site_id = %d AND bucket_hash IN (%s, %s)", $site, $hash1, $hash2 ),
			ARRAY_A
		);

		$now  = time();
		$wait = 0;
		foreach ( (array) $rows as $row ) {
			$until = isset( $row['blocked_until'] ) ? (int) $row['blocked_until'] : 0;
			if ( $until > $now ) {
				$wait = max( $wait, $until - $now );
			}
		}

		return $wait;
	}

	private function record_failure( $context ) {
		foreach ( $context['buckets'] as $bucket ) {
			$this->increment_bucket( $context['site_id'], $bucket['hash'], $bucket['limit'], $bucket['window'], $bucket['lock'] );
		}
	}

	private function increment_bucket( $site_id, $hash, $limit, $window, $lock ) {
		global $wpdb;
		$table        = self::table_name();
		$now          = time();
		$decay_before = $now - DAY_IN_SECONDS;
		$window_ends  = "(window_started + {$window} <= {$now})";
		$trigger      = "(blocked_until <= {$now} AND (({$window_ends} AND {$limit} <= 1) OR (NOT {$window_ends} AND attempts + 1 >= {$limit})))";
		$duration     = "LEAST({$lock} * POW(2, GREATEST(penalty_level - 1, 0)), " . DAY_IN_SECONDS . ')';

		$sql = "INSERT INTO {$table} (site_id, bucket_hash, attempts, window_started, blocked_until, penalty_level, updated_at)
			VALUES (%d, %s, 1, %d, 0, 0, %d)
			ON DUPLICATE KEY UPDATE
			penalty_level = CASE WHEN updated_at < {$decay_before} THEN IF({$trigger}, 1, 0) WHEN {$trigger} THEN LEAST(penalty_level + 1, 7) ELSE penalty_level END,
			blocked_until = CASE WHEN blocked_until > {$now} THEN blocked_until WHEN {$trigger} THEN {$now} + {$duration} ELSE 0 END,
			attempts = IF({$window_ends}, 1, attempts + 1),
			window_started = IF({$window_ends}, {$now}, window_started),
			updated_at = {$now}";

		$wpdb->query( $wpdb->prepare( $sql, $site_id, $hash, $now, $now ) );
	}

	private function delete_bucket( $site_id, $hash ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE site_id = %d AND bucket_hash = %s", $site_id, $hash ) );
	}

	private function rate_limit_error( $wait ) {
		return new \WP_Error(
			'saza_login_rate_limited',
			__( 'Too many sign-in attempts. Please try again later.', 'saza-login' ),
			array( 'status' => 429, 'retry_after' => (int) $wait )
		);
	}

	private function send_retry_headers( $wait ) {
		if ( ! headers_sent() ) {
			header( 'Retry-After: ' . max( 1, (int) $wait ), true );
			status_header( 429 );
		}
	}

	public static function get_client_ip( $server = null, $settings = null ) {
		$server   = is_array( $server ) ? $server : $_SERVER;
		$settings = is_array( $settings ) ? $settings : Settings::get();
		$remote   = isset( $server['REMOTE_ADDR'] ) && is_string( $server['REMOTE_ADDR'] ) ? trim( $server['REMOTE_ADDR'] ) : '';
		if ( ! self::is_valid_ip( $remote ) ) {
			return '';
		}

		$trusted = isset( $settings['trusted_proxies'] ) ? (array) $settings['trusted_proxies'] : array();
		if ( 'none' === $settings['proxy_header'] || ! self::ip_is_trusted( $remote, $trusted ) ) {
			return $remote;
		}

		if ( 'cf-connecting-ip' === $settings['proxy_header'] ) {
			$forwarded = isset( $server['HTTP_CF_CONNECTING_IP'] ) && is_string( $server['HTTP_CF_CONNECTING_IP'] ) ? trim( $server['HTTP_CF_CONNECTING_IP'] ) : '';
			return self::is_valid_ip( $forwarded ) ? $forwarded : $remote;
		}

		if ( 'x-forwarded-for' === $settings['proxy_header'] ) {
			$header = isset( $server['HTTP_X_FORWARDED_FOR'] ) && is_string( $server['HTTP_X_FORWARDED_FOR'] ) ? $server['HTTP_X_FORWARDED_FOR'] : '';
			$chain  = array_reverse( array_map( 'trim', explode( ',', $header ) ) );
			$current = $remote;
			foreach ( $chain as $candidate ) {
				if ( ! self::ip_is_trusted( $current, $trusted ) || ! self::is_valid_ip( $candidate ) ) {
					break;
				}
				$current = $candidate;
				if ( ! self::ip_is_trusted( $candidate, $trusted ) ) {
					break;
				}
			}
			return $current;
		}

		return $remote;
	}

	public static function is_valid_ip( $ip ) {
		return is_string( $ip ) && false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	public static function is_valid_proxy_cidr( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$parts = explode( '/', trim( $value ), 2 );
		if ( ! self::is_valid_ip( $parts[0] ) ) {
			return false;
		}
		if ( ! isset( $parts[1] ) ) {
			return true;
		}

		$packed = inet_pton( $parts[0] );
		$bits   = strlen( $packed ) * 8;
		return ctype_digit( $parts[1] ) && (int) $parts[1] > 0 && (int) $parts[1] <= $bits;
	}

	public static function ip_is_trusted( $ip, $ranges ) {
		if ( ! self::is_valid_ip( $ip ) ) {
			return false;
		}

		foreach ( (array) $ranges as $range ) {
			if ( self::ip_in_cidr( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	public static function ip_in_cidr( $ip, $cidr ) {
		if ( ! self::is_valid_proxy_cidr( $cidr ) ) {
			return false;
		}

		$parts     = explode( '/', $cidr, 2 );
		$ip_binary = inet_pton( $ip );
		$net_binary = inet_pton( $parts[0] );
		if ( false === $ip_binary || false === $net_binary || strlen( $ip_binary ) !== strlen( $net_binary ) ) {
			return false;
		}

		$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip_binary ) * 8;
		$full_bytes = (int) floor( $bits / 8 );
		$remaining  = $bits % 8;
		if ( substr( $ip_binary, 0, $full_bytes ) !== substr( $net_binary, 0, $full_bytes ) ) {
			return false;
		}
		if ( 0 === $remaining ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $remaining ) ) & 0xff;
		return ( ord( $ip_binary[ $full_bytes ] ) & $mask ) === ( ord( $net_binary[ $full_bytes ] ) & $mask );
	}

	public static function next_state( $state, $limit, $window, $base_lock, $now ) {
		$state = wp_parse_args(
			is_array( $state ) ? $state : array(),
			array( 'attempts' => 0, 'window_started' => 0, 'blocked_until' => 0, 'penalty_level' => 0, 'updated_at' => 0 )
		);
		if ( (int) $state['updated_at'] < $now - DAY_IN_SECONDS ) {
			$state['penalty_level'] = 0;
		}
		$blocked_until = (int) $state['blocked_until'];
		if ( (int) $state['window_started'] + (int) $window <= $now ) {
			$state['attempts'] = 0;
			$state['window_started'] = $now;
		}
		$state['attempts'] = (int) $state['attempts'] + 1;
		$state['blocked_until'] = $blocked_until > $now ? $blocked_until : 0;
		if ( $blocked_until <= $now && $state['attempts'] >= $limit ) {
			$duration = min( (int) $base_lock * pow( 2, min( (int) $state['penalty_level'], 6 ) ), DAY_IN_SECONDS );
			$state['blocked_until'] = $now + (int) $duration;
			$state['penalty_level'] = min( (int) $state['penalty_level'] + 1, 7 );
		}
		$state['updated_at'] = $now;
		return $state;
	}
}

