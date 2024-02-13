<?php
/**
 * Plugin Name: Store Tema UI
 * Description: Off-canvas paneller (giriş/sepet çekmecesi) ve header araçlarını yeni tasarım diline uyarlar + resmi Microsoft Surface animasyonu. Site geneli, tema güncellemesinden bağımsız.
 * Version: 1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_enqueue_scripts', function () {
	$path = WPMU_PLUGIN_DIR . '/assets/theme-ui.css';
	if ( file_exists( $path ) ) {
		wp_enqueue_style(
			'wcp-theme-ui',
			content_url( 'mu-plugins/assets/theme-ui.css' ),
			array(),
			filemtime( $path )
		);
	}
}, 100 );

/**
 * Off-canvas çekmecelere (boş sepet + giriş) resmi Microsoft Surface 360° animasyonunu
 * zarif şekilde enjekte eder. Çekmece açılınca oynar, kapanınca durur; sepet değişince yeniden eklenir.
 */
add_action( 'wp_footer', function () {
	$mp4    = content_url( 'mu-plugins/assets/wcp-surface-360.mp4' );
	$webm   = content_url( 'mu-plugins/assets/wcp-surface-360.webm' );
	$poster = content_url( 'mu-plugins/assets/wcp-surface-360-poster.jpg' );
	?>
<script id="wcp-anim-js">
(function () {
	var MP4 = <?php echo wp_json_encode( $mp4 ); ?>;
	var WEBM = <?php echo wp_json_encode( $webm ); ?>;
	var POSTER = <?php echo wp_json_encode( $poster ); ?>;

	function block(caption) {
		var html = '<div class="wcp-anim" aria-hidden="true">'
			+ '<div class="wcp-anim-stage">'
			+ '<video class="wcp-anim-v" muted loop playsinline preload="none" poster="' + POSTER + '">'
			+ '<source src="' + WEBM + '" type="video/webm">'
			+ '<source src="' + MP4 + '" type="video/mp4">'
			+ '</video></div>';
		if (caption) html += '<span class="wcp-anim-cap">' + caption + '</span>';
		return html + '</div>';
	}

	function inject() {
		// boş sepet
		document.querySelectorAll('.wd-empty-mini-cart').forEach(function (el) {
			if (el.querySelector('.wcp-anim')) return;
			el.classList.add('wcp-has-anim');
			el.insertAdjacentHTML('afterbegin', block(''));
		});
		// giriş çekmecesi
		var ls = document.querySelector('.login-form-side');
		if (ls && !ls.querySelector('.wcp-anim')) {
			var ca = ls.querySelector('.create-account-question');
			var html = block('Surface ailesini keşfedin');
			if (ca) ca.insertAdjacentHTML('beforebegin', html);
			else ls.insertAdjacentHTML('beforeend', html);
		}
	}

	function toggle() {
		document.querySelectorAll('.login-form-side, .cart-widget-side').forEach(function (d) {
			var v = d.querySelector('.wcp-anim-v'); if (!v) return;
			var open = d.classList.contains('wd-opened');
			if (open) { if (v.paused) { try { v.play(); } catch (e) {} } }
			else { if (!v.paused) { try { v.pause(); } catch (e) {} } }
		});
	}

	var t;
	function run() { clearTimeout(t); t = setTimeout(function () { inject(); toggle(); }, 120); }

	if (document.readyState !== 'loading') run(); else document.addEventListener('DOMContentLoaded', run);
	document.addEventListener('click', function () { setTimeout(run, 320); }, true);
	if (window.jQuery) {
		jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded updated_wc_div', run);
	}
	// çekmece aç/kapa + sepet yeniden render için gözlemci
	try {
		var mo = new MutationObserver(run);
		['.login-form-side', '.cart-widget-side'].forEach(function (s) {
			var el = document.querySelector(s);
			if (el) mo.observe(el, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
		});
	} catch (e) {}
})();
</script>
	<?php
}, 100 );
