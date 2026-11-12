# Staygo — Laravel Worldwide Hotel Discovery & Booking Platform

**Tagline:** Find your stay. Love your journey.

Staygo is a Laravel 13 starter application based on the supplied project plan. It includes a responsive public website, customer registration/login, hotel and destination pages, Wikipedia destination summaries, optional Amadeus hotel search, optional OpenWeather data, reviews with moderation, favourites, booking-request records, and starter admin/manager dashboards.

> **Important truth about live inventory:** Wikipedia does not provide hotel prices, room availability, or booking confirmations. This project uses Wikipedia only for destination editorial summaries. Amadeus is the initial hotel-supplier adapter. You must obtain supplier credentials and have the required API access enabled. The included seeded hotel rows are explicitly development/sample records, not real live inventory. A booking request is not a confirmed reservation. Staygo only labels a reservation confirmed after a supplier booking API has returned a valid confirmation; that supplier booking/payment workflow still needs to be implemented and certified before production.

## 1. Requirements

- PHP **8.3 or later** (Laravel 13 requirement)
- Composer 2
- MySQL 8.0+ (or compatible MariaDB)
- Node.js/npm only if you later add a frontend build pipeline; this starter uses Bootstrap CDN and plain JS
- PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml

Check versions:

```bash
php -v
composer --version
mysql --version
```

If your MAMP PHP is older than 8.3, select/enable a PHP 8.3+ version in MAMP or use a compatible local PHP installation. Laravel 13 will not run on PHP 8.2.

## 2. Install on macOS with MAMP

1. Extract `staygo.zip`.
2. Move the `staygo` folder to your development directory, for example:
   `/Applications/MAMP/htdocs/staygo`
3. Open Terminal and run:

```bash
cd /Applications/MAMP/htdocs/staygo
composer install
cp .env.example .env
php artisan key:generate
```

4. Start MySQL in MAMP and create a database named `staygo` using phpMyAdmin.
5. Edit `.env` and set the database credentials. Typical MAMP defaults are often:
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=8889` (check your MAMP MySQL port)
   - `DB_DATABASE=staygo`
   - `DB_USERNAME=root`
   - `DB_PASSWORD=root` (check your local MAMP settings)

6. Run migrations and sample seed data:

```bash
php artisan migrate --seed
php artisan storage:link
```

7. Start Laravel's development server:

```bash
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

8. Visit `http://127.0.0.1:8000`.

You can use MAMP's Apache virtual host instead, but its document root must point to the Laravel project's **public** directory, never the project root.

## 3. Demo administrator

The database seeder creates:

- Email: `admin@staygo.test`
- Password: `ChangeThisPassword123!`

Log in and change this password immediately. Do not use this demo credential on a public server. For a production installation, remove or replace the demo seeder account and create the first administrator securely.

## 4. Configure real hotel supplier search

### Amadeus Self-Service API

1. Create an account at https://developers.amadeus.com/.
2. Create an app and obtain the API key and API secret.
3. Start with the test environment. Check API product availability and current access/booking restrictions for your account.
4. Add credentials to `.env`:

```dotenv
AMADEUS_BASE_URL=https://test.api.amadeus.com
AMADEUS_CLIENT_ID=your_client_id
AMADEUS_CLIENT_SECRET=your_client_secret
```

5. Run:

```bash
php artisan config:clear
```

The homepage destination autocomplete requests Amadeus city suggestions and fills the matching 3-letter IATA city code (`city_code`) when the customer selects a suggestion. Manual text entry remains available, but live hotel search requires a valid city code. The Amadeus adapter uses:
- OAuth client-credentials token endpoint
- City/hotel reference-data endpoints
- Hotel Offers Search endpoint

The API may return no hotels/offers because of coverage, test data, credentials, city code, date, or account permissions. The code reports no live offers rather than inventing prices. The initial UI displays live offer totals when the supplier actually returns them.

**Before production:** implement the supplier's full booking/confirmation endpoint, supplier-specific cancellation and modification, price recheck, idempotency, webhook/reconciliation, and payment orchestration. Confirm which Amadeus products are enabled for your account. The included booking request flow stores a `pending_supplier_check` request only and never claims the room is reserved.

