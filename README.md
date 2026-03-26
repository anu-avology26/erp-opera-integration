# opera-erp-integration

This project keeps Business Central corporate customers and their credit settings in sync with Oracle Opera Cloud AR accounts and writes Opera account numbers back to the ERP, with an admin UI and API for running and monitoring the integration.

## Prerequisites

- PHP 8.2+, Composer, Node/npm (for assets if using dashboard with Vite).
- Database (SQLite for dev; MySQL/PostgreSQL for production as needed).
- ERP (Business Central 365) and Opera Cloud OHIP credentials and endpoints (see [Requirements](#1-requirements)).

## Initial setup

1. Clone the repo and install dependencies:
   ```bash
   composer install
   npm install && npm run build
   ```

2. Copy environment and configure:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env`: set ERP_* and OPERA_* variables (and DB_* if not using SQLite). Admin dashboard is protected by login.

3. Run migrations and seed admin user:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
   Default admin: **admin@example.com** / **password**. Change in production.

