<?php
/**
 * Optional custom login route while preserving the WordPress login script.
 *
 * @package SazaLogin
 */

namespace SazaLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_Url {
	const QUERY_VAR = 'saza_login';
	const VERIFIED_OPTION = 'saza_login_route_verified';

	private static $serving_custom_login = false;

	public function register() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'serve_custom_login' ), 0 );
		add_action( 'login_init', array( $this, 'hide_default_login' ), 0 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 20, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_network_site_url' ), 20, 3 );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 20, 3 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 20, 2 );
		add_filter( 'register_url', array( $this, 'filter_register_url' ), 20, 1 );
		add_action( 'updated_option', array( $this, 'option_updated' ), 10, 3 );
	}

	public static function is_enabled() {
		$slug = (string) Settings::get( 'login_slug' );
		return (int) Settings::get( 'login_url_enabled' ) === 1
			&& preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,46}[a-z0-9])?$/', $slug )
			&& ! in_array( $slug, self::reserved_slugs(), true )
			&& '' !== (string) get_option( 'permalink_structure' )
			&& ! is_multisite();
	}

	public static function current_url() {
		$slug = (string) Settings::get( 'login_slug' );
		return site_url( $slug . '/', 'login' );
	}

	public static function is_active() {
		return self::is_enabled() && (bool) get_option( self::VERIFIED_OPTION, 0 );
	}

	public static function initialize() {
		add_option( self::VERIFIED_OPTION, 0, '', true );
	}

	public static function validate_slug( $slug ) {
		if ( ! is_string( $slug ) || ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,46}[a-z0-9])?$/', $slug ) ) {
			return __( 'The login slug must be one lowercase URL segment using letters, numbers, or internal hyphens.', 'saza-login' );
		}

		if ( in_array( $slug, self::reserved_slugs(), true ) ) {
			return __( 'That login slug is reserved by WordPress or commonly used by a server endpoint.', 'saza-login' );
		}

		return '';
	}

	private static function reserved_slugs() {
		return array(
			'wp-admin', 'wp-content', 'wp-includes', 'wp-login', 'wp-login.php', 'wp-signup',
			'wp-activate', 'wp-activate.php', 'wp-cron', 'wp-cron.php', 'wp-comments-post',
			'wp-comments-post.php', 'wp-json', 'xmlrpc', 'xmlrpc.php', 'admin-ajax', 'admin-ajax.php',
			'admin-post', 'admin-post.php', 'index.php', 'feed', 'robots.txt', 'sitemap.xml',
		);
	}

	public static function availability_error( $slug ) {
		if ( is_multisite() ) {
			return __( 'Custom login addresses are not supported on Multisite. The standard WordPress login URL remains available.', 'saza-login' );
		}

		if ( '' === get_option( 'permalink_structure' ) ) {
			return __( 'Custom login addresses require pretty permalinks. Choose a permalink structure first.', 'saza-login' );
		}

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		if ( $public_types && get_page_by_path( $slug, OBJECT, array_values( $public_types ) ) ) {
			return __( 'That slug is already used by a public page, post, or content type. Choose a different slug.', 'saza-login' );
		}

		$filesystem_path = trailingslashit( ABSPATH ) . $slug;
		if ( file_exists( $filesystem_path ) ) {
			return __( 'That slug matches a file or directory in the WordPress installation. Choose a different slug.', 'saza-login' );
		}

		return '';
	}

	public static function register_rewrite_rule() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$slug = preg_quote( (string) Settings::get( 'login_slug' ), '#' );
		add_rewrite_rule( '^' . $slug . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function serve_custom_login() {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Not Found', 'saza-login' ), '', array( 'response' => 404 ) );
		}

		self::$serving_custom_login = true;
		update_option( self::VERIFIED_OPTION, 1 );
		status_header( 200 );
		nocache_headers();
		$this->initialize_core_login_state();
		global $error;
		$user_login = '';
		require ABSPATH . 'wp-login.php';
		exit;
	}

	public function hide_default_login() {
		$is_default_login = $this->is_default_login_request();
		if ( self::is_enabled() && ! self::$serving_custom_login && $is_default_login ) {
			$this->initialize_core_login_state();
		}

		if ( ! self::is_active() || self::$serving_custom_login || ! $is_default_login ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$legacy_actions = array(
			'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass', 'register',
			'confirmaction', 'enter_recovery_mode', 'entered_recovery_mode', 'checkemail',
			'confirm_admin_email',
		);

		$legacy_checkemail = '' === $action && isset( $_GET['checkemail'] ) && is_string( $_GET['checkemail'] ) && in_array( sanitize_key( wp_unslash( $_GET['checkemail'] ) ), array( 'confirm', 'registered' ), true );
		$legacy_registration_notice = '' === $action && isset( $_GET['registration'] ) && is_string( $_GET['registration'] ) && 'disabled' === sanitize_key( wp_unslash( $_GET['registration'] ) );
		if ( in_array( $action, $legacy_actions, true ) || $legacy_checkemail || $legacy_registration_notice ) {
			return;
		}

		status_header( 404 );
		nocache_headers();
		wp_die( esc_html__( 'Not Found', 'saza-login' ), '', array( 'response' => 404 ) );
	}

	private function is_default_login_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		return is_string( $path ) && 'wp-login.php' === basename( untrailingslashit( $path ) );
	}

	private function initialize_core_login_state() {
		global $error, $user_login;
		if ( ! isset( $error ) ) {
			$error = '';
		}
		if ( ! isset( $user_login ) ) {
			$user_login = '';
		}
	}

	public function filter_site_url( $url, $path, $scheme, $blog_id = null ) {
		return self::replace_login_path( $url, $path );
	}

	public function filter_network_site_url( $url, $path, $scheme ) {
		return self::replace_login_path( $url, $path );
	}

	public function filter_login_url( $url, $redirect, $force_reauth ) {
		return self::replace_login_path( $url, 'wp-login.php' );
	}

	public function filter_lostpassword_url( $url, $redirect ) {
		return self::replace_login_path( $url, 'wp-login.php' );
	}

	public function filter_register_url( $url ) {
		return self::replace_login_path( $url, 'wp-login.php' );
	}

	private static function replace_login_path( $url, $path ) {
		if ( ! is_string( $url ) || ! is_string( $path ) || false === strpos( $path, 'wp-login.php' ) || ! self::is_active() ) {
			return $url;
		}

		$slug = (string) Settings::get( 'login_slug' );
		return preg_replace( '/wp-login\.php(?=[?#]|$)/i', $slug, $url, 1 );
	}

	public function option_updated( $option, $old_value, $new_value ) {
		if ( 'permalink_structure' === $option ) {
			if ( empty( $new_value ) ) {
				self::remove_rewrite_rule( (string) Settings::get( 'login_slug' ) );
				self::set_verified( false );
				flush_rewrite_rules( false );
			} elseif ( ! empty( Settings::get( 'login_url_enabled' ) ) ) {
				self::set_verified( false );
				self::register_rewrite_rule();
				flush_rewrite_rules( false );
			}
			return;
		}

		if ( Settings::OPTION !== $option || ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		$old_enabled = ! empty( $old_value['login_url_enabled'] );
		$new_enabled = ! empty( $new_value['login_url_enabled'] );
		$slug_changed = isset( $old_value['login_slug'], $new_value['login_slug'] ) && $old_value['login_slug'] !== $new_value['login_slug'];
		if ( $old_enabled === $new_enabled && ( ! $new_enabled || ! $slug_changed ) ) {
			return;
		}

		self::set_verified( false );

		if ( $old_enabled && isset( $old_value['login_slug'] ) ) {
			self::remove_rewrite_rule( $old_value['login_slug'] );
		}

		if ( $new_enabled && ! self::availability_error( $new_value['login_slug'] ) ) {
			self::register_rewrite_rule();
		}

		flush_rewrite_rules( false );
	}

	private static function remove_rewrite_rule( $slug ) {
		global $wp_rewrite;
		if ( ! isset( $wp_rewrite->extra_rules_top ) || ! is_string( $slug ) || '' === $slug ) {
			return;
		}

		$regex = '^' . preg_quote( $slug, '#' ) . '/?$';
		unset( $wp_rewrite->extra_rules_top[ $regex ] );
	}

	private static function set_verified( $verified ) {
		add_option( self::VERIFIED_OPTION, 0, '', true );
		update_option( self::VERIFIED_OPTION, $verified ? 1 : 0 );
	}

	public function disable_on_deactivation() {
		$settings = Settings::get();
		if ( ! empty( $settings['login_url_enabled'] ) ) {
			$settings['login_url_enabled'] = 0;
			update_option( Settings::OPTION, $settings );
		}
		self::set_verified( false );
	}
}

