=== SudoMock Product Customizer ===
Contributors: sudomock
Tags: product customizer, mockup generator, product personalization, print on demand, custom products
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.0
WC tested up to: 11.1
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce products to the SudoMock PSD rendering engine. Customers upload artwork, preview it on your PSD mockups, and buy.

== Description ==

**SudoMock Product Customizer** connects your WooCommerce store to the SudoMock PSD rendering engine. Customers upload their artwork, logos, or text and see it rendered onto your PSD mockup templates using Photoshop Smart Object replacement.

https://www.youtube.com/watch?v=nmD0ePncAm4

= Features =

* **PSD Rendering** - High-fidelity mockups with 27 blend modes, CMYK support, and up to 10000px output resolution once a card is verified on your SudoMock account (1024px before that)
* **White-Label** - Fully customizable labels, button text, and colors. Once a card is verified, no third-party branding is shown to customers
* **Plans From $25 Per Month** - 5,000 renders a month, which is $0.005 per render, and without any subscription at all it is $0.05 per PSD render with a $5 minimum
* **Cart Integration** - Rendered mockup preview automatically attaches to cart and order
* **HPOS Compatible** - Built for WooCommerce High-Performance Order Storage
* **Blocks Compatible** - Works with both classic checkout and WooCommerce Blocks checkout
* **GDPR Compliant** - Full data export and erasure for customer personalization data
* **Internationalization** - Translation-ready, with 10 translations included

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

