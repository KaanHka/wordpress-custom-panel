<?php
/* Plugin Name: WCP WebP — yeni yüklemelerde otomatik WebP üretimi */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function wcp_make_webp( $p ) {
	if ( ! $p || ! preg_match( '/\.(jpe?g|png)$/i', $p ) || ! is_file( $p ) ) { return; }
	$w = $p . '.webp';
	if ( is_file( $w ) && filemtime( $w ) >= filemtime( $p ) ) { return; }
	if ( ! class_exists( 'Imagick' ) ) { return; }
	try {
		$im = new Imagick( $p );
		if ( $im->getImageColorspace() === Imagick::COLORSPACE_CMYK ) { $im->transformImageColorspace( Imagick::COLORSPACE_SRGB ); }
		$im->setImageFormat( 'webp' );
		$im->setImageCompressionQuality( 82 );
		$im->stripImage();
		$im->writeImage( $w );
		$im->clear();
		$im->destroy();
	} catch ( \Throwable $e ) {}
}

/* Yeni yüklenen görsel + tüm boyutları için webp üret */
add_filter( 'wp_generate_attachment_metadata', function ( $meta, $id ) {
	$file = get_attached_file( $id );
	wcp_make_webp( $file );
	if ( ! empty( $meta['sizes'] ) && $file ) {
		$base = trailingslashit( dirname( $file ) );
		foreach ( $meta['sizes'] as $s ) { if ( ! empty( $s['file'] ) ) { wcp_make_webp( $base . $s['file'] ); } }
	}
	return $meta;
}, 20, 2 );

/* Ek boyut üretiminde de webp */
add_filter( 'wp_image_editors', function ( $e ) { return $e; } );

/* Görsel silinince webp siblinglerini de sil */
add_action( 'delete_attachment', function ( $id ) {
	$file = get_attached_file( $id );
	if ( $file && is_file( $file . '.webp' ) ) { @unlink( $file . '.webp' ); }
	$meta = wp_get_attachment_metadata( $id );
	if ( ! empty( $meta['sizes'] ) && $file ) {
		$base = trailingslashit( dirname( $file ) );
		foreach ( $meta['sizes'] as $s ) { if ( ! empty( $s['file'] ) && is_file( $base . $s['file'] . '.webp' ) ) { @unlink( $base . $s['file'] . '.webp' ); } }
	}
} );
