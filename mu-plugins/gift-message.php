<?php
/**
 * Plugin Name: STORE Hediye / Bundle
 * Description: Ürün alınınca otomatik ücretsiz hediye (STORE ÖZEL HEDİYE). Ürün-başına / kategori / tutar-eşiği senaryoları, sepete 0 TL kalem olarak otomatik eklenir.
 * Author: STORE
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCP_Gift {

	const META = '_wcp_gift_ids';        // ürün-başına hediye ürün ID dizisi
	const OPT  = 'wcp_gift_settings';     // global ayarlar
	const FLAG = 'wcp_gift';              // sepet kalemi işareti

	protected static $syncing = false;

	public static function init() {
		// Sepet senkronu (hediyeleri ekle/çıkar) — sepet değişimlerinde
		foreach ( array( 'woocommerce_add_to_cart', 'woocommerce_cart_item_removed', 'woocommerce_cart_item_restored', 'woocommerce_after_cart_item_quantity_update', 'woocommerce_cart_loaded_from_session', 'woocommerce_applied_coupon', 'woocommerce_removed_coupon' ) as $h ) {
			add_action( $h, array( __CLASS__, 'sync_gifts' ), 20 );
		}
		// Hediye kalemlerinin fiyatını 0 yap
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'zero_prices' ), 20 );
		// Hediye kalemi görünümü (sepet/checkout)
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'item_name' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'item_qty' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( __CLASS__, 'item_remove' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_class', array( __CLASS__, 'item_class' ), 10, 3 );
		// Sipariş kalemine hediye işareti taşı
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 4 );
		// Görsel blok: kısa kod + ürün sayfasına otomatik yerleştirme + stil
		add_shortcode( 'wcp_gift_bundle', array( __CLASS__, 'shortcode_bundle' ) );
		// Render (Woodmart yalnız summary hook'unu tetikliyor) — JS sonra tam-genişliğe taşır
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_on_product' ), 55 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'render_on_product' ), 8 );
		add_action( 'wp_head', array( __CLASS__, 'assets' ), 50 );
		add_action( 'wp_footer', array( __CLASS__, 'relocate_js' ), 99 );
	}

	/* ---------------- ayarlar ---------------- */
	public static function settings() {
		$d = array(
			'enabled'          => 1,
			'per_product'      => 1,
			'by_category'      => 0,
			'by_threshold'     => 0,
			'cat_map'          => array(),   // [cat_term_id => [gift_pid,...]]
			'threshold_amount' => 0,
			'threshold_gift'   => 0,
			'title'            => 'STORE ÖZEL HEDİYE',
		);
		$s = get_option( self::OPT, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), $d );
	}

	/* Bir ürün için tanımlı hediye ID'leri (ürün-başına + kategori) */
	public static function gifts_for_product( $pid ) {
		$s = self::settings();
		$ids = array();
		if ( ! empty( $s['per_product'] ) ) {
			$m = get_post_meta( $pid, self::META, true );
			if ( is_array( $m ) ) { $ids = array_merge( $ids, $m ); }
		}
		if ( ! empty( $s['by_category'] ) && ! empty( $s['cat_map'] ) ) {
			$cats = wc_get_product_term_ids( $pid, 'product_cat' );
			foreach ( $s['cat_map'] as $cat_id => $gift_ids ) {
				if ( in_array( (int) $cat_id, (array) $cats, true ) ) { $ids = array_merge( $ids, (array) $gift_ids ); }
			}
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/* Sepet için hak edilen tüm hediye ID'leri */
	public static function gifts_for_cart( $cart ) {
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) { return array(); }
		$gifts = array();
		$subtotal = 0.0;
		$bought = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::FLAG ] ) ) { continue; } // hediye kaleminin kendisi sayılmaz
			$pid = (int) $item['product_id'];
			$bought[ $pid ] = true;
			if ( isset( $item['data'] ) && is_object( $item['data'] ) ) {
				$subtotal += (float) $item['data']->get_price() * (int) $item['quantity'];
			}
			foreach ( self::gifts_for_product( $pid ) as $g ) { $gifts[ $g ] = true; }
		}
		if ( ! empty( $s['by_threshold'] ) && (int) $s['threshold_gift'] > 0 && (float) $s['threshold_amount'] > 0 ) {
			if ( $subtotal >= (float) $s['threshold_amount'] ) { $gifts[ (int) $s['threshold_gift'] ] = true; }
		}
		// Hediye ürünün kendisi ana ürün olarak sepetteyse hediye verme (çakışma önle)
		foreach ( array_keys( $gifts ) as $gid ) { if ( isset( $bought[ $gid ] ) ) { unset( $gifts[ $gid ] ); } }
		return array_keys( $gifts );
	}

	/* ---------------- sepet senkronu ---------------- */
	public static function sync_gifts() {
		if ( self::$syncing ) { return; }
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { return; }
		self::$syncing = true;
		$cart = WC()->cart;
		$want = self::gifts_for_cart( $cart );
		$have = array();
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item[ self::FLAG ] ) ) { $have[ (int) $item['product_id'] ] = $key; }
		}
		// fazla hediyeleri kaldır
		foreach ( $have as $gid => $key ) {
			if ( ! in_array( $gid, $want, true ) ) { $cart->remove_cart_item( $key ); }
		}
		// eksik hediyeleri ekle (0 TL)
		foreach ( $want as $gid ) {
			if ( ! isset( $have[ $gid ] ) ) {
				$prod = wc_get_product( $gid );
				if ( $prod && $prod->exists() && 'publish' === get_post_status( $gid ) ) {
					$cart->add_to_cart( $gid, 1, 0, array(), array( self::FLAG => '1' ) );
				}
			}
		}
		self::$syncing = false;
	}

	public static function zero_prices( $cart ) {
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) { return; }
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item[ self::FLAG ] ) && isset( $item['data'] ) && is_object( $item['data'] ) ) {
				$item['data']->set_price( 0 );
			}
		}
	}

	/* ---------------- hediye kalemi görünümü ---------------- */
	public static function item_name( $name, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item[ self::FLAG ] ) ) {
			$name = '<span class="wcp-gift-tag">🎁 Hediye</span> ' . $name;
		}
		return $name;
	}
	public static function item_price( $price, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item[ self::FLAG ] ) ) { return '<span class="wcp-gift-free">Ücretsiz</span>'; }
		return $price;
	}
	public static function item_qty( $qty_html, $cart_item_key, $cart_item ) {
		if ( ! empty( $cart_item[ self::FLAG ] ) ) { return '1'; }
		return $qty_html;
	}
	public static function item_remove( $link, $cart_item_key ) {
		$cart = WC()->cart ? WC()->cart->get_cart() : array();
		if ( isset( $cart[ $cart_item_key ] ) && ! empty( $cart[ $cart_item_key ][ self::FLAG ] ) ) { return ''; }
		return $link;
	}
	public static function item_class( $class, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item[ self::FLAG ] ) ) { $class .= ' wcp-gift-row'; }
		return $class;
	}
	public static function order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values[ self::FLAG ] ) ) {
			$item->add_meta_data( '_wcp_gift', '1', true );
			$item->add_meta_data( 'Hediye', 'STORE Özel Hediye', true );
		}
	}

	/* ---------------- görsel blok (STORE ÖZEL HEDİYE) ---------------- */
	public static function shortcode_bundle( $atts ) {
		$pid = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : 0;
		if ( ! $pid && function_exists( 'is_product' ) && is_product() ) { $pid = get_queried_object_id(); }
		return self::bundle_html( $pid );
	}

	public static function render_on_product() {
		static $done = false;
		if ( $done ) { return; }
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		$html = self::bundle_html( get_queried_object_id() );
		if ( '' === $html ) { return; }
		$done = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* Paket içeriği etiketleri: klavye→"Klavye + Kalem", kalem→"Slim Pen", ana ürün→kısa ad */
	public static function bundle_label( $p, $is_main = false ) {
		$slugs = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'slugs' ) );
		$slugs = is_array( $slugs ) ? $slugs : array();
		$title = $p->get_name();
		if ( ! $is_main ) {
			if ( in_array( 'klavye', $slugs, true ) || mb_stripos( $title, 'Klavye' ) !== false ) {
				$has_pen = ( mb_stripos( $title, 'Slim Pen' ) !== false || mb_stripos( $title, 'Kalem' ) !== false );
				return $has_pen ? 'Klavye + Kalem' : 'Klavye';
			}
			if ( in_array( 'kalem', $slugs, true ) || mb_stripos( $title, 'Slim Pen' ) !== false || mb_stripos( $title, 'Kalem' ) !== false ) { return 'Slim Pen'; }
		}
		$clean = preg_replace( '/^(Micro?soft|Microosft)\s*[–-]\s*/iu', '', $title );
		return wp_trim_words( $clean, $is_main ? 3 : 4, '' );
	}

	/* Hediye yoksa "set içeriği" rozetleri (paket içeriğinden türetilir) */
	public static function set_checks( $bundle_objs ) {
		$out = array();
		foreach ( $bundle_objs as $bp ) {
			$lbl = self::bundle_label( $bp );
			if ( $lbl === 'Klavye + Kalem' ) { $out[] = 'Klavye Dahil'; $out[] = 'Slim Pen Dahil'; }
			elseif ( $lbl === 'Klavye' ) { $out[] = 'Klavye Dahil'; }
			elseif ( $lbl === 'Slim Pen' ) { $out[] = 'Slim Pen Dahil'; }
			else { $out[] = $lbl . ' Dahil'; }
		}
		$out[] = 'Orijinal Microsoft Ürünü';
		$out[] = '2 Yıl Garanti';
		return array_values( array_unique( $out ) );
	}

	/* Slim Pen görseli (kalem kategorisindeki bir üründen) — yoksa boş döner */
	public static function pen_image() {
		static $cache = null;
		if ( null !== $cache ) { return $cache; }
		$cache = '';
		$q = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array( 'kalem', 'slim-pen', 'surface-kalem' ) ) ),
		) );
		if ( $q && has_post_thumbnail( $q[0] ) ) {
			$cache = (string) wp_get_attachment_image_url( get_post_thumbnail_id( $q[0] ), 'thumbnail' );
		}
		return $cache;
	}

	public static function bundle_html( $pid ) {
		if ( ! $pid || ! function_exists( 'wc_get_product' ) ) { return ''; }
		$main = wc_get_product( $pid );
		if ( ! $main ) { return ''; }
		$gifts = self::gifts_for_product( $pid );
		$s = self::settings();

		$gift_objs = array();
		$gift_total = 0.0;
		foreach ( $gifts as $gid ) {
			$g = wc_get_product( $gid );
			if ( ! $g ) { continue; }
			$reg = (float) get_post_meta( $gid, '_regular_price', true );
			if ( ! $reg ) { $reg = (float) $g->get_price(); }
			$gift_objs[] = array( 'p' => $g, 'reg' => $reg );
			$gift_total += $reg;
		}

		// Paket içeriği — "yanındaki ürünler" (görsel; klavye/kalem vb.)
		$bundle_objs = array();
		foreach ( array_map( 'intval', (array) get_post_meta( $pid, '_wcp_bundle_ids', true ) ) as $bid ) {
			$bp = $bid ? wc_get_product( $bid ) : false;
			if ( $bp ) { $bundle_objs[] = $bp; }
		}

		$has_gift = ! empty( $gift_objs );
		if ( ! $has_gift && empty( $bundle_objs ) ) { return ''; } // ne hediye ne paket → gösterme

		$main_price = (float) $main->get_price();
		$pkg_total  = $main_price + $gift_total;
		$mimg = $main->get_image_id() ? wp_get_attachment_image_url( $main->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src();

		// Fiyat değerleri (regular_price, yoksa price)
		$pval = function ( $p ) { $v = (float) get_post_meta( $p->get_id(), '_regular_price', true ); if ( ! $v ) { $v = (float) $p->get_price(); } return $v; };
		$bundle_value = 0.0;
		foreach ( $bundle_objs as $bp ) { $bundle_value += $pval( $bp ); }
		$sep_total = $main_price + $bundle_value;   // ayrı ayrı alınsa (set için)

		// Paket görsel öğeleri (cihaz + klavye + slim pen ayrı + hediye)
		$pen_img = '';
		$pack    = array();
		$pack[]  = array( 'img' => $mimg, 'label' => self::bundle_label( $main, true ), 'price' => wc_price( $main_price ), 'cls' => '' );
		foreach ( $bundle_objs as $bp ) {
			$bi  = $bp->get_image_id() ? wp_get_attachment_image_url( $bp->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src();
			$lbl = self::bundle_label( $bp );
			$bv  = $pval( $bp );
			if ( 'Klavye + Kalem' === $lbl ) {
				$pack[] = array( 'img' => $bi, 'label' => 'Klavye', 'price' => wc_price( $bv ), 'cls' => '' );
				if ( '' === $pen_img ) { $pen_img = self::pen_image(); }
				$pack[] = array( 'img' => $pen_img ? $pen_img : $bi, 'label' => 'Slim Pen', 'price' => '__incl__', 'cls' => 'wcp-gb-pi-pen' );
			} else {
				$pack[] = array( 'img' => $bi, 'label' => $lbl, 'price' => wc_price( $bv ), 'cls' => '' );
			}
		}
		foreach ( $gift_objs as $go ) {
			$pi     = $go['p']->get_image_id() ? wp_get_attachment_image_url( $go['p']->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src();
			$pack[] = array( 'img' => $pi, 'label' => wp_trim_words( $go['p']->get_name(), 4, '' ), 'price' => '__gift__', 'cls' => 'wcp-gb-pi-gift' );
		}

		// Değer kutusu onay maddeleri (2 kolon)
		$checks = array();
		foreach ( $bundle_objs as $bp ) {
			$lbl = self::bundle_label( $bp );
			if ( 'Klavye + Kalem' === $lbl ) { $checks[] = 'Klavye Dahil'; $checks[] = 'Slim Pen Dahil'; }
			elseif ( 'Slim Pen' === $lbl ) { $checks[] = 'Slim Pen Dahil'; }
			elseif ( 'Klavye' === $lbl ) { $checks[] = 'Klavye Dahil'; }
			else { $checks[] = $lbl . ' Dahil'; }
		}
		$checks[] = 'Ekstra Masraf Yok';
		$checks[] = $has_gift ? 'Ücretsiz Hediye' : 'Orijinal Ürün';
		$checks   = array_values( array_unique( $checks ) );

		// Hediye özellik maddeleri (kısa açıklamadan)
		$bullets = array();
		if ( $has_gift ) {
			$gsd = wp_strip_all_tags( $gift_objs[0]['p']->get_short_description() );
			if ( $gsd ) {
				$parts = preg_split( '/\r\n|\r|\n|•|·|;|\|/u', $gsd );
				if ( count( array_filter( array_map( 'trim', (array) $parts ) ) ) < 2 ) { $parts = preg_split( '/,/u', $gsd ); }
				foreach ( (array) $parts as $pt ) { $pt = trim( $pt ); if ( '' !== $pt ) { $bullets[] = $pt; } if ( count( $bullets ) >= 4 ) { break; } }
			}
		}

		ob_start();
		?>
<div class="wcp-gb<?php echo $has_gift ? '' : ' wcp-gb-nogift'; ?>">
	<div class="wcp-gb-main">
	<div class="wcp-gb-head"><span class="wcp-gb-badge"><?php echo $has_gift ? '🎁 ' . esc_html( $s['title'] ) : '📦 SET İÇERİĞİ'; ?></span></div>
	<div class="wcp-gb-body">
		<?php if ( $has_gift ) : $first = $gift_objs[0]; $gimg = $first['p']->get_image_id() ? wp_get_attachment_image_url( $first['p']->get_image_id(), 'medium' ) : wc_placeholder_img_src(); ?>
		<div class="wcp-gb-gift">
			<div class="wcp-gb-giftimg"><span class="wcp-gb-free">ÜCRETSİZ</span><img src="<?php echo esc_url( $gimg ); ?>" alt=""></div>
			<div class="wcp-gb-giftinfo">
				<h4><?php echo esc_html( $first['p']->get_name() ); ?></h4>
				<?php if ( $first['reg'] > 0 ) : ?><div class="wcp-gb-val">Piyasa Değeri: <b><?php echo wp_kses_post( wc_price( $first['reg'] ) ); ?></b></div><?php endif; ?>
				<?php if ( $bullets ) : ?><ul class="wcp-gb-blist"><?php foreach ( $bullets as $bt ) : ?><li><?php echo esc_html( $bt ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="wcp-gb-pack">
			<div class="wcp-gb-packtitle">PAKET İÇERİĞİ</div>
			<div class="wcp-gb-packrow">
				<?php foreach ( $pack as $i => $it ) : ?>
				<?php if ( $i > 0 ) : ?><span class="wcp-gb-plus">+</span><?php endif; ?>
				<div class="wcp-gb-pi <?php echo esc_attr( $it['cls'] ); ?>"><?php if ( 'wcp-gb-pi-gift' === $it['cls'] ) : ?><span class="wcp-gb-pi-gbadge">🎁</span><?php endif; ?><img src="<?php echo esc_url( $it['img'] ); ?>" alt=""><span><?php echo esc_html( $it['label'] ); ?></span><?php if ( '__gift__' === $it['price'] ) : ?><small class="wcp-gb-pp wcp-gb-pp-free">Hediye</small><?php elseif ( '__incl__' === $it['price'] ) : ?><small class="wcp-gb-pp wcp-gb-pp-incl">dahil</small><?php elseif ( '' !== $it['price'] ) : ?><small class="wcp-gb-pp"><?php echo wp_kses_post( $it['price'] ); ?></small><?php endif; ?></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	</div>
	<div class="wcp-gb-value">
		<ul class="wcp-gb-checks">
			<?php foreach ( $checks as $ck ) : ?><li><?php echo esc_html( $ck ); ?></li><?php endforeach; ?>
		</ul>
		<div class="wcp-gb-total<?php echo $has_gift ? '' : ' wcp-gb-settotal'; ?>">
			<span class="wcp-gb-tlabel">Toplam Paket Değeri</span>
			<?php if ( $has_gift ) : ?>
			<b><?php echo wp_kses_post( wc_price( $pkg_total ) ); ?></b><small class="wcp-gb-save">Hediye Dahil</small>
			<?php else : ?>
			<?php if ( $bundle_value > 0 ) : ?><span class="wcp-gb-sep">Ayrı ayrı: <s><?php echo wp_kses_post( wc_price( $sep_total ) ); ?></s></span><?php endif; ?><b><?php echo wp_kses_post( wc_price( $main_price ) ); ?></b><?php if ( $bundle_value > 0 ) : ?><small class="wcp-gb-save">Klavye + Kalem dahil</small><?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
		<?php
		return ob_get_clean();
	}

	/* Bloğu summary kolonundan çıkarıp ürün satırının ALTINA, tam-genişliğe taşı */
	public static function relocate_js() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		?>
<script>
(function(){
	function move(){
		var gb=document.querySelector('.wcp-gb'); if(!gb||gb.getAttribute('data-wcpmoved')) return;
		var product=document.querySelector('.single-product-page.product')||document.querySelector('div.product.entry-content')||document.querySelector('div.product'); if(!product) return;
		var pw=product.getBoundingClientRect().width; if(!pw) return;
		var hostW=gb.parentElement?gb.parentElement.getBoundingClientRect().width:0;
		if(hostW>=pw*0.8){ gb.setAttribute('data-wcpmoved','1'); return; } // mobil: konteyner zaten tam genişlik, taşıma
		var node=gb, full=null;
		while(node && node!==product){ var w=node.getBoundingClientRect().width; if(w>=pw*0.9){ full=node; break; } node=node.parentElement; }
		if(full && full.parentNode){ gb.setAttribute('data-wcpmoved','1'); full.parentNode.insertBefore(gb, full.nextSibling); }
	}
	if(document.readyState!=='loading'){ move(); } else { document.addEventListener('DOMContentLoaded',move); }
	setTimeout(move,400); setTimeout(move,1200); setTimeout(move,2500);
	setTimeout(function(){ var e=document.querySelector('.wcp-gb'); if(e&&!e.getAttribute('data-wcpmoved')) e.setAttribute('data-wcpmoved','1'); },4000);
})();
</script>
		<?php
	}

	public static function assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
		?>
<style id="wcp-gift-css">
.wcp-gb:not([data-wcpmoved]){display:none!important;}
.wcp-gb{display:flex;align-items:stretch;border:1px solid #e6e9ef;border-radius:16px;background:linear-gradient(180deg,#fff,#f7f9fc);box-shadow:0 8px 30px rgba(16,24,40,.06);overflow:hidden;margin:18px 0;font-family:inherit;container-type:inline-size;}
.wcp-gb-main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;}
.wcp-gb-head{padding:14px 22px 2px;}
.wcp-gb-badge{display:inline-flex;align-items:center;gap:7px;font-weight:800;font-size:15px;color:#0b63d6;letter-spacing:.3px;}
.wcp-gb-body{display:flex;align-items:stretch;flex:1;}
.wcp-gb-gift{flex:0 0 38%;display:flex;gap:15px;align-items:center;padding:14px 22px 16px;border-right:1px solid #eef1f6;}
.wcp-gb-giftimg{position:relative;flex:0 0 100px;width:100px;height:100px;border-radius:13px;background:#fff;border:1px solid #eef1f6;display:flex;align-items:center;justify-content:center;}
.wcp-gb-giftimg img{max-width:82%;max-height:82%;object-fit:contain;}
.wcp-gb-free{position:absolute;top:-9px;left:-9px;background:#16a34a;color:#fff;font-size:9.5px;font-weight:800;padding:3px 8px;border-radius:999px;letter-spacing:.4px;box-shadow:0 3px 8px rgba(22,163,74,.35);}
.wcp-gb-giftinfo{min-width:0;}
.wcp-gb-giftinfo h4{margin:0 0 4px;font-size:14px;font-weight:800;color:#15202b;line-height:1.25;}
.wcp-gb-val{font-size:12.5px;color:#5b6471;margin-bottom:7px;}.wcp-gb-val b{color:#0b63d6;}
.wcp-gb-blist{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:3px;}
.wcp-gb-blist li{position:relative;padding-left:18px;font-size:11.5px;color:#5b6471;line-height:1.35;}
.wcp-gb-blist li:before{content:"✓";position:absolute;left:0;top:0;color:#16a34a;font-weight:900;font-size:11px;}
.wcp-gb-pack{flex:1 1 auto;min-width:0;padding:14px 22px 16px;display:flex;flex-direction:column;justify-content:center;}
.wcp-gb-packtitle{font-size:11px;font-weight:800;letter-spacing:1px;color:#9aa1ad;margin-bottom:12px;text-align:center;}
.wcp-gb-packrow{display:flex;align-items:flex-start;justify-content:center;gap:6px;flex-wrap:wrap;}
.wcp-gb-pi{position:relative;display:flex;flex-direction:column;align-items:center;gap:5px;width:90px;text-align:center;}
.wcp-gb-pi img{width:68px;height:68px;object-fit:contain;border:1px solid #eef1f6;border-radius:12px;background:#fff;padding:6px;}
.wcp-gb-pi span{font-size:11px;color:#5b6471;font-weight:600;line-height:1.25;}
.wcp-gb-pp{font-size:11px;font-weight:800;color:#0b63d6;margin-top:1px;}
.wcp-gb-pp .amount{font-size:10.5px;}
.wcp-gb-pp-free{color:#16a34a!important;}
.wcp-gb-pp-incl{color:#9aa1ad!important;font-weight:600;}
.wcp-gb-pi-gift img{border-color:#bbf7d0;box-shadow:0 0 0 2px rgba(22,163,74,.12);}
.wcp-gb-pi-gbadge{position:absolute;top:-7px;right:9px;font-size:11px;background:#0b63d6;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(11,99,214,.4);z-index:1;}
.wcp-gb-plus{font-size:20px;font-weight:300;color:#c2c8d2;align-self:center;}
.wcp-gb-value{flex:0 0 268px;background:linear-gradient(160deg,#10233f,#1a3257);color:#fff;display:flex;flex-direction:column;justify-content:center;gap:14px;padding:18px 22px;}
.wcp-gb-checks{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:9px 12px;}
.wcp-gb-checks li{position:relative;padding-left:22px;font-size:11.5px;font-weight:600;color:#dbe4f0;line-height:1.25;}
.wcp-gb-checks li:before{content:"✓";position:absolute;left:0;top:-1px;width:16px;height:16px;background:#16a34a;color:#fff;border-radius:50%;font-size:10px;font-weight:900;display:flex;align-items:center;justify-content:center;}
.wcp-gb-total{border-top:1px solid rgba(255,255,255,.13);padding-top:13px;display:flex;flex-direction:column;align-items:center;text-align:center;}
.wcp-gb-tlabel{font-size:12px;color:#9fb3cd;}
.wcp-gb-total b{font-size:25px;font-weight:800;color:#fff;line-height:1.1;margin:2px 0;}
.wcp-gb-save{font-size:11.5px;color:#7fd99a;font-weight:700;line-height:1.35;}
.wcp-gb-sep,.wcp-gb-sep s{font-size:11px;color:#9fb3cd;}
.wcp-gift-tag{display:inline-block;background:#16a34a;color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:6px;vertical-align:middle;}
.wcp-gift-free{color:#16a34a;font-weight:800;}
.wcp-gb-nogift .wcp-gb-badge{color:#15202b;}
/* SET (hediyesiz) modunda paket öğeleri büyük ve belirgin */
.wcp-gb-nogift .wcp-gb-value{flex:0 0 300px;}
.wcp-gb-nogift .wcp-gb-pack{padding:18px 28px;}
.wcp-gb-nogift .wcp-gb-packtitle{font-size:13px;letter-spacing:1.5px;margin-bottom:18px;}
.wcp-gb-nogift .wcp-gb-packrow{gap:24px;}
.wcp-gb-nogift .wcp-gb-pi{width:142px;}
.wcp-gb-nogift .wcp-gb-pi img{width:104px;height:104px;border-radius:15px;padding:9px;}
.wcp-gb-nogift .wcp-gb-pi span{font-size:14px;}
.wcp-gb-nogift .wcp-gb-pp{font-size:14px;}
.wcp-gb-nogift .wcp-gb-plus{font-size:30px;}
/* ===== TABLET (≤860px): kolonları dikey yığ ===== */
@container (max-width:860px){.wcp-gb{flex-direction:column;}.wcp-gb-value{flex:1 1 auto;border-radius:0 0 16px 16px;}.wcp-gb-body{flex-direction:column;}.wcp-gb-gift{flex:1 1 auto;border-right:0;border-bottom:1px solid #eef1f6;}}
@media (max-width:860px){.wcp-gb{flex-direction:column;}.wcp-gb-value{flex:1 1 auto!important;border-radius:0 0 16px 16px;}.wcp-gb-body{flex-direction:column;}.wcp-gb-gift{flex:1 1 auto;border-right:0;border-bottom:1px solid #eef1f6;}}

/* ===== MOBİL (≤600px): tamamen ayrı — paket dikey itemize liste ===== */
@media (max-width:600px){
	.wcp-gb{margin:14px 0;border-radius:14px;}
	.wcp-gb-head{padding:14px 16px 0;text-align:center;}
	.wcp-gb-badge{font-size:14px;}
	.wcp-gb-gift{padding:14px 16px;gap:12px;align-items:flex-start;}
	.wcp-gb-giftimg{flex:0 0 74px;width:74px;height:74px;}
	.wcp-gb-giftinfo h4{font-size:13px;}
	.wcp-gb-val{font-size:12px;margin-bottom:6px;}
	.wcp-gb-blist li{font-size:11px;}
	.wcp-gb-pack{padding:14px 16px;}
	.wcp-gb-nogift .wcp-gb-pack{padding:14px 16px;}
	.wcp-gb-packtitle{text-align:left;margin-bottom:11px;letter-spacing:1px;font-size:11px;}
	.wcp-gb-plus{display:none;}
	.wcp-gb-packrow{flex-direction:column;align-items:stretch;gap:8px;}
	.wcp-gb-pi,.wcp-gb-nogift .wcp-gb-pi{flex-direction:row;width:100%;align-items:center;gap:12px;text-align:left;background:#fff;border:1px solid #eef1f6;border-radius:12px;padding:9px 13px;}
	.wcp-gb-pi img,.wcp-gb-nogift .wcp-gb-pi img{width:46px;height:46px;flex:0 0 46px;border:0;padding:0;border-radius:8px;box-shadow:none;}
	.wcp-gb-pi span,.wcp-gb-nogift .wcp-gb-pi span{flex:1;min-width:0;font-size:12.5px;color:#15202b;font-weight:600;text-align:left;}
	.wcp-gb-pp,.wcp-gb-nogift .wcp-gb-pp{margin:0 0 0 auto;font-size:13px;white-space:nowrap;}
	.wcp-gb-pi-gift{background:#f0fdf4;border-color:#bbf7d0!important;}
	.wcp-gb-pi-gbadge{display:none;}
	.wcp-gb-value{flex:1 1 auto!important;padding:16px;gap:13px;border-radius:0 0 14px 14px;}
	.wcp-gb-nogift .wcp-gb-value{flex:1 1 auto!important;}
	.wcp-gb-checks{grid-template-columns:1fr 1fr;gap:8px 10px;}
	.wcp-gb-total b{font-size:26px;}
}
@media (max-width:360px){.wcp-gb-checks{grid-template-columns:1fr;}}
</style>
		<?php
	}
}

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WooCommerce' ) ) { WCP_Gift::init(); }
}, 20 );
