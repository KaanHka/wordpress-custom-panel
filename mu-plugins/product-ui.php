<?php
/**
 * Plugin Name: STORE Ürün Sayfası UI
 * Description: Ürün sayfası tasarım öğeleri — kategori bazlı özellik ikonları, Microsoft Solutions Partner güven bandı, kargo & garanti kutuları. Tüm ürünlerde.
 * Author: STORE
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCP_Product_UI {

	public static function init() {
		// Özellik ikonları — summary'de (fiyat ile sepet arası)
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_features' ), 25 );
		// Microsoft Solutions Partner güven bandı — summary'de render, JS ile full-width'e taşınır
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_trust' ), 58 );
		// Kargo & Garanti kutuları — summary'de render, JS ile sidebar kolonuna taşınır
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_sidebar' ), 56 );
		add_action( 'wp_footer', array( __CLASS__, 'relocate_js' ), 100 );
		add_action( 'wp_head', array( __CLASS__, 'assets' ), 51 );
	}

	public static function ms_logo() {
		return '<svg viewBox="0 0 23 23" width="22" height="22"><rect x="1" y="1" width="10" height="10" fill="#f25022"/><rect x="12" y="1" width="10" height="10" fill="#7fba00"/><rect x="1" y="12" width="10" height="10" fill="#00a4ef"/><rect x="12" y="12" width="10" height="10" fill="#ffb900"/></svg>';
	}

	public static function trust_items() {
		return array(
			array( 'logo', 'Microsoft Solutions Partner', 'Yetkili İş Ortağı' ),
			array( 'users', '1000+ Kurumsal Müşteri', 'Güvenle Tercih Edildi' ),
			array( 'ship', 'Aynı Gün Kargo', 'Hızlı ve Güvenli Teslimat' ),
			array( 'original', 'Orijinal Microsoft Ürünü', 'Türkiye Garantili' ),
			array( 'support', '7/24 Destek', 'Uzman Ekibimiz Yanınızda' ),
		);
	}

	public static function render_trust() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		echo '<div class="wcp-msband"><div class="wcp-msband-in">';
		foreach ( self::trust_items() as $it ) {
			$ic = $it[0] === 'logo' ? self::ms_logo() : self::svg( $it[0] );
			echo '<div class="wcp-msitem"><span class="wcp-msic' . ( $it[0] === 'logo' ? ' is-logo' : '' ) . '">' . $ic . '</span><span class="wcp-mstx"><b>' . esc_html( $it[1] ) . '</b><small>' . esc_html( $it[2] ) . '</small></span></div>';
		}
		echo '</div></div>';
	}

	/* Resmi + dini tatil listesi (Y-m-d) — bayram tarihleri yaklaşık, filtre ile güncellenebilir */
	public static function holidays() {
		$y = (int) current_time( 'Y' );
		$out = array();
		foreach ( array( $y, $y + 1 ) as $yy ) {
			foreach ( array( '01-01', '04-23', '05-01', '05-19', '07-15', '08-30', '10-29' ) as $md ) { $out[] = $yy . '-' . $md; }
		}
		$out = array_merge( $out, array(
			'2027-03-10', '2027-03-11', '2027-03-12',                // Ramazan Bayramı 2027 (yaklaşık)
			'2027-05-16', '2027-05-17', '2027-05-18', '2027-05-19',  // Kurban Bayramı 2027 (yaklaşık)
		) );
		return apply_filters( 'wcp_delivery_holidays', $out );
	}

	/* Kurallara göre tahmini teslim gününü hesapla → "Salı, 30 Haziran" */
	public static function estimated_delivery() {
		$tz  = wp_timezone();
		try { $now = new DateTime( 'now', $tz ); } catch ( Exception $e ) { return 'Yarın kapınızda'; }
		$hol = self::holidays();
		$isbiz = function ( $dt ) use ( $hol ) {
			if ( (int) $dt->format( 'N' ) >= 6 ) { return false; }            // Cmt(6)/Paz(7)
			if ( in_array( $dt->format( 'Y-m-d' ), $hol, true ) ) { return false; } // tatil
			return true;
		};
		$nextbiz = function ( $dt ) use ( $isbiz ) {
			$d = clone $dt; $d->modify( '+1 day' );
			$g = 0; while ( ! $isbiz( $d ) && $g < 90 ) { $d->modify( '+1 day' ); $g++; }
			return $d;
		};
		$cutoff = (int) apply_filters( 'wcp_delivery_cutoff_hour', 16 );
		if ( ! $isbiz( $now ) || (int) $now->format( 'G' ) >= $cutoff ) {
			$process = $nextbiz( $now );   // hafta sonu / tatil / 17:00 sonrası → sonraki iş günü işlenir
		} else {
			$process = clone $now;         // hafta içi, 17:00 öncesi → bugün işlenir
		}
		$delivery = $nextbiz( $process );  // teslim = işlem gününden sonraki ilk iş günü
		$gunler = array( 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar' );
		$aylar  = array( '', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
		return $gunler[ (int) $delivery->format( 'N' ) - 1 ] . ', ' . (int) $delivery->format( 'j' ) . ' ' . $aylar[ (int) $delivery->format( 'n' ) ];
	}

	public static function render_sidebar() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		echo '<div class="wcp-sidebox-wrap">';
		// Kargo ve Teslimat
		echo '<div class="wcp-sidebox"><div class="wcp-sbhead">' . self::svg( 'ship' ) . '<span>Kargo ve Teslimat</span></div><div class="wcp-sblist">';
		$kargo = array(
			array( 'store', 'Mağazadan Teslim', 'Mağazadan hemen teslim, beklemeden alın' ),
			array( 'ship', 'Aynı Gün Kargo', "16:00'a kadar siparişler aynı gün kargoda" ),
			array( 'free', 'Ücretsiz Kargo', "Tüm Türkiye'ye ücretsiz teslimat" ),
			array( 'clock', 'Tahmini Teslimat', self::estimated_delivery() . ' kapınızda' ),
		);
		foreach ( $kargo as $k ) {
			echo '<div class="wcp-sbrow"><span class="wcp-sbic">' . self::svg( $k[0] ) . '</span><span class="wcp-sbtx"><b>' . esc_html( $k[1] ) . '</b><small>' . esc_html( $k[2] ) . '</small></span></div>';
		}
		echo '</div></div>';
		// Garanti ve Güvence
		echo '<div class="wcp-sidebox"><div class="wcp-sbhead">' . self::svg( 'shield' ) . '<span>Garanti ve Güvence</span></div><div class="wcp-sblist">';
		foreach ( array( '2 Yıl Resmi Garanti · Türkiye Garantili', 'Orijinal Microsoft Ürünü', 'Faturalı ve Kurumsal Satış' ) as $g ) {
			echo '<div class="wcp-sbrow wcp-sbcheck"><span class="wcp-sbchk">' . self::svg( 'compat' ) . '</span><span>' . esc_html( $g ) . '</span></div>';
		}
		echo '</div><a class="wcp-sblink" href="' . esc_url( home_url( '/garanti-ve-servis-bilgileri' ) ) . '">Detaylı Bilgi <span>&rarr;</span></a></div>';
		echo '</div>';
	}

	public static function relocate_js() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		?>
<script>
(function(){
	function fullRowOf(node){ var product=document.querySelector('.single-product-page.product')||document.querySelector('div.product.entry-content')||document.querySelector('div.product'); if(!product)return null; var pw=product.getBoundingClientRect().width; if(!pw)return null; var n=node; while(n && n!==product){ if(n.getBoundingClientRect().width>=pw*0.9){ return n; } n=n.parentElement; } return null; }
	function moveSidebar(){
		var sb=document.querySelector('.wcp-sidebox-wrap'); if(!sb||sb.getAttribute('data-wcpmoved')) return;
		var gallery=document.querySelector('.woocommerce-product-gallery'); if(!gallery) return;
		var product=document.querySelector('.single-product-page.product')||document.querySelector('div.product'); var pw=product?product.getBoundingClientRect().width:0;
		var hostW=sb.parentElement?sb.parentElement.getBoundingClientRect().width:0;
		if(pw && hostW>=pw*0.8){ sb.setAttribute('data-wcpmoved','1'); return; } // mobil: sidebar yok, summary akışında kalsın
		var fullRow=fullRowOf(gallery); if(!fullRow) return;
		var galChild=gallery; while(galChild && galChild.parentElement!==fullRow){ galChild=galChild.parentElement; }
		if(!galChild) return;
		var col=null;
		[].forEach.call(fullRow.children,function(c){ if(c===galChild||c===sb) return; var w=c.getBoundingClientRect().width; if(w>=240 && w<=640){ col=c; } });
		sb.setAttribute('data-wcpmoved','1');
		if(col){ // mevcut sidebar kolonu var → içini temizle, kutuları koy
			[].forEach.call(col.children,function(c){ if(c!==sb && !c.classList.contains('wcp-sidebox-wrap')){ c.style.display='none'; } });
			col.appendChild(sb);
		} else { // sidebar kolonu yok (kaldırılmış) → full-width satıra SAĞ kolon olarak ekle
			try{ var cs=getComputedStyle(fullRow); if(cs.display.indexOf('flex')<0){ fullRow.style.display='flex'; fullRow.style.flexWrap='wrap'; fullRow.style.alignItems='flex-start'; } }catch(e){}
			sb.classList.add('wcp-asright');
			fullRow.appendChild(sb);
		}
	}
	function move(){
		var el=document.querySelector('.wcp-msband'); if(!el||el.getAttribute('data-wcpmoved')) return;
		var product=document.querySelector('.single-product-page.product')||document.querySelector('div.product.entry-content')||document.querySelector('div.product'); if(!product) return;
		var pw=product.getBoundingClientRect().width; if(!pw) return;
		var hostW=el.parentElement?el.parentElement.getBoundingClientRect().width:0;
		if(hostW>=pw*0.8){ el.setAttribute('data-wcpmoved','1'); return; } // mobil: zaten tam genişlik
		var node=el, full=null;
		while(node && node!==product){ if(node.getBoundingClientRect().width>=pw*0.9){ full=node; break; } node=node.parentElement; }
		if(full && full.parentNode){ el.setAttribute('data-wcpmoved','1'); var gb=document.querySelector('.wcp-gb[data-wcpmoved]'); var ref=gb||full; ref.parentNode.insertBefore(el, ref.nextSibling); }
	}
	function all(){ try{ moveSidebar(); }catch(e){} try{ move(); }catch(e){} }
	if(document.readyState!=='loading'){ all(); } else { document.addEventListener('DOMContentLoaded',all); }
	setTimeout(all,400); setTimeout(all,1200); setTimeout(all,2500);
	// fallback: taşınamadıysa yine de göster (gizli kalmasın)
	setTimeout(function(){ ['.wcp-msband','.wcp-sidebox-wrap'].forEach(function(s){ var e=document.querySelector(s); if(e&&!e.getAttribute('data-wcpmoved')) e.setAttribute('data-wcpmoved','1'); }); },4000);
})();
</script>
		<?php
	}

	/* En spesifik kategoriden en geneline doğru sıralı — ürün ilk eşleşen setini alır */
	public static function category_features() {
		return array(
			'surface-pro-10'            => array( array( 'copilot', 'Copilot+ PC' ), array( 'ai', 'Yapay Zeka Destekli' ), array( 'npu', '45 TOPS NPU' ), array( 'battery', 'Tüm Gün Pil' ), array( 'touch', 'Dokunmatik Ekran' ) ),
			'surface-pro-copilot'       => array( array( 'copilot', 'Copilot+ PC' ), array( 'ai', 'Yapay Zeka Destekli' ), array( 'npu', '45 TOPS NPU' ), array( 'battery', 'Tüm Gün Pil' ), array( 'touch', 'Dokunmatik Ekran' ) ),
			'surface-laptop-copilot-pc' => array( array( 'copilot', 'Copilot+ PC' ), array( 'ai', 'Yapay Zeka' ), array( 'npu', '45 TOPS NPU' ), array( 'keyboard', 'Tam Klavye' ), array( 'battery', 'Uzun Pil Ömrü' ) ),
			'surface-pro'               => array( array( 'touch', 'Dokunmatik Ekran' ), array( 'pen', 'Kalem Desteği' ), array( 'portable', 'İnce & Taşınabilir' ), array( 'windows', 'Windows 11' ), array( 'battery', 'Uzun Pil' ) ),
			'klavye'                    => array( array( 'magnet', 'Manyetik Bağlantı' ), array( 'keyboard', 'İngilizce Q Klavye' ), array( 'compat', 'Surface Uyumlu' ), array( 'original', 'Orijinal Ürün' ) ),
			'mouse'                     => array( array( 'bluetooth', 'Bluetooth' ), array( 'ergo', 'Ergonomik' ), array( 'compat', 'Surface Uyumlu' ), array( 'original', 'Orijinal Ürün' ) ),
			'kalem'                     => array( array( 'pen', 'Basınç Hassasiyeti' ), array( 'magnet', 'Manyetik' ), array( 'charge', 'Şarj Edilebilir' ), array( 'compat', 'Surface Uyumlu' ) ),
			'baglanti-istasyonu-dock'   => array( array( 'ports', 'Çoklu Port' ), array( 'charge', 'Hızlı Şarj' ), array( 'plug', 'Tak & Çalıştır' ), array( 'compat', 'Surface Uyumlu' ) ),
			'bagdastirici'              => array( array( 'charge', 'Orijinal Şarj' ), array( 'plug', 'Tak & Çalıştır' ), array( 'compat', 'Surface Uyumlu' ) ),
			'surface-aksesuar'          => array( array( 'compat', 'Surface Uyumlu' ), array( 'original', 'Orijinal Ürün' ), array( 'shield', '2 Yıl Garanti' ) ),
			'surface'                   => array( array( 'touch', 'Dokunmatik' ), array( 'windows', 'Windows 11' ), array( 'original', 'Orijinal Microsoft' ), array( 'shield', '2 Yıl Garanti' ) ),
		);
	}

	public static function default_features() {
		return array( array( 'original', 'Orijinal Ürün' ), array( 'shield', '2 Yıl Garanti' ), array( 'ship', 'Aynı Gün Kargo' ), array( 'support', '7/24 Destek' ) );
	}

	public static function features_for_product( $pid ) {
		$map  = self::category_features();
		$slugs = wc_get_product_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
		if ( is_array( $slugs ) ) {
			foreach ( array_keys( $map ) as $slug ) { // config sırası = öncelik (en spesifik üstte)
				if ( in_array( $slug, $slugs, true ) ) { return $map[ $slug ]; }
			}
		}
		return self::default_features();
	}

	/* Basit, çizgisel inline SVG ikon seti (front-end FA bağımlılığı olmadan) */
	public static function svg( $key ) {
		$p = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">';
		$e = '</svg>';
		$paths = array(
			'copilot'  => '<path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8z"/><circle cx="18" cy="17" r="2.4"/>',
			'ai'       => '<path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9z"/><path d="M6 17l.9 2.1L9 20l-2.1.9L6 23l-.9-2.1L3 20l2.1-.9z"/>',
			'npu'      => '<rect x="7" y="7" width="10" height="10" rx="1.5"/><path d="M10 7V4M14 7V4M10 20v-3M14 20v-3M7 10H4M7 14H4M20 10h-3M20 14h-3"/>',
			'battery'  => '<rect x="3" y="8" width="15" height="8" rx="2"/><path d="M21 11v2"/><path d="M6 12h7"/>',
			'touch'    => '<path d="M9 11V6.5a1.5 1.5 0 013 0V11"/><path d="M12 11V8a1.5 1.5 0 013 0v3"/><path d="M15 11.5a1.5 1.5 0 013 0V14c0 3-2 6-5 6h-1c-2 0-3-1-4-3l-2-3.5a1.4 1.4 0 012.3-1.6L9 12"/>',
			'pen'      => '<path d="M15.5 4.5l4 4L8 20l-5 1 1-5z"/><path d="M13.5 6.5l4 4"/>',
			'keyboard' => '<rect x="3" y="7" width="18" height="11" rx="2"/><path d="M7 11h.01M11 11h.01M15 11h.01M8 14.5h8"/>',
			'bluetooth'=> '<path d="M7 7l10 10-5 4V3l5 4L7 17"/>',
			'windows'  => '<rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/>',
			'charge'   => '<path d="M13 3L5 13h6l-1 8 8-11h-6z"/>',
			'ports'    => '<rect x="4" y="9" width="16" height="9" rx="1.5"/><path d="M8 9V6M16 9V6M9 13h6"/>',
			'plug'     => '<path d="M9 3v6M15 3v6"/><path d="M7 9h10v2a5 5 0 01-10 0z"/><path d="M12 16v5"/>',
			'magnet'   => '<path d="M6 4v7a6 6 0 0012 0V4"/><path d="M6 8h4M14 8h4"/>',
			'ergo'     => '<path d="M12 3a5 5 0 015 5v6a5 5 0 01-10 0V8a5 5 0 015-5z"/><path d="M12 3v7"/>',
			'compat'   => '<path d="M20 6L9 17l-5-5"/>',
			'original' => '<path d="M12 2l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 14.4 7.2 16.9l.9-5.4L4.2 7.7l5.4-.8z"/>',
			'shield'   => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
			'ship'     => '<rect x="2" y="7" width="11" height="9" rx="1"/><path d="M13 10h4l4 4v2h-8z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
			'support'  => '<path d="M4 13a8 8 0 0116 0"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19a4 4 0 01-4 3h-2"/>',
			'portable' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M10 18h4"/>',
			'users'    => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0112 0"/><path d="M16 6a3 3 0 010 6M21 20a6 6 0 00-4-5.6"/>',
			'store'    => '<path d="M4 10h16v10H4z"/><path d="M3 10l1.4-5h15.2L21 10z"/><path d="M9 20v-5h6v5"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 1.8"/>',
			'free'     => '<path d="M3 8h18v4H3z"/><path d="M5 12v9h14v-9"/><path d="M12 8V21"/><path d="M12 8S10 3 7.5 4 9 8 12 8zM12 8s2-5 4.5-4S15 8 12 8z"/>',
		);
		$d = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['original'];
		return $p . $d . $e;
	}

	public static function render_features() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		$pid = get_queried_object_id();
		if ( ! $pid ) { return; }
		$feats = self::features_for_product( $pid );
		if ( empty( $feats ) ) { return; }
		echo '<div class="wcp-feats">';
		foreach ( $feats as $f ) {
			echo '<div class="wcp-feat"><span class="wcp-feat-ic">' . self::svg( $f[0] ) . '</span><span class="wcp-feat-lb">' . esc_html( $f[1] ) . '</span></div>';
		}
		echo '</div>';
	}

	public static function assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		?>
