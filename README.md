# Instructor Revenue Ledger

The money core of an LMS: subscription payments come in, each instructor's share is recognized over time, and payouts go out through an unreliable payment provider without ever paying anyone twice.

Design reasoning lives in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). How I used AI is in [docs/AI_USAGE.md](docs/AI_USAGE.md).

## Setup

Requirements: PHP 8.2+ with the `intl` extension (Filament needs it), Composer, and MySQL 8 or MariaDB. I developed and tested on MariaDB (XAMPP); everything the design relies on (unique indexes, CHECK constraints, row locks) behaves the same on MySQL 8.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create two databases, `ledger` and `ledger_test`, and set your MySQL credentials in `.env` with `DB_CONNECTION=mysql`, `DB_DATABASE=ledger` and `QUEUE_CONNECTION=database`.

```bash
php artisan migrate --seed
php artisan make:filament-user
```

## Running the money flow

```bash
php artisan ledger:recognize --date=2026-09-20   # recognize earnings up to a date
php artisan ledger:payout --date=2026-09-20      # reserve balances and queue payouts
php artisan queue:work --stop-when-empty         # send payouts through the mock provider
php artisan ledger:reconcile --stale=0           # settle unknown or stuck payouts
```

Then open `/admin` to see each instructor's earned, paid, in flight and outstanding amounts, plus their payout history.

By default the mock provider picks a random outcome per payout (success, permanent failure, or timeout after the money already moved), so each run looks a little different. To demo one scenario on purpose, force the outcome:

```bash
MOCK_PAYMENT_OUTCOME=timeout php artisan queue:work --stop-when-empty   # everything lands in "unknown"
php artisan ledger:reconcile --stale=0                                   # settles them without resending
```

Valid values are `success`, `fail` and `timeout`. Leave it unset for random.

## Running the tests

```bash
./vendor/bin/pest
```

Tests run against the `ledger_test` MySQL database on purpose. The idempotency guarantees depend on unique indexes, CHECK constraints and row locks, so I wanted the tests to exercise the real engine rather than SQLite.

The suite covers the rounding and recognition math (unit tests), recognition idempotency and refunds, and the three required payout guarantees: running the payout twice, retried or duplicated jobs, and provider timeouts never cause a double payment. It also covers permanent failure and a worker crashing after the money moved.

## Assumptions

1. All money is stored as integer piastres. There's no multi-currency support.
2. A subscription's term runs from `starts_on` (inclusive) to `ends_on` (exclusive). The start day counts as day 1, and the refund day counts as a used day.
3. Engagement is modeled as a fixed weight per instructor per subscription (for example, watch minutes). Weights don't change mid-term.
4. Students aren't modeled. They aren't needed for the money logic, so a subscription only carries its amount, term and instructors.
5. Instructors are a separate table from `users`. The `users` table only holds admins who log in to Filament.
6. The platform's share isn't written to the ledger. It's derived as payment minus instructor earnings, so leftover piastres from rounding stay with the platform automatically.
7. The refund payable to the student is calculated by the same formula (payment minus earned to date) but isn't executed as a money movement here, since the brief only covers instructor payouts.

## Known gaps

See the "Known limitations" section in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
