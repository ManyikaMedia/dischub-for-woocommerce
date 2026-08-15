=== DiscHub for WooCommerce ===
Contributors: manyikamedia
Tags: woocommerce, payment gateway, zimbabwe, ecocash, innbucks
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept EcoCash and InnBucks payments seamlessly via DiscHub Payment Gateway on WooCommerce.

== Description ==

**DiscHub for WooCommerce** allows Zimbabwean WooCommerce merchants to accept mobile money payments effortlessly using EcoCash and InnBucks.

Customers can pay using their EcoCash mobile number (triggering an instant USSD PIN prompt directly on their handset) or generate an instant payment code and QR code for the InnBucks app.

### Features
* **EcoCash Direct Payments**: Customers receive a direct USSD prompt on their phone to enter their EcoCash PIN.
* **InnBucks QR & Authorization Code**: Generates instant InnBucks payment codes and QR codes.
* **Multi-Currency Support**: Native support for **USD** and **ZWG** (Zimbabwe Gold).
* **Seamless On-Site Verification**: Real-time payment verification directly on the Order Received / Thank You page with zero unnecessary redirects.
* **Instant Payment Notifications (IPN / Webhooks)**: Server-to-server webhook confirmation with direct status verification.
* **HPOS & WooCommerce Blocks Compatible**: Fully compatible with WooCommerce High-Performance Order Storage (HPOS) and Cart & Checkout Blocks.
* **Order Management Meta Box**: View DiscHub references, timestamps, and customer payment numbers with a 1-click status refresh button.
* **Secure Debug Logging**: Optional debug logging to WooCommerce Status Logs.

### Third-Party Service Disclosure

This plugin integrates with the **DiscHub Payment Gateway API** provided by DiscHub (https://dischub.co.zw) to create payment requests and verify transaction statuses.

* **Service Provider**: DiscHub (https://dischub.co.zw)
* **Data Transmitted**: When a customer initiates a payment at checkout, the following information is sent to the DiscHub API:
  * Merchant API Key and Merchant Account Email
  * WooCommerce Order Reference ID
  * Transaction Amount and Currency (USD or ZWG)
  * Selected Payment Method (EcoCash or InnBucks)
  * Customer Mobile Phone Number (for sending USSD push prompts)
* **Terms of Service**: https://dischub.co.zw
* **Privacy Policy**: https://dischub.co.zw

== Installation ==

1. Upload the `dischub-for-woocommerce` folder to your `/wp-content/plugins/` directory, or upload the ZIP file through **Plugins > Add New > Upload Plugin** in your WordPress dashboard.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce > Settings > Payments > DiscHub (EcoCash & InnBucks)**.
4. Enter your **DiscHub API Key** and **DiscHub Merchant Email** from your DiscHub merchant dashboard.
5. Choose your environment mode (**Live mode** or **Test mode**) and save changes.

== Frequently Asked Questions ==

= Which payment methods are supported? =
The plugin currently supports EcoCash and InnBucks for merchants in Zimbabwe.

= Which currencies are supported? =
The plugin supports USD and ZWG (Zimbabwe Gold).

= Does this plugin work with WooCommerce Blocks? =
Yes, the plugin is fully compatible with both Modern Cart & Checkout Blocks and Classic Shortcode Checkout.

= Is High-Performance Order Storage (HPOS) supported? =
Yes, the plugin declares full compatibility with HPOS custom order tables.

== Screenshots ==

1. WooCommerce Checkout payment selection for EcoCash and InnBucks.
2. DiscHub Payment Gateway settings screen.
3. Order edit screen with DiscHub transaction details meta box.
4. On-site payment confirmation and real-time approval status on the Thank You page.

== Changelog ==

= 1.0.0 =
* Initial release of DiscHub for WooCommerce.
* Support for EcoCash and InnBucks payment methods.
* Multi-currency support for USD and ZWG.
* Seamless on-site real-time order polling and webhook IPN handling.
* Full compatibility with WooCommerce Blocks and HPOS.
