<?php
/**
 * Plugin Name: WCP Kargo — Yurtiçi self-servis entegrasyonu (bağımsız)
 * Description: Panelden takip no gir → "Kargoya Verildi" + müşteriye e-posta; Yurtiçi WS ile otomatik durum sorgusu → teslimde "Tamamlandı" + e-posta. Hezarfen'den bağımsız.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
   Ayarlar (Yurtiçi WS bilgileri panelden girilir)
   ============================================================ */
function wcp_kargo_opt( $k, $def = '' ) { $o = get_option( 'wcp_kargo', array() ); return isset( $o[ $k ] ) && $o[ $k ] !== '' ? $o[ $k ] : $def; }
function wcp_kargo_set( $arr ) { $o = get_option( 'wcp_kargo', array() ); update_option( 'wcp_kargo', array_merge( is_array( $o ) ? $o : array(), $arr ) ); }

/* ============================================================
   Özel sipariş durumu: "Kargoya Verildi" (wc-kargoda)
   ============================================================ */
add_action( 'init', function () {
	register_post_status( 'wc-kargoda', array(
		'label'                     => 'Kargoya Verildi',
		'public'                    => true,
		'show_in_admin_status_list' => true,
		'show_in_admin_all_list'    => true,
		/* translators: %s: order count */
		'label_count'               => _n_noop( 'Kargoya Verildi <span class="count">(%s)</span>', 'Kargoya Verildi <span class="count">(%s)</span>' ),
	) );
} );
add_filter( 'wc_order_statuses', function ( $st ) {
	$new = array();
	foreach ( $st as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'wc-processing' === $k ) { $new['wc-kargoda'] = 'Kargoya Verildi'; }
	}
	if ( ! isset( $new['wc-kargoda'] ) ) { $new['wc-kargoda'] = 'Kargoya Verildi'; }
	return $new;
} );
/* "Kargoya Verildi" siparişler yönetici e-posta/rapor akışında sayılsın */
add_filter( 'woocommerce_reports_order_statuses', function ( $s ) { $s[] = 'kargoda'; return $s; } );

/* ============================================================
   Kargoya ver: takip no kaydet + durum + müşteri e-postası
   ============================================================ */
function wcp_kargo_no_temizle( $t ) { return preg_replace( '/[^0-9A-Za-z]/', '', (string) $t ); }
function wcp_kargo_takip_url( $tno ) { return 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . rawurlencode( $tno ); }

function wcp_kargo_ver( $order_id, $tracking, $notify = true ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return false; }
	$tno = wcp_kargo_no_temizle( $tracking );
	if ( $tno === '' ) { return false; }
	$order->update_meta_data( '_wcp_kargo_firma', 'Yurtiçi Kargo' );
	$order->update_meta_data( '_wcp_kargo_no', $tno );
	$order->update_meta_data( '_wcp_kargo_url', wcp_kargo_takip_url( $tno ) );
	$order->update_meta_data( '_wcp_kargo_tarih', current_time( 'mysql' ) );
	$order->save();
	if ( $order->get_status() !== 'kargoda' && $order->get_status() !== 'completed' ) {
		$order->update_status( 'kargoda', 'Kargo takip no girildi: ' . $tno . '. ' );
	} else {
		$order->add_order_note( 'Kargo takip no güncellendi: ' . $tno );
	}
	if ( $notify ) { wcp_kargo_mail_shipped( $order ); }
	return true;
}

/* ============================================================
   Yurtiçi Kargo Web Servis — durum sorgusu
   (creds gelince canlı test edilip parser kesinleştirilecek)
   ============================================================ */
