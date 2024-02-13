<?php
/* Plugin Name: WCP Cloudflare Auto Purge */
if ( ! defined( "ABSPATH" ) ) { exit; }
if ( ! defined( "WCP_CF_TOKEN" ) || ! defined( "WCP_CF_ZONE" ) ) { return; }

function wcp_cf_purge_all() {
	static $done = false; if ( $done ) { return; } $done = true;
	wp_remote_post( "https://api.cloudflare.com/client/v4/zones/" . WCP_CF_ZONE . "/purge_cache", array(
		"timeout"  => 8,
		"blocking" => false,
		"headers"  => array( "Authorization" => "Bearer " . WCP_CF_TOKEN, "Content-Type" => "application/json" ),
		"body"     => wp_json_encode( array( "purge_everything" => true ) ),
	) );
	wcp_cf_warm_after_purge();
}

/* Purge sonrası arka planda cache ısıt (debounce: 30sn içinde toplu değişiklikleri birleştirir) */
function wcp_cf_warm_after_purge() {
	if ( get_transient( "wcp_cf_warm_pending" ) ) { return; }
	set_transient( "wcp_cf_warm_pending", 1, 30 );
	$sh = "/home/example/warm-cache.sh";
	if ( function_exists( "exec" ) && is_file( $sh ) ) {
		@exec( "nohup /bin/bash " . escapeshellarg( $sh ) . " --delay >/dev/null 2>&1 &" );
	}
}
add_action( "save_post", function ( $id ) {
	if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
	if ( in_array( get_post_status( $id ), array( "publish", "trash", "draft", "private", "pending" ), true ) ) { wcp_cf_purge_all(); }
}, 10, 1 );
foreach ( array(
	"woocommerce_update_product", "woocommerce_product_set_stock", "woocommerce_variation_set_stock",
	"woocommerce_product_set_stock_status", "woocommerce_save_product_variation", "woocommerce_order_status_changed",
	"edited_term", "created_term", "delete_term", "wp_update_nav_menu", "switch_theme",
	"customize_save_after", "upgrader_process_complete", "woocommerce_settings_saved",
) as $h ) { add_action( $h, "wcp_cf_purge_all" ); }

/* Admin bar: manuel "Cloudflare boşalt" butonu */
add_action( "admin_bar_menu", function ( $bar ) {
	if ( ! current_user_can( "manage_options" ) ) { return; }
	$bar->add_node( array( "id" => "wcp-cf-purge", "title" => "⚡ Cache boşalt", "href" => wp_nonce_url( admin_url( "?wcp_cf_purge=1" ), "wcp_cf_purge" ) ) );
}, 100 );
add_action( "admin_init", function () {
	if ( ! empty( $_GET["wcp_cf_purge"] ) && current_user_can( "manage_options" ) && check_admin_referer( "wcp_cf_purge" ) ) {
		wcp_cf_purge_all(); wp_safe_redirect( admin_url() ); exit;
	}
} );
