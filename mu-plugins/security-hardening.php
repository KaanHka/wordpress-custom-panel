<?php
/**
 * Plugin Name: WCP Security — sertleştirme
 * Description: REST/author enumeration engeli, xmlrpc kapatma, güvenlik başlıkları, Cloudflare-uyumlu giriş deneme sınırı, app-password kapatma.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* Cloudflare arkasında gerçek istemci IP */
function wcp_client_ip() {
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) { return preg_replace( '/[^0-9a-fA-F:.]/', '', $_SERVER['HTTP_CF_CONNECTING_IP'] ); }
	return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
}

/* 1) REST kullanıcı enumeration engeli (giriş yoksa /users kapalı) */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $r ) { if ( isset( $endpoints[ $r ] ) ) { unset( $endpoints[ $r ] ); } }
	}
	return $endpoints;
} );

/* 2) Author enumeration engeli — ?author=N canonical redirect'ten ÖNCE (init) yakala */
add_action( 'init', function () {
	if ( is_admin() || is_user_logged_in() ) { return; }
	if ( isset( $_GET['author'] ) && preg_match( '/^\d+$/', trim( (string) wp_unslash( $_GET['author'] ) ) ) ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
} );
add_action( 'template_redirect', function () {
	if ( ! is_admin() && ! is_user_logged_in() && is_author() ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
}, 0 );

/* 3) XML-RPC tamamen kapalı + pingback */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () { return array(); } );
add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );
add_filter( 'pings_open', '__return_false' );

/* 4) Uygulama şifreleri (REST temel kimlik) kapalı */
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/* 5) Güvenlik başlıkları */
add_action( 'send_headers', function () {
	@header( 'X-Content-Type-Options: nosniff' );
	@header( 'X-Frame-Options: SAMEORIGIN' );
	@header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	@header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
}, 11 );

/* 6) Giriş deneme sınırı (Cloudflare-uyumlu; 12 hata / 15 dk → 15 dk kilit) */
add_action( 'wp_login_failed', function () {
	$ip = wcp_client_ip(); if ( ! $ip ) { return; }
	$k = 'wcp_lf_' . md5( $ip ); $n = (int) get_transient( $k );
	set_transient( $k, $n + 1, 15 * MINUTE_IN_SECONDS );
} );
add_filter( 'authenticate', function ( $user, $username ) {
	if ( empty( $username ) ) { return $user; }
	$ip = wcp_client_ip(); if ( ! $ip ) { return $user; }
	if ( (int) get_transient( 'wcp_lf_' . md5( $ip ) ) >= 12 ) {
		return new WP_Error( 'wcp_locked', 'Çok fazla başarısız giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.' );
	}
	return $user;
}, 30, 2 );
add_action( 'wp_login', function () { $ip = wcp_client_ip(); if ( $ip ) { delete_transient( 'wcp_lf_' . md5( $ip ) ); } } );

/* 7) Giriş hata mesajını genelleştir (kullanıcı adı/şifre ipucu verme) */
add_filter( 'login_errors', function () { return 'Giriş bilgileri hatalı.'; } );

/* 8) REST oembed ile kullanıcı slug sızıntısını azalt */
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