function wcp_yk_query( $tracking ) {
	$user = wcp_kargo_opt( 'ws_user' ); $pass = wcp_kargo_opt( 'ws_pass' );
	$lang = wcp_kargo_opt( 'ws_lang', 'TR' );
	$wsdl = wcp_kargo_opt( 'ws_wsdl', 'https://ws.yurticikargo.com/KOPSWebServices/QueryShipmentDetailServices?wsdl' );
	if ( $user === '' || $pass === '' ) { return array( 'ok' => false, 'reason' => 'no_creds' ); }
	if ( ! class_exists( 'SoapClient' ) ) { return array( 'ok' => false, 'reason' => 'no_soap' ); }
	$tno = wcp_kargo_no_temizle( $tracking );
	try {
		$client = new SoapClient( $wsdl, array( 'trace' => 1, 'exceptions' => true, 'connection_timeout' => 15, 'cache_wsdl' => WSDL_CACHE_NONE ) );
		$resp = $client->queryShipmentDetail( array(
			'wsUserName'        => $user,
			'wsPassword'        => $pass,
			'wsLanguage'        => $lang,
			'keys'              => array( $tno ),
			'keyType'           => 0, // 0: kargo takip no
			'addHistoricalData' => true,
			'onlyTracking'      => false,
		) );
		$raw = wp_json_encode( $resp );
		$delivered = false;
		$status_text = '';
		// Yanıtta teslim göstergesi ara (kesin alanlar creds testinde netleşecek)
		if ( is_object( $resp ) || is_array( $resp ) ) {
			$flat = strtoupper( (string) $raw );
			if ( strpos( $flat, 'TESL' ) !== false && ( strpos( $flat, 'TESLIM ED' ) !== false || strpos( $flat, 'TESLİM ED' ) !== false || strpos( $flat, 'DELIVERED' ) !== false ) ) { $delivered = true; }
			// durum metni: yanıttan makul bir açıklama çek
			if ( preg_match( '/"(?:cargoEventExplanation|operationMessage|statusName|deliveryStatusExplanation)"\s*:\s*"([^"]+)"/u', (string) $raw, $m ) ) { $status_text = $m[1]; }
		}
		return array( 'ok' => true, 'delivered' => $delivered, 'status' => $status_text, 'raw' => $raw );
	} catch ( \Throwable $e ) {
		return array( 'ok' => false, 'reason' => 'soap_error', 'error' => $e->getMessage() );
	}
}

/* Tek siparişi sorgula + gerekiyorsa tamamla */
function wcp_kargo_sorgula( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return array( 'ok' => false, 'reason' => 'no_order' ); }
	$tno = $order->get_meta( '_wcp_kargo_no' );
	if ( ! $tno ) { return array( 'ok' => false, 'reason' => 'no_tracking' ); }
	$r = wcp_yk_query( $tno );
	if ( ! empty( $r['ok'] ) ) {
		$order->update_meta_data( '_wcp_kargo_durum', $r['status'] );
		$order->update_meta_data( '_wcp_kargo_sorgu_tarih', current_time( 'mysql' ) );
		$order->save();
		$prev = $order->get_meta( '_wcp_kargo_durum_prev' );
		if ( $r['status'] && $r['status'] !== $prev ) {
			$order->add_order_note( 'Kargo durumu: ' . $r['status'] );
			$order->update_meta_data( '_wcp_kargo_durum_prev', $r['status'] ); $order->save();
		}
		if ( ! empty( $r['delivered'] ) && $order->get_status() === 'kargoda' ) {
			$order->update_status( 'completed', 'Yurtiçi: teslim edildi (otomatik).' );
			wcp_kargo_mail_delivered( $order );
		}
	}
	return $r;
}

/* ============================================================
   Cron — saatlik: "Kargoya Verildi" siparişleri sorgula
   ============================================================ */
add_filter( 'cron_schedules', function ( $s ) { if ( ! isset( $s['wcp_hourly'] ) ) { $s['wcp_hourly'] = array( 'interval' => 3600, 'display' => 'WCP Saatlik' ); } return $s; } );
add_action( 'init', function () { if ( ! wp_next_scheduled( 'wcp_kargo_poll' ) ) { wp_schedule_event( time() + 300, 'wcp_hourly', 'wcp_kargo_poll' ); } } );
add_action( 'wcp_kargo_poll', function () {
	if ( wcp_kargo_opt( 'ws_user' ) === '' ) { return; } // creds yoksa çalışma
	$orders = wc_get_orders( array( 'status' => 'kargoda', 'limit' => 60, 'orderby' => 'modified', 'order' => 'ASC', 'return' => 'ids' ) );
	foreach ( $orders as $oid ) { wcp_kargo_sorgula( $oid ); }
} );

