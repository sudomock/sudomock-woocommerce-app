# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Opaque short-lived session tokens (API key never exposed to client)
- Signed-request verification on session creation
- Mockup ownership verification at session creation
- 10 language translations (TR, DE, FR, ES, PT-BR, IT, NL, JA, KO, ZH-CN)

### Changed
- Session URL parameter: `?token=` → `?session=`
- verify-session now returns studio_config (merged response)
- Error report endpoint accepts optional session token

### Security
- Hardened session-token handling so credentials are never exposed to the browser
- Added postMessage origin validation on all platforms
- Mockup search input sanitization
- Settings config whitelist validation
