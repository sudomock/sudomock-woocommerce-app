=== SudoMock Product Customizer - PSD Mockup Personalization for WooCommerce ===
Contributors: sudomock
Tags: product customizer, mockup generator, product personalization, print on demand, custom products
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 9.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WooCommerce products into customizable experiences. Customers upload artwork, preview it on your real PSD mockups, and buy - all in real time.

== Description ==

**SudoMock Product Customizer** transforms your WooCommerce store into a product personalization platform. Customers upload their artwork, logos, or text and instantly see it rendered onto your real PSD mockup templates - not flat overlays, but actual Photoshop Smart Object replacement.

= The Problem =

Generic product customizers use 2D overlays that look fake. Customers cannot visualize their artwork on your actual product, leading to cart abandonment and returns.

= The Solution =

SudoMock renders customer artwork INTO your real Photoshop mockups using Smart Object replacement. The preview matches your physical product exactly - curves, textures, shadows, and all.

= Why SudoMock? =

* **Real PSD Rendering** - Actual Photoshop Smart Object replacement, not SVG overlays. 27 blend modes, CMYK support, up to 8000px output resolution
* **White-Label** - Zero SudoMock branding shown to customers. Customize every label, button text, and color
* **No Transaction Fees** - $0.002 per render. No per-order costs. No percentage-based fees
* **Instant Setup** - Connect account, map mockups to products, done. No code required
* **Cart Integration** - Rendered mockup preview automatically attaches to cart and order
* **HPOS Compatible** - Built for WooCommerce High-Performance Order Storage
* **Blocks Compatible** - Works with both classic checkout and WooCommerce Blocks checkout
* **GDPR Compliant** - Full data export and erasure for customer personalization data
* **Internationalization** - Translation-ready with EN and DE included

= How It Works =

1. **Upload** PSD mockups to your free SudoMock account
2. **Map** mockups to WooCommerce products in one click
3. **Customers** see a "Customize" button on product pages
4. **Preview** - customers upload artwork and see real-time mockup rendering
5. **Buy** - rendered mockup image attaches to cart and order automatically

= Who Is It For? =

* Print-on-demand WooCommerce stores
* Custom merchandise shops (t-shirts, mugs, posters, phone cases)
* Gift stores with personalization (engraving, printing, embroidery)
* Brand merchandise with strict visual guidelines
* Any WooCommerce store selling customizable products

= Pricing =

* **Free:** 500 one-time render credits. No credit card required
* **Starter:** From $17.49/month (5,000 credits, 3 parallel renders)
* **Pro:** From $27.49/month (5,000 credits, 10 parallel renders)
* **Scale:** From $52.49/month (5,000 credits, 25 parallel renders)

All prices net. No hidden fees. No transaction percentages.

= Integrations =

* n8n, Zapier, Make automation workflows
* Printful, Printify POD fulfillment
* REST API for custom integrations
* Shopify app also available for multi-platform sellers

= Requirements =

