<?php
/**
 * Plugin Name: Store Mağaza Tasarımı (Hesap/Sepet/Ödeme)
 * Description: Hesap (login), Sepet ve Ödeme sayfalarına yeni tasarım dilini uygulayan CSS katmanı. WooCommerce işlevine DOKUNMAZ; sadece görünüm. Dosya silinince eski hâle döner.
 * Author: Store
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_account_page' ) ) { return; }
	if ( is_account_page() || is_cart() || is_checkout() ) {
		$css = WPMU_PLUGIN_DIR . '/assets/shop-redesign.css';
		$ver = file_exists( $css ) ? (string) filemtime( $css ) : '1.0.0';
		wp_enqueue_style( 'wcp-shop', WPMU_PLUGIN_URL . '/assets/shop-redesign.css', array(), $ver );
	}
}, 100 );
