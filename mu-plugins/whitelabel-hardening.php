<?php
/* Plugin Name: WCP Stealth — WP/WooCommerce iz gizleme (white-label) */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- 1) <head> ve sürüm izleri ---------- */
remove_action( 'wp_head', 'wp_generator' );
foreach ( array( 'the_generator', 'get_the_generator_html', 'get_the_generator_xhtml', 'get_the_generator_atom', 'get_the_generator_rss2', 'get_the_generator_rdf', 'get_the_generator_comment', 'get_the_generator_export' ) as $f ) {
	add_filter( $f, '__return_empty_string' );
}
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
add_filter( 'xmlrpc_enabled', '__return_false' );

/* Emoji (wp-includes/js/wp-emoji*) */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

/* ?ver= sürüm sorgusunu kaldır (asset'lerden) */
function wcp_strip_ver( $src ) { return ( $src && strpos( $src, 'ver=' ) !== false ) ? remove_query_arg( 'ver', $src ) : $src; }
add_filter( 'style_loader_src', 'wcp_strip_ver', 9999 );
add_filter( 'script_loader_src', 'wcp_strip_ver', 9999 );

/* Sunucu başlıkları */
add_action( 'send_headers', function () { @header_remove( 'X-Powered-By' ); @header_remove( 'X-Pingback' ); }, 11 );
add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );

/* ---------- 2) Çıktı tamponu: yol maskeleme + jenerator temizliği ---------- */
function wcp_stealth_buffer( $html ) {
	if ( ! is_string( $html ) || stripos( $html, '<html' ) === false ) { return $html; }
	$pairs = array(
		'/wp-includes/'        => '/core/',
		'/wp-content/plugins/' => '/medya/uygulama/',
		'/medya/plugins/'      => '/medya/uygulama/',
		'/wp-content/themes/'  => '/medya/gorunum/',
		'/medya/themes/'       => '/medya/gorunum/',
		'/wp-content/'         => '/medya/',
		'/medya/uygulama/woocommerce/' => '/medya/uygulama/mgz/',
	);
	$html = str_replace( array_keys( $pairs ), array_values( $pairs ), $html );

	/* WebP: .webp sürümü olan görsel URL'lerini otomatik webp'ye çevir (URL bazlı, Cloudflare-güvenli) */
	$html = preg_replace_callback( '#/medya/uploads/[^\s"\'\\\\)?]+?\.(?:jpe?g|png)#i', function ( $m ) {
		$url = $m[0];
		$fs  = WP_CONTENT_DIR . substr( $url, strlen( '/medya' ) );
		return ( is_file( $fs . '.webp' ) ) ? $url . '.webp' : $url;
	}, $html );

	/* kalan generator meta'larını sil */
	$html = preg_replace( '#<meta[^>]+name=["\']generator["\'][^>]*>\s*#i', '', $html );

	/* LCP: hero görsel(ler)ini erken yükle — lazy kaldır + fetchpriority=high.
	   Hedef: dosya adında "hero" geçen görseller (banner/hero). */
	$html = preg_replace_callback( '#<img\b[^>]*>#i', function ( $m ) {
		$tag = $m[0];
		if ( ! preg_match( '#(src|srcset)=(["\'])[^"\']*hero[^"\']*\2#i', $tag ) ) { return $tag; }
		$tag = preg_replace( '#\s+loading=(["\'])lazy\1#i', '', $tag );
		if ( stripos( $tag, 'fetchpriority' ) === false ) { $tag = preg_replace( '#<img\b#i', '<img fetchpriority="high" loading="eager"', $tag, 1 ); }
		return $tag;
	}, $html );
	return $html;
}

/* Preconnect: font/CDN kaynaklarına erken bağlan (FCP) */
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
}, 1 );
add_action( 'template_redirect', function () {
	if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || is_feed() ) { return; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) { return; }
	ob_start( 'wcp_stealth_buffer' );
}, 0 );
