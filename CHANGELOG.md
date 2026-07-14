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
- Retired the dead `_sudomock_render_url` write path (hidden form fields were never transferred to cart item data); admin order thumbnail and GDPR export/erase now read the meta that is actually written (`_sudomock_preview_url` and artwork keys), with a legacy read fallback

### Changed
- Session URL parameter: `?token=` → `?session=`
- verify-session now returns studio_config (merged response)
- Error report endpoint accepts optional session token

### Security
- Hardened session-token handling so credentials are never exposed to the browser
- Added postMessage origin validation on all platforms
- Mockup search input sanitization
- Settings config whitelist validation
