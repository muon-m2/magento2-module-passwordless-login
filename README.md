# Muon_PasswordlessLogin

Allows Magento 2 customers to log in without a password using a one-time magic link sent to their registered email
address.

## Features

- "Login without password" button on the standard Magento login page, injected via the `form.additional.info`
  extension point (no core template override).
- Dedicated request form at `/passwordlesslogin/login/request`.
- Confirmation landing page at `/passwordlesslogin/login/authenticate` — the customer must click "Sign In" to
  complete login, preventing automated email scanners from triggering authentication.
- Secure single-use, time-limited magic links (SHA-256 hashed token, never stored raw).
- Account status verification before login — locked or unconfirmed accounts are rejected.
- DB-based rate limiting (configurable max requests per email per hour).
- Hourly cron cleanup of expired and consumed tokens.
- Fully translatable (`i18n/en_US.csv`).
- Admin configuration for enable/disable, token lifetime, and rate limit threshold.

## Installation

```bash
bin/magento module:enable Muon_PasswordlessLogin
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Configuration

Navigate to **Stores → Configuration → Muon → Passwordless Login**.

| Field                     | Default | Description                                             |
|---------------------------|---------|---------------------------------------------------------|
| Enable Passwordless Login | Yes     | Enables or disables the feature site-wide.              |
| Token Lifetime (minutes)  | 15      | How long a magic link remains valid before expiring.    |
| Max Requests Per Hour     | 5       | Maximum magic link requests per email address per hour. |

Configuration paths:

- `muon_passwordlesslogin/general/enabled`
- `muon_passwordlesslogin/general/token_lifetime`
- `muon_passwordlesslogin/general/max_attempts`

## Authentication Flow

1. Customer clicks **Login without password** on the login page.
2. Customer submits their email on the request form (POST).
3. If the email is registered and under the rate limit, a magic link email is sent.
4. Customer clicks the link → lands on a confirmation page (GET).
5. Customer clicks **Sign In** on the confirmation page (POST).
6. Module validates the token, checks account status, marks the token as consumed, and logs the customer in.

The two-step confirmation (GET landing + POST submit) prevents email scanners and browser pre-fetchers from
inadvertently consuming the token.

## Public API

### `Muon\PasswordlessLogin\Api\MagicLinkServiceInterface`

| Method | Description |
|---|---|
| `sendLink(string $email): void` | Generates a token and sends the magic link email. Silently succeeds for unknown emails to prevent enumeration. |
| `authenticate(string $rawToken): CustomerInterface` | Validates the token, checks account status (confirmed, not locked), consumes the token, and returns the customer. Throws `LocalizedException` on any failure. |

### `Muon\PasswordlessLogin\Api\TokenRepositoryInterface`

| Method | Description |
|---|---|
| `save(TokenInterface $token): TokenInterface` | Persist a token. |
| `getByToken(string $tokenHash): TokenInterface` | Load a token by its SHA-256 hash. Throws `NoSuchEntityException` if not found. |
| `countRecentByCustomerId(int $customerId, string $since): int` | Count tokens created since a given datetime (used for rate limiting). |
| `deleteExpired(): void` | Delete expired and consumed tokens (called by cron). |

## Controllers

| Controller | HTTP | Route | Description |
|---|---|---|---|
| `Controller/Login/Request` | GET | `/passwordlesslogin/login/request` | Renders the email request form. |
| `Controller/Login/Post` | POST | `/passwordlesslogin/login/post` | Accepts email, calls `sendLink()`. |
| `Controller/Login/Authenticate` | GET | `/passwordlesslogin/login/authenticate` | Renders the sign-in confirmation page. |
| `Controller/Login/AuthenticatePost` | POST | `/passwordlesslogin/login/authenticatePost` | Calls `authenticate()`, logs the customer in. |

## Dependencies

| Module | Reason |
|---|---|
| `Magento_Customer` | Load customer by email; account management (confirmation status, lock check); programmatic login via `CustomerSession`. |
| `Magento_Framework` | Email transport, config, URL builder, Stdlib DateTime. |
| `Magento_Store` | Store-scoped configuration and email context. |

## Security

- Tokens are generated using `random_bytes(32)` and stored as SHA-256 hashes — the raw token only ever exists in
  the email.
- Tokens are single-use: consumed immediately on first valid POST authentication.
- Account confirmation and lock status are checked before the token is consumed.
- All validation errors return a generic message to prevent email enumeration.
- Authentication requires a POST request with a valid form key (CSRF protection).
- Session ID is regenerated after login to prevent session fixation.
- All datetimes use `Magento\Framework\Stdlib\DateTime\DateTime` (UTC) for consistency with Magento's timezone
  handling.

## Known Limitations

- Email-based only. SMS and OAuth are out of scope.
- Rate limiting uses a DB counter (hourly window). For high-traffic stores, a Redis-based sliding window can be
  added without changing the service interface.
- Admin-panel passwordless login is not supported.
- Remember-me / persistent session is not handled by this module.
- The "Login without password" button renders above the Sign In button (inside the `form.additional.info` extension
  point, which Magento places before the actions toolbar). Repositioning it further requires a theme-level CSS
  adjustment or a theme template override — intentionally kept out of module scope to maximise compatibility.
