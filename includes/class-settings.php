<?php
/**
 * Plugin settings and Settings API integration.
 *
 * @package SazaLogin
 */

namespace SazaLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	const OPTION = 'saza_login_settings';
	const PAGE   = 'saza-login';
	const GROUP  = 'saza_login';

	public static function defaults() {
		return array(
			'login_style_enabled'       => 1,
			'logo_attachment_id'        => 0,
			'primary_color'             => '#146c60',
			'background_color'         => '#f1f5f4',
			'login_url_enabled'         => 0,
			'login_slug'                => 'secure-login',
			'brute_force_enabled'       => 1,
			'max_attempts'              => 5,
			'window_minutes'            => 15,
			'lockout_minutes'           => 15,
			'ip_max_attempts'           => 20,
			'xmlrpc_auth_disabled'      => 0,
			'proxy_header'              => 'none',
			'trusted_proxies'           => array(),
			'delete_on_uninstall'       => 0,
		);
	}

	public static function get( $key = null ) {
		$stored   = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public static function add_defaults() {
		add_option( self::OPTION, self::defaults(), '', true );
	}

	public function register() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_saza_login_clear_limits', array( $this, 'clear_limits' ) );
		add_action( 'admin_notices', array( $this, 'database_notice' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Saza-login', 'saza-login' ),
			__( 'Saza-login', 'saza-login' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'saza_login_appearance',
			__( 'Appearance', 'saza-login' ),
			array( $this, 'appearance_intro' ),
			self::PAGE
		);
		add_settings_section(
			'saza_login_address',
			__( 'Login address', 'saza-login' ),
			array( $this, 'address_intro' ),
			self::PAGE
		);
		add_settings_section(
			'saza_login_security',
			__( 'Security', 'saza-login' ),
			array( $this, 'security_intro' ),
			self::PAGE
		);

		$this->add_field( 'saza_login_appearance', 'login_style_enabled', __( 'Custom login design', 'saza-login' ), __( 'Use the lightweight Saza-login design on the standard WordPress login screen.', 'saza-login' ), 'checkbox' );
		$this->add_field( 'saza_login_appearance', 'logo_attachment_id', __( 'Login logo', 'saza-login' ), __( 'A selected logo takes priority over the site logo. Leave it empty to use the site logo automatically.', 'saza-login' ), 'media' );
		$this->add_field( 'saza_login_appearance', 'primary_color', __( 'Primary color', 'saza-login' ), __( 'Used for the login button and focus states.', 'saza-login' ), 'color' );
		$this->add_field( 'saza_login_appearance', 'background_color', __( 'Background color', 'saza-login' ), __( 'Used beneath the login form and its soft gradient.', 'saza-login' ), 'color' );

		$this->add_field( 'saza_login_address', 'login_url_enabled', __( 'Change login address', 'saza-login' ), __( 'This hides the ordinary wp-login.php page for login requests. Save the new URL before enabling this option.', 'saza-login' ), 'checkbox' );
		$this->add_field( 'saza_login_address', 'login_slug', __( 'Login slug', 'saza-login' ), __( 'Use one lowercase URL segment with letters, numbers, and hyphens.', 'saza-login' ), 'text' );
		$this->add_field( 'saza_login_address', 'current_login_url', __( 'Current or proposed login URL', 'saza-login' ), __( 'Copy and store the address before changing the login route.', 'saza-login' ), 'url' );

		$this->add_field( 'saza_login_security', 'brute_force_enabled', __( 'Brute-force protection', 'saza-login' ), __( 'Counts failed authentication through WordPress core hooks and temporarily blocks the source IP and login pair.', 'saza-login' ), 'checkbox' );
		$this->add_field( 'saza_login_security', 'max_attempts', __( 'Login and IP-pair attempts', 'saza-login' ), __( 'Failed attempts for one source IP and one username or email during the counting window.', 'saza-login' ), 'number' );
		$this->add_field( 'saza_login_security', 'window_minutes', __( 'Counting window (minutes)', 'saza-login' ), __( 'How long the failed-attempt count remains active.', 'saza-login' ), 'number' );
		$this->add_field( 'saza_login_security', 'lockout_minutes', __( 'Initial lockout (minutes)', 'saza-login' ), __( 'Repeat lockouts for the same source are extended progressively, up to 24 hours.', 'saza-login' ), 'number' );
		$this->add_field( 'saza_login_security', 'ip_max_attempts', __( 'Total attempts per IP', 'saza-login' ), __( 'A separate IP-wide ceiling helps limit attempts spread across many usernames.', 'saza-login' ), 'number' );
		$this->add_field( 'saza_login_security', 'xmlrpc_auth_disabled', __( 'Disable authenticated XML-RPC methods', 'saza-login' ), __( 'Disables XML-RPC methods that require a WordPress username and password. This can affect mobile apps and publishing integrations; pingbacks and custom unauthenticated XML-RPC methods are not disabled by this setting.', 'saza-login' ), 'checkbox' );
		$this->add_field( 'saza_login_security', 'proxy_header', __( 'Trusted proxy header', 'saza-login' ), __( 'Forwarded IP headers are ignored unless the connecting proxy is listed below.', 'saza-login' ), 'select' );
		$this->add_field( 'saza_login_security', 'trusted_proxies', __( 'Trusted proxy IPs or CIDRs', 'saza-login' ), __( 'Enter one trusted proxy IP address or CIDR range per line. Only configure addresses controlled by you.', 'saza-login' ), 'textarea' );
		$this->add_field( 'saza_login_security', 'delete_on_uninstall', __( 'Delete data on uninstall', 'saza-login' ), __( 'By default, uninstall preserves settings. Enable this to delete this site’s settings and rate-limit records when the plugin is deleted.', 'saza-login' ), 'checkbox' );
	}

	private function add_field( $section, $key, $title, $description, $type ) {
		add_settings_field(
			'saza_login_' . $key,
			$title,
			array( $this, 'render_field' ),
			self::PAGE,
			$section,
			array(
				'key'         => $key,
				'type'        => $type,
				'description' => $description,
			)
		);
	}

	public function appearance_intro() {
		echo '<p>' . esc_html__( 'The native WordPress login form remains in place. Saza-login changes its presentation and logo only.', 'saza-login' ) . '</p>';
	}

	public function address_intro() {
		echo '<p class="saza-login-warning">' . esc_html__( 'Changing the login address can interrupt sign-in if server rewrites or another security plugin conflict. Keep an administrator session open and store the new address first.', 'saza-login' ) . '</p>';
		if ( Login_Url::is_enabled() && ! Login_Url::is_active() ) {
			$url = Login_Url::current_url();
			echo '<p class="notice notice-info inline">' . esc_html__( 'The custom address is pending verification. The standard login remains available until the new route successfully opens.', 'saza-login' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the new login address', 'saza-login' ) . '</a></p>';
		}
		if ( is_multisite() ) {
			echo '<p class="saza-login-warning">' . esc_html__( 'Custom login addresses are unavailable on Multisite. The standard WordPress login URL remains active.', 'saza-login' ) . '</p>';
		} elseif ( '' === get_option( 'permalink_structure' ) ) {
			echo '<p class="saza-login-warning">' . esc_html__( 'Custom login addresses require pretty permalinks. Choose a permalink structure before enabling this feature.', 'saza-login' ) . '</p>';
		}
	}

	public function security_intro() {
		echo '<p>' . esc_html__( 'Rate limits are stored in a small indexed table. The plugin never stores submitted passwords, raw usernames, or raw client IP addresses.', 'saza-login' ) . '</p>';
	}

	public function render_field( $args ) {
		$key         = $args['key'];
		$type        = $args['type'];
		$description = $args['description'];
		$value       = self::get( $key );
		$name        = self::OPTION . '[' . $key . ']';

		if ( 'checkbox' === $type ) {
			printf(
				'<label><input id="%1$s" name="%2$s" type="checkbox" value="1" %3$s /> %4$s</label>',
				esc_attr( 'saza-login-' . $key ),
				esc_attr( $name ),
				checked( 1, (int) $value, false ),
				esc_html__( 'Enabled', 'saza-login' )
			);
		} elseif ( 'media' === $type ) {
			$image = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
			printf( '<div class="saza-login-media-preview">%s</div>', $image ? '<img src="' . esc_url( $image ) . '" alt="" />' : '<span>' . esc_html__( 'No custom logo selected', 'saza-login' ) . '</span>' );
			printf( '<input id="saza-login-logo-id" name="%s" type="hidden" value="%d" />', esc_attr( $name ), (int) $value );
			printf( '<button class="button" id="saza-login-select-logo" type="button">%s</button> ', esc_html__( 'Choose image', 'saza-login' ) );
			printf( '<button class="button" id="saza-login-remove-logo" type="button">%s</button>', esc_html__( 'Remove custom logo', 'saza-login' ) );
		} elseif ( 'color' === $type ) {
			printf( '<input id="%1$s" name="%2$s" type="color" value="%3$s" />', esc_attr( 'saza-login-' . $key ), esc_attr( $name ), esc_attr( $value ) );
		} elseif ( 'number' === $type ) {
			$limits = array(
				'max_attempts'    => array( 3, 20 ),
				'window_minutes'  => array( 1, 1440 ),
				'lockout_minutes' => array( 1, 1440 ),
				'ip_max_attempts' => array( 5, 200 ),
			);
			$min    = isset( $limits[ $key ] ) ? $limits[ $key ][0] : 1;
			$max    = isset( $limits[ $key ] ) ? $limits[ $key ][1] : 1440;
			printf( '<input class="small-text" id="%1$s" name="%2$s" type="number" min="%3$d" max="%4$d" value="%5$d" />', esc_attr( 'saza-login-' . $key ), esc_attr( $name ), $min, $max, (int) $value );
		} elseif ( 'text' === $type ) {
			printf( '<input class="regular-text" id="%1$s" name="%2$s" type="text" maxlength="48" pattern="[a-z0-9-]+" value="%3$s" autocomplete="off" />', esc_attr( 'saza-login-' . $key ), esc_attr( $name ), esc_attr( $value ) );
		} elseif ( 'url' === $type ) {
			$url = Login_Url::is_active() || Login_Url::is_enabled() ? Login_Url::current_url() : site_url( 'wp-login.php', 'login' );
			printf( '<input class="regular-text code" id="saza-login-current-url" type="url" readonly value="%s" /> ', esc_attr( $url ) );
			printf( '<button class="button" data-copy-url="saza-login-current-url" type="button">%s</button>', esc_html__( 'Copy URL', 'saza-login' ) );
			printf( '<p class="description" aria-live="polite" id="saza-login-copy-status">%s</p>', esc_html__( 'The default address is active until a custom route has been verified.', 'saza-login' ) );
		} elseif ( 'select' === $type ) {
			printf( '<select id="saza-login-proxy-header" name="%s">', esc_attr( $name ) );
			$headers = array(
				'none'              => __( 'Do not read forwarded headers', 'saza-login' ),
				'cf-connecting-ip'  => __( 'Cloudflare CF-Connecting-IP', 'saza-login' ),
				'x-forwarded-for'   => __( 'X-Forwarded-For chain', 'saza-login' ),
			);
			foreach ( $headers as $header => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $header ), selected( $value, $header, false ), esc_html( $label ) );
			}
			echo '</select>';
		} elseif ( 'textarea' === $type ) {
			printf( '<textarea class="large-text code" id="saza-login-trusted-proxies" name="%1$s" rows="5">%2$s</textarea>', esc_attr( $name ), esc_textarea( implode( "\n", (array) $value ) ) );
		}

		if ( $description && 'url' !== $type ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap saza-login-admin"><h1>' . esc_html__( 'Saza-login settings', 'saza-login' ) . '</h1>';
		settings_errors();

		if ( isset( $_GET['saza_login_cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Active rate limits were cleared.', 'saza-login' ) . '</p></div>';
		}

		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button( __( 'Save settings', 'saza-login' ) );
		echo '</form>';
		$this->render_clear_limits_form();
		echo '</div>';
	}

	private function render_clear_limits_form() {
		echo '<section class="saza-login-clear-limits"><h2>' . esc_html__( 'Active limits', 'saza-login' ) . '</h2>';
		echo '<p>' . esc_html__( 'Clear all rate-limit records for this site if a legitimate user needs access.', 'saza-login' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="saza_login_clear_limits" />';
		wp_nonce_field( 'saza_login_clear_limits' );
		echo '<button class="button" type="submit">' . esc_html__( 'Clear active limits', 'saza-login' ) . '</button>';
		echo '</form></section>';
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'saza-login-admin', SAZA_LOGIN_URL . 'assets/css/admin.css', array(), SAZA_LOGIN_VERSION );
		wp_enqueue_script( 'saza-login-admin', SAZA_LOGIN_URL . 'assets/js/admin.js', array(), SAZA_LOGIN_VERSION, true );
		wp_localize_script(
			'saza-login-admin',
			'SazaLoginAdmin',
			array(
				'mediaTitle'  => __( 'Select a login logo', 'saza-login' ),
				'mediaButton' => __( 'Use this logo', 'saza-login' ),
				'noLogo'      => __( 'No custom logo selected', 'saza-login' ),
				'copied'      => __( 'Login URL copied.', 'saza-login' ),
				'copyFailed'  => __( 'Could not copy the URL. Select and copy it manually.', 'saza-login' ),
			)
		);
	}

	public function clear_limits() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Saza-login settings.', 'saza-login' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'saza_login_clear_limits' );
		Rate_Limiter::clear_site_limits();
		wp_safe_redirect( add_query_arg( 'saza_login_cleared', '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	public function database_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! current_user_can( 'manage_options' ) || ! $screen || 'settings_page_' . self::PAGE !== $screen->id ) {
			return;
		}

		if ( get_option( 'saza_login_db_ready', 0 ) && Rate_Limiter::table_exists() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'Saza-login could not create its rate-limit table. Brute-force protection is unavailable until the database issue is fixed and the plugin is reactivated.', 'saza-login' ) . '</p></div>';
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();
		$new   = self::defaults();

		$new['login_style_enabled']  = isset( $input['login_style_enabled'] ) ? 1 : 0;
		$new['login_url_enabled']    = isset( $input['login_url_enabled'] ) ? 1 : 0;
		$new['brute_force_enabled']  = isset( $input['brute_force_enabled'] ) ? 1 : 0;
		$new['xmlrpc_auth_disabled'] = isset( $input['xmlrpc_auth_disabled'] ) ? 1 : 0;
		$new['delete_on_uninstall']  = isset( $input['delete_on_uninstall'] ) ? 1 : 0;

		$logo_id = isset( $input['logo_attachment_id'] ) ? absint( $input['logo_attachment_id'] ) : 0;
		$new['logo_attachment_id'] = $logo_id && wp_attachment_is_image( $logo_id ) ? $logo_id : 0;

		foreach ( array( 'primary_color', 'background_color' ) as $color_key ) {
			$color = isset( $input[ $color_key ] ) ? sanitize_hex_color( $input[ $color_key ] ) : false;
			$new[ $color_key ] = $color ? $color : self::defaults()[ $color_key ];
		}

		$slug = isset( $input['login_slug'] ) && is_string( $input['login_slug'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $input['login_slug'] ) ) ) ) : $old['login_slug'];
		$error = Login_Url::validate_slug( $slug );
		if ( $error ) {
			$new['login_slug'] = $old['login_slug'];
			$new['login_url_enabled'] = 0;
			add_settings_error( self::OPTION, 'saza_login_invalid_slug', $error, 'error' );
		} else {
			$new['login_slug'] = $slug;
		}

		$numeric = array(
			'max_attempts'    => array( 3, 20 ),
			'window_minutes'  => array( 1, 1440 ),
			'lockout_minutes' => array( 1, 1440 ),
			'ip_max_attempts' => array( 5, 200 ),
		);
		foreach ( $numeric as $key => $bounds ) {
			$value = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : self::defaults()[ $key ];
			$new[ $key ] = max( $bounds[0], min( $bounds[1], $value ) );
		}

		$proxy_header = isset( $input['proxy_header'] ) ? sanitize_key( $input['proxy_header'] ) : 'none';
		$new['proxy_header'] = in_array( $proxy_header, array( 'none', 'cf-connecting-ip', 'x-forwarded-for' ), true ) ? $proxy_header : 'none';
		$proxy_lines = isset( $input['trusted_proxies'] ) && is_string( $input['trusted_proxies'] ) ? preg_split( '/\r\n|\r|\n/', wp_unslash( $input['trusted_proxies'] ) ) : array();
		$proxies = array();
		foreach ( $proxy_lines as $proxy_line ) {
			$proxy_line = trim( $proxy_line );
			if ( '' === $proxy_line ) {
				continue;
			}
			if ( ! Rate_Limiter::is_valid_proxy_cidr( $proxy_line ) ) {
				add_settings_error( self::OPTION, 'saza_login_invalid_proxy', __( 'One or more trusted proxy entries were invalid and were ignored.', 'saza-login' ), 'error' );
				continue;
			}
			$proxies[] = $proxy_line;
		}
		$new['trusted_proxies'] = array_values( array_unique( array_slice( $proxies, 0, 100 ) ) );

		if ( $new['login_url_enabled'] ) {
			$availability_error = Login_Url::availability_error( $new['login_slug'] );
			if ( $availability_error ) {
				$new['login_url_enabled'] = 0;
				add_settings_error( self::OPTION, 'saza_login_route_unavailable', $availability_error, 'error' );
			}
		}

		return $new;
	}
}

