=== SudoMock Product Customizer ===
Contributors: sudomock
Tags: product customization, mockup, psd, woocommerce, personalization
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let shoppers personalize products with their own artwork, logos, and text — rendered onto your PSD mockups in real time.

== Description ==

SudoMock Product Customizer connects your WooCommerce store to [SudoMock](https://sudomock.com), enabling real-time product customization powered by your PSD mockups.

**How it works:**

1. Upload PSD mockup files on sudomock.com
2. Map mockups to WooCommerce products
3. Customers upload artwork and preview on your mockup
4. Rendered mockup image attached to cart and order

**Features:**

* Real-time PSD mockup rendering
* Customer artwork upload with live preview
* Automatic cart integration with rendered preview
* HPOS (High-Performance Order Storage) compatible
* WooCommerce Blocks (Checkout Blocks) compatible
* No SudoMock branding shown to customers (white-label)
* GDPR compliant with data export and erasure
* Internationalization ready (i18n)

**Requirements:**

* WooCommerce 8.0 or later
* PHP 7.4 or later
* A SudoMock account (free tier available with 500 renders/month)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sudomock-product-customizer/` or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce → SudoMock and click "Connect Account" to link your SudoMock account.
4. Map PSD mockups to your products in the Products tab.
5. Customers will see a "Customize" button on mapped product pages.

== Frequently Asked Questions ==

= Do I need a SudoMock account? =

Yes. Sign up for free at [sudomock.com](https://sudomock.com/register). The free plan includes 500 renders per month.

= Is the customizer white-labeled? =

Yes. No SudoMock branding is shown to your customers. You can customize colors, labels, and branding in the Settings tab.

= Does it work with WooCommerce Blocks checkout? =

Yes. The plugin is fully compatible with both the classic checkout and the new WooCommerce Blocks-based checkout.

= What PSD files are supported? =

Any PSD with smart object layers. The smart objects become customizable areas where customers upload their artwork.

= Is it GDPR compliant? =

Yes. The plugin includes data export and erasure handlers for customer personalization data.

== Screenshots ==

1. Dashboard — Account status, credits, and setup progress.
2. Products — Map PSD mockups to your WooCommerce products.
3. Mockups — Browse and search your PSD mockup library.
4. Settings — Customize button label and display mode.
5. Storefront — Setup guide for your store.

== Changelog ==

= 1.0.0 =
* Initial release
* OAuth connect flow with sudomock.com
* Product-to-mockup mapping
* Real-time PSD mockup rendering
* Cart integration with rendered preview
* HPOS and WooCommerce Blocks compatibility
* GDPR data export and erasure
* i18n ready (EN + DE)

== Upgrade Notice ==

= 1.0.0 =
Initial release.
