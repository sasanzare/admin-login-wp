<?php
/**
 * Preserve plugin data unless the administrator opted into deletion.
 *
 * @package SazaLogin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$saza_login_settings = get_option( 'saza_login_settings', array() );
if ( ! is_array( $saza_login_settings ) || empty( $saza_login_settings['delete_on_uninstall'] ) ) {
	return;
}

require_once __DIR__ . '/includes/class-rate-limiter.php';
\SazaLogin\Rate_Limiter::delete_site_data();
delete_option( 'saza_login_settings' );

