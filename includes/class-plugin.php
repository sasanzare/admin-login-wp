<?php
/**
 * Plugin bootstrap and lifecycle.
 *
 * @package SazaLogin
 */

namespace SazaLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/** @var Settings */
	private $settings;

	/** @var Login_Url */
	private $login_url;

	/** @var Login_Style */
	private $login_style;

	/** @var Rate_Limiter */
	private $rate_limiter;

	public function __construct() {
		$this->settings     = new Settings();
		$this->login_url    = new Login_Url();
		$this->login_style  = new Login_Style();
		$this->rate_limiter = new Rate_Limiter();
	}

	public function register() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		$this->settings->register();
		$this->login_url->register();
		$this->login_style->register();
		$this->rate_limiter->register();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'saza-login', false, dirname( plugin_basename( SAZA_LOGIN_FILE ) ) . '/languages' );
	}

	public function activate() {
		Settings::add_defaults();
		Login_Url::initialize();
		Rate_Limiter::create_table();
		Rate_Limiter::schedule_cleanup();
		Login_Url::register_rewrite_rule();
		flush_rewrite_rules( false );
	}

	public function deactivate() {
		$this->login_url->disable_on_deactivation();
		Rate_Limiter::clear_temporary_limits();
		wp_clear_scheduled_hook( Rate_Limiter::CLEANUP_HOOK );
	}
}

