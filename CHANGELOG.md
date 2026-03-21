# Changelog

## [Unreleased]

## [1.0.0] - 2026-03-13

### Added

- Initial module release.
- Magic link (passwordless) login flow for frontend customers.
- `Api\MagicLinkServiceInterface` — generates and sends a one-time login link.
- `Api\TokenRepositoryInterface` — token persistence (save, get by hash, delete expired).
- `Api\Data\TokenInterface` — token DTO.
- `muon_passwordless_login_token` database table with indexes for token lookup, rate-limit query, and cron cleanup.
- Admin configuration: enable/disable, token lifetime, max requests per hour.
- Rate limiting: DB-based, per email, per hour window.
- Hourly cron job to delete expired and consumed tokens.
- "Login without password" link injected on the standard Magento login page.
- Dedicated request form at `/passwordlesslogin/login/request`.
- Magic link email template (`muon_passwordlesslogin_magic_link`).
- ACL resource `Muon_PasswordlessLogin::config`.
- Unit tests for `MagicLinkService` and `TokenRepository`.
- `i18n/en_US.csv` with all user-facing strings.
