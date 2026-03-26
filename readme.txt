=== SudoMock Product Customizer ===
Contributors: sudomock
Tags: product customizer, mockup generator, product personalization, print on demand, custom products
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 10.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce products to the SudoMock PSD rendering engine. Customers upload artwork, preview it on your PSD mockups, and buy.

== Description ==

**SudoMock Product Customizer** connects your WooCommerce store to the SudoMock PSD rendering engine. Customers upload their artwork, logos, or text and see it rendered onto your PSD mockup templates using Photoshop Smart Object replacement.

= Features =

* **PSD Rendering** - Photoshop Smart Object replacement with 27 blend modes, CMYK support, and up to 8000px output resolution
* **White-Label** - Fully customizable labels, button text, and colors. No third-party branding shown to customers
* **Pay Per Render** - $0.002 per render from your credit balance
* **Cart Integration** - Rendered mockup preview automatically attaches to cart and order
* **HPOS Compatible** - Built for WooCommerce High-Performance Order Storage
* **Blocks Compatible** - Works with both classic checkout and WooCommerce Blocks checkout
* **GDPR Compliant** - Full data export and erasure for customer personalization data
* **Internationalization** - Translation-ready with EN and DE included

= How It Works =

1. **Upload** PSD mockups to your SudoMock account
2. **Map** mockups to WooCommerce products in one click
3. **Customers** see a "Customize" button on product pages
4. **Preview** - customers upload artwork and see the mockup rendering
5. **Buy** - rendered mockup image attaches to cart and order automatically

= Who Is It For? =

* Print-on-demand WooCommerce stores
* Custom merchandise shops (t-shirts, mugs, posters, phone cases)
* Gift stores with personalization (engraving, printing, embroidery)
* Brand merchandise with strict visual guidelines
* Any WooCommerce store selling customizable products

= Integrations =

* n8n, Zapier, Make automation workflows
* Printful, Printify POD fulfillment
* REST API for custom integrations

= Requirements =

* WooCommerce 8.0 or later
* PHP 7.4 or later
* A SudoMock account ([free signup](https://sudomock.com/register))

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sudomock-product-customizer/` or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **WooCommerce > SudoMock** and click "Connect Account" to link your SudoMock account.
4. Sign up for a free account at [sudomock.com](https://sudomock.com/register) if you do not have one.
5. Map PSD mockups to your products in the Products tab.
6. Customers will see a "Customize" button on mapped product pages.

== Frequently Asked Questions ==

= Do I need a SudoMock account? =

Yes. Sign up for free at [sudomock.com/register](https://sudomock.com/register). The free plan includes 500 render credits with no credit card required.

= Is the product customizer white-labeled? =

Yes. No SudoMock branding is visible to your customers. You can customize the button label, colors, display mode, and all customer-facing text in the Settings tab.

= Does it work with WooCommerce Blocks checkout? =

Yes. The plugin is fully compatible with both the classic WooCommerce checkout and the new Blocks-based checkout. HPOS (High-Performance Order Storage) is also fully supported.

= What PSD files are supported? =

Any PSD with Smart Object layers. Smart Objects become customizable areas where customers upload their artwork. Supports files up to 300MB, unlimited layers, RGB/CMYK color modes, and 27 blend modes.

= What output formats does the mockup renderer support? =

PNG, JPEG, and WebP. You can configure quality (1-100), resolution up to 8000px, and transparency (alpha channel support).

= Is it GDPR compliant? =

Yes. The plugin includes full data export and erasure handlers for customer personalization data, compliant with GDPR, CCPA, and other privacy regulations.

= Can I use my own PSD mockup templates? =

Yes. Upload your own PSD files with Smart Object layers. You are not limited to a template library.

= Does it support batch processing for Print on Demand? =

Yes. Use the REST API or automation integrations (n8n, Zapier, Make) to process mockup renders in bulk. Ideal for POD fulfillment workflows with Printful, Printify, or custom fulfillment systems.

= Is there a limit on the number of products I can customize? =

No product limit. Map as many products as you want to mockup templates. The only limit is render credits per billing period, which you control by selecting a credit pack.

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
Initial release. Install, connect your SudoMock account, and start customizing products.

== External services ==

This plugin connects to the external SudoMock service to provide PSD mockup rendering functionality for WooCommerce products. No data is transmitted until the store administrator explicitly connects their SudoMock account.

= SudoMock API (api.sudomock.com) =

The plugin communicates with the SudoMock API at https://api.sudomock.com for the following operations:

* **Account verification** — When the admin connects their SudoMock account, the plugin sends an API key to verify the account (GET /api/v1/me).
* **Mockup listing** — When the admin opens the Products tab, the plugin fetches the list of available PSD mockup templates from the merchant's account (GET /api/v1/mockups).
* **Mockup details and thumbnails** — When viewing mapped products, the plugin fetches mockup metadata including thumbnail image URLs to display in the admin panel (GET /api/v1/mockups/{uuid}). The thumbnail images are served from SudoMock servers.
* **Studio session creation** — When a customer clicks the "Customize" button, the plugin creates a rendering session on the server (POST /api/v1/studio/create-session).
* **Render processing** — When a mockup render is requested, the plugin sends the mockup UUID and smart object data to the rendering API (POST /api/v1/renders).
* **Studio configuration** — The plugin reads and updates white-label editor settings stored on the SudoMock server (GET/PUT /api/v1/studio/config).
* **Support tickets** — When the admin submits a support message via the plugin's Help tab, it is sent to the SudoMock support API (POST /api/v1/support/ticket). As a fallback, the message may be sent via email to support@sudomock.com.
* **Account disconnect** — When the admin disconnects, a notification is sent to the SudoMock server (POST /api/v1/woocommerce/disconnect).

All API calls are made server-to-server using wp_remote_request. The API key is stored encrypted (AES-256-CBC) and is never exposed to the browser.

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).

= SudoMock Studio (studio.sudomock.com) =

The plugin loads the SudoMock Studio editor from https://studio.sudomock.com in an iframe on the frontend product page. This happens only when a customer clicks the "Customize" button. Inside the Studio editor, the customer can upload their own artwork/images which are transmitted to the SudoMock rendering service to generate a preview of the customized product. The rendered image URL is then sent back to WordPress via postMessage and stored alongside the customer's order.

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).

= SudoMock Website (sudomock.com) =

The plugin links to the SudoMock website at https://sudomock.com for the following purposes:

* **OAuth connect flow** — The admin is redirected to sudomock.com/integrations/woocommerce/connect to authorize the WooCommerce integration and obtain an API key.
* **Account registration** — Links to sudomock.com/register for new account signup.
* **Dashboard links** — Links to sudomock.com/dashboard/playground for PSD mockup management and sudomock.com/dashboard/billing for plan management. These are navigational links that open in a new browser tab.
* **Documentation links** — Links to sudomock.com/docs for integration guides and PSD preparation documentation.

These are browser-side navigational links only. No data is automatically transmitted to sudomock.com by the plugin.

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).