Yes. Sign up at [sudomock.com/register](https://sudomock.com/register). New accounts start with 500 free credits and no credit card. While the account is in trial, renders are watermarked and capped at 1024px, you can store 5 PSD templates, and one render runs at a time. Verifying a card and funding $5 removes all of it. Plans start at $25 per month for 5,000 renders, which is $0.005 per render, and without any subscription at all it is $0.05 per PSD render.

= What happens to my PSD templates on a trial account? =

A template that has not been rendered for 7 days gets three warning emails, two days apart, and is deleted on day 13. Rendering it at any point resets the clock. Templates on an account with a funded balance or an active plan are never deleted.

= Is the product customizer white-labeled? =

Once a card is verified on your SudoMock account, no SudoMock branding is visible to your customers. Trial renders carry a watermark. You can customize the button label, colors, and all customer-facing text in the Settings tab.

= Does it work with WooCommerce Blocks checkout? =

Yes. The plugin is fully compatible with both the classic WooCommerce checkout and the new Blocks-based checkout. HPOS (High-Performance Order Storage) is also fully supported.

= What PSD files are supported? =

PSD templates can contain multiple customizable areas for customer artwork. Supports files up to 300MB, unlimited layers, RGB/CMYK color modes, and 27 blend modes.

= What output formats does the mockup renderer support? =

PNG, JPEG, and WebP. You can configure quality (1-100), resolution up to 10000px, and transparency (alpha channel support). A trial account is capped at 1024px; requests above that are rejected with an error rather than silently resized.

= Is it GDPR compliant? =

Yes. The plugin includes full data export and erasure handlers for customer personalization data, compliant with GDPR, CCPA, and other privacy regulations.

= Can I use my own PSD mockup templates? =

Yes. Upload your own PSD files with Smart Object layers. You are not limited to a template library.

= Does it support batch processing for Print on Demand? =

Yes. Use the REST API or automation integrations (n8n, Zapier, Make) to process mockup renders in bulk. Ideal for POD fulfillment workflows with Printful, Printify, or custom fulfillment systems.

= Is there a limit on the number of products I can customize? =

No product limit. Map as many products as you want to mockup templates.

== Screenshots ==

1. **Dashboard** - Account connection status, render credits, setup progress, and quick actions.
2. **Products** - Browse WooCommerce products with one-click mockup mapping and status indicators.
3. **Mockup Library** - Search, filter, and preview your PSD mockup templates.
4. **Product Mapping** - Select a mockup for any product with instant preview.
5. **Settings** - Customize button label and storefront behavior.
6. **Storefront Preview** - Customer view with the "Customize" button on product page.
7. **Customer Customizer** - Upload artwork and see real-time mockup preview.
8. **Cart Integration** - Rendered mockup preview attached to cart line item.
9. **Order Detail** - Rendered mockup in order admin for fulfillment.

== Changelog ==

= 1.4.0 =
* The cart shows the customization preview again: a customized cart line carries the shopper's finished design as its thumbnail. The Cart block shows it too, on WooCommerce 9.6 and later.
* The order carries the customization preview and the source design links again, as "Customization Preview" and "Source Design" on the order line, ready for production.
* The Turkish, Dutch, Japanese, Korean and Simplified Chinese translations now load in the store's language.
* A personal data erasure removes every SudoMock key from the customer's order lines, including a line that carries no links.

= 1.3.1 =
* Fixed: a finished customization reaches the cart again. A design submitted from the customizer could be refused on its way to the cart, with nothing shown to explain why.
* The credits panel shows a prepaid balance next to a subscription allowance, and reads correctly for an account that holds only one of the two.

= 1.3.0 =
* 2D mockups: create product mockups directly from a product photo, no PSD template required
* Storefront customization flow now supports 2D mockups end to end, alongside PSD mockups
* Add-to-cart is confirmed with a server-verified receipt, so only completed customizations reach the cart

= 1.2.0 =
* Original customer artwork is now attached to orders as downloadable "Source Design" links, alongside the customization preview, for production
* Fixed variable products so the shopper's chosen variation (and price) is added to the cart
* Fixed the product quantity being ignored (always added 1)
* Add-to-cart failures no longer discard the shopper's design; the editor stays open to retry
* Orphaned mockup mappings are flagged in the admin ("Mapped (invalid)", with a Remap action) and hide the storefront button instead of showing an error
* Studio now always opens in an iframe modal; duplicate add-to-cart clicks are guarded; a fresh security token is fetched on cached pages
* Fixed a fatal error on some classic themes' product pages
* Security: restores stores affected by a rare saved-connection issue, prevents recurrence, and strengthens order links, admin displays, and error reporting
* GDPR erasure now deletes the stored design files from SudoMock (with automatic retry), not just local order meta
* Removed unused code paths for a lighter, cleaner plugin

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

= 1.2.0 =
Adds original artwork files to orders, fixes variable-product and quantity handling in the cart, and includes security, GDPR, and stability improvements. Recommended for all stores.

= 1.0.0 =
Initial release. Install, connect your SudoMock account, and start customizing products.

== External services ==

This plugin connects to the external SudoMock service to provide PSD mockup rendering functionality for WooCommerce products. No data is transmitted until the store administrator explicitly connects their SudoMock account.

= SudoMock API (api.sudomock.com) =

The plugin communicates with the SudoMock API at https://api.sudomock.com for the following operations. Every request carries the store's API key and the plugin's version number.

* **Account verification**: When the admin connects their SudoMock account, and when a connected admin opens the plugin screen, the plugin sends the API key and reads back the account email, the plan, the credit usage and the prepaid balance (GET /api/v1/me). The answer is reused for 5 minutes.
* **Mockup listing**: When the admin opens the Mockups tab, opens the mockup picker in the Products tab, or opens the edit screen of a product, the plugin fetches the PSD mockups and the photo mockups in the merchant's account (GET /api/v1/mockups and GET /api/v1/sudoai/2d-mockups). Only paging and filter values are sent. Text typed into the mockup search is matched on the store and is not sent.
* **Mockup details and thumbnails**: When the admin opens the Products tab, or the edit screen of a product that has a mockup, the plugin sends the mockup ID and fetches the mockup name and its thumbnail image addresses (GET /api/v1/mockups/{uuid} for a PSD mockup, GET /api/v1/sudoai/2d-mockups/{uuid} for a photo mockup). The thumbnail images are served from SudoMock servers.
* **Studio session creation**: When a customer clicks the "Customize" button, the plugin opens an editor session (POST /api/v1/studio/create-session). It sends the mockup ID and type, the store's web address, the WooCommerce product and variation IDs, and the purpose of the session (customizing a product to add it to the cart).
* **Add to cart confirmation**: When a customer adds a finished design to the cart, the plugin confirms it with SudoMock before the cart line is created (POST /api/v1/studio/actions/consume). It sends the session and request IDs, the mockup ID, the ID of the finished design, the store's host name, the product and variation IDs, and the name of the action (add to cart). The answer carries the links to the customization preview and the source design files, which are saved with the cart line and the order.
* **Studio configuration**: When the admin opens the Settings tab, the plugin reads the white-label editor settings stored on the SudoMock server (GET /api/v1/studio/config). When the admin saves them, it sends the new settings: colors, texts, logo address and editor options (PUT /api/v1/studio/config).
* **Support messages**: When the admin submits the "Need Help?" form in the Settings tab, the subject and the message are sent to SudoMock with the site address and the SudoMock account email (POST /api/v1/support/ticket). If that request does not succeed, the same message is sent by email from the site to hello@sudomock.com, with the site address and the account email, or the site administrator's email when no account email is stored.
* **Stored design file deletion**: When a personal data erasure request is processed for a customer, the plugin asks SudoMock to delete the preview and source design files linked to that customer's orders (POST /api/v1/artworks/delete). It sends the links of those files. A deletion that cannot be confirmed is retried once a day.
* **Account disconnect**: When the admin disconnects, the plugin notifies the SudoMock server (POST /api/v1/woocommerce/disconnect). Nothing but the API key and the version number is sent.

All API calls are made server-to-server using wp_remote_request. The API key is stored encrypted (AES-256-CBC).

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).

= SudoMock Studio (studio.sudomock.com) =

The product editor at https://studio.sudomock.com opens in an iframe modal only when a customer clicks the "Customize" button. Customers can upload artwork to preview their customized product. The preview and source-design file links are returned to the store and saved with the order for fulfilment.

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).

= SudoMock Website (sudomock.com) =

The plugin links to the SudoMock website at https://sudomock.com for the following purposes:

* **OAuth connect flow**: The admin is redirected to sudomock.com/integrations/woocommerce/connect to authorize the WooCommerce integration and obtain an API key. Clicking it sends the store's web address and a return address (the plugin's admin page) to sudomock.com.
* **Account registration**: Links to sudomock.com/register for new account signup.
* **Dashboard links**: Links to sudomock.com/dashboard/playground for PSD mockup management and sudomock.com/dashboard/billing for plan management. These are navigational links that open in a new browser tab.
* **Documentation links**: Links to sudomock.com/docs for integration guides and PSD preparation documentation.

These are browser-side navigational links only. Nothing is sent to sudomock.com until the admin follows one of these links.

This service is provided by "SudoMock": [Terms of Service](https://sudomock.com/legal/terms), [Privacy Policy](https://sudomock.com/legal/privacy).
