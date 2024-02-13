<?php
/**
 * Plugin Name: Store Checkout Layout
 * Description: Ödeme bloğunu sipariş özetinden çıkarıp formun hemen altına (customer details sonrası) taşır. Sipariş özeti tablosu sağda kalır. Sadece düzen; işlev/PayTR korunur.
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', function () {
	// Ödemeyi sipariş-özeti alanından çıkar...
	remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	// ...ve formun altına (fatura detaylarından sonra) yerleştir.
	add_action( 'woocommerce_checkout_after_customer_details', 'woocommerce_checkout_payment', 20 );
}, 100 );
