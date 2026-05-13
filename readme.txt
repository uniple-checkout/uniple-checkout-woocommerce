=== uniple checkout for WooCommerce ===
Contributors: uniple
Tags: woocommerce, payment, jpyc, stablecoin, crypto
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

JPYC stablecoin hosted checkout for WooCommerce, powered by uniple.

== Description ==

uniple checkout for WooCommerce lets merchants accept JPYC (electronic payment instrument, 電子決済手段) via uniple's hosted checkout.

Plugin redirects shoppers to uniple checkout; uniple handles wallet connection and on-chain settlement. Payment confirmation is delivered back to your shop via signed webhook + return URL fallback.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate via the WordPress Plugins menu.
3. Under WooCommerce → Settings → Payments, enable "uniple checkout (JPYC)" and enter your API key and webhook secret issued by uniple.

== Frequently Asked Questions ==

= Does this plugin support Checkout Blocks? =

Yes. Both classic checkout and Cart / Checkout Blocks are supported.

= Does this plugin support High-Performance Order Storage (HPOS)? =

Yes. `custom_order_tables` compatibility is declared.

== Changelog ==

= 0.1.0 =
* Initial preview release. WC_Payment_Gateway + Blocks support + HPOS + signed webhook + return URL fallback.
