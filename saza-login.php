<?php
/**
 * Plugin Name: Saza-login
 * Description: A lightweight login screen design and authentication rate limiter for WordPress.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Saza-login
 * Text Domain: saza-login
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SazaLogin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAZA_LOGIN_VERSION', '1.0.0' );
define( 'SAZA_LOGIN_FILE', __FILE__ );
define( 'SAZA_LOGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAZA_LOGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SAZA_LOGIN_DIR . 'includes/class-settings.php';
require_once SAZA_LOGIN_DIR . 'includes/class-login-url.php';
require_once SAZA_LOGIN_DIR . 'includes/class-login-style.php';
require_once SAZA_LOGIN_DIR . 'includes/class-rate-limiter.php';
require_once SAZA_LOGIN_DIR . 'includes/class-plugin.php';

$saza_login_plugin = new \SazaLogin\Plugin();
$saza_login_plugin->register();

register_activation_hook( __FILE__, array( $saza_login_plugin, 'activate' ) );
register_deactivation_hook( __FILE__, array( $saza_login_plugin, 'deactivate' ) );

