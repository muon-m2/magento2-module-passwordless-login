# Changelog

## [Unreleased]

## [1.1.0] - 2026-10-15

### Added

- `TokenRepositoryInterface::markSupersededByCustomerId()` marks a customer's outstanding tokens as
  consumed without deleting them. Any other implementation of the interface must add it.

### Changed

- The "Login without password" entry point moved from under the sign-in form to the right column,
  under **New Customers**, styled as a login block with its own button.

### Fixed

- **No customer could complete a passwordless login.** The confirmation form posted to
  `authenticate_post`, which Magento's router resolves to a nonexistent `Authenticate\Post`
  controller, so every sign-in ended in a 404. It now posts to `authenticatePost`.
- **The per-hour rate limit never triggered.** Each request deleted the customer's unused tokens,
  which are the rows the limit counts, so the count never passed 1. Outstanding tokens are now
  marked superseded instead. The hourly cleanup now removes only rows created before the one-hour
  window: with the 15-minute default token lifetime, it was also deleting tokens still being counted.
- **The package could not be installed on Magento 2.4.8 or later.** `magento/module-cron: ^101.0`
  is unsatisfiable there, because 2.4.8 and 2.4.9 ship `100.4.x`. The constraint is now `^100.4`.
- **Email enumeration during a mail outage.** A failed send for a registered address showed a
  different message from an unknown address. The failure is now logged, and the response is always
  the generic one.
- The login-page entry point now honours **Enable Passwordless Login**. It was rendered even with
  the feature turned off.
- `i18n/en_US.csv` gains the one phrase it was missing, `Could not supersede tokens: %1`.

## [1.0.0] - 2026-04-07

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
