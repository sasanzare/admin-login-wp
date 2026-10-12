<?php
/**
 * Lightweight styling and branding for the native WordPress login screen.
 *
 * @package SazaLogin
 */

namespace SazaLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_Style {
	public function register() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
		add_filter( 'login_body_class', array( $this, 'body_classes' ), 10, 2 );
	}

	public function enqueue() {
		if ( ! Settings::get( 'login_style_enabled' ) ) {
			return;
		}

		$style_version = SAZA_LOGIN_VERSION . '.' . filemtime( SAZA_LOGIN_DIR . 'assets/css/login.css' );
		wp_enqueue_style( 'saza-login', SAZA_LOGIN_URL . 'assets/css/login.css', array(), $style_version );
		$settings = Settings::get();
		$primary  = sanitize_hex_color( $settings['primary_color'] ) ? $settings['primary_color'] : Settings::defaults()['primary_color'];
		$background = sanitize_hex_color( $settings['background_color'] ) ? $settings['background_color'] : Settings::defaults()['background_color'];
		$css      = ':root{--saza-primary:' . $primary . ';--saza-background:' . $background . ';--saza-button-text:' . $this->button_text_color( $primary ) . ';}';
		$logo_url = $this->logo_url();
		if ( $logo_url ) {
			$css .= 'body.login.saza-login-has-logo #login h1 a{background-image:url("' . $this->escape_css_url( $logo_url ) . '");}';
		}
		wp_add_inline_style( 'saza-login', $css );
	}

	public function header_url( $url ) {
		return home_url( '/' );
	}

	public function header_text( $text ) {
		if ( ! Settings::get( 'login_style_enabled' ) ) {
			return $text;
		}

		return esc_html( get_bloginfo( 'name', 'display' ) );
	}

	public function body_classes( $classes, $action ) {
		if ( ! Settings::get( 'login_style_enabled' ) ) {
			return $classes;
		}

		$classes[] = $this->logo_url() ? 'saza-login-has-logo' : 'saza-login-no-logo';
		return $classes;
	}

	private function logo_url() {
		$attachment_id = (int) Settings::get( 'logo_attachment_id' );
		if ( ! $attachment_id ) {
			$attachment_id = (int) get_theme_mod( 'custom_logo' );
		}

		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		return is_array( $image ) && ! empty( $image[0] ) ? $image[0] : '';
	}

	private function escape_css_url( $url ) {
		$url = esc_url_raw( $url );
		$url = str_replace( array( "\r", "\n", "\f" ), '', $url );
		return str_replace(
			array( '\\', '"', '<', '>' ),
			array( '\\5C ', '\\22 ', '\\3C ', '\\3E ' ),
			$url
		);
	}

	private function button_text_color( $color ) {
		$hex = ltrim( $color, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$rgb = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		$linear = array();
		foreach ( $rgb as $channel ) {
			$value = $channel / 255;
			$linear[] = $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}
		$luminance     = ( 0.2126 * $linear[0] ) + ( 0.7152 * $linear[1] ) + ( 0.0722 * $linear[2] );
		$white_contrast = 1.05 / ( $luminance + 0.05 );
		$black_contrast = ( $luminance + 0.05 ) / 0.05;
		return $white_contrast >= $black_contrast ? '#ffffff' : '#111111';
	}
}