<style id="wcp-produi-css">
/* taşınana kadar gizle (flicker/zıplama önleme) */
.wcp-msband:not([data-wcpmoved]),.wcp-sidebox-wrap:not([data-wcpmoved]){display:none!important;}
.wcp-feats{display:flex;flex-wrap:wrap;gap:6px;margin:16px 0;}
.wcp-feat{flex:1 1 60px;min-width:58px;max-width:100px;display:flex;flex-direction:column;align-items:center;gap:5px;text-align:center;padding:9px 4px;border:1px solid #e9edf3;border-radius:12px;background:linear-gradient(180deg,#fff,#f6f9fd);transition:border-color .15s,box-shadow .15s,transform .1s;}
.wcp-feat:hover{border-color:#bcd4f5;box-shadow:0 6px 16px rgba(11,99,214,.08);transform:translateY(-2px);}
.wcp-feat-ic{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#0b63d6,#1aa0ff);display:flex;align-items:center;justify-content:center;color:#fff;flex:0 0 auto;}
.wcp-feat-ic svg{width:17px;height:17px;}
.wcp-feat-lb{font-size:10px;font-weight:700;color:#2a3340;line-height:1.2;}
/* Microsoft Solutions Partner güven bandı */
.wcp-msband{border:1px solid #e6e9ef;border-radius:14px;background:#fff;box-shadow:0 6px 22px rgba(16,24,40,.05);margin:14px 0;padding:6px;container-type:inline-size;}
.wcp-msband-in{display:flex;flex-wrap:wrap;align-items:stretch;}
.wcp-msitem{flex:1 1 0;min-width:150px;display:flex;align-items:center;gap:11px;padding:13px 16px;border-right:1px solid #eef1f6;}
.wcp-msitem:last-child{border-right:0;}
.wcp-msic{width:38px;height:38px;flex:0 0 auto;border-radius:10px;background:linear-gradient(135deg,#eef4fd,#dcebfd);display:flex;align-items:center;justify-content:center;color:#0b63d6;}
.wcp-msic svg{width:21px;height:21px;}
.wcp-msic.is-logo{background:#fff;border:1px solid #e6e9ef;}
.wcp-mstx{display:flex;flex-direction:column;min-width:0;}
.wcp-mstx b{font-size:12.5px;font-weight:800;color:#172033;line-height:1.2;}
.wcp-mstx small{font-size:11px;color:#7a828f;margin-top:2px;}
@container (max-width:760px){.wcp-msitem{flex:1 1 46%;border-right:0;}}
@media (max-width:560px){.wcp-msitem{flex:1 1 100%;min-width:0;}}
/* Kargo & Garanti sidebar kutuları */
.wcp-sidebox-wrap{display:flex;flex-direction:column;gap:14px;}
.wcp-sidebox-wrap.wcp-asright{flex:0 0 360px;max-width:360px;align-self:flex-start;margin-left:auto;}
@media (max-width:1024px){.wcp-sidebox-wrap.wcp-asright{flex-basis:100%;max-width:none;margin-left:0;}}
.wcp-sidebox{border:1px solid #e6e9ef;border-radius:14px;background:#fff;box-shadow:0 6px 22px rgba(16,24,40,.05);overflow:hidden;}
.wcp-sbhead{display:flex;align-items:center;gap:9px;padding:13px 16px;border-bottom:1px solid #eef1f6;background:linear-gradient(180deg,#f7faff,#fff);font-weight:800;font-size:14px;color:#172033;}
.wcp-sbhead svg{width:20px;height:20px;color:#0b63d6;}
.wcp-sblist{padding:11px 16px;display:flex;flex-direction:column;gap:11px;}
.wcp-sbrow{display:flex;align-items:flex-start;gap:11px;}
.wcp-sbic{width:32px;height:32px;flex:0 0 auto;border-radius:9px;background:linear-gradient(135deg,#eef4fd,#dcebfd);display:flex;align-items:center;justify-content:center;color:#0b63d6;}
.wcp-sbic svg{width:17px;height:17px;}
.wcp-sbtx{display:flex;flex-direction:column;}
.wcp-sbtx b{font-size:12.5px;font-weight:700;color:#1d2735;line-height:1.25;}
.wcp-sbtx small{font-size:11px;color:#7a828f;margin-top:1px;line-height:1.3;}
.wcp-sbcheck{align-items:center;}
.wcp-sbchk{width:20px;height:20px;flex:0 0 auto;border-radius:50%;background:#16a34a;display:flex;align-items:center;justify-content:center;color:#fff;}
.wcp-sbchk svg{width:12px;height:12px;stroke-width:2.6;}
.wcp-sbcheck > span:last-child{font-size:12.5px;font-weight:600;color:#2a3340;line-height:1.3;}
.wcp-sblink{display:flex;align-items:center;justify-content:center;gap:6px;margin:0 16px 14px;padding:9px 14px;border:1px solid #d7e3f7;border-radius:9px;background:#f4f8ff;color:#0b63d6!important;font-size:12.5px;font-weight:700;text-decoration:none!important;transition:background .14s,border-color .14s;}
.wcp-sblink:hover{background:#0b63d6;border-color:#0b63d6;color:#fff!important;}
.wcp-sblink span{font-size:14px;line-height:1;}
</style>
		<?php
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) { WCP_Product_UI::init(); }
}, 20 );