* WooCommerce 8.0 or later
* PHP 7.4 or later
* A SudoMock account ([free signup](https://sudomock.com/register))

= External Services =

This plugin connects to the **SudoMock API** ([sudomock.com](https://sudomock.com)) to provide PSD mockup rendering functionality. No data is transmitted until the store admin explicitly connects their account.

**Services used:**

1. **api.sudomock.com** — Server-to-server API calls (via `wp_remote_request`) for account verification, mockup listing, session creation, and render processing. Called only when the admin connects their account or a customer clicks the Customize button. The store's API key is stored encrypted (AES-256-CBC) and never exposed to the browser.
2. **studio.sudomock.com** — The mockup design editor, loaded in an iframe on the frontend only when a customer clicks the "Customize" button on a product page. Customer-uploaded images are transmitted to the SudoMock rendering service to generate previews.

**No data is collected or transmitted in the background.** All external communication requires explicit user action (admin connecting account, or customer clicking the customize button).

* [SudoMock Terms of Service](https://sudomock.com/legal/terms)
* [SudoMock Privacy Policy](https://sudomock.com/legal/privacy)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sudomock-product-customizer/` or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **WooCommerce > SudoMock** and click "Connect Account" to link your SudoMock account.
4. Sign up for a free account at [sudomock.com](https://sudomock.com/register) if you do not have one.
5. Map PSD mockups to your products in the Products tab.
6. Customers will see a "Customize" button on mapped product pages.

== Frequently Asked Questions ==

= Do I need a SudoMock account? =

Yes. Sign up for free at [sudomock.com/register](https://sudomock.com/register). The free plan includes 500 render credits with no credit card required. Dashboard usage is completely free and unlimited forever.

= Is the product customizer white-labeled? =

Yes. No SudoMock branding is visible to your customers. You can customize the button label, colors, display mode, and all customer-facing text in the Settings tab.

= How does SudoMock compare to other WooCommerce product customizers? =

SudoMock uses actual PSD rendering with Photoshop Smart Object replacement, not flat SVG overlays. The result is photorealistic previews that match your physical product exactly. At $0.002/render with zero transaction fees, it is also significantly more affordable than alternatives charging per-order percentages.

= Does it work with WooCommerce Blocks checkout? =

Yes. The plugin is fully compatible with both the classic WooCommerce checkout and the new Blocks-based checkout. HPOS (High-Performance Order Storage) is also fully supported.

= What PSD files are supported? =

Any PSD with Smart Object layers. Smart Objects become customizable areas where customers upload their artwork. Supports files up to 300MB, unlimited layers, RGB/CMYK color modes, and 27 blend modes.

= What output formats does the mockup renderer support? =

PNG, JPEG, and WebP. You can configure quality (1-100), resolution up to 8000px, and transparency (alpha channel support).

= Is it GDPR compliant? =

Yes. The plugin includes full data export and erasure handlers for customer personalization data, compliant with GDPR, CCPA, and other privacy regulations.

= Can I use my own PSD mockup templates? =

Yes. Upload your own PSD files with Smart Object layers. You are not limited to a template library. If you have a PSD, it works. The AI Mockup Studio is also available for generating mockup templates without Photoshop.

= Does it support batch processing for Print on Demand? =

Yes. Use the REST API or automation integrations (n8n, Zapier, Make) to process mockup renders in bulk. Ideal for POD fulfillment workflows with Printful, Printify, or custom fulfillment systems.

= Is there a limit on the number of products I can customize? =

No product limit. Map as many products as you want to mockup templates. The only limit is render credits per billing period, which you control by selecting a credit pack.

= Can I use SudoMock on both Shopify and WooCommerce? =

Yes. SudoMock has native plugins for both Shopify and WooCommerce. Your mockup library, credits, and account settings are shared across platforms.

== Screenshots ==

1. **Dashboard** - Account connection status, render credits, setup progress, and quick actions.
2. **Products** - Browse WooCommerce products with one-click mockup mapping and status indicators.
3. **Mockup Library** - Search, filter, and preview your PSD mockup templates.
4. **Product Mapping** - Select a mockup for any product with instant preview.
5. **Settings** - Customize button label, display mode, and storefront behavior.
6. **Storefront Preview** - Customer view with the "Customize" button on product page.
7. **Customer Customizer** - Upload artwork and see real-time mockup preview.
8. **Cart Integration** - Rendered mockup preview attached to cart line item.
9. **Order Detail** - Rendered mockup in order admin for fulfillment.

== Changelog ==

= 1.0.0 =
* Initial release
* OAuth 2.0 connect flow with sudomock.com
* Product-to-mockup mapping via custom post meta
* Real-time PSD mockup rendering with Smart Object replacement
* Cart integration with rendered mockup preview
* WooCommerce HPOS (High-Performance Order Storage) compatibility
* WooCommerce Blocks (Checkout Blocks) compatibility
* GDPR data export and erasure handlers
* Internationalization ready (EN + DE)
* n8n, Zapier, Make automation support
* REST API for custom integrations

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install, connect your free SudoMock account, and start customizing products in minutes.
