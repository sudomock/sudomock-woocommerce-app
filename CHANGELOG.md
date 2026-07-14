# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2026-07-14

### Added
- Original customer artwork on orders: `_sudomock_artwork_url` (+ `_2`..`_10`) hidden keys and merchant-visible "Source Design" (+ numbered) order item meta, populated from the Studio add-to-cart payload (`artwork_urls` / `artwork_url`)
- `_sudomock_render_uuid` order item meta for merchant cross-reference
- Admin order screen now lists downloadable source design file links next to the preview thumbnail
- GDPR exporter/eraser cover preview, artwork, and render-reference meta (visible labels included)
- Opaque short-lived session tokens (API key never exposed to client)
- Signed-request verification on session creation
- Mockup ownership verification at session creation
- 10 language translations (TR, DE, FR, ES, PT-BR, IT, NL, JA, KO, ZH-CN)

### Fixed
- Orphaned mappings no longer show a dead "Customizer temporarily unavailable" alert to shoppers: when a mapped mockup no longer belongs to the connected account (deleted, or the store was reconnected to a different account), the button is hidden and the Products screen flags it as "Mapped (invalid) — Remap" so the merchant can fix it
- Variable products: the shopper's chosen variation is now added to the cart (parent product_id + real variation_id); previously the variation id was miswired as the product id, so variable products failed or added the wrong variant/price
- Quantity: the product-form quantity is honoured (was always forced to 1)
- Add-to-cart failure no longer destroys the customizer session — the editor stays open with the artwork preserved and the error is reported to Studio for retry (was: overlay closed + blocking alert, losing the design)
- Retired the dead `_sudomock_render_url` write path (hidden form fields were never transferred to cart item data); admin order thumbnail and GDPR export/erase now read the meta that is actually written (`_sudomock_preview_url` and artwork keys), with a legacy read fallback
- Classic themes: resolve the WooCommerce `$product` global (can be a string at enqueue time) before use, preventing a fatal on product pages

### Security
- Fixed a rare API-key corruption: the encrypted-key IV separator could collide with random IV bytes (~1/4400 keys), silently breaking the stored key; keys are now stored as `base64(iv)::base64(ciphertext)` (legacy values still decrypt)
- Order-item artwork/preview URLs from the browser are host-validated (https + public host) before being written to merchant-facing order meta
- Admin/product mockup grids escape quotes in mockup names/URLs, closing an attribute-context stored XSS

### Changed
- Session URL parameter: `?token=` → `?session=`
- verify-session now returns studio_config (merged response)
- Error report endpoint accepts optional session token

### Security
- Hardened session-token handling so credentials are never exposed to the browser
- Added postMessage origin validation on all platforms
- Mockup search input sanitization
- Settings config whitelist validation
