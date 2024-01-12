# WordPress Custom Panel

A **standalone, white‑labeled admin panel** for WordPress + WooCommerce stores — a fast custom dashboard that replaces the heavy `wp-admin` for day‑to‑day store operations, shipped together with a suite of drop‑in **mu‑plugins** for performance, security, white‑labeling and storefront UX.

Everything is delivered as **must‑use plugins** (`mu-plugins`): no activation screen, no build step — copy the files and they load automatically.

> The admin UI is Turkish‑localized (the project was built for a Turkish store). All identifying/branding data has been removed; configure it for your own store.

---

## ✨ Features

| Module (`mu-plugins/…`) | What it does |
|---|---|
| **custom-panel.php** | The core. A standalone `/panel` admin (dashboard, orders + detail, products, categories, tags, attributes, **brands**, coupons, customers, reviews, stock, **bulk product editor**, **CSV import/export**, **cancel/return requests**, analytics suite — sales/product/customer/geo/coupon/**refund**/**tax** reports — plus **integrations** overview). Capability‑gated, nonce‑protected. |
| **shipping-tracking.php** | Self‑service cargo tracking: enter a tracking number → order marked *shipped* + branded customer e‑mail; scheduled carrier status polling → auto‑complete on delivery. Carrier web‑service credentials are stored as options (never in code). |
| **security-hardening.php** | Blocks REST/author user‑enumeration, disables XML‑RPC & app‑passwords, adds security headers, Cloudflare‑aware login throttling, generic login errors. |
| **cloudflare-cache.php** | Edge‑cache purge on content changes + admin‑bar “purge” button + post‑purge cache warming. |
| **webp-converter.php** | Auto‑generates WebP siblings for uploads (and existing media) and serves them transparently. |
| **whitelabel-hardening.php** | Removes WordPress/WooCommerce fingerprints (generator/version tags, emoji, oEmbed, path masking) without breaking functionality. |
| **shop-styles.php** / **product-ui.php** / **theme-ui.php** | Storefront/theme refinements (archive & product‑page polish, responsive tweaks). |
| **mobile-dock.php** | Mobile bottom navigation dock. |
| **custom-cart.php** / **checkout-layout.php** | Cart & checkout UX enhancements. |
| **gift-message.php** / **home-preview.php** | Gift‑message field & homepage preview helper. |

---

## ✅ Requirements

- WordPress **6.0+**
- WooCommerce **8.0+** (HPOS compatible)
- PHP **8.1+** (developed on 8.2)
- Optional: **Redis** (object cache), **Cloudflare** (edge cache + purge), **Imagick** (WebP)

---

## 🚀 Installation

### 1. Copy the mu‑plugins
```bash
# from your WordPress root
mkdir -p wp-content/mu-plugins
cp /path/to/wordpress-custom-panel/mu-plugins/*.php wp-content/mu-plugins/
```
`mu-plugins` load automatically — there is **nothing to activate**.

> Prefer only some modules? Copy just the files you want. `custom-panel.php` is standalone; the others are independent and can be added à la carte.

### 2. Flush permalinks (for the `/panel` route)
Visit **Settings → Permalinks** and click *Save*, or via WP‑CLI:
```bash
wp rewrite flush
```

### 3. Open the panel
Log in as an administrator (or a user with `manage_woocommerce`) and go to:
```
https://your-site.com/panel
```

### 4. (Optional) Cloudflare edge cache
Add to `wp-config.php`:
```php
define( 'WCP_CF_TOKEN', 'your-cloudflare-api-token' ); // token with "Cache Purge" + "Cache Rules" edit
define( 'WCP_CF_ZONE',  'your-cloudflare-zone-id' );
```
The purge button and auto‑purge/warm hooks activate automatically once these are defined.

### 5. (Optional) Shipping tracking
Open **Panel → (order detail) → Kargo** and enter your carrier **web‑service credentials** (stored as options). Add the query WSDL/endpoint if your carrier requires one. Scheduled polling runs hourly via WP‑Cron.

### 6. (Optional) White‑label content path
To serve uploads under a masked path (e.g. `/media` instead of `/wp-content`), set in `wp-config.php`:
```php
define( 'WP_CONTENT_URL', 'https://your-site.com/media' );
```
and add the matching rewrite alias in `.htaccess` (see `whitelabel-hardening.php` header for notes).

---

## ⚙️ Configuration reference

| Where | Key | Purpose |
|---|---|---|
| `wp-config.php` | `WCP_CF_TOKEN`, `WCP_CF_ZONE` | Cloudflare cache purge/warm |
| `wp-config.php` | `WP_CONTENT_URL` | White‑label content path (optional) |
| Panel options | shipping WS user/pass/WSDL | Carrier tracking integration |
| WP option | `woocommerce_notify_low_stock_amount` | Low‑stock threshold used by the dashboard |

**No secrets are stored in code.** Credentials live in `wp-config.php` or the options table.

---

## 🖼️ Screenshots

_Add your own screenshots here (dashboard, orders, analytics, bulk editor)._

---

## 🧱 Architecture notes

- Pure PHP, no framework/build step. The panel renders via `template_redirect` behind a rewrite rule; front‑end assets are enqueued only on panel routes.
- Every write action is capability‑checked and nonce/`check_admin_referer` protected; AJAX endpoints verify `check_ajax_referer`.
- Modules are independent files and fail safe if WooCommerce is inactive.

---

## 📄 License

[MIT](LICENSE) © KaanHka
