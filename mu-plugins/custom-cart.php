<?php
/**
 * Plugin Name: Store Özel Sepet
 * Description: [wcp_cart] — tamamen özgün, premium sepet. AJAX otomatik adet güncelleme; özet/teslimat/kupon/ödeme WooCommerce işlevleriyle korunur.
 * Version: 2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode( 'wcp_cart', 'wcp_render_custom_cart' );

function wcp_render_custom_cart() {
	if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
		return '';
	}
	WC()->cart->calculate_totals();
	ob_start();

	do_action( 'woocommerce_before_cart' );

	$shop_url = ( $sid = wc_get_page_id( 'shop' ) ) ? get_permalink( $sid ) : home_url();

	if ( WC()->cart->is_empty() ) {
		?>
		<div class="wcpc-empty">
			<div class="wcpc-empty-ic">
				<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.6a1.5 1.5 0 0 0 1.5 1.2h8.7a1.5 1.5 0 0 0 1.5-1.2L22 7H6"/></svg>
			</div>
			<h2>Sepetiniz şu an boş</h2>
			<p>Surface Pro ve Copilot+ PC modellerini keşfedin, beğendiğinizi sepete ekleyin.</p>
			<a class="wcpc-btn-primary" href="<?php echo esc_url( $shop_url ); ?>">Alışverişe başla</a>
		</div>
		<?php
		return ob_get_clean();
	}

	$count = WC()->cart->get_cart_contents_count();
	?>
	<div class="wcpc">
		<div class="wcpc-head">
			<div>
				<h1>Sepetim</h1>
				<span class="wcpc-count"><?php echo (int) $count; ?> ürün</span>
			</div>
			<a class="wcpc-continue" href="<?php echo esc_url( $shop_url ); ?>">&larr; Alışverişe devam et</a>
		</div>

		<div class="wcpc-grid">
			<div class="wcpc-main">
				<form class="wcpc-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
					<div class="wcpc-items">
						<?php
						foreach ( WC()->cart->get_cart() as $key => $item ) {
							$p = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key );
							if ( ! $p || ! $p->exists() || $item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $item, $key ) ) {
								continue;
							}
							$perma = $p->is_visible() ? $p->get_permalink( $item ) : '';
							$name  = $p->get_name();
							?>
							<div class="wcpc-item">
								<?php if ( $perma ) : ?><a class="wcpc-thumb" href="<?php echo esc_url( $perma ); ?>"><?php echo $p->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore ?></a>
								<?php else : ?><span class="wcpc-thumb"><?php echo $p->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore ?></span><?php endif; ?>

								<div class="wcpc-info">
									<?php if ( $perma ) : ?><a class="wcpc-name" href="<?php echo esc_url( $perma ); ?>"><?php echo esc_html( $name ); ?></a>
									<?php else : ?><span class="wcpc-name"><?php echo esc_html( $name ); ?></span><?php endif; ?>
									<div class="wcpc-unit"><?php echo WC()->cart->get_product_price( $p ); // phpcs:ignore ?> <span>/ adet</span></div>
									<?php echo wc_get_formatted_cart_item_data( $item ); // phpcs:ignore ?>
								</div>

								<div class="wcpc-qty">
									<?php
									echo woocommerce_quantity_input(
										array(
											'input_name'  => "cart[{$key}][qty]",
											'input_value' => $item['quantity'],
											'min_value'   => $p->is_sold_individually() ? 1 : 0,
											'max_value'   => $p->get_max_purchase_quantity(),
											'product_name'=> $name,
										),
										$p,
										false
									);
									?>
								</div>

								<div class="wcpc-sub"><?php echo WC()->cart->get_product_subtotal( $p, $item['quantity'] ); // phpcs:ignore ?></div>

								<a class="wcpc-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $key ) ); ?>" aria-label="<?php esc_attr_e( 'Ürünü kaldır', 'woocommerce' ); ?>" title="Kaldır">
									<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>
								</a>
							</div>
							<?php
						}
						?>
					</div>

					<div class="wcpc-bar">
						<?php if ( wc_coupons_enabled() ) : ?>
							<div class="wcpc-coupon">
								<input type="text" name="coupon_code" id="coupon_code" class="input-text" placeholder="İndirim kuponunuz var mı?" />
								<button type="submit" class="wcpc-btn-ghost" name="apply_coupon" value="1">Uygula</button>
							</div>
						<?php endif; ?>
						<button type="submit" class="wcpc-btn-ghost wcpc-update" name="update_cart" value="1">Sepeti güncelle</button>
						<?php do_action( 'woocommerce_cart_actions' ); ?>
						<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
					</div>
				</form>
			</div>

			<aside class="wcpc-summary">
				<div class="wcpc-sumcard">
					<h3 class="wcpc-sumtitle">Sipariş Özeti</h3>
					<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
					<?php woocommerce_cart_totals(); ?>
					<a class="wcpc-pay" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
						Ödeme adımına geç
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</a>
					<ul class="wcpc-trust">
						<li><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg> 256-bit SSL ile güvenli ödeme</li>
						<li><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 13l4 4L19 7"/></svg> Türkiye geneli ücretsiz kargo</li>
						<li><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 13l4 4L19 7"/></svg> Orijinal &amp; faturalı ürün</li>
					</ul>
				</div>
			</aside>
		</div>

		<div class="wcpc-cross"><?php woocommerce_cross_sell_display(); ?></div>
	</div>

	<script>
	(function () {
		function qs(s, r){ return (r||document).querySelector(s); }
		var t;
		function busy(on){ var g=qs('.wcpc-grid'); if(g) g.classList.toggle('wcpc-busy', !!on); }
		function schedule(){ clearTimeout(t); busy(true); t=setTimeout(run, 550); }
		function run(){
			var f=qs('.wcpc-form'); if(!f){ busy(false); return; }
			var fd=new FormData(f); fd.set('update_cart','1');
			fetch(f.action,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.text();}).then(function(html){
				replaceFrom(html);
			}).catch(function(){ busy(false); });
		}
		function replaceFrom(html){
			var d=new DOMParser().parseFromString(html,'text/html');
			var ng=d.querySelector('.wcpc-grid');
			if(!ng){ window.location.reload(); return; }
			var cg=qs('.wcpc-grid'); if(cg) cg.innerHTML=ng.innerHTML;
			var nh=d.querySelector('.wcpc-head'), ch=qs('.wcpc-head'); if(nh&&ch) ch.innerHTML=nh.innerHTML;
			bind();
			if(window.jQuery){ jQuery(document.body).trigger('updated_cart_totals'); }
			busy(false);
		}
		function bind(){
			document.querySelectorAll('.wcpc-qty input.qty').forEach(function(inp){ if(inp._tb)return; inp._tb=1; inp.addEventListener('change',schedule); });
			document.querySelectorAll('.wcpc-qty .plus, .wcpc-qty .minus, .wcpc-qty button').forEach(function(b){ if(b._tb)return; b._tb=1; b.addEventListener('click',function(){ setTimeout(schedule,70); }); });
			document.querySelectorAll('.wcpc-remove').forEach(function(a){ if(a._tb)return; a._tb=1; a.addEventListener('click',function(e){ e.preventDefault(); busy(true); fetch(a.href,{credentials:'same-origin'}).then(function(r){return r.text();}).then(replaceFrom).catch(function(){ window.location.reload(); }); }); });
		}
		if(document.readyState!=='loading') bind(); else document.addEventListener('DOMContentLoaded', bind);
	})();
	</script>
	<?php
	do_action( 'woocommerce_after_cart' );
	return ob_get_clean();
}