/* ============================================================
   AJAX — panelden "Şimdi sorgula" / "Kargoya ver"
   ============================================================ */
add_action( 'wp_ajax_wcp_kargo_query', function () {
	if ( ! current_user_can( 'edit_shop_orders' ) ) { wp_send_json_error( 'yetki' ); }
	check_ajax_referer( 'wcp_kargo' );
	$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$r = wcp_kargo_sorgula( $oid );
	if ( ! empty( $r['ok'] ) ) { wp_send_json_success( array( 'durum' => $r['status'] ? $r['status'] : 'Bilgi alındı', 'teslim' => ! empty( $r['delivered'] ) ) ); }
	wp_send_json_error( isset( $r['reason'] ) ? $r['reason'] : 'hata' );
} );

/* ============================================================
   Müşteri e-postaları (markalı HTML)
   ============================================================ */
function wcp_kargo_mail_wrap( $baslik, $govde ) {
	$logo = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 128 ) : '';
	$site = get_bloginfo( 'name' );
	ob_start(); ?>
<div style="background:#f4f6f9;padding:28px 0;font-family:Segoe UI,Arial,sans-serif">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(20,23,28,.08)">
<div style="background:#0078D4;padding:20px 26px;color:#fff;font-size:18px;font-weight:700"><?php echo esc_html( $site ); ?></div>
<div style="padding:26px"><h2 style="margin:0 0 14px;color:#1f2430;font-size:20px"><?php echo esc_html( $baslik ); ?></h2><?php echo $govde; // phpcs:ignore ?></div>
<div style="padding:16px 26px;background:#f7f8fa;color:#8a94a6;font-size:12px">Bu e-posta <?php echo esc_html( $site ); ?> tarafından gönderilmiştir.</div>
</div></div>
<?php return ob_get_clean();
}
function wcp_kargo_mail_shipped( $order ) {
	$to = $order->get_billing_email(); if ( ! $to ) { return; }
	$tno = $order->get_meta( '_wcp_kargo_no' ); $url = $order->get_meta( '_wcp_kargo_url' );
	$ad = trim( $order->get_billing_first_name() );
	$govde = '<p style="color:#39414f;line-height:1.6">Merhaba ' . esc_html( $ad ) . ',<br>#' . esc_html( $order->get_order_number() ) . ' numaralı siparişiniz <b>kargoya verildi</b> 🚚</p>'
		. '<p style="color:#39414f">Kargo firması: <b>Yurtiçi Kargo</b><br>Takip numarası: <b>' . esc_html( $tno ) . '</b></p>'
		. '<p style="margin:22px 0"><a href="' . esc_url( $url ) . '" style="background:#0078D4;color:#fff;text-decoration:none;padding:12px 22px;border-radius:9px;font-weight:600;display:inline-block">Kargomu Takip Et</a></p>'
		. '<p style="color:#8a94a6;font-size:13px">Teslimat sağlandığında bilgilendirileceksiniz.</p>';
	wp_mail( $to, 'Siparişiniz kargoya verildi — #' . $order->get_order_number(), wcp_kargo_mail_wrap( 'Siparişiniz yolda!', $govde ), array( 'Content-Type: text/html; charset=UTF-8' ) );
}
function wcp_kargo_mail_delivered( $order ) {
	$to = $order->get_billing_email(); if ( ! $to ) { return; }
	$ad = trim( $order->get_billing_first_name() );
	$govde = '<p style="color:#39414f;line-height:1.6">Merhaba ' . esc_html( $ad ) . ',<br>#' . esc_html( $order->get_order_number() ) . ' numaralı siparişiniz <b>teslim edildi</b> ✅</p>'
		. '<p style="color:#39414f">Bizi tercih ettiğiniz için teşekkür ederiz. Ürününüzü beğeneceğinizi umuyoruz!</p>'
		. '<p style="color:#8a94a6;font-size:13px">Herhangi bir sorunuz olursa bize ulaşabilirsiniz.</p>';
	wp_mail( $to, 'Siparişiniz teslim edildi — #' . $order->get_order_number(), wcp_kargo_mail_wrap( 'Siparişiniz teslim edildi 🎉', $govde ), array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/* ============================================================
   AJAX: kargoya ver + WS ayarları kaydet
   ============================================================ */
add_action( 'wp_ajax_wcp_kargo_ver', function () {
	if ( ! current_user_can( 'edit_shop_orders' ) ) { wp_send_json_error( 'yetki' ); }
	check_ajax_referer( 'wcp_kargo' );
	$oid = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$tno = isset( $_POST['tracking'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking'] ) ) : '';
	if ( wcp_kargo_ver( $oid, $tno ) ) { wp_send_json_success(); }
	wp_send_json_error( 'kaydedilemedi' );
} );
add_action( 'wp_ajax_wcp_kargo_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error( 'yetki' ); }
	check_ajax_referer( 'wcp_kargo' );
	wcp_kargo_set( array(
		'ws_user' => isset( $_POST['ws_user'] ) ? sanitize_text_field( wp_unslash( $_POST['ws_user'] ) ) : '',
		'ws_pass' => isset( $_POST['ws_pass'] ) ? sanitize_text_field( wp_unslash( $_POST['ws_pass'] ) ) : '',
		'ws_lang' => isset( $_POST['ws_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['ws_lang'] ) ) : 'TR',
		'ws_wsdl' => isset( $_POST['ws_wsdl'] ) ? esc_url_raw( wp_unslash( $_POST['ws_wsdl'] ) ) : '',
	) );
	wp_send_json_success();
} );

/* ============================================================
   Panel sipariş detayı kargo kutusu (wcp-panel.php view_order'dan çağrılır)
   ============================================================ */
function wcp_kargo_panel_box( $order ) {
	if ( ! is_a( $order, 'WC_Order' ) ) { $order = wc_get_order( $order ); }
	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) ) { return; }
	$oid   = $order->get_id();
	$tno   = $order->get_meta( '_wcp_kargo_no' );
	$url   = $order->get_meta( '_wcp_kargo_url' );
	$durum = $order->get_meta( '_wcp_kargo_durum' );
	$nonce = wp_create_nonce( 'wcp_kargo' );
	$ajax  = admin_url( 'admin-ajax.php' );
	$creds = wcp_kargo_opt( 'ws_user' ) !== '';
	$admin = current_user_can( 'manage_woocommerce' );

	echo '<div class="card card-outline tx-card"><div class="card-header"><h3 class="card-title"><i class="fas fa-truck-fast mr-2"></i>Kargo (Yurtiçi)</h3>';
	echo '<div class="card-tools"><span class="tx-badge ' . ( $creds ? 'tx-ok' : 'tx-muted' ) . '">' . ( $creds ? 'Otomatik açık' : 'WS bilgisi yok' ) . '</span></div></div><div class="card-body">';
	if ( $tno ) {
		echo '<div class="tx-kv"><div><span>Takip No</span><b><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $tno ) . '</a></b></div>';
		echo '<div><span>Son durum</span><b id="wcp-k-durum">' . esc_html( $durum ? $durum : '—' ) . '</b></div></div>';
	}
	echo '<label class="tx-label mt-2">Yurtiçi takip no</label><div style="display:flex;gap:8px"><input class="form-control" id="wcp-k-no" value="' . esc_attr( $tno ) . '" placeholder="örn. 101178013035"><button class="btn tx-btn primary" type="button" id="wcp-k-ver">Kargoya Ver</button></div>';
	if ( $tno ) { echo '<button class="btn tx-btn mt-2" type="button" id="wcp-k-sorgu"' . ( $creds ? '' : ' disabled' ) . '><i class="fas fa-rotate mr-1"></i>Durumu şimdi sorgula</button>'; }
	echo '<div id="wcp-k-msg" class="tx-mini mt-2" style="min-height:16px"></div>';

	if ( $admin ) {
		echo '<details class="mt-2"><summary style="cursor:pointer;color:#5b6677;font-size:13px">Yurtiçi WS ayarları</summary><div class="mt-2">';
		echo '<input class="form-control mb-1" id="wcp-ws-user" placeholder="WS kullanıcı adı" value="' . esc_attr( wcp_kargo_opt( 'ws_user' ) ) . '">';
		echo '<input class="form-control mb-1" id="wcp-ws-pass" type="password" placeholder="WS şifre" value="' . esc_attr( wcp_kargo_opt( 'ws_pass' ) ) . '">';
		echo '<input class="form-control mb-1" id="wcp-ws-lang" placeholder="Dil (TR)" value="' . esc_attr( wcp_kargo_opt( 'ws_lang', 'TR' ) ) . '">';
		echo '<input class="form-control mb-1" id="wcp-ws-wsdl" placeholder="WSDL URL (boş=varsayılan)" value="' . esc_attr( wcp_kargo_opt( 'ws_wsdl' ) ) . '">';
		echo '<button class="btn tx-btn mt-1" type="button" id="wcp-ws-save">Ayarları kaydet</button></div></details>';
	}
	?>
	<script>(function(){
		var AJAX=<?php echo wp_json_encode( $ajax ); ?>, N=<?php echo wp_json_encode( $nonce ); ?>, OID=<?php echo (int) $oid; ?>;
		function post(a,ex,cb){ var b=new URLSearchParams(); b.append('action',a); b.append('_ajax_nonce',N); b.append('order_id',OID); for(var k in ex)b.append(k,ex[k]); fetch(AJAX,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(function(r){return r.json();}).then(cb).catch(function(){cb({success:false});}); }
		var msg=document.getElementById('wcp-k-msg');
		var ver=document.getElementById('wcp-k-ver'); if(ver)ver.addEventListener('click',function(){ var t=document.getElementById('wcp-k-no').value.trim(); if(!t){msg.textContent='Takip no girin.';return;} ver.disabled=true; msg.textContent='Kaydediliyor...'; post('wcp_kargo_ver',{tracking:t},function(r){ msg.textContent=(r&&r.success)?'✓ Kargoya verildi, müşteriye e-posta gitti.':'Hata'; setTimeout(function(){location.reload();},1000); }); });
		var sg=document.getElementById('wcp-k-sorgu'); if(sg)sg.addEventListener('click',function(){ sg.disabled=true; msg.textContent='Sorgulanıyor...'; post('wcp_kargo_query',{},function(r){ sg.disabled=false; if(r&&r.success){ msg.textContent='Durum: '+((r.data&&r.data.durum)||'')+((r.data&&r.data.teslim)?' → TESLİM (Tamamlandı)':''); if(r.data&&r.data.teslim)setTimeout(function(){location.reload();},1300);} else { msg.textContent='Sorgu başarısız'+((r&&r.data)?(': '+r.data):''); } }); });
		var ws=document.getElementById('wcp-ws-save'); if(ws)ws.addEventListener('click',function(){ ws.disabled=true; post('wcp_kargo_settings',{ws_user:document.getElementById('wcp-ws-user').value,ws_pass:document.getElementById('wcp-ws-pass').value,ws_lang:document.getElementById('wcp-ws-lang').value,ws_wsdl:document.getElementById('wcp-ws-wsdl').value},function(r){ ws.disabled=false; msg.textContent=(r&&r.success)?'✓ WS ayarları kaydedildi.':'Kaydedilemedi'; }); });
	})();</script>
	<?php
	echo '</div></div>';
}
