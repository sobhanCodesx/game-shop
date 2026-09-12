# Google login: connection recovery and hosting checks

The supplied production log reports `cURL error 28: Resolving timed out after
5000 milliseconds` for `openidconnect.googleapis.com/v1/userinfo`. This is a
server-side DNS failure while retrieving the identity after the token exchange.

## Application behavior

- User-info requests make at most three attempts, with 200 ms and 500 ms delays,
  for connection failures, HTTP 429, and HTTP 5xx responses.
- Token exchange is retried only for confirmed DNS/connect failures before the
  request was sent. An ambiguous response timeout is not retried because the
  authorization code may already have been consumed.
- Each attempt retains a 5-second connection timeout and a 12-second total
  timeout. Authentication never retries indefinitely.
- Permanent OAuth errors, invalid state, and unverified identities still fail
  closed. Exhausted connection failures return a Persian message directing the
  user to start a fresh Google login.
- HTTP redirects are disabled for token and identity requests. TLS verification
  remains enabled. OAuth secrets and tokens are not included in the new
  connection-failure warning.

## Production follow-up

Deploy the changed service and controller using the normal application release
process. No migration, dependency installation, or frontend build is needed for
this fix. Refresh PHP OPcache through the hosting deployment process if needed.

Ask the hosting provider to investigate intermittent DNS resolution and outbound
HTTPS connectivity to `oauth2.googleapis.com` and
`openidconnect.googleapis.com` from the PHP hosting environment. Include the
timestamp and DNS timeout from the original log, without credentials or tokens.
Check both DNS resolver health and the host's IPv4/IPv6 routes. Avoid hardcoding
Google IP addresses or disabling certificate verification.

The automated tests simulate network faults; they do not verify the production
host's DNS or perform a real Google account login. Persistent hosting/network
outages cannot be eliminated by application retries. Verify several real login
attempts on the deployed host and monitor for
`Google OAuth connection failed after bounded recovery.`

## Validation

Run `php artisan test --compact tests/Feature/GoogleAuthenticationTest.php`.
The suite covers ordinary login, account linking, remember-me, invalid state,
unverified email, blocked accounts, transient DNS failures at both stages,
userinfo 429/503 recovery, exhausted retries, and non-retryable failures.
