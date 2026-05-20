# Getting started

- Run `composer` to install dependencies.
- Ensure a mysql server is running (WAMP, docker, etc.)
- `cp .env.example .env` and confiugure the database connection in the `.env` file.
- Run `php artisan migrate` to create the database tables.
- Run `php artisan serve` to start the development server.