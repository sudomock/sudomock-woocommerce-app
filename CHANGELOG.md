# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Opaque Redis session tokens (API key never exposed to client)
- Shopify App Proxy HMAC verification
- Mockup ownership verification at session creation
- 10 language translations (TR, DE, FR, ES, PT-BR, IT, NL, JA, KO, ZH-CN)

### Changed
- Session URL parameter: `?token=` → `?session=`
- verify-session now returns studio_config (merged response)
- Error report endpoint accepts optional session token

### Security
- Removed JWT tokens (contained raw API key in base64-decodable payload)
- Added postMessage origin validation on all platforms
- GraphQL search input sanitization
- Settings config whitelist validation
