<?php
/**
 * Plugin Name: Store Mobil Dock
 * Description: Mobil alt toolbar'ı yüzen "liquid glass" dinamik bir dock'a çevirir. Aşağı kaydırınca sola büzülüp yalnız "Menü" kalır, yukarı kaydırınca tüm butonlar geri gelir. Site geneli, tema güncellemesinden bağımsız.
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', function () {
	$path = WPMU_PLUGIN_DIR . '/assets/wcp-mobile-dock.css';
	if ( file_exists( $path ) ) {
		wp_enqueue_style(
			'wcp-mobile-dock',
			content_url( 'mu-plugins/assets/wcp-mobile-dock.css' ),
			array(),
			filemtime( $path )
		);
	}
}, 110 );

/**
 * Kaydırma yönüne göre dock'u büzer/açar.
 * Aşağı -> .wcp-dock-min (sola, yalnız Menü) ; yukarı / tepe -> tam dock.
 */
add_action( 'wp_footer', function () {
	?>
<script id="wcp-mobile-dock-js">
(function () {
	var bar = null, lastY = 0, ticking = false, min = false, away = false, started = false, moPending = false;
	// Açık çekmece sinyalleri (Woodmart off-canvas paneller açıkken .wd-opened alır):
	// burger menü (.mobile-nav), sepet (.cart-widget-side), giriş (.login-form-side), arama, mağaza filtreleri
	var OPEN_SEL = '.mobile-nav.wd-opened, .cart-widget-side.wd-opened, .login-form-side.wd-opened, .wd-search-full.wd-opened, .wd-search-dropdown.wd-opened, .widget-area.wd-opened, .wd-side-hidden.wd-opened';
	function dock() { if (!bar) bar = document.querySelector('.wd-toolbar'); return bar; }
	function drawerOpen() { return !!document.querySelector(OPEN_SEL); }
	function setMin(v) { var b = dock(); if (!b || v === min) return; min = v; b.classList.toggle('wcp-dock-min', v); }
	function setAway(v) { var b = dock(); if (!b || v === away) return; away = v; b.classList.toggle('wcp-dock-away', v); }
	function syncDrawer() { setAway(drawerOpen()); }
	function onScroll() {
		if (ticking) return; ticking = true;
		requestAnimationFrame(function () {
			ticking = false;
			if (drawerOpen()) { setAway(true); return; }   // çekmece açıksa gizli kalsın
			setAway(false);
			var y = window.pageYOffset || document.documentElement.scrollTop || 0;
			if (!started) { lastY = y; started = true; }
			var dy = y - lastY;
			if (y < 90) {                 // tepeye yakın -> her zaman tam dock
				setMin(false);
			} else if (dy > 7) {          // aşağı -> büz (sola, yalnız Menü)
				setMin(true);
			} else if (dy < -7) {         // yukarı -> aç (tüm butonlar)
				setMin(false);
			}
			lastY = y;
		});
	}
	function moSync() { if (moPending) return; moPending = true; requestAnimationFrame(function () { moPending = false; syncDrawer(); }); }
	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('load', onScroll);
	// Çekmece aç/kapa anında yakala (tıklama + DOM değişimi)
	document.addEventListener('click', function () { setTimeout(syncDrawer, 50); setTimeout(syncDrawer, 360); }, true);
	try {
		var mo = new MutationObserver(moSync);
		mo.observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
	} catch (e) {}
})();
</script>
	<?php
}, 110 );
