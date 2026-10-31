# Authentication service

## API

- `POST /api/user_register` accepts `email` and `password` JSON properties. Passwords must be at least 8 characters. It returns `201` on success and `409` if the email is already registered. A confirmation email is sent to the new address.
- `GET /api/verify/email` validates the signed link from that email and marks the account as verified. The link does not require an API token.
- `POST /api/user_login` accepts the same credentials and returns an API token.
- Send that token in the `X-AUTH-TOKEN` header to access protected endpoints. `GET /api/me` returns the authenticated user's public profile, and `POST /api/user_logout` revokes the current token.

Only a hash of each API token is stored. Passwords are hashed by Symfony's configured password hasher.

## Mail

`MAILER_DSN` defaults to `sendmail://default`, which uses the local sendmail binary. Point it at a real SMTP server in `.env.local` where needed. Tests use `null://null`, and assertions read messages through Symfony's mailer test transport.

## Fixtures and tests

Load the development fixtures with `php bin/console doctrine:fixtures:load --no-interaction`. Loading fixtures purges existing fixture-managed data. The fixture account is `demo@example.com` with password `ChangeMe123!`.

Tests are split into three suites:

- `composer unit-test` runs `tests/Unit` (no kernel).
- `composer integration-test` runs `tests/Integration` (kernel services, database, mailer).
- `composer application-test` runs `tests/Application` (HTTP requests through the firewall).

Run all of them with `php bin/phpunit`, and check code style with `composer csfix`.