Other supplier integrations (Booking.com Demand, Expedia Rapid, Hotelbeds) require separate commercial onboarding, contracts, credentials, and provider-specific API adapters. They are not activated by adding a Wikipedia key.

## 5. Wikipedia destination summaries

No API key is required for the public Wikipedia REST summary endpoint. Staygo calls:

`https://en.wikipedia.org/api/rest_v1/page/summary/{title}`

The service caches summaries for 12 hours and displays a link to the source page. Wikipedia is editorial destination content, not a source for current hotel inventory or rates. Respect Wikimedia API terms, attribution expectations, rate limits, and user-agent guidance.

## 6. Weather

Get an API key from https://openweathermap.org/api and add:

```dotenv
OPENWEATHER_API_KEY=your_key
```

Then clear config:

```bash
php artisan config:clear
```

The destination page displays current weather only. It does not invent a forecast for future travel dates.

## 7. Main routes

| Route | Purpose |
|---|---|
| `/` | Staygo homepage |
| `/search` | Hotel search |
| `/hotels/{hotel}` | Hotel details, reviews, availability request |
| `/destinations/{city}` | Wikipedia summary and weather |
| `/register`, `/login` | Customer authentication |
| `/account` | Customer dashboard and favourites |
| `/my-bookings` | Booking request history |
| `/admin` | Admin dashboard |
| `/admin/users` | User management |
| `/admin/reviews` | Review moderation |
| `/manager` | Starter manager dashboard |

## 8. Roles

- `customer`: normal customer access
- `admin`: admin dashboard, user deletion, review moderation
- `manager`: starter manager dashboard

Manager property ownership, inventory permissions, and rate editing are intentionally not exposed yet. A real implementation must map managers to authorised properties and must not let managers change supplier-owned prices.

## 9. Database tables

- `users`
- `hotels`
- `reviews`
- `bookings`
- `favourites`
- Laravel `sessions` and `password_reset_tokens`

This is the first foundation schema, not the final complete schema from the full product vision. Future phases should add suppliers, hotel-room mappings, room inventory, rate plans, booking guests, payment transactions, refunds, promotions, notifications, support tickets, audit logs, and supplier price snapshots.

## 10. Security and production checklist

Before going live:

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a strong unique `APP_KEY`.
- Use HTTPS and secure cookies.
- Never commit `.env` or API credentials.
- Use least-privilege database credentials.
- Add email verification and password reset delivery.
- Add policy-based authorization and manager-property ownership checks.
- Add upload malware scanning, image processing, file-size limits, and moderation.
- Add supplier price revalidation and booking idempotency.
- Implement payment webhooks and reconciliation. Never store full card numbers or CVC.
- Implement cancellation/refund flows with the supplier and payment provider.
- Add rate limiting, audit logs, monitoring, backups, and GDPR data-request workflows.
- Review API provider terms, commercial approval, and permitted display/cache rules.
- Add automated feature/integration tests with mocked supplier responses.
- Never treat seeded sample properties as real hotel inventory.
- Do not mark a booking as confirmed until the supplier confirms it.

## 11. Useful commands

```bash
php artisan route:list
php artisan migrate:status
php artisan migrate:fresh --seed   # WARNING: deletes existing tables/data
php artisan storage:link
php artisan optimize:clear
php artisan test
```

## 12. Official documentation

- Laravel 13: https://laravel.com/docs/13.x
- Amadeus for Developers: https://developers.amadeus.com/
- Wikipedia REST API: https://en.wikipedia.org/api/rest_v1/
- OpenWeather: https://openweathermap.org/api
- OpenStreetMap: https://www.openstreetmap.org/
- Stripe: https://docs.stripe.com/

## Current delivery status

This ZIP is a runnable Laravel 13 foundation with working UI and first-pass core workflows. It is **not yet a production-ready global OTA**. Real-time supplier inventory depends on approved API credentials and the supplier booking lifecycle, payment orchestration, cancellation, room inventory, email, and production security work described above. Build and test those phases before accepting customer payments or promising reservations.
