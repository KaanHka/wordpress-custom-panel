<?php
/**
 * Plugin Name: Store Yeni Ana Sayfa (önizleme)
 * Description: 'yeni-anasayfa' sayfasinda yeni tasarim CSS'ini yukler, wpautop'u kapatir, tema baslik/container kisitlarini sifirlar. Sadece o sayfada calisir.
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function wcp_home_page_id() {
	static $id = null;
	if ( $id === null ) {
		$p  = get_page_by_path( 'yeni-anasayfa' );
		$id = $p ? (int) $p->ID : 0;
	}
	return $id;
}

function wcp_is_home_preview() {
	$id = wcp_home_page_id();
	return $id && ( is_page( $id ) || ( function_exists( 'is_front_page' ) && is_front_page() && (int) get_option( 'page_on_front' ) === $id ) );
}

/* Canli urun fiyati kisa kodu: [wcp_price id="123"] -> guncel fiyat */
add_shortcode( 'wcp_price', function ( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$product = wc_get_product( (int) $atts['id'] );
	if ( ! $product ) {
		return '—';
	}
	$price = $product->get_price();
	if ( '' === $price || null === $price ) {
		return '—';
	}
	return wc_price( $price );
} );

/* wpautop kapat (ham HTML bozulmasin) */
add_action( 'wp', function () {
	if ( wcp_is_home_preview() ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
	}
} );

/* CSS yukle */
add_action( 'wp_enqueue_scripts', function () {
	if ( wcp_is_home_preview() ) {
		$css_path = WPMU_PLUGIN_DIR . '/assets/home-redesign.css';
		$ver      = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';
		wp_enqueue_style( 'wcp-home', WPMU_PLUGIN_URL . '/assets/home-redesign.css', array(), $ver );

		$js_path = WPMU_PLUGIN_DIR . '/assets/home-anim.js';
		$jver    = file_exists( $js_path ) ? (string) filemtime( $js_path ) : '1.0.0';
		wp_enqueue_script( 'wcp-home-anim', WPMU_PLUGIN_URL . '/assets/home-anim.js', array(), $jver, true );
	}
}, 100 );

/* Sayfaya ozel: tema baslik & container sifirlama (id dinamik) */
add_action( 'wp_head', function () {
	if ( ! wcp_is_home_preview() ) { return; }
	$id = wcp_home_page_id();
	// Taslak iken arama motorlarina kapali (ana sayfa olunca acilir)
	if ( ! is_front_page() ) { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }
	echo "\n<style id=\"wcp-home-reset\">\n"
	. "body.page-id-{$id} .page-title,body.page-id-{$id} .wd-page-title,body.page-id-{$id} .woodmart-page-title,body.page-id-{$id} .entry-header,body.page-id-{$id} .breadcrumbs{display:none!important;}\n"
	. "body.page-id-{$id} .content-area{width:100%!important;max-width:100%!important;padding:0!important;margin:0!important;flex:0 0 100%!important;}\n"
	. "body.page-id-{$id} .site-content,body.page-id-{$id} .content-area>.row{padding:0!important;}\n"
	. "body.page-id-{$id} .site-content>.container,body.page-id-{$id} .main-page-wrapper>.container{max-width:100%!important;width:100%!important;padding:0!important;}\n"
	. "</style>\n";
}, 101 );
