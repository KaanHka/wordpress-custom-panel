<?php
/**
 * Plugin Name: Store Panel (Standalone Store Admin)
 * Description: WordPress/WooCommerce'in ÜSTÜNDE, /panel adresinde tam bağımsız çalışan AdminLTE beyaz-etiket mağaza yönetim paneli. wp-admin kullanmaz; kendi tam HTML sayfasını üretir. Veri WooCommerce API'siyle çekilir, ürün/sipariş yönetimi panel içinde yapılır. Fonksiyonlar korunur.
 * Version: 3.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCP_Panel {

	const VER  = '3.0.4';
	const BASE_PATH = 'panel';
	const BS   = 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css';
	const FA   = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css';
	const LTE  = 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css';
	const JQ   = 'https://code.jquery.com/jquery-3.6.4.min.js';
	const BSJS = 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js';
	const LJS  = 'https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js';
	const FONT = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 ); // wp-admin barına marka+hızlı menü
		add_action( 'wp_head', array( __CLASS__, 'front_bar_css' ), 1000 );   // front: WP barı gizle + custom bar stili
		add_action( 'wp_footer', array( __CLASS__, 'front_bar_render' ), 100 ); // front: STORE custom barı
		add_action( 'admin_init', array( __CLASS__, 'restrict_wpadmin' ) ); // mağaza yön.: wp-admin'de yalnız düzenleyici, gerisi → panel
		add_action( 'admin_page_access_denied', array( __CLASS__, 'restrict_denied' ) ); // yetki reddinde wp_die yerine panele yönlendir
		add_action( 'admin_menu', array( __CLASS__, 'capture_menu' ), 99999 );   // tüm WP+eklenti menüsünü yakala
		add_action( 'admin_head', array( __CLASS__, 'frame_hide' ), 1 );         // panel içinde gömülünce WP arayüzünü gizle
		add_action( 'wp_ajax_wcp_prod_img', array( __CLASS__, 'ajax_prod_img' ) ); // anlık görsel işlemleri
		add_action( 'wp_ajax_wcp_prod_search', array( __CLASS__, 'ajax_prod_search' ) );   // ürün otomatik tamamlama
		add_action( 'wp_ajax_wcp_prod_field', array( __CLASS__, 'ajax_prod_field' ) );     // tek alan anlık kaydet
		add_action( 'wp_ajax_wcp_prod_action', array( __CLASS__, 'ajax_prod_action' ) );   // çoğalt / çöp / taslak
		add_action( 'wp_ajax_wcp_term_search', array( __CLASS__, 'ajax_term_search' ) );   // nitelik değeri otomatik tamamlama
		add_action( 'wp_ajax_wcp_media_upload', array( __CLASS__, 'ajax_media_upload' ) ); // medya yükle (sürükle-bırak)
		add_action( 'wp_ajax_wcp_media_save', array( __CLASS__, 'ajax_media_save' ) );     // medya alanı anlık kaydet
		add_action( 'wp_ajax_wcp_media_delete', array( __CLASS__, 'ajax_media_delete' ) ); // medya sil
		// Whitelabel: WordPress giriş ekranı (sıfırdan özel tasarım)
		add_filter( 'login_title', array( __CLASS__, 'login_doc_title' ), 10, 2 );
		add_filter( 'login_headerurl', array( __CLASS__, 'login_logo_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'login_logo_text' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_branding' ) );
		add_filter( 'login_message', array( __CLASS__, 'login_message' ) );
		add_filter( 'login_body_class', array( __CLASS__, 'login_body_class' ) );
	}

	public static function login_body_class( $classes ) { $classes[] = 'wcp-login'; return $classes; }

	/* Form üstüne marka + tagline */
	public static function login_message( $m ) {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'login';
		$sub = 'Mağaza Yönetim Paneli';
		if ( $action === 'lostpassword' || $action === 'retrievepassword' ) { $sub = 'Şifre sıfırlama'; }
		elseif ( $action === 'rp' || $action === 'resetpass' ) { $sub = 'Yeni şifre belirle'; }
		elseif ( $action === 'register' ) { $sub = 'Hesap oluştur'; }
		return '<div class="wcp-login-head"><span class="wcp-login-sub">' . esc_html( $sub ) . '</span></div>' . $m;
	}

	/* Giriş ekranı: sekme başlığından "WordPress" kaldır → "İşlem ‹ Mağaza adı" */
	public static function login_doc_title( $login_title, $title = '' ) {
		$name = get_bloginfo( 'name', 'display' );
		return ( $title !== '' ? $title . ' ‹ ' : '' ) . $name;
	}

	/* Giriş logosu linki → ana sayfa (wordpress.org yerine) */
	public static function login_logo_url() { return home_url( '/' ); }

	/* Giriş logosu metni → mağaza adı ("Powered by WordPress" yerine) */
	public static function login_logo_text() { return get_bloginfo( 'name', 'display' ); }

	/* Giriş ekranı — sıfırdan özel tasarım (whitelabel) */
	public static function login_branding() {
		$logo = '';
		if ( function_exists( 'get_site_icon_url' ) && get_site_icon_url( 256 ) ) {
			$logo = get_site_icon_url( 256 );
		} elseif ( get_theme_mod( 'custom_logo' ) ) {
			$img = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
			if ( $img ) { $logo = $img[0]; }
		}
		$name    = get_bloginfo( 'name', 'display' );
		$initial = mb_strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 1, 'UTF-8' ), 'UTF-8' );
		echo '<link rel="stylesheet" href="' . esc_url( self::FONT ) . '">';
		?>
<style>
:root{--wcp-dark:#15171c;--wcp-accent:#5c7cfa;}
body.login{min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;font-family:'Inter',-apple-system,Segoe UI,Roboto,sans-serif;
  background:radial-gradient(1100px 600px at 12% 8%,rgba(92,124,250,.20),transparent 60%),radial-gradient(900px 500px at 92% 92%,rgba(92,124,250,.12),transparent 55%),linear-gradient(135deg,#16181f 0%,#1d212b 45%,#262c39 100%);}
body.login::before{content:"";position:fixed;inset:0;background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:22px 22px;pointer-events:none;}
#login{width:380px;max-width:92vw;padding:0;position:relative;z-index:1;}

/* Marka rozeti */
.login h1{margin:0 0 2px;text-align:center;}
.login h1 a{display:inline-flex!important;align-items:center;justify-content:center;width:84px!important;height:84px!important;margin:0 auto 14px!important;border-radius:24px!important;
  background:#fff <?php echo $logo ? 'url(' . esc_url( $logo ) . ') center/52px no-repeat' : ''; ?>!important;
  box-shadow:0 14px 40px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.06)!important;
  text-indent:0!important;overflow:visible!important;font-size:38px!important;font-weight:900!important;color:#15171c!important;line-height:84px!important;}
<?php if ( ! $logo ) { ?>.login h1 a{font-size:38px!important;}<?php } else { ?>.login h1 a{font-size:0!important;}<?php } ?>

/* Marka başlığı + alt yazı */
.wcp-login-head{text-align:center;margin:0 0 18px;}
.login .wcp-login-sub{display:block;color:rgba(255,255,255,.62);font-size:13.5px;font-weight:500;letter-spacing:.02em;margin-top:2px;}

/* Kart form */
.login form{margin:0;background:#fff;border:0;border-radius:18px;padding:30px 28px 26px;box-shadow:0 30px 70px rgba(0,0,0,.40);}
.login form .forgetmenot{margin-bottom:14px;}
.login label{color:#39414f;font-size:13.5px;font-weight:600;}
.login form .input,.login input[type=text],.login input[type=password],.login input[type=email]{
  width:100%;background:#f7f8fa;border:1.5px solid #e4e8ef;border-radius:11px;padding:12px 13px;font-size:15px;color:#1f2632;box-shadow:none;margin:6px 0 4px;transition:border-color .15s,box-shadow .15s;}
.login form .input:focus,.login input:focus{border-color:var(--wcp-dark);background:#fff;box-shadow:0 0 0 3px rgba(21,23,28,.10);outline:0;}
.login .password-input-wrapper{width:100%;}
.wp-core-ui .button.wp-hide-pw{border:0;background:transparent;}

/* Buton */
.wp-core-ui .button-primary{display:block;width:100%;text-align:center;background:var(--wcp-dark)!important;border:0!important;border-radius:11px!important;
  padding:12px 18px!important;height:auto!important;font-size:15px!important;font-weight:700!important;text-shadow:none!important;box-shadow:0 8px 20px rgba(21,23,28,.25)!important;margin-top:6px;transition:transform .06s,background .15s;}
.wp-core-ui .button-primary:hover{background:#000!important;}
.wp-core-ui .button-primary:active{transform:translateY(1px);}

/* "Beni hatırla" */
.login .forgetmenot label{font-weight:500;color:#5b6677;font-size:13px;}

/* Alt linkler (koyu zemin) */
.login #nav,.login #backtoblog{text-align:center;padding:0;margin:18px auto 0;}
.login #nav a,.login #backtoblog a{color:rgba(255,255,255,.70)!important;font-size:13px;text-decoration:none;}
.login #nav a:hover,.login #backtoblog a:hover{color:#fff!important;}
.login #nav{margin-top:16px;}

/* Hata / mesaj kutuları */
.login #login_error,.login .message,.login .notice,.login .success{border-radius:12px;border:0;border-left:4px solid var(--wcp-dark);box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:13.5px;}
.login .message,.login .success{border-left-color:#16a34a;}
.login #login_error{border-left-color:#dc2626;}

/* dil seçici vb. gizle */
.login .language-switcher{display:none;}
</style>
<?php
		// Logo yoksa rozette marka baş harfini göster
		if ( ! $logo ) {
			echo '<script>document.addEventListener("DOMContentLoaded",function(){var a=document.querySelector(".login h1 a");if(a){a.textContent=' . wp_json_encode( $initial ) . ';}});</script>';
		}
	}

	/* Medya yükle — sürükle-bırak / dosya seç */
	public static function ajax_media_upload() {
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		if ( empty( $_FILES['file'] ) ) { wp_send_json_error( 'dosya yok' ); }
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $id ) ) { wp_send_json_error( $id->get_error_message() ); }
		$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
		wp_send_json_success( array( 'id' => $id, 'thumb' => $thumb ? $thumb : wp_mime_type_icon( $id ), 'edit' => self::url( 'media-item', $id ), 'title' => get_the_title( $id ) ) );
	}

	/* Medya tek alan anlık kaydet (başlık/alt/açıklama/altyazı) */
	public static function ajax_media_save() {
		if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$att   = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) { wp_send_json_error( 'medya yok' ); }
		switch ( $field ) {
			case 'title':       wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $value ) ) ); break;
			case 'alt':         update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $value ) ); break;
			case 'caption':     wp_update_post( array( 'ID' => $id, 'post_excerpt' => wp_kses_post( $value ) ) ); break;
			case 'description': wp_update_post( array( 'ID' => $id, 'post_content' => wp_kses_post( $value ) ) ); break;
			default: wp_send_json_error( 'alan: ' . $field );
		}
		wp_send_json_success( array( 'ok' => true ) );
	}

	/* Medya sil (kalıcı) */
	public static function ajax_media_delete() {
		if ( ! current_user_can( 'delete_posts' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) { wp_send_json_error( 'medya yok' ); }
		$r = wp_delete_attachment( $id, true );
		if ( ! $r ) { wp_send_json_error( 'silinemedi' ); }
		wp_send_json_success( array( 'ok' => true, 'redirect' => self::url( 'media' ) ) );
	}

	/* Ürün görsel AJAX: öne çıkan/galeri anlık ekle-sil-sırala (sayfa kaydetmeden) */
	public static function ajax_prod_img() {
		if ( ! current_user_can( 'edit_products' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
		$p   = $pid ? wc_get_product( $pid ) : false;
		if ( ! $p ) { wp_send_json_error( 'ürün yok' ); }
		if ( $op === 'set_featured' ) { $p->set_image_id( isset( $_POST['att'] ) ? absint( $_POST['att'] ) : 0 ); }
		elseif ( $op === 'remove_featured' ) { $p->set_image_id( '' ); }
		elseif ( $op === 'add_gallery' ) {
			$ids = isset( $_POST['atts'] ) ? array_map( 'absint', (array) $_POST['atts'] ) : array();
			$gal = $p->get_gallery_image_ids();
			foreach ( $ids as $i ) { if ( $i && ! in_array( $i, $gal, true ) ) { $gal[] = $i; } }
			$p->set_gallery_image_ids( $gal );
		} elseif ( $op === 'remove_gallery' ) {
			$rm = isset( $_POST['att'] ) ? absint( $_POST['att'] ) : 0;
			$p->set_gallery_image_ids( array_values( array_diff( $p->get_gallery_image_ids(), array( $rm ) ) ) );
		} elseif ( $op === 'reorder_gallery' ) {
			$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();
			$p->set_gallery_image_ids( $order );
		} else { wp_send_json_error( 'op' ); }
		$p->save();
		$fid = $p->get_image_id();
		wp_send_json_success( array(
			'featured' => $fid ? wp_get_attachment_image_url( $fid, array( 90, 90 ) ) : '',
			'gallery'  => array_map( function ( $g ) { return array( 'id' => $g, 'url' => wp_get_attachment_image_url( $g, array( 70, 70 ) ) ); }, $p->get_gallery_image_ids() ),
		) );
	}

	/* Ürün otomatik tamamlama (ad / SKU / ID ile) — bağlantılı ürünler, FBT */
	public static function ajax_prod_search() {
		if ( ! current_user_can( 'edit_products' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$exclude = isset( $_GET['exclude'] ) ? array_filter( array_map( 'absint', explode( ',', (string) $_GET['exclude'] ) ) ) : array();
		if ( $term === '' ) { wp_send_json_success( array() ); }
		$ids = array();
		if ( is_numeric( $term ) && wc_get_product( (int) $term ) ) { $ids[] = (int) $term; }
		$by_sku = wc_get_product_id_by_sku( $term );
		if ( $by_sku ) { $ids[] = $by_sku; }
		$q = new WP_Query( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 20, 's' => $term, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'relevance' ) );
		$ids = array_merge( $ids, $q->posts );
		$ids = array_values( array_diff( array_unique( $ids ), $exclude ) );
		$out = array();
		foreach ( array_slice( $ids, 0, 20 ) as $i ) {
			$pp = wc_get_product( $i ); if ( ! $pp ) { continue; }
			$rp = get_post_meta( $i, '_regular_price', true );
			$out[] = array(
				'id'    => $i,
				'text'  => html_entity_decode( wp_strip_all_tags( $pp->get_name() ) ),
				'sku'   => $pp->get_sku(),
				'price' => $rp !== '' ? html_entity_decode( wp_strip_all_tags( wc_price( $rp ) ) ) : '',
				'thumb' => $pp->get_image_id() ? wp_get_attachment_image_url( $pp->get_image_id(), array( 44, 44 ) ) : wc_placeholder_img_src(),
			);
		}
		wp_send_json_success( $out );
	}

	/* Nitelik değeri otomatik tamamlama (taxonomy terimleri) */
	public static function ajax_term_search() {
		if ( ! current_user_can( 'edit_products' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$tax = isset( $_GET['tax'] ) ? sanitize_key( $_GET['tax'] ) : '';
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( ! $tax || ! taxonomy_exists( $tax ) ) { wp_send_json_success( array() ); }
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'search' => $term, 'number' => 20 ) );
		$out = array();
		if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { $out[] = array( 'id' => $t->term_id, 'text' => $t->name ); } }
		wp_send_json_success( $out );
	}

	/* Tek alan anlık kaydet (sayfayı kaydetmeden) */
	public static function ajax_prod_field() {
		if ( ! current_user_can( 'edit_products' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$field = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$value = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';
		$p = $pid ? wc_get_product( $pid ) : false;
		if ( ! $p ) { wp_send_json_error( 'ürün yok' ); }
		$resp = array();
		// dizi alanları (bağlantılı ürünler / FBT)
		if ( in_array( $field, array( 'upsell_ids', 'cross_sell_ids', 'ssd_ids', 'gift_ids', 'bundle_ids' ), true ) ) {
			$ids = isset( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) ) ) : array();
			if ( $field === 'upsell_ids' ) { $p->set_upsell_ids( $ids ); $p->save(); }
			elseif ( $field === 'cross_sell_ids' ) { $p->set_cross_sell_ids( $ids ); $p->save(); }
			elseif ( $field === 'ssd_ids' ) {
				if ( $ids ) { update_post_meta( $pid, '_fbt_product_ids', $ids ); } else { delete_post_meta( $pid, '_fbt_product_ids' ); }
				delete_post_meta( $pid, '_fbt_product_id' );
			}
			elseif ( $field === 'gift_ids' ) {
				if ( $ids ) { update_post_meta( $pid, '_wcp_gift_ids', $ids ); } else { delete_post_meta( $pid, '_wcp_gift_ids' ); }
			}
			elseif ( $field === 'bundle_ids' ) {
				if ( $ids ) { update_post_meta( $pid, '_wcp_bundle_ids', $ids ); } else { delete_post_meta( $pid, '_wcp_bundle_ids' ); }
			}
			wp_send_json_success( array( 'ok' => true ) );
		}
		try {
			switch ( $field ) {
				case 'title': $p->set_name( sanitize_text_field( $value ) ); break;
				case 'slug': $p->set_slug( sanitize_title( $value ) ); break;
				case 'regular_price': $p->set_regular_price( wc_format_decimal( $value ) ); break;
				case 'sale_price': $p->set_sale_price( $value !== '' ? wc_format_decimal( $value ) : '' ); break;
				case 'sale_from': $p->set_date_on_sale_from( $value !== '' ? wc_clean( $value ) : '' ); break;
				case 'sale_to': $p->set_date_on_sale_to( $value !== '' ? wc_clean( $value ) : '' ); break;
				case 'sku': $p->set_sku( sanitize_text_field( $value ) ); break;
				case 'manage_stock': $p->set_manage_stock( $value === '1' ); break;
				case 'stock_quantity': $p->set_manage_stock( true ); $p->set_stock_quantity( wc_stock_amount( $value ) ); break;
				case 'low_stock': $p->set_low_stock_amount( $value !== '' ? wc_stock_amount( $value ) : '' ); break;
				case 'stock_status': $p->set_stock_status( in_array( $value, array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $value : 'instock' ); break;
				case 'backorders': $p->set_backorders( in_array( $value, array( 'no', 'notify', 'yes' ), true ) ? $value : 'no' ); break;
				case 'sold_individually': $p->set_sold_individually( $value === '1' ); break;
				case 'weight': $p->set_weight( wc_format_decimal( $value ) ); break;
				case 'length': $p->set_length( wc_format_decimal( $value ) ); break;
				case 'width': $p->set_width( wc_format_decimal( $value ) ); break;
				case 'height': $p->set_height( wc_format_decimal( $value ) ); break;
				case 'short_description': $p->set_short_description( wp_kses_post( $value ) ); break;
				case 'description': $p->set_description( wp_kses_post( $value ) ); break;
				case 'purchase_note': $p->set_purchase_note( wp_kses_post( $value ) ); break;
				case 'menu_order': $p->set_menu_order( (int) $value ); break;
				case 'status': $p->set_status( in_array( $value, array( 'publish', 'draft', 'pending' ), true ) ? $value : 'draft' ); break;
				case 'catalog_visibility': $p->set_catalog_visibility( in_array( $value, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ? $value : 'visible' ); break;
				case 'featured': $p->set_featured( $value === '1' ); break;
				case 'tax_status': $p->set_tax_status( in_array( $value, array( 'taxable', 'shipping', 'none' ), true ) ? $value : 'taxable' ); break;
				case 'tax_class': $p->set_tax_class( sanitize_title( $value ) ); break;
				default: wp_send_json_error( 'alan: ' . $field );
			}
		} catch ( Exception $e ) { wp_send_json_error( $e->getMessage() ); }
		$p->save();
		if ( $field === 'slug' ) { $resp['permalink'] = get_permalink( $pid ); $resp['slug'] = $p->get_slug(); }
		$resp['ok'] = true;
		wp_send_json_success( $resp );
	}

	/* Ürün eylemleri: çoğalt / çöpe at */
	public static function ajax_prod_action() {
		if ( ! current_user_can( 'edit_products' ) ) { wp_send_json_error( 'yetki' ); }
		check_ajax_referer( 'wcp_prod_img' );
		$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$do  = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		$p = $pid ? wc_get_product( $pid ) : false;
		if ( ! $p ) { wp_send_json_error( 'ürün yok' ); }
		if ( $do === 'duplicate' ) {
			if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) { require_once WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php'; }
			if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) { wp_send_json_error( 'çoğaltma yok' ); }
			$dup = ( new WC_Admin_Duplicate_Product() )->product_duplicate( $p );
			$nid = is_object( $dup ) ? $dup->get_id() : 0;
			if ( ! $nid ) { wp_send_json_error( 'kopyalanamadı' ); }
			wp_send_json_success( array( 'edit' => self::url( 'product', $nid ) ) );
		} elseif ( $do === 'trash' ) {
			wp_trash_post( $pid );
			wp_send_json_success( array( 'redirect' => self::url( 'products' ) ) );
		}
		wp_send_json_error( 'işlem' );
	}

	/* Ürün editörü JS: anlık kaydet + seçici otomatik tamamlama + eylemler */
	protected static function product_editor_js( $pid ) {
		if ( ! $pid ) { return; }
		$nonce = wp_create_nonce( 'wcp_prod_img' );
		$ajax  = admin_url( 'admin-ajax.php' );
		?>
<script>
jQuery(function($){
	var PID=<?php echo (int) $pid; ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>, AJAX=<?php echo wp_json_encode( $ajax ); ?>;
	if(!PID){return;}
	function badge(state){ var b=$('#tx-autosave'); if(!b.length)return; b.removeClass('is-saving is-ok is-err'); if(state==='saving'){b.addClass('is-saving').html('<i class="fas fa-circle-notch fa-spin"></i> Kaydediliyor');} else if(state==='ok'){b.addClass('is-ok').html('<i class="fas fa-check"></i> Kaydedildi'); setTimeout(function(){b.removeClass('is-ok').html('<i class="fas fa-bolt"></i> Otomatik kayıt');},1600);} else {b.addClass('is-err').html('<i class="fas fa-triangle-exclamation"></i> Hata');} }
	function flash(el,ok){ if(!el)return; el.addClass(ok?'tx-saved':'tx-saveerr'); setTimeout(function(){el.removeClass('tx-saved tx-saveerr');},1300); }
	function saveField(field,extra,el){ var data=$.extend({action:'wcp_prod_field',_ajax_nonce:NONCE,product_id:PID,field:field},extra); badge('saving'); $.post(AJAX,data,function(r){ if(r&&r.success){ badge('ok'); flash(el,true); if(field==='slug'&&r.data&&r.data.permalink){ $('#tx-permalink').attr('href',r.data.permalink).text(r.data.permalink); } } else { badge('err'); flash(el,false); if(r&&r.data){ alert(r.data); } } }).fail(function(){badge('err');flash(el,false);}); }
	// --- anlık kaydet (data-wcpsave) ---
	$(document).on('change','[data-wcpsave]',function(){ var el=$(this),f=el.data('wcpsave'),v; if(el.is(':checkbox')){v=el.is(':checked')?'1':'0';} else {v=el.val();} saveField(f,{value:v},el); });
	// --- ürün seçiciler (bağlantılı / FBT) ---
	$('.tx-picker').each(function(){
		var box=$(this), field=box.data('field'), input=box.find('.tx-pick-input'), dd=box.find('.tx-pick-dd'), chips=box.find('.tx-chips-sel'), hidden=box.find('.tx-pick-hidden'), t;
		function ids(){ return chips.find('.tx-chip-sel').map(function(){return String($(this).data('id'));}).get(); }
		function persist(){ var a=ids(); hidden.val(a.join(',')); var data={action:'wcp_prod_field',_ajax_nonce:NONCE,product_id:PID,field:field}; data['ids']=a; badge('saving'); $.post(AJAX,data,function(r){ badge(r&&r.success?'ok':'err'); }).fail(function(){badge('err');}); }
		function addChip(it){ if(ids().indexOf(String(it.id))>-1)return; var c=$('<span class="tx-chip-sel"></span>').attr('data-id',it.id); c.html('<img src="'+it.thumb+'"><span></span><a href="#" class="tx-chip-x" title="Kaldır">×</a>'); c.find('span').text(it.text); chips.append(c); }
		input.on('input',function(){ clearTimeout(t); var q=$.trim(input.val()); if(q.length<2){dd.hide().empty();return;} t=setTimeout(function(){ $.get(AJAX,{action:'wcp_prod_search',_ajax_nonce:NONCE,q:q,exclude:ids().join(',')},function(r){ dd.empty(); if(r&&r.success&&r.data.length){ r.data.forEach(function(it){ var row=$('<div class="tx-pick-opt"></div>'); row.append($('<img>').attr('src',it.thumb)); var sp=$('<span></span>').text(it.text); row.append(sp); if(it.sku){row.append($('<small></small>').text(it.sku));} if(it.price){row.append($('<b></b>').html(it.price));} row.on('click',function(){ addChip(it); input.val(''); dd.hide().empty(); persist(); }); dd.append(row); }); dd.show(); } else { dd.html('<div class="tx-pick-empty">Sonuç yok</div>').show(); } }); },250); });
		chips.on('click','.tx-chip-x',function(e){ e.preventDefault(); $(this).closest('.tx-chip-sel').remove(); persist(); });
		input.on('keydown',function(e){ if(e.which===13){e.preventDefault();} });
		$(document).on('click',function(e){ if(!$.contains(box[0],e.target)){ dd.hide(); } });
	});
	// --- eylemler ---
	$('#tx-act-duplicate').on('click',function(){ if(!confirm('Bu ürün kopyalansın mı? Kopya taslak olarak açılır.'))return; var b=$(this).prop('disabled',true); $.post(AJAX,{action:'wcp_prod_action',_ajax_nonce:NONCE,product_id:PID,'do':'duplicate'},function(r){ if(r&&r.success&&r.data.edit){ location.href=r.data.edit; } else { alert((r&&r.data)||'Kopyalanamadı'); b.prop('disabled',false); } }); });
	$('#tx-act-trash').on('click',function(){ if(!confirm('Ürün çöp kutusuna taşınsın mı?'))return; $.post(AJAX,{action:'wcp_prod_action',_ajax_nonce:NONCE,product_id:PID,'do':'trash'},function(r){ if(r&&r.success&&r.data.redirect){ location.href=r.data.redirect; } else { alert('İşlem başarısız'); } }); });
	$('#tx-act-draft').on('click',function(){ saveField('status',{value:'draft'}); $('select[name=status]').val('draft'); });
	// --- Facebook özel görsel ---
	$('#tx-fb-imgsrc').on('change',function(){ $('#tx-fb-customimg').toggle($(this).val()==='custom'); });
	$('#tx-fb-img-select').on('click',function(e){ e.preventDefault(); var f=wp.media({title:'Facebook görseli seç',button:{text:'Seç'},multiple:false,library:{type:'image'}}); f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); var u=(a.sizes&&a.sizes.medium)?a.sizes.medium.url:a.url; $('#tx-fb-img-url').val(a.url); $('#tx-fb-img-prev').attr('src',u).show(); }); f.open(); });
	// --- SSD Yükseltme seçenekleri (anlık) ---
	$('#tx-ssdbox').on('change','.tx-ssdcb',function(){ var ids=$('#tx-ssdbox .tx-ssdcb:checked').map(function(){return $(this).val();}).get(); var data={action:'wcp_prod_field',_ajax_nonce:NONCE,product_id:PID,field:'ssd_ids'}; data['ids']=ids; badge('saving'); $.post(AJAX,data,function(r){ badge(r&&r.success?'ok':'err'); }).fail(function(){badge('err');}); });
	// ===== Nitelikler tam yönetim =====
	var attrIdx=window.WCP_ATTR_IDX||0, attrBox=$('#tx-attrs');
	function attrOrder(){ var o=attrBox.children('.tx-attr').map(function(){return $(this).attr('data-idx');}).get(); $('#tx-attr-order').val(o.join(',')); }
	function attrSync(row){ var vals=row.find('.tx-attr-vals .tx-vchip').map(function(){return String($(this).attr('data-v'));}).get(); row.find('.tx-attr-vhidden').val(vals.join('|')); row.find('.tx-attr-sum').text(vals.slice(0,5).join(', ')+(vals.length>5?' …':'')); }
	function addVal(row,v){ v=$.trim(v); if(!v)return; var dup=false; row.find('.tx-attr-vals .tx-vchip').each(function(){ if(String($(this).attr('data-v')).toLowerCase()===v.toLowerCase())dup=true; }); if(dup)return; var c=$('<span class="tx-vchip"><span></span><a href="#" class="tx-vx" title="Kaldır">×</a></span>'); c.attr('data-v',v); c.find('span').text(v); row.find('.tx-attr-vals').append(c); attrSync(row); }
	attrBox.on('click','.tx-attr-toggle',function(e){ e.preventDefault(); $(this).closest('.tx-attr').toggleClass('is-open'); });
	attrBox.on('click','.tx-attr-del',function(e){ e.preventDefault(); $(this).closest('.tx-attr').remove(); attrOrder(); if(!attrBox.children('.tx-attr').length){$('#tx-attr-empty').show();} });
	attrBox.on('click','.tx-vx',function(e){ e.preventDefault(); var row=$(this).closest('.tx-attr'); $(this).closest('.tx-vchip').remove(); attrSync(row); });
	attrBox.on('input','.tx-attr-cname',function(){ $(this).closest('.tx-attr').find('.tx-attr-name').text($(this).val()||'Özel nitelik'); });
	attrBox.on('keydown','.tx-attr-vinput',function(e){ if(e.which===13){ e.preventDefault(); var row=$(this).closest('.tx-attr'); addVal(row,$(this).val()); $(this).val(''); row.find('.tx-attr-dd').hide().empty(); } });
	attrBox.on('input','.tx-attr-vinput',function(){ var inp=$(this),row=inp.closest('.tx-attr'),tax=row.attr('data-taxonomy'),dd=row.find('.tx-attr-dd'); if(!tax){dd.hide();return;} var q=$.trim(inp.val()); if(q.length<1){dd.hide().empty();return;} clearTimeout(inp.data('t')); inp.data('t',setTimeout(function(){ $.get(AJAX,{action:'wcp_term_search',_ajax_nonce:NONCE,tax:tax,q:q},function(r){ dd.empty(); if(r&&r.success&&r.data.length){ r.data.forEach(function(it){ var o=$('<div class="tx-pick-opt"></div>'); o.append($('<span></span>').text(it.text)); o.on('mousedown',function(ev){ ev.preventDefault(); addVal(row,it.text); inp.val(''); dd.hide().empty(); }); dd.append(o); }); dd.show(); } else { dd.hide(); } }); },220)); });
	attrBox.on('blur','.tx-attr-vinput',function(){ var dd=$(this).closest('.tx-attr').find('.tx-attr-dd'); setTimeout(function(){dd.hide();},180); });
	$('#tx-attr-expand').on('click',function(){ attrBox.children('.tx-attr').addClass('is-open'); });
	$('#tx-attr-collapse').on('click',function(){ attrBox.children('.tx-attr').removeClass('is-open'); });
	function buildAttrRow(idx,key,label,tax){
		var h='<div class="tx-attr is-open" data-idx="'+idx+'" data-tax="'+(tax?1:0)+'"'+(tax?(' data-taxonomy="'+key+'"'):'')+'>'
		+'<input type="hidden" name="attr_key['+idx+']" value="'+(tax?key:'custom')+'">'
		+'<input type="hidden" name="attr_tax['+idx+']" value="'+(tax?1:0)+'">'
		+'<div class="tx-attr-head"><span class="tx-attr-handle" title="Sürükle">≡</span><span class="tx-attr-name"></span><span class="tx-attr-sum"></span><a href="#" class="tx-attr-toggle">▾</a><a href="#" class="tx-attr-del">×</a></div>'
		+'<div class="tx-attr-body">'
		+(tax?'':'<label class="tx-label">Nitelik adı</label><input class="form-control mb-2 tx-attr-cname" name="attr_name['+idx+']" value="" placeholder="ör. Garanti süresi">')
		+'<label class="tx-label">Değerler</label><div class="tx-attr-vals"></div>'
		+'<div class="tx-attr-valwrap"><input type="text" class="form-control tx-attr-vinput" autocomplete="off" placeholder="'+(tax?'Değer yazın — öneriler çıkar':'Değer yazıp Enter')+'"><div class="tx-pick-dd tx-attr-dd"></div></div>'
		+'<input type="hidden" class="tx-attr-vhidden" name="attr_vals['+idx+']" value="">'
		+'<label class="tx-check mt-2"><input type="checkbox" name="attr_visible['+idx+']" value="1" checked> Ürün sayfasında göster</label>'
		+'</div></div>';
		var row=$(h); row.find('.tx-attr-name').text(tax?label:'Özel nitelik'); return row;
	}
	$('#tx-attr-add').on('change',function(){ var opt=$(this).find('option:selected'),v=$(this).val(); if(!v)return; var tax=String(opt.attr('data-tax'))==='1',key=(v==='__custom__')?'':v,label=opt.attr('data-label')||''; var row=buildAttrRow(attrIdx++,key,label,tax); attrBox.append(row); $('#tx-attr-empty').hide(); attrOrder(); $(this).val(''); row.find(tax?'.tx-attr-vinput':'.tx-attr-cname').focus(); });
	if($.fn.sortable){ attrBox.sortable({ handle:'.tx-attr-handle', items:'.tx-attr', cursor:'move', axis:'y', update:attrOrder }); }
	attrOrder();
});
</script>
		<?php
	}

	/* Tek nitelik satırı (accordion: başlık + değer chip'leri + autocomplete + görünür) */
	protected static function attr_row( $idx, $key, $label, $is_tax, $values, $visible ) {
		$values = array_values( array_filter( array_map( 'trim', (array) $values ), function ( $v ) { return $v !== ''; } ) );
		echo '<div class="tx-attr" data-idx="' . (int) $idx . '" data-tax="' . ( $is_tax ? '1' : '0' ) . '"' . ( $is_tax ? ' data-taxonomy="' . esc_attr( $key ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="attr_key[' . (int) $idx . ']" value="' . esc_attr( $is_tax ? $key : 'custom' ) . '">';
		echo '<input type="hidden" name="attr_tax[' . (int) $idx . ']" value="' . ( $is_tax ? '1' : '0' ) . '">';
		echo '<div class="tx-attr-head"><span class="tx-attr-handle" title="Sürükle">≡</span><span class="tx-attr-name">' . esc_html( $label ) . '</span><span class="tx-attr-sum">' . esc_html( implode( ', ', array_slice( $values, 0, 5 ) ) . ( count( $values ) > 5 ? ' …' : '' ) ) . '</span><a href="#" class="tx-attr-toggle" title="Genişlet / kapat">▾</a><a href="#" class="tx-attr-del" title="Kaldır">×</a></div>';
		echo '<div class="tx-attr-body">';
		if ( ! $is_tax ) { echo '<label class="tx-label">Nitelik adı</label><input class="form-control mb-2 tx-attr-cname" name="attr_name[' . (int) $idx . ']" value="' . esc_attr( $label ) . '" placeholder="ör. Garanti süresi">'; }
		echo '<label class="tx-label">Değerler</label><div class="tx-attr-vals">';
		foreach ( $values as $v ) { echo '<span class="tx-vchip" data-v="' . esc_attr( $v ) . '"><span>' . esc_html( $v ) . '</span><a href="#" class="tx-vx" title="Kaldır">×</a></span>'; }
		echo '</div>';
		echo '<div class="tx-attr-valwrap"><input type="text" class="form-control tx-attr-vinput" autocomplete="off" placeholder="' . ( $is_tax ? 'Değer yazın — öneriler çıkar' : 'Değer yazıp Enter\'a basın' ) . '"><div class="tx-pick-dd tx-attr-dd"></div></div>';
		echo '<input type="hidden" class="tx-attr-vhidden" name="attr_vals[' . (int) $idx . ']" value="' . esc_attr( implode( '|', $values ) ) . '">';
		echo '<label class="tx-check mt-2"><input type="checkbox" name="attr_visible[' . (int) $idx . ']" value="1"' . checked( $visible, true, false ) . '> Ürün sayfasında göster</label>';
		echo '</div></div>';
	}

	/* Chip + otomatik tamamlamalı ürün seçici (bağlantılı ürünler / FBT) */
	protected static function product_picker( $field, $hidden, $label, $hint, $ids ) {
		echo '<div class="tx-picker" data-field="' . esc_attr( $field ) . '">';
		echo '<label class="tx-label">' . esc_html( $label ) . '</label>';
		echo '<input type="hidden" class="tx-pick-hidden" name="' . esc_attr( $hidden ) . '" value="' . esc_attr( implode( ',', array_map( 'intval', (array) $ids ) ) ) . '">';
		echo '<div class="tx-chips-sel">';
		foreach ( (array) $ids as $i ) {
			$sp = wc_get_product( $i ); if ( ! $sp ) { continue; }
			$thumb = $sp->get_image_id() ? wp_get_attachment_image_url( $sp->get_image_id(), array( 44, 44 ) ) : wc_placeholder_img_src();
			echo '<span class="tx-chip-sel" data-id="' . esc_attr( $i ) . '"><img src="' . esc_url( $thumb ) . '"><span>' . esc_html( wp_strip_all_tags( $sp->get_name() ) ) . '</span><a href="#" class="tx-chip-x" title="Kaldır">×</a></span>';
		}
		echo '</div>';
		echo '<div class="tx-pick-wrap"><input type="text" class="form-control tx-pick-input" placeholder="Ürün adı, SKU veya ID yazın…" autocomplete="off"><div class="tx-pick-dd"></div></div>';
		if ( $hint ) { echo '<p class="text-muted" style="font-size:11.5px;margin:5px 0 0">' . esc_html( $hint ) . '</p>'; }
		echo '</div>';
	}

	/* Ürün düzenleme görsel JS (wp.media + jquery-ui sortable + AJAX) */
	protected static function product_media_js( $pid ) {
		$nonce = wp_create_nonce( 'wcp_prod_img' );
		$ajax  = admin_url( 'admin-ajax.php' );
		?>
<script>
jQuery(function($){
	var PID=<?php echo (int) $pid; ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>, AJAX=<?php echo wp_json_encode( $ajax ); ?>;
	if(!PID){return;}
	function call(data,cb){ data.action='wcp_prod_img'; data.product_id=PID; data._ajax_nonce=NONCE; $.post(AJAX,data,cb); }
	function renderGallery(g){ var h=''; (g||[]).forEach(function(it){ h+='<div class="tx-gitem" data-id="'+it.id+'"><img src="'+it.url+'"><a href="#" class="tx-gremove" data-id="'+it.id+'">×</a></div>'; }); $('#tx-gallery').html(h); }
	// öne çıkan seç
	$('#tx-feat-select').on('click',function(e){ e.preventDefault();
		var f=wp.media({title:'Öne çıkan görsel seç', button:{text:'Seç'}, multiple:false, library:{type:'image'}});
		f.on('select',function(){ var a=f.state().get('selection').first().toJSON();
			call({op:'set_featured',att:a.id},function(r){ if(r.success){ var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url; $('#tx-feat-img').attr('src',u).show(); $('#tx-feat-remove').show(); }});
		}); f.open();
	});
	$('#tx-feat-remove').on('click',function(e){ e.preventDefault(); var btn=this; call({op:'remove_featured'},function(r){ if(r.success){ $('#tx-feat-img').hide().attr('src',''); $(btn).hide(); }}); });
	// galeri ekle
	$('#tx-gal-add').on('click',function(e){ e.preventDefault();
		var f=wp.media({title:'Galeri görselleri', button:{text:'Ekle'}, multiple:true, library:{type:'image'}});
		f.on('select',function(){ var ids=f.state().get('selection').toJSON().map(function(a){return a.id;});
			call({op:'add_gallery',atts:ids},function(r){ if(r.success){ renderGallery(r.data.gallery); }});
		}); f.open();
	});
	$('#tx-gallery').on('click','.tx-gremove',function(e){ e.preventDefault(); var id=$(this).data('id');
		call({op:'remove_gallery',att:id},function(r){ if(r.success){ renderGallery(r.data.gallery); }});
	});
	if($.fn.sortable){ $('#tx-gallery').sortable({ items:'.tx-gitem', cursor:'move', update:function(){ var order=$('#tx-gallery .tx-gitem').map(function(){return $(this).data('id');}).get(); call({op:'reorder_gallery',order:order},function(){}); } }); }
});
</script>
		<?php
	}

	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) { return; }
		// wp-admin üst barına marka + hızlı menü (front-end için ayrı, tamamen custom bar var)
		$bar->add_node( array( 'id' => 'wcp-brand', 'title' => '◼ STORE Panel', 'href' => self::url(), 'meta' => array( 'title' => 'STORE Mağaza Paneli' ) ) );
		$bar->add_node( array( 'parent' => 'wcp-brand', 'id' => 'wcp-q-dash', 'title' => 'Genel Bakış', 'href' => self::url() ) );
		$bar->add_node( array( 'parent' => 'wcp-brand', 'id' => 'wcp-q-orders', 'title' => 'Siparişler', 'href' => self::url( 'orders' ) ) );
		$bar->add_node( array( 'parent' => 'wcp-brand', 'id' => 'wcp-q-products', 'title' => 'Ürünler', 'href' => self::url( 'products' ) ) );
		$bar->add_node( array( 'parent' => 'wcp-brand', 'id' => 'wcp-q-pages', 'title' => 'Sayfalar', 'href' => self::url( 'pages' ) ) );
	}

	protected static function front_bar_can() {
		return ! is_admin() && is_user_logged_in() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
	}

	/* Mağaza yöneticilerini wp-admin'de yalnız düzenleyici (post/sayfa/Elementor) sayfalarında bırak; gerisinde panele yönlendir */
	public static function restrict_wpadmin() {
		if ( wp_doing_ajax() ) { return; }                              // editör AJAX/autosave bozulmasın
		if ( current_user_can( 'manage_options' ) ) { return; }         // yöneticiler tam serbest
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }   // panel-dışı kullanıcılara karışma (normal WP davranışı)
		global $pagenow;
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// YALNIZCA Elementor görsel düzenleyici serbest (post.php?action=elementor)
		if ( $pagenow === 'post.php' && $action === 'elementor' ) { return; }
		// editör içi medya/yardımcı uçlar (Elementor'un ihtiyaç duyduğu)
		if ( in_array( $pagenow, array( 'async-upload.php', 'media-upload.php', 'admin-post.php' ), true ) ) { return; }
		// gerisi (post.php?action=edit, post-new, plugins, ayarlar, tema...) → panel
		wp_safe_redirect( self::url() );
		exit;
	}

	/* Yetki reddi (plugins/themes/options vb.) → WP'nin "yetkiniz yok" sayfası yerine panele yönlendir */
	public static function restrict_denied() {
		if ( current_user_can( 'manage_options' ) ) { return; }
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; } // panel-dışı kullanıcı: normal WP davranışı
		wp_safe_redirect( self::url() );
		exit;
	}

	/* Custom barın "Tümü" menüsündeki öğeler (yetkiye göre) */
	protected static function front_bar_items() {
		$it = array(
			array( 'Genel Bakış', self::url() ),
			array( 'Siparişler', self::url( 'orders' ) ),
			array( 'Ürünler', self::url( 'products' ) ),
			array( 'Kategoriler', self::url( 'categories' ) ),
			array( 'Kuponlar', self::url( 'coupons' ) ),
			array( 'Müşteriler', self::url( 'customers' ) ),
			array( 'Değerlendirmeler', self::url( 'reviews' ) ),
			array( 'Stok Yönetimi', self::url( 'stock' ) ),
			array( 'Sayfalar', self::url( 'pages' ) ),
			array( 'Medya', self::url( 'media' ) ),
			array( 'Raporlar', self::url( 'reports' ) ),
		);
		if ( current_user_can( 'list_users' ) ) { $it[] = array( 'Kullanıcılar', self::url( 'users' ) ); }
		if ( current_user_can( 'manage_options' ) ) { $it[] = array( 'Yetkilendirme', self::url( 'perms' ) ); $it[] = array( 'Ayarlar', self::url( 'settings' ) ); }
		return $it;
	}

	/* Front-end: WP barını gizle + modern STORE custom barı (CSS) */
	public static function front_bar_css() {
		if ( ! self::front_bar_can() ) { return; }
		echo '<link rel="stylesheet" href="' . esc_url( self::FONT ) . '">';
		echo "<style id=\"wcp-frontbar\">"
			. "#wpadminbar{display:none!important;}html{margin-top:34px!important;}"
			. "@media screen and (max-width:782px){html{margin-top:48px!important;}}"
			. "#wcp-bar{position:fixed;top:0;left:0;right:0;height:34px;z-index:99990;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 12px;background:linear-gradient(90deg,#101218,#1b1f27 55%,#191c22);box-shadow:0 0 0 1px rgba(255,255,255,.05),0 8px 26px rgba(0,0,0,.30);font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;font-size:12.5px;box-sizing:border-box;-webkit-font-smoothing:antialiased;}"
			. "#wcp-bar *{box-sizing:border-box;}"
			. ".wcp-bar-l,.wcp-bar-r{display:flex;align-items:center;gap:3px;min-width:0;}"
			. ".wcp-bar-brand{display:inline-flex;align-items:center;gap:7px;text-decoration:none;padding-right:8px;margin-right:3px;}"
			. ".wcp-bar-mark{width:20px;height:20px;border-radius:6px;background:linear-gradient(135deg,#fff,#d8dbe2);color:#15171c;font-weight:900;font-size:12px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3);}"
			. "#wcp-bar .wcp-bar-brand b{color:#fff!important;font-weight:800;letter-spacing:.3px;}"
			. "#wcp-bar .wcp-bar-brand i{color:#7e8593!important;font-style:normal;font-weight:600;font-size:11px;}"
			. ".wcp-bar-nav{display:flex;gap:1px;}"
			. "#wcp-bar a,#wcp-bar button{color:#c3c8d2!important;text-decoration:none!important;font-family:'Inter',sans-serif!important;box-shadow:none;}"
			. "#wcp-bar a:hover{text-decoration:none!important;}"
			. ".wcp-bar-nav a,.wcp-pop-t,.wcp-bar-store{display:inline-flex;align-items:center;gap:5px;height:25px;padding:0 11px;border-radius:8px;font-weight:600;border:0;background:transparent;cursor:pointer;font-size:12.5px;line-height:1;transition:background .13s,color .13s;}"
			. "#wcp-bar .wcp-bar-nav a:hover,#wcp-bar .wcp-pop-t:hover,#wcp-bar .wcp-bar-store:hover,#wcp-bar .wcp-pop.open>.wcp-pop-t{background:rgba(255,255,255,.10);color:#fff!important;}"
			. ".wcp-pop-t .cv{transition:transform .15s;opacity:.7;}.wcp-pop.open>.wcp-pop-t .cv{transform:rotate(180deg);}"
			. "#wcp-bar .wcp-bar-edit{display:inline-flex;align-items:center;gap:6px;height:25px;padding:0 13px;border-radius:8px;background:linear-gradient(180deg,#fff,#eef0f3);color:#15171c!important;font-weight:700;text-decoration:none!important;box-shadow:0 2px 8px rgba(0,0,0,.28);transition:filter .13s;}"
			. ".wcp-bar-edit:hover{filter:brightness(.95);}"
			. ".wcp-bar-edit.has-c{border-radius:8px 0 0 8px;padding-right:10px;}"
			. "#wcp-bar .wcp-bar-edit-c{height:25px;padding:0 7px;border-radius:0 8px 8px 0;background:linear-gradient(180deg,#fff,#eef0f3);color:#15171c!important;border:0;border-left:1px solid #dde0e6;cursor:pointer;font-weight:700;margin-right:5px;display:inline-flex;align-items:center;}"
			. ".wcp-bar-edit-c:hover{filter:brightness(.95);}"
			. ".wcp-bar-user{display:inline-flex;align-items:center;gap:7px;height:26px;padding:0 9px 0 3px;border-radius:14px;background:rgba(255,255,255,.07);cursor:pointer;border:0;font-family:inherit;}"
			. ".wcp-bar-user:hover,.wcp-pop.open>.wcp-bar-user{background:rgba(255,255,255,.15);}"
			. ".wcp-bar-user img{width:21px;height:21px;border-radius:50%;}"
			. "#wcp-bar .wcp-bar-user span{color:#e7e9ee!important;font-weight:600;}"
			. ".wcp-pop{position:relative;display:inline-flex;align-items:center;}"
			. ".wcp-pop-m{position:absolute;top:calc(100% + 8px);left:0;min-width:206px;background:#1b1f27;border:1px solid rgba(255,255,255,.09);border-radius:13px;box-shadow:0 18px 44px rgba(0,0,0,.45);padding:7px;opacity:0;visibility:hidden;transform:translateY(-8px) scale(.98);transform-origin:top;transition:opacity .15s,transform .15s,visibility .15s;z-index:5;}"
			. ".wcp-pop-m.wcp-right{left:auto;right:0;transform-origin:top right;}"
			. ".wcp-pop.open>.wcp-pop-m{opacity:1;visibility:visible;transform:translateY(0) scale(1);}"
			. "#wcp-bar .wcp-pop-m a{display:block;padding:8px 13px;border-radius:9px;color:#c3c8d2!important;font-weight:600;font-size:12.5px;white-space:nowrap;text-decoration:none!important;transition:background .1s,color .1s;}"
			. "#wcp-bar .wcp-pop-m a:hover{background:rgba(255,255,255,.11);color:#fff!important;}"
			. ".wcp-pop-sep{height:1px;background:rgba(255,255,255,.09);margin:6px 7px;}"
			. ".wcp-pop-m .wcp-pop-h{font-size:10px;font-weight:700;letter-spacing:.6px;color:#6c7280;text-transform:uppercase;padding:6px 13px 3px;}"
			. "@media screen and (max-width:980px){.wcp-bar-nav{display:none;}}"
			. "@media screen and (max-width:782px){#wcp-bar{height:48px;}.wcp-bar-brand i{display:none;}.wcp-bar-user span{display:none;}.wcp-bar-store{display:none;}.wcp-bar-nav a,.wcp-pop-t,.wcp-bar-store,.wcp-bar-edit,.wcp-bar-edit-c{height:34px;}.wcp-bar-user{height:36px;}}"
			. "</style>";
	}

	/* Front-end: STORE custom barını çiz (theme footer'da) */
	public static function front_bar_render() {
		if ( ! self::front_bar_can() ) { return; }
		$u  = wp_get_current_user();
		$av = get_avatar_url( $u->ID, array( 'size' => 44 ) );
		$cv = '<svg class="cv" width="9" height="9" viewBox="0 0 10 10" fill="none"><path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		$edit_panel = ''; $edit_label = ''; $visual_url = ''; $visual_label = '';
		if ( is_singular() ) {
			$pid = (int) get_queried_object_id();
			$pt  = $pid ? get_post_type( $pid ) : '';
			if ( $pid && current_user_can( 'edit_post', $pid ) ) {
				if ( $pt === 'product' ) { $edit_panel = self::url( 'product', $pid ); $edit_label = 'Ürünü düzenle'; }
				elseif ( $pt === 'page' ) { $edit_panel = self::url( 'page', $pid ); $edit_label = 'Sayfayı düzenle'; }
				// Görsel düzenleyici: Elementor sayfasıysa doğrudan Elementor; değilse WP editörü (yalnız yönetici)
				if ( get_post_meta( $pid, '_elementor_edit_mode', true ) === 'builder' ) {
					$visual_url = admin_url( 'post.php?post=' . $pid . '&action=elementor' );
					$visual_label = '🎨 Elementor ile düzenle';
				} elseif ( current_user_can( 'manage_options' ) ) {
					$vw = (string) get_edit_post_link( $pid, 'raw' );
					if ( $vw ) { $visual_url = $vw; $visual_label = '🎨 Görsel düzenleyici (wp-admin)'; }
				}
			}
		}
		echo '<div id="wcp-bar"><div class="wcp-bar-l">';
		echo '<a class="wcp-bar-brand" href="' . esc_url( self::url() ) . '"><span class="wcp-bar-mark">T</span><b>STORE</b><i>Panel</i></a>';
		echo '<nav class="wcp-bar-nav"><a href="' . esc_url( self::url( 'orders' ) ) . '">Siparişler</a><a href="' . esc_url( self::url( 'products' ) ) . '">Ürünler</a><a href="' . esc_url( self::url( 'pages' ) ) . '">Sayfalar</a></nav>';
		echo '<div class="wcp-pop"><button type="button" class="wcp-pop-t">Tümü ' . $cv . '</button><div class="wcp-pop-m"><div class="wcp-pop-h">Panel menüsü</div>';
		foreach ( self::front_bar_items() as $i ) { echo '<a href="' . esc_url( $i[1] ) . '">' . esc_html( $i[0] ) . '</a>'; }
		if ( current_user_can( 'manage_options' ) ) { echo '<div class="wcp-pop-sep"></div><a href="' . esc_url( admin_url() ) . '">wp-admin Yönetim</a>'; }
		echo '</div></div>';
		echo '</div><div class="wcp-bar-r">';
		if ( $edit_panel ) {
			$has_c = $visual_url ? ' has-c' : '';
			echo '<div class="wcp-pop"><a class="wcp-bar-edit' . $has_c . '" href="' . esc_url( $edit_panel ) . '">✎ ' . esc_html( $edit_label ) . '</a>';
			if ( $visual_url ) {
				echo '<button type="button" class="wcp-bar-edit-c wcp-pop-t" aria-label="Düzenleme seçenekleri">' . $cv . '</button>';
				echo '<div class="wcp-pop-m wcp-right"><a href="' . esc_url( $edit_panel ) . '">✎ Panelde düzenle</a><a href="' . esc_url( $visual_url ) . '" target="_blank">' . esc_html( $visual_label ) . '</a></div>';
			}
			echo '</div>';
		}
		echo '<a class="wcp-bar-store" href="' . esc_url( home_url( '/' ) ) . '">🏬 Mağaza</a>';
		echo '<div class="wcp-pop"><button type="button" class="wcp-bar-user"><img src="' . esc_url( $av ) . '" alt=""><span>' . esc_html( $u->display_name ) . '</span> ' . $cv . '</button><div class="wcp-pop-m wcp-right">';
		echo '<a href="' . esc_url( self::url() ) . '">Mağaza Paneli</a>';
		if ( current_user_can( 'manage_options' ) ) { echo '<a href="' . esc_url( admin_url() ) . '">wp-admin Yönetim</a>'; }
		echo '<div class="wcp-pop-sep"></div><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Çıkış yap</a>';
		echo '</div></div>';
		echo '</div></div>';
		echo '<script>(function(){var bar=document.getElementById("wcp-bar");if(!bar)return;function close(){var o=bar.querySelectorAll(".wcp-pop.open");for(var i=0;i<o.length;i++)o[i].classList.remove("open");}var ts=bar.querySelectorAll(".wcp-pop > button");for(var i=0;i<ts.length;i++){ts[i].addEventListener("click",function(e){e.preventDefault();e.stopPropagation();var pop=this.closest(".wcp-pop");var was=pop.classList.contains("open");close();if(!was)pop.classList.add("open");});}document.addEventListener("click",function(e){if(!e.target.closest(".wcp-pop"))close();});document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});})();</script>';
	}

	/* Panel içine iframe ile gömülen WP ekranlarında WP kabuğunu gizle (Sec-Fetch-Dest:iframe veya wcp_frame). */
	public static function frame_hide() {
		$framed = ( isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe' ) || ! empty( $_GET['wcp_frame'] ) || ! empty( $_GET['wcp_capture'] );
		if ( ! $framed ) { return; }
		echo '<style id="wcp-frame-hide">#adminmenumain,#adminmenuback,#adminmenuwrap,#wpadminbar,#wpfooter,#screen-meta-links{display:none!important}html.wp-toolbar{padding-top:0!important}#wpcontent,#wpbody-content{margin-left:0!important}#wpbody{padding-top:0!important}#wpwrap{background:#fff;min-height:auto}.wrap{margin-top:10px}</style>';
	}

	/* Tüm admin menüsünü (çekirdek + eklentiler) bir option'a serialize et. Her wp-admin yüklemesinde tazelenir. */
	public static function capture_menu() {
		global $menu, $submenu;
		if ( ! is_array( $menu ) ) { return; }
		$strip = function ( $t ) { $t = wp_strip_all_tags( (string) $t ); $t = preg_replace( '/\s*\d.*$/s', '', $t ); return trim( $t ); };
		$out = array();
		foreach ( $menu as $m ) {
			if ( empty( $m[2] ) ) { continue; }
			if ( isset( $m[4] ) && strpos( $m[4], 'wp-menu-separator' ) !== false ) { continue; }
			$title = $strip( $m[0] );
			if ( $title === '' ) { continue; }
			$slug = $m[2];
			if ( $slug === 'wcp-panel' ) { continue; }
			$children = array();
			if ( ! empty( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $s ) {
					$st = $strip( $s[0] );
					if ( $st === '' ) { continue; }
					$children[] = array( 't' => $st, 'u' => self::resolve_admin_url( $s[2] ), 'c' => $s[1] );
				}
			}
			$out[] = array( 't' => $title, 'u' => self::resolve_admin_url( $slug ), 'c' => $m[1], 'i' => isset( $m[6] ) ? $m[6] : '', 'sub' => $children );
		}
		update_option( 'wcp_admin_menu', $out, false );
	}

	protected static function resolve_admin_url( $slug ) {
		if ( preg_match( '#^https?://#', $slug ) ) { return $slug; }
		if ( strpos( $slug, '.php' ) !== false ) { return admin_url( $slug ); }
		return admin_url( 'admin.php?page=' . $slug );
	}
	protected static function b64u( $s ) { return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); }
	protected static function unb64u( $s ) { return base64_decode( strtr( (string) $s, '-_', '+/' ) ); }
	protected static function dashicon( $i ) {
		if ( strpos( (string) $i, 'dashicons-' ) === 0 ) { return '<span class="nav-icon dashicons ' . esc_attr( $i ) . '"></span>'; }
		if ( $i && ( strpos( $i, 'data:' ) === 0 || strpos( $i, 'http' ) === 0 ) ) { return '<img class="nav-icon tx-mi" src="' . esc_url( $i ) . '" alt="">'; }
		return '<i class="nav-icon fas fa-circle-dot"></i>';
	}

	public static function rewrite() {
		add_rewrite_rule( '^' . self::BASE_PATH . '/?$', 'index.php?wcp_panel=1', 'top' );
		add_rewrite_rule( '^' . self::BASE_PATH . '/([a-z0-9\-]+)/?$', 'index.php?wcp_panel=1&wcp_view=$matches[1]', 'top' );
		add_rewrite_rule( '^' . self::BASE_PATH . '/([a-z0-9\-]+)/([0-9]+)/?$', 'index.php?wcp_panel=1&wcp_view=$matches[1]&wcp_id=$matches[2]', 'top' );
		if ( get_option( 'wcp_panel_rw' ) !== self::VER ) {
			flush_rewrite_rules( false );
			update_option( 'wcp_panel_rw', self::VER );
		}
	}
	public static function query_vars( $v ) { $v[] = 'wcp_panel'; $v[] = 'wcp_view'; $v[] = 'wcp_id'; return $v; }

	public static function url( $view = '', $id = 0, $args = array() ) {
		$u = home_url( '/' . self::BASE_PATH . ( $view ? '/' . $view : '' ) . ( $id ? '/' . $id : '' ) . '/' );
		return $args ? add_query_arg( $args, $u ) : $u;
	}
	public static function login_redirect( $redirect, $request, $user ) {
		if ( $user && ! is_wp_error( $user ) && ( user_can( $user, 'manage_woocommerce' ) || user_can( $user, 'manage_options' ) ) ) {
			return self::url();
		}
		return $redirect;
	}

	public static function maybe_render() {
		if ( ! get_query_var( 'wcp_panel' ) ) { return; }
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( get_query_var( 'wcp_view' ) ) ) ); exit;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( home_url( '/' ) ); exit;
		}
		self::handle_actions();
		self::render();
		exit;
	}

	/* ---------------- yardımcılar ---------------- */
	protected static function money( $v ) { return function_exists( 'wc_price' ) ? wc_price( $v ) : number_format_i18n( (float) $v, 2 ) . ' TL'; }
	protected static function status_label( $status ) {
		$status = 'wc-' === substr( $status, 0, 3 ) ? $status : 'wc-' . $status;
		$all = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		return isset( $all[ $status ] ) ? $all[ $status ] : ucfirst( str_replace( 'wc-', '', $status ) );
	}
	protected static function badge_class( $status ) {
		$s = str_replace( 'wc-', '', $status );
		$map = array( 'completed' => 'tx-ok', 'processing' => 'tx-info', 'hezarfen-shipped' => 'tx-info', 'pending' => 'tx-warn', 'on-hold' => 'tx-warn', 'cancel-request' => 'tx-warn', 'cancelled' => 'tx-bad', 'failed' => 'tx-bad', 'refunded' => 'tx-muted' );
		return isset( $map[ $s ] ) ? $map[ $s ] : 'tx-muted';
	}
	/* WooCommerce PDF Invoices & Packing Slips belge URL'i — eklentinin kendi üreticisiyle (doğru access_key/nonce). */
	protected static function pdf_url( $oid, $type ) {
		if ( function_exists( 'WPO_WCPDF' ) ) {
			$api = WPO_WCPDF();
			if ( isset( $api->endpoint ) && method_exists( $api->endpoint, 'get_document_link' ) ) {
				$o = wc_get_order( $oid );
				if ( $o ) {
					$link = $api->endpoint->get_document_link( $o, $type );
					if ( $link ) { return $link; }
				}
			}
		}
		return wp_nonce_url( admin_url( 'admin-ajax.php?action=generate_wpo_wcpdf&document_type=' . rawurlencode( $type ) . '&order_ids=' . absint( $oid ) ), 'generate_wpo_wcpdf' );
	}
	/* Hezarfen fatura tipi: company=Kurumsal, aksi=Bireysel */
	protected static function fatura_tipi( $o ) {
		return ( $o->get_meta( '_billing_hez_invoice_type' ) === 'company' ) ? 'Kurumsal' : 'Bireysel';
	}
	/* Hezarfen gönderi (kargo) özeti */
	protected static function gonderi_ozet( $o ) {
		$ship = $o->get_meta( '_hezarfen_mst_shipment_data' );
		if ( ! $ship ) { return ''; }
		$pp = explode( '||', $ship );
		$car = isset( $pp[3] ) ? $pp[3] : ''; $tno = isset( $pp[4] ) ? $pp[4] : ''; $turl = isset( $pp[5] ) ? $pp[5] : '';
		if ( ! $car && ! $tno ) { return ''; }
		$out = esc_html( $car );
		if ( $tno ) { $out .= ' · ' . ( $turl ? '<a href="' . esc_url( $turl ) . '" target="_blank">' . esc_html( $tno ) . '</a>' : esc_html( $tno ) ); }
		return $out;
	}
	/* WooCommerce sipariş atıf "Kaynak/Menşe" etiketi (wc-orders'daki Origin sütunuyla birebir aynı; aynı çeviri + filtreler) */
	protected static function order_origin( $o ) {
		$type   = (string) $o->get_meta( '_wc_order_attribution_source_type' );
		$source = (string) $o->get_meta( '_wc_order_attribution_utm_source' );
		switch ( $type ) {
			case 'utm':        $label = __( 'Source: %s', 'woocommerce' ); break;
			case 'organic':    $label = __( 'Organic: %s', 'woocommerce' ); break;
			case 'referral':   $label = __( 'Referral: %s', 'woocommerce' ); break;
			case 'typein':     $label = ''; $source = __( 'Direct', 'woocommerce' ); break;
			case 'mobile_app': $label = ''; $source = __( 'Mobile app', 'woocommerce' ); break;
			case 'admin':      $label = ''; $source = __( 'Web admin', 'woocommerce' ); break;
			case 'pos':        $label = ''; $source = __( 'Point of Sale', 'woocommerce' ); break;
			default:           $label = ''; $source = __( 'Unknown', 'woocommerce' ); break;
		}
		$formatted = apply_filters( 'wc_order_attribution_origin_formatted_source', ucfirst( trim( $source, '()' ) ), $source );
		$label     = (string) apply_filters( 'wc_order_attribution_origin_label', $label, $type, $source, $formatted );
		if ( false === strpos( $label, '%' ) ) { return $formatted; }
		return sprintf( $label, $formatted );
	}

	/* Kaynak tipi için okunaklı Türkçe etiket */
	protected static function source_type_label( $t ) {
		$m = array(
			'utm'        => 'Ücretli kampanya (UTM)',
			'organic'    => 'Organik arama',
			'referral'   => 'Yönlendirme (referral)',
			'typein'     => 'Doğrudan giriş',
			'admin'      => 'Yönetici (elle oluşturuldu)',
			'mobile_app' => 'Mobil uygulama',
			'pos'        => 'Satış noktası (POS)',
		);
		return isset( $m[ $t ] ) ? $m[ $t ] : 'Bilinmiyor';
	}

	/* "k:v|k:v|..." dizisini ayrıştır; boş/undefined değerleri ele */
	protected static function pys_pairs( $str ) {
		$out = array();
		foreach ( explode( '|', (string) $str ) as $pair ) {
			$p = explode( ':', $pair, 2 );
			if ( count( $p ) === 2 ) {
				$v = trim( $p[1] );
				if ( $v !== '' && strtolower( $v ) !== 'undefined' ) { $out[ trim( $p[0] ) ] = $v; }
			}
		}
		return $out;
	}

	/* Verilen URL'lerden reklam tıklama kimliklerini (gclid, fbclid, vb.) çıkar */
	protected static function extract_click_ids( $urls ) {
		$keys = array(
			'gclid'   => 'Google Ads (gclid)',
			'gbraid'  => 'Google Ads (gbraid)',
			'wbraid'  => 'Google Ads (wbraid)',
			'fbclid'  => 'Meta / Facebook (fbclid)',
			'msclkid' => 'Microsoft Ads (msclkid)',
			'ttclid'  => 'TikTok (ttclid)',
			'dclid'   => 'Display & Video (dclid)',
			'twclid'  => 'X / Twitter (twclid)',
		);
		$found = array();
		foreach ( (array) $urls as $u ) {
			if ( ! $u ) { continue; }
			$q = wp_parse_url( $u, PHP_URL_QUERY );
			if ( ! $q ) { continue; }
			parse_str( $q, $params );
			foreach ( $keys as $k => $lbl ) {
				if ( ! empty( $params[ $k ] ) && ! isset( $found[ $lbl ] ) ) { $found[ $lbl ] = $params[ $k ]; }
			}
		}
		return $found;
	}

	/* Sipariş kaynağı / atıf detay kutusu — WooCommerce Order Attribution + PixelYourSite (ilk & son dokunuş) */
	protected static function order_attribution_box( $o ) {
		$g   = function ( $f ) use ( $o ) { return trim( (string) $o->get_meta( '_wc_order_attribution_' . $f ) ); };
		$wc  = array(
			'type'   => $g( 'source_type' ), 'source' => $g( 'utm_source' ), 'medium' => $g( 'utm_medium' ),
			'camp'   => $g( 'utm_campaign' ), 'term' => $g( 'utm_term' ), 'content' => $g( 'utm_content' ),
			'id'     => $g( 'utm_id' ), 'platform' => $g( 'utm_source_platform' ), 'ref' => $g( 'referrer' ),
			'entry'  => $g( 'session_entry' ), 'device' => $g( 'device_type' ), 'sstart' => $g( 'session_start_time' ),
			'spages' => $g( 'session_pages' ), 'scount' => $g( 'session_count' ), 'ua' => $g( 'user_agent' ),
		);
		$pe  = $o->get_meta( 'pys_enrich_data' );
		$pys = is_array( $pe ) ? $pe : ( $pe ? json_decode( $pe, true ) : array() );
		if ( ! is_array( $pys ) ) { $pys = array(); }

		$has_wc  = ( $wc['type'] !== '' || $wc['ref'] !== '' || $wc['entry'] !== '' );
		$has_pys = ! empty( $pys );
		if ( ! $has_wc && ! $has_pys ) { return; }

		// satır yazıcı: değer boş/undefined ise atla
		$kv = function ( $label, $value, $url = false ) {
			$value = trim( (string) $value );
			if ( $value === '' || strtolower( $value ) === 'undefined' ) { return; }
			echo '<div class="tx-arow"><span class="tx-alabel">' . esc_html( $label ) . '</span>';
			if ( $url ) {
				echo '<a class="tx-aval tx-aurl" href="' . esc_url( $value ) . '" target="_blank" rel="noopener" title="' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
			} else {
				echo '<span class="tx-aval">' . esc_html( $value ) . '</span>';
			}
			echo '</div>';
		};
		// UTM grubu (PYS parçalanmış dizisinden) yaz
		$utm_block = function ( $arr ) use ( $kv ) {
			$map = array( 'utm_source' => 'Kaynak (source)', 'utm_medium' => 'Ortam (medium)', 'utm_campaign' => 'Kampanya', 'utm_term' => 'Anahtar kelime (term)', 'utm_content' => 'İçerik (content)' );
			foreach ( $map as $k => $lbl ) { if ( isset( $arr[ $k ] ) ) { $kv( $lbl, $arr[ $k ] ); } }
		};

		echo '<style>.tx-attr .tx-asec{margin:0 0 14px}.tx-attr .tx-asec:last-child{margin-bottom:0}.tx-attr .tx-ahead{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#6c7a91;margin:0 0 8px;padding-bottom:5px;border-bottom:1px solid #eef0f4}.tx-attr .tx-arow{display:flex;gap:12px;padding:5px 0;border-bottom:1px dashed #f0f2f5;font-size:13px;align-items:flex-start}.tx-attr .tx-arow:last-child{border-bottom:0}.tx-attr .tx-alabel{flex:0 0 168px;color:#7a8699}.tx-attr .tx-aval{flex:1;min-width:0;font-weight:600;color:#2b3445;word-break:break-word;overflow-wrap:anywhere}.tx-attr .tx-aurl{font-weight:500}.tx-attr .tx-acols{display:grid;grid-template-columns:1fr 1fr;gap:0 26px}@media(max-width:680px){.tx-attr .tx-acols{grid-template-columns:1fr}.tx-attr .tx-alabel{flex-basis:120px}}</style>';

		echo '<div class="card card-outline tx-card tx-attr"><div class="card-header"><h3 class="card-title"><i class="fas fa-bullseye mr-2"></i>Sipariş Kaynağı / Atıf <small class="text-muted">(nereden geldi)</small></h3><div class="card-tools"><span class="tx-badge tx-info">' . esc_html( self::order_origin( $o ) ) . '</span></div></div><div class="card-body">';

		// ÖZET
		echo '<div class="tx-asec"><div class="tx-ahead">Özet</div>';
		$kv( 'Kaynak (Origin)', self::order_origin( $o ) );
		if ( $wc['type'] !== '' ) { $kv( 'Kaynak tipi', self::source_type_label( $wc['type'] ) . ' · ' . $wc['type'] ); }
		$kv( 'Cihaz', $wc['device'] );
		echo '</div>';

		// WOOCOMMERCE ATIF
		if ( $has_wc ) {
			echo '<div class="tx-asec"><div class="tx-ahead">Mağaza Atıf</div>';
			$kv( 'Kaynak (utm_source)', $wc['source'] );
			$kv( 'Ortam (utm_medium)', $wc['medium'] );
			$kv( 'Kampanya', $wc['camp'] );
			$kv( 'Anahtar kelime', $wc['term'] );
			$kv( 'İçerik', $wc['content'] );
			$kv( 'Kampanya ID', $wc['id'] );
			$kv( 'Kaynak platformu', $wc['platform'] );
			$kv( 'Giriş / İniş sayfası', $wc['entry'], true );
			$kv( 'Yönlendiren (referrer)', $wc['ref'], true );
			$kv( 'Oturum başlangıcı', $wc['sstart'] );
			$kv( 'Görüntülenen sayfa', $wc['spages'] );
			$kv( 'Ziyaret sayısı', $wc['scount'] );
			$kv( 'Tarayıcı (user agent)', $wc['ua'] );
			echo '</div>';
		}

		// PIXELYOURSITE — İLK & SON DOKUNUŞ
		if ( $has_pys ) {
			$first_utm = self::pys_pairs( isset( $pys['pys_utm'] ) ? $pys['pys_utm'] : '' );
			$last_utm  = self::pys_pairs( isset( $pys['last_pys_utm'] ) ? $pys['last_pys_utm'] : '' );
			$first_ids = self::pys_pairs( isset( $pys['pys_utm_id'] ) ? $pys['pys_utm_id'] : '' );
			$last_ids  = self::pys_pairs( isset( $pys['last_pys_utm_id'] ) ? $pys['last_pys_utm_id'] : '' );

			echo '<div class="tx-acols">';
			// İlk dokunuş
			echo '<div class="tx-asec"><div class="tx-ahead">İlk Dokunuş (First Touch)</div>';
			$kv( 'İlk iniş sayfası', isset( $pys['pys_landing'] ) ? $pys['pys_landing'] : '', true );
			$kv( 'İlk kaynak', isset( $pys['pys_source'] ) ? $pys['pys_source'] : '' );
			$utm_block( $first_utm );
			foreach ( $first_ids as $k => $v ) { $kv( 'Reklam ID · ' . $k, $v ); }
			echo '</div>';
			// Son dokunuş
			echo '<div class="tx-asec"><div class="tx-ahead">Son Dokunuş (Last Touch)</div>';
			$kv( 'Son iniş sayfası', isset( $pys['last_pys_landing'] ) ? $pys['last_pys_landing'] : '', true );
			$kv( 'Son kaynak', isset( $pys['last_pys_source'] ) ? $pys['last_pys_source'] : '' );
			$utm_block( $last_utm );
			foreach ( $last_ids as $k => $v ) { $kv( 'Reklam ID · ' . $k, $v ); }
			if ( isset( $pys['pys_browser_time'] ) ) { $kv( 'Tarayıcı zamanı', str_replace( '|', ' · ', (string) $pys['pys_browser_time'] ) ); }
			echo '</div>';
			echo '</div>';
		}

		// REKLAM TIKLAMA ID'LERİ (URL'lerden çıkarılan)
		$clicks = self::extract_click_ids( array(
			$wc['entry'], $wc['ref'],
			isset( $pys['pys_landing'] ) ? $pys['pys_landing'] : '',
			isset( $pys['last_pys_landing'] ) ? $pys['last_pys_landing'] : '',
		) );
		if ( $clicks ) {
			echo '<div class="tx-asec"><div class="tx-ahead">Reklam Tıklama Kimlikleri</div>';
			foreach ( $clicks as $lbl => $val ) { $kv( $lbl, $val ); }
			echo '</div>';
		}

		echo '</div></div>';
	}
	/* PayTR taksit bilgisi (sipariş notundaki "Installment Count" satırından) */
	protected static function paytr_taksit( $o ) {
		if ( strpos( (string) $o->get_payment_method(), 'paytr' ) === false ) { return ''; }
		$notes = wc_get_order_notes( array( 'order_id' => $o->get_id() ) );
		foreach ( $notes as $n ) {
			if ( preg_match( '/Installment Count\s*:?\s*([^\n<]+)/i', $n->content, $m ) ) {
				$val = trim( $m[1] );
				if ( $val === '1' || stripos( $val, 'one shot' ) !== false || stripos( $val, 'tek' ) !== false ) { return 'Tek çekim'; }
				if ( is_numeric( $val ) ) { return $val . ' Taksit'; }
				return $val;
			}
		}
		return '';
	}
	/* PayTR ödeme denemeleri (meta JSON) */
	protected static function paytr_attempts( $o ) {
		$raw = $o->get_meta( 'paytr_payment_attempts' );
		if ( ! $raw ) { return array(); }
		$arr = is_array( $raw ) ? $raw : json_decode( $raw, true );
		return is_array( $arr ) ? $arr : array();
	}
	/* Virgülle ayrılmış SKU/ID listesini ürün ID dizisine çevir (bağlantılı ürünler) */
	protected static function ids_from_refs( $str ) {
		$out = array();
		foreach ( explode( ',', (string) $str ) as $r ) {
			$r = trim( $r ); if ( $r === '' ) { continue; }
			$pid = wc_get_product_id_by_sku( $r ); if ( ! $pid && is_numeric( $r ) ) { $pid = absint( $r ); }
			if ( $pid ) { $out[] = $pid; }
		}
		return $out;
	}
	/* ID dizisini "ad (SKU)" etiketli virgüllü metne çevir (form değeri) */
	protected static function refs_label( $ids ) {
		$out = array();
		foreach ( (array) $ids as $pid ) { $pp = wc_get_product( $pid ); if ( $pp ) { $out[] = $pp->get_sku() ? $pp->get_sku() : $pid; } }
		return implode( ', ', $out );
	}
	/* Kategori term ID'lerini slug'a çevir */
	protected static function cat_slugs( $ids ) {
		$s = array();
		foreach ( (array) $ids as $id ) { $t = get_term( $id, 'product_cat' ); if ( $t && ! is_wp_error( $t ) ) { $s[] = $t->slug; } }
		return $s;
	}
	/* Bir kategorideki mevcut ürünlerden nitelik adlarını (sıklığa göre) topla — şablon */
	protected static function category_attribute_template( $cat_ids, $exclude_keys = array() ) {
		$slugs = self::cat_slugs( $cat_ids );
		if ( ! $slugs ) { return array(); }
		$ids = wc_get_products( array( 'limit' => 40, 'return' => 'ids', 'category' => $slugs, 'status' => 'publish' ) );
		$counts = array();
		foreach ( $ids as $pid ) {
			$pp = wc_get_product( $pid ); if ( ! $pp ) { continue; }
			foreach ( $pp->get_attributes() as $a ) { $k = $a->get_name(); if ( in_array( $k, $exclude_keys, true ) ) { continue; } $counts[ $k ] = isset( $counts[ $k ] ) ? $counts[ $k ] + 1 : 1; }
		}
		arsort( $counts );
		return array_keys( $counts );
	}

	/* ---------------- aksiyonlar ---------------- */
	protected static $flash = '';
	public static function handle_actions() {
		// Yetkilendirme matrisi — aksiyonun ait olduğu bölüm rol için kapalıysa engelle (menü gizlemekle kalmaz, eylemi de durdurur).
		// Yönetici her zaman geçer; yapılandırılmamış roller geriye dönük tam erişim.
		$req_sec = self::request_section();
		if ( $req_sec && ! self::section_allowed( $req_sec ) ) {
			wp_safe_redirect( self::url( '', 0, array( 'msg' => 'noperm' ) ) ); exit;
		}
		// --- Satır aksiyonları (GET, nonce'lu): çöpe at / geri yükle / kalıcı sil ---
		if ( ! empty( $_GET['row'] ) && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_row_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			if ( $o ) {
				$r = sanitize_key( $_GET['row'] );
				if ( $r === 'trash' ) { $o->delete( false ); }
				elseif ( $r === 'untrash' ) { $prev = $o->get_meta( '_wp_trash_meta_status' ); $o->set_status( $prev ? $prev : 'pending' ); $o->save(); }
				elseif ( $r === 'delete' ) { $o->delete( true ); }
			}
			$ret = isset( $_GET['ret_status'] ) ? sanitize_key( $_GET['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'orders', 0, array( 'status' => $ret ) ) : self::url( 'orders' ) ); exit;
		}

		// --- Ürün satır aksiyonları (GET): çöp / geri yükle / kalıcı sil ---
		if ( ! empty( $_GET['prow'] ) && current_user_can( 'edit_products' ) ) {
			$pid2 = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_prow_' . $pid2 );
			$r = sanitize_key( $_GET['prow'] );
			if ( $pid2 ) {
				if ( $r === 'trash' ) { wp_trash_post( $pid2 ); }
				elseif ( $r === 'untrash' ) { wp_untrash_post( $pid2 ); }
				elseif ( $r === 'delete' ) { wp_delete_post( $pid2, true ); }
			}
			$ret = isset( $_GET['ret_status'] ) ? sanitize_key( $_GET['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'products', 0, array( 'status' => $ret ) ) : self::url( 'products' ) ); exit;
		}

		// --- Sayfa satır eylemi (GET): çöp/geri/kalıcı sil ---
		if ( ! empty( $_GET['pgrow'] ) && current_user_can( 'edit_pages' ) ) {
			$pid2 = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_pgrow_' . $pid2 );
			$r = sanitize_key( $_GET['pgrow'] );
			if ( $pid2 ) {
				if ( $r === 'trash' ) { wp_trash_post( $pid2 ); }
				elseif ( $r === 'untrash' ) { wp_untrash_post( $pid2 ); }
				elseif ( $r === 'delete' ) { wp_delete_post( $pid2, true ); }
			}
			$ret = isset( $_GET['ret_status'] ) ? sanitize_key( $_GET['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'pages', 0, array( 'status' => $ret ) ) : self::url( 'pages' ) ); exit;
		}

		// --- Kullanıcı sil (GET) ---
		if ( ! empty( $_GET['urow'] ) && current_user_can( 'delete_users' ) ) {
			$uid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_urow_' . $uid );
			$msg = 'err';
			if ( $uid && $uid !== get_current_user_id() && current_user_can( 'delete_user', $uid ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				if ( wp_delete_user( $uid, get_current_user_id() ) ) { $msg = 'deleted'; }
			}
			wp_safe_redirect( self::url( 'users', 0, array( 'msg' => $msg ) ) ); exit;
		}

		// --- Kategori sil (GET) ---
		if ( ! empty( $_GET['crow'] ) && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_crow_' . $tid );
			if ( $tid && sanitize_key( $_GET['crow'] ) === 'delete' ) { wp_delete_term( $tid, 'product_cat' ); }
			wp_safe_redirect( self::url( 'categories' ) ); exit;
		}

		// --- Kupon satır aksiyonu (GET): çöp / geri yükle / sil ---
		if ( ! empty( $_GET['cuprow'] ) && current_user_can( 'edit_shop_coupons' ) ) {
			$cid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_cuprow_' . $cid );
			$r = sanitize_key( $_GET['cuprow'] );
			if ( $cid ) {
				if ( $r === 'trash' ) { wp_trash_post( $cid ); }
				elseif ( $r === 'untrash' ) { wp_untrash_post( $cid ); }
				elseif ( $r === 'delete' ) { wp_delete_post( $cid, true ); }
			}
			$ret = isset( $_GET['ret_status'] ) ? sanitize_key( $_GET['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'coupons', 0, array( 'status' => $ret ) ) : self::url( 'coupons' ) ); exit;
		}

		// --- Rapor dışa aktar (PDF / Excel) — GET, indirme ---
		if ( ! empty( $_GET['wcp_report'] ) && current_user_can( 'manage_woocommerce' ) && class_exists( 'WooCommerce' ) ) {
			check_admin_referer( 'wcp_report_export' );
			$fmt = sanitize_key( $_GET['wcp_report'] );
			$rr  = self::report_resolve_range(
				isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last7',
				isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
				isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
			);
			$D = self::report_data( $rr['from_ts'], $rr['to_ts'] );
			if ( $fmt === 'pdf' ) { self::output_report_pdf( $D, $rr ); }
			elseif ( $fmt === 'xls' ) { self::output_report_xls( $D, $rr ); }
			exit;
		}

		// --- İptal talebi onayla/reddet (GET) ---
		if ( ! empty( $_GET['crreq'] ) && current_user_can( 'edit_shop_orders' ) && class_exists( 'WooCommerce' ) ) {
			$oid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_crreq_' . $oid );
			$o = wc_get_order( $oid ); $a2 = sanitize_key( $_GET['crreq'] );
			if ( $o ) {
				if ( $a2 === 'approve' ) { $o->update_status( 'cancelled', 'İptal talebi onaylandı (panel).' ); }
				elseif ( $a2 === 'reject' ) { $o->update_status( 'processing', 'İptal talebi reddedildi (panel).' ); }
			}
			wp_safe_redirect( self::url( 'talepler' ) ); exit;
		}

		// --- CSV dışa aktarma (ürün / sipariş) ---
		if ( ! empty( $_GET['wcp_export'] ) && current_user_can( 'manage_woocommerce' ) && class_exists( 'WooCommerce' ) ) {
			check_admin_referer( 'wcp_export' );
			$what = sanitize_key( $_GET['wcp_export'] );
			nocache_headers();
			if ( $what === 'products' ) {
				header( 'Content-Type: text/csv; charset=UTF-8' );
				header( 'Content-Disposition: attachment; filename=urunler-' . date( 'Ymd-His' ) . '.csv' );
				$out = fopen( 'php://output', 'w' );
				fwrite( $out, "\xEF\xBB\xBF" );
				fputcsv( $out, array( 'sku', 'ad', 'duzenli_fiyat', 'satis_fiyati', 'stok', 'stok_durumu', 'durum', 'kategoriler', 'id' ) );
				$ids = wc_get_products( array( 'limit' => -1, 'status' => array( 'publish', 'draft', 'private' ), 'return' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) );
				foreach ( $ids as $pid ) { $p = wc_get_product( $pid ); if ( ! $p ) { continue; } $cats = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) ); fputcsv( $out, array( $p->get_sku(), $p->get_name(), $p->get_regular_price(), $p->get_sale_price(), $p->managing_stock() ? $p->get_stock_quantity() : '', $p->get_stock_status(), $p->get_status(), implode( '|', is_wp_error( $cats ) ? array() : $cats ), $pid ) ); }
				fclose( $out ); exit;
			}
			if ( $what === 'orders' ) {
				$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
				header( 'Content-Type: text/csv; charset=UTF-8' );
				header( 'Content-Disposition: attachment; filename=siparisler-' . $rr['from'] . '_' . $rr['to'] . '.csv' );
				$out = fopen( 'php://output', 'w' );
				fwrite( $out, "\xEF\xBB\xBF" );
				fputcsv( $out, array( 'siparis_no', 'tarih', 'durum', 'musteri', 'eposta', 'telefon', 'il', 'odeme', 'tutar', 'iade' ) );
				$orders = wc_get_orders( array( 'limit' => -1, 'date_created' => $rr['from_ts'] . '...' . $rr['to_ts'], 'status' => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ), 'orderby' => 'date', 'order' => 'DESC' ) );
				foreach ( $orders as $o ) { fputcsv( $out, array( $o->get_order_number(), $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d H:i' ) : '', wc_get_order_status_name( $o->get_status() ), trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), $o->get_billing_email(), $o->get_billing_phone(), $o->get_billing_state(), wp_strip_all_tags( $o->get_payment_method_title() ), $o->get_total(), $o->get_total_refunded() ) ); }
				fclose( $out ); exit;
			}
			exit;
		}

		if ( empty( $_POST['wcp_action'] ) ) { return; }
		$act = sanitize_key( $_POST['wcp_action'] );

		// --- Rapor e-posta gönder (PDF/Excel ekli) ---
		if ( $act === 'report_email' && current_user_can( 'manage_woocommerce' ) && class_exists( 'WooCommerce' ) ) {
			check_admin_referer( 'wcp_report_email' );
			$to = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$rr = self::report_resolve_range(
				isset( $_POST['range'] ) ? sanitize_key( $_POST['range'] ) : 'last7',
				isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '',
				isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : ''
			);
			$ok = false;
			if ( is_email( $to ) ) {
				$D    = self::report_data( $rr['from_ts'], $rr['to_ts'] );
				$dir  = trailingslashit( get_temp_dir() );
				$atts = array(); $tmp = array();
				if ( ! empty( $_POST['fmt_pdf'] ) ) {
					$pdf = self::output_report_pdf( $D, $rr, true );
					if ( $pdf ) { $f = $dir . self::report_filename( $rr, 'pdf' ); if ( file_put_contents( $f, $pdf ) ) { $atts[] = $f; $tmp[] = $f; } }
				}
				if ( ! empty( $_POST['fmt_xls'] ) ) {
					$xls = self::output_report_xls( $D, $rr, true );
					$f = $dir . self::report_filename( $rr, 'xls' ); if ( file_put_contents( $f, "\xEF\xBB\xBF" . $xls ) ) { $atts[] = $f; $tmp[] = $f; }
				}
				$subject = get_option( 'blogname' ) . ' — Satış Raporu (' . $rr['label'] . ')';
				$body    = self::report_doc( $D, $rr );
				$host = wp_parse_url( home_url(), PHP_URL_HOST ); $host = preg_replace( '/^www\./', '', (string) $host );
				$from = 'raporlama@' . ( $host ? $host : 'example.com' );
				$headers = array(
					'Content-Type: text/html; charset=UTF-8',
					'From: ' . get_option( 'blogname' ) . ' Raporlama <' . $from . '>',
				);
				$ok = wp_mail( $to, $subject, $body, $headers, $atts );
				foreach ( $tmp as $f ) { @unlink( $f ); }
			}
			wp_safe_redirect( self::url( 'reports', 0, array( 'range' => $rr['range'], 'from' => $rr['from'], 'to' => $rr['to'], 'msg' => $ok ? 'mailed' : 'mailfail' ) ) ); exit;
		}

		// --- Ürün toplu işlemler ---
		if ( $act === 'products_bulk' && current_user_can( 'edit_products' ) ) {
			check_admin_referer( 'wcp_products_bulk' );
			$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$do  = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
			foreach ( $ids as $pid2 ) {
				if ( $do === 'trash' ) { wp_trash_post( $pid2 ); }
				elseif ( $do === 'untrash' ) { wp_untrash_post( $pid2 ); }
				elseif ( $do === 'delete' ) { wp_delete_post( $pid2, true ); }
				elseif ( $do === 'publish' || $do === 'draft' ) { $pp = wc_get_product( $pid2 ); if ( $pp ) { $pp->set_status( $do ); $pp->save(); } }
			}
			$ret = isset( $_POST['ret_status'] ) ? sanitize_key( $_POST['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'products', 0, array( 'status' => $ret ) ) : self::url( 'products' ) ); exit;
		}

		// --- Sayfa kaydet (ekle/düzenle) ---
		if ( $act === 'page_save' && current_user_can( 'edit_pages' ) ) {
			$pid = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
			check_admin_referer( 'wcp_page_' . $pid );
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
			$content = $g( 'content' );
			if ( ! current_user_can( 'unfiltered_html' ) ) { $content = wp_kses_post( $content ); }
			$st = sanitize_key( $g( 'status' ) ); if ( ! in_array( $st, array( 'publish', 'draft', 'pending', 'private' ), true ) ) { $st = 'draft'; }
			$data = array(
				'post_type'    => 'page',
				'post_title'   => sanitize_text_field( $g( 'title' ) ),
				'post_content' => $content,
				'post_excerpt' => wp_kses_post( $g( 'excerpt' ) ),
				'post_status'  => $st,
				'post_parent'  => absint( $g( 'parent' ) ),
				'menu_order'   => (int) $g( 'menu_order' ),
			);
			if ( trim( $g( 'slug' ) ) !== '' ) { $data['post_name'] = sanitize_title( $g( 'slug' ) ); }
			if ( $pid ) { $data['ID'] = $pid; wp_update_post( $data ); } else { $pid = wp_insert_post( $data ); }
			if ( $pid && ! is_wp_error( $pid ) ) {
				$tpl = sanitize_text_field( $g( 'template' ) );
				update_post_meta( $pid, '_wp_page_template', $tpl ? $tpl : 'default' );
				$thumb = absint( $g( 'thumb_id' ) );
				if ( $thumb ) { set_post_thumbnail( $pid, $thumb ); } else { delete_post_thumbnail( $pid ); }
			}
			wp_safe_redirect( self::url( 'page', (int) $pid, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Sayfa toplu işlemler ---
		if ( $act === 'pages_bulk' && current_user_can( 'edit_pages' ) ) {
			check_admin_referer( 'wcp_pages_bulk' );
			$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$do  = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
			foreach ( $ids as $pid2 ) {
				if ( $do === 'trash' ) { wp_trash_post( $pid2 ); }
				elseif ( $do === 'untrash' ) { wp_untrash_post( $pid2 ); }
				elseif ( $do === 'delete' ) { wp_delete_post( $pid2, true ); }
				elseif ( $do === 'publish' || $do === 'draft' ) { wp_update_post( array( 'ID' => $pid2, 'post_status' => $do ) ); }
			}
			$ret = isset( $_POST['ret_status'] ) ? sanitize_key( $_POST['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'pages', 0, array( 'status' => $ret ) ) : self::url( 'pages' ) ); exit;
		}

		// --- Kullanıcı kaydet (ekle/düzenle) ---
		if ( $act === 'user_save' ) {
			$uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
			check_admin_referer( 'wcp_user_' . $uid );
			$is_new = ! $uid;
			$me = get_current_user_id();
			$allowed = $is_new ? current_user_can( 'create_users' ) : self::can_edit_target_user( $uid );
			if ( ! $allowed ) { wp_safe_redirect( self::url( 'users', 0, array( 'msg' => 'err' ) ) ); exit; }
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
			$email = sanitize_email( $g( 'email' ) );
			$role  = sanitize_key( $g( 'role' ) );
			$valid_role = in_array( $role, self::assignable_roles(), true ); // ayrıcalıklı role yükseltme engelli
			$pass  = (string) $g( 'pass' );
			$common = array(
				'first_name'  => sanitize_text_field( $g( 'first_name' ) ),
				'last_name'   => sanitize_text_field( $g( 'last_name' ) ),
				'user_url'    => esc_url_raw( $g( 'url' ) ),
				'description' => sanitize_textarea_field( $g( 'description' ) ),
				'user_email'  => $email,
			);
			$msg = 'err';
			if ( $is_new ) {
				$login = sanitize_user( $g( 'user_login' ), true );
				if ( $login && is_email( $email ) && ! email_exists( $email ) && ! username_exists( $login ) && $pass !== '' ) {
					$res = wp_insert_user( $common + array( 'user_login' => $login, 'user_pass' => $pass, 'role' => $valid_role ? $role : 'subscriber' ) );
					if ( ! is_wp_error( $res ) ) { $uid = (int) $res; $msg = 'saved'; }
				}
			} else {
				if ( is_email( $email ) ) {
					$data = $common + array( 'ID' => $uid );
					$dn = sanitize_text_field( $g( 'display_name' ) ); if ( $dn !== '' ) { $data['display_name'] = $dn; }
					if ( $pass !== '' ) { $data['user_pass'] = $pass; }
					if ( $valid_role && $uid !== $me && current_user_can( 'promote_user', $uid ) ) { $data['role'] = $role; }
					$res = wp_update_user( $data );
					if ( ! is_wp_error( $res ) ) { $msg = 'saved'; }
				}
			}
			wp_safe_redirect( self::url( 'users', 0, array( 'msg' => $msg ) ) ); exit;
		}

		// --- Yetkilendirme kaydet (rol bazlı menü erişimi) ---
		if ( $act === 'perms_save' && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'wcp_perms' );
			$in   = ( isset( $_POST['perm'] ) && is_array( $_POST['perm'] ) ) ? wp_unslash( $_POST['perm'] ) : array();
			$secs = array_keys( self::panel_sections() );
			$map  = array();
			foreach ( self::panel_roles() as $slug => $name ) {
				if ( $slug === 'administrator' ) { continue; } // yönetici her zaman tam erişim
				$list  = isset( $in[ $slug ] ) ? array_map( 'sanitize_key', (array) $in[ $slug ] ) : array();
				$clean = array_values( array_intersect( $secs, $list ) );
				$clean = array_values( array_diff( $clean, array( 'wp' ) ) ); // wp = her zaman yönetici-only
				$clean[] = 'dashboard';
				$map[ $slug ] = array_values( array_unique( $clean ) );
			}
			update_option( 'wcp_panel_access', $map, false );
			wp_safe_redirect( self::url( 'perms', 0, array( 'msg' => 'saved' ) ) ); exit;
		}

		// --- Promosyon (hediye) ayarları kaydet ---
		if ( $act === 'promosyon_save' && current_user_can( 'manage_woocommerce' ) ) {
			check_admin_referer( 'wcp_promosyon' );
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
			$set = array(
				'enabled'          => empty( $_POST['enabled'] ) ? 0 : 1,
				'per_product'      => empty( $_POST['per_product'] ) ? 0 : 1,
				'by_category'      => empty( $_POST['by_category'] ) ? 0 : 1,
				'by_threshold'     => empty( $_POST['by_threshold'] ) ? 0 : 1,
				'title'            => sanitize_text_field( $g( 'title' ) ) ?: 'STORE ÖZEL HEDİYE',
				'threshold_amount' => (float) wc_format_decimal( $g( 'threshold_amount' ) ),
				'threshold_gift'   => 0,
				'cat_map'          => array(),
			);
			$tg = self::ids_from_refs( $g( 'threshold_gift' ) );
			$set['threshold_gift'] = $tg ? (int) $tg[0] : 0;
			if ( isset( $_POST['catgift'] ) && is_array( $_POST['catgift'] ) ) {
				foreach ( $_POST['catgift'] as $tid => $ref ) {
					$ids = self::ids_from_refs( wp_unslash( $ref ) );
					if ( $ids ) { $set['cat_map'][ (int) $tid ] = $ids; }
				}
			}
			update_option( 'wcp_gift_settings', $set, false );
			wp_safe_redirect( self::url( 'promosyon', 0, array( 'msg' => 'saved' ) ) ); exit;
		}

		// --- Ayarlar kaydet (bölüm bazlı) ---
		if ( $act === 'settings_save' && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'wcp_settings' );
			$sec = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';
			$g   = function ( $k ) { return isset( $_POST[ $k ] ) ? trim( wp_unslash( $_POST[ $k ] ) ) : ''; };
			if ( $sec === 'store' ) {
				update_option( 'blogname', sanitize_text_field( $g( 'blogname' ) ) );
				update_option( 'blogdescription', sanitize_text_field( $g( 'blogdescription' ) ) );
				$email = sanitize_email( $g( 'admin_email' ) );
				if ( is_email( $email ) ) { update_option( 'admin_email', $email ); }
				update_option( 'woocommerce_store_address', sanitize_text_field( $g( 'store_address' ) ) );
				update_option( 'woocommerce_store_address_2', sanitize_text_field( $g( 'store_address_2' ) ) );
				update_option( 'woocommerce_store_city', sanitize_text_field( $g( 'store_city' ) ) );
				update_option( 'woocommerce_store_postcode', sanitize_text_field( $g( 'store_postcode' ) ) );
				$cc = sanitize_text_field( $g( 'store_country' ) );
				$st = sanitize_text_field( $g( 'store_state' ) );
				if ( $cc !== '' ) { update_option( 'woocommerce_default_country', $st !== '' ? $cc . ':' . $st : $cc ); }
			} elseif ( $sec === 'currency' ) {
				update_option( 'woocommerce_currency', sanitize_text_field( $g( 'currency' ) ) );
				$pos = $g( 'currency_pos' );
				update_option( 'woocommerce_currency_pos', in_array( $pos, array( 'left', 'right', 'left_space', 'right_space' ), true ) ? $pos : 'left' );
				update_option( 'woocommerce_price_thousand_sep', $g( 'thousand_sep' ) );
				update_option( 'woocommerce_price_decimal_sep', $g( 'decimal_sep' ) );
				update_option( 'woocommerce_price_num_decimals', max( 0, (int) $g( 'num_decimals' ) ) );
			} elseif ( $sec === 'stock' ) {
				update_option( 'woocommerce_manage_stock', ! empty( $_POST['manage_stock'] ) ? 'yes' : 'no' );
				update_option( 'woocommerce_notify_low_stock_amount', max( 0, (int) $g( 'low_stock_amount' ) ) );
				update_option( 'woocommerce_notify_no_stock_amount', max( 0, (int) $g( 'no_stock_amount' ) ) );
				update_option( 'woocommerce_hide_out_of_stock_items', ! empty( $_POST['hide_oos'] ) ? 'yes' : 'no' );
				$sf = $g( 'stock_format' );
				update_option( 'woocommerce_stock_format', in_array( $sf, array( '', 'low_amount', 'no_amount' ), true ) ? $sf : '' );
			}
			wp_safe_redirect( self::url( 'settings', 0, array( 'msg' => 'saved', 'sec' => $sec ) ) ); exit;
		}

		// --- Kategori kaydet (ekle/düzenle) ---
		if ( $act === 'category_save' && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
			check_admin_referer( 'wcp_category_' . $tid );
			$name = isset( $_POST['cat_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cat_name'] ) ) : '';
			if ( $name === '' ) { wp_safe_redirect( self::url( 'categories' ) ); exit; }
			$args = array(
				'parent'      => isset( $_POST['cat_parent'] ) ? absint( $_POST['cat_parent'] ) : 0,
				'description' => isset( $_POST['cat_desc'] ) ? wp_kses_post( wp_unslash( $_POST['cat_desc'] ) ) : '',
			);
			if ( ! empty( $_POST['cat_slug'] ) ) { $args['slug'] = sanitize_title( wp_unslash( $_POST['cat_slug'] ) ); }
			if ( $tid ) {
				$args['name'] = $name;
				wp_update_term( $tid, 'product_cat', $args );
			} else {
				$res = wp_insert_term( $name, 'product_cat', $args );
				if ( ! is_wp_error( $res ) ) { $tid = $res['term_id']; }
			}
			if ( $tid ) {
				if ( isset( $_POST['cat_thumb_id'] ) ) { update_term_meta( $tid, 'thumbnail_id', absint( $_POST['cat_thumb_id'] ) ); }
				if ( isset( $_POST['cat_display'] ) ) { update_term_meta( $tid, 'display_type', sanitize_key( $_POST['cat_display'] ) ); }
			}
			wp_safe_redirect( self::url( 'categories', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Ürün etiketi sil (GET) ---
		if ( ! empty( $_GET['trow'] ) && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_trow_' . $tid );
			if ( $tid && sanitize_key( $_GET['trow'] ) === 'delete' ) { wp_delete_term( $tid, 'product_tag' ); }
			wp_safe_redirect( self::url( 'tags' ) ); exit;
		}

		// --- Ürün etiketi kaydet (ekle/düzenle) ---
		if ( $act === 'tag_save' && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
			check_admin_referer( 'wcp_tag_' . $tid );
			$name = isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
			if ( $name === '' ) { wp_safe_redirect( self::url( 'tags' ) ); exit; }
			$args = array( 'description' => isset( $_POST['tag_desc'] ) ? wp_kses_post( wp_unslash( $_POST['tag_desc'] ) ) : '' );
			if ( ! empty( $_POST['tag_slug'] ) ) { $args['slug'] = sanitize_title( wp_unslash( $_POST['tag_slug'] ) ); }
			if ( $tid ) { $args['name'] = $name; wp_update_term( $tid, 'product_tag', $args ); }
			else { wp_insert_term( $name, 'product_tag', $args ); }
			wp_safe_redirect( self::url( 'tags', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Marka sil (GET) ---
		if ( ! empty( $_GET['brrow'] ) && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_brrow_' . $tid );
			if ( $tid && sanitize_key( $_GET['brrow'] ) === 'delete' ) { wp_delete_term( $tid, 'product_brand' ); }
			wp_safe_redirect( self::url( 'brands' ) ); exit;
		}

		// --- Marka kaydet (ekle/düzenle) ---
		if ( $act === 'brand_save' && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
			check_admin_referer( 'wcp_brand_' . $tid );
			$name = isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '';
			if ( $name === '' ) { wp_safe_redirect( self::url( 'brands' ) ); exit; }
			$args = array( 'description' => isset( $_POST['brand_desc'] ) ? wp_kses_post( wp_unslash( $_POST['brand_desc'] ) ) : '' );
			if ( ! empty( $_POST['brand_slug'] ) ) { $args['slug'] = sanitize_title( wp_unslash( $_POST['brand_slug'] ) ); }
			if ( $tid ) { $args['name'] = $name; wp_update_term( $tid, 'product_brand', $args ); }
			else { $res = wp_insert_term( $name, 'product_brand', $args ); if ( ! is_wp_error( $res ) ) { $tid = $res['term_id']; } }
			if ( $tid && isset( $_POST['brand_thumb_id'] ) ) { update_term_meta( $tid, 'thumbnail_id', absint( $_POST['brand_thumb_id'] ) ); }
			wp_safe_redirect( self::url( 'brands', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Toplu ürün düzenleme ---
		if ( $act === 'bulk_apply' && current_user_can( 'edit_products' ) ) {
			check_admin_referer( 'wcp_bulk' );
			$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
			$val    = isset( $_POST['bulk_val'] ) ? wp_unslash( $_POST['bulk_val'] ) : '';
			$fv     = (float) wc_format_decimal( $val );
			$n = 0;
			foreach ( $ids as $pid ) {
				$p = wc_get_product( $pid ); if ( ! $p ) { continue; }
				$r = (float) $p->get_regular_price();
				switch ( $action ) {
					case 'fiyat_set':      $p->set_regular_price( wc_format_decimal( $val ) ); break;
					case 'fiyat_pct_up':   if ( $r > 0 ) { $p->set_regular_price( wc_format_decimal( $r * ( 1 + $fv / 100 ) ) ); } break;
					case 'fiyat_pct_down': if ( $r > 0 ) { $p->set_regular_price( wc_format_decimal( $r * ( 1 - $fv / 100 ) ) ); } break;
					case 'fiyat_add':      $p->set_regular_price( wc_format_decimal( $r + $fv ) ); break;
					case 'fiyat_sub':      $p->set_regular_price( wc_format_decimal( max( 0, $r - $fv ) ) ); break;
					case 'satis_set':      $p->set_sale_price( $val !== '' ? wc_format_decimal( $val ) : '' ); break;
					case 'satis_pct':      if ( $r > 0 ) { $p->set_sale_price( wc_format_decimal( $r * ( 1 - $fv / 100 ) ) ); } break;
					case 'satis_clear':    $p->set_sale_price( '' ); break;
					case 'stok_set':       $p->set_manage_stock( true ); $p->set_stock_quantity( wc_stock_amount( $val ) ); break;
					case 'stok_add':       $p->set_manage_stock( true ); $p->set_stock_quantity( wc_stock_amount( (float) $p->get_stock_quantity() + $fv ) ); break;
					case 'stok_sub':       $p->set_manage_stock( true ); $p->set_stock_quantity( wc_stock_amount( max( 0, (float) $p->get_stock_quantity() - $fv ) ) ); break;
					case 'durum_instock':  $p->set_stock_status( 'instock' ); break;
					case 'durum_outofstock': $p->set_stock_status( 'outofstock' ); break;
					case 'yayin_publish':  $p->set_status( 'publish' ); break;
					case 'yayin_draft':    $p->set_status( 'draft' ); break;
				}
				$p->save(); $n++;
			}
			$rargs = array( 'msg' => 'ok', 'n' => $n );
			if ( ! empty( $_POST['cat'] ) ) { $rargs['cat'] = sanitize_text_field( wp_unslash( $_POST['cat'] ) ); }
			wp_safe_redirect( self::url( 'toplu-urun', 0, $rargs ) ); exit;
		}

		// --- Ürün CSV içe aktar (SKU ile güncelle) ---
		if ( $act === 'import_products' && current_user_can( 'edit_products' ) && class_exists( 'WooCommerce' ) ) {
			check_admin_referer( 'wcp_import' );
			$updated = 0; $skipped = 0;
			if ( ! empty( $_FILES['csv']['tmp_name'] ) && is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
				$fh = fopen( $_FILES['csv']['tmp_name'], 'r' );
				if ( $fh ) {
					$head = fgetcsv( $fh );
					$map = array();
					if ( is_array( $head ) ) { foreach ( $head as $i => $h ) { $map[ trim( mb_strtolower( preg_replace( '/^\xEF\xBB\xBF/', '', $h ) ) ) ] = $i; } }
					$get = function ( $row, $key ) use ( $map ) { return isset( $map[ $key ], $row[ $map[ $key ] ] ) ? trim( $row[ $map[ $key ] ] ) : null; };
					while ( ( $row = fgetcsv( $fh ) ) !== false ) {
						$sku = $get( $row, 'sku' );
						if ( ! $sku ) { $skipped++; continue; }
						$pid = wc_get_product_id_by_sku( $sku );
						if ( ! $pid ) { $skipped++; continue; }
						$p = wc_get_product( $pid ); if ( ! $p ) { $skipped++; continue; }
						$ch = false;
						$rp = $get( $row, 'duzenli_fiyat' ); if ( $rp !== null && $rp !== '' ) { $p->set_regular_price( wc_format_decimal( $rp ) ); $ch = true; }
						$sp = $get( $row, 'satis_fiyati' ); if ( $sp !== null ) { $p->set_sale_price( $sp !== '' ? wc_format_decimal( $sp ) : '' ); $ch = true; }
						$stk = $get( $row, 'stok' ); if ( $stk !== null && $stk !== '' ) { $p->set_manage_stock( true ); $p->set_stock_quantity( wc_stock_amount( $stk ) ); $ch = true; }
						$du = $get( $row, 'durum' ); if ( $du ) { $du = sanitize_key( $du ); if ( in_array( $du, array( 'publish', 'draft', 'private' ), true ) ) { $p->set_status( $du ); $ch = true; } }
						$sd = $get( $row, 'stok_durumu' ); if ( $sd ) { $sd = sanitize_key( $sd ); if ( in_array( $sd, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) { $p->set_stock_status( $sd ); $ch = true; } }
						if ( $ch ) { $p->save(); $updated++; } else { $skipped++; }
					}
					fclose( $fh );
				}
			}
			wp_safe_redirect( self::url( 'aktar', 0, array( 'imp' => 'ok', 'u' => $updated, 's' => $skipped ) ) ); exit;
		}

		// --- Nitelik (attribute) sil (GET) ---
		if ( ! empty( $_GET['arow'] ) && current_user_can( 'manage_woocommerce' ) ) {
			$aid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_arow_' . $aid );
			if ( $aid && sanitize_key( $_GET['arow'] ) === 'delete' && function_exists( 'wc_delete_attribute' ) ) { wc_delete_attribute( $aid ); }
			wp_safe_redirect( self::url( 'attributes' ) ); exit;
		}

		// --- Nitelik kaydet (ekle/düzenle) ---
		if ( $act === 'attr_save' && current_user_can( 'manage_woocommerce' ) && function_exists( 'wc_create_attribute' ) ) {
			$aid = isset( $_POST['attr_id'] ) ? absint( $_POST['attr_id'] ) : 0;
			check_admin_referer( 'wcp_attr_' . $aid );
			$label = isset( $_POST['attr_label'] ) ? sanitize_text_field( wp_unslash( $_POST['attr_label'] ) ) : '';
			if ( $label === '' ) { wp_safe_redirect( self::url( 'attributes' ) ); exit; }
			$slug = ( isset( $_POST['attr_slug'] ) && $_POST['attr_slug'] !== '' ) ? wc_sanitize_taxonomy_name( wp_unslash( $_POST['attr_slug'] ) ) : wc_sanitize_taxonomy_name( $label );
			$data = array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => isset( $_POST['attr_type'] ) ? sanitize_text_field( wp_unslash( $_POST['attr_type'] ) ) : 'select',
				'order_by'     => isset( $_POST['attr_orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['attr_orderby'] ) ) : 'menu_order',
				'has_archives' => ! empty( $_POST['attr_archives'] ),
			);
			if ( $aid ) { wc_update_attribute( $aid, $data ); }
			else { $res = wc_create_attribute( $data ); if ( ! is_wp_error( $res ) ) { $aid = (int) $res; } }
			wp_safe_redirect( $aid ? self::url( 'attribute', $aid, array( 'msg' => 'ok' ) ) : self::url( 'attributes' ) ); exit;
		}

		// --- Nitelik değeri (term) kaydet ---
		if ( $act === 'attrterm_save' && current_user_can( 'manage_product_terms' ) ) {
			$aid = isset( $_POST['attr_id'] ) ? absint( $_POST['attr_id'] ) : 0;
			check_admin_referer( 'wcp_attrterm_' . $aid );
			$tax  = isset( $_POST['attr_tax'] ) ? sanitize_text_field( wp_unslash( $_POST['attr_tax'] ) ) : '';
			$tid  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
			$name = isset( $_POST['term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['term_name'] ) ) : '';
			if ( $tax && taxonomy_exists( $tax ) && $name !== '' ) {
				$targs = array( 'description' => isset( $_POST['term_desc'] ) ? wp_kses_post( wp_unslash( $_POST['term_desc'] ) ) : '' );
				if ( ! empty( $_POST['term_slug'] ) ) { $targs['slug'] = sanitize_title( wp_unslash( $_POST['term_slug'] ) ); }
				if ( $tid ) { $targs['name'] = $name; wp_update_term( $tid, $tax, $targs ); }
				else { wp_insert_term( $name, $tax, $targs ); }
			}
			wp_safe_redirect( self::url( 'attribute', $aid, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Nitelik değeri sil (GET) ---
		if ( ! empty( $_GET['atrow'] ) && current_user_can( 'manage_product_terms' ) ) {
			$tid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			$tax = isset( $_GET['tax'] ) ? sanitize_text_field( wp_unslash( $_GET['tax'] ) ) : '';
			$aid = isset( $_GET['aid'] ) ? absint( $_GET['aid'] ) : 0;
			check_admin_referer( 'wcp_atrow_' . $tid );
			if ( $tid && $tax && taxonomy_exists( $tax ) && sanitize_key( $_GET['atrow'] ) === 'delete' ) { wp_delete_term( $tid, $tax ); }
			wp_safe_redirect( self::url( 'attribute', $aid ) ); exit;
		}

		// --- Kargo bölgesi ekle ---
		if ( $act === 'ship_zone_add' && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Shipping_Zone' ) ) {
			check_admin_referer( 'wcp_ship' );
			$name = isset( $_POST['zone_name'] ) ? sanitize_text_field( wp_unslash( $_POST['zone_name'] ) ) : '';
			if ( $name !== '' ) {
				$z = new WC_Shipping_Zone();
				$z->set_zone_name( $name );
				$locs = array();
				foreach ( (array) ( isset( $_POST['zone_countries'] ) ? $_POST['zone_countries'] : array() ) as $c ) { $c = wc_clean( wp_unslash( $c ) ); if ( $c ) { $locs[] = array( 'code' => $c, 'type' => 'country' ); } }
				if ( $locs ) { $z->set_locations( $locs ); }
				$z->save();
			}
			wp_safe_redirect( self::url( 'shipping', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Kargo bölgesi sil (GET) ---
		if ( ! empty( $_GET['zonedel'] ) && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$zid = absint( $_GET['zonedel'] );
			check_admin_referer( 'wcp_zonedel_' . $zid );
			WC_Shipping_Zones::delete_zone( $zid );
			wp_safe_redirect( self::url( 'shipping' ) ); exit;
		}

		// --- Bölgeye kargo yöntemi ekle ---
		if ( $act === 'ship_method_add' && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$zid = isset( $_POST['zone_id'] ) ? absint( $_POST['zone_id'] ) : 0;
			check_admin_referer( 'wcp_shipmethod_' . $zid );
			$type = isset( $_POST['method_type'] ) ? wc_clean( wp_unslash( $_POST['method_type'] ) ) : '';
			$z = WC_Shipping_Zones::get_zone( $zid );
			if ( $z && in_array( $type, array( 'flat_rate', 'free_shipping', 'local_pickup' ), true ) ) { $z->add_shipping_method( $type ); }
			wp_safe_redirect( self::url( 'shipping', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Kargo yöntemi sil (GET) ---
		if ( ! empty( $_GET['methoddel'] ) && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$zid = isset( $_GET['zone'] ) ? absint( $_GET['zone'] ) : 0;
			$iid = absint( $_GET['methoddel'] );
			check_admin_referer( 'wcp_methoddel_' . $iid );
			$z = WC_Shipping_Zones::get_zone( $zid );
			if ( $z ) { $z->delete_shipping_method( $iid ); }
			wp_safe_redirect( self::url( 'shipping' ) ); exit;
		}

		// --- Kargo yöntemi ayarlarını kaydet (başlık / ücret / durum) ---
		if ( $act === 'ship_method_save' && current_user_can( 'manage_woocommerce' ) ) {
			$iid = isset( $_POST['instance_id'] ) ? absint( $_POST['instance_id'] ) : 0;
			check_admin_referer( 'wcp_methodsave_' . $iid );
			$mtype = isset( $_POST['method_type'] ) ? wc_clean( wp_unslash( $_POST['method_type'] ) ) : '';
			$okey  = 'woocommerce_' . $mtype . '_' . $iid . '_settings';
			$s = get_option( $okey, array() ); if ( ! is_array( $s ) ) { $s = array(); }
			if ( isset( $_POST['m_title'] ) ) { $s['title'] = sanitize_text_field( wp_unslash( $_POST['m_title'] ) ); }
			if ( isset( $_POST['m_cost'] ) ) { $s['cost'] = wc_format_decimal( wp_unslash( $_POST['m_cost'] ) ); }
			if ( isset( $_POST['m_min_amount'] ) ) { $s['min_amount'] = wc_format_decimal( wp_unslash( $_POST['m_min_amount'] ) ); }
			if ( $mtype === 'free_shipping' && isset( $_POST['m_requires'] ) ) { $s['requires'] = wc_clean( wp_unslash( $_POST['m_requires'] ) ); }
			update_option( $okey, $s );
			global $wpdb;
			$en = ! empty( $_POST['m_enabled'] ) ? 1 : 0;
			$wpdb->update( "{$wpdb->prefix}woocommerce_shipping_zone_methods", array( 'is_enabled' => $en ), array( 'instance_id' => $iid ) );
			if ( class_exists( 'WC_Cache_Helper' ) ) { WC_Cache_Helper::get_transient_version( 'shipping', true ); }
			wp_safe_redirect( self::url( 'shipping', 0, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Vergi oranı kaydet (ekle/düzenle) ---
		if ( $act === 'tax_save' && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Tax' ) ) {
			$rid = isset( $_POST['rate_id'] ) ? absint( $_POST['rate_id'] ) : 0;
			check_admin_referer( 'wcp_tax' );
			$class = isset( $_POST['rate_class'] ) ? sanitize_title( wp_unslash( $_POST['rate_class'] ) ) : '';
			if ( $class === 'standard' ) { $class = ''; }
			$rate = array(
				'tax_rate_country'  => isset( $_POST['rate_country'] ) ? wc_clean( wp_unslash( $_POST['rate_country'] ) ) : '',
				'tax_rate_state'    => isset( $_POST['rate_state'] ) ? wc_clean( wp_unslash( $_POST['rate_state'] ) ) : '',
				'tax_rate'          => isset( $_POST['rate_percent'] ) ? (string) wc_format_decimal( wp_unslash( $_POST['rate_percent'] ) ) : '0',
				'tax_rate_name'     => isset( $_POST['rate_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_name'] ) ) : '',
				'tax_rate_priority' => isset( $_POST['rate_priority'] ) ? absint( $_POST['rate_priority'] ) : 1,
				'tax_rate_compound' => ! empty( $_POST['rate_compound'] ) ? 1 : 0,
				'tax_rate_shipping' => ! empty( $_POST['rate_shipping'] ) ? 1 : 0,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => $class,
			);
			if ( $rid ) { WC_Tax::_update_tax_rate( $rid, $rate ); }
			else { $rid = WC_Tax::_insert_tax_rate( $rate ); }
			if ( $rid ) {
				WC_Tax::_update_tax_rate_postcodes( $rid, isset( $_POST['rate_postcode'] ) ? wc_clean( wp_unslash( $_POST['rate_postcode'] ) ) : '' );
				WC_Tax::_update_tax_rate_cities( $rid, isset( $_POST['rate_city'] ) ? wc_clean( wp_unslash( $_POST['rate_city'] ) ) : '' );
			}
			wp_safe_redirect( self::url( 'tax', 0, array( 'class' => $class === '' ? 'standard' : $class, 'msg' => 'ok' ) ) ); exit;
		}

		// --- Vergi oranı sil (GET) ---
		if ( ! empty( $_GET['taxdel'] ) && current_user_can( 'manage_woocommerce' ) && class_exists( 'WC_Tax' ) ) {
			$rid = absint( $_GET['taxdel'] );
			check_admin_referer( 'wcp_taxdel_' . $rid );
			WC_Tax::_delete_tax_rate( $rid );
			wp_safe_redirect( self::url( 'tax', 0, array( 'class' => isset( $_GET['class'] ) ? sanitize_title( wp_unslash( $_GET['class'] ) ) : 'standard' ) ) ); exit;
		}

		// --- Mağaza Ayarları: Genel ---
		if ( $act === 'wcset_general' && current_user_can( 'manage_woocommerce' ) ) {
			check_admin_referer( 'wcp_wcset' );
			$map = array(
				'woocommerce_store_address' => 'store_address', 'woocommerce_store_address_2' => 'store_address_2',
				'woocommerce_store_city' => 'store_city', 'woocommerce_store_postcode' => 'store_postcode',
				'woocommerce_default_country' => 'default_country', 'woocommerce_currency' => 'currency',
				'woocommerce_currency_pos' => 'currency_pos', 'woocommerce_price_thousand_sep' => 'thousand_sep',
				'woocommerce_price_decimal_sep' => 'decimal_sep', 'woocommerce_price_num_decimals' => 'num_decimals',
			);
			foreach ( $map as $opt => $f ) { if ( isset( $_POST[ $f ] ) ) { update_option( $opt, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) ); } }
			update_option( 'woocommerce_calc_taxes', ! empty( $_POST['calc_taxes'] ) ? 'yes' : 'no' );
			update_option( 'woocommerce_enable_coupons', ! empty( $_POST['enable_coupons'] ) ? 'yes' : 'no' );
			wp_safe_redirect( self::url( 'wc-ayarlar', 0, array( 'tab' => 'genel', 'msg' => 'ok' ) ) ); exit;
		}

		// --- Mağaza Ayarları: Ödeme yöntemi ---
		if ( $act === 'wcset_payment' && current_user_can( 'manage_woocommerce' ) ) {
			$gid = isset( $_POST['gateway_id'] ) ? sanitize_key( $_POST['gateway_id'] ) : '';
			check_admin_referer( 'wcp_pay_' . $gid );
			if ( $gid ) {
				$okey = 'woocommerce_' . $gid . '_settings';
				$s = get_option( $okey, array() ); if ( ! is_array( $s ) ) { $s = array(); }
				$s['enabled'] = ! empty( $_POST['p_enabled'] ) ? 'yes' : 'no';
				if ( isset( $_POST['p_title'] ) ) { $s['title'] = sanitize_text_field( wp_unslash( $_POST['p_title'] ) ); }
				if ( isset( $_POST['p_description'] ) ) { $s['description'] = wp_kses_post( wp_unslash( $_POST['p_description'] ) ); }
				update_option( $okey, $s );
			}
			wp_safe_redirect( self::url( 'wc-ayarlar', 0, array( 'tab' => 'odeme', 'msg' => 'ok' ) ) ); exit;
		}

		// --- Mağaza Ayarları: E-posta (genel görünüm/gönderen) ---
		if ( $act === 'wcset_email' && current_user_can( 'manage_woocommerce' ) ) {
			check_admin_referer( 'wcp_wcemail' );
			if ( isset( $_POST['from_name'] ) ) { update_option( 'woocommerce_email_from_name', sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) ); }
			if ( isset( $_POST['from_address'] ) ) { update_option( 'woocommerce_email_from_address', sanitize_email( wp_unslash( $_POST['from_address'] ) ) ); }
			if ( isset( $_POST['header_image'] ) ) { update_option( 'woocommerce_email_header_image', esc_url_raw( wp_unslash( $_POST['header_image'] ) ) ); }
			if ( isset( $_POST['footer_text'] ) ) { update_option( 'woocommerce_email_footer_text', wp_kses_post( wp_unslash( $_POST['footer_text'] ) ) ); }
			if ( isset( $_POST['base_color'] ) ) { $hc = sanitize_hex_color( wp_unslash( $_POST['base_color'] ) ); if ( $hc ) { update_option( 'woocommerce_email_base_color', $hc ); } }
			wp_safe_redirect( self::url( 'wc-ayarlar', 0, array( 'tab' => 'eposta', 'msg' => 'ok' ) ) ); exit;
		}

		// --- Mağaza Ayarları: E-posta türü (aç/kapa + alıcı) ---
		if ( $act === 'wcset_email_item' && current_user_can( 'manage_woocommerce' ) ) {
			$eid = isset( $_POST['email_id'] ) ? sanitize_key( $_POST['email_id'] ) : '';
			check_admin_referer( 'wcp_emailitem_' . $eid );
			if ( $eid ) {
				$okey = 'woocommerce_' . $eid . '_settings';
				$s = get_option( $okey, array() ); if ( ! is_array( $s ) ) { $s = array(); }
				$s['enabled'] = ! empty( $_POST['e_enabled'] ) ? 'yes' : 'no';
				if ( isset( $_POST['e_recipient'] ) ) { $s['recipient'] = sanitize_text_field( wp_unslash( $_POST['e_recipient'] ) ); }
				update_option( $okey, $s );
			}
			wp_safe_redirect( self::url( 'wc-ayarlar', 0, array( 'tab' => 'eposta', 'msg' => 'ok' ) ) ); exit;
		}

		// --- Kupon toplu işlemler ---
		if ( $act === 'coupons_bulk' && current_user_can( 'edit_shop_coupons' ) ) {
			check_admin_referer( 'wcp_coupons_bulk' );
			$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$do  = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
			foreach ( $ids as $cid ) {
				if ( $do === 'trash' ) { wp_trash_post( $cid ); }
				elseif ( $do === 'untrash' ) { wp_untrash_post( $cid ); }
				elseif ( $do === 'delete' ) { wp_delete_post( $cid, true ); }
				elseif ( $do === 'publish' || $do === 'draft' ) { wp_update_post( array( 'ID' => $cid, 'post_status' => $do ) ); }
			}
			$ret = isset( $_POST['ret_status'] ) ? sanitize_key( $_POST['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'coupons', 0, array( 'status' => $ret ) ) : self::url( 'coupons' ) ); exit;
		}

		// --- Kupon kaydet ---
		if ( $act === 'coupon_save' && current_user_can( 'edit_shop_coupons' ) ) {
			$cid = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
			check_admin_referer( 'wcp_coupon_' . $cid );
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
			$code = sanitize_text_field( $g( 'code' ) );
			if ( $code === '' ) { wp_safe_redirect( self::url( 'coupons' ) ); exit; }
			$co = $cid ? new WC_Coupon( $cid ) : new WC_Coupon();
			$co->set_code( $code );
			$co->set_description( wp_kses_post( $g( 'description' ) ) );
			$co->set_discount_type( in_array( $g( 'discount_type' ), array( 'percent', 'fixed_cart', 'fixed_product' ), true ) ? $g( 'discount_type' ) : 'fixed_cart' );
			$co->set_amount( wc_format_decimal( $g( 'amount' ) ) );
			$co->set_free_shipping( ! empty( $_POST['free_shipping'] ) );
			$co->set_date_expires( $g( 'date_expires' ) !== '' ? wc_clean( $g( 'date_expires' ) ) : null );
			$co->set_minimum_amount( $g( 'minimum_amount' ) !== '' ? wc_format_decimal( $g( 'minimum_amount' ) ) : '' );
			$co->set_maximum_amount( $g( 'maximum_amount' ) !== '' ? wc_format_decimal( $g( 'maximum_amount' ) ) : '' );
			$co->set_individual_use( ! empty( $_POST['individual_use'] ) );
			$co->set_exclude_sale_items( ! empty( $_POST['exclude_sale_items'] ) );
			$co->set_product_ids( self::ids_from_refs( $g( 'product_ids' ) ) );
			$co->set_excluded_product_ids( self::ids_from_refs( $g( 'excluded_product_ids' ) ) );
			$co->set_product_categories( isset( $_POST['product_categories'] ) ? array_map( 'intval', (array) $_POST['product_categories'] ) : array() );
			$co->set_excluded_product_categories( isset( $_POST['excluded_product_categories'] ) ? array_map( 'intval', (array) $_POST['excluded_product_categories'] ) : array() );
			$co->set_email_restrictions( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $g( 'email_restrictions' ) ) ) ) ) );
			$co->set_usage_limit( $g( 'usage_limit' ) !== '' ? absint( $g( 'usage_limit' ) ) : 0 );
			$co->set_limit_usage_to_x_items( $g( 'limit_usage_to_x_items' ) !== '' ? absint( $g( 'limit_usage_to_x_items' ) ) : null );
			$co->set_usage_limit_per_user( $g( 'usage_limit_per_user' ) !== '' ? absint( $g( 'usage_limit_per_user' ) ) : 0 );
			$newid = $co->save();
			wp_update_post( array( 'ID' => $newid, 'post_status' => in_array( $g( 'status' ), array( 'publish', 'draft' ), true ) ? $g( 'status' ) : 'publish' ) );
			wp_safe_redirect( self::url( 'coupon', $newid, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Müşteri kaydet ---
		if ( $act === 'customer_save' && current_user_can( 'edit_users' ) ) {
			$uid = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
			check_admin_referer( 'wcp_customer_' . $uid );
			if ( ! $uid || ! self::can_edit_target_user( $uid ) ) { wp_safe_redirect( self::url( 'customers', 0, array( 'msg' => 'err' ) ) ); exit; }
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? trim( wp_unslash( $_POST[ $k ] ) ) : ''; };
			$ud = array( 'ID' => $uid );
			$ud['first_name']   = sanitize_text_field( $g( 'first_name' ) );
			$ud['last_name']    = sanitize_text_field( $g( 'last_name' ) );
			$ud['display_name'] = sanitize_text_field( $g( 'display_name' ) );
			$email = sanitize_email( $g( 'user_email' ) );
			if ( $email && is_email( $email ) ) { $ud['user_email'] = $email; }
			if ( $g( 'new_password' ) !== '' ) { $ud['user_pass'] = $g( 'new_password' ); }
			$role = sanitize_key( $g( 'role' ) );
			if ( $role && in_array( $role, self::assignable_roles(), true ) && current_user_can( 'promote_user', $uid ) ) { $ud['role'] = $role; }
			wp_update_user( $ud );
			$bfields = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );
			foreach ( $bfields as $f ) {
				if ( isset( $_POST[ 'billing_' . $f ] ) ) {
					$val = $f === 'email' ? sanitize_email( $g( 'billing_' . $f ) ) : sanitize_text_field( $g( 'billing_' . $f ) );
					update_user_meta( $uid, 'billing_' . $f, $val );
				}
			}
			$sfields = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );
			foreach ( $sfields as $f ) {
				if ( isset( $_POST[ 'shipping_' . $f ] ) ) { update_user_meta( $uid, 'shipping_' . $f, sanitize_text_field( $g( 'shipping_' . $f ) ) ); }
			}
			wp_safe_redirect( self::url( 'customer', $uid, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Değerlendirme satır aksiyonu (GET) ---
		if ( ! empty( $_GET['rvrow'] ) && current_user_can( 'moderate_comments' ) ) {
			$cid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'wcp_rvrow_' . $cid );
			$r = sanitize_key( $_GET['rvrow'] );
			if ( $cid ) {
				if ( $r === 'approve' ) { wp_set_comment_status( $cid, 'approve' ); }
				elseif ( $r === 'unapprove' ) { wp_set_comment_status( $cid, 'hold' ); }
				elseif ( $r === 'spam' ) { wp_spam_comment( $cid ); }
				elseif ( $r === 'unspam' ) { wp_unspam_comment( $cid ); }
				elseif ( $r === 'trash' ) { wp_trash_comment( $cid ); }
				elseif ( $r === 'untrash' ) { wp_untrash_comment( $cid ); }
				elseif ( $r === 'delete' ) { wp_delete_comment( $cid, true ); }
			}
			$ret = isset( $_GET['ret_status'] ) ? sanitize_key( $_GET['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'reviews', 0, array( 'status' => $ret ) ) : self::url( 'reviews' ) ); exit;
		}

		// --- Değerlendirme toplu işlem ---
		if ( $act === 'reviews_bulk' && current_user_can( 'moderate_comments' ) ) {
			check_admin_referer( 'wcp_reviews_bulk' );
			$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$do  = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
			foreach ( $ids as $cid ) {
				if ( $do === 'approve' ) { wp_set_comment_status( $cid, 'approve' ); }
				elseif ( $do === 'unapprove' ) { wp_set_comment_status( $cid, 'hold' ); }
				elseif ( $do === 'spam' ) { wp_spam_comment( $cid ); }
				elseif ( $do === 'unspam' ) { wp_unspam_comment( $cid ); }
				elseif ( $do === 'trash' ) { wp_trash_comment( $cid ); }
				elseif ( $do === 'untrash' ) { wp_untrash_comment( $cid ); }
				elseif ( $do === 'delete' ) { wp_delete_comment( $cid, true ); }
			}
			$ret = isset( $_POST['ret_status'] ) ? sanitize_key( $_POST['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'reviews', 0, array( 'status' => $ret ) ) : self::url( 'reviews' ) ); exit;
		}

		// --- Değerlendirme kaydet ---
		if ( $act === 'review_save' && current_user_can( 'moderate_comments' ) ) {
			$cid = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
			check_admin_referer( 'wcp_review_' . $cid );
			if ( ! $cid ) { wp_safe_redirect( self::url( 'reviews' ) ); exit; }
			$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
			wp_update_comment( array(
				'comment_ID'           => $cid,
				'comment_content'      => wp_kses_post( $g( 'content' ) ),
				'comment_author'       => sanitize_text_field( $g( 'author' ) ),
				'comment_author_email' => sanitize_email( $g( 'author_email' ) ),
			) );
			$rating = absint( $g( 'rating' ) );
			if ( $rating >= 1 && $rating <= 5 ) { update_comment_meta( $cid, 'rating', $rating ); }
			elseif ( $g( 'rating' ) === '0' || $g( 'rating' ) === '' ) { delete_comment_meta( $cid, 'rating' ); }
			$st = sanitize_key( $g( 'status' ) );
			if ( $st === 'approve' ) { wp_set_comment_status( $cid, 'approve' ); }
			elseif ( $st === 'hold' ) { wp_set_comment_status( $cid, 'hold' ); }
			wp_safe_redirect( self::url( 'review', $cid, array( 'msg' => 'ok' ) ) ); exit;
		}

		// --- Değerlendirmeye yanıt ekle ---
		if ( $act === 'review_reply' && current_user_can( 'moderate_comments' ) ) {
			$cid = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
			check_admin_referer( 'wcp_review_' . $cid );
			$reply = isset( $_POST['reply'] ) ? wp_kses_post( wp_unslash( $_POST['reply'] ) ) : '';
			$parent = $cid ? get_comment( $cid ) : null;
			if ( $cid && $parent && trim( $reply ) !== '' ) {
				$user = wp_get_current_user();
				wp_insert_comment( array(
					'comment_post_ID'      => $parent->comment_post_ID,
					'comment_content'      => $reply,
					'comment_parent'       => $cid,
					'user_id'              => $user->ID,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_approved'     => 1,
					'comment_type'         => 'comment',
				) );
			}
			wp_safe_redirect( self::url( 'review', $cid, array( 'msg' => 'reply' ) ) ); exit;
		}

		// --- Toplu işlemler (sipariş) ---
		if ( $act === 'orders_bulk' && current_user_can( 'edit_shop_orders' ) ) {
			check_admin_referer( 'wcp_orders_bulk' );
			$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
			$do  = isset( $_POST['bulk'] ) ? sanitize_key( $_POST['bulk'] ) : '';
			foreach ( $ids as $oid ) {
				$o = wc_get_order( $oid ); if ( ! $o ) { continue; }
				if ( strpos( $do, 'status-' ) === 0 ) { $o->update_status( substr( $do, 7 ) ); }
				elseif ( $do === 'trash' ) { $o->delete( false ); }
				elseif ( $do === 'untrash' ) { $prev = $o->get_meta( '_wp_trash_meta_status' ); $o->set_status( $prev ? $prev : 'pending' ); $o->save(); }
				elseif ( $do === 'delete' ) { $o->delete( true ); }
			}
			$ret = isset( $_POST['ret_status'] ) ? sanitize_key( $_POST['ret_status'] ) : '';
			wp_safe_redirect( $ret ? self::url( 'orders', 0, array( 'status' => $ret ) ) : self::url( 'orders' ) ); exit;
		}

		if ( $act === 'order_status' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$order = $oid ? wc_get_order( $oid ) : false;
			if ( $order ) { $order->update_status( sanitize_key( $_POST['new_status'] ), 'Panel üzerinden güncellendi.' ); }
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'ok' ) ) ); exit;
		}

		if ( $act === 'order_note' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$order = $oid ? wc_get_order( $oid ) : false;
			$txt   = isset( $_POST['note'] ) ? trim( wp_kses_post( wp_unslash( $_POST['note'] ) ) ) : '';
			if ( $order && $txt !== '' ) {
				$order->add_order_note( $txt, ! empty( $_POST['note_customer'] ) ? 1 : 0, true );
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'note' ) ) ); exit;
		}

		// --- Adres / fatura / tarih / müşteri notu kaydet ---
		if ( $act === 'order_save' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			if ( $o ) {
				foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' ) as $f ) {
					if ( isset( $_POST[ 'b_' . $f ] ) ) { $m = 'set_billing_' . $f; $o->$m( sanitize_text_field( wp_unslash( $_POST[ 'b_' . $f ] ) ) ); }
				}
				foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ) as $f ) {
					$m = 'set_shipping_' . $f;
					if ( isset( $_POST[ 's_' . $f ] ) && method_exists( $o, $m ) ) { $o->$m( sanitize_text_field( wp_unslash( $_POST[ 's_' . $f ] ) ) ); }
				}
				if ( isset( $_POST['hez_type'] ) ) { $o->update_meta_data( '_billing_hez_invoice_type', $_POST['hez_type'] === 'company' ? 'company' : 'person' ); }
				if ( isset( $_POST['hez_tax_number'] ) ) { $o->update_meta_data( '_billing_hez_tax_number', sanitize_text_field( wp_unslash( $_POST['hez_tax_number'] ) ) ); }
				if ( isset( $_POST['hez_tax_office'] ) ) { $o->update_meta_data( '_billing_hez_tax_office', sanitize_text_field( wp_unslash( $_POST['hez_tax_office'] ) ) ); }
				if ( isset( $_POST['hez_tc'] ) ) { $o->update_meta_data( '_billing_hez_TC_number', sanitize_text_field( wp_unslash( $_POST['hez_tc'] ) ) ); }
				if ( isset( $_POST['cust_note'] ) ) { $o->set_customer_note( sanitize_textarea_field( wp_unslash( $_POST['cust_note'] ) ) ); }
				if ( ! empty( $_POST['order_date'] ) ) { $ts = strtotime( wp_unslash( $_POST['order_date'] ) ); if ( $ts ) { $o->set_date_created( $ts ); } }
				$o->save();
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'saved' ) ) ); exit;
		}

		// --- Sipariş işlemi (e-posta tekrar gönder vb.) ---
		if ( $act === 'order_action' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			$action = isset( $_POST['order_action'] ) ? sanitize_key( $_POST['order_action'] ) : '';
			if ( $o && $action ) { do_action( 'woocommerce_order_action_' . $action, $o ); }
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'action' ) ) ); exit;
		}

		// --- Kalemler: adet güncelle / kaldır / yeniden hesapla ---
		if ( $act === 'order_items' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			if ( $o ) {
				if ( ! empty( $_POST['remove_item'] ) ) {
					$o->remove_item( absint( $_POST['remove_item'] ) );
				} elseif ( ! empty( $_POST['qty'] ) && is_array( $_POST['qty'] ) ) {
					foreach ( $_POST['qty'] as $iid => $q ) {
						$item = $o->get_item( absint( $iid ) );
						if ( $item && is_a( $item, 'WC_Order_Item_Product' ) ) {
							$old_q = max( 1, (int) $item->get_quantity() );
							$new_q = max( 1, (int) $q );
							$unit_sub = (float) $item->get_subtotal() / $old_q;
							$unit_tot = (float) $item->get_total() / $old_q;
							$item->set_quantity( $new_q );
							$item->set_subtotal( $unit_sub * $new_q );
							$item->set_total( $unit_tot * $new_q );
							$item->save();
						}
					}
				}
				$o->calculate_totals();
				$o->save();
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'items' ) ) ); exit;
		}

		// --- Siparişe ürün ekle (SKU veya ID) ---
		if ( $act === 'order_add_item' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			$ref = isset( $_POST['add_product'] ) ? sanitize_text_field( wp_unslash( $_POST['add_product'] ) ) : '';
			$q = max( 1, isset( $_POST['add_qty'] ) ? (int) $_POST['add_qty'] : 1 );
			if ( $o && $ref !== '' ) {
				$pid = wc_get_product_id_by_sku( $ref ); if ( ! $pid && is_numeric( $ref ) ) { $pid = absint( $ref ); }
				$prod = $pid ? wc_get_product( $pid ) : null;
				if ( $prod ) { $o->add_product( $prod, $q ); $o->calculate_totals(); $o->save(); }
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'items' ) ) ); exit;
		}

		// --- Ücret / kredi kartı komisyonu ekle ---
		if ( $act === 'order_add_fee' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			$name = isset( $_POST['fee_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_name'] ) ) : '';
			$amt = isset( $_POST['fee_amount'] ) ? (float) wc_format_decimal( $_POST['fee_amount'] ) : 0;
			if ( $o && $name !== '' && $amt != 0 ) {
				$fee = new WC_Order_Item_Fee();
				$fee->set_name( $name ); $fee->set_amount( $amt ); $fee->set_total( $amt ); $fee->set_tax_status( 'none' );
				$o->add_item( $fee ); $o->calculate_totals(); $o->save();
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'items' ) ) ); exit;
		}

		// --- Kargo satırı ekle ---
		if ( $act === 'order_add_shipping' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			$title = isset( $_POST['ship_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ship_title'] ) ) : '';
			$amt = isset( $_POST['ship_amount'] ) ? (float) wc_format_decimal( $_POST['ship_amount'] ) : 0;
			if ( $o && $title !== '' ) {
				$sh = new WC_Order_Item_Shipping();
				$sh->set_method_title( $title ); $sh->set_total( $amt );
				$o->add_item( $sh ); $o->calculate_totals(); $o->save();
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'items' ) ) ); exit;
		}

		// --- Para iadesi (manuel; gateway'i otomatik tetiklemez) ---
		if ( $act === 'order_refund' && current_user_can( 'edit_shop_orders' ) ) {
			$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			check_admin_referer( 'wcp_order_' . $oid );
			$o = $oid ? wc_get_order( $oid ) : false;
			$amt = isset( $_POST['refund_amount'] ) ? (float) wc_format_decimal( $_POST['refund_amount'] ) : 0;
			$reason = isset( $_POST['refund_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['refund_reason'] ) ) : '';
			if ( $o && $amt > 0 ) {
				wc_create_refund( array(
					'order_id'       => $oid,
					'amount'         => $amt,
					'reason'         => $reason,
					'refund_payment' => false,
					'restock_items'  => ! empty( $_POST['restock'] ),
				) );
			}
			wp_safe_redirect( self::url( 'order', $oid, array( 'msg' => 'refund' ) ) ); exit;
		}

		if ( $act === 'product_save' && current_user_can( 'edit_products' ) ) {
			$pid = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			check_admin_referer( 'wcp_product_' . $pid );
			$p = $pid ? wc_get_product( $pid ) : new WC_Product_Simple();
			if ( $p ) {
				$g = function ( $k ) { return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; };
				$p->set_name( sanitize_text_field( $g( 'title' ) ) );
				if ( $g( 'slug' ) !== '' ) { $p->set_slug( sanitize_title( $g( 'slug' ) ) ); }
				$p->set_status( in_array( $g( 'status' ), array( 'publish', 'draft', 'pending' ), true ) ? $g( 'status' ) : 'draft' );
				$p->set_featured( ! empty( $_POST['featured'] ) );
				$p->set_catalog_visibility( in_array( $g( 'catalog_visibility' ), array( 'visible', 'catalog', 'search', 'hidden' ), true ) ? $g( 'catalog_visibility' ) : 'visible' );
				try { $p->set_sku( sanitize_text_field( $g( 'sku' ) ) ); } catch ( Exception $e ) {}
				$p->set_regular_price( wc_format_decimal( $g( 'regular_price' ) ) );
				$p->set_sale_price( $g( 'sale_price' ) !== '' ? wc_format_decimal( $g( 'sale_price' ) ) : '' );
				$p->set_date_on_sale_from( $g( 'sale_from' ) !== '' ? wc_clean( $g( 'sale_from' ) ) : '' );
				$p->set_date_on_sale_to( $g( 'sale_to' ) !== '' ? wc_clean( $g( 'sale_to' ) ) : '' );
				$manage = ! empty( $_POST['manage_stock'] );
				$p->set_manage_stock( $manage );
				if ( $manage ) {
					$p->set_stock_quantity( wc_stock_amount( $g( 'stock_quantity' ) ) );
					$p->set_backorders( in_array( $g( 'backorders' ), array( 'no', 'notify', 'yes' ), true ) ? $g( 'backorders' ) : 'no' );
					if ( $g( 'low_stock' ) !== '' ) { $p->set_low_stock_amount( wc_stock_amount( $g( 'low_stock' ) ) ); }
				}
				$p->set_stock_status( in_array( $g( 'stock_status' ), array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $g( 'stock_status' ) : 'instock' );
				$p->set_short_description( wp_kses_post( $g( 'short_description' ) ) );
				$p->set_description( wp_kses_post( $g( 'description' ) ) );
				$p->set_weight( wc_format_decimal( $g( 'weight' ) ) );
				$p->set_length( wc_format_decimal( $g( 'length' ) ) );
				$p->set_width( wc_format_decimal( $g( 'width' ) ) );
				$p->set_height( wc_format_decimal( $g( 'height' ) ) );
				if ( ! empty( $_POST['remove_image'] ) ) { $p->set_image_id( '' ); }
				if ( ! empty( $_POST['remove_gallery'] ) && is_array( $_POST['remove_gallery'] ) ) {
					$rm = array_map( 'absint', $_POST['remove_gallery'] );
					$p->set_gallery_image_ids( array_values( array_diff( $p->get_gallery_image_ids(), $rm ) ) );
				}
				// Bağlantılı ürünler
				if ( isset( $_POST['upsells'] ) ) { $p->set_upsell_ids( self::ids_from_refs( $g( 'upsells' ) ) ); }
				if ( isset( $_POST['cross_sells'] ) ) { $p->set_cross_sell_ids( self::ids_from_refs( $g( 'cross_sells' ) ) ); }
				// Gönderim sınıfı + vergi
				if ( isset( $_POST['shipping_class'] ) ) { $p->set_shipping_class_id( absint( $_POST['shipping_class'] ) ); }
				$p->set_tax_status( in_array( $g( 'tax_status' ), array( 'taxable', 'shipping', 'none' ), true ) ? $g( 'tax_status' ) : 'taxable' );
				if ( isset( $_POST['tax_class'] ) ) { $p->set_tax_class( sanitize_title( $g( 'tax_class' ) ) ); }
				// Gelişmiş
				$p->set_purchase_note( wp_kses_post( $g( 'purchase_note' ) ) );
				$p->set_menu_order( (int) $g( 'menu_order' ) );
				$p->set_reviews_allowed( ! empty( $_POST['reviews_allowed'] ) );
				$p->set_sold_individually( ! empty( $_POST['sold_individually'] ) );
				// Nitelikler — formdan tamamen yeniden kur (ekle/kaldır/sırala/değer)
				if ( isset( $_POST['attr_present'] ) ) {
					$order = array();
					if ( isset( $_POST['attr_order'] ) && $_POST['attr_order'] !== '' ) {
						$order = array_map( 'intval', explode( ',', (string) wp_unslash( $_POST['attr_order'] ) ) );
					} elseif ( ! empty( $_POST['attr_key'] ) && is_array( $_POST['attr_key'] ) ) {
						$order = array_map( 'intval', array_keys( $_POST['attr_key'] ) );
					}
					$attrs = array(); $pos = 0;
					foreach ( $order as $ai ) {
						if ( ! isset( $_POST['attr_key'][ $ai ] ) ) { continue; }
						$akey = sanitize_text_field( wp_unslash( $_POST['attr_key'][ $ai ] ) );
						$is_tax = ! empty( $_POST['attr_tax'][ $ai ] ) && taxonomy_exists( $akey );
						$raw = isset( $_POST['attr_vals'][ $ai ] ) ? wp_unslash( $_POST['attr_vals'][ $ai ] ) : '';
						$vals = array_values( array_filter( array_map( 'trim', preg_split( '/\|/', (string) $raw ) ), function ( $v ) { return $v !== ''; } ) );
						$visible = ! empty( $_POST['attr_visible'][ $ai ] );
						if ( $is_tax ) {
							if ( ! $vals ) { continue; }
							$term_ids = array();
							foreach ( $vals as $vn ) {
								$term = get_term_by( 'name', $vn, $akey );
								if ( ! $term ) { $ins = wp_insert_term( $vn, $akey ); if ( is_wp_error( $ins ) ) { continue; } $tid = $ins['term_id']; } else { $tid = $term->term_id; }
								$term_ids[] = (int) $tid;
							}
							if ( ! $term_ids ) { continue; }
							$a = new WC_Product_Attribute();
							$a->set_id( wc_attribute_taxonomy_id_by_name( $akey ) );
							$a->set_name( $akey );
							$a->set_options( $term_ids );
							$a->set_visible( $visible );
							$a->set_position( $pos++ );
							$attrs[ $akey ] = $a;
						} else {
							$aname = isset( $_POST['attr_name'][ $ai ] ) ? sanitize_text_field( wp_unslash( $_POST['attr_name'][ $ai ] ) ) : '';
							if ( $aname === '' || ! $vals ) { continue; }
							$a = new WC_Product_Attribute();
							$a->set_name( $aname );
							$a->set_options( $vals );
							$a->set_visible( $visible );
							$a->set_position( $pos++ );
							$attrs[ sanitize_title( $aname ) ] = $a;
						}
					}
					$p->set_attributes( $attrs );
				}
				$newid = $p->save();
				// Marka
				if ( taxonomy_exists( 'product_brand' ) ) {
					wp_set_object_terms( $newid, isset( $_POST['product_brand'] ) ? array_map( 'intval', (array) $_POST['product_brand'] ) : array(), 'product_brand' );
				}
				// Google entegrasyonu
				if ( isset( $_POST['gla_present'] ) ) {
					update_post_meta( $newid, '_wc_gla_visibility', ( isset( $_POST['gla_visibility'] ) && $_POST['gla_visibility'] === 'dont-sync-and-show' ) ? 'dont-sync-and-show' : 'sync-and-show' );
					$glamap = array( 'gla_google_product_category' => '_wc_gla_google_product_category', 'gla_gtin' => '_wc_gla_gtin', 'gla_mpn' => '_wc_gla_mpn', 'gla_brand' => '_wc_gla_brand', 'gla_condition' => '_wc_gla_condition', 'gla_size' => '_wc_gla_size', 'gla_color' => '_wc_gla_color', 'gla_gender' => '_wc_gla_gender', 'gla_age_group' => '_wc_gla_ageGroup', 'gla_material' => '_wc_gla_material', 'gla_pattern' => '_wc_gla_pattern' );
					foreach ( $glamap as $f => $mk ) { if ( isset( $_POST[ $f ] ) ) { update_post_meta( $newid, $mk, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) ); } }
				}
				// Facebook & Instagram kanalı
				if ( isset( $_POST['fb_sync_toggle'] ) ) {
					$fbon = ! empty( $_POST['fb_sync'] ) ? 'yes' : 'no';
					update_post_meta( $newid, '_wc_facebook_sync_enabled', $fbon );
					update_post_meta( $newid, 'fb_visibility', $fbon );
					if ( isset( $_POST['fb_description'] ) ) { update_post_meta( $newid, 'fb_product_description', wp_kses_post( wp_unslash( $_POST['fb_description'] ) ) ); }
					if ( isset( $_POST['fb_image_source'] ) ) { update_post_meta( $newid, '_wc_facebook_product_image_source', $_POST['fb_image_source'] === 'custom' ? 'custom' : 'product' ); }
					if ( isset( $_POST['fb_image_url'] ) ) { update_post_meta( $newid, 'fb_product_image', esc_url_raw( wp_unslash( $_POST['fb_image_url'] ) ) ); }
					if ( isset( $_POST['fb_price'] ) ) { update_post_meta( $newid, 'fb_product_price', sanitize_text_field( wp_unslash( $_POST['fb_price'] ) ) ); }
					if ( isset( $_POST['fb_google_category'] ) ) { update_post_meta( $newid, '_wc_facebook_google_product_category', sanitize_text_field( wp_unslash( $_POST['fb_google_category'] ) ) ); }
					foreach ( array( 'fb_brand', 'fb_mpn', 'fb_size', 'fb_color', 'fb_material', 'fb_age_group', 'fb_gender', 'fb_product_condition', 'fb_pattern' ) as $fk ) {
						if ( isset( $_POST[ $fk ] ) ) { update_post_meta( $newid, $fk, sanitize_text_field( wp_unslash( $_POST[ $fk ] ) ) ); }
					}
				}
				// SSD Yükseltme Seçenekleri (Surface SSD modülü — _fbt_product_ids)
				if ( isset( $_POST['ssd_present'] ) ) {
					$sids = isset( $_POST['ssd_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $_POST['ssd_ids'] ) ) ) ) : array();
					if ( $sids ) { update_post_meta( $newid, '_fbt_product_ids', $sids ); } else { delete_post_meta( $newid, '_fbt_product_ids' ); }
					delete_post_meta( $newid, '_fbt_product_id' );
				}
				// 🎁 Hediye ürünleri (_wcp_gift_ids)
				if ( isset( $_POST['wcp_gift_ids'] ) ) {
					$gids = self::ids_from_refs( $g( 'wcp_gift_ids' ) );
					if ( $gids ) { update_post_meta( $newid, '_wcp_gift_ids', $gids ); } else { delete_post_meta( $newid, '_wcp_gift_ids' ); }
				}
				// 📦 Paket içeriği — yanındaki ürünler (_wcp_bundle_ids)
				if ( isset( $_POST['wcp_bundle_ids'] ) ) {
					$bids = self::ids_from_refs( $g( 'wcp_bundle_ids' ) );
					if ( $bids ) { update_post_meta( $newid, '_wcp_bundle_ids', $bids ); } else { delete_post_meta( $newid, '_wcp_bundle_ids' ); }
				}
				if ( isset( $_POST['product_cat'] ) ) { wp_set_object_terms( $newid, array_map( 'intval', (array) $_POST['product_cat'] ), 'product_cat' ); }
				else { wp_set_object_terms( $newid, array(), 'product_cat' ); }
				$tags = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $g( 'product_tags' ) ) ) ) );
				wp_set_object_terms( $newid, $tags, 'product_tag' );
				// Görsel & galeri yükleme
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				if ( ! empty( $_FILES['featured_image']['name'] ) ) {
					$aid = media_handle_upload( 'featured_image', $newid );
					if ( ! is_wp_error( $aid ) ) { $p->set_image_id( $aid ); $p->save(); }
				}
				if ( ! empty( $_FILES['gallery_images']['name'][0] ) ) {
					$files = $_FILES['gallery_images']; $gal = $p->get_gallery_image_ids();
					foreach ( $files['name'] as $i => $nm ) {
						if ( ! $nm ) { continue; }
						$_FILES['wcp_g'] = array( 'name' => $files['name'][ $i ], 'type' => $files['type'][ $i ], 'tmp_name' => $files['tmp_name'][ $i ], 'error' => $files['error'][ $i ], 'size' => $files['size'][ $i ] );
						$aid = media_handle_upload( 'wcp_g', $newid );
						if ( ! is_wp_error( $aid ) ) { $gal[] = $aid; }
					}
					$p->set_gallery_image_ids( $gal ); $p->save();
				}
				wp_safe_redirect( self::url( 'product', $newid, array( 'msg' => 'ok' ) ) ); exit;
			}
		}
	}

	/* ---------------- render: tam HTML belge ---------------- */
	public static function render() {
		$view = get_query_var( 'wcp_view' ) ? sanitize_key( get_query_var( 'wcp_view' ) ) : 'dashboard';
		$id   = (int) get_query_var( 'wcp_id' );
		// Rol bazlı menü erişim kontrolü (Yetkilendirme ekranından)
		if ( ! self::section_allowed( self::view_section( $view ) ) ) { $view = 'dashboard'; $id = 0; }
		$titles = array( 'dashboard' => 'Genel Bakış', 'orders' => 'Siparişler', 'order' => 'Sipariş', 'products' => 'Ürünler', 'product' => 'Ürün', 'categories' => 'Kategoriler', 'category' => 'Kategori', 'tags' => 'Ürün Etiketleri', 'tag' => 'Etiket', 'attributes' => 'Ürün Nitelikleri', 'attribute' => 'Nitelik', 'brands' => 'Markalar', 'brand' => 'Marka', 'toplu-urun' => 'Toplu Ürün Düzenleme', 'aktar' => 'Dışa / İçe Aktar', 'talepler' => 'İptal / İade Talepleri', 'entegrasyonlar' => 'Entegrasyonlar', 'coupons' => 'Kuponlar', 'coupon' => 'Kupon', 'promosyon' => 'Promosyon', 'customers' => 'Müşteriler', 'customer' => 'Müşteri', 'reviews' => 'Değerlendirmeler', 'review' => 'Değerlendirme', 'stock' => 'Stok Yönetimi', 'reports' => 'Raporlar', 'kaynak-analiz' => 'Reklam & Kaynak Analizi', 'satis-analiz' => 'Satış Analizi', 'urun-analiz' => 'Ürün Analizi', 'musteri-analiz' => 'Müşteri Analizi', 'cografya-analiz' => 'Coğrafya Analizi', 'kupon-analiz' => 'Kupon & İndirim Analizi', 'iade-analiz' => 'İade Raporu', 'vergi-analiz' => 'Vergi Raporu', 'media' => 'Medya', 'media-item' => 'Medya öğesi', 'pages' => 'Sayfalar', 'page' => 'Sayfa', 'users' => 'Kullanıcılar', 'user' => 'Kullanıcı', 'perms' => 'Yetkilendirme', 'settings' => 'Ayarlar', 'shipping' => 'Kargo Bölgeleri', 'tax' => 'Vergi Oranları', 'wc-ayarlar' => 'Mağaza Ayarları', 'wc-durum' => 'Mağaza Durumu', 'wp' => 'Yönetim' );
		$media_views = array( 'product', 'categories', 'category', 'page', 'brand' );
		$title = isset( $titles[ $view ] ) ? $titles[ $view ] : 'Panel';
		$css   = content_url( 'mu-plugins/assets/wcp-panel.css' );
		$cssv  = file_exists( WPMU_PLUGIN_DIR . '/assets/wcp-panel.css' ) ? filemtime( WPMU_PLUGIN_DIR . '/assets/wcp-panel.css' ) : self::VER;
		$fav   = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 64 ) : '';
		if ( in_array( $view, $media_views, true ) ) { wp_enqueue_media(); wp_enqueue_script( 'jquery-ui-sortable' ); }
		nocache_headers();
		?><!doctype html>
<html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>STORE Panel · <?php echo esc_html( $title ); ?></title>
<?php if ( $fav ) { echo '<link rel="icon" href="' . esc_url( $fav ) . '">'; } ?>
<link rel="stylesheet" href="<?php echo esc_url( self::FONT ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( self::BS ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( self::FA ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( self::LTE ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>?ver=<?php echo esc_attr( $cssv ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
<?php if ( in_array( $view, $media_views, true ) ) { $wpv = get_bloginfo( 'version' ); echo '<link rel="stylesheet" href="' . esc_url( includes_url( 'css/media-views.min.css' ) ) . '?ver=' . esc_attr( $wpv ) . '"><link rel="stylesheet" href="' . esc_url( admin_url( 'css/buttons.min.css' ) ) . '?ver=' . esc_attr( $wpv ) . '"><link rel="stylesheet" href="' . esc_url( includes_url( 'css/jquery/ui/dialog.min.css' ) ) . '?ver=' . esc_attr( $wpv ) . '">'; } ?>
</head>
<body class="wcp-lte wcp-standalone sidebar-mini layout-fixed">
<div class="wrapper wcp-wrapper">
<?php
		self::navbar( $title );
		self::sidebar( $view );
		if ( $view === 'wp' ) {
			echo '<div class="content-wrapper tx-frame-mode">';
			self::view_wp();
			echo '</div>';
		} else {
			echo '<div class="content-wrapper"><section class="content"><div class="container-fluid pt-3">';
			switch ( $view ) {
				case 'orders':    self::view_orders(); break;
				case 'order':     self::view_order( $id ); break;
				case 'products':  self::view_products(); break;
				case 'product':   self::view_product_form( $id ); break;
				case 'categories': self::view_categories(); break;
				case 'category':  self::view_category_form( $id ); break;
				case 'tags':      self::view_tags(); break;
				case 'tag':       self::view_tag_form( $id ); break;
				case 'attributes': self::view_attributes(); break;
				case 'attribute': self::view_attribute_form( $id ); break;
				case 'brands':    self::view_brands(); break;
				case 'brand':     self::view_brand_form( $id ); break;
				case 'toplu-urun': self::view_bulk_products(); break;
				case 'aktar':     self::view_export(); break;
				case 'talepler':  self::view_cancel_requests(); break;
				case 'entegrasyonlar': self::view_integrations(); break;
				case 'shipping':  self::view_shipping(); break;
				case 'tax':       self::view_tax(); break;
				case 'wc-ayarlar': self::view_store_settings(); break;
				case 'wc-durum':  self::view_wc_status(); break;
				case 'coupons':   self::view_coupons(); break;
				case 'promosyon': self::view_promosyon(); break;
				case 'coupon':    self::view_coupon_form( $id ); break;
				case 'customers': self::view_customers(); break;
				case 'customer':  self::view_customer( $id ); break;
				case 'reviews':   self::view_reviews(); break;
				case 'review':    self::view_review( $id ); break;
				case 'stock':     self::view_stock(); break;
				case 'reports':   self::view_reports(); break;
				case 'kaynak-analiz': self::view_attribution(); break;
				case 'satis-analiz':   self::view_sales_analytics(); break;
				case 'urun-analiz':    self::view_product_analytics(); break;
				case 'musteri-analiz': self::view_customer_analytics(); break;
				case 'cografya-analiz': self::view_geo_analytics(); break;
				case 'kupon-analiz':   self::view_coupon_analytics(); break;
				case 'iade-analiz':    self::view_refund_report(); break;
				case 'vergi-analiz':   self::view_tax_report(); break;
				case 'media':     self::view_media(); break;
				case 'media-item': self::view_media_item( $id ); break;
				case 'pages':     self::view_pages(); break;
				case 'page':      self::view_page_form( $id ); break;
				case 'users':     self::view_users(); break;
				case 'user':      self::view_user_form( $id ); break;
				case 'perms':     self::view_perms(); break;
				case 'settings':  self::view_settings(); break;
				default:          self::view_dashboard();
			}
			echo '</div></section>';
			echo '<footer class="main-footer wcp-footer"><strong>STORE</strong> Mağaza Paneli · ' . esc_html( date_i18n( 'd.m.Y H:i' ) ) . '</footer>';
			echo '</div>';
		}
		?>
</div>
<?php if ( in_array( $view, $media_views, true ) ) : ?>
<?php wp_print_scripts(); ?>
<script src="<?php echo esc_url( self::BSJS ); ?>"></script>
<script src="<?php echo esc_url( self::LJS ); ?>"></script>
<?php wp_print_media_templates(); if ( $view === 'product' ) { self::product_media_js( $id ); self::product_editor_js( $id ); } elseif ( $view === 'page' ) { self::page_media_js(); } else { self::category_media_js(); } ?>
<?php else : ?>
<script src="<?php echo esc_url( self::JQ ); ?>"></script>
<script src="<?php echo esc_url( self::BSJS ); ?>"></script>
<script src="<?php echo esc_url( self::LJS ); ?>"></script>
<?php endif; ?>
</body></html><?php
	}

	protected static function navbar( $title ) {
		$u = wp_get_current_user();
		echo '<nav class="main-header navbar navbar-expand navbar-white navbar-light wcp-navbar">';
		echo '<ul class="navbar-nav">';
		echo '<li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>';
		echo '<li class="nav-item d-none d-sm-inline-block"><span class="navbar-text tx-page-title">' . esc_html( $title ) . '</span></li>';
		echo '</ul><ul class="navbar-nav ml-auto">';
		echo '<li class="nav-item"><a class="nav-link" href="' . esc_url( home_url( '/' ) ) . '" target="_blank"><i class="fas fa-store mr-1"></i> Mağaza</a></li>';
		echo '<li class="nav-item"><span class="nav-link tx-user"><span class="tx-ava">' . esc_html( mb_substr( $u->display_name, 0, 1 ) ) . '</span> ' . esc_html( $u->display_name ) . '</span></li>';
		echo '<li class="nav-item"><a class="nav-link" href="' . esc_url( wp_logout_url( self::url() ) ) . '" title="Çıkış"><i class="fas fa-sign-out-alt"></i></a></li>';
		echo '</ul></nav>';
	}

	/* Bir nav öğesi aktif mi? (alt görünümler ana öğeyi aktif yapar) */
	protected static function nav_active( $view, $current ) {
		if ( $view === $current ) { return true; }
		$sub = array(
			'orders' => 'order', 'products' => 'product', 'categories' => 'category',
			'coupons' => 'coupon', 'customers' => 'customer', 'reviews' => 'review',
			'media' => 'media-item', 'pages' => 'page', 'users' => 'user',
		);
		return ( isset( $sub[ $view ] ) && $sub[ $view ] === $current );
	}

	/* Tek nav bağlantısı — yetki kontrolüyle */
	protected static function nav_link( $view, $label, $icon, $current ) {
		if ( ! self::section_allowed( $view ) ) { return; }
		$active = self::nav_active( $view, $current ) ? ' active' : '';
		printf( '<li class="nav-item"><a href="%s" class="nav-link%s"><i class="nav-icon fas %s"></i><p>%s</p></a></li>', esc_url( self::url( $view === 'dashboard' ? '' : $view ) ), $active, esc_attr( $icon ), esc_html( $label ) );
	}

	/* Geriye dönük uyumluluk */
	protected static function nav( $view, $label, $icon, $current ) { self::nav_link( $view, $label, $icon, $current ); }

	/* Akordeon grup başlığı: en az bir alt öğe yetkiliyse render edilir; aktif öğe içeriyorsa açık başlar.
	   $items: array( array('v'=>view,'l'=>label,'i'=>icon[,'cap'=>capability]) ) */
	protected static function nav_group( $title, $gicon, $items, $current ) {
		$vis = array();
		foreach ( $items as $it ) {
			if ( ! self::section_allowed( $it['v'] ) ) { continue; }
			if ( ! empty( $it['cap'] ) && ! current_user_can( $it['cap'] ) ) { continue; }
			$vis[] = $it;
		}
		if ( empty( $vis ) ) { return; }
		$open = false;
		foreach ( $vis as $it ) { if ( self::nav_active( $it['v'], $current ) ) { $open = true; break; } }
		echo '<li class="nav-item has-treeview' . ( $open ? ' menu-open' : '' ) . '">';
		echo '<a href="#" class="nav-link tx-group"><i class="nav-icon fas ' . esc_attr( $gicon ) . '"></i><p>' . esc_html( $title ) . '<i class="right fas fa-angle-left"></i></p></a>';
		echo '<ul class="nav nav-treeview">';
		foreach ( $vis as $it ) { self::nav_link( $it['v'], $it['l'], $it['i'], $current ); }
		echo '</ul></li>';
	}

	protected static function sidebar( $current ) {
		echo '<aside class="main-sidebar sidebar-dark-primary elevation-4 wcp-side">';
		echo '<a href="' . esc_url( self::url() ) . '" class="brand-link tx-brand"><span class="tx-brand-mark">T</span><span class="brand-text">STORE<small>MAĞAZA PANELİ</small></span></a>';
		echo '<div class="sidebar"><nav class="mt-2"><ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">';
		echo '<li class="nav-header">GENEL</li>';
		self::nav_link( 'dashboard', 'Genel Bakış', 'fa-gauge-high', $current );

		echo '<li class="nav-header">YÖNETİM</li>';
		self::nav_group( 'Mağaza', 'fa-store', array(
			array( 'v' => 'orders', 'l' => 'Siparişler', 'i' => 'fa-cart-shopping' ),
			array( 'v' => 'products', 'l' => 'Ürünler', 'i' => 'fa-box' ),
			array( 'v' => 'categories', 'l' => 'Kategoriler', 'i' => 'fa-tags' ),
			array( 'v' => 'tags', 'l' => 'Ürün Etiketleri', 'i' => 'fa-hashtag' ),
			array( 'v' => 'attributes', 'l' => 'Ürün Nitelikleri', 'i' => 'fa-sliders' ),
			array( 'v' => 'brands', 'l' => 'Markalar', 'i' => 'fa-copyright' ),
			array( 'v' => 'coupons', 'l' => 'Kuponlar', 'i' => 'fa-ticket' ),
			array( 'v' => 'promosyon', 'l' => 'Promosyon', 'i' => 'fa-gift' ),
			array( 'v' => 'customers', 'l' => 'Müşteriler', 'i' => 'fa-users' ),
			array( 'v' => 'reviews', 'l' => 'Değerlendirmeler', 'i' => 'fa-star' ),
			array( 'v' => 'stock', 'l' => 'Stok Yönetimi', 'i' => 'fa-warehouse' ),
			array( 'v' => 'toplu-urun', 'l' => 'Toplu Ürün Düzenle', 'i' => 'fa-pen-to-square' ),
			array( 'v' => 'aktar', 'l' => 'Dışa / İçe Aktar', 'i' => 'fa-file-csv' ),
			array( 'v' => 'talepler', 'l' => 'İptal/İade Talepleri', 'i' => 'fa-rotate-left' ),
		), $current );

		self::nav_group( 'İçerik', 'fa-folder-open', array(
			array( 'v' => 'pages', 'l' => 'Sayfalar', 'i' => 'fa-file-lines' ),
			array( 'v' => 'media', 'l' => 'Medya', 'i' => 'fa-photo-film' ),
		), $current );

		self::nav_group( 'Analiz', 'fa-chart-line', array(
			array( 'v' => 'reports', 'l' => 'Satış Özeti', 'i' => 'fa-chart-pie' ),
			array( 'v' => 'satis-analiz', 'l' => 'Satış Analizi', 'i' => 'fa-chart-line' ),
			array( 'v' => 'urun-analiz', 'l' => 'Ürün Analizi', 'i' => 'fa-box' ),
			array( 'v' => 'musteri-analiz', 'l' => 'Müşteri Analizi', 'i' => 'fa-users' ),
			array( 'v' => 'cografya-analiz', 'l' => 'Coğrafya', 'i' => 'fa-map-location-dot' ),
			array( 'v' => 'kupon-analiz', 'l' => 'Kupon & İndirim', 'i' => 'fa-ticket' ),
			array( 'v' => 'iade-analiz', 'l' => 'İade Raporu', 'i' => 'fa-rotate-left' ),
			array( 'v' => 'vergi-analiz', 'l' => 'Vergi Raporu', 'i' => 'fa-percent' ),
			array( 'v' => 'kaynak-analiz', 'l' => 'Reklam & Kaynak', 'i' => 'fa-bullhorn' ),
		), $current );

		self::nav_group( 'Mağaza Ayarları', 'fa-store', array(
			array( 'v' => 'wc-ayarlar', 'l' => 'Genel Ayarlar', 'i' => 'fa-gear', 'cap' => 'manage_woocommerce' ),
			array( 'v' => 'shipping', 'l' => 'Kargo Bölgeleri', 'i' => 'fa-truck-fast', 'cap' => 'manage_woocommerce' ),
			array( 'v' => 'tax', 'l' => 'Vergi Oranları', 'i' => 'fa-percent', 'cap' => 'manage_woocommerce' ),
			array( 'v' => 'wc-durum', 'l' => 'Mağaza Durumu', 'i' => 'fa-heart-pulse', 'cap' => 'manage_woocommerce' ),
		), $current );

		self::nav_group( 'Sistem', 'fa-sliders', array(
			array( 'v' => 'users', 'l' => 'Kullanıcılar', 'i' => 'fa-user-gear', 'cap' => 'list_users' ),
			array( 'v' => 'perms', 'l' => 'Yetkilendirme', 'i' => 'fa-user-shield', 'cap' => 'manage_options' ),
			array( 'v' => 'settings', 'l' => 'Ayarlar', 'i' => 'fa-gear' ),
			array( 'v' => 'entegrasyonlar', 'l' => 'Entegrasyonlar', 'i' => 'fa-plug', 'cap' => 'manage_woocommerce' ),
		), $current );

		// ---- Tüm WordPress + eklenti/tema yönetimi (gömülü, açılır-kapanır) — YALNIZCA YÖNETİCİ ----
		if ( self::section_allowed( 'wp' ) ) {
		$am    = get_option( 'wcp_admin_menu', array() );
		$cur_u = ( $current === 'wp' && isset( $_GET['u'] ) ) ? self::unb64u( $_GET['u'] ) : '';
		echo '<li class="nav-header">GELİŞMİŞ</li>';
		if ( ! empty( $am ) ) {
			$wp_open = ( $current === 'wp' );
			echo '<li class="nav-item has-treeview' . ( $wp_open ? ' menu-open' : '' ) . '">';
			echo '<a href="#" class="nav-link tx-group"><i class="nav-icon fas fa-screwdriver-wrench"></i><p>Tüm Yönetim<i class="right fas fa-angle-left"></i></p></a>';
			echo '<ul class="nav nav-treeview tx-wpmenu">';
			foreach ( $am as $it ) {
				if ( ! empty( $it['c'] ) && ! current_user_can( $it['c'] ) ) { continue; }
				$has  = ! empty( $it['sub'] );
				$act  = ( $cur_u && $cur_u === $it['u'] ) ? ' active' : '';
				$open = '';
				if ( $has ) { foreach ( $it['sub'] as $s ) { if ( $cur_u && $cur_u === $s['u'] ) { $open = ' menu-open'; $act = ' active'; } } }
				echo '<li class="nav-item' . ( $has ? ' has-treeview' : '' ) . $open . '">';
				echo '<a href="' . esc_url( self::url( 'wp', 0, array( 'u' => self::b64u( $it['u'] ) ) ) ) . '" class="nav-link' . $act . '">' . self::dashicon( $it['i'] ) . '<p>' . esc_html( $it['t'] );
				if ( $has ) { echo ' <i class="right fas fa-angle-left"></i>'; }
				echo '</p></a>';
				if ( $has ) {
					echo '<ul class="nav nav-treeview">';
					foreach ( $it['sub'] as $s ) {
						if ( ! empty( $s['c'] ) && ! current_user_can( $s['c'] ) ) { continue; }
						$sact = ( $cur_u && $cur_u === $s['u'] ) ? ' active' : '';
						echo '<li class="nav-item"><a href="' . esc_url( self::url( 'wp', 0, array( 'u' => self::b64u( $s['u'] ) ) ) ) . '" class="nav-link' . $sact . '"><i class="far fa-circle nav-icon"></i><p>' . esc_html( $s['t'] ) . '</p></a></li>';
					}
					echo '</ul>';
				}
				echo '</li>';
			}
			echo '</ul></li>';
		} else {
			echo '<li class="nav-item has-treeview menu-open"><a href="#" class="nav-link tx-group"><i class="nav-icon fas fa-screwdriver-wrench"></i><p>Tüm Yönetim<i class="right fas fa-angle-left"></i></p></a><ul class="nav nav-treeview"><li class="nav-item"><a class="nav-link" href="#"><i class="nav-icon fas fa-circle-notch fa-spin"></i><p>Menü hazırlanıyor…</p></a></li></ul></li>';
			echo '<iframe src="' . esc_url( admin_url( 'index.php?wcp_capture=1' ) ) . '" style="position:absolute;left:-9999px;width:10px;height:10px;border:0" title="m" onload="if(!window.__wcpprimed){window.__wcpprimed=1;setTimeout(function(){location.reload();},1300);}"></iframe>';
		}
		} // section_allowed wp
		echo '</ul></nav></div></aside>';
	}

	protected static function view_wp() {
		$u = isset( $_GET['u'] ) ? self::unb64u( $_GET['u'] ) : admin_url();
		if ( strpos( $u, admin_url() ) !== 0 ) { $u = admin_url(); }
		$src = $u . ( strpos( $u, '?' ) !== false ? '&' : '?' ) . 'wcp_frame=1';
		echo '<iframe class="tx-frame" src="' . esc_url( $src ) . '" title="Yönetim"></iframe>';
	}

	protected static function smallbox( $value, $label, $icon, $tone = 'dark', $html = false ) {
		echo '<div class="col-lg-3 col-sm-6"><div class="small-box tx-box tone-' . esc_attr( $tone ) . '"><div class="inner"><h3 class="tx-box-v">' . ( $html ? wp_kses_post( $value ) : esc_html( $value ) ) . '</h3><p>' . esc_html( $label ) . '</p></div><div class="icon"><i class="fas ' . esc_attr( $icon ) . '"></i></div></div></div>';
	}

	/* ---------------- dashboard ---------------- */
	protected static function view_dashboard() {
		$o_today = $o_month = $o_year = 0;
		$r_today = $r_week = $r_month = $r_year = $r_lastmonth = 0.0;
		$pending = 0; $customers = 0; $recent = array();
		$prod_sales = array(); $cat_sales = array();
		$oos_count = 0; $low_count = 0; $alerts = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$now = current_time( 'timestamp' );
			$today_str = date( 'Y-m-d', $now );
			$month_str = date( 'Y-m', $now );
			$lastmonth_str = date( 'Y-m', strtotime( 'first day of last month', $now ) );
			$lastmonth_first = date( 'Y-m-01', strtotime( 'first day of last month', $now ) );
			$year_first = date( 'Y-01-01', $now );
			$dow = (int) date( 'w', $now );
			$sow = (int) get_option( 'start_of_week', 1 );
			$wdiff = ( $dow - $sow + 7 ) % 7;
			$week_start_str = date( 'Y-m-d', $now - $wdiff * DAY_IN_SECONDS );

			// Ciroya dahil olan statüler (iptal/başarısız/iade/bekleyen hariç)
			$paid = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-pending', 'wc-checkout-draft' ) );
			$query_start = ( $lastmonth_first < $year_first ) ? $lastmonth_first : $year_first; // Ocak'ta geçen ay geçen yıla düşer
			$orders = wc_get_orders( array( 'limit' => -1, 'status' => $paid, 'date_created' => '>=' . $query_start ) );
			foreach ( $orders as $o ) {
				$od = $o->get_date_created(); if ( ! $od ) { continue; }
				$d = $od->date( 'Y-m-d' ); $tot = (float) $o->get_total();
				if ( $d >= $year_first ) { $o_year++; $r_year += $tot; }
				if ( substr( $d, 0, 7 ) === $month_str ) { $o_month++; $r_month += $tot; }
				if ( substr( $d, 0, 7 ) === $lastmonth_str ) { $r_lastmonth += $tot; }
				if ( $d >= $week_start_str ) { $r_week += $tot; }
				if ( $d === $today_str ) { $o_today++; $r_today += $tot; }
			}
			$pending = (int) wc_orders_count( 'processing' ) + (int) wc_orders_count( 'pending' ) + (int) wc_orders_count( 'on-hold' );
			$recent  = wc_get_orders( array( 'limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ) ) );

			// En çok satanlar (toplam satış meta'sından — tüm zamanlar)
			$pq = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
			foreach ( $pq->posts as $pid ) {
				$ts = (int) get_post_meta( $pid, 'total_sales', true );
				if ( $ts <= 0 ) { continue; }
				$prod_sales[ $pid ] = $ts;
				$terms = get_the_terms( $pid, 'product_cat' );
				if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { if ( ! isset( $cat_sales[ $t->term_id ] ) ) { $cat_sales[ $t->term_id ] = array( 'name' => $t->name, 'sales' => 0 ); } $cat_sales[ $t->term_id ]['sales'] += $ts; } }
			}
			arsort( $prod_sales );
			uasort( $cat_sales, function ( $a, $b ) { return $b['sales'] <=> $a['sales']; } );

			// Stok uyarıları (tükendi + düşük stok)
			$oos_ids = wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish', 'stock_status' => 'outofstock', 'orderby' => 'title', 'order' => 'ASC' ) );
			$gthr = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ); if ( $gthr < 1 ) { $gthr = 2; }
			$lowq = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'meta_value_num', 'meta_key' => '_stock', 'order' => 'ASC', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_manage_stock', 'value' => 'yes' ), array( 'key' => '_stock', 'value' => $gthr, 'compare' => '<=', 'type' => 'NUMERIC' ), array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
			$low_ids = $lowq->posts;
			$oos_count = count( $oos_ids ); $low_count = count( $low_ids );
			foreach ( $oos_ids as $pid ) { if ( count( $alerts ) >= 12 ) { break; } $pp = wc_get_product( $pid ); if ( $pp ) { $alerts[] = array( 'pid' => $pid, 'name' => $pp->get_name(), 'sku' => $pp->get_sku(), 'type' => 'oos', 'qty' => $pp->get_stock_quantity() ); } }
			foreach ( $low_ids as $pid ) { if ( count( $alerts ) >= 12 ) { break; } $pp = wc_get_product( $pid ); if ( $pp ) { $alerts[] = array( 'pid' => $pid, 'name' => $pp->get_name(), 'sku' => $pp->get_sku(), 'type' => 'low', 'qty' => $pp->get_stock_quantity() ); } }
		}
		$cu = count_users(); $customers = isset( $cu['avail_roles']['customer'] ) ? (int) $cu['avail_roles']['customer'] : 0;
		$pcp = wp_count_posts( 'product' ); $products = isset( $pcp->publish ) ? (int) $pcp->publish : 0;

		// Aylık ciro değişimi (geçen aya göre)
		if ( $r_lastmonth > 0 ) {
			$pct = (int) round( ( $r_month - $r_lastmonth ) / $r_lastmonth * 100 );
			$dcls = $pct > 0 ? 'up' : ( $pct < 0 ? 'down' : 'flat' );
			$darr = $pct > 0 ? '▲' : ( $pct < 0 ? '▼' : '→' );
			$delta_html = '<span class="tx-delta ' . $dcls . '">' . $darr . ' %' . abs( $pct ) . '</span>';
		} elseif ( $r_month > 0 ) {
			$delta_html = '<span class="tx-delta up">▲ yeni</span>';
		} else {
			$delta_html = '<span class="tx-delta flat">—</span>';
		}

		// === Bekleyen İşler (aksiyon widget'ı) ===
		if ( class_exists( 'WooCommerce' ) ) {
			$c_processing = (int) wc_orders_count( 'processing' );
			$c_pending    = (int) wc_orders_count( 'pending' );
			$c_hold       = (int) wc_orders_count( 'on-hold' );
			$c_cancelreq  = (int) wc_orders_count( 'cancel-request' );
			$c_kargoda    = (int) wc_orders_count( 'kargoda' );
			$c_reviews    = (int) get_comments( array( 'status' => 'hold', 'post_type' => 'product', 'count' => true ) );
			$c_stock      = (int) ( $oos_count + $low_count );
			$chips = array();
			if ( $c_processing ) { $chips[] = array( 'Hazırlanan sipariş', $c_processing, self::url( 'orders', 0, array( 'status' => 'processing' ) ), 'fa-box-open' ); }
			if ( $c_pending )     { $chips[] = array( 'Ödeme bekleyen', $c_pending, self::url( 'orders', 0, array( 'status' => 'pending' ) ), 'fa-hourglass-half' ); }
			if ( $c_hold )        { $chips[] = array( 'Beklemede', $c_hold, self::url( 'orders', 0, array( 'status' => 'on-hold' ) ), 'fa-pause' ); }
			if ( $c_cancelreq )   { $chips[] = array( 'İptal/iade talebi', $c_cancelreq, self::url( 'orders', 0, array( 'status' => 'cancel-request' ) ), 'fa-rotate-left' ); }
			if ( $c_kargoda )     { $chips[] = array( 'Kargoda (yolda)', $c_kargoda, self::url( 'orders', 0, array( 'status' => 'kargoda' ) ), 'fa-truck-fast' ); }
			if ( $c_reviews )     { $chips[] = array( 'Onay bekleyen yorum', $c_reviews, self::url( 'reviews' ), 'fa-star' ); }
			if ( $c_stock )       { $chips[] = array( 'Stok uyarısı', $c_stock, self::url( 'stock', 0, array( 'stock' => 'outofstock' ) ), 'fa-triangle-exclamation' ); }
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check mr-2"></i>Bekleyen İşler</h3></div><div class="card-body">';
			if ( empty( $chips ) ) { echo '<div class="tx-empty" style="padding:6px 0">Bekleyen iş yok 🎉</div>'; }
			else {
				echo '<div style="display:flex;flex-wrap:wrap;gap:10px">';
				foreach ( $chips as $c ) {
					echo '<a href="' . esc_url( $c[2] ) . '" style="display:inline-flex;align-items:center;gap:9px;padding:10px 14px;border-radius:11px;border:1px solid #e6e9ef;background:#fbfcfe;text-decoration:none;color:#2b3445;font-weight:600"><i class="fas ' . esc_attr( $c[3] ) . '" style="color:#0078D4"></i>' . esc_html( $c[0] ) . ' <span style="background:#15171c;color:#fff;border-radius:20px;padding:2px 9px;font-size:12px;font-weight:700">' . (int) $c[1] . '</span></a>';
				}
				echo '</div>';
			}
			echo '</div>';
		}

		// Sipariş istatistikleri
		echo '<div class="row">';
		self::smallbox( (int) $o_today, 'Bugünkü sipariş', 'fa-cart-shopping', 'dark' );
		self::smallbox( (int) $o_month, 'Bu ayki sipariş', 'fa-calendar-days', 'light' );
		self::smallbox( (int) $o_year, 'Bu yılki sipariş', 'fa-calendar', 'light' );
		self::smallbox( (int) $pending, 'Bekleyen sipariş', 'fa-clock', 'light' );
		echo '</div>';
		// Ciro istatistikleri
		echo '<div class="row">';
		self::smallbox( self::money( $r_today ), 'Bugünkü ciro', 'fa-lira-sign', 'dark', true );
		self::smallbox( self::money( $r_week ), 'Bu haftaki ciro', 'fa-calendar-week', 'dark', true );
		self::smallbox( self::money( $r_year ), 'Bu yılki ciro', 'fa-calendar-check', 'dark', true );
		self::smallbox( self::money( $o_year ? $r_year / $o_year : 0 ), 'Ortalama sepet (yıl)', 'fa-receipt', 'light', true );
		echo '</div>';
		// Özet istatistikleri
		echo '<div class="row">';
		self::smallbox( $delta_html, 'Aylık ciro değişimi', 'fa-arrow-trend-up', 'light', true );
		self::smallbox( (int) ( $oos_count + $low_count ), 'Stok uyarısı', 'fa-triangle-exclamation', 'light' );
		self::smallbox( (int) $customers, 'Müşteri', 'fa-users', 'light' );
		self::smallbox( (int) $products, 'Yayındaki ürün', 'fa-box', 'light' );
		echo '</div>';

		// Stok uyarıları kartı
		if ( $oos_count + $low_count > 0 ) {
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-triangle-exclamation mr-2"></i>Stok uyarıları <small class="text-muted">(' . (int) $oos_count . ' tükendi · ' . (int) $low_count . ' düşük)</small></h3><div class="card-tools"><a class="tx-link" href="' . esc_url( self::url( 'stock', 0, array( 'stock' => 'outofstock' ) ) ) . '">Stok yönetimi →</a></div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Ürün</th><th>SKU</th><th>Durum</th><th></th></tr></thead><tbody>';
			foreach ( $alerts as $a ) {
				$badge = $a['type'] === 'oos' ? '<span class="tx-badge tx-bad">Tükendi</span>' : '<span class="tx-badge tx-warn">Düşük stok: ' . (int) $a['qty'] . '</span>';
				echo '<tr><td><a class="tx-strong" href="' . esc_url( self::url( 'product', $a['pid'] ) ) . '"><span class="tx-trunc" style="max-width:320px">' . esc_html( $a['name'] ) . '</span></a></td><td class="text-muted">' . esc_html( $a['sku'] ? $a['sku'] : '—' ) . '</td><td>' . $badge . '</td><td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'product', $a['pid'] ) ) . '">Düzenle</a></td></tr>';
			}
			echo '</tbody></table></div>';
			if ( $oos_count + $low_count > 12 ) { echo '<div class="tx-empty" style="padding:11px">+' . ( ( $oos_count + $low_count ) - 12 ) . ' ürün daha · <a class="tx-link" href="' . esc_url( self::url( 'stock', 0, array( 'stock' => 'outofstock' ) ) ) . '">tümünü gör</a></div>'; }
			echo '</div></div>';
		}

		// En çok satanlar
		echo '<div class="row">';
		echo '<div class="col-lg-7"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">En çok satan ürünler <small class="text-muted">(tüm zamanlar)</small></h3><div class="card-tools"><a class="tx-link" href="' . esc_url( self::url( 'products' ) ) . '">Ürünler →</a></div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>#</th><th>Ürün</th><th class="text-center">Satış</th><th class="text-right">Fiyat</th></tr></thead><tbody>';
		if ( empty( $prod_sales ) ) { echo '<tr><td colspan="4"><div class="tx-empty">Henüz satış yok.</div></td></tr>'; }
		$i = 0;
		foreach ( $prod_sales as $pid => $ts ) { $i++; if ( $i > 8 ) { break; }
			$pp = wc_get_product( $pid ); if ( ! $pp ) { continue; }
			$img = $pp->get_image_id() ? wp_get_attachment_image_url( $pp->get_image_id(), array( 32, 32 ) ) : wc_placeholder_img_src();
			echo '<tr><td class="text-muted">' . $i . '</td><td><div class="tx-flexcell"><img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt=""><a class="tx-strong" href="' . esc_url( self::url( 'product', $pid ) ) . '"><span class="tx-trunc" style="max-width:240px">' . esc_html( $pp->get_name() ) . '</span></a></div></td><td class="text-center tx-strong">' . (int) $ts . '</td><td class="text-right">' . wp_kses_post( $pp->get_price_html() ? $pp->get_price_html() : self::money( $pp->get_price() ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div></div></div>';

		echo '<div class="col-lg-5"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">En çok satan kategoriler</h3></div><div class="card-body">';
		$cmax = 0; foreach ( $cat_sales as $c ) { if ( $c['sales'] > $cmax ) { $cmax = $c['sales']; } }
		if ( empty( $cat_sales ) ) { echo '<div class="tx-empty">Veri yok.</div>'; }
		$i = 0;
		foreach ( $cat_sales as $c ) { $i++; if ( $i > 8 ) { break; }
			$pct = $cmax > 0 ? round( $c['sales'] / $cmax * 100 ) : 0;
			echo '<div class="tx-bar-row"><div class="tx-bar-head"><span class="tx-trunc">' . esc_html( $c['name'] ) . '</span><b>' . (int) $c['sales'] . ' satış</b></div><div class="tx-bar"><span style="width:' . (int) $pct . '%"></span></div></div>';
		}
		echo '</div></div></div>';
		echo '</div>';

		// Son siparişler
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Son siparişler</h3><div class="card-tools"><a class="tx-link" href="' . esc_url( self::url( 'orders' ) ) . '">Tümü →</a></div></div><div class="card-body p-0">';
		self::orders_table( $recent );
		echo '</div></div>';
	}

	protected static function orders_table( $orders, $manage = false, $is_trash = false, $ret = '' ) {
		if ( empty( $orders ) ) { echo '<div class="tx-empty">Kayıt yok.</div>'; return; }
		echo '<div class="tx-tablewrap"><table class="table table-hover tx-table mb-0' . ( $manage ? ' tx-orders' : '' ) . '"><thead><tr>';
		if ( $manage ) { echo '<th style="width:32px"><input type="checkbox" class="tx-checkall"></th>'; }
		echo '<th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th>Ödeme</th>';
		if ( $manage ) { echo '<th>Kaynak</th><th>Fatura tipi</th>'; }
		echo '<th>Durum</th><th class="text-right">Tutar</th>';
		if ( $manage ) { echo '<th>Menşe / Gönderi</th><th>Faturalama</th><th>Eylemler</th>'; }
		else { echo '<th></th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $orders as $o ) {
			if ( ! is_a( $o, 'WC_Order' ) ) { continue; }
			$st = $o->get_status();
			$name = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
			if ( ! $name ) { $name = $o->get_billing_company() ? $o->get_billing_company() : '—'; }
			$date = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '—';
			$pm = wp_strip_all_tags( (string) $o->get_payment_method_title() ); if ( $pm === '' ) { $pm = '—'; }
			$rn = wp_create_nonce( 'wcp_row_' . $o->get_id() );
			echo '<tr>';
			if ( $manage ) { echo '<td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $o->get_id() ) . '"></td>'; }
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">#' . esc_html( $o->get_order_number() ) . '</a>';
			if ( $manage ) {
				echo '<div class="tx-row-actions">';
				if ( $is_trash ) {
					echo '<a href="' . esc_url( self::url( 'orders', 0, array( 'row' => 'untrash', 'id' => $o->get_id(), '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '">Geri yükle</a> · <a class="tx-del" href="' . esc_url( self::url( 'orders', 0, array( 'row' => 'delete', 'id' => $o->get_id(), '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '" onclick="return confirm(\'Kalıcı olarak silinsin mi?\')">Kalıcı sil</a>';
				} else {
					echo '<a href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">Görüntüle</a> · <a class="tx-del" href="' . esc_url( self::url( 'orders', 0, array( 'row' => 'trash', 'id' => $o->get_id(), '_wpnonce' => $rn, 'ret_status' => $ret ) ) ) . '">Çöpe at</a>';
				}
				echo '</div>';
			}
			echo '</td>';
			echo '<td class="text-muted tx-c-date">' . esc_html( $date ) . '</td><td class="tx-c-cust"><span class="tx-trunc">' . esc_html( $name ) . '</span></td>';
			echo '<td class="text-muted tx-c-pay"><span class="tx-trunc">' . esc_html( $pm ) . '</span></td>';
			if ( $manage ) {
				$origin = self::order_origin( $o );
				echo '<td class="text-muted tx-c-src"><span class="tx-trunc" title="' . esc_attr( $origin ) . '">' . esc_html( $origin ) . '</span></td>';
				$ftk = ( self::fatura_tipi( $o ) === 'Kurumsal' );
				echo '<td><span class="tx-badge ' . ( $ftk ? 'tx-info' : 'tx-muted' ) . '">' . esc_html( self::fatura_tipi( $o ) ) . '</span></td>';
			}
			echo '<td><span class="tx-badge ' . esc_attr( self::badge_class( $st ) ) . '">' . esc_html( self::status_label( $st ) ) . '</span></td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $o->get_total() ) ) . '</td>';
			if ( $manage ) {
				$g = self::gonderi_ozet( $o );
				echo '<td class="text-muted tx-c-ship">' . ( $g ? '<span class="tx-trunc">' . wp_kses_post( $g ) . '</span>' : '—' ) . '</td>';
				$inv = $o->get_meta( '_wcpdf_invoice_number' );
				echo '<td>' . ( $inv ? '<span class="tx-strong">#' . esc_html( $inv ) . '</span>' : '<span class="text-muted">—</span>' ) . '</td>';
				echo '<td class="tx-actions-cell"><div class="tx-doc-actions">';
				echo '<a class="tx-doc" title="PDF Fatura" target="_blank" rel="noopener" href="' . esc_url( self::pdf_url( $o->get_id(), 'invoice' ) ) . '"><i class="fas fa-file-invoice"></i></a>';
				echo '<a class="tx-doc" title="Paketleme Fişi" target="_blank" rel="noopener" href="' . esc_url( self::pdf_url( $o->get_id(), 'packing-slip' ) ) . '"><i class="fas fa-box-open"></i></a>';
				echo '<a class="tx-doc" title="Görüntüle" href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '"><i class="fas fa-eye"></i></a>';
				echo '</div></td>';
			} else {
				echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">Görüntüle</a></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ---------------- orders ---------------- */
	protected static function view_orders() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$is_trash = ( $status === 'trash' );
		$s        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$df       = isset( $_GET['df'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['df'] ) : '';
		$dt       = isset( $_GET['dt'] ) ? preg_replace( '/[^0-9\-]/', '', $_GET['dt'] ) : '';
		$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per = 20; $max = 1; $orders = array();

		if ( class_exists( 'WooCommerce' ) ) {
			if ( $s !== '' && is_numeric( $s ) ) {
				$o = wc_get_order( absint( $s ) ); $orders = ( $o && is_a( $o, 'WC_Order' ) ) ? array( $o ) : array();
			} else {
				$args = array( 'limit' => $per, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC', 'paginate' => true );
				if ( $is_trash ) { $args['status'] = 'trash'; }
				elseif ( $status ) { $args['status'] = $status; }
				else { $args['status'] = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ); } // Tümü: taslakları gizle
				if ( $s !== '' && strpos( $s, '@' ) !== false ) { $args['customer'] = $s; }
				if ( $df && $dt ) { $args['date_created'] = $df . '...' . $dt; }
				elseif ( $df ) { $args['date_created'] = '>=' . $df; }
				elseif ( $dt ) { $args['date_created'] = '<=' . $dt; }
				$res = wc_get_orders( $args );
				$orders = $res->orders; $max = (int) $res->max_num_pages;
			}
		}

		// Sayaçlar
		$cc = array(); $cstatuses = array( 'processing', 'pending', 'on-hold', 'hezarfen-shipped', 'completed', 'cancelled' );
		foreach ( $cstatuses as $st ) { $cc[ $st ] = (int) wc_orders_count( $st ); }
		$draft_n = (int) wc_orders_count( 'checkout-draft' );
		$trash_n = count( wc_get_orders( array( 'status' => 'trash', 'limit' => -1, 'return' => 'ids' ) ) );

		// Filtre çubuğu
		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'orders' ) ) . '">';
		if ( $status ) { echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">'; }
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Sipariş no / e-posta">';
		echo '<input type="date" class="form-control" name="df" value="' . esc_attr( $df ) . '" title="Başlangıç tarihi">';
		echo '<input type="date" class="form-control" name="dt" value="' . esc_attr( $dt ) . '" title="Bitiş tarihi">';
		echo '<button class="btn tx-btn" type="submit">Filtrele</button>';
		if ( $s || $df || $dt ) { echo '<a class="btn tx-btn" href="' . esc_url( $status ? self::url( 'orders', 0, array( 'status' => $status ) ) : self::url( 'orders' ) ) . '">Temizle</a>'; }
		echo '</form>';

		// Durum çipleri (sayaçlı) + Çöp
		echo '<div class="tx-chips mb-3">';
		$tabs = array( '' => 'Tümü', 'processing' => 'Hazırlanıyor', 'pending' => 'Ödeme bekliyor', 'on-hold' => 'Beklemede', 'hezarfen-shipped' => 'Kargoda', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal' );
		foreach ( $tabs as $k => $v ) {
			$on  = ( ! $is_trash && $k === $status ) ? ' is-on' : '';
			$lbl = $v . ( $k && isset( $cc[ $k ] ) ? ' (' . $cc[ $k ] . ')' : '' );
			echo '<a class="tx-chip' . $on . '" href="' . esc_url( $k ? self::url( 'orders', 0, array( 'status' => $k ) ) : self::url( 'orders' ) ) . '">' . esc_html( $lbl ) . '</a>';
		}
		echo '<a class="tx-chip' . ( $status === 'checkout-draft' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'orders', 0, array( 'status' => 'checkout-draft' ) ) ) . '">Taslak (' . $draft_n . ')</a>';
		echo '<a class="tx-chip tx-chip-trash' . ( $is_trash ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'orders', 0, array( 'status' => 'trash' ) ) ) . '">🗑 Çöp (' . $trash_n . ')</a>';
		echo '</div>';

		// Toplu işlem + tablo (tek form)
		$form_action = $status ? self::url( 'orders', 0, array( 'status' => $status ) ) : self::url( 'orders' );
		echo '<form method="post" action="' . esc_url( $form_action ) . '">';
		wp_nonce_field( 'wcp_orders_bulk' );
		echo '<input type="hidden" name="wcp_action" value="orders_bulk"><input type="hidden" name="ret_status" value="' . esc_attr( $status ) . '">';
		echo '<div class="tx-bulkbar mb-2"><select name="bulk" class="form-control tx-select">';
		if ( $is_trash ) {
			echo '<option value="">Toplu işlem…</option><option value="untrash">Geri yükle</option><option value="delete">Kalıcı olarak sil</option>';
		} else {
			echo '<option value="">Toplu işlem…</option><option value="status-processing">Hazırlanıyor yap</option><option value="status-completed">Tamamlandı yap</option><option value="status-on-hold">Beklemede yap</option><option value="status-cancelled">İptal et</option><option value="trash">Çöp kutusuna taşı</option>';
		}
		echo '</select><button class="btn tx-btn" type="submit" onclick="return this.form.bulk.value!==\'\'">Uygula</button></div>';
		echo '<div class="card card-outline tx-card"><div class="card-body p-0">';
		self::orders_table( $orders, true, $is_trash, $status );
		echo '</div></div></form>';

		// Sayfalama
		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) {
				$a = array( 'paged' => $i );
				if ( $status ) { $a['status'] = $status; } if ( $s ) { $a['s'] = $s; } if ( $df ) { $a['df'] = $df; } if ( $dt ) { $a['dt'] = $dt; }
				echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'orders', 0, $a ) ) . '">' . $i . '</a>';
			}
			echo '</div>';
		}

		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	protected static function view_order( $id ) {
		$o = $id && class_exists( 'WooCommerce' ) ? wc_get_order( $id ) : false;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'orders' ) ) . '">← Siparişlere dön</a>';
		if ( ! $o ) { echo '<div class="tx-empty">Sipariş bulunamadı.</div>'; return; }
		$msg = isset( $_GET['msg'] ) ? $_GET['msg'] : '';
		$flash = array( 'ok' => 'Durum güncellendi.', 'note' => 'Not eklendi.', 'saved' => 'Bilgiler kaydedildi.', 'action' => 'İşlem uygulandı.', 'items' => 'Kalemler güncellendi.' );
		if ( isset( $flash[ $msg ] ) ) { echo '<div class="alert tx-flash">' . esc_html( $flash[ $msg ] ) . '</div>'; }
		$editable = current_user_can( 'edit_shop_orders' );

		echo '<div class="row"><div class="col-lg-8">';

		// Kalemler (ürün + ücret + kargo + iade) + ekleme araçları
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Sipariş #' . esc_html( $o->get_order_number() ) . ' — Kalemler</h3><div class="card-tools"><span class="tx-badge ' . esc_attr( self::badge_class( $o->get_status() ) ) . '">' . esc_html( self::status_label( $o->get_status() ) ) . '</span></div></div><div class="card-body p-0">';
		echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '">';
		wp_nonce_field( 'wcp_order_' . $id );
		echo '<input type="hidden" name="wcp_action" value="order_items"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
		echo '<table class="table tx-table tx-table-sm mb-0"><thead><tr><th></th><th>Kalem</th><th style="width:74px">Adet</th><th class="text-right">Tutar</th>' . ( $editable ? '<th style="width:30px"></th>' : '' ) . '</tr></thead><tbody>';
		foreach ( $o->get_items() as $iid => $item ) {
			$prod = $item->get_product();
			$img  = ( $prod && $prod->get_image_id() ) ? wp_get_attachment_image_url( $prod->get_image_id(), array( 38, 38 ) ) : wc_placeholder_img_src();
			echo '<tr><td style="width:44px"><img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt=""></td>';
			echo '<td>' . esc_html( $item->get_name() );
			if ( $prod && $prod->get_sku() ) { echo '<div class="tx-mini">SKU: ' . esc_html( $prod->get_sku() ) . '</div>'; }
			echo '</td>';
			echo '<td>' . ( $editable ? '<input type="number" min="1" class="form-control tx-qty" name="qty[' . esc_attr( $iid ) . ']" value="' . esc_attr( $item->get_quantity() ) . '">' : 'x' . esc_html( $item->get_quantity() ) ) . '</td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $item->get_total() ) ) . '</td>';
			if ( $editable ) { echo '<td class="text-right"><button class="tx-itemdel" type="submit" name="remove_item" value="' . esc_attr( $iid ) . '" title="Kaldır" onclick="return confirm(\'Kaldırılsın mı?\')"><i class="fas fa-times"></i></button></td>'; }
			echo '</tr>';
		}
		foreach ( $o->get_items( 'fee' ) as $iid => $fee ) {
			echo '<tr class="tx-extrarow"><td class="text-center"><i class="fas fa-percent text-muted"></i></td><td>' . esc_html( $fee->get_name() ) . ' <span class="tx-mini">(ücret)</span></td><td></td><td class="text-right tx-strong">' . wp_kses_post( self::money( $fee->get_total() ) ) . '</td>';
			if ( $editable ) { echo '<td class="text-right"><button class="tx-itemdel" type="submit" name="remove_item" value="' . esc_attr( $iid ) . '" onclick="return confirm(\'Kaldırılsın mı?\')"><i class="fas fa-times"></i></button></td>'; }
			echo '</tr>';
		}
		foreach ( $o->get_items( 'shipping' ) as $iid => $sh ) {
			echo '<tr class="tx-extrarow"><td class="text-center"><i class="fas fa-truck text-muted"></i></td><td>' . esc_html( $sh->get_method_title() ) . ' <span class="tx-mini">(kargo)</span></td><td></td><td class="text-right tx-strong">' . wp_kses_post( self::money( $sh->get_total() ) ) . '</td>';
			if ( $editable ) { echo '<td class="text-right"><button class="tx-itemdel" type="submit" name="remove_item" value="' . esc_attr( $iid ) . '" onclick="return confirm(\'Kaldırılsın mı?\')"><i class="fas fa-times"></i></button></td>'; }
			echo '</tr>';
		}
		$end = $editable ? '<td></td>' : '';
		echo '</tbody><tfoot>';
		echo '<tr><td colspan="3" class="text-right text-muted">Ara toplam</td><td class="text-right">' . wp_kses_post( self::money( $o->get_subtotal() ) ) . '</td>' . $end . '</tr>';
		if ( (float) $o->get_total_discount() > 0 ) { echo '<tr><td colspan="3" class="text-right text-muted">İndirim</td><td class="text-right">- ' . wp_kses_post( self::money( $o->get_total_discount() ) ) . '</td>' . $end . '</tr>'; }
		if ( (float) $o->get_total_tax() > 0 ) { echo '<tr><td colspan="3" class="text-right text-muted">KDV</td><td class="text-right">' . wp_kses_post( self::money( $o->get_total_tax() ) ) . '</td>' . $end . '</tr>'; }
		echo '<tr class="tx-total"><td colspan="3" class="text-right"><b>Toplam</b></td><td class="text-right"><b>' . wp_kses_post( self::money( $o->get_total() ) ) . '</b></td>' . $end . '</tr>';
		$refunded = (float) $o->get_total_refunded();
		if ( $refunded > 0 ) {
			echo '<tr><td colspan="3" class="text-right text-muted">İade edildi</td><td class="text-right tx-refund">- ' . wp_kses_post( self::money( $refunded ) ) . '</td>' . $end . '</tr>';
			echo '<tr><td colspan="3" class="text-right text-muted">Net tahsilat</td><td class="text-right tx-strong">' . wp_kses_post( self::money( $o->get_total() - $refunded ) ) . '</td>' . $end . '</tr>';
		}
		echo '</tfoot></table>';
		if ( $editable ) { echo '<div class="tx-itemsbar"><button class="btn btn-sm tx-btn primary" type="submit"><i class="fas fa-calculator mr-1"></i> Adetleri kaydet & yeniden hesapla</button></div>'; }
		echo '</form>';
		if ( $editable ) {
			echo '<div class="tx-addtools">';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '" class="tx-addform"><input type="hidden" name="wcp_action" value="order_add_item"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<span class="tx-addlbl"><i class="fas fa-plus"></i> Ürün</span><input class="form-control" name="add_product" placeholder="SKU veya ürün ID"><input class="form-control tx-qty2" type="number" min="1" value="1" name="add_qty"><button class="btn tx-btn" type="submit">Ekle</button></form>';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '" class="tx-addform"><input type="hidden" name="wcp_action" value="order_add_fee"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<span class="tx-addlbl"><i class="fas fa-percent"></i> Komisyon/Ücret</span><input class="form-control" name="fee_name" value="Kredi kartı komisyonu"><input class="form-control tx-qty2" name="fee_amount" placeholder="Tutar"><button class="btn tx-btn" type="submit">Ekle</button></form>';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '" class="tx-addform"><input type="hidden" name="wcp_action" value="order_add_shipping"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<span class="tx-addlbl"><i class="fas fa-truck"></i> Kargo</span><input class="form-control" name="ship_title" value="Kargo ile adrese teslim"><input class="form-control tx-qty2" name="ship_amount" placeholder="Ücret"><button class="btn tx-btn" type="submit">Ekle</button></form>';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '" class="tx-addform tx-refundform" onsubmit="return confirm(\'İade işlenecek, onaylıyor musunuz?\')"><input type="hidden" name="wcp_action" value="order_refund"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<span class="tx-addlbl"><i class="fas fa-rotate-left"></i> Para iadesi</span><input class="form-control tx-qty2" name="refund_amount" placeholder="Tutar"><input class="form-control" name="refund_reason" placeholder="Sebep (ops.)"><label class="tx-check"><input type="checkbox" name="restock" value="1"> Stoğa iade</label><button class="btn tx-btn tx-btn-danger" type="submit">İade et</button></form>';
			echo '</div>';
		}
		echo '</div></div>';

		// Fatura & Adres (düzenlenebilir) — sol sütun
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Fatura & Adres Bilgileri</h3></div><div class="card-body">';
		if ( $editable ) {
			$bv = function ( $f ) use ( $o ) { $m = 'get_billing_' . $f; return esc_attr( $o->$m() ); };
			$sv = function ( $f ) use ( $o ) { $m = 'get_shipping_' . $f; return method_exists( $o, $m ) ? esc_attr( $o->$m() ) : ''; };
			$htype = $o->get_meta( '_billing_hez_invoice_type' ) === 'company' ? 'company' : 'person';
			$odate = $o->get_date_created() ? $o->get_date_created()->date( 'Y-m-d\TH:i' ) : '';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<input type="hidden" name="wcp_action" value="order_save"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			echo '<div class="tx-formgrid tx-formgrid-wide">';
			echo '<div class="full"><label class="tx-label">Fatura tipi</label><select name="hez_type" class="form-control"><option value="person"' . selected( $htype, 'person', false ) . '>Bireysel</option><option value="company"' . selected( $htype, 'company', false ) . '>Kurumsal</option></select></div>';
			echo '<div><label class="tx-label">T.C. No</label><input class="form-control" name="hez_tc" value="' . esc_attr( $o->get_meta( '_billing_hez_TC_number' ) ) . '"></div>';
			echo '<div><label class="tx-label">Vergi No</label><input class="form-control" name="hez_tax_number" value="' . esc_attr( $o->get_meta( '_billing_hez_tax_number' ) ) . '"></div>';
			echo '<div><label class="tx-label">Vergi Dairesi</label><input class="form-control" name="hez_tax_office" value="' . esc_attr( $o->get_meta( '_billing_hez_tax_office' ) ) . '"></div>';
			echo '<div><label class="tx-label">Ad</label><input class="form-control" name="b_first_name" value="' . $bv( 'first_name' ) . '"></div>';
			echo '<div><label class="tx-label">Soyad</label><input class="form-control" name="b_last_name" value="' . $bv( 'last_name' ) . '"></div>';
			echo '<div><label class="tx-label">Telefon</label><input class="form-control" name="b_phone" value="' . $bv( 'phone' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Firma</label><input class="form-control" name="b_company" value="' . $bv( 'company' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">E-posta</label><input class="form-control" name="b_email" value="' . $bv( 'email' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Adres</label><input class="form-control" name="b_address_1" value="' . $bv( 'address_1' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Adres 2</label><input class="form-control" name="b_address_2" value="' . $bv( 'address_2' ) . '"></div>';
			echo '<div><label class="tx-label">Şehir</label><input class="form-control" name="b_city" value="' . $bv( 'city' ) . '"></div>';
			echo '<div><label class="tx-label">İlçe/Eyalet</label><input class="form-control" name="b_state" value="' . $bv( 'state' ) . '"></div>';
			echo '<div><label class="tx-label">Posta Kodu</label><input class="form-control" name="b_postcode" value="' . $bv( 'postcode' ) . '"></div>';
			echo '<div><label class="tx-label">Ülke</label><input class="form-control" name="b_country" value="' . $bv( 'country' ) . '"></div>';
			echo '</div>';
			echo '<details class="tx-details"><summary>Teslimat adresi (farklıysa)</summary><div class="tx-formgrid tx-formgrid-wide mt-2">';
			echo '<div><label class="tx-label">Ad</label><input class="form-control" name="s_first_name" value="' . $sv( 'first_name' ) . '"></div>';
			echo '<div><label class="tx-label">Soyad</label><input class="form-control" name="s_last_name" value="' . $sv( 'last_name' ) . '"></div>';
			echo '<div><label class="tx-label">Telefon</label><input class="form-control" name="s_phone" value="' . $sv( 'phone' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Adres</label><input class="form-control" name="s_address_1" value="' . $sv( 'address_1' ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Adres 2</label><input class="form-control" name="s_address_2" value="' . $sv( 'address_2' ) . '"></div>';
			echo '<div><label class="tx-label">Şehir</label><input class="form-control" name="s_city" value="' . $sv( 'city' ) . '"></div>';
			echo '<div><label class="tx-label">İlçe</label><input class="form-control" name="s_state" value="' . $sv( 'state' ) . '"></div>';
			echo '<div><label class="tx-label">Posta</label><input class="form-control" name="s_postcode" value="' . $sv( 'postcode' ) . '"></div>';
			echo '</div></details>';
			echo '<div class="tx-formgrid tx-formgrid-wide mt-2">';
			echo '<div><label class="tx-label">Sipariş tarihi</label><input type="datetime-local" class="form-control" name="order_date" value="' . esc_attr( $odate ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Müşteri notu</label><textarea class="form-control" name="cust_note" rows="2">' . esc_textarea( $o->get_customer_note() ) . '</textarea></div>';
			echo '</div>';
			echo '<button class="btn tx-btn primary mt-2" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
			echo '</form>';
		} else {
			echo '<div class="tx-addr">' . wp_kses_post( $o->get_formatted_billing_address() ? $o->get_formatted_billing_address() : '—' ) . '</div>';
		}
		echo '</div></div>';

		// Müşteri geçmişi
		$cid = $o->get_customer_id();
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Müşteri geçmişi</h3></div><div class="card-body">';
		if ( $cid ) {
			echo '<div class="tx-histstats"><div><span class="tx-hs-v">' . (int) wc_get_customer_order_count( $cid ) . '</span><span class="tx-hs-l">sipariş</span></div><div><span class="tx-hs-v">' . wp_kses_post( self::money( wc_get_customer_total_spent( $cid ) ) ) . '</span><span class="tx-hs-l">toplam harcama</span></div></div>';
			$hist = wc_get_orders( array( 'customer_id' => $cid, 'limit' => 6, 'orderby' => 'date', 'order' => 'DESC', 'exclude' => array( $id ), 'status' => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ) ) );
			if ( $hist ) {
				echo '<table class="table tx-table tx-table-sm mb-0"><tbody>';
				foreach ( $hist as $h ) {
					echo '<tr><td><a class="tx-strong" href="' . esc_url( self::url( 'order', $h->get_id() ) ) . '">#' . esc_html( $h->get_order_number() ) . '</a></td><td class="text-muted">' . esc_html( $h->get_date_created() ? $h->get_date_created()->date_i18n( 'd.m.Y' ) : '' ) . '</td><td><span class="tx-badge ' . esc_attr( self::badge_class( $h->get_status() ) ) . '">' . esc_html( self::status_label( $h->get_status() ) ) . '</span></td><td class="text-right tx-strong">' . wp_kses_post( self::money( $h->get_total() ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			} else { echo '<p class="text-muted mb-0">Başka sipariş yok.</p>'; }
		} else { echo '<p class="text-muted mb-0">Misafir sipariş (kayıtlı müşteri değil).</p>'; }
		echo '</div></div>';

		// PayTR ödeme denemeleri
		$attempts = self::paytr_attempts( $o );
		if ( $attempts ) {
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">PayTR ödeme denemeleri</h3></div><div class="card-body p-0"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Tarih</th><th class="text-right">Tutar</th><th>Tip</th><th>Sonuç</th></tr></thead><tbody>';
			foreach ( $attempts as $a ) {
				$amt = isset( $a['amount'] ) ? ( (float) $a['amount'] ) / 100 : 0;
				$st = isset( $a['status'] ) ? $a['status'] : '';
				$cb = isset( $a['callback_status'] ) ? $a['callback_status'] : '';
				$type = isset( $a['payment_type'] ) ? $a['payment_type'] : '';
				$ok = ( $st === 'success' || $cb === 'success' );
				echo '<tr><td class="text-muted">' . esc_html( isset( $a['created_at'] ) ? $a['created_at'] : '' ) . '</td><td class="text-right tx-strong">' . wp_kses_post( self::money( $amt ) ) . '</td><td class="text-muted">' . esc_html( $type === 'card' ? 'Kart' : ( $type ? $type : '—' ) ) . '</td><td><span class="tx-badge ' . ( $ok ? 'tx-ok' : 'tx-bad' ) . '">' . esc_html( $ok ? 'Başarılı' : ( $cb ? $cb : ( $st ? $st : 'Bekliyor' ) ) ) . '</span></td></tr>';
			}
			echo '</tbody></table></div></div>';
		}

		// Sipariş kaynağı / atıf detayları (WooCommerce Order Attribution + PixelYourSite) — geniş sol sütun
		self::order_attribution_box( $o );

		// (Sipariş nitelikleri ve notları sağ sütuna taşındı)

		echo '</div><div class="col-lg-4">';

		// Kargo (Yurtiçi) kutusu — bağımsız mu-plugin (wcp-kargo.php)
		if ( function_exists( 'wcp_kargo_panel_box' ) ) { wcp_kargo_panel_box( $o ); }

		// Durumu değiştir — EN ÜSTTE
		if ( current_user_can( 'edit_shop_orders' ) ) {
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Durumu değiştir</h3></div><div class="card-body">';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<input type="hidden" name="wcp_action" value="order_status"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			echo '<select name="new_status" class="form-control tx-select mb-2">';
			foreach ( wc_get_order_statuses() as $k => $v ) {
				echo '<option value="' . esc_attr( str_replace( 'wc-', '', $k ) ) . '"' . selected( 'wc-' . $o->get_status(), $k, false ) . '>' . esc_html( $v ) . '</option>';
			}
			echo '</select><button class="btn btn-block tx-btn primary" type="submit">Güncelle</button></form>';
			echo '</div></div>';
		}

		// Sipariş işlemleri (e-posta gönder vb.)
		if ( $editable ) {
			$oactions = apply_filters( 'woocommerce_order_actions', array(
				'send_order_details'              => 'Sipariş/fatura detaylarını müşteriye gönder',
				'send_order_details_admin'        => 'Yeni sipariş bildirimini tekrar gönder',
				'regenerate_download_permissions' => 'İndirme izinlerini yenile',
			), $o );
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Sipariş işlemleri</h3></div><div class="card-body">';
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<input type="hidden" name="wcp_action" value="order_action"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			echo '<select name="order_action" class="form-control tx-select mb-2">';
			foreach ( $oactions as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; }
			echo '</select><button class="btn btn-block tx-btn" type="submit">Uygula</button></form>';
			echo '</div></div>';
		}

		// Müşteri + müşteri notu
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Müşteri</h3></div><div class="card-body tx-kv">';
		echo '<div><span>Ad</span><b>' . esc_html( trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) ) . '</b></div>';
		echo '<div><span>E-posta</span><b>' . esc_html( $o->get_billing_email() ) . '</b></div>';
		echo '<div><span>Telefon</span><b>' . esc_html( $o->get_billing_phone() ) . '</b></div>';
		echo '</div>';
		if ( $o->get_customer_note() ) { echo '<div class="tx-custnote"><i class="fas fa-quote-left mr-1"></i> ' . esc_html( $o->get_customer_note() ) . '</div>'; }
		echo '</div>';

		// Ödeme & Kargo
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ödeme & Kargo</h3></div><div class="card-body tx-kv">';
		$pm_title = wp_strip_all_tags( (string) $o->get_payment_method_title() ); if ( $pm_title === '' ) { $pm_title = '—'; }
		echo '<div><span>Ödeme</span><b>' . esc_html( $pm_title ) . '</b></div>';
		$taksit = self::paytr_taksit( $o );
		if ( $taksit ) { echo '<div><span>Taksit</span><b>' . esc_html( $taksit ) . '</b></div>'; }
		$smethod = $o->get_shipping_method();
		echo '<div><span>Teslimat</span><b>' . esc_html( $smethod ? $smethod : '—' ) . '</b></div>';
		$ship = $o->get_meta( '_hezarfen_mst_shipment_data' );
		if ( $ship ) {
			$pp = explode( '||', $ship );
			$carrier = isset( $pp[3] ) ? $pp[3] : ''; $tno = isset( $pp[4] ) ? $pp[4] : ''; $turl = isset( $pp[5] ) ? $pp[5] : '';
			if ( $carrier ) { echo '<div><span>Kargo</span><b>' . esc_html( $carrier ) . '</b></div>'; }
			if ( $tno ) { echo '<div><span>Takip No</span><b>' . ( $turl ? '<a href="' . esc_url( $turl ) . '" target="_blank">' . esc_html( $tno ) . '</a>' : esc_html( $tno ) ) . '</b></div>'; }
		}
		echo '</div></div>';

		// Fatura & Belgeler
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Fatura & Belgeler</h3></div><div class="card-body">';
		echo '<div class="tx-kv">';
		echo '<div><span>Fatura tipi</span><b>' . esc_html( self::fatura_tipi( $o ) ) . '</b></div>';
		if ( self::fatura_tipi( $o ) === 'Kurumsal' ) {
			if ( $o->get_meta( '_billing_hez_tax_number' ) ) { echo '<div><span>Vergi No</span><b>' . esc_html( $o->get_meta( '_billing_hez_tax_number' ) ) . '</b></div>'; }
			if ( $o->get_meta( '_billing_hez_tax_office' ) ) { echo '<div><span>Vergi Dairesi</span><b>' . esc_html( $o->get_meta( '_billing_hez_tax_office' ) ) . '</b></div>'; }
		} elseif ( $o->get_meta( '_billing_hez_TC_number' ) ) {
			echo '<div><span>T.C. No</span><b>' . esc_html( $o->get_meta( '_billing_hez_TC_number' ) ) . '</b></div>';
		}
		if ( $o->get_meta( '_wcpdf_invoice_number' ) ) { echo '<div><span>Fatura No</span><b>#' . esc_html( $o->get_meta( '_wcpdf_invoice_number' ) ) . '</b></div>'; }
		echo '</div>';
		echo '<div class="tx-doc-btns mt-2">';
		echo '<a class="btn tx-btn" target="_blank" rel="noopener" href="' . esc_url( self::pdf_url( $id, 'invoice' ) ) . '"><i class="fas fa-file-invoice mr-1"></i> PDF Fatura</a>';
		echo '<a class="btn tx-btn" target="_blank" rel="noopener" href="' . esc_url( self::pdf_url( $id, 'packing-slip' ) ) . '"><i class="fas fa-box-open mr-1"></i> Paketleme Fişi</a>';
		echo '</div></div></div>';

		// Sipariş nitelikleri
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Sipariş nitelikleri</h3></div><div class="card-body tx-kv">';
		echo '<div><span>Sipariş No</span><b>#' . esc_html( $o->get_order_number() ) . '</b></div>';
		echo '<div><span>Oluşturma</span><b>' . esc_html( $o->get_created_via() ? $o->get_created_via() : '—' ) . '</b></div>';
		echo '<div><span>Oluşturulma</span><b>' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '—' ) . '</b></div>';
		echo '<div><span>Ödeme tarihi</span><b>' . esc_html( $o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'd.m.Y H:i' ) : '—' ) . '</b></div>';
		echo '<div><span>Para birimi</span><b>' . esc_html( $o->get_currency() ) . '</b></div>';
		echo '<div><span>Müşteri IP</span><b>' . esc_html( $o->get_customer_ip_address() ? $o->get_customer_ip_address() : '—' ) . '</b></div>';
		echo '<div><span>Sipariş anahtarı</span><b class="tx-mini2">' . esc_html( $o->get_order_key() ) . '</b></div>';
		echo '</div></div>';

		// Sipariş notları
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Sipariş notları</h3></div><div class="card-body">';
		$notes = wc_get_order_notes( array( 'order_id' => $id, 'order_by' => 'date_created', 'order' => 'DESC' ) );
		if ( $notes ) {
			echo '<ul class="tx-notes">';
			foreach ( $notes as $n ) {
				$cls = $n->customer_note ? ' is-customer' : ( 'system' === $n->added_by ? ' is-system' : '' );
				echo '<li class="tx-note' . $cls . '"><div class="tx-note-b">' . wp_kses_post( wpautop( $n->content ) ) . '</div><div class="tx-note-m">' . esc_html( $n->date_created->date_i18n( 'd.m.Y H:i' ) ) . ( $n->customer_note ? ' · müşteriye gönderildi' : '' ) . '</div></li>';
			}
			echo '</ul>';
		} else { echo '<p class="text-muted mb-2">Henüz not yok.</p>'; }
		if ( current_user_can( 'edit_shop_orders' ) ) {
			echo '<form method="post" action="' . esc_url( self::url( 'order', $id ) ) . '" class="mt-2">';
			wp_nonce_field( 'wcp_order_' . $id );
			echo '<input type="hidden" name="wcp_action" value="order_note"><input type="hidden" name="order_id" value="' . esc_attr( $id ) . '">';
			echo '<textarea class="form-control mb-2" name="note" rows="2" placeholder="Not ekle..." required></textarea>';
			echo '<label class="tx-check mb-2"><input type="checkbox" name="note_customer" value="1"> Müşteriye e-posta gönder</label>';
			echo '<button class="btn tx-btn primary" type="submit">Not ekle</button></form>';
		}
		echo '</div></div>';

		echo '</div></div>';
	}

	/* ---------------- products ---------------- */
	protected static function view_products() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$is_trash = ( $status === 'trash' );
		$s        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$cat      = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
		$stockf   = isset( $_GET['stock'] ) ? sanitize_key( $_GET['stock'] ) : '';
		$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per = 24; $max = 1; $products = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$args = array( 'limit' => $per, 'page' => $paged, 'orderby' => 'date', 'order' => 'DESC', 'paginate' => true );
			if ( $is_trash ) { $args['status'] = 'trash'; }
			elseif ( $status === 'publish' || $status === 'draft' ) { $args['status'] = $status; }
			else { $args['status'] = array( 'publish', 'draft', 'pending', 'private' ); }
			if ( $s ) { $args['s'] = $s; }
			if ( $cat ) { $args['category'] = array( $cat ); }
			if ( in_array( $stockf, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) { $args['stock_status'] = $stockf; }
			$res = wc_get_products( $args );
			$products = $res->products; $max = (int) $res->max_num_pages;
		}
		$pc = wp_count_posts( 'product' );
		$n_pub = isset( $pc->publish ) ? (int) $pc->publish : 0;
		$n_draft = isset( $pc->draft ) ? (int) $pc->draft : 0;
		$n_trash = isset( $pc->trash ) ? (int) $pc->trash : 0;

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'products' ) ) . '">';
		if ( $status ) { echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">'; }
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Ürün / SKU ara">';
		echo '<select name="cat" class="form-control"><option value="">Tüm kategoriler</option>';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) ) as $c ) { echo '<option value="' . esc_attr( $c->slug ) . '"' . selected( $cat, $c->slug, false ) . '>' . esc_html( $c->name ) . ' (' . $c->count . ')</option>'; }
		echo '</select>';
		echo '<select name="stock" class="form-control"><option value="">Tüm stok</option><option value="instock"' . selected( $stockf, 'instock', false ) . '>Stokta</option><option value="outofstock"' . selected( $stockf, 'outofstock', false ) . '>Tükendi</option><option value="onbackorder"' . selected( $stockf, 'onbackorder', false ) . '>Ön sipariş</option></select>';
		echo '<button class="btn tx-btn" type="submit">Filtrele</button>';
		echo '<a class="btn tx-btn primary" href="' . esc_url( self::url( 'product' ) ) . '"><i class="fas fa-plus mr-1"></i> Yeni ürün</a></form>';

		echo '<div class="tx-chips mb-3">';
		echo '<a class="tx-chip' . ( ! $status ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'products' ) ) . '">Tümü</a>';
		echo '<a class="tx-chip' . ( $status === 'publish' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'products', 0, array( 'status' => 'publish' ) ) ) . '">Yayında (' . $n_pub . ')</a>';
		echo '<a class="tx-chip' . ( $status === 'draft' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'products', 0, array( 'status' => 'draft' ) ) ) . '">Taslak (' . $n_draft . ')</a>';
		echo '<a class="tx-chip tx-chip-trash' . ( $is_trash ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'products', 0, array( 'status' => 'trash' ) ) ) . '">🗑 Çöp (' . $n_trash . ')</a>';
		echo '</div>';

		echo '<form method="post" action="' . esc_url( $status ? self::url( 'products', 0, array( 'status' => $status ) ) : self::url( 'products' ) ) . '">';
		wp_nonce_field( 'wcp_products_bulk' );
		echo '<input type="hidden" name="wcp_action" value="products_bulk"><input type="hidden" name="ret_status" value="' . esc_attr( $status ) . '">';
		echo '<div class="tx-bulkbar mb-2"><select name="bulk" class="form-control tx-select">';
		if ( $is_trash ) { echo '<option value="">Toplu işlem…</option><option value="untrash">Geri yükle</option><option value="delete">Kalıcı sil</option>'; }
		else { echo '<option value="">Toplu işlem…</option><option value="publish">Yayınla</option><option value="draft">Taslak yap</option><option value="trash">Çöp kutusuna taşı</option>'; }
		echo '</select><button class="btn tx-btn" type="submit" onclick="return this.form.bulk.value!==\'\'">Uygula</button></div>';
		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th style="width:30px"><input type="checkbox" class="tx-checkall"></th><th></th><th>Ürün</th><th>SKU</th><th>Kategoriler</th><th>Stok</th><th class="text-right">Fiyat</th><th>Durum</th><th></th></tr></thead><tbody>';
		if ( empty( $products ) ) { echo '<tr><td colspan="9"><div class="tx-empty">Ürün yok.</div></td></tr>'; }
		foreach ( $products as $p ) {
			$img = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), array( 40, 40 ) ) : wc_placeholder_img_src();
			$stock = $p->get_stock_status();
			$sc = $stock === 'instock' ? 'tx-ok' : ( $stock === 'onbackorder' ? 'tx-warn' : 'tx-bad' );
			$sl = $stock === 'instock' ? ( $p->get_stock_quantity() !== null ? 'Stokta (' . $p->get_stock_quantity() . ')' : 'Stokta' ) : ( $stock === 'onbackorder' ? 'Ön sipariş' : 'Tükendi' );
			$cats_n = wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'names' ) );
			$pst = $p->get_status();
			$rn = wp_create_nonce( 'wcp_prow_' . $p->get_id() );
			echo '<tr><td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $p->get_id() ) . '"></td>';
			echo '<td><img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt=""></td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'product', $p->get_id() ) ) . '"><span class="tx-trunc" style="max-width:240px">' . esc_html( $p->get_name() ) . '</span></a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'product', $p->get_id() ) ) . '">Düzenle</a> · <a href="' . esc_url( get_permalink( $p->get_id() ) ) . '" target="_blank">Görüntüle</a> · ';
			if ( $is_trash ) {
				echo '<a href="' . esc_url( self::url( 'products', 0, array( 'prow' => 'untrash', 'id' => $p->get_id(), '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '">Geri yükle</a> · <a class="tx-del" href="' . esc_url( self::url( 'products', 0, array( 'prow' => 'delete', 'id' => $p->get_id(), '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '" onclick="return confirm(\'Kalıcı silinsin mi?\')">Kalıcı sil</a>';
			} else {
				echo '<a class="tx-del" href="' . esc_url( self::url( 'products', 0, array( 'prow' => 'trash', 'id' => $p->get_id(), '_wpnonce' => $rn, 'ret_status' => $status ) ) ) . '">Çöpe at</a>';
			}
			echo '</div></td>';
			echo '<td class="text-muted">' . esc_html( $p->get_sku() ? $p->get_sku() : '—' ) . '</td>';
			echo '<td class="text-muted"><span class="tx-trunc">' . esc_html( $cats_n ? implode( ', ', $cats_n ) : '—' ) . '</span></td>';
			echo '<td><span class="tx-badge ' . esc_attr( $sc ) . '">' . esc_html( $sl ) . '</span></td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( $p->get_price_html() ? $p->get_price_html() : self::money( $p->get_price() ) ) . '</td>';
			echo '<td><span class="tx-badge ' . ( $pst === 'publish' ? 'tx-ok' : 'tx-muted' ) . '">' . esc_html( $pst === 'publish' ? 'Yayında' : ( $pst === 'draft' ? 'Taslak' : $pst ) ) . '</span></td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'product', $p->get_id() ) ) . '">Düzenle</a></td></tr>';
		}
		echo '</tbody></table></div></div></div></form>';

		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) {
				$a = array( 'paged' => $i ); if ( $status ) { $a['status'] = $status; } if ( $s ) { $a['s'] = $s; } if ( $cat ) { $a['cat'] = $cat; } if ( $stockf ) { $a['stock'] = $stockf; }
				echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'products', 0, $a ) ) . '">' . $i . '</a>';
			}
			echo '</div>';
		}
		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	/* ---------------- Stok Yönetimi ---------------- */
	protected static function view_stock() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$s       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$cat     = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
		$stockf  = isset( $_GET['stock'] ) ? sanitize_key( $_GET['stock'] ) : '';
		$managed = ( isset( $_GET['managed'] ) && $_GET['managed'] === '1' );
		$paged   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per     = 30;
		$gthr    = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		if ( $gthr < 1 ) { $gthr = 2; }
		$products = array(); $max = 1;

		if ( $stockf === 'lowstock' ) {
			$mq = array(
				'relation' => 'AND',
				array( 'key' => '_manage_stock', 'value' => 'yes' ),
				array( 'key' => '_stock', 'value' => $gthr, 'compare' => '<=', 'type' => 'NUMERIC' ),
				array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			);
			$qa = array(
				'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => $per, 'paged' => $paged, 'orderby' => 'meta_value_num', 'meta_key' => '_stock', 'order' => 'ASC',
				'meta_query' => $mq, 'fields' => 'ids',
			);
			if ( $s ) { $qa['s'] = $s; }
			if ( $cat ) { $qa['tax_query'] = array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat ) ); }
			$q = new WP_Query( $qa );
			$max = (int) $q->max_num_pages;
			foreach ( $q->posts as $pid ) { $pp = wc_get_product( $pid ); if ( $pp ) { $products[] = $pp; } }
		} else {
			$args = array( 'limit' => $per, 'page' => $paged, 'orderby' => 'title', 'order' => 'ASC', 'paginate' => true, 'status' => array( 'publish', 'draft', 'pending', 'private' ) );
			if ( $s ) { $args['s'] = $s; }
			if ( $cat ) { $args['category'] = array( $cat ); }
			if ( in_array( $stockf, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) { $args['stock_status'] = $stockf; }
			if ( $managed ) { $args['manage_stock'] = true; }
			$res = wc_get_products( $args );
			$products = $res->products; $max = (int) $res->max_num_pages;
		}

		// İstatistik kutuları
		$c_in  = count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish', 'stock_status' => 'instock' ) ) );
		$c_out = count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish', 'stock_status' => 'outofstock' ) ) );
		$c_bo  = count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish', 'stock_status' => 'onbackorder' ) ) );
		$lq = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_manage_stock', 'value' => 'yes' ), array( 'key' => '_stock', 'value' => $gthr, 'compare' => '<=', 'type' => 'NUMERIC' ), array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
		$c_low = (int) $lq->found_posts;

		echo '<div class="row">';
		self::smallbox( $c_in, 'Stokta', 'fa-warehouse', 'dark' );
		self::smallbox( $c_out, 'Tükendi', 'fa-triangle-exclamation', 'light' );
		self::smallbox( $c_bo, 'Ön sipariş', 'fa-clock-rotate-left', 'light' );
		self::smallbox( $c_low, 'Düşük stok (≤' . $gthr . ')', 'fa-arrow-trend-down', 'light' );
		echo '</div>';

		// Filtre çubuğu
		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'stock' ) ) . '">';
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Ürün / SKU ara">';
		echo '<select name="cat" class="form-control"><option value="">Tüm kategoriler</option>';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) ) as $c ) { echo '<option value="' . esc_attr( $c->slug ) . '"' . selected( $cat, $c->slug, false ) . '>' . esc_html( $c->name ) . ' (' . $c->count . ')</option>'; }
		echo '</select>';
		echo '<select name="stock" class="form-control"><option value="">Tüm stok</option><option value="instock"' . selected( $stockf, 'instock', false ) . '>Stokta</option><option value="outofstock"' . selected( $stockf, 'outofstock', false ) . '>Tükendi</option><option value="onbackorder"' . selected( $stockf, 'onbackorder', false ) . '>Ön sipariş</option><option value="lowstock"' . selected( $stockf, 'lowstock', false ) . '>Düşük stok</option></select>';
		echo '<label class="tx-inline-check"><input type="checkbox" name="managed" value="1"' . checked( $managed, true, false ) . '> Sadece stok takipli</label>';
		echo '<button class="btn tx-btn" type="submit">Filtrele</button>';
		echo '<a class="btn tx-btn" href="' . esc_url( self::url( 'stock' ) ) . '">Sıfırla</a></form>';

		// Toplu işlemler
		echo '<div class="tx-bulkbar mb-2"><select id="tx-stk-bulk" class="form-control tx-select"><option value="">Toplu işlem…</option><option value="instock">Stokta olarak işaretle</option><option value="outofstock">Tükendi olarak işaretle</option><option value="manage_on">Stok takibini aç</option><option value="manage_off">Stok takibini kapat</option></select><button class="btn tx-btn" type="button" id="tx-stk-apply">Uygula</button><span class="tx-stk-hint"><i class="fas fa-bolt mr-1"></i>Değişiklikler anında kaydedilir — kaydet butonu yok.</span></div>';

		// Tablo
		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm tx-stocktable mb-0"><thead><tr>';
		echo '<th style="width:30px"><input type="checkbox" class="tx-checkall"></th><th></th><th>Ürün</th><th>SKU</th><th class="text-center">Takip</th><th>Stok adedi</th><th>Düşük eşik</th><th>Bekleyen sipariş</th><th>Stok durumu</th><th>Normal fiyat</th><th>İndirimli fiyat</th></tr></thead><tbody>';
		if ( empty( $products ) ) { echo '<tr><td colspan="11"><div class="tx-empty">Ürün bulunamadı.</div></td></tr>'; }
		foreach ( $products as $p ) {
			$pid    = $p->get_id();
			$img    = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), array( 36, 36 ) ) : wc_placeholder_img_src();
			$manage = $p->get_manage_stock();
			$qty    = $p->get_stock_quantity();
			$low    = get_post_meta( $pid, '_low_stock_amount', true );
			$bo     = $p->get_backorders();
			$ss     = $p->get_stock_status();
			$reg    = get_post_meta( $pid, '_regular_price', true );
			$sal    = get_post_meta( $pid, '_sale_price', true );
			$is_low = ( $manage && $qty !== null && $qty > 0 && $qty <= ( $low !== '' ? (int) $low : $gthr ) );
			echo '<tr' . ( $is_low ? ' class="tx-stk-lowrow"' : '' ) . '>';
			echo '<td><input type="checkbox" class="tx-cb tx-stk-cb" value="' . esc_attr( $pid ) . '"></td>';
			echo '<td><img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt=""></td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'product', $pid ) ) . '"><span class="tx-trunc" style="max-width:220px">' . esc_html( $p->get_name() ) . '</span></a></td>';
			echo '<td><input type="text" class="form-control form-control-sm tx-stk tx-stk-text" data-field="sku" data-pid="' . esc_attr( $pid ) . '" value="' . esc_attr( $p->get_sku() ) . '" style="width:120px"></td>';
			echo '<td class="text-center"><input type="checkbox" class="tx-stk-manage" data-field="manage_stock" data-pid="' . esc_attr( $pid ) . '"' . checked( $manage, true, false ) . '></td>';
			echo '<td><input type="number" step="1" class="form-control form-control-sm tx-stk tx-stk-text tx-stk-qty" data-field="stock_quantity" data-pid="' . esc_attr( $pid ) . '" value="' . esc_attr( $qty !== null ? $qty : '' ) . '" style="width:80px"' . ( $manage ? '' : ' disabled' ) . '></td>';
			echo '<td><input type="number" step="1" class="form-control form-control-sm tx-stk tx-stk-text" data-field="low_stock" data-pid="' . esc_attr( $pid ) . '" value="' . esc_attr( $low ) . '" placeholder="' . esc_attr( $gthr ) . '" style="width:70px"></td>';
			echo '<td><select class="form-control form-control-sm tx-stk-select tx-stk-bo" data-field="backorders" data-pid="' . esc_attr( $pid ) . '"' . ( $manage ? '' : ' disabled' ) . '>';
			foreach ( array( 'no' => 'Hayır', 'notify' => 'Bildir', 'yes' => 'İzin ver' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $bo, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
			echo '</select></td>';
			echo '<td><select class="form-control form-control-sm tx-stk-select tx-stk-status" data-field="stock_status" data-pid="' . esc_attr( $pid ) . '"' . ( $manage ? ' disabled' : '' ) . '>';
			foreach ( array( 'instock' => 'Stokta', 'outofstock' => 'Tükendi', 'onbackorder' => 'Ön sipariş' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $ss, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
			echo '</select></td>';
			echo '<td><input type="number" step="0.01" class="form-control form-control-sm tx-stk tx-stk-text" data-field="regular_price" data-pid="' . esc_attr( $pid ) . '" value="' . esc_attr( $reg ) . '" style="width:90px"></td>';
			echo '<td><input type="number" step="0.01" class="form-control form-control-sm tx-stk tx-stk-text" data-field="sale_price" data-pid="' . esc_attr( $pid ) . '" value="' . esc_attr( $sal ) . '" style="width:90px"></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></div></div>';

		// Sayfalama
		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) {
				$a = array( 'paged' => $i ); if ( $s ) { $a['s'] = $s; } if ( $cat ) { $a['cat'] = $cat; } if ( $stockf ) { $a['stock'] = $stockf; } if ( $managed ) { $a['managed'] = '1'; }
				echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'stock', 0, $a ) ) . '">' . $i . '</a>';
			}
			echo '</div>';
		}

		self::stock_js( admin_url( 'admin-ajax.php' ), wp_create_nonce( 'wcp_prod_img' ) );
	}

	protected static function stock_js( $ajax, $nonce ) {
		?>
<script>
(function(){
	var AJAX=<?php echo wp_json_encode( $ajax ); ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>;
	function post(data){ var b=new URLSearchParams(); for(var k in data){ b.append(k,data[k]); } return fetch(AJAX,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:b.toString()}).then(function(r){return r.json();}); }
	function flash(el,ok){ el.classList.remove('tx-stk-ok','tx-stk-err'); void el.offsetWidth; el.classList.add(ok?'tx-stk-ok':'tx-stk-err'); setTimeout(function(){ el.classList.remove('tx-stk-ok','tx-stk-err'); },1500); }
	function save(el){ var f=el.getAttribute('data-field'), pid=el.getAttribute('data-pid'); var val=(el.type==='checkbox')?(el.checked?'1':'0'):el.value; el.classList.add('tx-stk-saving'); post({action:'wcp_prod_field',_ajax_nonce:NONCE,product_id:pid,field:f,value:val}).then(function(r){ el.classList.remove('tx-stk-saving'); flash(el,!!(r&&r.success)); if(r&&!r.success){ console.warn('wcp stok:',r.data); } }).catch(function(){ el.classList.remove('tx-stk-saving'); flash(el,false); }); }
	var tbl=document.querySelector('.tx-stocktable');
	if(!tbl){ return; }
	// metin/sayı alanları: odak kaybında kaydet
	tbl.addEventListener('blur',function(e){ var t=e.target; if(t.classList&&t.classList.contains('tx-stk-text')){ save(t); if(t.classList.contains('tx-stk-qty')){ var tr=t.closest('tr'); var st=tr.querySelector('.tx-stk-status'); if(st){ var q=parseFloat(t.value); if(!isNaN(q)){ st.value=(q>0)?'instock':'outofstock'; } } } } },true);
	tbl.addEventListener('keydown',function(e){ var t=e.target; if(t.classList&&t.classList.contains('tx-stk-text')&&e.key==='Enter'){ e.preventDefault(); t.blur(); } });
	// select & stok takip toggle: değişince kaydet
	tbl.addEventListener('change',function(e){ var t=e.target; if(!t.classList){ return; }
		if(t.classList.contains('tx-stk-manage')){ var tr=t.closest('tr'); var on=t.checked; tr.querySelectorAll('.tx-stk-qty,.tx-stk-bo').forEach(function(x){ x.disabled=!on; }); var st=tr.querySelector('.tx-stk-status'); if(st){ st.disabled=on; } save(t); return; }
		if(t.classList.contains('tx-stk-select')){ save(t); return; }
	});
	// tümünü seç
	var ca=document.querySelector('.tx-checkall'); if(ca){ ca.addEventListener('change',function(){ tbl.querySelectorAll('.tx-stk-cb').forEach(function(c){ c.checked=ca.checked; }); }); }
	// toplu işlem
	var ap=document.getElementById('tx-stk-apply');
	if(ap){ ap.addEventListener('click',function(){ var act=document.getElementById('tx-stk-bulk').value; if(!act){ return; } var rows=Array.prototype.slice.call(document.querySelectorAll('.tx-stk-cb:checked')); if(!rows.length){ alert('Önce satır seçin.'); return; } if(!confirm(rows.length+' ürün için işlem uygulansın mı?')){ return; } ap.disabled=true; ap.textContent='Uygulanıyor…'; var done=0; rows.forEach(function(cb){ var pid=cb.value; var data={action:'wcp_prod_field',_ajax_nonce:NONCE,product_id:pid}; if(act==='instock'){ data.field='stock_status'; data.value='instock'; } else if(act==='outofstock'){ data.field='stock_status'; data.value='outofstock'; } else if(act==='manage_on'){ data.field='manage_stock'; data.value='1'; } else if(act==='manage_off'){ data.field='manage_stock'; data.value='0'; } post(data).then(function(){ done++; if(done>=rows.length){ location.reload(); } }).catch(function(){ done++; if(done>=rows.length){ location.reload(); } }); }); }); }
})();
</script>
		<?php
	}

	/* ---------------- Raporlar ---------------- */
	/* Tarih aralığını çöz (preset/özel) → from/to/timestamp/etiket */
	protected static function report_resolve_range( $range, $from = '', $to = '' ) {
		$now = current_time( 'timestamp' );
		$today = date( 'Y-m-d', $now );
		if ( $from === '' ) { $from = $today; }
		if ( $to === '' ) { $to = $today; }
		switch ( $range ) {
			case 'today':     $from = $today; $to = $today; break;
			case 'yesterday': $from = $to = date( 'Y-m-d', strtotime( '-1 day', $now ) ); break;
			case 'last30':    $from = date( 'Y-m-d', strtotime( '-29 days', $now ) ); $to = $today; break;
			case 'thismonth': $from = date( 'Y-m-01', $now ); $to = $today; break;
			case 'lastmonth': $from = date( 'Y-m-01', strtotime( 'first day of last month', $now ) ); $to = date( 'Y-m-t', strtotime( 'last day of last month', $now ) ); break;
			case 'thisyear':  $from = date( 'Y-01-01', $now ); $to = $today; break;
			case 'custom':
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) { $from = $today; }
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) { $to = $today; }
				break;
			case 'last7':
			default: $range = 'last7'; $from = date( 'Y-m-d', strtotime( '-6 days', $now ) ); $to = $today; break;
		}
		if ( strtotime( $from ) > strtotime( $to ) ) { $tmp = $from; $from = $to; $to = $tmp; }
		return array(
			'range' => $range, 'from' => $from, 'to' => $to,
			'from_ts' => strtotime( $from . ' 00:00:00' ), 'to_ts' => strtotime( $to . ' 23:59:59' ),
			'label' => date_i18n( 'd F Y', strtotime( $from ) ) . ' — ' . date_i18n( 'd F Y', strtotime( $to ) ),
		);
	}

	/* Bir aralık için tüm satış agregaları (görüntü + dışa aktarım + e-posta paylaşır) */
	protected static function report_data( $from_ts, $to_ts ) {
		$all_st  = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
		$exclude = array( 'cancelled', 'failed', 'refunded', 'pending' );
		$orders  = wc_get_orders( array( 'limit' => -1, 'status' => $all_st, 'date_created' => $from_ts . '...' . $to_ts, 'orderby' => 'date', 'order' => 'ASC' ) );
		$rev = 0.0; $tax = 0.0; $ship = 0.0; $items = 0; $ocount = 0; $refund = 0.0;
		$daily = array(); $daily_o = array(); $prod = array(); $cats = array(); $stcount = array(); $pay = array(); $catcache = array();
		for ( $cur = $from_ts; $cur <= $to_ts; $cur += DAY_IN_SECONDS ) { $d = date( 'Y-m-d', $cur ); $daily[ $d ] = 0.0; $daily_o[ $d ] = 0; }
		foreach ( $orders as $o ) {
			$plain = $o->get_status();
			$stcount[ $plain ] = isset( $stcount[ $plain ] ) ? $stcount[ $plain ] + 1 : 1;
			if ( in_array( $plain, $exclude, true ) ) { continue; }
			$ocount++;
			$ot      = (float) $o->get_total();
			$rev    += $ot;
			$tax    += (float) $o->get_total_tax();
			$ship   += (float) $o->get_shipping_total();
			$refund += (float) $o->get_total_refunded();
			$d = $o->get_date_created() ? $o->get_date_created()->date_i18n( 'Y-m-d' ) : null;
			if ( $d && isset( $daily[ $d ] ) ) { $daily[ $d ] += $ot; $daily_o[ $d ]++; }
			$pm = wp_strip_all_tags( (string) $o->get_payment_method_title() ); if ( $pm === '' ) { $pm = 'Diğer'; }
			if ( ! isset( $pay[ $pm ] ) ) { $pay[ $pm ] = array( 'count' => 0, 'rev' => 0.0 ); }
			$pay[ $pm ]['count']++; $pay[ $pm ]['rev'] += $ot;
			foreach ( $o->get_items() as $it ) {
				$q = (int) $it->get_quantity(); $items += $q;
				$line = (float) $it->get_total();
				$pid = $it->get_product_id();
				if ( ! $pid ) { continue; }
				if ( ! isset( $prod[ $pid ] ) ) { $prod[ $pid ] = array( 'name' => $it->get_name(), 'qty' => 0, 'rev' => 0.0 ); }
				$prod[ $pid ]['qty'] += $q; $prod[ $pid ]['rev'] += $line;
				if ( ! isset( $catcache[ $pid ] ) ) { $tt = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'all' ) ); $catcache[ $pid ] = is_wp_error( $tt ) ? array() : $tt; }
				foreach ( $catcache[ $pid ] as $c ) { if ( ! isset( $cats[ $c->term_id ] ) ) { $cats[ $c->term_id ] = array( 'name' => $c->name, 'rev' => 0.0 ); } $cats[ $c->term_id ]['rev'] += $line; }
			}
		}
		uasort( $prod, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		uasort( $cats, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		arsort( $stcount );
		uasort( $pay, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		return array(
			'rev' => $rev, 'tax' => $tax, 'ship' => $ship, 'items' => $items, 'ocount' => $ocount, 'refund' => $refund,
			'avg' => $ocount ? $rev / $ocount : 0, 'daily' => $daily, 'daily_o' => $daily_o,
			'prod' => $prod, 'cats' => $cats, 'stcount' => $stcount, 'pay' => $pay,
		);
	}

	/* Düz para biçimi (PDF/Excel için ₺ glyph sorununu önler → "TL") */
	protected static function money_plain( $v ) {
		if ( function_exists( 'wc_get_price_decimals' ) ) {
			return number_format( (float) $v, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) . ' TL';
		}
		return number_format( (float) $v, 2, ',', '.' ) . ' TL';
	}

	protected static function report_filename( $rr, $ext ) {
		return 'store-rapor-' . $rr['from'] . '_' . $rr['to'] . '.' . $ext;
	}

	/* Rapor HTML belgesi (hem PDF hem Excel hem e-posta gövdesi) */
	protected static function report_doc( $D, $rr ) {
		$store = get_option( 'blogname' );
		$summary = array(
			'Ciro' => self::money_plain( $D['rev'] ),
			'Sipariş' => (int) $D['ocount'],
			'Satılan ürün' => (int) $D['items'],
			'Ortalama sepet' => self::money_plain( $D['avg'] ),
			'Kargo geliri' => self::money_plain( $D['ship'] ),
			'Vergi' => self::money_plain( $D['tax'] ),
			'İade' => self::money_plain( $D['refund'] ),
			'Net gelir' => self::money_plain( $D['rev'] - $D['refund'] ),
		);
		ob_start();
		?>
<!doctype html><html lang="tr"><head><meta charset="UTF-8"><style>
body{font-family:'DejaVu Sans',Arial,sans-serif;color:#1b1f27;font-size:12px;margin:0;}
h1{font-size:19px;margin:0 0 2px;} h2{font-size:13px;margin:16px 0 6px;border-bottom:2px solid #15171c;padding-bottom:3px;}
.muted{color:#777;font-size:11px;}
table{width:100%;border-collapse:collapse;margin-bottom:4px;}
th,td{border:1px solid #ccc;padding:5px 7px;font-size:11px;text-align:left;}
th{background:#15171c;color:#fff;} .r{text-align:right;} .c{text-align:center;}
</style></head><body>
<h1><?php echo esc_html( $store ); ?> — Satış Raporu</h1>
<div class="muted"><?php echo esc_html( $rr['label'] ); ?> &nbsp;·&nbsp; Oluşturma: <?php echo esc_html( date_i18n( 'd.m.Y H:i' ) ); ?> &nbsp;·&nbsp; <?php echo (int) $D['ocount']; ?> ödenmiş sipariş</div>
<h2>Özet</h2>
<table><tr><?php foreach ( $summary as $l => $v ) { echo '<th>' . esc_html( $l ) . '</th>'; } ?></tr>
<tr><?php foreach ( $summary as $l => $v ) { echo '<td>' . esc_html( $v ) . '</td>'; } ?></tr></table>
<h2>En çok satan ürünler</h2>
<table><tr><th>#</th><th>Ürün</th><th class="c">Adet</th><th class="r">Gelir</th></tr>
<?php if ( empty( $D['prod'] ) ) { echo '<tr><td colspan="4">Bu aralıkta satış yok.</td></tr>'; } $i = 0; foreach ( $D['prod'] as $pid => $pd ) { $i++; if ( $i > 20 ) { break; } echo '<tr><td>' . $i . '</td><td>' . esc_html( $pd['name'] ) . '</td><td class="c">' . (int) $pd['qty'] . '</td><td class="r">' . esc_html( self::money_plain( $pd['rev'] ) ) . '</td></tr>'; } ?>
</table>
<h2>Kategori dağılımı</h2>
<table><tr><th>Kategori</th><th class="r">Gelir</th></tr>
<?php if ( empty( $D['cats'] ) ) { echo '<tr><td colspan="2">Veri yok.</td></tr>'; } foreach ( $D['cats'] as $c ) { echo '<tr><td>' . esc_html( $c['name'] ) . '</td><td class="r">' . esc_html( self::money_plain( $c['rev'] ) ) . '</td></tr>'; } ?>
</table>
<h2>Sipariş durumları</h2>
<table><tr><th>Durum</th><th class="r">Adet</th></tr>
<?php foreach ( $D['stcount'] as $st => $cnt ) { echo '<tr><td>' . esc_html( self::status_label( $st ) ) . '</td><td class="r">' . (int) $cnt . '</td></tr>'; } ?>
</table>
<h2>Ödeme yöntemleri</h2>
<table><tr><th>Yöntem</th><th class="c">Sipariş</th><th class="r">Gelir</th></tr>
<?php foreach ( $D['pay'] as $name => $pd ) { echo '<tr><td>' . esc_html( $name ) . '</td><td class="c">' . (int) $pd['count'] . '</td><td class="r">' . esc_html( self::money_plain( $pd['rev'] ) ) . '</td></tr>'; } ?>
</table>
<h2>Günlük döküm</h2>
<table><tr><th>Tarih</th><th class="c">Sipariş</th><th class="r">Ciro</th></tr>
<?php foreach ( $D['daily'] as $d => $v ) { echo '<tr><td>' . esc_html( date_i18n( 'd.m.Y', strtotime( $d ) ) ) . '</td><td class="c">' . (int) $D['daily_o'][ $d ] . '</td><td class="r">' . esc_html( self::money_plain( $v ) ) . '</td></tr>'; } ?>
</table>
<div class="muted" style="margin-top:14px;">STORE Mağaza Paneli · example.com</div>
</body></html>
		<?php
		return ob_get_clean();
	}

	/* PDF çıktısı (Dompdf — wcpdf eklentisinden) */
	protected static function output_report_pdf( $D, $rr, $return = false ) {
		$html = self::report_doc( $D, $rr );
		$cls  = '\\WPO\\IPS\\Vendor\\Dompdf\\Dompdf';
		$optc = '\\WPO\\IPS\\Vendor\\Dompdf\\Options';
		if ( class_exists( $cls ) ) {
			$opt = new $optc();
			$opt->set( 'isRemoteEnabled', false );
			$opt->set( 'defaultFont', 'DejaVu Sans' );
			$dompdf = new $cls( $opt );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$pdf = $dompdf->output();
			if ( $return ) { return $pdf; }
			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . self::report_filename( $rr, 'pdf' ) . '"' );
			echo $pdf; exit;
		}
		// Dompdf yoksa yazdırılabilir HTML'e düş
		if ( $return ) { return false; }
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $html . '<script>window.onload=function(){window.print();};</script>'; exit;
	}

	/* Excel çıktısı (HTML tablo → .xls) */
	protected static function output_report_xls( $D, $rr, $return = false ) {
		$xls = self::report_doc( $D, $rr );
		if ( $return ) { return $xls; }
		nocache_headers();
		header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . self::report_filename( $rr, 'xls' ) . '"' );
		echo "\xEF\xBB\xBF" . $xls; exit;
	}

	/* Ham kaynak adını okunaklı platform adına çevir */
	protected static function norm_platform( $src, $type = '' ) {
		$s = strtolower( trim( (string) $src, " ()" ) );
		if ( $s === '' || $s === 'direct' ) { return $type === 'typein' ? 'Doğrudan' : 'Bilinmiyor'; }
		// Kısaltma / tam eşleşmeler (substring araması yanlış eşleşmesin diye önce)
		$exact = array( 'ig' => 'Instagram', 'insta' => 'Instagram', 'fb' => 'Facebook', 'wa' => 'WhatsApp', 'tt' => 'TikTok', 'yt' => 'YouTube' );
		if ( isset( $exact[ $s ] ) ) { return $exact[ $s ]; }
		$map = array(
			'google'    => 'Google', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb' => 'Facebook',
			'meta'      => 'Meta', 'bing' => 'Bing', 'yandex' => 'Yandex', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
			'twitter'   => 'X (Twitter)', 'x.com' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'pinterest' => 'Pinterest',
			'whatsapp'  => 'WhatsApp', 'telegram' => 'Telegram', 'duckduckgo' => 'DuckDuckGo', 'email' => 'E-posta',
			'newsletter'=> 'E-posta', 'mail' => 'E-posta',
		);
		foreach ( $map as $k => $v ) { if ( strpos( $s, $k ) !== false ) { return $v; } }
		return ucfirst( $s );
	}

	/* Bir aralık için sipariş kaynağı / atıf agregaları */
	protected static function attribution_data( $from_ts, $to_ts ) {
		$all_st  = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
		$exclude = array( 'cancelled', 'failed', 'refunded', 'pending' );
		$orders  = wc_get_orders( array( 'limit' => -1, 'status' => $all_st, 'date_created' => $from_ts . '...' . $to_ts, 'orderby' => 'date', 'order' => 'ASC' ) );
		$tot = array( 'c' => 0, 'rev' => 0.0 );
		$sum_paid = array( 'c' => 0, 'rev' => 0.0 ); $sum_org = array( 'c' => 0, 'rev' => 0.0 );
		$sum_dir  = array( 'c' => 0, 'rev' => 0.0 ); $sum_ref = array( 'c' => 0, 'rev' => 0.0 );
		$byChan = array(); $byPlat = array(); $byPaid = array(); $byCamp = array(); $byMed = array(); $byDev = array(); $byLand = array();
		$paid_med = array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'display', 'cpm', 'ads', 'banner', 'retargeting' );
		$acc = function ( &$arr, $key, $amt ) {
			if ( $key === '' ) { $key = '(belirtilmemiş)'; }
			if ( ! isset( $arr[ $key ] ) ) { $arr[ $key ] = array( 'c' => 0, 'rev' => 0.0 ); }
			$arr[ $key ]['c']++; $arr[ $key ]['rev'] += (float) $amt;
		};
		foreach ( $orders as $o ) {
			if ( in_array( $o->get_status(), $exclude, true ) ) { continue; }
			$ot   = (float) $o->get_total();
			$type = (string) $o->get_meta( '_wc_order_attribution_source_type' );
			$src  = (string) $o->get_meta( '_wc_order_attribution_utm_source' );
			$med  = (string) $o->get_meta( '_wc_order_attribution_utm_medium' );
			$camp = (string) $o->get_meta( '_wc_order_attribution_utm_campaign' );
			$dev  = (string) $o->get_meta( '_wc_order_attribution_device_type' );
			$land = (string) $o->get_meta( '_wc_order_attribution_session_entry' );
			if ( $src === '' || $land === '' || $camp === '' || $med === '' ) {
				$pe = $o->get_meta( 'pys_enrich_data' ); $pys = is_array( $pe ) ? $pe : ( $pe ? json_decode( $pe, true ) : array() );
				if ( is_array( $pys ) ) {
					if ( $src === ''  && ! empty( $pys['pys_source'] ) )  { $src  = $pys['pys_source']; }
					if ( $land === '' && ! empty( $pys['pys_landing'] ) ) { $land = $pys['pys_landing']; }
					if ( ( $camp === '' || $med === '' ) && ! empty( $pys['pys_utm'] ) ) {
						$pp = self::pys_pairs( $pys['pys_utm'] );
						if ( $camp === '' && isset( $pp['utm_campaign'] ) ) { $camp = $pp['utm_campaign']; }
						if ( $med === ''  && isset( $pp['utm_medium'] ) )   { $med  = $pp['utm_medium']; }
					}
				}
			}
			$tot['c']++; $tot['rev'] += $ot;

			$plat    = self::norm_platform( $src, $type );
			$mlow    = strtolower( $med );
			$is_paid = ( $type === 'utm' ) || in_array( $mlow, $paid_med, true );

			if ( $type === 'typein' )       { $chan = 'Doğrudan';       $sum_dir['c']++;  $sum_dir['rev']  += $ot; }
			elseif ( $type === 'organic' )  { $chan = 'Organik arama';  $sum_org['c']++;  $sum_org['rev']  += $ot; }
			elseif ( $type === 'referral' ) { $chan = 'Yönlendirme';    $sum_ref['c']++;  $sum_ref['rev']  += $ot; }
			elseif ( $type === 'admin' )    { $chan = 'Yönetici (elle)'; }
			elseif ( $is_paid )             { $chan = 'Ücretli reklam'; $sum_paid['c']++; $sum_paid['rev'] += $ot; }
			elseif ( $type === '' )         { $chan = 'Bilinmiyor'; }
			else                            { $chan = self::source_type_label( $type ); }

			$acc( $byChan, $chan, $ot );
			$acc( $byPlat, $plat, $ot );
			$acc( $byMed, $med, $ot );
			$acc( $byDev, ( $dev === '' ? 'Bilinmiyor' : $dev ), $ot );
			if ( $is_paid ) { $acc( $byPaid, $plat, $ot ); }
			if ( $land !== '' ) { $u = wp_parse_url( $land ); $lp = isset( $u['path'] ) ? $u['path'] : ''; if ( $lp === '' ) { $lp = '/'; } $acc( $byLand, $lp, $ot ); }
			if ( $camp !== '' ) {
				if ( ! isset( $byCamp[ $camp ] ) ) { $byCamp[ $camp ] = array( 'c' => 0, 'rev' => 0.0, 'plat' => $plat ); }
				$byCamp[ $camp ]['c']++; $byCamp[ $camp ]['rev'] += $ot;
			}
		}
		return array(
			'tot' => $tot, 'sum_paid' => $sum_paid, 'sum_org' => $sum_org, 'sum_dir' => $sum_dir, 'sum_ref' => $sum_ref,
			'byChan' => $byChan, 'byPlat' => $byPlat, 'byPaid' => $byPaid, 'byCamp' => $byCamp, 'byMed' => $byMed, 'byDev' => $byDev, 'byLand' => $byLand,
		);
	}

	/* Genel kırılım tablosu — kanal/platform/ürün/kategori/coğrafya/ödeme vb. ($sortby ile sırala, $countlabel ile sayım başlığı, $limit ile kısıtla) */
	protected static function attr_break_table( $title, $icon, $rows, $total, $tot_c, $firstcol = 'Kaynak', $countlabel = 'Sipariş', $sortby = 'rev', $limit = 0 ) {
		if ( empty( $rows ) ) { return; }
		uasort( $rows, function ( $a, $b ) use ( $sortby ) { return ( isset( $b[ $sortby ] ) ? $b[ $sortby ] : 0 ) <=> ( isset( $a[ $sortby ] ) ? $a[ $sortby ] : 0 ); } );
		$total_n = count( $rows );
		if ( $limit > 0 && $total_n > $limit ) { $rows = array_slice( $rows, 0, $limit, true ); }
		$note = ( $limit > 0 && $total_n > $limit ) ? ( 'ilk ' . $limit . ' / ' . $total_n ) : ( $total_n . ' kayıt' );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas ' . esc_attr( $icon ) . ' mr-2"></i>' . esc_html( $title ) . '</h3><div class="card-tools text-muted">' . esc_html( $note ) . '</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>' . esc_html( $firstcol ) . '</th><th class="text-center" style="width:90px">' . esc_html( $countlabel ) . '</th><th style="width:200px">Pay</th><th class="text-right" style="width:150px">Ciro</th><th class="text-right" style="width:130px">Ort.</th></tr></thead><tbody>';
		foreach ( $rows as $name => $r ) {
			$base = isset( $r[ $sortby ] ) ? (float) $r[ $sortby ] : 0;
			$pct  = $total > 0 ? ( $base / $total * 100 ) : 0;
			$avg  = ! empty( $r['c'] ) ? $r['rev'] / $r['c'] : 0;
			echo '<tr><td class="tx-strong">' . esc_html( $name ) . '</td><td class="text-center">' . (int) ( isset( $r['c'] ) ? $r['c'] : 0 ) . '</td>';
			echo '<td><div class="tx-bar"><span style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></span></div> <small class="text-muted">%' . esc_html( number_format( $pct, 1 ) ) . '</small></td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $r['rev'] ) ) . '</td><td class="text-right text-muted">' . wp_kses_post( self::money( $avg ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div></div>';
	}

	/* Chart.js kütüphanesini bir kez yükle */
	protected static function chart_lib() { static $d = false; if ( $d ) { return; } $d = true; echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>'; }

	/* Grafik kartı (line/bar/doughnut) */
	protected static function chart_card( $title, $icon, $id, $type, $labels, $datasets, $height = 300 ) {
		self::chart_lib();
		$multi = count( $datasets ) > 1;
		$pie   = in_array( $type, array( 'doughnut', 'pie' ), true );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas ' . esc_attr( $icon ) . ' mr-2"></i>' . esc_html( $title ) . '</h3></div><div class="card-body"><div style="position:relative;height:' . (int) $height . 'px"><canvas id="' . esc_attr( $id ) . '"></canvas></div></div></div>';
		$scales = $pie ? '{}' : '{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:"#f0f2f5"},ticks:{callback:function(v){return Number(v).toLocaleString("tr-TR");}}}}';
		echo '<script>(function(){var el=document.getElementById(' . wp_json_encode( $id ) . ');if(!el||typeof Chart==="undefined")return;new Chart(el,{type:' . wp_json_encode( $type ) . ',data:{labels:' . wp_json_encode( $labels ) . ',datasets:' . wp_json_encode( $datasets ) . '},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:' . ( ( $multi || $pie ) ? 'true' : 'false' ) . ',position:' . ( $pie ? '"right"' : '"top"' ) . ',labels:{usePointStyle:true,font:{family:"Inter,sans-serif"}}}},scales:' . $scales . '}});})();</script>';
	}

	/* Analiz sayfaları üst sekme navigasyonu */
	protected static function analytics_tabs( $current ) {
		$tabs = array(
			'reports'       => array( 'Satış Özeti', 'fa-chart-pie' ),
			'satis-analiz'  => array( 'Satış Analizi', 'fa-chart-line' ),
			'urun-analiz'   => array( 'Ürün Analizi', 'fa-box' ),
			'musteri-analiz'=> array( 'Müşteri Analizi', 'fa-users' ),
			'cografya-analiz'=> array( 'Coğrafya', 'fa-map-location-dot' ),
			'kupon-analiz'  => array( 'Kupon & İndirim', 'fa-ticket' ),
			'iade-analiz'   => array( 'İadeler', 'fa-rotate-left' ),
			'vergi-analiz'  => array( 'Vergi', 'fa-percent' ),
			'kaynak-analiz' => array( 'Reklam & Kaynak', 'fa-bullhorn' ),
		);
		echo '<style>.tx-anav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}.tx-anav-i{display:inline-flex;align-items:center;padding:8px 13px;border:1px solid #e6e9ef;border-radius:9px;background:#fff;color:#445566;font-weight:600;font-size:13px;text-decoration:none}.tx-anav-i:hover{border-color:#b9c0cf;color:#222}.tx-anav-i.is-on{background:#15171c;color:#fff;border-color:#15171c}.tx-bar{position:relative;height:7px;border-radius:4px;background:#eef0f4;width:130px;display:inline-block;vertical-align:middle;overflow:hidden}.tx-bar>span{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#3b5bdb,#5c7cfa);border-radius:4px}</style>';
		echo '<div class="tx-anav">';
		foreach ( $tabs as $v => $t ) {
			if ( ! self::section_allowed( self::view_section( $v ) ) ) { continue; }
			echo '<a class="tx-anav-i' . ( $current === $v ? ' is-on' : '' ) . '" href="' . esc_url( self::url( $v ) ) . '"><i class="fas ' . esc_attr( $t[1] ) . ' mr-1"></i>' . esc_html( $t[0] ) . '</a>';
		}
		echo '</div>';
	}

	/* Tarih aralığı çubuğu (analiz sayfaları paylaşır) */
	protected static function analytics_datebar( $view, $rr ) {
		$range = $rr['range']; $from = $rr['from']; $to = $rr['to'];
		$presets = array( 'today' => 'Bugün', 'yesterday' => 'Dün', 'last7' => 'Son 7 gün', 'last30' => 'Son 30 gün', 'thismonth' => 'Bu ay', 'lastmonth' => 'Geçen ay', 'thisyear' => 'Bu yıl' );
		echo '<div class="tx-rep-head mb-2"><div class="tx-chips">';
		foreach ( $presets as $k => $lbl ) { echo '<a class="tx-chip' . ( $range === $k ? ' is-on' : '' ) . '" href="' . esc_url( self::url( $view, 0, array( 'range' => $k ) ) ) . '">' . esc_html( $lbl ) . '</a>'; }
		echo '</div>';
		echo '<form class="tx-rep-custom" method="get" action="' . esc_url( self::url( $view ) ) . '"><input type="hidden" name="range" value="custom"><input type="date" class="form-control" name="from" value="' . esc_attr( $from ) . '"><span class="tx-rep-dash">–</span><input type="date" class="form-control" name="to" value="' . esc_attr( $to ) . '"><button class="btn tx-btn" type="submit">Uygula</button></form>';
		echo '</div>';
		echo '<div class="tx-rep-period mb-3"><i class="far fa-calendar mr-1"></i>' . esc_html( $rr['label'] ) . '</div>';
	}

	/* Tüm analiz sayfaları için tek geçişte kapsamlı agrega */
	protected static function analytics_core( $from_ts, $to_ts ) {
		$all_st  = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
		$exclude = array( 'cancelled', 'failed', 'refunded', 'pending' );
		$orders  = wc_get_orders( array( 'limit' => -1, 'status' => $all_st, 'date_created' => $from_ts . '...' . $to_ts, 'orderby' => 'date', 'order' => 'ASC' ) );
		$T = array( 'oc' => 0, 'rev' => 0.0, 'tax' => 0.0, 'ship' => 0.0, 'disc' => 0.0, 'items' => 0, 'refund' => 0.0 );
		$daily = array(); $dow = array(); $hour = array();
		for ( $i = 0; $i < 7; $i++ ) { $dow[ $i ] = array( 'c' => 0, 'rev' => 0.0 ); }
		for ( $i = 0; $i < 24; $i++ ) { $hour[ $i ] = array( 'c' => 0, 'rev' => 0.0 ); }
		for ( $cur = $from_ts; $cur <= $to_ts; $cur += DAY_IN_SECONDS ) { $daily[ date( 'Y-m-d', $cur ) ] = array( 'rev' => 0.0, 'c' => 0 ); }
		$status = array(); $pay = array(); $aovb = array_fill( 0, 7, 0 ); $city = array(); $state = array(); $country = array(); $coupon = array();
		$cust = array(); $prod = array(); $cats = array(); $catcache = array();
		$guest = array( 'c' => 0, 'rev' => 0.0 ); $reg = array( 'c' => 0, 'rev' => 0.0 );
		$newc = array( 'c' => 0, 'rev' => 0.0 ); $retc = array( 'c' => 0, 'rev' => 0.0 ); $seen = array();
		$bk = array( array( 0, 500 ), array( 500, 1000 ), array( 1000, 2500 ), array( 2500, 5000 ), array( 5000, 10000 ), array( 10000, 25000 ), array( 25000, PHP_INT_MAX ) );
		$acc = function ( &$arr, $key, $amt ) { if ( $key === '' ) { $key = '(belirtilmemiş)'; } if ( ! isset( $arr[ $key ] ) ) { $arr[ $key ] = array( 'c' => 0, 'rev' => 0.0 ); } $arr[ $key ]['c']++; $arr[ $key ]['rev'] += (float) $amt; };
		foreach ( $orders as $o ) {
			$plain = $o->get_status();
			$status[ $plain ] = isset( $status[ $plain ] ) ? $status[ $plain ] + 1 : 1;
			if ( in_array( $plain, $exclude, true ) ) { continue; }
			$ot = (float) $o->get_total(); $rf = (float) $o->get_total_refunded();
			$T['oc']++; $T['rev'] += $ot; $T['tax'] += (float) $o->get_total_tax(); $T['ship'] += (float) $o->get_shipping_total(); $T['disc'] += (float) $o->get_total_discount(); $T['refund'] += $rf;
			$dt = $o->get_date_created();
			if ( $dt ) {
				$d = $dt->date_i18n( 'Y-m-d' ); if ( isset( $daily[ $d ] ) ) { $daily[ $d ]['rev'] += $ot; $daily[ $d ]['c']++; }
				$w = (int) $dt->date_i18n( 'w' ); $dow[ $w ]['c']++; $dow[ $w ]['rev'] += $ot;
				$h = (int) $dt->date_i18n( 'G' ); $hour[ $h ]['c']++; $hour[ $h ]['rev'] += $ot;
			}
			$pm = wp_strip_all_tags( (string) $o->get_payment_method_title() ); if ( $pm === '' ) { $pm = 'Diğer'; } $acc( $pay, $pm, $ot );
			foreach ( $bk as $bi => $b ) { if ( $ot >= $b[0] && $ot < $b[1] ) { $aovb[ $bi ]++; break; } }
			$acc( $city, ucwords( mb_strtolower( trim( (string) $o->get_billing_city() ), 'UTF-8' ) ), $ot );
			$st_code = trim( (string) $o->get_billing_state() ); $acc( $state, $st_code, $ot );
			$acc( $country, trim( (string) $o->get_billing_country() ), $ot );
			foreach ( $o->get_items( 'coupon' ) as $ci ) { $code = strtoupper( $ci->get_code() ); if ( ! isset( $coupon[ $code ] ) ) { $coupon[ $code ] = array( 'c' => 0, 'rev' => 0.0, 'disc' => 0.0 ); } $coupon[ $code ]['c']++; $coupon[ $code ]['rev'] += $ot; $coupon[ $code ]['disc'] += (float) $ci->get_discount(); }
			$email = strtolower( trim( (string) $o->get_billing_email() ) ); $cid = (int) $o->get_customer_id();
			if ( $cid > 0 ) { $reg['c']++; $reg['rev'] += $ot; } else { $guest['c']++; $guest['rev'] += $ot; }
			$ckey = $email !== '' ? $email : ( 'order#' . $o->get_id() );
			if ( ! isset( $cust[ $ckey ] ) ) { $cust[ $ckey ] = array( 'c' => 0, 'rev' => 0.0, 'name' => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ), 'email' => $email, 'city' => $o->get_billing_city() ); }
			$cust[ $ckey ]['c']++; $cust[ $ckey ]['rev'] += $ot;
			if ( isset( $seen[ $ckey ] ) ) { $retc['c']++; $retc['rev'] += $ot; } else { $newc['c']++; $newc['rev'] += $ot; $seen[ $ckey ] = 1; }
			foreach ( $o->get_items() as $it ) {
				$q = (int) $it->get_quantity(); $T['items'] += $q; $line = (float) $it->get_total(); $pid = $it->get_product_id(); if ( ! $pid ) { continue; }
				if ( ! isset( $prod[ $pid ] ) ) { $prod[ $pid ] = array( 'name' => $it->get_name(), 'c' => 0, 'rev' => 0.0 ); }
				$prod[ $pid ]['c'] += $q; $prod[ $pid ]['rev'] += $line;
				if ( ! isset( $catcache[ $pid ] ) ) { $tt = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'all' ) ); $catcache[ $pid ] = is_wp_error( $tt ) ? array() : $tt; }
				foreach ( $catcache[ $pid ] as $c ) { if ( ! isset( $cats[ $c->name ] ) ) { $cats[ $c->name ] = array( 'c' => 0, 'rev' => 0.0 ); } $cats[ $c->name ]['rev'] += $line; $cats[ $c->name ]['c'] += $q; }
			}
		}
		return compact( 'T', 'daily', 'dow', 'hour', 'status', 'pay', 'aovb', 'city', 'state', 'country', 'coupon', 'cust', 'prod', 'cats', 'guest', 'reg', 'newc', 'retc' );
	}

	/* ---------------- Satış Analizi ---------------- */
	protected static function view_sales_analytics() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'satis-analiz' );
		self::analytics_datebar( 'satis-analiz', $rr );
		$D = self::analytics_core( $rr['from_ts'], $rr['to_ts'] ); $T = $D['T'];
		if ( (int) $T['oc'] === 0 ) { echo '<div class="tx-empty">Bu aralıkta ödenmiş sipariş yok.</div>'; return; }
		$net = $T['rev'] - $T['refund'];
		echo '<div class="row">';
		self::smallbox( self::money( $T['rev'] ), 'Ciro', 'fa-lira-sign', 'dark', true );
		self::smallbox( self::money( $net ), 'Net gelir', 'fa-sack-dollar', 'dark', true );
		self::smallbox( (int) $T['oc'], 'Ödenmiş sipariş', 'fa-cart-shopping', 'light' );
		self::smallbox( self::money( $T['oc'] ? $T['rev'] / $T['oc'] : 0 ), 'Ortalama sepet', 'fa-receipt', 'light', true );
		echo '</div><div class="row">';
		self::smallbox( (int) $T['items'], 'Satılan ürün', 'fa-box', 'light' );
		self::smallbox( number_format( $T['oc'] ? $T['items'] / $T['oc'] : 0, 1, ',', '.' ), 'Sipariş başına ürün', 'fa-layer-group', 'light' );
		self::smallbox( self::money( $T['ship'] ), 'Kargo geliri', 'fa-truck', 'light', true );
		self::smallbox( self::money( $T['disc'] ), 'Toplam indirim', 'fa-tags', 'light', true );
		echo '</div>';

		// Günlük trend
		$labels = array(); $rd = array(); $od = array();
		foreach ( $D['daily'] as $d => $v ) { $labels[] = date_i18n( 'd M', strtotime( $d ) ); $rd[] = round( $v['rev'], 2 ); $od[] = $v['c']; }
		self::chart_card( 'Günlük ciro & sipariş trendi', 'fa-chart-line', 'tx-c-trend', 'line', $labels, array(
			array( 'label' => 'Ciro (₺)', 'data' => $rd, 'borderColor' => '#15171c', 'backgroundColor' => 'rgba(21,23,28,.07)', 'borderWidth' => 2, 'fill' => true, 'tension' => .32, 'pointRadius' => 2 ),
			array( 'label' => 'Sipariş', 'data' => $od, 'borderColor' => '#5c7cfa', 'borderWidth' => 2, 'borderDash' => array( 5, 4 ), 'fill' => false, 'tension' => .32, 'pointRadius' => 0 ),
		), 320 );

		// Haftanın günü (Pzt başlangıç)
		$order = array( 1, 2, 3, 4, 5, 6, 0 ); $dn = array( 0 => 'Pazar', 1 => 'Pzt', 2 => 'Salı', 3 => 'Çar', 4 => 'Per', 5 => 'Cuma', 6 => 'Cmt' );
		$dl = array(); $dv = array();
		foreach ( $order as $w ) { $dl[] = $dn[ $w ]; $dv[] = round( $D['dow'][ $w ]['rev'], 2 ); }
		self::chart_card( 'Haftanın gününe göre ciro', 'fa-calendar-week', 'tx-c-dow', 'bar', $dl, array( array( 'label' => 'Ciro (₺)', 'data' => $dv, 'backgroundColor' => '#5c7cfa', 'borderRadius' => 6 ) ), 260 );

		// Saate göre
		$hl = array(); $hv = array();
		for ( $i = 0; $i < 24; $i++ ) { $hl[] = sprintf( '%02d', $i ); $hv[] = round( $D['hour'][ $i ]['rev'], 2 ); }
		self::chart_card( 'Saate göre ciro (gün içi dağılım)', 'fa-clock', 'tx-c-hour', 'bar', $hl, array( array( 'label' => 'Ciro (₺)', 'data' => $hv, 'backgroundColor' => '#3b5bdb', 'borderRadius' => 5 ) ), 260 );

		// Sepet tutarı dağılımı
		$bn = array( '0 – 500', '500 – 1.000', '1.000 – 2.500', '2.500 – 5.000', '5.000 – 10.000', '10.000 – 25.000', '25.000 +' );
		self::chart_card( 'Sepet tutarı dağılımı (sipariş adedi)', 'fa-coins', 'tx-c-aov', 'bar', $bn, array( array( 'label' => 'Sipariş', 'data' => array_values( $D['aovb'] ), 'backgroundColor' => '#15171c', 'borderRadius' => 5 ) ), 260 );

		// Sipariş durumu tablosu
		$total_all = array_sum( $D['status'] );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-flag mr-2"></i>Sipariş durumuna göre dağılım</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Durum</th><th class="text-center" style="width:120px">Sipariş</th><th style="width:260px">Pay</th></tr></thead><tbody>';
		arsort( $D['status'] );
		foreach ( $D['status'] as $stt => $cnt ) { $p = $total_all > 0 ? $cnt / $total_all * 100 : 0; echo '<tr><td><span class="tx-badge ' . esc_attr( self::badge_class( $stt ) ) . '">' . esc_html( self::status_label( $stt ) ) . '</span></td><td class="text-center tx-strong">' . (int) $cnt . '</td><td><div class="tx-bar"><span style="width:' . esc_attr( round( $p, 1 ) ) . '%"></span></div> <small class="text-muted">%' . esc_html( number_format( $p, 1 ) ) . '</small></td></tr>'; }
		echo '</tbody></table></div></div></div>';

		// Ödeme yöntemi
		self::attr_break_table( 'Ödeme yöntemine göre', 'fa-credit-card', $D['pay'], $T['rev'], $T['oc'], 'Ödeme yöntemi' );
	}

	/* ---------------- Ürün Analizi ---------------- */
	protected static function view_product_analytics() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'urun-analiz' );
		self::analytics_datebar( 'urun-analiz', $rr );
		$D = self::analytics_core( $rr['from_ts'], $rr['to_ts'] ); $T = $D['T'];

		// Tüm zamanlar stok özeti (dönemden bağımsız)
		$stock_val = 0.0; $oos = 0; $low = 0; $managed = 0;
		$pids = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'ids' ) );
		$pcount = count( $pids );
		foreach ( $pids as $pid ) { $p = wc_get_product( $pid ); if ( ! $p ) { continue; } $ss = $p->get_stock_status(); if ( $ss === 'outofstock' ) { $oos++; } if ( $p->managing_stock() ) { $managed++; $sq = (float) $p->get_stock_quantity(); $stock_val += $sq * (float) $p->get_price(); $lo = $p->get_low_stock_amount(); $thr = $lo !== '' ? (float) $lo : (float) get_option( 'woocommerce_notify_low_stock_amount', 2 ); if ( $sq > 0 && $sq <= $thr ) { $low++; } } }

		echo '<div class="row">';
		self::smallbox( (int) count( $D['prod'] ), 'Satış yapan ürün', 'fa-box-open', 'dark' );
		self::smallbox( (int) $T['items'], 'Satılan adet', 'fa-cubes', 'light' );
		self::smallbox( self::money( $stock_val ), 'Stok değeri (maliyet/fiyat)', 'fa-warehouse', 'dark', true );
		self::smallbox( (int) $pcount, 'Yayında ürün', 'fa-list', 'light' );
		echo '</div><div class="row">';
		self::smallbox( (int) $oos, 'Tükenen ürün', 'fa-circle-xmark', 'light' );
		self::smallbox( (int) $low, 'Düşük stok', 'fa-triangle-exclamation', 'light' );
		self::smallbox( (int) $managed, 'Stok takipli ürün', 'fa-clipboard-check', 'light' );
		self::smallbox( self::money( $T['items'] ? $T['rev'] / $T['items'] : 0 ), 'Ort. birim fiyat', 'fa-tag', 'light', true );
		echo '</div>';

		self::attr_break_table( 'En çok satan ürünler (ciroya göre)', 'fa-trophy', $D['prod'], $T['rev'], $T['oc'], 'Ürün', 'Adet', 'rev', 25 );
		self::attr_break_table( 'En çok satan ürünler (adede göre)', 'fa-fire', $D['prod'], $T['items'], $T['oc'], 'Ürün', 'Adet', 'c', 25 );
		self::attr_break_table( 'Kategoriye göre satış', 'fa-tags', $D['cats'], $T['rev'], $T['oc'], 'Kategori', 'Adet', 'rev' );

		// En az satan / hiç satılmayan (tüm zamanlar)
		$low_sellers = wc_get_products( array( 'limit' => 25, 'status' => 'publish', 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'ASC' ) );
		if ( $low_sellers ) {
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-snowflake mr-2"></i>En az satan / hiç satılmayan ürünler <small class="text-muted">(tüm zamanlar)</small></h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Ürün</th><th>SKU</th><th class="text-center" style="width:110px">Toplam satış</th><th class="text-right" style="width:120px">Fiyat</th><th class="text-center" style="width:90px">Stok</th></tr></thead><tbody>';
			foreach ( $low_sellers as $p ) { echo '<tr><td class="tx-strong"><a href="' . esc_url( self::url( 'product', $p->get_id() ) ) . '">' . esc_html( $p->get_name() ) . '</a></td><td class="text-muted">' . esc_html( $p->get_sku() ? $p->get_sku() : '—' ) . '</td><td class="text-center">' . (int) $p->get_total_sales() . '</td><td class="text-right">' . wp_kses_post( self::money( $p->get_price() ) ) . '</td><td class="text-center text-muted">' . ( $p->managing_stock() ? (int) $p->get_stock_quantity() : '—' ) . '</td></tr>'; }
			echo '</tbody></table></div></div></div>';
		}
	}

	/* ---------------- Müşteri Analizi ---------------- */
	protected static function view_customer_analytics() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'musteri-analiz' );
		self::analytics_datebar( 'musteri-analiz', $rr );
		$D = self::analytics_core( $rr['from_ts'], $rr['to_ts'] ); $T = $D['T'];
		if ( (int) $T['oc'] === 0 ) { echo '<div class="tx-empty">Bu aralıkta ödenmiş sipariş yok.</div>'; return; }
		$ccount = count( $D['cust'] );
		$rep_rate = $T['oc'] > 0 ? $D['retc']['c'] / $T['oc'] * 100 : 0;
		echo '<div class="row">';
		self::smallbox( (int) $ccount, 'Tekil müşteri', 'fa-users', 'dark' );
		self::smallbox( (int) $D['newc']['c'] . ' <small>sip</small>', 'Yeni müşteri (dönem içi)', 'fa-user-plus', 'light', true );
		self::smallbox( (int) $D['retc']['c'] . ' <small>sip</small>', 'Tekrar eden sipariş', 'fa-repeat', 'dark', true );
		self::smallbox( '%' . number_format( $rep_rate, 1 ), 'Tekrar sipariş oranı', 'fa-rotate', 'light' );
		echo '</div><div class="row">';
		self::smallbox( self::money( $ccount ? $T['rev'] / $ccount : 0 ), 'Müşteri başına ciro', 'fa-hand-holding-dollar', 'dark', true );
		self::smallbox( number_format( $ccount ? $T['oc'] / $ccount : 0, 2, ',', '.' ), 'Müşteri başına sipariş', 'fa-receipt', 'light' );
		self::smallbox( (int) $D['reg']['c'] . ' <small>sip</small>', 'Kayıtlı müşteri siparişi', 'fa-id-card', 'light', true );
		self::smallbox( (int) $D['guest']['c'] . ' <small>sip</small>', 'Misafir siparişi', 'fa-user-secret', 'light', true );
		echo '</div>';

		self::chart_card( 'Yeni vs. tekrar eden (sipariş adedi)', 'fa-user-group', 'tx-c-nr', 'doughnut', array( 'Yeni müşteri', 'Tekrar eden' ), array( array( 'data' => array( (int) $D['newc']['c'], (int) $D['retc']['c'] ), 'backgroundColor' => array( '#5c7cfa', '#15171c' ) ) ), 240 );

		// En değerli müşteriler
		$cust = $D['cust']; uasort( $cust, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } ); $cust = array_slice( $cust, 0, 30, true );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-crown mr-2"></i>En değerli müşteriler <small class="text-muted">(dönem içi harcama)</small></h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Müşteri</th><th>E-posta</th><th>Şehir</th><th class="text-center" style="width:90px">Sipariş</th><th class="text-right" style="width:150px">Harcama</th><th class="text-right" style="width:140px">Ort. sepet</th></tr></thead><tbody>';
		foreach ( $cust as $cu ) { $nm = $cu['name'] !== '' ? $cu['name'] : '—'; $av = $cu['c'] > 0 ? $cu['rev'] / $cu['c'] : 0; echo '<tr><td class="tx-strong">' . esc_html( $nm ) . '</td><td class="text-muted">' . esc_html( $cu['email'] ) . '</td><td class="text-muted">' . esc_html( $cu['city'] ) . '</td><td class="text-center">' . (int) $cu['c'] . '</td><td class="text-right tx-strong">' . wp_kses_post( self::money( $cu['rev'] ) ) . '</td><td class="text-right text-muted">' . wp_kses_post( self::money( $av ) ) . '</td></tr>'; }
		echo '</tbody></table></div></div></div>';
	}

	/* ---------------- Coğrafya Analizi ---------------- */
	protected static function view_geo_analytics() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'cografya-analiz' );
		self::analytics_datebar( 'cografya-analiz', $rr );
		$D = self::analytics_core( $rr['from_ts'], $rr['to_ts'] ); $T = $D['T'];
		if ( (int) $T['oc'] === 0 ) { echo '<div class="tx-empty">Bu aralıkta ödenmiş sipariş yok.</div>'; return; }
		// Eyalet kodlarını isimlere çevir (TR illeri)
		$states = array(); if ( function_exists( 'WC' ) && WC()->countries ) { $st = WC()->countries->get_states( 'TR' ); if ( is_array( $st ) ) { $states = $st; } }
		$state_named = array();
		foreach ( $D['state'] as $code => $r ) { $key = isset( $states[ $code ] ) ? $states[ $code ] : ( $code === '' ? '(belirtilmemiş)' : $code ); if ( isset( $state_named[ $key ] ) ) { $state_named[ $key ]['c'] += $r['c']; $state_named[ $key ]['rev'] += $r['rev']; } else { $state_named[ $key ] = $r; } }
		// Ülke kodlarını isme
		$countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();
		$country_named = array();
		foreach ( $D['country'] as $code => $r ) { $key = isset( $countries[ $code ] ) ? $countries[ $code ] : ( $code === '' ? '(belirtilmemiş)' : $code ); $country_named[ $key ] = $r; }

		// İl bazında en iyi 10 grafik
		$sn = $state_named; uasort( $sn, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } ); $top = array_slice( $sn, 0, 10, true );
		$gl = array_keys( $top ); $gv = array(); foreach ( $top as $r ) { $gv[] = round( $r['rev'], 2 ); }
		self::chart_card( 'İl bazında ciro (ilk 10)', 'fa-map-location-dot', 'tx-c-state', 'bar', $gl, array( array( 'label' => 'Ciro (₺)', 'data' => $gv, 'backgroundColor' => '#5c7cfa', 'borderRadius' => 5 ) ), 300 );

		self::attr_break_table( 'İl / bölgeye göre', 'fa-map', $state_named, $T['rev'], $T['oc'], 'İl', 'Sipariş', 'rev', 50 );
		self::attr_break_table( 'Şehre göre', 'fa-city', $D['city'], $T['rev'], $T['oc'], 'Şehir', 'Sipariş', 'rev', 60 );
		self::attr_break_table( 'Ülkeye göre', 'fa-globe', $country_named, $T['rev'], $T['oc'], 'Ülke' );
	}

	/* ---------------- Kupon & İndirim Analizi ---------------- */
	protected static function view_coupon_analytics() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'kupon-analiz' );
		self::analytics_datebar( 'kupon-analiz', $rr );
		$D = self::analytics_core( $rr['from_ts'], $rr['to_ts'] ); $T = $D['T'];
		$coup = $D['coupon']; $cused = 0; $crev = 0.0;
		foreach ( $coup as $c ) { $cused += $c['c']; $crev += $c['rev']; }
		echo '<div class="row">';
		self::smallbox( self::money( $T['disc'] ), 'Toplam indirim', 'fa-tags', 'dark', true );
		self::smallbox( (int) $cused, 'Kupon kullanılan sipariş', 'fa-ticket', 'light' );
		self::smallbox( (int) ( $T['oc'] - $cused ), 'Kuponsuz sipariş', 'fa-ban', 'light' );
		self::smallbox( '%' . number_format( $T['oc'] ? $cused / $T['oc'] * 100 : 0, 1 ), 'Kupon kullanım oranı', 'fa-percent', 'light' );
		echo '</div>';
		if ( empty( $coup ) ) { echo '<div class="tx-empty">Bu aralıkta kupon kullanılmamış.</div>'; return; }
		uasort( $coup, function ( $a, $b ) { return $b['disc'] <=> $a['disc']; } );
		$maxd = 0.0; foreach ( $coup as $c ) { if ( $c['disc'] > $maxd ) { $maxd = $c['disc']; } }
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-ticket mr-2"></i>Kupon bazında</h3><div class="card-tools text-muted">' . count( $coup ) . ' kupon</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Kupon kodu</th><th class="text-center" style="width:110px">Kullanım</th><th style="width:200px">İndirim payı</th><th class="text-right" style="width:150px">Toplam indirim</th><th class="text-right" style="width:160px">Getirdiği ciro</th></tr></thead><tbody>';
		foreach ( $coup as $code => $c ) { $p = $maxd > 0 ? $c['disc'] / $maxd * 100 : 0; echo '<tr><td class="tx-strong">' . esc_html( $code ) . '</td><td class="text-center">' . (int) $c['c'] . '</td><td><div class="tx-bar"><span style="width:' . esc_attr( round( $p, 1 ) ) . '%"></span></div></td><td class="text-right tx-strong">' . wp_kses_post( self::money( $c['disc'] ) ) . '</td><td class="text-right text-muted">' . wp_kses_post( self::money( $c['rev'] ) ) . '</td></tr>'; }
		echo '</tbody></table></div></div></div>';
	}

	/* Kampanya tablosu (platform sütunlu) */
	protected static function attr_campaign_table( $rows, $tot_rev ) {
		if ( empty( $rows ) ) { return; }
		uasort( $rows, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-rectangle-ad mr-2"></i>Kampanyaya göre</h3><div class="card-tools text-muted">' . count( $rows ) . ' kampanya</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Kampanya</th><th style="width:130px">Platform</th><th class="text-center" style="width:90px">Sipariş</th><th style="width:190px">Ciro payı</th><th class="text-right" style="width:150px">Ciro</th><th class="text-right" style="width:130px">Ort. sepet</th></tr></thead><tbody>';
		foreach ( $rows as $name => $r ) {
			$pct = $tot_rev > 0 ? ( $r['rev'] / $tot_rev * 100 ) : 0;
			$avg = $r['c'] > 0 ? $r['rev'] / $r['c'] : 0;
			echo '<tr><td class="tx-strong">' . esc_html( $name ) . '</td><td class="text-muted">' . esc_html( $r['plat'] ) . '</td><td class="text-center">' . (int) $r['c'] . '</td>';
			echo '<td><div class="tx-bar"><span style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></span></div> <small class="text-muted">%' . esc_html( number_format( $pct, 1 ) ) . '</small></td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $r['rev'] ) ) . '</td><td class="text-right text-muted">' . wp_kses_post( self::money( $avg ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div></div>';
	}

	/* ---------------- Reklam & Kaynak Analizi ---------------- */
	protected static function view_attribution() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range(
			isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30',
			isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
		);
		$range = $rr['range']; $from = $rr['from']; $to = $rr['to'];
		$D = self::attribution_data( $rr['from_ts'], $rr['to_ts'] );

		self::analytics_tabs( 'kaynak-analiz' );
		$presets = array( 'today' => 'Bugün', 'yesterday' => 'Dün', 'last7' => 'Son 7 gün', 'last30' => 'Son 30 gün', 'thismonth' => 'Bu ay', 'lastmonth' => 'Geçen ay', 'thisyear' => 'Bu yıl' );
		echo '<div class="tx-rep-head mb-2"><div class="tx-chips">';
		foreach ( $presets as $k => $lbl ) { echo '<a class="tx-chip' . ( $range === $k ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'kaynak-analiz', 0, array( 'range' => $k ) ) ) . '">' . esc_html( $lbl ) . '</a>'; }
		echo '</div>';
		echo '<form class="tx-rep-custom" method="get" action="' . esc_url( self::url( 'kaynak-analiz' ) ) . '"><input type="hidden" name="range" value="custom"><input type="date" class="form-control" name="from" value="' . esc_attr( $from ) . '"><span class="tx-rep-dash">–</span><input type="date" class="form-control" name="to" value="' . esc_attr( $to ) . '"><button class="btn tx-btn" type="submit">Uygula</button></form>';
		echo '</div>';
		echo '<div class="tx-rep-period mb-3"><i class="far fa-calendar mr-1"></i>' . esc_html( $rr['label'] ) . ' · <b>' . (int) $D['tot']['c'] . '</b> ödenmiş sipariş</div>';

		if ( (int) $D['tot']['c'] === 0 ) { echo '<div class="tx-empty">Bu aralıkta ödenmiş sipariş yok.</div>'; return; }

		echo '<style>.tx-bar{position:relative;height:7px;border-radius:4px;background:#eef0f4;width:130px;display:inline-block;vertical-align:middle;overflow:hidden}.tx-bar>span{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,#3b5bdb,#5c7cfa);border-radius:4px}</style>';

		$rev = $D['tot']['rev']; $c = $D['tot']['c'];
		$paid = $D['sum_paid']; $org = $D['sum_org']; $dir = $D['sum_dir'];
		$paidpct = $rev > 0 ? round( $paid['rev'] / $rev * 100 ) : 0;
		echo '<div class="row">';
		self::smallbox( self::money( $rev ), 'Toplam ciro', 'fa-lira-sign', 'dark', true );
		self::smallbox( (int) $c, 'Ödenmiş sipariş', 'fa-cart-shopping', 'light' );
		self::smallbox( self::money( $paid['rev'] ) . ' <small>· ' . (int) $paid['c'] . ' sip · %' . $paidpct . '</small>', 'Ücretli reklam cirosu', 'fa-bullhorn', 'dark', true );
		self::smallbox( self::money( $org['rev'] ) . ' <small>· ' . (int) $org['c'] . ' sip</small>', 'Organik ciro', 'fa-seedling', 'light', true );
		echo '</div>';

		self::attr_break_table( 'Kanal tipine göre', 'fa-layer-group', $D['byChan'], $rev, $c, 'Kanal' );
		self::attr_break_table( 'Ücretli reklam — platform bazında', 'fa-bullhorn', $D['byPaid'], $paid['rev'], $paid['c'], 'Reklam platformu' );
		self::attr_campaign_table( $D['byCamp'], $rev );
		self::attr_break_table( 'Platforma göre (tüm kaynaklar)', 'fa-globe', $D['byPlat'], $rev, $c, 'Platform' );
		self::attr_break_table( 'Ortama göre (medium)', 'fa-diagram-project', $D['byMed'], $rev, $c, 'Ortam' );
		self::attr_break_table( 'Cihaza göre', 'fa-mobile-screen', $D['byDev'], $rev, $c, 'Cihaz' );
		self::attr_break_table( 'En çok dönüşüm getiren iniş sayfaları', 'fa-flag-checkered', $D['byLand'], $rev, $c, 'İniş sayfası (yol)' );
	}

	/* ---------------- Toplu Ürün Düzenleme ---------------- */
	protected static function view_bulk_products() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$cat = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
		$s   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash"><i class="fas fa-circle-check mr-1"></i>' . (int) ( isset( $_GET['n'] ) ? $_GET['n'] : 0 ) . ' ürün güncellendi.</div>'; }

		// Filtre çubuğu
		echo '<form method="get" action="' . esc_url( self::url( 'toplu-urun' ) ) . '" class="tx-rep-head mb-2"><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<select class="form-control tx-select" name="cat" style="max-width:260px"><option value="">Tüm kategoriler</option>';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) ) as $c ) { echo '<option value="' . esc_attr( $c->slug ) . '"' . selected( $cat, $c->slug, false ) . '>' . esc_html( $c->name ) . ' (' . $c->count . ')</option>'; }
		echo '</select>';
		echo '<input class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Ürün ara..." style="max-width:220px"><button class="btn tx-btn" type="submit">Filtrele</button>';
		if ( $cat || $s ) { echo '<a class="btn tx-btn" href="' . esc_url( self::url( 'toplu-urun' ) ) . '">Temizle</a>'; }
		echo '</div></form>';

		$args = array( 'status' => array( 'publish', 'draft' ), 'limit' => 300, 'orderby' => 'title', 'order' => 'ASC', 'return' => 'ids' );
		if ( $cat ) { $args['category'] = array( $cat ); }
		if ( $s ) { $args['s'] = $s; }
		$ids = wc_get_products( $args );

		echo '<form method="post" action="' . esc_url( self::url( 'toplu-urun' ) ) . '">';
		wp_nonce_field( 'wcp_bulk' );
		echo '<input type="hidden" name="wcp_action" value="bulk_apply"><input type="hidden" name="cat" value="' . esc_attr( $cat ) . '">';

		// Toplu işlem çubuğu
		echo '<div class="card card-outline tx-card"><div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">';
		echo '<div><label class="tx-label">İşlem</label><select class="form-control tx-select" name="bulk_action" style="min-width:240px">';
		$acts = array(
			'fiyat_pct_up' => 'Düzenli fiyatı % artır', 'fiyat_pct_down' => 'Düzenli fiyatı % azalt',
			'fiyat_add' => 'Düzenli fiyata + TL ekle', 'fiyat_sub' => 'Düzenli fiyattan - TL çıkar', 'fiyat_set' => 'Düzenli fiyatı ayarla (TL)',
			'satis_pct' => 'Düzenli fiyattan % indirim (satış fiyatı)', 'satis_set' => 'Satış fiyatını ayarla (TL)', 'satis_clear' => 'Satış fiyatını kaldır',
			'stok_set' => 'Stok adedini ayarla', 'stok_add' => 'Stok + ekle', 'stok_sub' => 'Stok - çıkar',
			'durum_instock' => 'Stok durumu: Stokta var', 'durum_outofstock' => 'Stok durumu: Tükendi',
			'yayin_publish' => 'Yayına al', 'yayin_draft' => 'Taslağa al',
		);
		foreach ( $acts as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>'; }
		echo '</select></div>';
		echo '<div><label class="tx-label">Değer</label><input class="form-control" name="bulk_val" placeholder="örn. 10" style="max-width:120px"></div>';
		echo '<button class="btn tx-btn primary" type="submit" onclick="return confirm(\'Seçili ürünlere uygulansın mı?\')"><i class="fas fa-bolt mr-1"></i> Seçililere uygula</button>';
		echo '<span class="tx-mini" style="color:#8a94a6">İşlem tipine göre "Değer": yüzde, TL veya adet.</span>';
		echo '</div></div>';

		// Ürün tablosu
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ürünler</h3><div class="card-tools text-muted">' . count( $ids ) . ' ürün</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th style="width:32px"><input type="checkbox" class="tx-checkall"></th><th>Ürün</th><th>SKU</th><th class="text-right">Düzenli</th><th class="text-right">Satış</th><th class="text-center">Stok</th><th>Durum</th></tr></thead><tbody>';
		if ( empty( $ids ) ) { echo '<tr><td colspan="7"><div class="tx-empty">Ürün bulunamadı.</div></td></tr>'; }
		else { foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid ); if ( ! $p ) { continue; }
			$st = $p->get_status();
			echo '<tr><td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $pid ) . '"></td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'product', $pid ) ) . '">' . esc_html( $p->get_name() ) . '</a></td>';
			echo '<td class="text-muted">' . esc_html( $p->get_sku() ? $p->get_sku() : '—' ) . '</td>';
			echo '<td class="text-right">' . wp_kses_post( self::money( $p->get_regular_price() ) ) . '</td>';
			echo '<td class="text-right">' . ( $p->get_sale_price() !== '' ? wp_kses_post( self::money( $p->get_sale_price() ) ) : '<span class="text-muted">—</span>' ) . '</td>';
			echo '<td class="text-center">' . ( $p->managing_stock() ? (int) $p->get_stock_quantity() : ( $p->get_stock_status() === 'instock' ? 'Var' : 'Yok' ) ) . '</td>';
			echo '<td><span class="tx-badge ' . ( $st === 'publish' ? 'tx-ok' : 'tx-muted' ) . '">' . esc_html( $st === 'publish' ? 'Yayında' : 'Taslak' ) . '</span></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
		echo '</form>';
		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	/* ---------------- İptal / İade Talepleri ---------------- */
	protected static function view_cancel_requests() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$orders = wc_get_orders( array( 'status' => 'cancel-request', 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-rotate-left mr-2"></i>İptal / İade Talepleri</h3><div class="card-tools text-muted">' . count( $orders ) . ' talep</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th class="text-right">Tutar</th><th>Sebep</th><th>Eylemler</th></tr></thead><tbody>';
		if ( empty( $orders ) ) { echo '<tr><td colspan="6"><div class="tx-empty">Bekleyen talep yok 🎉</div></td></tr>'; }
		else { foreach ( $orders as $o ) {
			$nm = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
			$reason = trim( (string) $o->get_meta( '_wc_cancel_key' ) . ' ' . (string) $o->get_meta( '_wc_cancel_additional_txt' ) );
			$rn = wp_create_nonce( 'wcp_crreq_' . $o->get_id() );
			echo '<tr><td class="tx-strong"><a href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">#' . esc_html( $o->get_order_number() ) . '</a></td>';
			echo '<td class="text-muted">' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '' ) . '</td>';
			echo '<td class="text-muted"><span class="tx-trunc">' . esc_html( $nm ) . '</span></td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $o->get_total() ) ) . '</td>';
			echo '<td class="text-muted"><span class="tx-trunc">' . esc_html( $reason ? $reason : '—' ) . '</span></td>';
			echo '<td class="tx-actions-cell"><a class="btn btn-sm tx-btn tx-btn-danger" href="' . esc_url( self::url( 'talepler', 0, array( 'crreq' => 'approve', 'id' => $o->get_id(), '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'İptal talebi onaylanıp sipariş İPTAL edilecek. Devam?\')">Onayla (iptal et)</a> <a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'talepler', 0, array( 'crreq' => 'reject', 'id' => $o->get_id(), '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Talep reddedilip sipariş Hazırlanıyor durumuna alınacak. Devam?\')">Reddet</a></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
	}

	/* ---------------- Entegrasyonlar & Pazarlama ---------------- */
	protected static function view_integrations() {
		$active = (array) get_option( 'active_plugins', array() );
		$has = function ( $slug ) use ( $active ) { foreach ( $active as $p ) { if ( strpos( $p, $slug . '/' ) === 0 || strpos( $p, '/' . $slug ) !== false ) { return true; } } return false; };
		$pixel = get_option( 'wc_facebook_pixel_id' );
		$pp = array();
		if ( $has( 'pixelyoursite' ) ) { $pp[] = 'PixelYourSite'; }
		if ( $has( 'facebook-for-woocommerce' ) ) { $pp[] = 'Facebook for WooCommerce'; }
		if ( $has( 'official-facebook-pixel' ) ) { $pp[] = 'Official Facebook Pixel'; }
		$gla_conn = get_option( 'gla_google_connected' ); $mc = get_option( 'gla_merchant_id' ); $adsid = get_option( 'gla_ads_id' );
		$bad = function ( $ok ) { return '<span class="tx-badge ' . ( $ok ? 'tx-ok' : 'tx-muted' ) . '">' . ( $ok ? 'Bağlı/Aktif' : 'Pasif' ) . '</span>'; };

		echo '<div class="row"><div class="col-lg-6">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-chart-simple mr-2"></i>Meta (Facebook) Pixel</h3></div><div class="card-body tx-kv">';
		echo '<div><span>Pixel ID</span><b>' . esc_html( $pixel ? $pixel : '—' ) . '</b></div>';
		echo '<div><span>Pixel yükleyen eklenti</span><b>' . esc_html( $pp ? implode( ', ', $pp ) : '—' ) . '</b></div>';
		echo '</div>';
		if ( count( $pp ) > 1 ) { echo '<div class="card-body" style="padding-top:0"><div style="background:#fff5f5;border-left:4px solid #dc2626;border-radius:8px;color:#8a1f1f;padding:10px 12px;font-size:13px"><b>⚠ Çift sayım riski:</b> ' . count( $pp ) . ' eklenti aynı Meta Pixel\'i yüklüyor → dönüşümler mükerrer sayılabilir. Öneri: yalnızca birini bırakın (ör. <b>Official Facebook Pixel</b>\'i kapatın).</div></div>'; }
		echo '</div>';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fab fa-google mr-2"></i>Google (Shopping / Ads)</h3></div><div class="card-body tx-kv">';
		echo '<div><span>Google bağlantısı</span><b>' . $bad( ! empty( $gla_conn ) ) . '</b></div>';
		echo '<div><span>Merchant Center ID</span><b>' . esc_html( $mc ? $mc : '—' ) . '</b></div>';
		echo '<div><span>Google Ads ID</span><b>' . esc_html( $adsid ? $adsid : '—' ) . '</b></div>';
		echo '</div></div>';
		echo '</div><div class="col-lg-6">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-credit-card mr-2"></i>Ödeme Yöntemleri</h3></div><div class="card-body tx-kv">';
		$gws = ( function_exists( 'WC' ) && WC()->payment_gateways() ) ? WC()->payment_gateways()->payment_gateways() : array();
		$any = false;
		foreach ( $gws as $gw ) { echo '<div><span>' . esc_html( $gw->get_method_title() ) . '</span><b>' . ( $gw->enabled === 'yes' ? '<span class="tx-badge tx-ok">Aktif</span>' : '<span class="tx-badge tx-muted">Kapalı</span>' ) . '</b></div>'; $any = true; }
		if ( ! $any ) { echo '<div class="tx-empty">Ödeme yöntemi yok.</div>'; }
		echo '</div></div>';
		$list = array(
			'Google Listings & Ads' => $has( 'google-listings-and-ads' ),
			'Facebook for WooCommerce' => $has( 'facebook-for-woocommerce' ),
			'PixelYourSite' => $has( 'pixelyoursite' ),
			'Omnisend (e-posta)' => $has( 'omnisend' ),
			'Google Tag Manager' => $has( 'duracelltomi-google-tag-manager' ),
			'Ürün Feed (Feed PRO)' => $has( 'woo-product-feed-pro' ),
			'Google Yorumlar' => $has( 'wp-reviews-plugin-for-google' ),
			'Canlı Sohbet (Tawk.to)' => $has( 'tawkto-live-chat' ),
			'PayTR Sanal POS' => $has( 'paytr-sanal-pos' ),
		);
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-plug mr-2"></i>Aktif Entegrasyonlar</h3></div><div class="card-body tx-kv">';
		foreach ( $list as $lbl => $ok ) { echo '<div><span>' . esc_html( $lbl ) . '</span><b>' . $bad( $ok ) . '</b></div>'; }
		echo '</div></div>';
		echo '</div></div>';
		echo '<p class="tx-mini" style="color:#8a94a6">Detaylı ayarlar için <b>Tüm Yönetim</b> bölümünü kullanın.</p>';
	}

	/* ---------------- Dışa / İçe Aktar ---------------- */
	protected static function view_export() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		if ( isset( $_GET['imp'] ) && $_GET['imp'] === 'ok' ) { echo '<div class="alert tx-flash"><i class="fas fa-circle-check mr-1"></i>' . (int) ( isset( $_GET['u'] ) ? $_GET['u'] : 0 ) . ' ürün güncellendi, ' . (int) ( isset( $_GET['s'] ) ? $_GET['s'] : 0 ) . ' atlandı.</div>'; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		$purl = wp_nonce_url( self::url( 'aktar', 0, array( 'wcp_export' => 'products' ) ), 'wcp_export' );
		$ourl = wp_nonce_url( self::url( 'aktar', 0, array( 'wcp_export' => 'orders', 'range' => $rr['range'], 'from' => $rr['from'], 'to' => $rr['to'] ) ), 'wcp_export' );

		echo '<div class="row"><div class="col-lg-6">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-file-export mr-2"></i>Ürün Dışa Aktar</h3></div><div class="card-body">';
		echo '<p class="tx-mini" style="color:#5b6677">Tüm ürünler (sku, ad, fiyat, satış fiyatı, stok, durum, kategoriler) CSV olarak indirilir.</p>';
		echo '<a class="btn tx-btn primary" href="' . esc_url( $purl ) . '"><i class="fas fa-download mr-1"></i> Ürünleri CSV indir</a>';
		echo '</div></div>';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-file-import mr-2"></i>Ürün İçe Aktar (güncelle)</h3></div><div class="card-body">';
		echo '<p class="tx-mini" style="color:#5b6677">CSV yükle → <b>SKU eşleşen</b> ürünler güncellenir. Sütunlar: <code>sku, duzenli_fiyat, satis_fiyati, stok, durum, stok_durumu</code> (sadece dolu alanlar uygulanır; yeni ürün eklenmez).</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( self::url( 'aktar' ) ) . '">';
		wp_nonce_field( 'wcp_import' );
		echo '<input type="hidden" name="wcp_action" value="import_products">';
		echo '<input type="file" name="csv" accept=".csv,text/csv" class="form-control mb-2" required>';
		echo '<button class="btn tx-btn primary" type="submit" onclick="return confirm(\'CSV içindeki SKU eşleşen ürünler güncellenecek. Devam?\')"><i class="fas fa-upload mr-1"></i> Yükle & güncelle</button>';
		echo '</form></div></div>';
		echo '</div><div class="col-lg-6">';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i>Sipariş Dışa Aktar</h3></div><div class="card-body">';
		$presets = array( 'today' => 'Bugün', 'yesterday' => 'Dün', 'last7' => 'Son 7 gün', 'last30' => 'Son 30 gün', 'thismonth' => 'Bu ay', 'lastmonth' => 'Geçen ay', 'thisyear' => 'Bu yıl' );
		echo '<div class="tx-chips mb-2">';
		foreach ( $presets as $k => $lbl ) { echo '<a class="tx-chip' . ( $rr['range'] === $k ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'aktar', 0, array( 'range' => $k ) ) ) . '">' . esc_html( $lbl ) . '</a>'; }
		echo '</div>';
		echo '<form class="tx-rep-custom mb-2" method="get" action="' . esc_url( self::url( 'aktar' ) ) . '"><input type="hidden" name="range" value="custom"><input type="date" class="form-control" name="from" value="' . esc_attr( $rr['from'] ) . '"><span class="tx-rep-dash">–</span><input type="date" class="form-control" name="to" value="' . esc_attr( $rr['to'] ) . '"><button class="btn tx-btn" type="submit">Aralık seç</button></form>';
		echo '<p class="tx-mini" style="color:#5b6677">' . esc_html( $rr['label'] ) . ' arası siparişler (no, tarih, durum, müşteri, iletişim, ödeme, tutar, iade).</p>';
		echo '<a class="btn tx-btn primary" href="' . esc_url( $ourl ) . '"><i class="fas fa-download mr-1"></i> Siparişleri CSV indir</a>';
		echo '</div></div>';
		echo '</div></div>';
	}

	/* ---------------- İade Raporu ---------------- */
	protected static function view_refund_report() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'iade-analiz' );
		self::analytics_datebar( 'iade-analiz', $rr );
		$orders = wc_get_orders( array( 'limit' => -1, 'date_created' => $rr['from_ts'] . '...' . $rr['to_ts'], 'status' => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft', 'wc-cancelled', 'wc-failed' ) ), 'orderby' => 'date', 'order' => 'DESC' ) );
		$rows = array(); $tot_ref = 0.0; $tot_rev = 0.0; $cnt = 0;
		foreach ( $orders as $o ) {
			$tot_rev += (float) $o->get_total();
			$ref = (float) $o->get_total_refunded();
			if ( $ref > 0 ) { $cnt++; $tot_ref += $ref; $reasons = array(); foreach ( $o->get_refunds() as $rf ) { $rs = trim( (string) $rf->get_reason() ); if ( $rs ) { $reasons[] = $rs; } } $rows[] = array( 'o' => $o, 'ref' => $ref, 'reason' => implode( ' · ', array_slice( $reasons, 0, 2 ) ) ); }
		}
		echo '<div class="row">';
		self::smallbox( self::money( $tot_ref ), 'Toplam iade', 'fa-rotate-left', 'dark', true );
		self::smallbox( (int) $cnt, 'İade edilen sipariş', 'fa-receipt', 'light' );
		self::smallbox( '%' . number_format( $tot_rev > 0 ? $tot_ref / $tot_rev * 100 : 0, 1 ), 'İade oranı (ciroya)', 'fa-percent', 'light' );
		self::smallbox( self::money( $cnt ? $tot_ref / $cnt : 0 ), 'Ort. iade tutarı', 'fa-coins', 'light', true );
		echo '</div>';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-rotate-left mr-2"></i>İadeler</h3><div class="card-tools text-muted">' . count( $rows ) . ' kayıt</div></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th class="text-right">Sipariş tutarı</th><th class="text-right">İade</th><th>Sebep</th></tr></thead><tbody>';
		if ( empty( $rows ) ) { echo '<tr><td colspan="6"><div class="tx-empty">Bu aralıkta iade yok.</div></td></tr>'; }
		else { foreach ( $rows as $r ) { $o = $r['o']; $nm = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ); echo '<tr><td class="tx-strong"><a href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">#' . esc_html( $o->get_order_number() ) . '</a></td><td class="text-muted">' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y' ) : '' ) . '</td><td class="text-muted"><span class="tx-trunc">' . esc_html( $nm ) . '</span></td><td class="text-right">' . wp_kses_post( self::money( $o->get_total() ) ) . '</td><td class="text-right tx-strong tx-refund">- ' . wp_kses_post( self::money( $r['ref'] ) ) . '</td><td class="text-muted"><span class="tx-trunc">' . esc_html( $r['reason'] ) . '</span></td></tr>'; } }
		echo '</tbody></table></div></div></div>';
	}

	/* ---------------- Vergi Raporu ---------------- */
	protected static function view_tax_report() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range( isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last30', isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '' );
		self::analytics_tabs( 'vergi-analiz' );
		self::analytics_datebar( 'vergi-analiz', $rr );
		$paid = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-pending', 'wc-checkout-draft' ) );
		$orders = wc_get_orders( array( 'limit' => -1, 'date_created' => $rr['from_ts'] . '...' . $rr['to_ts'], 'status' => $paid ) );
		$tot_tax = 0.0; $oc = 0; $byRate = array();
		foreach ( $orders as $o ) {
			$t = (float) $o->get_total_tax(); if ( $t > 0 ) { $oc++; } $tot_tax += $t;
			foreach ( $o->get_items( 'tax' ) as $ti ) {
				$lbl = $ti->get_label(); if ( $lbl === '' ) { $lbl = 'Vergi'; }
				$amt = (float) $ti->get_tax_total() + (float) $ti->get_shipping_tax_total();
				if ( ! isset( $byRate[ $lbl ] ) ) { $byRate[ $lbl ] = array( 'c' => 0, 'rev' => 0.0 ); }
				$byRate[ $lbl ]['c']++; $byRate[ $lbl ]['rev'] += $amt;
			}
		}
		echo '<div class="row">';
		self::smallbox( self::money( $tot_tax ), 'Toplam KDV/vergi', 'fa-percent', 'dark', true );
		self::smallbox( (int) $oc, 'Vergili sipariş', 'fa-receipt', 'light' );
		self::smallbox( self::money( $oc ? $tot_tax / $oc : 0 ), 'Sipariş başına vergi', 'fa-coins', 'light', true );
		echo '</div>';
		self::attr_break_table( 'Vergi oranına göre', 'fa-percent', $byRate, $tot_tax, $oc, 'Vergi', 'İşlem', 'rev' );
	}

	protected static function view_reports() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$rr = self::report_resolve_range(
			isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last7',
			isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
		);
		$range = $rr['range']; $from = $rr['from']; $to = $rr['to']; $from_ts = $rr['from_ts']; $to_ts = $rr['to_ts'];
		self::analytics_tabs( 'reports' );
		$D = self::report_data( $from_ts, $to_ts );
		$rev = $D['rev']; $tax = $D['tax']; $ship = $D['ship']; $items = $D['items']; $ocount = $D['ocount']; $refund = $D['refund']; $avg = $D['avg'];
		$daily = $D['daily']; $daily_o = $D['daily_o']; $prod = $D['prod']; $cats = $D['cats']; $stcount = $D['stcount']; $pay = $D['pay'];

		if ( isset( $_GET['msg'] ) ) {
			$m = sanitize_key( $_GET['msg'] );
			if ( $m === 'mailed' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Rapor e-posta ile gönderildi.</div>'; }
			elseif ( $m === 'mailfail' ) { echo '<div class="tx-flash-err mb-3"><i class="fas fa-circle-exclamation mr-2"></i>E-posta gönderilemedi — adresi/SMTP ayarını kontrol edin.</div>'; }
		}

		// === Tarih aralığı çubuğu ===
		$presets = array( 'today' => 'Bugün', 'yesterday' => 'Dün', 'last7' => 'Son 7 gün', 'last30' => 'Son 30 gün', 'thismonth' => 'Bu ay', 'lastmonth' => 'Geçen ay', 'thisyear' => 'Bu yıl' );
		echo '<div class="tx-rep-head mb-2"><div class="tx-chips">';
		foreach ( $presets as $k => $lbl ) { echo '<a class="tx-chip' . ( $range === $k ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'reports', 0, array( 'range' => $k ) ) ) . '">' . esc_html( $lbl ) . '</a>'; }
		echo '</div>';
		echo '<form class="tx-rep-custom" method="get" action="' . esc_url( self::url( 'reports' ) ) . '"><input type="hidden" name="range" value="custom"><input type="date" class="form-control" name="from" value="' . esc_attr( $from ) . '"><span class="tx-rep-dash">–</span><input type="date" class="form-control" name="to" value="' . esc_attr( $to ) . '"><button class="btn tx-btn" type="submit">Uygula</button></form>';
		echo '</div>';
		echo '<div class="tx-rep-period mb-3"><i class="far fa-calendar mr-1"></i>' . esc_html( date_i18n( 'd F Y', $from_ts ) ) . ' — ' . esc_html( date_i18n( 'd F Y', $to_ts ) ) . ' · <b>' . (int) $ocount . '</b> ödenmiş sipariş</div>';

		// === Dışa aktar & e-posta ===
		$exp = array( 'range' => $range, 'from' => $from, 'to' => $to );
		$pdf_url = wp_nonce_url( self::url( 'reports', 0, array_merge( $exp, array( 'wcp_report' => 'pdf' ) ) ), 'wcp_report_export' );
		$xls_url = wp_nonce_url( self::url( 'reports', 0, array_merge( $exp, array( 'wcp_report' => 'xls' ) ) ), 'wcp_report_export' );
		echo '<div class="tx-rep-export mb-3">';
		echo '<a class="btn tx-btn" href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener"><i class="fas fa-file-pdf mr-1"></i>PDF indir</a>';
		echo '<a class="btn tx-btn" href="' . esc_url( $xls_url ) . '"><i class="fas fa-file-excel mr-1"></i>Excel indir</a>';
		echo '<form method="post" action="' . esc_url( self::url( 'reports' ) ) . '" class="tx-rep-mailform">';
		wp_nonce_field( 'wcp_report_email' );
		echo '<input type="hidden" name="wcp_action" value="report_email"><input type="hidden" name="range" value="' . esc_attr( $range ) . '"><input type="hidden" name="from" value="' . esc_attr( $from ) . '"><input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
		echo '<input type="email" class="form-control" name="email" value="' . esc_attr( get_option( 'admin_email' ) ) . '" placeholder="E-posta adresi" required>';
		echo '<label class="tx-inline-check"><input type="checkbox" name="fmt_pdf" value="1" checked> PDF</label>';
		echo '<label class="tx-inline-check"><input type="checkbox" name="fmt_xls" value="1" checked> Excel</label>';
		echo '<button class="btn tx-btn primary" type="submit"><i class="fas fa-paper-plane mr-1"></i>E-posta gönder</button>';
		echo '</form></div>';

		// === İstatistik kartları ===
		echo '<div class="row">';
		self::smallbox( self::money( $rev ), 'Ciro', 'fa-lira-sign', 'dark', true );
		self::smallbox( (int) $ocount, 'Sipariş', 'fa-cart-shopping', 'light' );
		self::smallbox( (int) $items, 'Satılan ürün', 'fa-box', 'light' );
		self::smallbox( self::money( $avg ), 'Ortalama sepet', 'fa-receipt', 'light', true );
		echo '</div><div class="row">';
		self::smallbox( self::money( $ship ), 'Kargo geliri', 'fa-truck', 'light', true );
		self::smallbox( self::money( $tax ), 'Vergi', 'fa-percent', 'light', true );
		self::smallbox( self::money( $refund ), 'İade', 'fa-rotate-left', 'light', true );
		self::smallbox( self::money( $rev - $refund ), 'Net gelir', 'fa-sack-dollar', 'dark', true );
		echo '</div>';

		// === Satış grafiği ===
		$labels = array(); $rdata = array(); $odata = array();
		foreach ( $daily as $d => $v ) { $labels[] = date_i18n( 'd.m', strtotime( $d ) ); $rdata[] = round( $v, 2 ); $odata[] = (int) $daily_o[ $d ]; }
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-chart-area mr-2"></i>Satış grafiği</h3></div><div class="card-body"><canvas id="tx-rep-chart" height="90"></canvas></div></div>';

		echo '<div class="row">';
		// En çok satan ürünler
		echo '<div class="col-lg-7"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">En çok satan ürünler</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>#</th><th>Ürün</th><th class="text-center">Adet</th><th class="text-right">Gelir</th></tr></thead><tbody>';
		uasort( $prod, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		if ( empty( $prod ) ) { echo '<tr><td colspan="4"><div class="tx-empty">Bu aralıkta satış yok.</div></td></tr>'; }
		$i = 0;
		foreach ( $prod as $pid => $pd ) { $i++; if ( $i > 12 ) { break; }
			echo '<tr><td class="text-muted">' . $i . '</td><td><a class="tx-strong" href="' . esc_url( self::url( 'product', $pid ) ) . '"><span class="tx-trunc" style="max-width:300px">' . esc_html( $pd['name'] ) . '</span></a></td><td class="text-center">' . (int) $pd['qty'] . '</td><td class="text-right tx-strong">' . wp_kses_post( self::money( $pd['rev'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div></div></div></div>';

		// Kategori dağılımı
		echo '<div class="col-lg-5"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Kategori dağılımı</h3></div><div class="card-body">';
		uasort( $cats, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		$cmax = 0; foreach ( $cats as $c ) { if ( $c['rev'] > $cmax ) { $cmax = $c['rev']; } }
		if ( empty( $cats ) ) { echo '<div class="tx-empty">Veri yok.</div>'; }
		$i = 0;
		foreach ( $cats as $c ) { $i++; if ( $i > 10 ) { break; }
			$pct = $cmax > 0 ? round( $c['rev'] / $cmax * 100 ) : 0;
			echo '<div class="tx-bar-row"><div class="tx-bar-head"><span class="tx-trunc">' . esc_html( $c['name'] ) . '</span><b>' . wp_kses_post( self::money( $c['rev'] ) ) . '</b></div><div class="tx-bar"><span style="width:' . (int) $pct . '%"></span></div></div>';
		}
		echo '</div></div></div>';
		echo '</div>';

		// === Durum & ödeme dağılımı ===
		echo '<div class="row">';
		echo '<div class="col-lg-6"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Sipariş durumları</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Durum</th><th class="text-right">Adet</th></tr></thead><tbody>';
		arsort( $stcount );
		if ( empty( $stcount ) ) { echo '<tr><td colspan="2"><div class="tx-empty">Veri yok.</div></td></tr>'; }
		foreach ( $stcount as $st => $cnt ) { echo '<tr><td><span class="tx-badge ' . esc_attr( self::badge_class( $st ) ) . '">' . esc_html( self::status_label( $st ) ) . '</span></td><td class="text-right tx-strong">' . (int) $cnt . '</td></tr>'; }
		echo '</tbody></table></div></div></div></div>';

		echo '<div class="col-lg-6"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ödeme yöntemleri</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Yöntem</th><th class="text-center">Sipariş</th><th class="text-right">Gelir</th></tr></thead><tbody>';
		uasort( $pay, function ( $a, $b ) { return $b['rev'] <=> $a['rev']; } );
		if ( empty( $pay ) ) { echo '<tr><td colspan="3"><div class="tx-empty">Veri yok.</div></td></tr>'; }
		foreach ( $pay as $name => $pd ) { echo '<tr><td><span class="tx-trunc">' . esc_html( $name ) . '</span></td><td class="text-center">' . (int) $pd['count'] . '</td><td class="text-right tx-strong">' . wp_kses_post( self::money( $pd['rev'] ) ) . '</td></tr>'; }
		echo '</tbody></table></div></div></div></div>';
		echo '</div>';

		self::reports_chart_js( $labels, $rdata, $odata );
	}

	protected static function reports_chart_js( $labels, $rdata, $odata ) {
		?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
	var el=document.getElementById('tx-rep-chart'); if(!el||typeof Chart==='undefined'){ return; }
	new Chart(el.getContext('2d'),{
		type:'line',
		data:{ labels:<?php echo wp_json_encode( $labels ); ?>,
			datasets:[
				{ label:'Ciro (₺)', data:<?php echo wp_json_encode( $rdata ); ?>, borderColor:'#15171c', backgroundColor:'rgba(21,23,28,.07)', borderWidth:2, fill:true, tension:.32, pointRadius:3, pointBackgroundColor:'#15171c', yAxisID:'y' },
				{ label:'Sipariş', data:<?php echo wp_json_encode( $odata ); ?>, borderColor:'#b9bec9', backgroundColor:'rgba(185,190,201,.0)', borderWidth:2, borderDash:[5,4], fill:false, tension:.32, pointRadius:0, yAxisID:'y1' }
			]
		},
		options:{ responsive:true, maintainAspectRatio:false, interaction:{mode:'index',intersect:false},
			plugins:{ legend:{ labels:{ usePointStyle:true, font:{ family:'Inter,sans-serif' } } }, tooltip:{ callbacks:{ label:function(c){ return c.dataset.label+': '+(c.datasetIndex===0?(c.parsed.y.toLocaleString('tr-TR')+' ₺'):c.parsed.y); } } } },
			scales:{ x:{ grid:{ display:false } }, y:{ position:'left', beginAtZero:true, ticks:{ callback:function(v){ return v.toLocaleString('tr-TR'); } }, grid:{ color:'#f0f2f5' } }, y1:{ position:'right', beginAtZero:true, grid:{ display:false }, ticks:{ precision:0 } } }
		}
	});
})();
</script>
		<?php
	}

	/* ---------------- Medya ---------------- */
	protected static function view_media() {
		$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$mime  = isset( $_GET['mime'] ) ? sanitize_text_field( wp_unslash( $_GET['mime'] ) ) : '';
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per   = 40;
		$args  = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => $per, 'paged' => $paged, 'orderby' => 'date', 'order' => 'DESC' );
		if ( $s ) { $args['s'] = $s; }
		if ( in_array( $mime, array( 'image', 'audio', 'video', 'application' ), true ) ) { $args['post_mime_type'] = $mime; }
		$q     = new WP_Query( $args );
		$total = (int) $q->found_posts;
		$max   = (int) $q->max_num_pages;

		// Yükleme alanı
		echo '<div class="tx-dropzone" id="tx-dropzone"><input type="file" id="tx-file" multiple hidden><i class="fas fa-cloud-arrow-up"></i><div class="tx-dz-text">Dosyaları buraya <b>sürükleyin</b> ya da <button type="button" id="tx-file-btn" class="tx-linkbtn">bilgisayardan seçin</button></div><div class="tx-upload-status" id="tx-upload-status"></div></div>';

		// Filtre çubuğu
		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'media' ) ) . '">';
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Dosya / başlık ara">';
		echo '<select name="mime" class="form-control"><option value="">Tüm türler</option>';
		foreach ( array( 'image' => 'Görseller', 'video' => 'Videolar', 'audio' => 'Sesler', 'application' => 'Belgeler' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $mime, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
		echo '</select><button class="btn tx-btn" type="submit">Filtrele</button>';
		echo '<span class="tx-rep-period ml-2">' . (int) $total . ' öğe</span></form>';

		// Izgara
		echo '<div class="tx-media-grid" id="tx-media-grid">';
		if ( empty( $q->posts ) ) { echo '<div class="tx-empty" style="grid-column:1/-1">Medya bulunamadı.</div>'; }
		foreach ( $q->posts as $att ) {
			$aid    = $att->ID;
			$is_img = wp_attachment_is_image( $aid );
			$thumb  = $is_img ? wp_get_attachment_image_url( $aid, 'medium' ) : wp_mime_type_icon( $aid );
			echo '<a class="tx-media-item" href="' . esc_url( self::url( 'media-item', $aid ) ) . '">';
			echo '<div class="tx-media-thumb' . ( $is_img ? '' : ' is-file' ) . '"><img src="' . esc_url( $thumb ) . '" loading="lazy" alt=""></div>';
			echo '<div class="tx-media-name"><span class="tx-trunc">' . esc_html( get_the_title( $aid ) ? get_the_title( $aid ) : wp_basename( get_attached_file( $aid ) ) ) . '</span></div>';
			echo '</a>';
		}
		echo '</div>';

		// Sayfalama
		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) {
				$a = array( 'paged' => $i ); if ( $s ) { $a['s'] = $s; } if ( $mime ) { $a['mime'] = $mime; }
				echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'media', 0, $a ) ) . '">' . $i . '</a>';
			}
			echo '</div>';
		}

		self::media_grid_js( admin_url( 'admin-ajax.php' ), wp_create_nonce( 'wcp_prod_img' ) );
	}

	protected static function media_grid_js( $ajax, $nonce ) {
		?>
<script>
(function(){
	var AJAX=<?php echo wp_json_encode( $ajax ); ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>;
	var dz=document.getElementById('tx-dropzone'), fi=document.getElementById('tx-file'), btn=document.getElementById('tx-file-btn'), grid=document.getElementById('tx-media-grid'), st=document.getElementById('tx-upload-status');
	if(!dz){ return; }
	var queue=0, ok=0, fail=0;
	function setStatus(){ if(queue>0){ st.textContent='Yükleniyor… ('+ok+'/'+(ok+fail+queue)+')'; } else if(ok+fail>0){ st.textContent=ok+' yüklendi'+(fail?(', '+fail+' başarısız'):'') ; setTimeout(function(){ st.textContent=''; ok=0; fail=0; },4000); } }
	function upload(file){ queue++; setStatus(); var fd=new FormData(); fd.append('action','wcp_media_upload'); fd.append('_ajax_nonce',NONCE); fd.append('file',file);
		fetch(AJAX,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(r){ queue--; if(r&&r.success){ ok++; var a=document.createElement('a'); a.className='tx-media-item tx-media-new'; a.href=r.data.edit; a.innerHTML='<div class="tx-media-thumb"><img src="'+r.data.thumb+'" alt=""></div><div class="tx-media-name"><span class="tx-trunc">'+(r.data.title||'')+'</span></div>'; if(grid){ var empty=grid.querySelector('.tx-empty'); if(empty){ empty.remove(); } grid.insertBefore(a,grid.firstChild); } } else { fail++; console.warn('yükleme:',r&&r.data); } setStatus(); }).catch(function(){ queue--; fail++; setStatus(); }); }
	function handle(files){ Array.prototype.slice.call(files).forEach(upload); }
	if(btn){ btn.addEventListener('click',function(){ fi.click(); }); }
	if(fi){ fi.addEventListener('change',function(){ handle(fi.files); fi.value=''; }); }
	['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); e.stopPropagation(); dz.classList.add('is-over'); }); });
	['dragleave','drop'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); e.stopPropagation(); dz.classList.remove('is-over'); }); });
	dz.addEventListener('drop',function(e){ if(e.dataTransfer&&e.dataTransfer.files){ handle(e.dataTransfer.files); } });
})();
</script>
		<?php
	}

	protected static function view_media_item( $id ) {
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) { echo '<div class="tx-empty">Medya bulunamadı. <a href="' . esc_url( self::url( 'media' ) ) . '">← Medyaya dön</a></div>'; return; }
		$is_img = wp_attachment_is_image( $id );
		$full   = wp_get_attachment_url( $id );
		$meta   = wp_get_attachment_metadata( $id );
		$alt    = get_post_meta( $id, '_wp_attachment_image_alt', true );
		$file   = get_attached_file( $id );
		$size   = ( $file && file_exists( $file ) ) ? size_format( filesize( $file ) ) : '—';
		$dims   = ( $is_img && ! empty( $meta['width'] ) ) ? ( $meta['width'] . ' × ' . $meta['height'] . ' px' ) : '—';
		$mime   = get_post_mime_type( $id );
		$date   = get_the_date( 'd.m.Y H:i', $id );
		$uploader = get_userdata( $att->post_author );

		echo '<div class="tx-detail-top mb-3"><a class="tx-back" href="' . esc_url( self::url( 'media' ) ) . '"><i class="fas fa-arrow-left mr-1"></i>Medya kütüphanesi</a>';
		echo '<button type="button" class="btn tx-btn tx-btn-danger" id="tx-media-del" data-id="' . esc_attr( $id ) . '"><i class="fas fa-trash mr-1"></i>Kalıcı sil</button></div>';

		echo '<div class="row"><div class="col-lg-6"><div class="card card-outline tx-card"><div class="card-body tx-media-preview">';
		if ( $is_img ) { echo '<img src="' . esc_url( $full ) . '" alt="">'; }
		else { echo '<div class="tx-media-fileicon"><img src="' . esc_url( wp_mime_type_icon( $id ) ) . '" alt=""><div>' . esc_html( wp_basename( $file ) ) . '</div></div>'; }
		echo '</div></div></div>';

		echo '<div class="col-lg-6"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Detaylar</h3><span class="tx-autosave-badge" id="tx-mf-badge"></span></div><div class="card-body">';
		// info satırları
		echo '<div class="tx-media-info">';
		echo '<div><span>Tür</span><b>' . esc_html( $mime ) . '</b></div>';
		echo '<div><span>Boyut</span><b>' . esc_html( $size ) . '</b></div>';
		echo '<div><span>Boyutlar</span><b>' . esc_html( $dims ) . '</b></div>';
		echo '<div><span>Yüklenme</span><b>' . esc_html( $date ) . '</b></div>';
		echo '<div><span>Yükleyen</span><b>' . esc_html( $uploader ? $uploader->display_name : '—' ) . '</b></div>';
		echo '<div><span>ID</span><b>#' . (int) $id . '</b></div>';
		echo '</div>';
		// URL kopyala
		echo '<label class="tx-label mt-2">Dosya URL</label><div class="tx-copyrow"><input type="text" class="form-control" id="tx-media-url" value="' . esc_attr( $full ) . '" readonly><button type="button" class="btn tx-btn" data-copy="#tx-media-url"><i class="fas fa-copy"></i></button></div>';
		// düzenlenebilir alanlar (anlık kayıt)
		echo '<label class="tx-label mt-2">Başlık</label><input type="text" class="form-control tx-mf" data-field="title" data-id="' . esc_attr( $id ) . '" value="' . esc_attr( $att->post_title ) . '">';
		if ( $is_img ) { echo '<label class="tx-label mt-2">Alternatif metin (alt)</label><input type="text" class="form-control tx-mf" data-field="alt" data-id="' . esc_attr( $id ) . '" value="' . esc_attr( $alt ) . '">'; }
		echo '<label class="tx-label mt-2">Altyazı</label><textarea class="form-control tx-mf" data-field="caption" data-id="' . esc_attr( $id ) . '" rows="2">' . esc_textarea( $att->post_excerpt ) . '</textarea>';
		echo '<label class="tx-label mt-2">Açıklama</label><textarea class="form-control tx-mf" data-field="description" data-id="' . esc_attr( $id ) . '" rows="3">' . esc_textarea( $att->post_content ) . '</textarea>';
		echo '</div></div></div></div>';

		self::media_item_js( admin_url( 'admin-ajax.php' ), wp_create_nonce( 'wcp_prod_img' ) );
	}

	protected static function media_item_js( $ajax, $nonce ) {
		?>
<script>
(function(){
	var AJAX=<?php echo wp_json_encode( $ajax ); ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>;
	var badge=document.getElementById('tx-mf-badge');
	function post(data){ var b=new URLSearchParams(); for(var k in data){ b.append(k,data[k]); } return fetch(AJAX,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:b.toString()}).then(function(r){return r.json();}); }
	function setBadge(s){ if(!badge){ return; } badge.className='tx-autosave-badge'+(s==='saving'?' is-saving':(s==='ok'?' is-ok':(s==='err'?' is-err':''))); badge.textContent=s==='saving'?'⚡ Kaydediliyor…':(s==='ok'?'✓ Kaydedildi':(s==='err'?'✕ Hata':'')); if(s==='ok'){ setTimeout(function(){ badge.textContent=''; badge.className='tx-autosave-badge'; },1800); } }
	document.querySelectorAll('.tx-mf').forEach(function(el){ el.addEventListener('blur',function(){ setBadge('saving'); post({action:'wcp_media_save',_ajax_nonce:NONCE,id:el.getAttribute('data-id'),field:el.getAttribute('data-field'),value:el.value}).then(function(r){ setBadge(r&&r.success?'ok':'err'); }).catch(function(){ setBadge('err'); }); }); });
	document.querySelectorAll('[data-copy]').forEach(function(b){ b.addEventListener('click',function(){ var t=document.querySelector(b.getAttribute('data-copy')); if(!t){ return; } t.select(); try{ navigator.clipboard.writeText(t.value); }catch(e){ document.execCommand('copy'); } var o=b.innerHTML; b.innerHTML='<i class="fas fa-check"></i>'; setTimeout(function(){ b.innerHTML=o; },1200); }); });
	var del=document.getElementById('tx-media-del');
	if(del){ del.addEventListener('click',function(){ if(!confirm('Bu medya dosyası KALICI olarak silinsin mi? Bu işlem geri alınamaz.')){ return; } del.disabled=true; post({action:'wcp_media_delete',_ajax_nonce:NONCE,id:del.getAttribute('data-id')}).then(function(r){ if(r&&r.success){ location.href=r.data.redirect; } else { alert((r&&r.data)||'Silinemedi'); del.disabled=false; } }).catch(function(){ alert('Hata'); del.disabled=false; }); }); }
})();
</script>
		<?php
	}

	/* ---------------- Sayfalar ---------------- */
	protected static function view_pages() {
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$is_trash = ( $status === 'trash' );
		$s        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per      = 30;
		$args = array( 'post_type' => 'page', 'posts_per_page' => $per, 'paged' => $paged, 'orderby' => 'menu_order title', 'order' => 'ASC' );
		if ( $is_trash ) { $args['post_status'] = 'trash'; }
		elseif ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) { $args['post_status'] = $status; }
		else { $args['post_status'] = array( 'publish', 'draft', 'pending', 'private' ); }
		if ( $s ) { $args['s'] = $s; }
		$q   = new WP_Query( $args );
		$max = (int) $q->max_num_pages;
		$pc  = wp_count_posts( 'page' );
		$n_pub = isset( $pc->publish ) ? (int) $pc->publish : 0;
		$n_draft = isset( $pc->draft ) ? (int) $pc->draft : 0;
		$n_trash = isset( $pc->trash ) ? (int) $pc->trash : 0;
		$front = (int) get_option( 'page_on_front' );
		$posts_page = (int) get_option( 'page_for_posts' );

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'pages' ) ) . '">';
		if ( $status ) { echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">'; }
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Sayfa ara">';
		echo '<button class="btn tx-btn" type="submit">Filtrele</button>';
		echo '<a class="btn tx-btn primary" href="' . esc_url( self::url( 'page' ) ) . '"><i class="fas fa-plus mr-1"></i> Yeni sayfa</a></form>';

		echo '<div class="tx-chips mb-3">';
		echo '<a class="tx-chip' . ( ! $status ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'pages' ) ) . '">Tümü</a>';
		echo '<a class="tx-chip' . ( $status === 'publish' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'pages', 0, array( 'status' => 'publish' ) ) ) . '">Yayında (' . $n_pub . ')</a>';
		echo '<a class="tx-chip' . ( $status === 'draft' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'pages', 0, array( 'status' => 'draft' ) ) ) . '">Taslak (' . $n_draft . ')</a>';
		echo '<a class="tx-chip tx-chip-trash' . ( $is_trash ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'pages', 0, array( 'status' => 'trash' ) ) ) . '">🗑 Çöp (' . $n_trash . ')</a>';
		echo '</div>';

		echo '<form method="post" action="' . esc_url( $status ? self::url( 'pages', 0, array( 'status' => $status ) ) : self::url( 'pages' ) ) . '">';
		wp_nonce_field( 'wcp_pages_bulk' );
		echo '<input type="hidden" name="wcp_action" value="pages_bulk"><input type="hidden" name="ret_status" value="' . esc_attr( $status ) . '">';
		echo '<div class="tx-bulkbar mb-2"><select name="bulk" class="form-control tx-select">';
		if ( $is_trash ) { echo '<option value="">Toplu işlem…</option><option value="untrash">Geri yükle</option><option value="delete">Kalıcı sil</option>'; }
		else { echo '<option value="">Toplu işlem…</option><option value="publish">Yayınla</option><option value="draft">Taslak yap</option><option value="trash">Çöp kutusuna taşı</option>'; }
		echo '</select><button class="btn tx-btn" type="submit" onclick="return this.form.bulk.value!==\'\'">Uygula</button></div>';
		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th style="width:30px"><input type="checkbox" class="tx-checkall"></th><th>Başlık</th><th>Yazar</th><th>Durum</th><th>Tarih</th><th></th></tr></thead><tbody>';
		if ( empty( $q->posts ) ) { echo '<tr><td colspan="6"><div class="tx-empty">Sayfa yok.</div></td></tr>'; }
		foreach ( $q->posts as $pg ) {
			$pid = $pg->ID; $st = $pg->post_status;
			$rn = wp_create_nonce( 'wcp_pgrow_' . $pid );
			$author = get_userdata( $pg->post_author );
			$tag = ''; if ( $pid === $front ) { $tag = '<span class="tx-badge tx-info ml-1">Ana sayfa</span>'; } elseif ( $pid === $posts_page ) { $tag = '<span class="tx-badge tx-info ml-1">Blog</span>'; }
			echo '<tr><td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $pid ) . '"></td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'page', $pid ) ) . '">' . esc_html( $pg->post_title ? $pg->post_title : '(başlıksız)' ) . '</a>' . $tag;
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'page', $pid ) ) . '">Düzenle</a> · <a href="' . esc_url( get_permalink( $pid ) ) . '" target="_blank">Görüntüle</a> · ';
			if ( $is_trash ) {
				echo '<a href="' . esc_url( self::url( 'pages', 0, array( 'pgrow' => 'untrash', 'id' => $pid, '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '">Geri yükle</a> · <a class="tx-del" href="' . esc_url( self::url( 'pages', 0, array( 'pgrow' => 'delete', 'id' => $pid, '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '" onclick="return confirm(\'Kalıcı silinsin mi?\')">Kalıcı sil</a>';
			} else {
				echo '<a class="tx-del" href="' . esc_url( self::url( 'pages', 0, array( 'pgrow' => 'trash', 'id' => $pid, '_wpnonce' => $rn, 'ret_status' => $status ) ) ) . '">Çöpe at</a>';
			}
			echo '</div></td>';
			echo '<td class="text-muted">' . esc_html( $author ? $author->display_name : '—' ) . '</td>';
			echo '<td><span class="tx-badge ' . ( $st === 'publish' ? 'tx-ok' : ( $st === 'pending' ? 'tx-warn' : ( $st === 'private' ? 'tx-info' : 'tx-muted' ) ) ) . '">' . esc_html( self::page_status_label( $st ) ) . '</span></td>';
			echo '<td class="text-muted tx-c-date">' . esc_html( get_the_date( 'd.m.Y', $pid ) ) . '</td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'page', $pid ) ) . '">Düzenle</a></td></tr>';
		}
		echo '</tbody></table></div></div></div></form>';

		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) { $a = array( 'paged' => $i ); if ( $status ) { $a['status'] = $status; } if ( $s ) { $a['s'] = $s; } echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'pages', 0, $a ) ) . '">' . $i . '</a>'; }
			echo '</div>';
		}
		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	protected static function page_status_label( $st ) {
		$map = array( 'publish' => 'Yayında', 'draft' => 'Taslak', 'pending' => 'Beklemede', 'private' => 'Özel', 'trash' => 'Çöp', 'future' => 'Zamanlanmış' );
		return isset( $map[ $st ] ) ? $map[ $st ] : $st;
	}

	protected static function view_page_form( $id ) {
		$pg = $id ? get_post( $id ) : null;
		if ( $id && ( ! $pg || $pg->post_type !== 'page' ) ) { echo '<div class="tx-empty">Sayfa bulunamadı. <a href="' . esc_url( self::url( 'pages' ) ) . '">← Sayfalara dön</a></div>'; return; }
		$is_new = ! $pg;
		$title   = $pg ? $pg->post_title : '';
		$content = $pg ? $pg->post_content : '';
		$excerpt = $pg ? $pg->post_excerpt : '';
		$slug    = $pg ? $pg->post_name : '';
		$pstatus = $pg ? $pg->post_status : 'draft';
		$parent  = $pg ? (int) $pg->post_parent : 0;
		$order   = $pg ? (int) $pg->menu_order : 0;
		$tpl     = $pg ? get_post_meta( $pg->ID, '_wp_page_template', true ) : 'default';
		$thumb   = $pg ? (int) get_post_thumbnail_id( $pg->ID ) : 0;
		$thumb_url = $thumb ? wp_get_attachment_image_url( $thumb, 'medium' ) : '';

		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Sayfa kaydedildi.</div>'; }
		echo '<div class="tx-detail-top mb-3"><a class="tx-back" href="' . esc_url( self::url( 'pages' ) ) . '"><i class="fas fa-arrow-left mr-1"></i>Sayfalar</a>';
		if ( $pg ) { echo '<a class="btn tx-btn" href="' . esc_url( get_permalink( $pg->ID ) ) . '" target="_blank"><i class="fas fa-up-right-from-square mr-1"></i>Görüntüle</a>'; }
		echo '</div>';

		echo '<form method="post" action="' . esc_url( self::url( 'page', $id ) ) . '">';
		wp_nonce_field( 'wcp_page_' . $id );
		echo '<input type="hidden" name="wcp_action" value="page_save"><input type="hidden" name="post_id" value="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" name="thumb_id" id="tx-page-thumb-id" value="' . esc_attr( $thumb ) . '">';
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-body">';
		echo '<label class="tx-label">Başlık</label><input type="text" class="form-control mb-3" name="title" value="' . esc_attr( $title ) . '" placeholder="Sayfa başlığı">';
		echo '<label class="tx-label">İçerik <small class="text-muted">(HTML / shortcode)</small></label><textarea class="form-control tx-code" name="content" rows="18">' . esc_textarea( $content ) . '</textarea>';
		echo '<div class="tx-set-hint">Görsel düzenleyici (Woodmart/WPBakery) için sayfayı kaydedip <a href="' . ( $pg ? esc_url( admin_url( 'post.php?post=' . $pg->ID . '&action=edit' ) ) : '#' ) . '" target="_blank">yapı düzenleyicide</a> açabilirsiniz.</div>';
		echo '<label class="tx-label mt-3">Özet</label><textarea class="form-control" name="excerpt" rows="2">' . esc_textarea( $excerpt ) . '</textarea>';
		echo '</div></div></div>';

		echo '<div class="col-lg-4">';
		// Yayın kutusu
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yayınla</h3></div><div class="card-body">';
		echo '<label class="tx-label">Durum</label><select class="form-control mb-2" name="status">';
		foreach ( array( 'publish' => 'Yayında', 'draft' => 'Taslak', 'pending' => 'Beklemede', 'private' => 'Özel' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $pstatus, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
		echo '</select>';
		echo '<label class="tx-label">Kısa ad (slug)</label><input type="text" class="form-control mb-2" name="slug" value="' . esc_attr( $slug ) . '" placeholder="otomatik">';
		echo '<label class="tx-label">Üst sayfa</label><select class="form-control mb-2" name="parent"><option value="0">(üst yok)</option>';
		$plist = get_pages( array( 'sort_column' => 'menu_order,post_title', 'exclude' => $id ? array( $id ) : array() ) );
		foreach ( $plist as $pp ) { echo '<option value="' . esc_attr( $pp->ID ) . '"' . selected( $parent, $pp->ID, false ) . '>' . esc_html( $pp->post_title ) . '</option>'; }
		echo '</select>';
		echo '<label class="tx-label">Şablon</label><select class="form-control mb-2" name="template">';
		$tpls = array( 'default' => 'Varsayılan şablon' ) + (array) wp_get_theme()->get_page_templates( $pg );
		foreach ( $tpls as $file => $name ) { echo '<option value="' . esc_attr( $file ) . '"' . selected( $tpl ? $tpl : 'default', $file, false ) . '>' . esc_html( $name ) . '</option>'; }
		echo '</select>';
		echo '<label class="tx-label">Sıra</label><input type="number" class="form-control" name="menu_order" value="' . esc_attr( $order ) . '">';
		echo '<div class="tx-setfoot"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Kaydet</button>';
		if ( $pg ) { $rn = wp_create_nonce( 'wcp_pgrow_' . $pg->ID ); echo ' <a class="btn tx-btn tx-btn-danger" href="' . esc_url( self::url( 'pages', 0, array( 'pgrow' => 'trash', 'id' => $pg->ID, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Çöpe atılsın mı?\')"><i class="fas fa-trash"></i></a>'; }
		echo '</div></div></div>';
		// Öne çıkan görsel
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Öne çıkan görsel</h3></div><div class="card-body">';
		echo '<img id="tx-page-img" src="' . esc_url( $thumb_url ) . '" alt="" style="max-width:100%;border-radius:8px;' . ( $thumb_url ? '' : 'display:none;' ) . '">';
		echo '<div class="mt-2"><button type="button" class="btn tx-btn" id="tx-page-select"><i class="fas fa-image mr-1"></i>Görsel seç</button> <a href="#" id="tx-page-remove" class="tx-del" style="' . ( $thumb_url ? '' : 'display:none;' ) . '">Kaldır</a></div>';
		echo '</div></div>';
		echo '</div></div></form>';
	}

	protected static function page_media_js() {
		?>
<script>
jQuery(function($){
	if(!document.getElementById('tx-page-select')){return;}
	$('#tx-page-select').on('click',function(e){ e.preventDefault();
		var f=wp.media({title:'Öne çıkan görsel seç', button:{text:'Seç'}, multiple:false, library:{type:'image'}});
		f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); var u=(a.sizes&&a.sizes.medium)?a.sizes.medium.url:a.url;
			$('#tx-page-img').attr('src',u).show(); $('#tx-page-thumb-id').val(a.id); $('#tx-page-remove').show();
		}); f.open();
	});
	$('#tx-page-remove').on('click',function(e){ e.preventDefault(); $('#tx-page-img').hide().attr('src',''); $('#tx-page-thumb-id').val(''); $(this).hide(); });
});
</script>
		<?php
	}

	/* ---------------- Kullanıcılar (Hesap Yönetimi) ---------------- */
	protected static function role_label( $slug ) {
		$tr = array( 'administrator' => 'Yönetici', 'shop_manager' => 'Mağaza yöneticisi', 'editor' => 'Editör', 'author' => 'Yazar', 'contributor' => 'Katkıda bulunan', 'subscriber' => 'Abone', 'customer' => 'Müşteri' );
		if ( isset( $tr[ $slug ] ) ) { return $tr[ $slug ]; }
		$names = wp_roles()->get_names();
		return isset( $names[ $slug ] ) ? translate_user_role( $names[ $slug ] ) : ucfirst( str_replace( array( '_', '-' ), ' ', $slug ) );
	}

	protected static function view_users() {
		if ( ! current_user_can( 'list_users' ) ) { echo '<div class="tx-empty">Kullanıcıları görüntüleme yetkiniz yok.</div>'; return; }
		$role  = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per   = 30;
		$args  = array( 'number' => $per, 'paged' => $paged, 'orderby' => 'registered', 'order' => 'DESC' );
		if ( $role ) { $args['role'] = $role; }
		if ( $s ) { $args['search'] = '*' . $s . '*'; $args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' ); }
		$uq    = new WP_User_Query( $args );
		$users = $uq->get_results();
		$total = (int) $uq->get_total();
		$max   = (int) ceil( $total / $per );
		$counts = count_users();
		$avail  = isset( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();

		if ( isset( $_GET['msg'] ) ) { $m = sanitize_key( $_GET['msg'] ); if ( $m === 'saved' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Kullanıcı kaydedildi.</div>'; } elseif ( $m === 'deleted' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Kullanıcı silindi.</div>'; } elseif ( $m === 'err' ) { echo '<div class="tx-flash-err mb-3"><i class="fas fa-circle-exclamation mr-2"></i>İşlem başarısız (yetki / e-posta / kullanıcı adı).</div>'; } }

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'users' ) ) . '">';
		if ( $role ) { echo '<input type="hidden" name="role" value="' . esc_attr( $role ) . '">'; }
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Ad / e-posta / kullanıcı adı ara">';
		echo '<button class="btn tx-btn" type="submit">Filtrele</button>';
		if ( current_user_can( 'create_users' ) ) { echo '<a class="btn tx-btn primary" href="' . esc_url( self::url( 'user' ) ) . '"><i class="fas fa-plus mr-1"></i> Yeni kullanıcı</a>'; }
		echo '</form>';

		echo '<div class="tx-chips mb-3">';
		echo '<a class="tx-chip' . ( ! $role ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'users' ) ) . '">Tümü (' . (int) ( isset( $counts['total_users'] ) ? $counts['total_users'] : 0 ) . ')</a>';
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$c = isset( $avail[ $slug ] ) ? (int) $avail[ $slug ] : 0;
			if ( ! $c ) { continue; }
			echo '<a class="tx-chip' . ( $role === $slug ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'users', 0, array( 'role' => $slug ) ) ) . '">' . esc_html( self::role_label( $slug ) ) . ' (' . $c . ')</a>';
		}
		echo '</div>';

		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th>Kullanıcı</th><th>E-posta</th><th>Rol</th><th>Kayıt</th><th></th></tr></thead><tbody>';
		if ( empty( $users ) ) { echo '<tr><td colspan="5"><div class="tx-empty">Kullanıcı bulunamadı.</div></td></tr>'; }
		$me = get_current_user_id();
		foreach ( $users as $u ) {
			$rl = ! empty( $u->roles ) ? self::role_label( $u->roles[0] ) : '—';
			$av = get_avatar_url( $u->ID, array( 'size' => 36 ) );
			$reg = $u->user_registered ? date_i18n( 'd.m.Y', strtotime( $u->user_registered ) ) : '—';
			echo '<tr><td><div class="tx-flexcell"><img class="tx-thumb sm" style="border-radius:50%" src="' . esc_url( $av ) . '" alt=""><div><a class="tx-strong" href="' . esc_url( self::url( 'user', $u->ID ) ) . '">' . esc_html( $u->display_name ) . '</a><div class="text-muted" style="font-size:11px">@' . esc_html( $u->user_login ) . '</div></div></div>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'user', $u->ID ) ) . '">Düzenle</a>';
			if ( current_user_can( 'delete_users' ) && $u->ID !== $me ) { $rn = wp_create_nonce( 'wcp_urow_' . $u->ID ); echo ' · <a class="tx-del" href="' . esc_url( self::url( 'users', 0, array( 'urow' => 'delete', 'id' => $u->ID, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Kullanıcı silinsin mi? İçerikleri size aktarılır.\')">Sil</a>'; }
			echo '</div></td>';
			echo '<td class="text-muted"><a class="tx-link" href="mailto:' . esc_attr( $u->user_email ) . '">' . esc_html( $u->user_email ) . '</a></td>';
			echo '<td><span class="tx-badge ' . ( in_array( 'administrator', (array) $u->roles, true ) ? 'tx-info' : 'tx-muted' ) . '">' . esc_html( $rl ) . '</span></td>';
			echo '<td class="text-muted tx-c-date">' . esc_html( $reg ) . '</td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'user', $u->ID ) ) . '">Düzenle</a></td></tr>';
		}
		echo '</tbody></table></div></div></div>';

		if ( $max > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $max, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) { $a = array( 'paged' => $i ); if ( $role ) { $a['role'] = $role; } if ( $s ) { $a['s'] = $s; } echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'users', 0, $a ) ) . '">' . $i . '</a>'; }
			echo '</div>';
		}
	}

	protected static function view_user_form( $id ) {
		$u = $id ? get_userdata( $id ) : null;
		if ( $id && ! $u ) { echo '<div class="tx-empty">Kullanıcı bulunamadı. <a href="' . esc_url( self::url( 'users' ) ) . '">← Kullanıcılara dön</a></div>'; return; }
		$is_new = ! $u;
		if ( $is_new ) {
			if ( ! current_user_can( 'create_users' ) ) { echo '<div class="tx-empty">Yeni kullanıcı ekleme yetkiniz yok.</div>'; return; }
		} elseif ( ! self::can_edit_target_user( $id ) ) {
			echo '<div class="tx-empty">Bu kullanıcıyı düzenleme yetkiniz yok. <a href="' . esc_url( self::url( 'users' ) ) . '">← Kullanıcılara dön</a></div>'; return;
		}
		$me = get_current_user_id();

		echo '<div class="tx-detail-top mb-3"><a class="tx-back" href="' . esc_url( self::url( 'users' ) ) . '"><i class="fas fa-arrow-left mr-1"></i>Kullanıcılar</a></div>';
		echo '<form method="post" action="' . esc_url( self::url( 'user', $id ) ) . '"><div class="row"><div class="col-lg-7">';
		wp_nonce_field( 'wcp_user_' . $id );
		echo '<input type="hidden" name="wcp_action" value="user_save"><input type="hidden" name="user_id" value="' . esc_attr( $id ) . '">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_new ? 'Yeni kullanıcı' : 'Kullanıcı bilgileri' ) . '</h3></div><div class="card-body"><div class="tx-setgrid">';
		if ( $is_new ) {
			echo '<div><label class="tx-label">Kullanıcı adı *</label><input type="text" class="form-control" name="user_login" required></div>';
			echo '<div><label class="tx-label">E-posta *</label><input type="email" class="form-control" name="email" required></div>';
		} else {
			echo '<div><label class="tx-label">Kullanıcı adı</label><input type="text" class="form-control" value="' . esc_attr( $u->user_login ) . '" disabled></div>';
			echo '<div><label class="tx-label">E-posta *</label><input type="email" class="form-control" name="email" value="' . esc_attr( $u->user_email ) . '" required></div>';
		}
		echo '<div><label class="tx-label">Ad</label><input type="text" class="form-control" name="first_name" value="' . esc_attr( $u ? $u->first_name : '' ) . '"></div>';
		echo '<div><label class="tx-label">Soyad</label><input type="text" class="form-control" name="last_name" value="' . esc_attr( $u ? $u->last_name : '' ) . '"></div>';
		if ( ! $is_new ) { echo '<div><label class="tx-label">Görünen ad</label><input type="text" class="form-control" name="display_name" value="' . esc_attr( $u->display_name ) . '"></div>'; }
		echo '<div><label class="tx-label">Web sitesi</label><input type="url" class="form-control" name="url" value="' . esc_attr( $u ? $u->user_url : '' ) . '"></div>';
		echo '</div>';
		echo '<label class="tx-label mt-3">Biyografi</label><textarea class="form-control" name="description" rows="3">' . esc_textarea( $u ? get_user_meta( $u->ID, 'description', true ) : '' ) . '</textarea>';
		echo '</div></div>';

		echo '</div><div class="col-lg-5">';
		// Rol & şifre kutusu
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Rol & erişim</h3></div><div class="card-body">';
		$cur_role = $u && ! empty( $u->roles ) ? $u->roles[0] : 'subscriber';
		$can_role = ( $u ? current_user_can( 'promote_user', $u->ID ) : current_user_can( 'create_users' ) ) && ( ! $u || $u->ID !== $me );
		$assignable = self::assignable_roles();
		echo '<label class="tx-label">Rol</label><select class="form-control mb-2" name="role"' . ( $can_role ? '' : ' disabled' ) . '>';
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			if ( ! in_array( $slug, $assignable, true ) && $slug !== $cur_role ) { continue; } // atayamayacağı rolleri gösterme
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cur_role, $slug, false ) . '>' . esc_html( self::role_label( $slug ) ) . '</option>';
		}
		echo '</select>';
		if ( ! $can_role && $u && $u->ID === $me ) { echo '<div class="tx-set-hint">Kendi rolünüzü buradan değiştiremezsiniz.</div>'; }
		echo '<label class="tx-label mt-2">' . ( $is_new ? 'Şifre *' : 'Yeni şifre' ) . '</label><input type="text" class="form-control" name="pass" autocomplete="new-password"' . ( $is_new ? ' required' : ' placeholder="(değiştirmek için doldurun)"' ) . '>';
		echo '<div class="tx-set-hint">' . ( $is_new ? 'Kullanıcı bu şifreyle giriş yapacak.' : 'Boş bırakırsanız şifre değişmez.' ) . '</div>';
		echo '<div class="tx-setfoot"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Kaydet</button>';
		if ( $u && current_user_can( 'delete_users' ) && $u->ID !== $me ) { $rn = wp_create_nonce( 'wcp_urow_' . $u->ID ); echo ' <a class="btn tx-btn tx-btn-danger" href="' . esc_url( self::url( 'users', 0, array( 'urow' => 'delete', 'id' => $u->ID, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Kullanıcı silinsin mi?\')"><i class="fas fa-trash"></i></a>'; }
		echo '</div></div></div>';
		if ( $u ) {
			echo '<div class="card card-outline tx-card"><div class="card-body"><div class="tx-media-info">';
			echo '<div><span>ID</span><b>#' . (int) $u->ID . '</b></div>';
			echo '<div><span>Kayıt</span><b>' . esc_html( $u->user_registered ? date_i18n( 'd.m.Y', strtotime( $u->user_registered ) ) : '—' ) . '</b></div>';
			if ( class_exists( 'WooCommerce' ) ) { echo '<div><span>Sipariş</span><b>' . (int) wc_get_customer_order_count( $u->ID ) . '</b></div>'; echo '<div><span>Harcama</span><b>' . wp_kses_post( self::money( wc_get_customer_total_spent( $u->ID ) ) ) . '</b></div>'; }
			echo '</div></div></div>';
		}
		echo '</div></div></form>';
	}

	/* ---------------- Promosyon (hediye yönetimi) ---------------- */
	protected static function view_promosyon() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { echo '<div class="tx-empty">Yetkiniz yok.</div>'; return; }
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$d = array( 'enabled' => 1, 'per_product' => 1, 'by_category' => 0, 'by_threshold' => 0, 'cat_map' => array(), 'threshold_amount' => 0, 'threshold_gift' => 0, 'title' => 'STORE ÖZEL HEDİYE' );
		$s = get_option( 'wcp_gift_settings', array() );
		$s = wp_parse_args( is_array( $s ) ? $s : array(), $d );
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Promosyon ayarları kaydedildi.</div>'; }
		echo '<div class="tx-rep-period mb-3"><i class="fas fa-gift mr-1"></i>Ürün alınınca <b>ücretsiz hediye</b> verme kurallarını buradan yönetin — ürün sayfasında "' . esc_html( $s['title'] ) . '" bloğunda gösterilir, sepete <b>0 TL</b> olarak otomatik eklenir. Yazınca öneriler çıkar, tıklayıp ekleyin; sonunda <b>Kaydet</b>e basın.</div>';
		echo '<form id="wcp-promo-form" method="post" action="' . esc_url( self::url( 'promosyon' ) ) . '">';
		wp_nonce_field( 'wcp_promosyon' );
		echo '<input type="hidden" name="wcp_action" value="promosyon_save">';

		// Genel
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Genel</h3></div><div class="card-body">';
		echo '<label class="tx-switchrow"><input type="checkbox" name="enabled" value="1"' . checked( $s['enabled'], 1, false ) . '> <span><b>Hediye sistemi aktif</b><small>Kapatırsanız hiçbir üründe hediye eklenmez/gösterilmez</small></span></label>';
		echo '<div class="tx-setgrid mt-2"><div><label class="tx-label">Blok başlığı</label><input type="text" class="form-control" name="title" value="' . esc_attr( $s['title'] ) . '"></div></div>';
		echo '<label class="tx-label mt-3" style="margin-bottom:8px">Hediye verme yöntemleri (senaryolar)</label>';
		echo '<label class="tx-switchrow"><input type="checkbox" name="per_product" value="1"' . checked( $s['per_product'], 1, false ) . '> <span><b>Ürün başına</b><small>Her ürünün editöründe "Hediye ürün" seçilir</small></span></label>';
		echo '<label class="tx-switchrow mt-2"><input type="checkbox" name="by_category" value="1"' . checked( $s['by_category'], 1, false ) . '> <span><b>Kategoriye göre</b><small>Aşağıdaki kategori kurallarına göre</small></span></label>';
		echo '<label class="tx-switchrow mt-2"><input type="checkbox" name="by_threshold" value="1"' . checked( $s['by_threshold'], 1, false ) . '> <span><b>Tutar eşiğine göre</b><small>Sepet tutarı belirlenen eşiği aşınca</small></span></label>';
		echo '</div></div>';

		// Tutar eşiği
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Tutar eşiği kuralı</h3></div><div class="card-body">';
		echo '<div class="tx-setgrid"><div><label class="tx-label">Sepet tutarı eşiği (₺)</label><input type="number" step="0.01" class="form-control" name="threshold_amount" value="' . esc_attr( $s['threshold_amount'] ) . '" placeholder="örn. 50000"></div></div>';
		echo '<label class="tx-label mt-3">Bu tutar aşılınca verilecek hediye</label>';
		self::product_picker( 'promo_threshold', 'threshold_gift', '', '', $s['threshold_gift'] ? array( (int) $s['threshold_gift'] ) : array() );
		echo '</div></div>';

		// Kategori kuralları
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Kategori kuralları</h3></div><div class="card-body">';
		echo '<p class="text-muted" style="font-size:12px;margin:0 0 14px">Her kategori için hediye ürün(ler) seçin. Boş bırakılan kategorilerde kural yoktur.</p>';
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				if ( (int) $t->count < 1 ) { continue; }
				$cur = isset( $s['cat_map'][ $t->term_id ] ) ? array_map( 'intval', (array) $s['cat_map'][ $t->term_id ] ) : array();
				echo '<div class="wcp-promo-catrow"><div class="wcp-promo-catname">' . esc_html( $t->name ) . ' <small>(' . (int) $t->count . ')</small></div><div class="wcp-promo-catpick">';
				self::product_picker( 'promo_cat_' . $t->term_id, 'catgift[' . $t->term_id . ']', '', '', $cur );
				echo '</div></div>';
			}
		}
		echo '</div></div>';

		echo '<div class="tx-setfoot" style="margin-bottom:26px;padding:0"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Promosyon ayarlarını kaydet</button></div>';
		echo '</form>';
		self::promosyon_js();
	}

	protected static function promosyon_js() {
		?>
<script>
(function(){
	var AJAX=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, NONCE=<?php echo wp_json_encode( wp_create_nonce( 'wcp_prod_img' ) ); ?>;
	var form=document.getElementById('wcp-promo-form'); if(!form){ return; }
	Array.prototype.forEach.call(form.querySelectorAll('.tx-picker'),function(box){
		var input=box.querySelector('.tx-pick-input'), dd=box.querySelector('.tx-pick-dd'), chips=box.querySelector('.tx-chips-sel'), hidden=box.querySelector('.tx-pick-hidden'), t;
		function ids(){ return Array.prototype.map.call(chips.querySelectorAll('.tx-chip-sel'),function(c){return c.getAttribute('data-id');}); }
		function sync(){ hidden.value=ids().join(','); }
		function addChip(it){ if(ids().indexOf(String(it.id))>-1){ return; } var c=document.createElement('span'); c.className='tx-chip-sel'; c.setAttribute('data-id',it.id); c.innerHTML='<img alt=""><span></span><a href="#" class="tx-chip-x" title="Kaldır">×</a>'; c.querySelector('img').src=it.thumb; c.querySelector('span').textContent=it.text; chips.appendChild(c); sync(); }
		chips.addEventListener('click',function(e){ if(e.target.classList.contains('tx-chip-x')){ e.preventDefault(); var ch=e.target.closest('.tx-chip-sel'); if(ch){ ch.remove(); sync(); } } });
		if(!input){ return; }
		input.addEventListener('input',function(){ clearTimeout(t); var q=input.value.trim(); if(q.length<2){ dd.style.display='none'; dd.innerHTML=''; return; } t=setTimeout(function(){
			fetch(AJAX+'?action=wcp_prod_search&_ajax_nonce='+NONCE+'&q='+encodeURIComponent(q)+'&exclude='+ids().join(','),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){
				dd.innerHTML='';
				if(r&&r.success&&r.data.length){ r.data.forEach(function(it){ var row=document.createElement('div'); row.className='tx-pick-opt'; var im=document.createElement('img'); im.src=it.thumb; var sp=document.createElement('span'); sp.textContent=it.text; row.appendChild(im); row.appendChild(sp); if(it.sku){ var sm=document.createElement('small'); sm.textContent=it.sku; row.appendChild(sm); } if(it.price){ var bb=document.createElement('b'); bb.innerHTML=it.price; row.appendChild(bb); } row.addEventListener('click',function(){ addChip(it); input.value=''; dd.style.display='none'; dd.innerHTML=''; }); dd.appendChild(row); }); dd.style.display='block'; }
				else { dd.innerHTML='<div class="tx-pick-empty">Sonuç yok</div>'; dd.style.display='block'; }
			}).catch(function(){});
		},250); });
	});
	document.addEventListener('click',function(e){ if(!e.target.closest('.tx-picker')){ Array.prototype.forEach.call(form.querySelectorAll('.tx-pick-dd'),function(d){ d.style.display='none'; }); } });
})();
</script>
		<?php
	}

	/* ---------------- Yetkilendirme (rol bazlı menü erişimi) ---------------- */
	protected static function panel_sections() {
		return array(
			'dashboard'  => array( 'label' => 'Genel Bakış', 'views' => array( 'dashboard', '' ) ),
			'orders'     => array( 'label' => 'Siparişler', 'views' => array( 'orders', 'order' ) ),
			'products'   => array( 'label' => 'Ürünler', 'views' => array( 'products', 'product' ) ),
			'categories' => array( 'label' => 'Kategoriler', 'views' => array( 'categories', 'category' ) ),
			'tags'       => array( 'label' => 'Ürün Etiketleri', 'views' => array( 'tags', 'tag' ) ),
			'attributes' => array( 'label' => 'Ürün Nitelikleri', 'views' => array( 'attributes', 'attribute' ) ),
			'brands'     => array( 'label' => 'Markalar', 'views' => array( 'brands', 'brand' ) ),
			'toplu-urun' => array( 'label' => 'Toplu Ürün Düzenleme', 'views' => array( 'toplu-urun' ) ),
			'aktar'      => array( 'label' => 'Dışa / İçe Aktar', 'views' => array( 'aktar' ) ),
			'talepler'   => array( 'label' => 'İptal / İade Talepleri', 'views' => array( 'talepler' ) ),
			'entegrasyonlar' => array( 'label' => 'Entegrasyonlar', 'views' => array( 'entegrasyonlar' ) ),
			'coupons'    => array( 'label' => 'Kuponlar', 'views' => array( 'coupons', 'coupon' ) ),
			'promosyon'  => array( 'label' => 'Promosyon', 'views' => array( 'promosyon' ) ),
			'customers'  => array( 'label' => 'Müşteriler', 'views' => array( 'customers', 'customer' ) ),
			'reviews'    => array( 'label' => 'Değerlendirmeler', 'views' => array( 'reviews', 'review' ) ),
			'stock'      => array( 'label' => 'Stok Yönetimi', 'views' => array( 'stock' ) ),
			'pages'      => array( 'label' => 'Sayfalar', 'views' => array( 'pages', 'page' ) ),
			'media'      => array( 'label' => 'Medya', 'views' => array( 'media', 'media-item' ) ),
			'reports'    => array( 'label' => 'Raporlar', 'views' => array( 'reports' ) ),
			'kaynak-analiz' => array( 'label' => 'Reklam & Kaynak Analizi', 'views' => array( 'kaynak-analiz' ) ),
			'satis-analiz'  => array( 'label' => 'Satış Analizi', 'views' => array( 'satis-analiz' ) ),
			'urun-analiz'   => array( 'label' => 'Ürün Analizi', 'views' => array( 'urun-analiz' ) ),
			'musteri-analiz'=> array( 'label' => 'Müşteri Analizi', 'views' => array( 'musteri-analiz' ) ),
			'cografya-analiz'=> array( 'label' => 'Coğrafya Analizi', 'views' => array( 'cografya-analiz' ) ),
			'kupon-analiz'  => array( 'label' => 'Kupon & İndirim Analizi', 'views' => array( 'kupon-analiz' ) ),
			'iade-analiz'   => array( 'label' => 'İade Raporu', 'views' => array( 'iade-analiz' ) ),
			'vergi-analiz'  => array( 'label' => 'Vergi Raporu', 'views' => array( 'vergi-analiz' ) ),
			'users'      => array( 'label' => 'Kullanıcılar', 'views' => array( 'users', 'user' ) ),
			'settings'   => array( 'label' => 'Ayarlar', 'views' => array( 'settings' ) ),
			'shipping'   => array( 'label' => 'Kargo Bölgeleri', 'views' => array( 'shipping' ) ),
			'tax'        => array( 'label' => 'Vergi Oranları', 'views' => array( 'tax' ) ),
			'wc-ayarlar' => array( 'label' => 'Mağaza Ayarları', 'views' => array( 'wc-ayarlar' ) ),
			'wc-durum'   => array( 'label' => 'Mağaza Durumu', 'views' => array( 'wc-durum' ) ),
			'wp'         => array( 'label' => 'Tüm Yönetim (wp-admin)', 'views' => array( 'wp' ) ),
		);
	}

	protected static function view_section( $view ) {
		if ( $view === 'perms' ) { return 'perms'; }
		foreach ( self::panel_sections() as $key => $info ) { if ( in_array( $view, $info['views'], true ) ) { return $key; } }
		return 'dashboard';
	}

	protected static function access_map() {
		$m = get_option( 'wcp_panel_access', array() );
		return is_array( $m ) ? $m : array();
	}

	/* Geçerli kullanıcı bu bölümü görebilir mi? (Yönetici her zaman tam erişim) */
	protected static function section_allowed( $section ) {
		if ( $section === '' || $section === 'dashboard' ) { return true; }
		// Yetkilendirme + Tüm Yönetim (eklenti/tema/wp-admin) = YALNIZCA yönetici
		if ( $section === 'perms' || $section === 'wp' ) { return current_user_can( 'manage_options' ); }
		if ( current_user_can( 'manage_options' ) ) { return true; }
		$map = self::access_map();
		$u = wp_get_current_user();
		$configured = false; $allowed = false;
		foreach ( (array) $u->roles as $r ) {
			if ( ! isset( $map[ $r ] ) ) { continue; } // bu rol yapılandırılmamış → atla (diğer rolleri kontrol et)
			$configured = true;
			if ( in_array( $section, (array) $map[ $r ], true ) ) { $allowed = true; }
		}
		return $configured ? $allowed : true; // hiçbir rol yapılandırılmadıysa geriye dönük tam erişim
	}

	/* Geçerli kullanıcının ATAYABİLECEĞİ roller. Yönetici hepsini; diğerleri yalnızca yetkisiz rolleri
	   (customer/subscriber gibi) — admin/shop_manager gibi ayrıcalıklı rollere yükseltme ENGELLENİR. */
	protected static function assignable_roles() {
		$all = wp_roles()->roles;
		if ( current_user_can( 'manage_options' ) ) { return array_keys( $all ); }
		$safe = array();
		foreach ( $all as $slug => $r ) {
			$caps = isset( $r['capabilities'] ) ? (array) $r['capabilities'] : array();
			$priv = ! empty( $caps['manage_options'] ) || ! empty( $caps['edit_users'] ) || ! empty( $caps['promote_users'] )
				|| ! empty( $caps['manage_woocommerce'] ) || ! empty( $caps['edit_others_posts'] ) || ! empty( $caps['edit_others_pages'] );
			if ( ! $priv ) { $safe[] = $slug; }
		}
		return $safe;
	}

	/* Hedef kullanıcıyı düzenleyebilir mi? WC meta-cap'i kullanır (shop_manager YALNIZCA müşterileri yönetebilir). */
	protected static function can_edit_target_user( $uid ) {
		$uid = (int) $uid;
		if ( ! $uid ) { return false; }
		if ( $uid === get_current_user_id() ) { return true; }
		return current_user_can( 'edit_user', $uid );
	}

	/* Geçerli isteğin (POST wcp_action / GET satır eylemi) ait olduğu panel bölümü — yoksa '' döner.
	   handle_actions başında Yetkilendirme matrisini aksiyon düzeyinde uygulamak için kullanılır. */
	protected static function request_section() {
		$act = isset( $_POST['wcp_action'] ) ? sanitize_key( wp_unslash( $_POST['wcp_action'] ) ) : '';
		$pm  = array(
			'product_save' => 'products', 'products_bulk' => 'products',
			'category_save' => 'categories',
			'coupon_save' => 'coupons', 'coupons_bulk' => 'coupons',
			'customer_save' => 'customers',
			'order_save' => 'orders', 'order_status' => 'orders', 'order_note' => 'orders', 'order_refund' => 'orders',
			'orders_bulk' => 'orders', 'order_action' => 'orders', 'order_add_fee' => 'orders', 'order_add_item' => 'orders', 'order_add_shipping' => 'orders',
			'page_save' => 'pages', 'pages_bulk' => 'pages',
			'promosyon_save' => 'promosyon',
			'review_save' => 'reviews', 'reviews_bulk' => 'reviews', 'review_reply' => 'reviews',
			'settings_save' => 'settings', 'perms_save' => 'perms', 'user_save' => 'users', 'report_email' => 'reports',
		);
		if ( $act && isset( $pm[ $act ] ) ) { return $pm[ $act ]; }
		$gm = array( 'row' => 'orders', 'prow' => 'products', 'pgrow' => 'pages', 'urow' => 'users', 'crow' => 'categories', 'rvrow' => 'reviews', 'cuprow' => 'coupons' );
		foreach ( $gm as $k => $sec ) { if ( ! empty( $_GET[ $k ] ) ) { return $sec; } }
		return '';
	}

	/* Panele girebilen roller (manage_woocommerce / manage_options yetkili) */
	protected static function panel_roles() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $r ) {
			$caps = isset( $r['capabilities'] ) ? $r['capabilities'] : array();
			if ( ! empty( $caps['manage_woocommerce'] ) || ! empty( $caps['manage_options'] ) ) { $out[ $slug ] = $r['name']; }
		}
		return $out;
	}

	protected static function view_perms() {
		if ( ! current_user_can( 'manage_options' ) ) { echo '<div class="tx-empty">Bu sayfa için yetkiniz yok.</div>'; return; }
		$secs  = self::panel_sections();
		$map   = self::access_map();
		$proles = self::panel_roles();
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Yetkilendirme kaydedildi.</div>'; }
		echo '<div class="tx-rep-period mb-3"><i class="fas fa-circle-info mr-1"></i>Her rolün panelde hangi menüleri göreceğini buradan belirleyin. <b>Yönetici</b> her zaman tam erişime sahiptir. Panele yalnızca <b>mağaza yönetimi</b> yetkili roller girebilir.</div>';
		echo '<form method="post" action="' . esc_url( self::url( 'perms' ) ) . '">';
		wp_nonce_field( 'wcp_perms' );
		echo '<input type="hidden" name="wcp_action" value="perms_save">';
		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-permtable mb-0"><thead><tr><th>Menü / Bölüm</th>';
		foreach ( $proles as $slug => $name ) { echo '<th class="text-center">' . esc_html( self::role_label( $slug ) ) . ( $slug === 'administrator' ? ' <i class="fas fa-lock" style="font-size:10px"></i>' : '' ) . '</th>'; }
		echo '</tr></thead><tbody>';
		foreach ( $secs as $key => $info ) {
			$admin_only = ( $key === 'wp' ); // eklenti/tema/wp-admin = yalnızca yönetici
			echo '<tr><td class="tx-strong">' . esc_html( $info['label'] ) . ( $admin_only ? ' <span class="tx-badge tx-info">yalnızca yönetici</span>' : '' ) . '</td>';
			foreach ( $proles as $slug => $name ) {
				$is_admin = ( $slug === 'administrator' );
				$locked   = $is_admin || $key === 'dashboard' || $admin_only;
				$checked  = $is_admin ? true : ( $key === 'dashboard' ? true : ( $admin_only ? false : ( ! isset( $map[ $slug ] ) ? true : in_array( $key, (array) $map[ $slug ], true ) ) ) );
				echo '<td class="text-center"><input type="checkbox" name="perm[' . esc_attr( $slug ) . '][]" value="' . esc_attr( $key ) . '"' . checked( $checked, true, false ) . ( $locked ? ' disabled' : '' ) . '></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<div class="tx-setfoot" style="padding:14px 16px"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Yetkileri kaydet</button></div>';
		echo '</div></div></form>';
	}

	/* ---------------- Ayarlar ---------------- */
	protected static function set_text( $name, $label, $value, $type = 'text', $hint = '' ) {
		echo '<div><label class="tx-label">' . esc_html( $label ) . '</label><input type="' . esc_attr( $type ) . '" class="form-control" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $type === 'number' ? ' min="0"' : '' ) . '>';
		if ( $hint ) { echo '<div class="tx-set-hint">' . esc_html( $hint ) . '</div>'; }
		echo '</div>';
	}

	protected static function view_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { echo '<div class="tx-empty">Bu sayfa için yetkiniz yok.</div>'; return; }
		$wc = class_exists( 'WooCommerce' );
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) { echo '<div class="tx-flash-ok mb-3"><i class="fas fa-circle-check mr-2"></i>Ayarlar kaydedildi.</div>'; }

		// ===== Mağaza bilgileri =====
		$dc = $wc ? get_option( 'woocommerce_default_country', 'TR' ) : 'TR';
		$cc = $dc; $st = '';
		if ( strpos( (string) $dc, ':' ) !== false ) { list( $cc, $st ) = explode( ':', $dc, 2 ); }
		echo '<form method="post" action="' . esc_url( self::url( 'settings' ) ) . '" class="card card-outline tx-card tx-setcard">';
		wp_nonce_field( 'wcp_settings' );
		echo '<input type="hidden" name="wcp_action" value="settings_save"><input type="hidden" name="section" value="store">';
		echo '<div class="card-header"><h3 class="card-title"><i class="fas fa-store mr-2"></i>Mağaza bilgileri</h3></div><div class="card-body"><div class="tx-setgrid">';
		self::set_text( 'blogname', 'Site başlığı', get_option( 'blogname' ) );
		self::set_text( 'blogdescription', 'Slogan', get_option( 'blogdescription' ) );
		self::set_text( 'admin_email', 'Yönetici e-postası', get_option( 'admin_email' ), 'email' );
		self::set_text( 'store_address', 'Adres', $wc ? get_option( 'woocommerce_store_address' ) : '' );
		self::set_text( 'store_address_2', 'Adres (2. satır)', $wc ? get_option( 'woocommerce_store_address_2' ) : '' );
		self::set_text( 'store_city', 'Şehir', $wc ? get_option( 'woocommerce_store_city' ) : '' );
		self::set_text( 'store_postcode', 'Posta kodu', $wc ? get_option( 'woocommerce_store_postcode' ) : '' );
		echo '<div><label class="tx-label">Ülke</label><select class="form-control" name="store_country">';
		if ( $wc && function_exists( 'WC' ) ) { foreach ( WC()->countries->get_countries() as $code => $cname ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( $cc, $code, false ) . '>' . esc_html( $cname ) . '</option>'; } }
		echo '</select></div>';
		self::set_text( 'store_state', 'İl / Bölge kodu', $st, 'text', 'Örn. İstanbul için 34' );
		echo '</div><div class="tx-setfoot"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Kaydet</button></div></div></form>';

		// ===== Para birimi & fiyat biçimi =====
		echo '<form method="post" action="' . esc_url( self::url( 'settings' ) ) . '" class="card card-outline tx-card tx-setcard">';
		wp_nonce_field( 'wcp_settings' );
		echo '<input type="hidden" name="wcp_action" value="settings_save"><input type="hidden" name="section" value="currency">';
		echo '<div class="card-header"><h3 class="card-title"><i class="fas fa-lira-sign mr-2"></i>Para birimi & fiyat biçimi</h3></div><div class="card-body"><div class="tx-setgrid">';
		$cur = $wc ? get_option( 'woocommerce_currency', 'TRY' ) : 'TRY';
		echo '<div><label class="tx-label">Para birimi</label><select class="form-control" name="currency">';
		if ( function_exists( 'get_woocommerce_currencies' ) ) { foreach ( get_woocommerce_currencies() as $code => $cname ) { $sym = html_entity_decode( get_woocommerce_currency_symbol( $code ) ); echo '<option value="' . esc_attr( $code ) . '"' . selected( $cur, $code, false ) . '>' . esc_html( $cname . ' (' . $sym . ')' ) . '</option>'; } }
		echo '</select></div>';
		$pos = $wc ? get_option( 'woocommerce_currency_pos', 'left' ) : 'left';
		echo '<div><label class="tx-label">Sembol konumu</label><select class="form-control" name="currency_pos">';
		foreach ( array( 'left' => 'Sol (₺99)', 'right' => 'Sağ (99₺)', 'left_space' => 'Sol boşluklu (₺ 99)', 'right_space' => 'Sağ boşluklu (99 ₺)' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $pos, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
		echo '</select></div>';
		self::set_text( 'thousand_sep', 'Binlik ayıracı', $wc ? get_option( 'woocommerce_price_thousand_sep' ) : '.' );
		self::set_text( 'decimal_sep', 'Ondalık ayıracı', $wc ? get_option( 'woocommerce_price_decimal_sep' ) : ',' );
		self::set_text( 'num_decimals', 'Ondalık basamak sayısı', $wc ? get_option( 'woocommerce_price_num_decimals', 2 ) : 2, 'number' );
		echo '</div><div class="tx-setfoot"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Kaydet</button></div></div></form>';

		// ===== Stok ayarları =====
		if ( $wc ) {
			$ms   = get_option( 'woocommerce_manage_stock', 'yes' ) === 'yes';
			$hide = get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) === 'yes';
			$sf   = get_option( 'woocommerce_stock_format', '' );
			echo '<form method="post" action="' . esc_url( self::url( 'settings' ) ) . '" class="card card-outline tx-card tx-setcard">';
			wp_nonce_field( 'wcp_settings' );
			echo '<input type="hidden" name="wcp_action" value="settings_save"><input type="hidden" name="section" value="stock">';
			echo '<div class="card-header"><h3 class="card-title"><i class="fas fa-warehouse mr-2"></i>Stok ayarları</h3></div><div class="card-body">';
			echo '<label class="tx-switchrow"><input type="checkbox" name="manage_stock" value="1"' . checked( $ms, true, false ) . '> <span><b>Stok yönetimini etkinleştir</b><small>Ürün stok adetlerini Mağaza takip etsin</small></span></label>';
			echo '<div class="tx-setgrid mt-2">';
			self::set_text( 'low_stock_amount', 'Düşük stok eşiği', get_option( 'woocommerce_notify_low_stock_amount', 2 ), 'number', 'Bu adedin altına düşünce bildirim' );
			self::set_text( 'no_stock_amount', 'Tükendi eşiği', get_option( 'woocommerce_notify_no_stock_amount', 0 ), 'number', 'Bu adette/altında tükendi sayılır' );
			echo '<div><label class="tx-label">Stok gösterim biçimi</label><select class="form-control" name="stock_format">';
			foreach ( array( '' => 'Her zaman stok adedini göster', 'low_amount' => 'Yalnızca stok azaldığında göster', 'no_amount' => 'Stok adedini hiç gösterme' ) as $k => $lbl ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $sf, $k, false ) . '>' . esc_html( $lbl ) . '</option>'; }
			echo '</select></div>';
			echo '</div>';
			echo '<label class="tx-switchrow mt-2"><input type="checkbox" name="hide_oos" value="1"' . checked( $hide, true, false ) . '> <span><b>Tükenen ürünleri katalogda gizle</b><small>Stokta olmayan ürünler mağazada görünmez</small></span></label>';
			echo '<div class="tx-setfoot"><button class="btn tx-btn primary" type="submit"><i class="fas fa-floppy-disk mr-1"></i>Kaydet</button></div></div></form>';
		}

		// ===== Sistem bilgisi (salt okunur) =====
		global $wp_version;
		$theme = wp_get_theme();
		$rows = array(
			'WordPress sürümü' => $wp_version,
			'Mağaza sürümü' => ( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ),
			'PHP sürümü' => phpversion(),
			'Aktif tema' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'Bellek limiti' => ini_get( 'memory_limit' ),
			'Maks. yükleme boyutu' => size_format( wp_max_upload_size() ),
			'Sunucu' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '—',
			'Site adresi' => home_url( '/' ),
		);
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-circle-info mr-2"></i>Sistem bilgisi</h3></div><div class="card-body"><div class="tx-media-info tx-sysinfo">';
		foreach ( $rows as $k => $v ) { echo '<div><span>' . esc_html( $k ) . '</span><b>' . esc_html( $v ) . '</b></div>'; }
		echo '</div></div></div>';
	}

	/* Hiyerarşik taxonomy checkbox listesi (kategori/marka — alt kategori girintili) */
	protected static function render_term_checks( $tax, $field, $checked, $parent = 0, $depth = 0 ) {
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'parent' => $parent, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || ! $terms ) { return; }
		foreach ( $terms as $t ) {
			echo '<label class="tx-catitem" style="padding-left:' . ( $depth * 16 ) . 'px">' . ( $depth ? '<span class="tx-subdash">└</span> ' : '' ) . '<input type="checkbox" name="' . esc_attr( $field ) . '" value="' . esc_attr( $t->term_id ) . '"' . checked( in_array( $t->term_id, $checked ), true, false ) . '> ' . esc_html( $t->name ) . '</label>';
			self::render_term_checks( $tax, $field, $checked, $t->term_id, $depth + 1 );
		}
	}

	protected static function view_product_form( $id ) {
		$p = $id && class_exists( 'WooCommerce' ) ? wc_get_product( $id ) : false;
		$is_new = ! $p;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'products' ) ) . '">← Ürünlere dön</a>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="alert tx-flash">Ürün kaydedildi.</div>'; }
		$val = function ( $m, $d = '' ) use ( $p ) { return $p ? $p->$m() : $d; };
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( self::url( 'product', $id ? $id : 0 ) ) . '">';
		wp_nonce_field( 'wcp_product_' . ( $id ? $id : 0 ) );
		echo '<input type="hidden" name="wcp_action" value="product_save"><input type="hidden" name="product_id" value="' . esc_attr( $id ) . '">';
		echo '<div class="row"><div class="col-lg-8">';

		// Genel bilgi
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_new ? 'Yeni ürün' : 'Ürünü düzenle' ) . '</h3></div><div class="card-body">';
		echo '<label class="tx-label">Ürün adı</label><input class="form-control mb-3" type="text" data-wcpsave="title" name="title" value="' . esc_attr( $val( 'get_name' ) ) . '" required>';
		echo '<label class="tx-label">Uzun açıklama</label><textarea class="form-control mb-3" data-wcpsave="description" name="description" rows="6">' . esc_textarea( $p ? $p->get_description() : '' ) . '</textarea>';
		echo '<label class="tx-label">Kısa açıklama</label><textarea class="form-control" data-wcpsave="short_description" name="short_description" rows="3">' . esc_textarea( $p ? $p->get_short_description() : '' ) . '</textarea>';
		echo '</div></div>';

		// ===== ÜRÜN VERİSİ (sekmeli) =====
		echo '<div class="card card-outline tx-card tx-pdata"><div class="card-header"><h3 class="card-title">Ürün verisi</h3></div>';
		echo '<ul class="nav nav-tabs tx-tabs" role="tablist">';
		$atab = ( isset( $_GET['attrs'] ) && $_GET['attrs'] === 'fetch' ) ? 'nitelikler' : 'genel';
		$ptabs = array( 'genel' => 'Genel', 'envanter' => 'Envanter', 'gonderim' => 'Gönderim', 'baglantili' => 'Bağlantılı ürünler', 'nitelikler' => 'Nitelikler', 'gelismis' => 'Gelişmiş' );
		foreach ( $ptabs as $k => $lbl ) { echo '<li class="nav-item"><a class="nav-link' . ( $k === $atab ? ' active' : '' ) . '" data-toggle="tab" href="#tab-' . $k . '">' . esc_html( $lbl ) . '</a></li>'; }
		echo '</ul><div class="card-body"><div class="tab-content">';

		// Genel — fiyatlar ham metadan okunur (dinamik fiyatlandırma eklentisi get_regular_price()'ı boşaltabiliyor)
		$reg_raw  = $p ? get_post_meta( $p->get_id(), '_regular_price', true ) : '';
		$sale_raw = $p ? get_post_meta( $p->get_id(), '_sale_price', true ) : '';
		echo '<div class="tab-pane fade' . ( $atab === 'genel' ? ' show active' : '' ) . '" id="tab-genel"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">Normal fiyat (TL)</label><input class="form-control" data-wcpsave="regular_price" name="regular_price" value="' . esc_attr( $reg_raw ) . '"></div>';
		echo '<div><label class="tx-label">İndirimli fiyat (TL)</label><input class="form-control" data-wcpsave="sale_price" name="sale_price" value="' . esc_attr( $sale_raw ) . '"></div>';
		$sf = $p && $p->get_date_on_sale_from() ? $p->get_date_on_sale_from()->date( 'Y-m-d' ) : '';
		$sto = $p && $p->get_date_on_sale_to() ? $p->get_date_on_sale_to()->date( 'Y-m-d' ) : '';
		echo '<div><label class="tx-label">İndirim başlangıç</label><input type="date" class="form-control" data-wcpsave="sale_from" name="sale_from" value="' . esc_attr( $sf ) . '"></div>';
		echo '<div><label class="tx-label">İndirim bitiş</label><input type="date" class="form-control" data-wcpsave="sale_to" name="sale_to" value="' . esc_attr( $sto ) . '"></div>';
		$txs = $p ? $p->get_tax_status() : 'taxable';
		echo '<div><label class="tx-label">Vergi durumu</label><select class="form-control" data-wcpsave="tax_status" name="tax_status"><option value="taxable"' . selected( $txs, 'taxable', false ) . '>Vergilendirilebilir</option><option value="shipping"' . selected( $txs, 'shipping', false ) . '>Yalnız kargo</option><option value="none"' . selected( $txs, 'none', false ) . '>Yok</option></select></div>';
		$txc = $p ? $p->get_tax_class() : '';
		echo '<div><label class="tx-label">Vergi sınıfı</label><select class="form-control" data-wcpsave="tax_class" name="tax_class"><option value=""' . selected( $txc, '', false ) . '>Standart</option>';
		if ( class_exists( 'WC_Tax' ) ) { foreach ( WC_Tax::get_tax_classes() as $tc ) { $sl = sanitize_title( $tc ); echo '<option value="' . esc_attr( $sl ) . '"' . selected( $txc, $sl, false ) . '>' . esc_html( $tc ) . '</option>'; } }
		echo '</select></div>';
		if ( $p ) {
			$purl = get_permalink( $p->get_id() );
			echo '<div class="full"><label class="tx-label">URL adresi (kalıcı bağlantı)</label><div class="tx-slugrow"><span class="tx-slugbase">' . esc_html( trailingslashit( dirname( $purl ) ) ) . '</span><input class="form-control" data-wcpsave="slug" name="slug" value="' . esc_attr( $p->get_slug() ) . '"></div>';
			echo '<a class="tx-mini2" id="tx-permalink" href="' . esc_url( $purl ) . '" target="_blank">' . esc_html( $purl ) . '</a></div>';
		}
		echo '</div></div>';

		// Envanter
		$ss = $p ? $p->get_stock_status() : 'instock';
		$manage = $p ? $p->get_manage_stock() : false;
		$bo = $p ? $p->get_backorders() : 'no';
		$low_raw = $p ? get_post_meta( $p->get_id(), '_low_stock_amount', true ) : '';
		echo '<div class="tab-pane fade" id="tab-envanter"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">SKU</label><input class="form-control" data-wcpsave="sku" name="sku" value="' . esc_attr( $val( 'get_sku' ) ) . '"></div>';
		echo '<div><label class="tx-label">Stok durumu</label><select class="form-control" data-wcpsave="stock_status" name="stock_status"><option value="instock"' . selected( $ss, 'instock', false ) . '>Stokta</option><option value="outofstock"' . selected( $ss, 'outofstock', false ) . '>Tükendi</option><option value="onbackorder"' . selected( $ss, 'onbackorder', false ) . '>Ön sipariş</option></select></div>';
		echo '<div><label class="tx-label">Adet</label><input type="number" class="form-control" data-wcpsave="stock_quantity" name="stock_quantity" value="' . esc_attr( $p && $p->get_stock_quantity() !== null ? $p->get_stock_quantity() : '' ) . '"></div>';
		echo '<div><label class="tx-label">Düşük stok eşiği</label><input type="number" class="form-control" data-wcpsave="low_stock" name="low_stock" value="' . esc_attr( $low_raw ) . '" placeholder="ör. 2"></div>';
		echo '<div><label class="tx-label">Ön sipariş</label><select class="form-control" data-wcpsave="backorders" name="backorders"><option value="no"' . selected( $bo, 'no', false ) . '>İzin verme</option><option value="notify"' . selected( $bo, 'notify', false ) . '>İzin ver (bildir)</option><option value="yes"' . selected( $bo, 'yes', false ) . '>İzin ver</option></select></div>';
		echo '<div class="full"><label class="tx-check"><input type="checkbox" data-wcpsave="manage_stock" name="manage_stock" value="1"' . checked( $manage, true, false ) . '> Stok adedini yönet</label> &nbsp; <label class="tx-check"><input type="checkbox" data-wcpsave="sold_individually" name="sold_individually" value="1"' . checked( $p ? $p->get_sold_individually() : false, true, false ) . '> Tek başına satılır</label></div>';
		echo '</div></div>';

		// Gönderim
		echo '<div class="tab-pane fade" id="tab-gonderim"><div class="tx-formgrid tx-formgrid-3">';
		echo '<div><label class="tx-label">Ağırlık (kg)</label><input class="form-control" data-wcpsave="weight" name="weight" value="' . esc_attr( $val( 'get_weight' ) ) . '"></div>';
		echo '<div><label class="tx-label">Uzunluk (cm)</label><input class="form-control" data-wcpsave="length" name="length" value="' . esc_attr( $val( 'get_length' ) ) . '"></div>';
		echo '<div><label class="tx-label">Genişlik (cm)</label><input class="form-control" data-wcpsave="width" name="width" value="' . esc_attr( $val( 'get_width' ) ) . '"></div>';
		echo '<div><label class="tx-label">Yükseklik (cm)</label><input class="form-control" data-wcpsave="height" name="height" value="' . esc_attr( $val( 'get_height' ) ) . '"></div>';
		$scid = $p ? $p->get_shipping_class_id() : 0;
		echo '<div class="full"><label class="tx-label">Gönderim sınıfı</label><select class="form-control" name="shipping_class"><option value="0">Yok</option>';
		foreach ( get_terms( array( 'taxonomy' => 'product_shipping_class', 'hide_empty' => false ) ) as $sc ) { echo '<option value="' . esc_attr( $sc->term_id ) . '"' . selected( $scid, $sc->term_id, false ) . '>' . esc_html( $sc->name ) . '</option>'; }
		echo '</select></div></div></div>';

		// Bağlantılı ürünler + Beraber satın alınacak (FBT)
		echo '<div class="tab-pane fade" id="tab-baglantili">';
		self::product_picker( 'upsell_ids', 'upsells', 'Üst satış (Upsell)', 'Ürün sayfasında "bunları da beğenebilirsiniz" olarak gösterilir. Yazınca öneriler çıkar — tıklayıp ekleyin, anında kaydedilir.', $p ? $p->get_upsell_ids() : array() );
		echo '<div class="mt-3"></div>';
		self::product_picker( 'cross_sell_ids', 'cross_sells', 'Çapraz satış (Cross-sell)', 'Sepet sayfasında önerilir.', $p ? $p->get_cross_sell_ids() : array() );
		// 🎁 Hediye ürünleri (STORE ÖZEL HEDİYE — ücretsiz, sepete otomatik)
		echo '<hr class="tx-hr"><h4 class="tx-subhead">🎁 Hediye ürünleri (ücretsiz)</h4>';
		echo '<p class="text-muted" style="font-size:11.5px;margin:-4px 0 9px">Bu ürün alınınca seçtiğiniz ürün(ler) sepete <b>otomatik ve ücretsiz (0 TL)</b> eklenir; ürün sayfasında "STORE ÖZEL HEDİYE" bloğunda gösterilir. Yazınca öneriler çıkar — tıkla, anında kaydedilir.';
		echo '</p>';
		self::product_picker( 'gift_ids', 'wcp_gift_ids', 'Hediye ürün(ler)', '', $p ? array_map( 'intval', (array) get_post_meta( $p->get_id(), '_wcp_gift_ids', true ) ) : array() );
		// 📦 Paket içeriği — hediye bloğunda "yanında gösterilen" ürünler (klavye/kalem vb., görsel)
		echo '<div class="mt-3"></div>';
		echo '<h4 class="tx-subhead">📦 Paket içeriği (yanındaki ürünler)</h4>';
		echo '<p class="text-muted" style="font-size:11.5px;margin:-4px 0 9px">Hediye bloğundaki "Paket İçeriği" görselinde ana ürünün yanında gösterilir (ör. klavye, kalem). Sadece görsel — sepete eklenmez.</p>';
		self::product_picker( 'bundle_ids', 'wcp_bundle_ids', 'Yanında gösterilen ürün(ler)', '', $p ? array_map( 'intval', (array) get_post_meta( $p->get_id(), '_wcp_bundle_ids', true ) ) : array() );
		// SSD Yükseltme Seçenekleri (Surface İçin SSD Yükseltme Modülü — meta _fbt_product_ids)
		echo '<hr class="tx-hr"><h4 class="tx-subhead">SSD Yükseltme Seçenekleri</h4>';
		echo '<p class="text-muted" style="font-size:11.5px;margin:-4px 0 9px">Ürün sayfasında "Surface\'inizin SSD\'sini yükseltin" kutusunda gösterilir. İşaretle/kaldır — anında kaydedilir.</p>';
		$ssd_sel = $p ? array_map( 'intval', (array) get_post_meta( $p->get_id(), '_fbt_product_ids', true ) ) : array();
		$ssd_products = get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'post__not_in' => $id ? array( $id ) : array(), 'meta_query' => array( array( 'key' => '_sku', 'value' => 'SSD', 'compare' => 'LIKE' ) ), 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<input type="hidden" name="ssd_present" value="1">';
		echo '<div class="tx-ssdbox" id="tx-ssdbox">';
		if ( $ssd_products ) {
			foreach ( $ssd_products as $sp ) {
				$spp = wc_get_product( $sp->ID ); if ( ! $spp ) { continue; }
				$thumb = $spp->get_image_id() ? wp_get_attachment_image_url( $spp->get_image_id(), array( 44, 44 ) ) : wc_placeholder_img_src();
				$prc = get_post_meta( $sp->ID, '_regular_price', true );
				echo '<label class="tx-ssditem"><input type="checkbox" class="tx-ssdcb" name="ssd_ids[]" value="' . esc_attr( $sp->ID ) . '"' . checked( in_array( (int) $sp->ID, $ssd_sel, true ), true, false ) . '><img src="' . esc_url( $thumb ) . '"><span>' . esc_html( wp_strip_all_tags( $spp->get_name() ) ) . '</span><b>' . ( $prc !== '' ? wp_kses_post( wc_price( $prc ) ) : '' ) . '</b></label>';
			}
		} else {
			echo '<p class="text-muted" style="font-size:12px;margin:0">SKU\'sunda "SSD" geçen ürün yok. Önce bir "SSD Yükseltme" ürünü (ör. SKU <b>HS-SSD-...</b>) oluşturun; burada listelenir.</p>';
		}
		echo '</div>';
		echo '</div>';

		// Nitelikler — tam yönetim (ekle / kaldır / sırala / genişlet / değer otomatik tamamlama)
		echo '<div class="tab-pane fade' . ( $atab === 'nitelikler' ? ' show active' : '' ) . '" id="tab-nitelikler">';
		echo '<input type="hidden" name="attr_present" value="1"><input type="hidden" id="tx-attr-order" name="attr_order" value="">';
		$rows = array(); $used = array();
		if ( $p ) {
			foreach ( $p->get_attributes() as $a ) {
				$used[] = $a->get_name();
				$vals = array();
				if ( $a->is_taxonomy() ) { foreach ( $a->get_terms() as $t ) { $vals[] = $t->name; } } else { $vals = $a->get_options(); }
				$rows[] = array( 'key' => $a->get_name(), 'label' => wc_attribute_label( $a->get_name() ), 'tax' => $a->is_taxonomy(), 'vals' => $vals, 'vis' => $a->get_visible() );
			}
		}
		$pc_ids = $p ? wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'ids' ) ) : array();
		$tplcat = isset( $_GET['tplcat'] ) ? absint( $_GET['tplcat'] ) : ( ! empty( $pc_ids ) ? $pc_ids[0] : 0 );
		if ( isset( $_GET['attrs'] ) && $_GET['attrs'] === 'fetch' && $tplcat ) {
			foreach ( self::category_attribute_template( array( $tplcat ), $used ) as $tk ) {
				$rows[] = array( 'key' => $tk, 'label' => wc_attribute_label( $tk ), 'tax' => taxonomy_exists( $tk ), 'vals' => array(), 'vis' => true );
				$used[] = $tk;
			}
		}
		// araç çubuğu: ekle / aç / kapat
		$avail = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
		echo '<div class="tx-attr-toolbar">';
		echo '<select class="form-control tx-select" id="tx-attr-add"><option value="">+ Nitelik ekle…</option>';
		foreach ( $avail as $at ) { $tx = wc_attribute_taxonomy_name( $at->attribute_name ); echo '<option value="' . esc_attr( $tx ) . '" data-tax="1" data-label="' . esc_attr( $at->attribute_label ) . '">' . esc_html( $at->attribute_label ) . '</option>'; }
		echo '<option value="__custom__" data-tax="0" data-label="Özel nitelik">+ Özel ürün niteliği</option>';
		echo '</select>';
		echo '<button type="button" class="btn tx-btn" id="tx-attr-expand"><i class="fas fa-angles-down mr-1"></i> Tümünü aç</button>';
		echo '<button type="button" class="btn tx-btn" id="tx-attr-collapse"><i class="fas fa-angles-up mr-1"></i> Tümünü kapat</button>';
		echo '</div>';
		// kategoriden getir
		echo '<div class="tx-fetchbar mt-1"><select id="tx-tplcat" class="form-control">';
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) ) as $c ) { echo '<option value="' . esc_attr( $c->term_id ) . '"' . selected( $tplcat, $c->term_id, false ) . '>' . esc_html( $c->name ) . '</option>'; }
		echo '</select><a class="btn tx-btn" id="tx-fetchbtn" href="' . esc_url( self::url( 'product', $id, array( 'attrs' => 'fetch', 'tplcat' => $tplcat ) ) ) . '"><i class="fas fa-rotate mr-1"></i> Kategoriden boş nitelik getir</a></div>';
		echo '<script>(function(){var s=document.getElementById("tx-tplcat"),btn=document.getElementById("tx-fetchbtn");if(s&&btn)s.addEventListener("change",function(){try{var u=new URL(btn.href);u.searchParams.set("tplcat",s.value);btn.href=u.toString();}catch(e){}});})();</script>';
		// liste
		echo '<div class="tx-attrs" id="tx-attrs">';
		$idx = 0;
		foreach ( $rows as $r ) { self::attr_row( $idx, $r['key'], $r['label'], $r['tax'], $r['vals'], $r['vis'] ); $idx++; }
		echo '</div>';
		echo '<p class="text-muted" id="tx-attr-empty" style="font-size:12.5px;' . ( $rows ? 'display:none' : '' ) . '">Henüz nitelik yok. Yukarıdan ekleyin veya kategoriden getirin.</p>';
		echo '<p class="text-muted" style="font-size:11.5px;margin-top:9px"><i class="fas fa-circle-info"></i> Değer kutusuna yazınca öneriler çıkar (tıkla/Enter). Sürükleyerek sırala, başlığa tıklayarak aç/kapat. Nitelikler <b>Tümünü kaydet</b> ile kaydedilir.</p>';
		echo '<script>window.WCP_ATTR_IDX=' . (int) $idx . ';</script>';
		echo '</div>';

		// Gelişmiş
		echo '<div class="tab-pane fade" id="tab-gelismis">';
		echo '<label class="tx-label">Satın alma notu</label><textarea class="form-control mb-3" name="purchase_note" rows="3">' . esc_textarea( $p ? $p->get_purchase_note() : '' ) . '</textarea>';
		echo '<div class="tx-formgrid"><div><label class="tx-label">Menü sırası</label><input type="number" class="form-control" name="menu_order" value="' . esc_attr( $p ? $p->get_menu_order() : 0 ) . '"></div>';
		echo '<div class="full"><label class="tx-check"><input type="checkbox" name="reviews_allowed" value="1"' . checked( $p ? $p->get_reviews_allowed() : true, true, false ) . '> Değerlendirmelere izin ver</label></div></div></div>';

		echo '</div></div></div>'; // tab-content / card-body / tx-pdata

		// Görseller — WordPress medya kütüphanesi + anlık AJAX (kaydetmeden)
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Görseller</h3></div><div class="card-body">';
		if ( $p ) {
			$fimg = $p->get_image_id() ? wp_get_attachment_image_url( $p->get_image_id(), array( 90, 90 ) ) : '';
			echo '<label class="tx-label">Öne çıkan görsel</label><div class="tx-featbox">';
			echo '<img id="tx-feat-img" src="' . esc_url( $fimg ) . '"' . ( $fimg ? '' : ' style="display:none"' ) . '>';
			echo '<div class="tx-featbtns"><button type="button" class="btn tx-btn" id="tx-feat-select"><i class="fas fa-image mr-1"></i> Medyadan seç / yükle</button> <button type="button" class="btn tx-btn" id="tx-feat-remove"' . ( $fimg ? '' : ' style="display:none"' ) . '>Kaldır</button></div></div>';
			echo '<label class="tx-label mt-3">Galeri <span class="text-muted" style="font-weight:400">— sürükleyerek sırala</span></label>';
			echo '<div class="tx-gallery" id="tx-gallery">';
			foreach ( $p->get_gallery_image_ids() as $gid ) { echo '<div class="tx-gitem" data-id="' . esc_attr( $gid ) . '"><img src="' . esc_url( wp_get_attachment_image_url( $gid, array( 70, 70 ) ) ) . '"><a href="#" class="tx-gremove" data-id="' . esc_attr( $gid ) . '">×</a></div>'; }
			echo '</div><button type="button" class="btn tx-btn mt-2" id="tx-gal-add"><i class="fas fa-plus mr-1"></i> Galeri görseli ekle</button>';
			echo '<p class="text-muted mt-2" style="font-size:11.5px"><i class="fas fa-bolt"></i> Görseller anında kaydedilir — sayfayı kaydetmeye gerek yok.</p>';
		} else {
			echo '<p class="text-muted mb-0">Görsel eklemek için önce ürünü kaydedin, sonra düzenleyin.</p>';
		}
		echo '</div></div>';

		echo '</div><div class="col-lg-4">';

		// Yayın
		$st = $p ? $p->get_status() : 'publish';
		$vis = $p ? $p->get_catalog_visibility() : 'visible';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yayın</h3>' . ( $p ? '<span class="tx-autosave-badge" id="tx-autosave"><i class="fas fa-bolt"></i> Otomatik kayıt</span>' : '' ) . '</div><div class="card-body">';
		echo '<label class="tx-label">Durum</label><select class="form-control tx-select mb-2" data-wcpsave="status" name="status"><option value="publish"' . selected( $st, 'publish', false ) . '>Yayında</option><option value="draft"' . selected( $st, 'draft', false ) . '>Taslak</option><option value="pending"' . selected( $st, 'pending', false ) . '>Beklemede</option></select>';
		echo '<label class="tx-label">Katalog görünürlüğü</label><select class="form-control tx-select mb-2" data-wcpsave="catalog_visibility" name="catalog_visibility"><option value="visible"' . selected( $vis, 'visible', false ) . '>Mağaza ve arama</option><option value="catalog"' . selected( $vis, 'catalog', false ) . '>Sadece mağaza</option><option value="search"' . selected( $vis, 'search', false ) . '>Sadece arama</option><option value="hidden"' . selected( $vis, 'hidden', false ) . '>Gizli</option></select>';
		echo '<label class="tx-check mb-2"><input type="checkbox" data-wcpsave="featured" name="featured" value="1"' . checked( $p ? $p->get_featured() : false, true, false ) . '> Öne çıkan ürün</label>';
		echo '<button class="btn btn-block tx-btn primary" type="submit"><i class="fas fa-save mr-1"></i> Tümünü kaydet</button>';
		if ( $p ) {
			echo '<div class="tx-actrow mt-2">';
			echo '<a class="btn tx-btn" target="_blank" rel="noopener" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $id ) ) ) . '"><i class="fas fa-eye mr-1"></i> Önizleme</a>';
			echo '<button type="button" class="btn tx-btn" id="tx-act-duplicate"><i class="fas fa-copy mr-1"></i> Kopyala</button>';
			echo '</div>';
			echo '<div class="tx-actrow mt-2">';
			echo '<button type="button" class="btn tx-btn" id="tx-act-draft"><i class="fas fa-file mr-1"></i> Taslağa al</button>';
			echo '<button type="button" class="btn tx-btn tx-btn-danger" id="tx-act-trash"><i class="fas fa-trash mr-1"></i> Çöpe at</button>';
			echo '</div>';
		}
		if ( $p && defined( 'ELEMENTOR_VERSION' ) ) {
			$el_url = add_query_arg( array( 'post' => $id, 'action' => 'elementor' ), admin_url( 'post.php' ) );
			echo '<a class="btn btn-block tx-btn mt-2" target="_blank" rel="noopener" href="' . esc_url( $el_url ) . '"><i class="fas fa-pen-ruler mr-1"></i> Görsel olarak düzenle</a>';
		}
		echo '</div></div>';

		// Kategoriler (hiyerarşik)
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Kategoriler</h3></div><div class="card-body tx-catbox">';
		self::render_term_checks( 'product_cat', 'product_cat[]', $p ? wp_get_post_terms( $p->get_id(), 'product_cat', array( 'fields' => 'ids' ) ) : array() );
		echo '</div></div>';

		// Marka
		if ( taxonomy_exists( 'product_brand' ) ) {
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Marka</h3></div><div class="card-body tx-catbox">';
			self::render_term_checks( 'product_brand', 'product_brand[]', $p ? wp_get_post_terms( $p->get_id(), 'product_brand', array( 'fields' => 'ids' ) ) : array() );
			echo '</div></div>';
		}

		// Etiketler
		$ptags = $p ? wp_get_post_terms( $p->get_id(), 'product_tag', array( 'fields' => 'names' ) ) : array();
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Etiketler</h3></div><div class="card-body">';
		echo '<input class="form-control" name="product_tags" value="' . esc_attr( implode( ', ', $ptags ) ) . '" placeholder="virgülle ayırın"></div></div>';

		// Google entegrasyonu
		if ( in_array( 'google-listings-and-ads/google-listings-and-ads.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			$gg = function ( $k ) use ( $p ) { return $p ? get_post_meta( $p->get_id(), $k, true ) : ''; };
			$gla = $p && $gg( '_wc_gla_visibility' ) ? $gg( '_wc_gla_visibility' ) : 'sync-and-show';
			$gcond = $gg( '_wc_gla_condition' ); $ggen = $gg( '_wc_gla_gender' ); $gage = $gg( '_wc_gla_ageGroup' );
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Google entegrasyonu</h3></div><div class="card-body">';
			echo '<input type="hidden" name="gla_present" value="1">';
			echo '<label class="tx-label">Kanal görünürlüğü</label><select class="form-control tx-select mb-2" name="gla_visibility"><option value="sync-and-show"' . selected( $gla, 'sync-and-show', false ) . '>Katalogda senkronize et ve göster</option><option value="dont-sync-and-show"' . selected( $gla, 'dont-sync-and-show', false ) . '>Senkronize etme ve gizle</option></select>';
			echo '<div class="tx-formgrid">';
			echo '<div class="full"><label class="tx-label">Google ürün kategorisi</label><input class="form-control" name="gla_google_product_category" value="' . esc_attr( $gg( '_wc_gla_google_product_category' ) ) . '" placeholder="ör. Electronics > Computers > Tablet Computers"></div>';
			echo '<div><label class="tx-label">GTIN</label><input class="form-control" name="gla_gtin" value="' . esc_attr( $gg( '_wc_gla_gtin' ) ) . '"></div>';
			echo '<div><label class="tx-label">MPN</label><input class="form-control" name="gla_mpn" value="' . esc_attr( $gg( '_wc_gla_mpn' ) ) . '"></div>';
			echo '<div><label class="tx-label">Marka</label><input class="form-control" name="gla_brand" value="' . esc_attr( $gg( '_wc_gla_brand' ) ) . '"></div>';
			echo '<div><label class="tx-label">Durum</label><select class="form-control" name="gla_condition"><option value="">—</option><option value="new"' . selected( $gcond, 'new', false ) . '>Yeni</option><option value="refurbished"' . selected( $gcond, 'refurbished', false ) . '>Yenilenmiş</option><option value="used"' . selected( $gcond, 'used', false ) . '>İkinci el</option></select></div>';
			echo '<div><label class="tx-label">Boyut</label><input class="form-control" name="gla_size" value="' . esc_attr( $gg( '_wc_gla_size' ) ) . '"></div>';
			echo '<div><label class="tx-label">Renk</label><input class="form-control" name="gla_color" value="' . esc_attr( $gg( '_wc_gla_color' ) ) . '"></div>';
			echo '<div><label class="tx-label">Cinsiyet</label><select class="form-control" name="gla_gender"><option value="">—</option><option value="male"' . selected( $ggen, 'male', false ) . '>Erkek</option><option value="female"' . selected( $ggen, 'female', false ) . '>Kadın</option><option value="unisex"' . selected( $ggen, 'unisex', false ) . '>Unisex</option></select></div>';
			echo '<div><label class="tx-label">Yaş grubu</label><select class="form-control" name="gla_age_group"><option value="">—</option><option value="adult"' . selected( $gage, 'adult', false ) . '>Yetişkin</option><option value="kids"' . selected( $gage, 'kids', false ) . '>Çocuk</option><option value="toddler"' . selected( $gage, 'toddler', false ) . '>Küçük çocuk</option><option value="infant"' . selected( $gage, 'infant', false ) . '>Bebek</option><option value="newborn"' . selected( $gage, 'newborn', false ) . '>Yenidoğan</option></select></div>';
			echo '<div><label class="tx-label">Materyal</label><input class="form-control" name="gla_material" value="' . esc_attr( $gg( '_wc_gla_material' ) ) . '"></div>';
			echo '<div><label class="tx-label">Desen</label><input class="form-control" name="gla_pattern" value="' . esc_attr( $gg( '_wc_gla_pattern' ) ) . '"></div>';
			echo '</div>';
			if ( $p ) { $gs = $gg( '_wc_gla_sync_status' ); if ( $gs ) { echo '<p class="text-muted mt-2" style="font-size:11.5px">Senkron durumu: ' . esc_html( $gs ) . '</p>'; } }
			echo '</div></div>';
		}

		// Facebook & Instagram kanalı
		if ( in_array( 'facebook-for-woocommerce/facebook-for-woocommerce.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			$fbon = $p ? ( get_post_meta( $p->get_id(), '_wc_facebook_sync_enabled', true ) === 'yes' ) : true;
			$fbg = function ( $k ) use ( $p ) { return $p ? get_post_meta( $p->get_id(), $k, true ) : ''; };
			$cond = $fbg( 'fb_product_condition' ); $fbgen = $fbg( 'fb_gender' ); $fbage = $fbg( 'fb_age_group' );
			$imgsrc = $fbg( '_wc_facebook_product_image_source' ); if ( ! $imgsrc ) { $imgsrc = 'product'; }
			$fbimg = $fbg( 'fb_product_image' );
			echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Facebook & Instagram</h3></div><div class="card-body">';
			echo '<input type="hidden" name="fb_sync_toggle" value="1">';
			echo '<label class="tx-check mb-2"><input type="checkbox" name="fb_sync" value="1"' . checked( $fbon, true, false ) . '> Katalogla senkronize et</label>';
			echo '<label class="tx-label">Facebook açıklaması</label><textarea class="form-control mb-2" name="fb_description" rows="3" placeholder="Boşsa ürün açıklaması kullanılır">' . esc_textarea( $fbg( 'fb_product_description' ) ) . '</textarea>';
			echo '<label class="tx-label">Facebook ürün görseli</label><select class="form-control tx-select mb-2" id="tx-fb-imgsrc" name="fb_image_source"><option value="product"' . selected( $imgsrc, 'product', false ) . '>Mağaza görselini kullan</option><option value="custom"' . selected( $imgsrc, 'custom', false ) . '>Özel görsel kullan</option></select>';
			echo '<div id="tx-fb-customimg" class="tx-fb-customimg" style="' . ( $imgsrc === 'custom' ? '' : 'display:none' ) . '"><div class="tx-featbox mb-2">';
			echo '<img id="tx-fb-img-prev" src="' . esc_url( $fbimg ) . '"' . ( $fbimg ? '' : ' style="display:none"' ) . '>';
			echo '<button type="button" class="btn tx-btn" id="tx-fb-img-select"><i class="fas fa-image mr-1"></i> Ortam ekle</button></div>';
			echo '<input type="hidden" id="tx-fb-img-url" name="fb_image_url" value="' . esc_attr( $fbimg ) . '"></div>';
			echo '<label class="tx-label">Facebook fiyatı (TL)</label><input class="form-control mb-2" name="fb_price" value="' . esc_attr( $fbg( 'fb_product_price' ) ) . '" placeholder="Boşsa ürün fiyatı kullanılır">';
			echo '<p class="tx-fb-note">Reklam performansını optimize etmek için bu ek öznitelikleri sağlamanızı öneririz. <a href="#tab-nitelikler" data-toggle="tab" class="tx-attrlink">Özniteliklere git →</a></p>';
			echo '<div class="tx-formgrid">';
			echo '<div><label class="tx-label">Üretici Parça No (MPN)</label><input class="form-control" name="fb_mpn" value="' . esc_attr( $fbg( 'fb_mpn' ) ) . '"></div>';
			echo '<div><label class="tx-label">Marka</label><input class="form-control" name="fb_brand" value="' . esc_attr( $fbg( 'fb_brand' ) ) . '"></div>';
			echo '<div><label class="tx-label">Durum</label><select class="form-control" name="fb_product_condition"><option value="">Seç</option><option value="new"' . selected( $cond, 'new', false ) . '>Yeni</option><option value="refurbished"' . selected( $cond, 'refurbished', false ) . '>Yenilenmiş</option><option value="used"' . selected( $cond, 'used', false ) . '>İkinci el</option></select></div>';
			echo '<div><label class="tx-label">Boyut</label><input class="form-control" name="fb_size" value="' . esc_attr( $fbg( 'fb_size' ) ) . '"></div>';
			echo '<div><label class="tx-label">Renk</label><input class="form-control" name="fb_color" value="' . esc_attr( $fbg( 'fb_color' ) ) . '"></div>';
			echo '<div><label class="tx-label">Yaş grubu</label><select class="form-control" name="fb_age_group"><option value="">Seç</option><option value="adult"' . selected( $fbage, 'adult', false ) . '>Yetişkin</option><option value="all ages"' . selected( $fbage, 'all ages', false ) . '>Tüm yaşlar</option><option value="teen"' . selected( $fbage, 'teen', false ) . '>Genç</option><option value="kids"' . selected( $fbage, 'kids', false ) . '>Çocuk</option><option value="toddler"' . selected( $fbage, 'toddler', false ) . '>Küçük çocuk</option><option value="infant"' . selected( $fbage, 'infant', false ) . '>Bebek</option><option value="newborn"' . selected( $fbage, 'newborn', false ) . '>Yenidoğan</option></select></div>';
			echo '<div><label class="tx-label">Cinsiyet</label><select class="form-control" name="fb_gender"><option value="">Seç</option><option value="male"' . selected( $fbgen, 'male', false ) . '>Erkek</option><option value="female"' . selected( $fbgen, 'female', false ) . '>Kadın</option><option value="unisex"' . selected( $fbgen, 'unisex', false ) . '>Unisex</option></select></div>';
			echo '<div><label class="tx-label">Materyal</label><input class="form-control" name="fb_material" value="' . esc_attr( $fbg( 'fb_material' ) ) . '"></div>';
			echo '<div><label class="tx-label">Desen</label><input class="form-control" name="fb_pattern" value="' . esc_attr( $fbg( 'fb_pattern' ) ) . '"></div>';
			echo '<div class="full"><label class="tx-label">Google ürün kategorisi</label><input class="form-control" name="fb_google_category" value="' . esc_attr( $fbg( '_wc_facebook_google_product_category' ) ) . '" placeholder="ör. 222 (Tablet Bilgisayarlar)"></div>';
			echo '</div></div></div>';
		}

		echo '</div></div></form>';
	}

	/* ---------------- categories ---------------- */
	protected static function view_categories() {
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Kategori kaydedildi.</div>'; }
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Kategoriler</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th></th><th>Ad</th><th>Açıklama</th><th>Slug</th><th class="text-right">Ürün</th><th></th></tr></thead><tbody>';
		self::render_cat_rows( 0, 0 );
		echo '</tbody></table></div></div></div>';
		echo '</div><div class="col-lg-4">';
		self::category_form_card( false, null );
		echo '</div></div>';
	}

	protected static function render_cat_rows( $parent, $depth ) {
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || ! $terms ) { return; }
		foreach ( $terms as $t ) {
			$thumb_id = get_term_meta( $t->term_id, 'thumbnail_id', true );
			$img = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 40, 40 ) ) : wc_placeholder_img_src();
			$rn = wp_create_nonce( 'wcp_crow_' . $t->term_id );
			echo '<tr><td><img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt=""></td>';
			echo '<td style="padding-left:' . ( 8 + $depth * 18 ) . 'px">' . ( $depth ? '<span class="tx-subdash">└</span> ' : '' ) . '<a class="tx-strong" href="' . esc_url( self::url( 'category', $t->term_id ) ) . '">' . esc_html( $t->name ) . '</a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'category', $t->term_id ) ) . '">Düzenle</a> · <a href="' . esc_url( get_term_link( $t ) ) . '" target="_blank">Görüntüle</a> · <a class="tx-del" href="' . esc_url( self::url( 'categories', 0, array( 'crow' => 'delete', 'id' => $t->term_id, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Kategori silinsin mi?\')">Sil</a></div></td>';
			echo '<td class="text-muted"><span class="tx-trunc">' . esc_html( wp_strip_all_tags( $t->description ) ) . '</span></td>';
			echo '<td class="text-muted">' . esc_html( $t->slug ) . '</td>';
			echo '<td class="text-right tx-strong">' . (int) $t->count . '</td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'category', $t->term_id ) ) . '">Düzenle</a></td></tr>';
			self::render_cat_rows( $t->term_id, $depth + 1 );
		}
	}

	protected static function cat_parent_options( $parent, $depth, $selected, $exclude ) {
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) ) { return; }
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $exclude ) { continue; }
			echo '<option value="' . esc_attr( $t->term_id ) . '"' . selected( $selected, $t->term_id, false ) . '>' . esc_html( str_repeat( '— ', $depth ) . $t->name ) . '</option>';
			self::cat_parent_options( $t->term_id, $depth + 1, $selected, $exclude );
		}
	}

	protected static function category_form_card( $is_edit, $term ) {
		$tid = $term ? $term->term_id : 0;
		$thumb_id = $tid ? get_term_meta( $tid, 'thumbnail_id', true ) : 0;
		$thumb = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 90, 90 ) ) : '';
		$disp = $tid ? get_term_meta( $tid, 'display_type', true ) : '';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_edit ? 'Kategoriyi düzenle' : 'Yeni kategori ekle' ) . '</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'categories' ) ) . '">';
		wp_nonce_field( 'wcp_category_' . $tid );
		echo '<input type="hidden" name="wcp_action" value="category_save"><input type="hidden" name="term_id" value="' . esc_attr( $tid ) . '">';
		echo '<label class="tx-label">Ad</label><input class="form-control mb-2" name="cat_name" value="' . esc_attr( $term ? $term->name : '' ) . '" required>';
		echo '<label class="tx-label">Üst kategori</label><select class="form-control tx-select mb-2" name="cat_parent"><option value="0">Yok (üst düzey)</option>';
		self::cat_parent_options( 0, 0, $term ? $term->parent : 0, $tid );
		echo '</select>';
		echo '<label class="tx-label">Slug</label><input class="form-control mb-2" name="cat_slug" value="' . esc_attr( $term ? $term->slug : '' ) . '" placeholder="boş = otomatik">';
		echo '<label class="tx-label">Açıklama</label><textarea class="form-control mb-2" name="cat_desc" rows="3">' . esc_textarea( $term ? $term->description : '' ) . '</textarea>';
		echo '<label class="tx-label">Görüntüleme tipi</label><select class="form-control tx-select mb-2" name="cat_display"><option value=""' . selected( $disp, '', false ) . '>Varsayılan</option><option value="products"' . selected( $disp, 'products', false ) . '>Ürünler</option><option value="subcategories"' . selected( $disp, 'subcategories', false ) . '>Alt kategoriler</option><option value="both"' . selected( $disp, 'both', false ) . '>İkisi</option></select>';
		echo '<label class="tx-label">Görsel</label><div class="tx-featbox"><img id="tx-cat-img" src="' . esc_url( $thumb ) . '"' . ( $thumb ? '' : ' style="display:none"' ) . '><div class="tx-featbtns"><button type="button" class="btn tx-btn" id="tx-cat-select"><i class="fas fa-image mr-1"></i> Medyadan seç</button> <button type="button" class="btn tx-btn" id="tx-cat-remove"' . ( $thumb ? '' : ' style="display:none"' ) . '>Kaldır</button></div></div>';
		echo '<input type="hidden" name="cat_thumb_id" id="tx-cat-thumb-id" value="' . esc_attr( $thumb_id ? $thumb_id : '' ) . '">';
		echo '<button class="btn btn-block tx-btn primary mt-3" type="submit"><i class="fas fa-save mr-1"></i> ' . ( $is_edit ? 'Güncelle' : 'Ekle' ) . '</button>';
		if ( $is_edit ) { echo '<a class="btn btn-block tx-btn mt-2" href="' . esc_url( self::url( 'categories' ) ) . '">← Listeye dön</a>'; }
		echo '</form></div></div>';
	}

	protected static function view_category_form( $id ) {
		$term = $id ? get_term( $id, 'product_cat' ) : null;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'categories' ) ) . '">← Kategorilere dön</a>';
		if ( ! $term || is_wp_error( $term ) ) { echo '<div class="tx-empty">Kategori bulunamadı.</div>'; return; }
		echo '<div class="row"><div class="col-lg-6">';
		self::category_form_card( true, $term );
		echo '</div></div>';
	}

	protected static function category_media_js() {
		?>
<script>
jQuery(function($){
	if(!document.getElementById('tx-cat-select')){return;}
	$('#tx-cat-select').on('click',function(e){ e.preventDefault();
		var f=wp.media({title:'Kategori görseli seç', button:{text:'Seç'}, multiple:false, library:{type:'image'}});
		f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); var u=(a.sizes&&a.sizes.thumbnail)?a.sizes.thumbnail.url:a.url;
			$('#tx-cat-img').attr('src',u).show(); $('#tx-cat-thumb-id').val(a.id); $('#tx-cat-remove').show();
		}); f.open();
	});
	$('#tx-cat-remove').on('click',function(e){ e.preventDefault(); $('#tx-cat-img').hide().attr('src',''); $('#tx-cat-thumb-id').val(''); $(this).hide(); });
});
</script>
		<?php
	}

	/* ---------------- Ürün Etiketleri ---------------- */
	protected static function view_tags() {
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Etiket kaydedildi.</div>'; }
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ürün Etiketleri</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th>Ad</th><th>Açıklama</th><th>Slug</th><th class="text-right">Ürün</th><th></th></tr></thead><tbody>';
		$terms = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || ! $terms ) { echo '<tr><td colspan="5"><div class="tx-empty">Kayıt yok.</div></td></tr>'; }
		else { foreach ( $terms as $t ) {
			$rn = wp_create_nonce( 'wcp_trow_' . $t->term_id );
			echo '<tr><td><a class="tx-strong" href="' . esc_url( self::url( 'tag', $t->term_id ) ) . '">' . esc_html( $t->name ) . '</a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'tag', $t->term_id ) ) . '">Düzenle</a> · <a href="' . esc_url( get_term_link( $t ) ) . '" target="_blank">Görüntüle</a> · <a class="tx-del" href="' . esc_url( self::url( 'tags', 0, array( 'trow' => 'delete', 'id' => $t->term_id, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Etiket silinsin mi?\')">Sil</a></div></td>';
			echo '<td class="text-muted"><span class="tx-trunc">' . esc_html( wp_strip_all_tags( $t->description ) ) . '</span></td><td class="text-muted">' . esc_html( $t->slug ) . '</td><td class="text-right tx-strong">' . (int) $t->count . '</td><td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'tag', $t->term_id ) ) . '">Düzenle</a></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
		echo '</div><div class="col-lg-4">';
		self::tag_form_card( false, null );
		echo '</div></div>';
	}

	protected static function tag_form_card( $is_edit, $term ) {
		$tid = $term ? $term->term_id : 0;
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_edit ? 'Etiketi düzenle' : 'Yeni etiket ekle' ) . '</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'tags' ) ) . '">';
		wp_nonce_field( 'wcp_tag_' . $tid );
		echo '<input type="hidden" name="wcp_action" value="tag_save"><input type="hidden" name="term_id" value="' . esc_attr( $tid ) . '">';
		echo '<label class="tx-label">Ad</label><input class="form-control mb-2" name="tag_name" value="' . esc_attr( $term ? $term->name : '' ) . '" required>';
		echo '<label class="tx-label">Slug</label><input class="form-control mb-2" name="tag_slug" value="' . esc_attr( $term ? $term->slug : '' ) . '" placeholder="boş = otomatik">';
		echo '<label class="tx-label">Açıklama</label><textarea class="form-control mb-2" name="tag_desc" rows="3">' . esc_textarea( $term ? $term->description : '' ) . '</textarea>';
		echo '<button class="btn btn-block tx-btn primary mt-2" type="submit"><i class="fas fa-save mr-1"></i> ' . ( $is_edit ? 'Güncelle' : 'Ekle' ) . '</button>';
		if ( $is_edit ) { echo '<a class="btn btn-block tx-btn mt-2" href="' . esc_url( self::url( 'tags' ) ) . '">← Listeye dön</a>'; }
		echo '</form></div></div>';
	}

	protected static function view_tag_form( $id ) {
		$term = $id ? get_term( $id, 'product_tag' ) : null;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'tags' ) ) . '">← Etiketlere dön</a>';
		if ( ! $term || is_wp_error( $term ) ) { echo '<div class="tx-empty">Etiket bulunamadı.</div>'; return; }
		echo '<div class="row"><div class="col-lg-6">';
		self::tag_form_card( true, $term );
		echo '</div></div>';
	}

	/* ---------------- Markalar (product_brand) ---------------- */
	protected static function view_brands() {
		if ( ! taxonomy_exists( 'product_brand' ) ) { echo '<div class="tx-empty">Marka taksonomisi bulunamadı.</div>'; return; }
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Marka kaydedildi.</div>'; }
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Markalar</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th></th><th>Ad</th><th>Slug</th><th class="text-right">Ürün</th><th></th></tr></thead><tbody>';
		$terms = get_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || ! $terms ) { echo '<tr><td colspan="5"><div class="tx-empty">Kayıt yok.</div></td></tr>'; }
		else { foreach ( $terms as $t ) {
			$rn = wp_create_nonce( 'wcp_brrow_' . $t->term_id );
			$thumb_id = get_term_meta( $t->term_id, 'thumbnail_id', true );
			$img = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 40, 40 ) ) : '';
			echo '<tr><td>' . ( $img ? '<img class="tx-thumb sm" src="' . esc_url( $img ) . '" alt="">' : '' ) . '</td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'brand', $t->term_id ) ) . '">' . esc_html( $t->name ) . '</a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'brand', $t->term_id ) ) . '">Düzenle</a> · <a class="tx-del" href="' . esc_url( self::url( 'brands', 0, array( 'brrow' => 'delete', 'id' => $t->term_id, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Marka silinsin mi?\')">Sil</a></div></td>';
			echo '<td class="text-muted">' . esc_html( $t->slug ) . '</td><td class="text-right tx-strong">' . (int) $t->count . '</td><td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'brand', $t->term_id ) ) . '">Düzenle</a></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
		echo '</div><div class="col-lg-4">';
		self::brand_form_card( false, null );
		echo '</div></div>';
	}

	protected static function brand_form_card( $is_edit, $term ) {
		$tid = $term ? $term->term_id : 0;
		$thumb_id = $tid ? get_term_meta( $tid, 'thumbnail_id', true ) : 0;
		$thumb = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 90, 90 ) ) : '';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_edit ? 'Markayı düzenle' : 'Yeni marka ekle' ) . '</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'brands' ) ) . '">';
		wp_nonce_field( 'wcp_brand_' . $tid );
		echo '<input type="hidden" name="wcp_action" value="brand_save"><input type="hidden" name="term_id" value="' . esc_attr( $tid ) . '">';
		echo '<label class="tx-label">Ad</label><input class="form-control mb-2" name="brand_name" value="' . esc_attr( $term ? $term->name : '' ) . '" required>';
		echo '<label class="tx-label">Slug</label><input class="form-control mb-2" name="brand_slug" value="' . esc_attr( $term ? $term->slug : '' ) . '" placeholder="boş = otomatik">';
		echo '<label class="tx-label">Açıklama</label><textarea class="form-control mb-2" name="brand_desc" rows="3">' . esc_textarea( $term ? $term->description : '' ) . '</textarea>';
		echo '<label class="tx-label">Logo</label><div class="tx-featbox"><img id="tx-cat-img" src="' . esc_url( $thumb ) . '"' . ( $thumb ? '' : ' style="display:none"' ) . '><div class="tx-featbtns"><button type="button" class="btn tx-btn" id="tx-cat-select"><i class="fas fa-image mr-1"></i> Medyadan seç</button> <button type="button" class="btn tx-btn" id="tx-cat-remove"' . ( $thumb ? '' : ' style="display:none"' ) . '>Kaldır</button></div></div>';
		echo '<input type="hidden" name="brand_thumb_id" id="tx-cat-thumb-id" value="' . esc_attr( $thumb_id ? $thumb_id : '' ) . '">';
		echo '<button class="btn btn-block tx-btn primary mt-3" type="submit"><i class="fas fa-save mr-1"></i> ' . ( $is_edit ? 'Güncelle' : 'Ekle' ) . '</button>';
		if ( $is_edit ) { echo '<a class="btn btn-block tx-btn mt-2" href="' . esc_url( self::url( 'brands' ) ) . '">← Listeye dön</a>'; }
		echo '</form></div></div>';
	}

	protected static function view_brand_form( $id ) {
		$term = $id ? get_term( $id, 'product_brand' ) : null;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'brands' ) ) . '">← Markalara dön</a>';
		if ( ! $term || is_wp_error( $term ) ) { echo '<div class="tx-empty">Marka bulunamadı.</div>'; return; }
		echo '<div class="row"><div class="col-lg-6">';
		self::brand_form_card( true, $term );
		echo '</div></div>';
		self::category_media_js();
	}

	/* ---------------- Ürün Nitelikleri (Attributes) ---------------- */
	protected static function view_attributes() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Nitelik kaydedildi.</div>'; }
		$atts = wc_get_attribute_taxonomies();
		$types = array( 'select' => 'Seç (açılır)', 'text' => 'Metin' );
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ürün Nitelikleri</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th>Ad</th><th>Slug</th><th>Tip</th><th class="text-right">Değer</th><th></th></tr></thead><tbody>';
		if ( empty( $atts ) ) { echo '<tr><td colspan="5"><div class="tx-empty">Henüz nitelik yok.</div></td></tr>'; }
		else { foreach ( $atts as $a ) {
			$tax = wc_attribute_taxonomy_name( $a->attribute_name );
			$cnt = taxonomy_exists( $tax ) ? (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : 0;
			$rn  = wp_create_nonce( 'wcp_arow_' . $a->attribute_id );
			echo '<tr><td><a class="tx-strong" href="' . esc_url( self::url( 'attribute', $a->attribute_id ) ) . '">' . esc_html( $a->attribute_label ) . '</a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'attribute', $a->attribute_id ) ) . '">Değerleri yönet</a> · <a class="tx-del" href="' . esc_url( self::url( 'attributes', 0, array( 'arow' => 'delete', 'id' => $a->attribute_id, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Nitelik ve tüm değerleri silinsin mi?\')">Sil</a></div></td>';
			echo '<td class="text-muted">' . esc_html( $a->attribute_name ) . '</td><td class="text-muted">' . esc_html( isset( $types[ $a->attribute_type ] ) ? $types[ $a->attribute_type ] : $a->attribute_type ) . '</td><td class="text-right tx-strong">' . $cnt . '</td><td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'attribute', $a->attribute_id ) ) . '">Yönet</a></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
		echo '</div><div class="col-lg-4">';
		self::attribute_form_card( false, null );
		echo '</div></div>';
	}

	protected static function attribute_form_card( $is_edit, $a ) {
		$aid = $a ? $a->attribute_id : 0;
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_edit ? 'Niteliği düzenle' : 'Yeni nitelik ekle' ) . '</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'attributes' ) ) . '">';
		wp_nonce_field( 'wcp_attr_' . $aid );
		echo '<input type="hidden" name="wcp_action" value="attr_save"><input type="hidden" name="attr_id" value="' . esc_attr( $aid ) . '">';
		echo '<label class="tx-label">Ad</label><input class="form-control mb-2" name="attr_label" value="' . esc_attr( $a ? $a->attribute_label : '' ) . '" required>';
		echo '<label class="tx-label">Slug</label><input class="form-control mb-2" name="attr_slug" value="' . esc_attr( $a ? $a->attribute_name : '' ) . '" placeholder="boş = otomatik"' . ( $is_edit ? ' readonly' : '' ) . '>';
		$ty = $a ? $a->attribute_type : 'select';
		echo '<label class="tx-label">Tip</label><select class="form-control tx-select mb-2" name="attr_type"><option value="select"' . selected( $ty, 'select', false ) . '>Seç (açılır liste)</option><option value="text"' . selected( $ty, 'text', false ) . '>Metin</option></select>';
		$ob = $a ? $a->attribute_orderby : 'menu_order';
		echo '<label class="tx-label">Sıralama</label><select class="form-control tx-select mb-2" name="attr_orderby"><option value="menu_order"' . selected( $ob, 'menu_order', false ) . '>Özel sıra</option><option value="name"' . selected( $ob, 'name', false ) . '>Ada göre</option><option value="name_num"' . selected( $ob, 'name_num', false ) . '>Ada göre (sayısal)</option><option value="id"' . selected( $ob, 'id', false ) . '>ID</option></select>';
		echo '<button class="btn btn-block tx-btn primary mt-2" type="submit"><i class="fas fa-save mr-1"></i> ' . ( $is_edit ? 'Güncelle' : 'Ekle' ) . '</button>';
		if ( $is_edit ) { echo '<a class="btn btn-block tx-btn mt-2" href="' . esc_url( self::url( 'attributes' ) ) . '">← Listeye dön</a>'; }
		echo '</form></div></div>';
	}

	protected static function view_attribute_form( $id ) {
		echo '<a class="tx-back" href="' . esc_url( self::url( 'attributes' ) ) . '">← Niteliklere dön</a>';
		$a = null;
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) { foreach ( wc_get_attribute_taxonomies() as $t ) { if ( (int) $t->attribute_id === (int) $id ) { $a = $t; break; } } }
		if ( ! $a ) { echo '<div class="tx-empty">Nitelik bulunamadı.</div>'; return; }
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Kaydedildi.</div>'; }
		$tax = wc_attribute_taxonomy_name( $a->attribute_name );
		echo '<div class="row"><div class="col-lg-5">';
		self::attribute_form_card( true, $a );
		echo '</div><div class="col-lg-7">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">"' . esc_html( $a->attribute_label ) . '" değerleri</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Değer</th><th>Slug</th><th class="text-right">Ürün</th><th></th></tr></thead><tbody>';
		$terms = taxonomy_exists( $tax ) ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ) ) : array();
		if ( is_wp_error( $terms ) || ! $terms ) { echo '<tr><td colspan="4"><div class="tx-empty">Henüz değer yok.</div></td></tr>'; }
		else { foreach ( $terms as $t ) {
			$rn = wp_create_nonce( 'wcp_atrow_' . $t->term_id );
			echo '<tr><td class="tx-strong">' . esc_html( $t->name ) . '</td><td class="text-muted">' . esc_html( $t->slug ) . '</td><td class="text-right">' . (int) $t->count . '</td><td class="text-right"><a class="tx-del" href="' . esc_url( self::url( 'attribute', $a->attribute_id, array( 'atrow' => 'delete', 'id' => $t->term_id, 'tax' => $tax, 'aid' => $a->attribute_id, '_wpnonce' => $rn ) ) ) . '" onclick="return confirm(\'Değer silinsin mi?\')">Sil</a></td></tr>';
		} }
		echo '</tbody></table></div>';
		echo '<div class="p-3 border-top">';
		echo '<form method="post" action="' . esc_url( self::url( 'attribute', $a->attribute_id ) ) . '" class="tx-addform">';
		wp_nonce_field( 'wcp_attrterm_' . $a->attribute_id );
		echo '<input type="hidden" name="wcp_action" value="attrterm_save"><input type="hidden" name="attr_id" value="' . esc_attr( $a->attribute_id ) . '"><input type="hidden" name="attr_tax" value="' . esc_attr( $tax ) . '">';
		echo '<span class="tx-addlbl"><i class="fas fa-plus"></i> Yeni değer</span><input class="form-control" name="term_name" placeholder="Değer adı" required><input class="form-control" name="term_slug" placeholder="slug (ops.)"><button class="btn tx-btn primary" type="submit">Ekle</button>';
		echo '</form></div>';
		echo '</div></div>';
		echo '</div></div>';
	}

	/* ---------------- Kargo Bölgeleri ---------------- */
	protected static function view_shipping() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Kaydedildi.</div>'; }
		echo '<style>.tx-shipm{border:1px solid #eef0f4;border-radius:10px;padding:12px;margin-bottom:10px;background:#fbfcfe}.tx-shipm-grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}.tx-shipm-grid>div{flex:1;min-width:140px}.tx-shipm-foot{flex:0 0 100%;display:flex;align-items:center;gap:16px;margin-top:6px}</style>';
		echo '<div class="row"><div class="col-lg-8">';
		foreach ( WC_Shipping_Zones::get_zones() as $z ) { self::shipping_zone_card( $z['id'], $z['zone_name'], $z['formatted_zone_location'] ); }
		$row = new WC_Shipping_Zone( 0 );
		self::shipping_zone_card( 0, $row->get_zone_name(), 'Diğer tüm bölgeler (eşleşmeyen)' );
		echo '</div><div class="col-lg-4">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yeni kargo bölgesi</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'shipping' ) ) . '">';
		wp_nonce_field( 'wcp_ship' );
		echo '<input type="hidden" name="wcp_action" value="ship_zone_add">';
		echo '<label class="tx-label">Bölge adı</label><input class="form-control mb-2" name="zone_name" required>';
		echo '<label class="tx-label">Ülke(ler)</label><select class="form-control tx-select" name="zone_countries[]" multiple size="8">';
		if ( function_exists( 'WC' ) && WC()->countries ) { foreach ( WC()->countries->get_countries() as $code => $nm ) { echo '<option value="' . esc_attr( $code ) . '"' . ( $code === 'TR' ? ' selected' : '' ) . '>' . esc_html( $nm ) . '</option>'; } }
		echo '</select>';
		echo '<button class="btn btn-block tx-btn primary mt-3" type="submit"><i class="fas fa-plus mr-1"></i> Bölge ekle</button>';
		echo '</form></div></div>';
		echo '</div></div>';
	}

	protected static function shipping_zone_card( $zid, $zname, $zloc ) {
		$zone = WC_Shipping_Zones::get_zone( $zid );
		$methods = $zone ? $zone->get_shipping_methods( false ) : array();
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-location-dot mr-2"></i>' . esc_html( $zname ) . ' <small class="text-muted">' . esc_html( $zloc ) . '</small></h3>';
		if ( $zid ) { $dn = wp_create_nonce( 'wcp_zonedel_' . $zid ); echo '<div class="card-tools"><a class="tx-del" href="' . esc_url( self::url( 'shipping', 0, array( 'zonedel' => $zid, '_wpnonce' => $dn ) ) ) . '" onclick="return confirm(\'Bölge silinsin mi?\')">Bölgeyi sil</a></div>'; }
		echo '</div><div class="card-body">';
		if ( empty( $methods ) ) { echo '<div class="tx-empty">Bu bölgede kargo yöntemi yok.</div>'; }
		foreach ( $methods as $iid => $m ) { self::shipping_method_form( $zid, $iid, $m ); }
		echo '<form method="post" action="' . esc_url( self::url( 'shipping' ) ) . '" class="tx-addform mt-2">';
		wp_nonce_field( 'wcp_shipmethod_' . $zid );
		echo '<input type="hidden" name="wcp_action" value="ship_method_add"><input type="hidden" name="zone_id" value="' . esc_attr( $zid ) . '">';
		echo '<span class="tx-addlbl"><i class="fas fa-plus"></i> Yöntem ekle</span><select class="form-control" name="method_type"><option value="flat_rate">Sabit ücret</option><option value="free_shipping">Ücretsiz kargo</option><option value="local_pickup">Mağazadan teslim</option></select><button class="btn tx-btn" type="submit">Ekle</button>';
		echo '</form>';
		echo '</div></div>';
	}

	protected static function shipping_method_form( $zid, $iid, $m ) {
		$type = $m->id; $title = $m->get_title(); $enabled = $m->is_enabled();
		$cost = method_exists( $m, 'get_option' ) ? $m->get_option( 'cost' ) : '';
		$min  = method_exists( $m, 'get_option' ) ? $m->get_option( 'min_amount' ) : '';
		$req  = method_exists( $m, 'get_option' ) ? $m->get_option( 'requires' ) : '';
		$dn   = wp_create_nonce( 'wcp_methoddel_' . $iid );
		echo '<form method="post" action="' . esc_url( self::url( 'shipping' ) ) . '" class="tx-shipm">';
		wp_nonce_field( 'wcp_methodsave_' . $iid );
		echo '<input type="hidden" name="wcp_action" value="ship_method_save"><input type="hidden" name="instance_id" value="' . esc_attr( $iid ) . '"><input type="hidden" name="method_type" value="' . esc_attr( $type ) . '">';
		echo '<div class="tx-shipm-grid">';
		echo '<div><label class="tx-label">Başlık</label><input class="form-control" name="m_title" value="' . esc_attr( $title ) . '"></div>';
		if ( $type === 'flat_rate' || $type === 'local_pickup' ) { echo '<div><label class="tx-label">Ücret (₺)</label><input class="form-control" name="m_cost" value="' . esc_attr( $cost ) . '"></div>'; }
		if ( $type === 'free_shipping' ) {
			echo '<div><label class="tx-label">Koşul</label><select class="form-control" name="m_requires"><option value=""' . selected( $req, '', false ) . '>Herkes</option><option value="min_amount"' . selected( $req, 'min_amount', false ) . '>Min. tutar</option><option value="coupon"' . selected( $req, 'coupon', false ) . '>Kupon</option><option value="either"' . selected( $req, 'either', false ) . '>Tutar VEYA kupon</option><option value="both"' . selected( $req, 'both', false ) . '>İkisi de</option></select></div>';
			echo '<div><label class="tx-label">Min. tutar (₺)</label><input class="form-control" name="m_min_amount" value="' . esc_attr( $min ) . '"></div>';
		}
		echo '<div class="tx-shipm-foot"><label class="tx-check"><input type="checkbox" name="m_enabled" value="1"' . checked( $enabled, true, false ) . '> Aktif</label>';
		echo '<button class="btn btn-sm tx-btn primary" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
		echo '<a class="tx-del" href="' . esc_url( self::url( 'shipping', 0, array( 'methoddel' => $iid, 'zone' => $zid, '_wpnonce' => $dn ) ) ) . '" onclick="return confirm(\'Yöntem silinsin mi?\')">Sil</a></div>';
		echo '</div></form>';
	}

	/* ---------------- Vergi Oranları ---------------- */
	protected static function view_tax() {
		if ( ! class_exists( 'WC_Tax' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Kaydedildi.</div>'; }
		if ( ! wc_tax_enabled() ) { echo '<div class="alert tx-flash"><i class="fas fa-circle-info mr-1"></i> Vergi sistemi şu an kapalı; oranlar kaydedilebilir ama uygulanması için Mağaza Ayarları → Genel\'den vergiyi açın.</div>'; }
		$cur = isset( $_GET['class'] ) ? sanitize_title( wp_unslash( $_GET['class'] ) ) : 'standard';
		$classes = array( 'standard' => 'Standart' );
		foreach ( WC_Tax::get_tax_classes() as $c ) { $classes[ sanitize_title( $c ) ] = $c; }
		if ( ! isset( $classes[ $cur ] ) ) { $cur = 'standard'; }
		echo '<div class="tx-anav"><style>.tx-anav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}.tx-anav-i{display:inline-flex;align-items:center;padding:8px 13px;border:1px solid #e6e9ef;border-radius:9px;background:#fff;color:#445566;font-weight:600;font-size:13px;text-decoration:none}.tx-anav-i.is-on{background:#15171c;color:#fff;border-color:#15171c}</style>';
		foreach ( $classes as $slug => $lbl ) { echo '<a class="tx-anav-i' . ( $cur === $slug ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'tax', 0, array( 'class' => $slug ) ) ) . '">' . esc_html( $lbl ) . '</a>'; }
		echo '</div>';
		$rates = WC_Tax::get_rates_for_tax_class( $cur === 'standard' ? '' : $cur );
		echo '<div class="row"><div class="col-lg-8">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Vergi oranları — ' . esc_html( $classes[ $cur ] ) . '</h3></div><div class="card-body p-0"><div class="tx-tablewrap"><table class="table tx-table tx-table-sm mb-0"><thead><tr><th>Ülke</th><th>İl</th><th>Şehir</th><th>Posta</th><th class="text-right">Oran %</th><th>Ad</th><th class="text-center">Öncelik</th><th></th></tr></thead><tbody>';
		if ( empty( $rates ) ) { echo '<tr><td colspan="8"><div class="tx-empty">Bu sınıfta vergi oranı yok.</div></td></tr>'; }
		else { foreach ( $rates as $r ) {
			$dn = wp_create_nonce( 'wcp_taxdel_' . $r->tax_rate_id );
			$pc = isset( $r->postcode ) ? implode( ', ', (array) $r->postcode ) : '';
			$ct = isset( $r->city ) ? implode( ', ', (array) $r->city ) : '';
			echo '<tr><td class="tx-strong">' . esc_html( $r->tax_rate_country ? $r->tax_rate_country : '*' ) . '</td><td>' . esc_html( $r->tax_rate_state ? $r->tax_rate_state : '*' ) . '</td><td>' . esc_html( $ct ? $ct : '*' ) . '</td><td>' . esc_html( $pc ? $pc : '*' ) . '</td><td class="text-right tx-strong">' . esc_html( rtrim( rtrim( $r->tax_rate, '0' ), '.' ) ) . '</td><td>' . esc_html( $r->tax_rate_name ) . '</td><td class="text-center">' . esc_html( $r->tax_rate_priority ) . '</td>';
			echo '<td class="text-right"><a class="tx-del" href="' . esc_url( self::url( 'tax', 0, array( 'taxdel' => $r->tax_rate_id, 'class' => $cur, '_wpnonce' => $dn ) ) ) . '" onclick="return confirm(\'Silinsin mi?\')">Sil</a></td></tr>';
		} }
		echo '</tbody></table></div></div></div>';
		echo '</div><div class="col-lg-4">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yeni vergi oranı</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'tax' ) ) . '">';
		wp_nonce_field( 'wcp_tax' );
		echo '<input type="hidden" name="wcp_action" value="tax_save"><input type="hidden" name="rate_class" value="' . esc_attr( $cur ) . '">';
		echo '<label class="tx-label">Ülke kodu</label><input class="form-control mb-2" name="rate_country" value="TR" placeholder="* = tümü">';
		echo '<label class="tx-label">İl/Eyalet kodu</label><input class="form-control mb-2" name="rate_state" placeholder="* = tümü">';
		echo '<label class="tx-label">Şehir</label><input class="form-control mb-2" name="rate_city" placeholder="* = tümü">';
		echo '<label class="tx-label">Posta kodu</label><input class="form-control mb-2" name="rate_postcode" placeholder="* = tümü">';
		echo '<label class="tx-label">Oran (%)</label><input class="form-control mb-2" name="rate_percent" value="20.0000">';
		echo '<label class="tx-label">Vergi adı</label><input class="form-control mb-2" name="rate_name" value="KDV">';
		echo '<label class="tx-label">Öncelik</label><input class="form-control mb-2" type="number" name="rate_priority" value="1">';
		echo '<label class="tx-check d-block mb-1"><input type="checkbox" name="rate_shipping" value="1" checked> Kargoya da uygula</label>';
		echo '<label class="tx-check d-block"><input type="checkbox" name="rate_compound" value="1"> Bileşik vergi</label>';
		echo '<button class="btn btn-block tx-btn primary mt-3" type="submit"><i class="fas fa-plus mr-1"></i> Ekle</button>';
		echo '</form></div></div>';
		echo '</div></div>';
	}

	/* ---------------- Mağaza Ayarları (Genel / Ödeme / E-posta) ---------------- */
	protected static function view_store_settings() {
		if ( ! class_exists( 'WooCommerce' ) ) { echo '<div class="tx-empty">Mağaza etkin değil.</div>'; return; }
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'genel';
		if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'ok' ) { echo '<div class="alert tx-flash">Ayarlar kaydedildi.</div>'; }
		$tabs = array( 'genel' => array( 'Genel', 'fa-gear' ), 'odeme' => array( 'Ödemeler', 'fa-credit-card' ), 'eposta' => array( 'E-postalar', 'fa-envelope' ) );
		echo '<style>.tx-anav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}.tx-anav-i{display:inline-flex;align-items:center;padding:8px 13px;border:1px solid #e6e9ef;border-radius:9px;background:#fff;color:#445566;font-weight:600;font-size:13px;text-decoration:none}.tx-anav-i.is-on{background:#15171c;color:#fff;border-color:#15171c}.tx-shipm{border:1px solid #eef0f4;border-radius:10px;padding:12px;margin-bottom:10px;background:#fbfcfe}.tx-shipm-grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}.tx-shipm-grid>div{flex:1;min-width:150px}.tx-shipm-foot{flex:0 0 100%;display:flex;align-items:center;gap:16px;margin-top:6px;flex-wrap:wrap}</style>';
		echo '<div class="tx-anav">';
		foreach ( $tabs as $k => $t ) { echo '<a class="tx-anav-i' . ( $tab === $k ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'wc-ayarlar', 0, array( 'tab' => $k ) ) ) . '"><i class="fas ' . esc_attr( $t[1] ) . ' mr-1"></i>' . esc_html( $t[0] ) . '</a>'; }
		echo '</div>';
		if ( $tab === 'odeme' ) { self::wcset_payments(); }
		elseif ( $tab === 'eposta' ) { self::wcset_emails(); }
		else { self::wcset_general(); }
	}

	protected static function wcset_general() {
		$country = get_option( 'woocommerce_default_country' ); $cur = get_option( 'woocommerce_currency' ); $pos = get_option( 'woocommerce_currency_pos' );
		echo '<div class="row"><div class="col-lg-8"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Genel ayarlar</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'wc-ayarlar' ) ) . '">';
		wp_nonce_field( 'wcp_wcset' );
		echo '<input type="hidden" name="wcp_action" value="wcset_general">';
		echo '<div class="tx-formgrid tx-formgrid-wide">';
		echo '<div class="full"><label class="tx-label">Mağaza adresi</label><input class="form-control" name="store_address" value="' . esc_attr( get_option( 'woocommerce_store_address' ) ) . '"></div>';
		echo '<div class="full"><label class="tx-label">Adres (2. satır)</label><input class="form-control" name="store_address_2" value="' . esc_attr( get_option( 'woocommerce_store_address_2' ) ) . '"></div>';
		echo '<div><label class="tx-label">Şehir</label><input class="form-control" name="store_city" value="' . esc_attr( get_option( 'woocommerce_store_city' ) ) . '"></div>';
		echo '<div><label class="tx-label">Posta kodu</label><input class="form-control" name="store_postcode" value="' . esc_attr( get_option( 'woocommerce_store_postcode' ) ) . '"></div>';
		echo '<div><label class="tx-label">Ülke</label><select class="form-control tx-select" name="default_country">';
		if ( WC()->countries ) { foreach ( WC()->countries->get_countries() as $code => $nm ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( ( strpos( (string) $country, ':' ) !== false ? substr( $country, 0, strpos( $country, ':' ) ) : $country ), $code, false ) . '>' . esc_html( $nm ) . '</option>'; } }
		echo '</select></div>';
		echo '<div><label class="tx-label">Para birimi</label><select class="form-control tx-select" name="currency">';
		if ( function_exists( 'get_woocommerce_currencies' ) ) { foreach ( get_woocommerce_currencies() as $code => $nm ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( $cur, $code, false ) . '>' . esc_html( $nm . ' (' . get_woocommerce_currency_symbol( $code ) . ')' ) . '</option>'; } }
		echo '</select></div>';
		echo '<div><label class="tx-label">Sembol konumu</label><select class="form-control tx-select" name="currency_pos"><option value="left"' . selected( $pos, 'left', false ) . '>Sol (₺99)</option><option value="right"' . selected( $pos, 'right', false ) . '>Sağ (99₺)</option><option value="left_space"' . selected( $pos, 'left_space', false ) . '>Sol boşluklu</option><option value="right_space"' . selected( $pos, 'right_space', false ) . '>Sağ boşluklu</option></select></div>';
		echo '<div><label class="tx-label">Binlik ayraç</label><input class="form-control" name="thousand_sep" value="' . esc_attr( get_option( 'woocommerce_price_thousand_sep' ) ) . '"></div>';
		echo '<div><label class="tx-label">Ondalık ayraç</label><input class="form-control" name="decimal_sep" value="' . esc_attr( get_option( 'woocommerce_price_decimal_sep' ) ) . '"></div>';
		echo '<div><label class="tx-label">Ondalık basamak</label><input class="form-control" type="number" min="0" name="num_decimals" value="' . esc_attr( get_option( 'woocommerce_price_num_decimals' ) ) . '"></div>';
		echo '</div>';
		echo '<label class="tx-check d-block mt-2"><input type="checkbox" name="calc_taxes" value="1"' . checked( get_option( 'woocommerce_calc_taxes' ), 'yes', false ) . '> Vergi hesaplamayı etkinleştir</label>';
		echo '<label class="tx-check d-block"><input type="checkbox" name="enable_coupons" value="1"' . checked( get_option( 'woocommerce_enable_coupons' ), 'yes', false ) . '> Kuponları etkinleştir</label>';
		echo '<button class="btn tx-btn primary mt-3" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
		echo '</form></div></div></div>';
	}

	protected static function wcset_payments() {
		$gws = ( function_exists( 'WC' ) && WC()->payment_gateways() ) ? WC()->payment_gateways()->payment_gateways() : array();
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ödeme yöntemleri</h3></div><div class="card-body">';
		if ( empty( $gws ) ) { echo '<div class="tx-empty">Ödeme yöntemi bulunamadı.</div>'; }
		foreach ( $gws as $gw ) {
			$gid = $gw->id; $en = ( $gw->enabled === 'yes' );
			echo '<form method="post" action="' . esc_url( self::url( 'wc-ayarlar' ) ) . '" class="tx-shipm">';
			wp_nonce_field( 'wcp_pay_' . $gid );
			echo '<input type="hidden" name="wcp_action" value="wcset_payment"><input type="hidden" name="gateway_id" value="' . esc_attr( $gid ) . '">';
			echo '<div class="tx-shipm-grid">';
			echo '<div style="flex:0 0 100%"><b>' . esc_html( $gw->get_method_title() ) . '</b> <small class="text-muted">' . esc_html( $gid ) . '</small></div>';
			echo '<div><label class="tx-label">Müşteriye görünen başlık</label><input class="form-control" name="p_title" value="' . esc_attr( $gw->get_title() ) . '"></div>';
			echo '<div style="flex:1 1 100%"><label class="tx-label">Açıklama</label><input class="form-control" name="p_description" value="' . esc_attr( wp_strip_all_tags( $gw->get_description() ) ) . '"></div>';
			echo '<div class="tx-shipm-foot"><label class="tx-check"><input type="checkbox" name="p_enabled" value="1"' . checked( $en, true, false ) . '> Aktif</label><button class="btn btn-sm tx-btn primary" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button><span class="text-muted" style="font-size:12px">Gelişmiş ayarlar (API anahtarı vb.) için "Tüm Yönetim"i kullanın.</span></div>';
			echo '</div></form>';
		}
		echo '</div></div>';
	}

	protected static function wcset_emails() {
		echo '<div class="row"><div class="col-lg-5"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Gönderen & görünüm</h3></div><div class="card-body">';
		echo '<form method="post" action="' . esc_url( self::url( 'wc-ayarlar' ) ) . '">';
		wp_nonce_field( 'wcp_wcemail' );
		echo '<input type="hidden" name="wcp_action" value="wcset_email">';
		echo '<label class="tx-label">Gönderen adı</label><input class="form-control mb-2" name="from_name" value="' . esc_attr( get_option( 'woocommerce_email_from_name' ) ) . '">';
		echo '<label class="tx-label">Gönderen e-posta</label><input class="form-control mb-2" name="from_address" value="' . esc_attr( get_option( 'woocommerce_email_from_address' ) ) . '">';
		echo '<label class="tx-label">Üst görsel URL</label><input class="form-control mb-2" name="header_image" value="' . esc_attr( get_option( 'woocommerce_email_header_image' ) ) . '">';
		echo '<label class="tx-label">Ana renk</label><input class="form-control mb-2" type="text" name="base_color" value="' . esc_attr( get_option( 'woocommerce_email_base_color' ) ) . '">';
		echo '<label class="tx-label">Alt bilgi metni</label><textarea class="form-control mb-2" name="footer_text" rows="3">' . esc_textarea( get_option( 'woocommerce_email_footer_text' ) ) . '</textarea>';
		echo '<button class="btn tx-btn primary mt-2" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
		echo '</form></div></div>';
		echo '<div class="col-lg-7"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">E-posta bildirimleri</h3></div><div class="card-body">';
		$emails = ( function_exists( 'WC' ) && WC()->mailer() ) ? WC()->mailer()->get_emails() : array();
		foreach ( $emails as $email ) {
			$eid = $email->id; $en = $email->is_enabled(); $is_cust = method_exists( $email, 'is_customer_email' ) ? $email->is_customer_email() : ( strpos( $eid, 'customer' ) !== false );
			echo '<form method="post" action="' . esc_url( self::url( 'wc-ayarlar' ) ) . '" class="tx-shipm">';
			wp_nonce_field( 'wcp_emailitem_' . $eid );
			echo '<input type="hidden" name="wcp_action" value="wcset_email_item"><input type="hidden" name="email_id" value="' . esc_attr( $eid ) . '">';
			echo '<div class="tx-shipm-grid">';
			echo '<div style="flex:0 0 100%"><b>' . esc_html( $email->get_title() ) . '</b></div>';
			if ( ! $is_cust ) { $recip = property_exists( $email, 'recipient' ) ? $email->recipient : ''; echo '<div style="flex:1 1 100%"><label class="tx-label">Alıcı(lar) — virgülle ayır</label><input class="form-control" name="e_recipient" value="' . esc_attr( $recip ) . '"></div>'; }
			echo '<div class="tx-shipm-foot"><label class="tx-check"><input type="checkbox" name="e_enabled" value="1"' . checked( $en, true, false ) . '> Aktif</label><button class="btn btn-sm tx-btn primary" type="submit">Kaydet</button></div>';
			echo '</div></form>';
		}
		echo '</div></div></div></div>';
	}

	/* ---------------- Mağaza Durumu (System Status) ---------------- */
	protected static function view_wc_status() {
		global $wpdb;
		$kv = function ( $title, $icon, $rows ) {
			echo '<div class="col-lg-6"><div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas ' . esc_attr( $icon ) . ' mr-2"></i>' . esc_html( $title ) . '</h3></div><div class="card-body tx-kv">';
			foreach ( $rows as $k => $v ) { echo '<div><span>' . esc_html( $k ) . '</span><b>' . wp_kses_post( $v ) . '</b></div>'; }
			echo '</div></div></div>';
		};
		$hpos = '—';
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) { $hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? '<span class="tx-badge tx-ok">Açık (HPOS)</span>' : 'Kapalı (klasik)'; }
		$prod_n = wp_count_posts( 'product' );
		echo '<div class="row">';
		$kv( 'Ortam', 'fa-server', array(
			'Site adresi'      => esc_html( home_url() ),
			'WordPress sürümü' => esc_html( get_bloginfo( 'version' ) ),
			'Mağaza sürümü'    => esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ),
			'PHP sürümü'       => esc_html( phpversion() ),
			'MySQL sürümü'     => esc_html( $wpdb->db_version() ),
			'Sunucu'           => esc_html( isset( $_SERVER['SERVER_SOFTWARE'] ) ? wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) : '—' ),
			'Maks. yükleme'    => esc_html( size_format( wp_max_upload_size() ) ),
			'Bellek limiti'    => esc_html( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '—' ),
			'PHP zaman aşımı'  => esc_html( ini_get( 'max_execution_time' ) . ' sn' ),
		) );
		$kv( 'Mağaza', 'fa-store', array(
			'Para birimi'   => esc_html( get_woocommerce_currency() . ' (' . get_woocommerce_currency_symbol() . ')' ),
			'Vergi'         => function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() ? '<span class="tx-badge tx-ok">Açık</span>' : '<span class="tx-badge tx-muted">Kapalı</span>',
			'Kuponlar'      => get_option( 'woocommerce_enable_coupons' ) === 'yes' ? '<span class="tx-badge tx-ok">Açık</span>' : '<span class="tx-badge tx-muted">Kapalı</span>',
			'Misafir ödeme' => get_option( 'woocommerce_enable_guest_checkout' ) === 'yes' ? 'Açık' : 'Kapalı',
			'Stok yönetimi' => get_option( 'woocommerce_manage_stock' ) === 'yes' ? 'Açık' : 'Kapalı',
			'Sipariş depolama' => $hpos,
			'Ülke'          => esc_html( get_option( 'woocommerce_default_country' ) ),
		) );
		$order_n = 0;
		if ( function_exists( 'wc_get_orders' ) ) { $cnt = wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) ); $order_n = is_object( $cnt ) ? $cnt->total : 0; }
		$kv( 'İçerik sayıları', 'fa-database', array(
			'Yayında ürün'    => (int) $prod_n->publish,
			'Taslak ürün'     => (int) $prod_n->draft,
			'Toplam sipariş'  => (int) $order_n,
			'Kategori'        => (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ),
			'Etiket'          => (int) wp_count_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) ),
			'Nitelik'         => function_exists( 'wc_get_attribute_taxonomies' ) ? count( wc_get_attribute_taxonomies() ) : 0,
			'Kullanıcı'       => (int) count_users()['total_users'],
		) );
		$theme = wp_get_theme();
		$kv( 'Tema & Eklentiler', 'fa-puzzle-piece', array(
			'Aktif tema'      => esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ),
			'Aktif eklenti'   => (int) count( (array) get_option( 'active_plugins', array() ) ),
			'PHP bellek (anlık)' => esc_html( size_format( memory_get_usage( true ) ) ),
			'Sunucu saati'    => esc_html( date_i18n( 'd.m.Y H:i' ) ),
			'WP dili'         => esc_html( get_locale() ),
		) );
		echo '</div>';
	}

	/* ---------------- reviews ---------------- */
	protected static function review_stars( $n ) {
		$n = (int) $n;
		if ( $n < 1 ) { return '<span class="text-muted" style="font-size:12px">—</span>'; }
		$out = '<span class="tx-stars" title="' . $n . '/5">';
		for ( $i = 1; $i <= 5; $i++ ) { $out .= '<span class="' . ( $i <= $n ? 'on' : '' ) . '">★</span>'; }
		return $out . '</span>';
	}

	protected static function review_badge( $approved ) {
		if ( $approved === '1' ) { return '<span class="tx-badge tx-ok">Onaylı</span>'; }
		if ( $approved === '0' ) { return '<span class="tx-badge tx-warn">Bekliyor</span>'; }
		if ( $approved === 'spam' ) { return '<span class="tx-badge tx-bad">Spam</span>'; }
		return '<span class="tx-badge tx-muted">Çöp</span>';
	}

	protected static function review_counts() {
		$base = array( 'post_type' => 'product', 'type__in' => array( 'review', 'comment' ), 'count' => true );
		return array(
			'all'     => (int) get_comments( $base + array( 'status' => 'all' ) ),
			'hold'    => (int) get_comments( $base + array( 'status' => 'hold' ) ),
			'approve' => (int) get_comments( $base + array( 'status' => 'approve' ) ),
			'spam'    => (int) get_comments( $base + array( 'status' => 'spam' ) ),
			'trash'   => (int) get_comments( $base + array( 'status' => 'trash' ) ),
		);
	}

	protected static function view_reviews() {
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
		if ( ! in_array( $status, array( 'all', 'hold', 'approve', 'spam', 'trash' ), true ) ) { $status = 'all'; }
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per = 20;
		$args = array( 'post_type' => 'product', 'type__in' => array( 'review', 'comment' ), 'status' => $status, 'number' => $per, 'paged' => $paged, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' );
		if ( $s !== '' ) { $args['search'] = $s; }
		$reviews = get_comments( $args );
		$c = self::review_counts();
		$is_trash = ( $status === 'trash' ); $is_spam = ( $status === 'spam' );

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'reviews' ) ) . '">';
		echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">';
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Değerlendirmelerde ara">';
		echo '<button class="btn tx-btn" type="submit">Ara</button>';
		if ( $s !== '' ) { echo '<a class="btn tx-btn" href="' . esc_url( self::url( 'reviews', 0, array( 'status' => $status ) ) ) . '">Temizle</a>'; }
		echo '</form>';

		echo '<div class="tx-chips mb-3">';
		$mk = function ( $key, $label ) use ( $status, $c ) {
			$href = $key === 'all' ? WCP_Panel::url( 'reviews' ) : WCP_Panel::url( 'reviews', 0, array( 'status' => $key ) );
			$on = ( $status === $key ) ? ' is-on' : '';
			$cls = in_array( $key, array( 'spam', 'trash' ), true ) ? ' tx-chip-trash' : '';
			return '<a class="tx-chip' . $cls . $on . '" href="' . esc_url( $href ) . '">' . esc_html( $label ) . ' (' . (int) $c[ $key ] . ')</a>';
		};
		echo $mk( 'all', 'Tümü' ) . $mk( 'hold', 'Bekleyen' ) . $mk( 'approve', 'Onaylı' ) . $mk( 'spam', 'Spam' ) . $mk( 'trash', 'Çöp' );
		echo '</div>';

		echo '<form method="post" action="' . esc_url( self::url( 'reviews', 0, array( 'status' => $status ) ) ) . '">';
		wp_nonce_field( 'wcp_reviews_bulk' );
		echo '<input type="hidden" name="wcp_action" value="reviews_bulk"><input type="hidden" name="ret_status" value="' . esc_attr( $status ) . '">';
		echo '<div class="tx-bulkbar mb-2"><select name="bulk" class="form-control tx-select"><option value="">Toplu işlem…</option>';
		if ( $is_trash ) { echo '<option value="untrash">Geri yükle</option><option value="delete">Kalıcı sil</option>'; }
		elseif ( $is_spam ) { echo '<option value="unspam">Spam değil</option><option value="delete">Kalıcı sil</option>'; }
		else { echo '<option value="approve">Onayla</option><option value="unapprove">Onayı kaldır</option><option value="spam">Spam</option><option value="trash">Çöpe taşı</option>'; }
		echo '</select><button class="btn tx-btn" type="submit" onclick="return this.form.bulk.value!==\'\'">Uygula</button></div>';

		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th style="width:30px"><input type="checkbox" class="tx-checkall"></th><th>Yazar</th><th>Puan</th><th>Değerlendirme</th><th>Ürün</th><th>Tarih</th><th>Durum</th></tr></thead><tbody>';
		if ( empty( $reviews ) ) { echo '<tr><td colspan="7"><div class="tx-empty">Değerlendirme yok.</div></td></tr>'; }
		foreach ( $reviews as $rv ) {
			$cid = $rv->comment_ID;
			$rn = wp_create_nonce( 'wcp_rvrow_' . $cid );
			$rating = get_comment_meta( $cid, 'rating', true );
			$approved = $rv->comment_approved;
			$pid = $rv->comment_post_ID;
			$rurl = function ( $action ) use ( $cid, $rn, $status ) { return WCP_Panel::url( 'reviews', 0, array( 'rvrow' => $action, 'id' => $cid, '_wpnonce' => $rn, 'ret_status' => $status ) ); };
			echo '<tr>';
			echo '<td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $cid ) . '"></td>';
			echo '<td><span class="tx-strong">' . esc_html( $rv->comment_author ) . '</span><div class="tx-row-actions">' . esc_html( $rv->comment_author_email ) . '</div></td>';
			echo '<td>' . self::review_stars( $rating ) . '</td>';
			echo '<td class="tx-rvcontent">' . wp_kses_post( wp_trim_words( $rv->comment_content, 22 ) );
			echo '<div class="tx-row-actions">';
			$acts = array( '<a href="' . esc_url( self::url( 'review', $cid ) ) . '">Düzenle / Yanıtla</a>' );
			if ( $is_trash ) {
				$acts[] = '<a href="' . esc_url( $rurl( 'untrash' ) ) . '">Geri yükle</a>';
				$acts[] = '<a class="tx-del" href="' . esc_url( $rurl( 'delete' ) ) . '" onclick="return confirm(\'Kalıcı silinsin mi?\')">Kalıcı sil</a>';
			} elseif ( $is_spam ) {
				$acts[] = '<a href="' . esc_url( $rurl( 'unspam' ) ) . '">Spam değil</a>';
				$acts[] = '<a class="tx-del" href="' . esc_url( $rurl( 'delete' ) ) . '" onclick="return confirm(\'Kalıcı silinsin mi?\')">Kalıcı sil</a>';
			} else {
				if ( $approved === '1' ) { $acts[] = '<a href="' . esc_url( $rurl( 'unapprove' ) ) . '">Onayı kaldır</a>'; }
				else { $acts[] = '<a href="' . esc_url( $rurl( 'approve' ) ) . '">Onayla</a>'; }
				$acts[] = '<a href="' . esc_url( $rurl( 'spam' ) ) . '">Spam</a>';
				$acts[] = '<a class="tx-del" href="' . esc_url( $rurl( 'trash' ) ) . '">Çöpe at</a>';
			}
			echo implode( ' · ', $acts );
			echo '</div></td>';
			echo '<td class="tx-trunc"><a href="' . esc_url( self::url( 'product', $pid ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $pid ) ) ) . '</a></td>';
			echo '<td class="text-muted">' . esc_html( date_i18n( 'd.m.Y', strtotime( $rv->comment_date ) ) ) . '</td>';
			echo '<td>' . self::review_badge( $approved ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div></div></div></form>';

		$tot = isset( $c[ $status ] ) ? $c[ $status ] : $c['all'];
		$pages = (int) ceil( $tot / $per );
		if ( $pages > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $pages, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) { $a = array( 'paged' => $i, 'status' => $status ); if ( $s !== '' ) { $a['s'] = $s; } echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'reviews', 0, $a ) ) . '">' . $i . '</a>'; }
			echo '</div>';
		}
		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	protected static function view_review( $id ) {
		$rv = $id ? get_comment( $id ) : null;
		if ( ! $rv ) { echo '<a class="tx-back" href="' . esc_url( self::url( 'reviews' ) ) . '">← Değerlendirmelere dön</a><div class="tx-empty">Değerlendirme bulunamadı.</div>'; return; }
		$cid = $rv->comment_ID;
		$pid = $rv->comment_post_ID;
		$rating = (int) get_comment_meta( $cid, 'rating', true );
		echo '<a class="tx-back" href="' . esc_url( self::url( 'reviews' ) ) . '">← Değerlendirmelere dön</a>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="alert tx-flash">' . ( $_GET['msg'] === 'reply' ? 'Yanıt eklendi.' : 'Değerlendirme kaydedildi.' ) . '</div>'; }
		echo '<div class="row"><div class="col-lg-8">';

		echo '<form method="post" action="' . esc_url( self::url( 'review', $cid ) ) . '">';
		wp_nonce_field( 'wcp_review_' . $cid );
		echo '<input type="hidden" name="wcp_action" value="review_save"><input type="hidden" name="review_id" value="' . esc_attr( $cid ) . '">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Değerlendirme</h3></div><div class="card-body"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">Yazar</label><input class="form-control" name="author" value="' . esc_attr( $rv->comment_author ) . '"></div>';
		echo '<div><label class="tx-label">E-posta</label><input class="form-control" name="author_email" value="' . esc_attr( $rv->comment_author_email ) . '"></div>';
		echo '<div><label class="tx-label">Puan</label><select class="form-control tx-select" name="rating"><option value="0"' . selected( $rating, 0, false ) . '>Puansız</option>';
		for ( $i = 1; $i <= 5; $i++ ) { echo '<option value="' . $i . '"' . selected( $rating, $i, false ) . '>' . $i . ' yıldız</option>'; }
		echo '</select></div>';
		echo '<div><label class="tx-label">Durum</label><select class="form-control tx-select" name="status"><option value="approve"' . selected( $rv->comment_approved, '1', false ) . '>Onaylı</option><option value="hold"' . selected( $rv->comment_approved, '0', false ) . '>Bekliyor</option></select></div>';
		echo '<div class="full"><label class="tx-label">İçerik</label><textarea class="form-control" name="content" rows="5">' . esc_textarea( $rv->comment_content ) . '</textarea></div>';
		echo '</div><div class="mt-3"><button class="btn tx-btn primary" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button></div>';
		echo '</div></div></form>';

		$replies = get_comments( array( 'parent' => $cid, 'status' => 'all', 'orderby' => 'comment_date_gmt', 'order' => 'ASC' ) );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yanıtlar</h3></div><div class="card-body">';
		if ( $replies ) {
			echo '<div class="tx-replies">';
			foreach ( $replies as $rp ) {
				echo '<div class="tx-reply"><div class="tx-reply-head"><strong>' . esc_html( $rp->comment_author ) . '</strong> <span class="text-muted">' . esc_html( date_i18n( 'd.m.Y H:i', strtotime( $rp->comment_date ) ) ) . '</span></div><div>' . wp_kses_post( wpautop( $rp->comment_content ) ) . '</div></div>';
			}
			echo '</div>';
		} else { echo '<p class="text-muted" style="font-size:13px">Henüz yanıt yok.</p>'; }
		echo '<form method="post" action="' . esc_url( self::url( 'review', $cid ) ) . '" class="mt-2">';
		wp_nonce_field( 'wcp_review_' . $cid );
		echo '<input type="hidden" name="wcp_action" value="review_reply"><input type="hidden" name="review_id" value="' . esc_attr( $cid ) . '">';
		echo '<label class="tx-label">Mağaza yanıtı ekle</label><textarea class="form-control mb-2" name="reply" rows="3" placeholder="Müşteriye yanıt yazın…"></textarea>';
		echo '<button class="btn tx-btn" type="submit"><i class="fas fa-reply mr-1"></i> Yanıtla</button>';
		echo '</form></div></div>';

		echo '</div><div class="col-lg-4">';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Ürün</h3></div><div class="card-body">';
		$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
		echo '<div class="tx-featbox">';
		if ( $thumb ) { echo '<img id="tx-feat-img" src="' . esc_url( $thumb ) . '">'; }
		echo '<div><a class="tx-strong" href="' . esc_url( self::url( 'product', $pid ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $pid ) ) ) . '</a></div>';
		echo '</div></div></div>';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Bilgi</h3></div><div class="card-body">';
		echo '<div class="tx-stat"><span>Durum</span><strong>' . self::review_badge( $rv->comment_approved ) . '</strong></div>';
		echo '<div class="tx-stat"><span>Puan</span><strong>' . self::review_stars( $rating ) . '</strong></div>';
		echo '<div class="tx-stat"><span>Tarih</span><strong>' . esc_html( date_i18n( 'd.m.Y H:i', strtotime( $rv->comment_date ) ) ) . '</strong></div>';
		if ( $rv->comment_author_IP ) { echo '<div class="tx-stat"><span>IP</span><strong>' . esc_html( $rv->comment_author_IP ) . '</strong></div>'; }
		echo '</div></div>';
		echo '</div></div>';
	}

	/* ---------------- coupons ---------------- */
	protected static function view_coupons() {
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$is_trash = ( $status === 'trash' );
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per = 20;
		$pstatus = $is_trash ? 'trash' : ( in_array( $status, array( 'publish', 'draft' ), true ) ? $status : array( 'publish', 'draft' ) );
		$q = new WP_Query( array( 'post_type' => 'shop_coupon', 'posts_per_page' => $per, 'paged' => $paged, 'post_status' => $pstatus, 's' => $s, 'orderby' => 'date', 'order' => 'DESC' ) );
		$cc = wp_count_posts( 'shop_coupon' );
		$n_pub = isset( $cc->publish ) ? (int) $cc->publish : 0; $n_draft = isset( $cc->draft ) ? (int) $cc->draft : 0; $n_trash = isset( $cc->trash ) ? (int) $cc->trash : 0;
		$types = wc_get_coupon_types();

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'coupons' ) ) . '">';
		if ( $status ) { echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '">'; }
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="Kupon kodu ara">';
		echo '<button class="btn tx-btn" type="submit">Ara</button>';
		echo '<a class="btn tx-btn primary" href="' . esc_url( self::url( 'coupon' ) ) . '"><i class="fas fa-plus mr-1"></i> Yeni kupon</a></form>';

		echo '<div class="tx-chips mb-3">';
		echo '<a class="tx-chip' . ( ! $status ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'coupons' ) ) . '">Tümü</a>';
		echo '<a class="tx-chip' . ( $status === 'publish' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'coupons', 0, array( 'status' => 'publish' ) ) ) . '">Yayında (' . $n_pub . ')</a>';
		echo '<a class="tx-chip' . ( $status === 'draft' ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'coupons', 0, array( 'status' => 'draft' ) ) ) . '">Taslak (' . $n_draft . ')</a>';
		echo '<a class="tx-chip tx-chip-trash' . ( $is_trash ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'coupons', 0, array( 'status' => 'trash' ) ) ) . '">🗑 Çöp (' . $n_trash . ')</a>';
		echo '</div>';

		echo '<form method="post" action="' . esc_url( $status ? self::url( 'coupons', 0, array( 'status' => $status ) ) : self::url( 'coupons' ) ) . '">';
		wp_nonce_field( 'wcp_coupons_bulk' );
		echo '<input type="hidden" name="wcp_action" value="coupons_bulk"><input type="hidden" name="ret_status" value="' . esc_attr( $status ) . '">';
		echo '<div class="tx-bulkbar mb-2"><select name="bulk" class="form-control tx-select">';
		if ( $is_trash ) { echo '<option value="">Toplu işlem…</option><option value="untrash">Geri yükle</option><option value="delete">Kalıcı sil</option>'; }
		else { echo '<option value="">Toplu işlem…</option><option value="publish">Yayınla</option><option value="draft">Taslak yap</option><option value="trash">Çöp kutusuna taşı</option>'; }
		echo '</select><button class="btn tx-btn" type="submit" onclick="return this.form.bulk.value!==\'\'">Uygula</button></div>';

		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th style="width:30px"><input type="checkbox" class="tx-checkall"></th><th>Kod</th><th>Tip</th><th class="text-right">Tutar</th><th>Son kullanma</th><th>Kullanım</th><th>Durum</th><th></th></tr></thead><tbody>';
		if ( ! $q->have_posts() ) { echo '<tr><td colspan="8"><div class="tx-empty">Kupon yok.</div></td></tr>'; }
		while ( $q->have_posts() ) {
			$q->the_post(); $cid = get_the_ID(); $co = new WC_Coupon( $cid );
			$type = $co->get_discount_type();
			$amt = $type === 'percent' ? '%' . esc_html( $co->get_amount() ) : self::money( $co->get_amount() );
			$exp = $co->get_date_expires() ? $co->get_date_expires()->date_i18n( 'd.m.Y' ) : '—';
			$ul = $co->get_usage_limit() ? $co->get_usage_count() . '/' . $co->get_usage_limit() : (string) $co->get_usage_count();
			$cst = get_post_status( $cid );
			$rn = wp_create_nonce( 'wcp_cuprow_' . $cid );
			echo '<tr><td><input type="checkbox" class="tx-cb" name="ids[]" value="' . esc_attr( $cid ) . '"></td>';
			echo '<td><a class="tx-strong" href="' . esc_url( self::url( 'coupon', $cid ) ) . '">' . esc_html( $co->get_code() ) . '</a>';
			echo '<div class="tx-row-actions"><a href="' . esc_url( self::url( 'coupon', $cid ) ) . '">Düzenle</a> · ';
			if ( $is_trash ) { echo '<a href="' . esc_url( self::url( 'coupons', 0, array( 'cuprow' => 'untrash', 'id' => $cid, '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '">Geri yükle</a> · <a class="tx-del" href="' . esc_url( self::url( 'coupons', 0, array( 'cuprow' => 'delete', 'id' => $cid, '_wpnonce' => $rn, 'ret_status' => 'trash' ) ) ) . '" onclick="return confirm(\'Kalıcı silinsin mi?\')">Kalıcı sil</a>'; }
			else { echo '<a class="tx-del" href="' . esc_url( self::url( 'coupons', 0, array( 'cuprow' => 'trash', 'id' => $cid, '_wpnonce' => $rn, 'ret_status' => $status ) ) ) . '">Çöpe at</a>'; }
			echo '</div></td>';
			echo '<td class="text-muted">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( $amt ) . '</td>';
			echo '<td class="text-muted">' . esc_html( $exp ) . '</td>';
			echo '<td class="text-muted">' . esc_html( $ul ) . '</td>';
			echo '<td><span class="tx-badge ' . ( $cst === 'publish' ? 'tx-ok' : 'tx-muted' ) . '">' . esc_html( $cst === 'publish' ? 'Yayında' : ( $cst === 'draft' ? 'Taslak' : $cst ) ) . '</span></td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'coupon', $cid ) ) . '">Düzenle</a></td></tr>';
		}
		wp_reset_postdata();
		echo '</tbody></table></div></div></div></form>';

		if ( $q->max_num_pages > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $q->max_num_pages, $from + 9 );
			for ( $i = $from; $i <= $to; $i++ ) { $a = array( 'paged' => $i ); if ( $status ) { $a['status'] = $status; } if ( $s ) { $a['s'] = $s; } echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'coupons', 0, $a ) ) . '">' . $i . '</a>'; }
			echo '</div>';
		}
		echo '<script>(function(){var a=document.querySelector(".tx-checkall");if(a)a.addEventListener("change",function(){document.querySelectorAll(".tx-cb").forEach(function(c){c.checked=a.checked;});});})();</script>';
	}

	protected static function view_coupon_form( $id ) {
		$co = $id ? new WC_Coupon( $id ) : null;
		if ( $id && ( ! $co || ! $co->get_id() ) ) { $co = null; }
		$is_new = ! $co;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'coupons' ) ) . '">← Kuponlara dön</a>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="alert tx-flash">Kupon kaydedildi.</div>'; }
		echo '<form method="post" action="' . esc_url( self::url( 'coupon', $id ? $id : 0 ) ) . '">';
		wp_nonce_field( 'wcp_coupon_' . ( $id ? $id : 0 ) );
		echo '<input type="hidden" name="wcp_action" value="coupon_save"><input type="hidden" name="coupon_id" value="' . esc_attr( $id ) . '">';
		echo '<div class="row"><div class="col-lg-8">';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . ( $is_new ? 'Yeni kupon' : 'Kuponu düzenle' ) . '</h3></div><div class="card-body">';
		echo '<label class="tx-label">Kupon kodu</label><input class="form-control mb-3" name="code" value="' . esc_attr( $co ? $co->get_code() : '' ) . '" required>';
		echo '<label class="tx-label">Açıklama</label><textarea class="form-control" name="description" rows="2">' . esc_textarea( $co ? $co->get_description() : '' ) . '</textarea>';
		echo '</div></div>';

		echo '<div class="card card-outline tx-card tx-pdata"><div class="card-header"><h3 class="card-title">Kupon verisi</h3></div>';
		echo '<ul class="nav nav-tabs tx-tabs" role="tablist">';
		$ct = array( 'cgenel' => 'Genel', 'ckisit' => 'Kullanım kısıtlaması', 'climit' => 'Kullanım limitleri' );
		$f = true; foreach ( $ct as $k => $lbl ) { echo '<li class="nav-item"><a class="nav-link' . ( $f ? ' active' : '' ) . '" data-toggle="tab" href="#tab-' . $k . '">' . esc_html( $lbl ) . '</a></li>'; $f = false; }
		echo '</ul><div class="card-body"><div class="tab-content">';

		$dt = $co ? $co->get_discount_type() : 'fixed_cart';
		$exp = $co && $co->get_date_expires() ? $co->get_date_expires()->date( 'Y-m-d' ) : '';
		echo '<div class="tab-pane fade show active" id="tab-cgenel"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">İndirim tipi</label><select class="form-control" name="discount_type">';
		foreach ( wc_get_coupon_types() as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $dt, $k, false ) . '>' . esc_html( $v ) . '</option>'; }
		echo '</select></div>';
		echo '<div><label class="tx-label">Kupon tutarı</label><input class="form-control" name="amount" value="' . esc_attr( $co ? $co->get_amount() : '' ) . '"></div>';
		echo '<div><label class="tx-label">Son kullanma tarihi</label><input type="date" class="form-control" name="date_expires" value="' . esc_attr( $exp ) . '"></div>';
		echo '<div class="full"><label class="tx-check"><input type="checkbox" name="free_shipping" value="1"' . checked( $co ? $co->get_free_shipping() : false, true, false ) . '> Ücretsiz kargo sağla</label></div>';
		echo '</div></div>';

		$allcats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
		$pc = $co ? $co->get_product_categories() : array(); $epc = $co ? $co->get_excluded_product_categories() : array();
		echo '<div class="tab-pane fade" id="tab-ckisit"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">Minimum harcama</label><input class="form-control" name="minimum_amount" value="' . esc_attr( $co ? $co->get_minimum_amount() : '' ) . '"></div>';
		echo '<div><label class="tx-label">Maksimum harcama</label><input class="form-control" name="maximum_amount" value="' . esc_attr( $co ? $co->get_maximum_amount() : '' ) . '"></div>';
		echo '<div class="full"><label class="tx-check"><input type="checkbox" name="individual_use" value="1"' . checked( $co ? $co->get_individual_use() : false, true, false ) . '> Yalnızca bireysel kullanım</label> &nbsp; <label class="tx-check"><input type="checkbox" name="exclude_sale_items" value="1"' . checked( $co ? $co->get_exclude_sale_items() : false, true, false ) . '> İndirimli ürünleri hariç tut</label></div>';
		echo '<div><label class="tx-label">Ürünler (SKU/ID, virgül)</label><input class="form-control" name="product_ids" value="' . esc_attr( $co ? self::refs_label( $co->get_product_ids() ) : '' ) . '"></div>';
		echo '<div><label class="tx-label">Hariç ürünler (SKU/ID)</label><input class="form-control" name="excluded_product_ids" value="' . esc_attr( $co ? self::refs_label( $co->get_excluded_product_ids() ) : '' ) . '"></div>';
		echo '<div><label class="tx-label">Kategoriler</label><select class="form-control" name="product_categories[]" multiple size="5">';
		foreach ( $allcats as $c ) { echo '<option value="' . esc_attr( $c->term_id ) . '"' . ( in_array( $c->term_id, $pc ) ? ' selected' : '' ) . '>' . esc_html( $c->name ) . '</option>'; }
		echo '</select></div>';
		echo '<div><label class="tx-label">Hariç kategoriler</label><select class="form-control" name="excluded_product_categories[]" multiple size="5">';
		foreach ( $allcats as $c ) { echo '<option value="' . esc_attr( $c->term_id ) . '"' . ( in_array( $c->term_id, $epc ) ? ' selected' : '' ) . '>' . esc_html( $c->name ) . '</option>'; }
		echo '</select></div>';
		echo '<div class="full"><label class="tx-label">İzinli e-postalar (virgül)</label><input class="form-control" name="email_restrictions" value="' . esc_attr( $co ? implode( ', ', $co->get_email_restrictions() ) : '' ) . '"></div>';
		echo '</div></div>';

		echo '<div class="tab-pane fade" id="tab-climit"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">Kupon başına kullanım limiti</label><input type="number" class="form-control" name="usage_limit" value="' . esc_attr( $co && $co->get_usage_limit() ? $co->get_usage_limit() : '' ) . '"></div>';
		echo '<div><label class="tx-label">X ürüne kadar sınırla</label><input type="number" class="form-control" name="limit_usage_to_x_items" value="' . esc_attr( $co && $co->get_limit_usage_to_x_items() ? $co->get_limit_usage_to_x_items() : '' ) . '"></div>';
		echo '<div><label class="tx-label">Kullanıcı başına limit</label><input type="number" class="form-control" name="usage_limit_per_user" value="' . esc_attr( $co && $co->get_usage_limit_per_user() ? $co->get_usage_limit_per_user() : '' ) . '"></div>';
		echo '</div></div>';

		echo '</div></div></div>';

		echo '</div><div class="col-lg-4">';
		$cst = $id ? get_post_status( $id ) : 'publish';
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Yayın</h3></div><div class="card-body">';
		echo '<label class="tx-label">Durum</label><select class="form-control tx-select mb-2" name="status"><option value="publish"' . selected( $cst, 'publish', false ) . '>Yayında</option><option value="draft"' . selected( $cst, 'draft', false ) . '>Taslak</option></select>';
		if ( $co ) { echo '<p class="text-muted" style="font-size:12.5px">Kullanım: ' . (int) $co->get_usage_count() . ( $co->get_usage_limit() ? ' / ' . (int) $co->get_usage_limit() : '' ) . '</p>'; }
		echo '<button class="btn btn-block tx-btn primary" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
		echo '</div></div>';
		echo '</div></div></form>';
	}

	/* ---------------- customers ---------------- */
	protected static function loc_label( $city, $state, $country ) {
		$st = $state;
		if ( $country ) { $states = WC()->countries->get_states( $country ); if ( is_array( $states ) && isset( $states[ $state ] ) ) { $st = $states[ $state ]; } }
		$cn = ( $country && isset( WC()->countries->countries[ $country ] ) ) ? WC()->countries->countries[ $country ] : '';
		return implode( ', ', array_filter( array( $city, $st, $cn ) ) );
	}

	protected static function view_customers() {
		$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per = 25;
		$args = array( 'role__in' => array( 'customer' ), 'number' => $per, 'paged' => $paged, 'orderby' => 'registered', 'order' => 'DESC', 'count_total' => true );
		if ( $s !== '' ) { $args['search'] = '*' . $s . '*'; $args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' ); }
		$q = new WP_User_Query( $args );
		$users = $q->get_results();
		$total = (int) $q->get_total();
		$pages = (int) ceil( $total / $per );

		echo '<form class="tx-filterbar mb-3" method="get" action="' . esc_url( self::url( 'customers' ) ) . '">';
		echo '<input type="text" class="form-control" name="s" value="' . esc_attr( $s ) . '" placeholder="İsim, e-posta veya kullanıcı adı ara">';
		echo '<button class="btn tx-btn" type="submit">Ara</button>';
		if ( $s !== '' ) { echo '<a class="btn tx-btn" href="' . esc_url( self::url( 'customers' ) ) . '">Temizle</a>'; }
		echo '<span class="tx-count">' . number_format_i18n( $total ) . ' müşteri</span></form>';

		echo '<div class="card card-outline tx-card"><div class="card-body p-0"><div class="tx-tablewrap"><table class="table table-hover tx-table tx-table-sm mb-0"><thead><tr><th>Müşteri</th><th>E-posta</th><th>Konum</th><th class="text-right">Sipariş</th><th class="text-right">Harcama</th><th>Son sipariş</th><th>Kayıt</th><th></th></tr></thead><tbody>';
		if ( empty( $users ) ) { echo '<tr><td colspan="8"><div class="tx-empty">Müşteri bulunamadı.</div></td></tr>'; }
		foreach ( $users as $u ) {
			$uid = $u->ID;
			$count = wc_get_customer_order_count( $uid );
			$spent = wc_get_customer_total_spent( $uid );
			$last = wc_get_customer_last_order( $uid );
			$lastd = ( $last && $last->get_date_created() ) ? $last->get_date_created()->date_i18n( 'd.m.Y' ) : '—';
			$reg = $u->user_registered ? date_i18n( 'd.m.Y', strtotime( $u->user_registered ) ) : '—';
			$loc = self::loc_label( get_user_meta( $uid, 'billing_city', true ), get_user_meta( $uid, 'billing_state', true ), get_user_meta( $uid, 'billing_country', true ) );
			$name = $u->display_name ? $u->display_name : $u->user_login;
			echo '<tr><td><a class="tx-userlink" href="' . esc_url( self::url( 'customer', $uid ) ) . '"><span class="tx-ava sm">' . esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ) . '</span> <span class="tx-strong">' . esc_html( $name ) . '</span></a><div class="tx-row-actions">@' . esc_html( $u->user_login ) . '</div></td>';
			echo '<td class="text-muted tx-trunc">' . esc_html( $u->user_email ) . '</td>';
			echo '<td class="text-muted tx-trunc">' . esc_html( $loc ? $loc : '—' ) . '</td>';
			echo '<td class="text-right tx-strong">' . (int) $count . '</td>';
			echo '<td class="text-right tx-strong">' . wp_kses_post( self::money( $spent ) ) . '</td>';
			echo '<td class="text-muted">' . esc_html( $lastd ) . '</td>';
			echo '<td class="text-muted">' . esc_html( $reg ) . '</td>';
			echo '<td class="text-right"><a class="btn btn-sm tx-btn" href="' . esc_url( self::url( 'customer', $uid ) ) . '">Görüntüle</a></td></tr>';
		}
		echo '</tbody></table></div></div></div>';

		if ( $pages > 1 ) {
			echo '<div class="tx-pager">';
			$from = max( 1, $paged - 4 ); $to = min( $pages, $from + 9 );
			if ( $from > 1 ) { $a = array( 'paged' => 1 ); if ( $s !== '' ) { $a['s'] = $s; } echo '<a class="tx-page" href="' . esc_url( self::url( 'customers', 0, $a ) ) . '">1</a><span class="tx-page-gap">…</span>'; }
			for ( $i = $from; $i <= $to; $i++ ) { $a = array( 'paged' => $i ); if ( $s !== '' ) { $a['s'] = $s; } echo '<a class="tx-page' . ( $i === $paged ? ' is-on' : '' ) . '" href="' . esc_url( self::url( 'customers', 0, $a ) ) . '">' . $i . '</a>'; }
			if ( $to < $pages ) { $a = array( 'paged' => $pages ); if ( $s !== '' ) { $a['s'] = $s; } echo '<span class="tx-page-gap">…</span><a class="tx-page" href="' . esc_url( self::url( 'customers', 0, $a ) ) . '">' . $pages . '</a>'; }
			echo '</div>';
		}
	}

	protected static function addr_fields( $prefix, $uid, $contact ) {
		$v = function ( $f ) use ( $prefix, $uid ) { return get_user_meta( $uid, $prefix . '_' . $f, true ); };
		$country = $v( 'country' ); if ( ! $country ) { $country = 'TR'; }
		echo '<div class="tx-formgrid">';
		echo '<div><label class="tx-label">Ad</label><input class="form-control" name="' . $prefix . '_first_name" value="' . esc_attr( $v( 'first_name' ) ) . '"></div>';
		echo '<div><label class="tx-label">Soyad</label><input class="form-control" name="' . $prefix . '_last_name" value="' . esc_attr( $v( 'last_name' ) ) . '"></div>';
		echo '<div class="full"><label class="tx-label">Şirket</label><input class="form-control" name="' . $prefix . '_company" value="' . esc_attr( $v( 'company' ) ) . '"></div>';
		echo '<div class="full"><label class="tx-label">Adres satırı 1</label><input class="form-control" name="' . $prefix . '_address_1" value="' . esc_attr( $v( 'address_1' ) ) . '"></div>';
		echo '<div class="full"><label class="tx-label">Adres satırı 2</label><input class="form-control" name="' . $prefix . '_address_2" value="' . esc_attr( $v( 'address_2' ) ) . '"></div>';
		echo '<div><label class="tx-label">İlçe / Şehir</label><input class="form-control" name="' . $prefix . '_city" value="' . esc_attr( $v( 'city' ) ) . '"></div>';
		$states = WC()->countries->get_states( $country );
		echo '<div><label class="tx-label">İl</label>';
		if ( is_array( $states ) && ! empty( $states ) ) {
			echo '<select class="form-control tx-select" name="' . $prefix . '_state"><option value="">—</option>';
			foreach ( $states as $code => $sname ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( $v( 'state' ), $code, false ) . '>' . esc_html( $sname ) . '</option>'; }
			echo '</select>';
		} else {
			echo '<input class="form-control" name="' . $prefix . '_state" value="' . esc_attr( $v( 'state' ) ) . '">';
		}
		echo '</div>';
		echo '<div><label class="tx-label">Posta kodu</label><input class="form-control" name="' . $prefix . '_postcode" value="' . esc_attr( $v( 'postcode' ) ) . '"></div>';
		echo '<div><label class="tx-label">Ülke</label><select class="form-control tx-select" name="' . $prefix . '_country">';
		foreach ( WC()->countries->get_countries() as $code => $cname ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( $country, $code, false ) . '>' . esc_html( $cname ) . '</option>'; }
		echo '</select></div>';
		echo '<div><label class="tx-label">Telefon</label><input class="form-control" name="' . $prefix . '_phone" value="' . esc_attr( $v( 'phone' ) ) . '"></div>';
		if ( $contact ) { echo '<div><label class="tx-label">E-posta</label><input class="form-control" name="' . $prefix . '_email" value="' . esc_attr( $v( 'email' ) ) . '"></div>'; }
		echo '</div>';
	}

	protected static function view_customer( $id ) {
		$u = $id ? get_user_by( 'id', $id ) : null;
		if ( ! $u ) { echo '<a class="tx-back" href="' . esc_url( self::url( 'customers' ) ) . '">← Müşterilere dön</a><div class="tx-empty">Müşteri bulunamadı.</div>'; return; }
		$uid = $u->ID;
		if ( ! self::can_edit_target_user( $uid ) ) { echo '<a class="tx-back" href="' . esc_url( self::url( 'customers' ) ) . '">← Müşterilere dön</a><div class="tx-empty">Bu kullanıcıyı görüntüleme/düzenleme yetkiniz yok.</div>'; return; }
		$count = wc_get_customer_order_count( $uid );
		$spent = wc_get_customer_total_spent( $uid );
		$aov = $count > 0 ? $spent / $count : 0;
		echo '<a class="tx-back" href="' . esc_url( self::url( 'customers' ) ) . '">← Müşterilere dön</a>';
		if ( isset( $_GET['msg'] ) ) { echo '<div class="alert tx-flash">Müşteri kaydedildi.</div>'; }
		echo '<form method="post" action="' . esc_url( self::url( 'customer', $uid ) ) . '">';
		wp_nonce_field( 'wcp_customer_' . $uid );
		echo '<input type="hidden" name="wcp_action" value="customer_save"><input type="hidden" name="customer_id" value="' . esc_attr( $uid ) . '">';
		echo '<div class="row"><div class="col-lg-8">';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Profil</h3></div><div class="card-body"><div class="tx-formgrid">';
		echo '<div><label class="tx-label">Ad</label><input class="form-control" name="first_name" value="' . esc_attr( get_user_meta( $uid, 'first_name', true ) ) . '"></div>';
		echo '<div><label class="tx-label">Soyad</label><input class="form-control" name="last_name" value="' . esc_attr( get_user_meta( $uid, 'last_name', true ) ) . '"></div>';
		echo '<div><label class="tx-label">Görünen ad</label><input class="form-control" name="display_name" value="' . esc_attr( $u->display_name ) . '"></div>';
		echo '<div><label class="tx-label">E-posta</label><input type="email" class="form-control" name="user_email" value="' . esc_attr( $u->user_email ) . '"></div>';
		echo '<div><label class="tx-label">Kullanıcı adı</label><input class="form-control" value="' . esc_attr( $u->user_login ) . '" disabled></div>';
		echo '<div><label class="tx-label">Rol</label><select class="form-control tx-select" name="role">';
		$cur_role = ! empty( $u->roles ) ? $u->roles[0] : 'customer';
		$assignable = self::assignable_roles();
		foreach ( get_editable_roles() as $rk => $r ) {
			if ( ! in_array( $rk, $assignable, true ) && $rk !== $cur_role ) { continue; }
			echo '<option value="' . esc_attr( $rk ) . '"' . selected( $cur_role, $rk, false ) . '>' . esc_html( translate_user_role( $r['name'] ) ) . '</option>';
		}
		echo '</select></div>';
		echo '<div class="full"><label class="tx-label">Yeni şifre <span class="text-muted">(boş bırakırsan değişmez)</span></label><input type="text" class="form-control" name="new_password" value="" autocomplete="new-password"></div>';
		echo '</div></div></div>';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Fatura adresi</h3></div><div class="card-body">';
		self::addr_fields( 'billing', $uid, true );
		echo '</div></div>';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Teslimat adresi</h3></div><div class="card-body">';
		self::addr_fields( 'shipping', $uid, false );
		echo '</div></div>';

		echo '</div><div class="col-lg-4">';

		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">' . esc_html( $u->display_name ? $u->display_name : $u->user_login ) . '</h3></div><div class="card-body">';
		echo '<button class="btn btn-block tx-btn primary mb-3" type="submit"><i class="fas fa-save mr-1"></i> Kaydet</button>';
		echo '<div class="tx-stat"><span>Sipariş</span><strong>' . (int) $count . '</strong></div>';
		echo '<div class="tx-stat"><span>Toplam harcama</span><strong>' . wp_kses_post( self::money( $spent ) ) . '</strong></div>';
		echo '<div class="tx-stat"><span>Ortalama sepet</span><strong>' . wp_kses_post( self::money( $aov ) ) . '</strong></div>';
		echo '<div class="tx-stat"><span>Kayıt tarihi</span><strong>' . esc_html( $u->user_registered ? date_i18n( 'd.m.Y', strtotime( $u->user_registered ) ) : '—' ) . '</strong></div>';
		$phone = get_user_meta( $uid, 'billing_phone', true );
		if ( $phone ) { echo '<div class="tx-stat"><span>Telefon</span><strong>' . esc_html( $phone ) . '</strong></div>'; }
		echo '</div></div>';

		$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
		$orders = wc_get_orders( array( 'customer_id' => $uid, 'limit' => 10, 'orderby' => 'date', 'order' => 'DESC', 'status' => $statuses ) );
		echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title">Son siparişler</h3></div><div class="card-body p-0">';
		if ( empty( $orders ) ) { echo '<div class="tx-empty">Sipariş yok.</div>'; }
		else {
			echo '<table class="table tx-table tx-table-sm mb-0"><tbody>';
			foreach ( $orders as $o ) {
				echo '<tr><td><a class="tx-strong" href="' . esc_url( self::url( 'order', $o->get_id() ) ) . '">#' . esc_html( $o->get_order_number() ) . '</a><div class="tx-row-actions">' . esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'd.m.Y' ) : '' ) . '</div></td><td><span class="tx-badge ' . esc_attr( self::badge_class( $o->get_status() ) ) . '">' . esc_html( self::status_label( $o->get_status() ) ) . '</span></td><td class="text-right tx-strong">' . wp_kses_post( self::money( $o->get_total() ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';

		echo '</div></div></form>';
	}
}
WCP_Panel::init();
