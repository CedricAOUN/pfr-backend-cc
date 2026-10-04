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
