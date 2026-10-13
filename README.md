# Getting started

- Run `composer` to install dependencies.
- Ensure a mysql server is running (WAMP, docker, etc.). You can use the provided `docker-compose.yml` to set up a mysql server using docker.
- `cp .env.example .env` and confiugure the database connection in the `.env` file.
- Run `php artisan migrate` to create the database tables.
- Run `php artisan serve` to start the development server.

## Tests and coverage

```sh
composer test
composer run test:coverage
```

Coverage requires PHP 8.3+ for PHPUnit 11, with Xdebug (and `pdo_sqlite`). The coverage command
enables Xdebug coverage, runs PHPUnit, writes HTML/Clover reports, and then fails
if global executable-line coverage across **all of `app/`** is below 50%.
The working target is 60% or more; classes/methods remain informational.

Open `coverage/html/index.html` for the report. CI can consume
`coverage/clover.xml`. The gate can also be run directly:

```sh
php scripts/check-coverage.php coverage/clover.xml 50
```

Feature tests use SQLite in memory, seeded roles and fake storage/mail. Google,
Groq and Stripe are mocked at their integration boundaries; billing tests reject
unexpected Stripe transport calls. No live service credentials or production
database are required. Existing authorization and authentication tests are retained.

## Email and AI diagnostics

Password recovery is available at `/forgot-password` and `/reset-password` on
the frontend. Set `FRONTEND_URL` to that frontend's public origin so reset emails
link to the right deployment. Laravel's existing password broker stores hashed,
single-use tokens, expiring after 60 minutes by default. Google-linked accounts
are excluded. Requests use a generic response and are rate limited; successful
resets revoke API tokens and stored sessions. No new database migration is needed.
Reset emails use the configured mail transport and `password_reset` diagnostics.

The `integrations` channel writes one JSON record per event to stderr, visible in
the application's container logs. It defaults to `info`, independently of
`LOG_CHANNEL` and `LOG_LEVEL`; set `INTEGRATION_LOG_LEVEL` to change its verbosity.
Redeploy the backend to load these changes. If configuration is cached, rebuild
that cache as part of deployment.

Filter for `mail.send.failed` or `ai.request.failed`, then follow the matching
`context.operation_id` to see the attempt and its outcome. Logs include the
notification type, user/recipe/subscription IDs, duration, exception class and
message, configured mailer/transport or AI model, and whether credentials are
configured. Credentials and email addresses are redacted from exception messages;
email bodies, AI prompts and generated text are not logged as payloads.

`mail.send.completed` means the configured transport accepted the message, not
that it reached an inbox. `delivery_mode: simulated` identifies `log`/`array`
transports, which do not send email. Provider transports also log the message ID
when available. For failures, inspect `error_message`, `from_domain`, mailer and
credential-presence flags; a missing key, SMTP connection problem or unverified
sender can then be distinguished.

AI events distinguish cached suggestions, denied access, database loading,
provider requests, empty responses and suggestion persistence failures. Check
`stage`, `model`, `api_key_configured` and `provider_error_type` on failures.
The chat-completions route requires a chat model; the example environment's
`GROQ_MODEL=whisper-large-v3-turbo` setting should be checked if copied to production.
