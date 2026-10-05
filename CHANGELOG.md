# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.4.3] - 2026-10-05

### Added
- The order carries a print file for each design area whose inner layers the
  shopper filled. The order line holds a "Print File" link next to "Source
  Design", numbered "Print File 2", "Print File 3" when there are several. The
  admin order screen shows their download links, and the personal data export
  lists them and the erasure deletes them like the artwork files.
- An order without print files is saved exactly as before.
- The privacy policy text the plugin suggests to the store names the print
  file too, and it is now translated in all ten languages.

## [1.4.2] - 2026-10-05

### Changed
- The plugin page states plans and prices the way the SudoMock site does, and
  the installation steps name the SudoMock menu the plugin adds to the
  WordPress admin.

## [1.4.1] - 2026-10-04

### Fixed
- The German, Spanish, French, Italian and Brazilian Portuguese translations
  now load in the store's language, with their accented letters restored.

## [1.4.0] - 2026-10-04

### Fixed
- The cart shows the customization preview again. Since 1.3.0 a customized
  cart line kept the product's own image; it carries the shopper's finished
  design as its thumbnail again. The Cart block shows it too, on WooCommerce
  9.6 and later.
- The order carries the customization preview and the source design links
  again. The order line holds a "Customization Preview" link and one "Source
  Design" link per artwork file, up to 10, as it did in 1.2.0, so the admin
  order screen shows the preview with its download links and the personal data
  export and erasure cover them.
- The Turkish, Dutch, Japanese, Korean and Simplified Chinese translations
  now load in the store's language.
- A personal data erasure removes every SudoMock key from the customer's order
  lines. It used to leave the add to cart confirmation reference behind, and
  it skipped a line that carried no links, as every line written by 1.3.0 and
  1.3.1 does.

The links are the ones SudoMock returns when it confirms the add to cart. A
link sent by the shopper's browser is never used, and a link that does not
start with https:// is not saved.

## [1.3.1] - 2026-09-02

### Fixed
- A finished customization could not reach the cart. The storefront accepted a
  submitted design only when its payload named exactly three fields, and the
  customizer has sent its render parameters alongside them since 11 August, so
  every real submission was refused: no cart line was created and nothing said
  why. The bridge now validates the fields it acts on and ignores the rest,
  which is also what keeps the next field the customizer adds from breaking it
  again. The request the store authorizes still carries only the three
  identifiers.
- The dashboard showed a store paying as it goes as `0 / 0 credits` under a
  progress bar frozen at 0%, while its SudoMock account was funded and working.
  An account is funded either by a subscription allowance or by a prepaid
  balance, and the dashboard only ever read the allowance.

### Added
- The prepaid balance is read from the account endpoint, stored alongside the
  credit options, and shown when the account holds one. A store with both sees
  both.
- The credits bar is drawn only when there is an allowance to draw it from. A
  balance is an amount, not a fraction, so it has no denominator to be a
  percentage of and is shown as a figure instead. An account with neither reads
  "No credits or balance" rather than a row of zeroes.

The balance is rendered in its own currency and deliberately not through
`wc_price()`, which would restate it as a store amount the merchant does not
hold.

## [1.3.0] - 2026-07-27

### Added
- 2D mockup support: merchants can map products to 2D mockups created directly from a product photo, no PSD template required
- Storefront customization flow supports 2D mockups end to end, alongside existing PSD mockups
- Add-to-cart is confirmed with a server-verified receipt, so only completed customizations are attached to the cart and order

## [1.2.0] - 2026-07-14

### Added
- Original customer artwork on orders: `_sudomock_artwork_url` (+ `_2`..`_10`) hidden keys and merchant-visible "Source Design" (+ numbered) order item meta, populated from the Studio add-to-cart payload (`artwork_urls` / `artwork_url`)
- `_sudomock_render_uuid` order item meta for merchant cross-reference
- Admin order screen now lists downloadable source design file links next to the preview thumbnail
- GDPR exporter/eraser cover preview, artwork, and render-reference meta (visible labels included)
- Opaque short-lived session tokens
- Signed-request verification on session creation
- Mockup ownership verification at session creation
- 10 language translations (TR, DE, FR, ES, PT-BR, IT, NL, JA, KO, ZH-CN)

### Fixed
- Studio now always opens in an iframe modal on the product page
- Double-clicking "Add to Cart" no longer creates duplicate cart lines (in-flight guard)
- Full-page-cached storefronts: a fresh nonce is fetched before Customize/add-to-cart, so a stale cached nonce no longer breaks the flow with a generic error
- The customize button/shortcode resolve the WooCommerce `$product` global (can be a string) before use, matching the enqueue fatal fix
- Orphaned mappings no longer show a dead "Customizer temporarily unavailable" alert to shoppers: when a mapped mockup no longer belongs to the connected account (deleted, or the store was reconnected to a different account), the button is hidden and the Products screen flags it as "Mapped (invalid) — Remap" so the merchant can fix it
- Variable products: the shopper's chosen variation is now added to the cart (parent product_id + real variation_id); previously the variation id was miswired as the product id, so variable products failed or added the wrong variant/price
- Quantity: the product-form quantity is honoured (was always forced to 1)
- Add-to-cart failure no longer destroys the customizer session — the editor stays open with the artwork preserved and the error is reported to Studio for retry (was: overlay closed + blocking alert, losing the design)
- Retired the dead `_sudomock_render_url` write path (hidden form fields were never transferred to cart item data); admin order thumbnail and GDPR export/erase now read the meta that is actually written (`_sudomock_preview_url` and artwork keys), with a legacy read fallback
- Classic themes: resolve the WooCommerce `$product` global (can be a string at enqueue time) before use, preventing a fatal on product pages

### Security
- Restores stores affected by a rare saved-connection issue and prevents recurrence; recovery is automatic and requires no reconnect or data migration
- Order-item artwork/preview URLs from the browser are host-validated (https + public host) before being written to merchant-facing order meta
- Admin/product mockup grids escape quotes in mockup names/URLs, closing an attribute-context stored XSS
- Storefront error reports send the page path only, not the full URL (no query-string leakage)

### Privacy
- GDPR erasure now deletes the actual stored design files from SudoMock storage (owner-scoped), not just the local order meta; deletions that cannot be confirmed immediately are queued and retried daily so no file is orphaned

### Changed
- Session URL parameter: `?token=` → `?session=`
- verify-session now returns studio_config (merged response)
- Error report endpoint accepts optional session token

### Security
- Hardened session-token handling
- Added postMessage origin validation on all platforms
- Mockup search input sanitization
- Settings config whitelist validation
