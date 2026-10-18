# LoMaS

Loan Management System for small lending operations. This repository currently
contains **Phase 1 — Foundation**, **Phase 2 — Borrowers** and **Phase 3 — Loan
Configuration** of the build order in `AGENTS.md`: authentication, users, roles,
permissions, system settings, the audit trail, the application shell, the
borrower registry, loan products and deduction types.

`AGENTS.md` is the source of truth for the product. Read it before changing
financial behaviour.

## Stack

| Layer    | Choice                                    |
| -------- | ----------------------------------------- |
| Backend  | Laravel 13, PHP 8.5                       |
| Frontend | Svelte 5, TypeScript, Inertia 3           |
| UI       | Tailwind CSS 4, shadcn-svelte, Lucide     |
| Database | MySQL / MariaDB (`DECIMAL(15,2)` money)   |
| Auth     | Fortify (login, reset, 2FA, passkeys)     |
| Access   | `spatie/laravel-permission`               |
| Reports  | `barryvdh/laravel-dompdf` (server PDF)    |
| Quality  | Pest, Pint, Larastan, svelte-check, Vite+ |

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the database, then:
php artisan migrate --seed
npm run build
npm run dev
```

Sign in with the seeded administrator:

```text
email:    admin@lomas.test
password: password
```

Set `LOMAS_ADMIN_NAME`, `LOMAS_ADMIN_EMAIL` and `LOMAS_ADMIN_PASSWORD` in `.env`
**before** seeding any real installation, and change the password after the
first sign in. Public registration is disabled: accounts are only created by an
administrator and are usable immediately.

### Environment

| Variable               | Default            | Purpose                                   |
| ---------------------- | ------------------ | ----------------------------------------- |
| `LOMAS_MONEY_LOCALE`   | `en_PH`            | Locale used to format amounts for display |
| `LOMAS_ADMIN_NAME`     | `Administrator`    | Name of the seeded administrator          |
| `LOMAS_ADMIN_EMAIL`    | `admin@lomas.test` | Email of the seeded administrator         |
| `LOMAS_ADMIN_PASSWORD` | `password`         | Password of the seeded administrator      |

Business name, address, contact details, currency and the loan/borrower number
formats are **not** environment values. They are rows in `system_settings` and
are edited on the System Settings screen.

## Roles and permissions

19 permissions are seeded in `App\Domain\Access\Permission`, grouped for the role
editor, together with five default roles in `App\Domain\Access\Role`:

| Role          | Permissions | Intended use                              |
| ------------- | ----------- | ----------------------------------------- |
| Administrator | 19 (all)    | Full access, user and role administration |
| Loan Officer  | 13          | Borrowers, loans, releases, reports       |
| Collector     | 5           | Borrower lookup and collection posting    |
| Encoder       | 8           | Borrower and loan data entry              |
| Viewer        | 4           | Read only                                 |

Routes are gated with `permission:<permission>` middleware and form requests
authorize themselves, so permissions are enforced on the server regardless of
what the interface shows.

## Conventions

- **Thin controllers.** Controllers validate, delegate and redirect. Business
  rules live in `app/Domain` and `app/Actions`.
- **Money is never a float.** `App\Support\Money` wraps an exact decimal
  (`brick/math`), rejects floats, rounds half up to the currency scale and can
  allocate an amount into parts that always add back up to the original. Money
  columns are `DECIMAL(15,2)`.
- **Financial writes are transactional.** User, role and settings changes run
  inside `DB::transaction`.
- **Everything important is audited.** `App\Domain\Audit\AuditLogger` records who
  changed what, when, from which IP and why. `audit_logs` is append only.
- **Numbering is configurable.** `App\Domain\Settings\SequenceFormatter` renders
  formats such as `LN-{year}-{seq:6}` → `LN-2026-000042`. A format must contain a
  supported placeholder.
- **No invented financial rules.** Unknown rules are listed in `AGENTS.md` §63 and
  are left unimplemented on purpose.

## Tests

```bash
php artisan test                                   # SQLite in memory
DB_CONNECTION=mysql DB_DATABASE=lomas_test php artisan test
vendor/bin/pest tests/Unit/MoneyTest.php            # money arithmetic
```

The suite covers money arithmetic and allocation, number formatting, system
settings, user management, role management, audit logging, borrower management,
loan products, deduction types, the collection calendar and the disabled
registration endpoint.

## Quality checks

```bash
vendor/bin/pint                      # PHP style
vendor/bin/phpstan analyse --memory-limit=1G
npm run types:check                  # svelte-check
npm run check                        # lint + format check
npm run build                        # production assets
```

## Layout

```text
app/Domain/Access      permissions, roles, role persistence
app/Domain/Audit       audit actions and the audit logger
app/Domain/Settings    system settings and number formatting
app/Support/Money      exact decimal money primitive
app/Http/Controllers   thin Inertia endpoints
app/Actions/Users      user creation and update
app/Actions/Borrowers  borrower creation and update
app/Domain/Borrowers   borrower number generation
app/Actions/LoanProducts  loan product creation and update
app/Actions/DeductionTypes deduction type creation and update
app/Domain/Loans      collection calendar (which dates are collected)
app/Domain/Payments   payments, allocations, balances, collection posting
app/Domain/Reporting  PDF renderer and report read models
resources/js/pages    Inertia pages (Svelte 5)
resources/js/components shared components, including the sidebar
resources/views/reports print-only Blade layouts rendered to PDF
```

## Status

| Phase | Scope                                    | State       |
| ----- | ---------------------------------------- | ----------- |
| 1     | Foundation                               | Complete    |
| 2     | Borrowers                                | Complete    |
| 3     | Loan products and deduction types        | Complete    |
| 3.5   | Node identity and offline foundation     | Complete    |
| 4     | Loan encoding                            | Complete    |
| 5     | Loan processing (approval and release)   | Complete    |
| 6     | Amortization                             | Complete    |
| 7     | Collections (daily collection, payments) | Complete    |
| 8     | Reporting (print/PDF)                    | In progress |
| 9     | Production hardening                     | Not started |
| 10    | Branch sync and central consolidation    | Not started |

Navigation shows the modules of later phases as disabled entries so the intended
information architecture is visible without pretending the features exist.

## Reports and printing

Reports are rendered server-side to PDF with `barryvdh/laravel-dompdf`. Each
report has a dedicated, print-only Blade layout under `resources/views/reports`;
the browser page is never printed directly. `App\Domain\Reporting\ReportPdf`
fixes the shared options (A4, remote resources disabled so a branch stays
offline-capable) and a read model such as `LoanScheduleReport` prepares the
formatted figures.

The layouts are dense on purpose. A 100-day schedule is split into balanced
side-by-side columns, so the whole schedule, its summary and its terms fit on a
single A4 sheet. Amounts are grouped from their exact decimal strings, and the
currency is stated once instead of repeated in every cell.

- `GET /loans/{loan}/schedule` renders the amortization schedule inline for
  printing; add `?download=1` to save it as a file.
- `GET /reports/notes-collection?week=YYYY-MM-DD` shows the weekly Notes
  Collection Report (Monday–Saturday) on screen.
- `GET /reports/notes-collection/export?week=YYYY-MM-DD` renders it to PDF
  (`?download=1` to save); add `&format=csv` for a CSV export. Every total is
  recalculated from posted payments, so the document always reconciles with the
  transactions.

## Multi-branch and offline operation

LoMaS is designed to run as several complete installations. Every branch is a
full install with its own database and web UI, and central is the same plus one
responsibility: consolidating what the branches produced. No screen ever waits
on another node, and a node that has never synced can still work.

The rules are in `AGENTS.md` section 69. The parts that shape the schema, so they
must land before loans exist:

- Every domain table gets a `uuid v7` primary key and a `node_id`, never an
  auto-increment counter.
- Business numbers are node qualified, e.g. `BR-MNL1-000001`, so two offline
  nodes cannot collide and no central allocator is needed.
- Central owns configuration, such as loan products, deduction types and number
  formats, and distributes it; a branch reads configuration and cannot change it.
- Every business write also appends an immutable `sync_events` row in the same
  transaction, and inbound bundles are recorded in `sync_batches`.
- Records move between nodes as signed files, verified and applied idempotently
  on `(node_id, sequence)`.
- Reports are computable per node and hub-wide, and every figure groups by
  `node_id`.

A loan belongs to the branch that encoded it, and only that branch collects it.

## Loan products and deduction types

Loan configuration is data, not code. Nothing about a product is hard coded in
the loan engine.

A loan product holds the term, the collection calendar and the interest:

- `term` is counted in `term_unit`, which today only accepts `collection_days`.
  The term is never a number of calendar days.
- `payment_frequency` today only accepts `daily`.
- `exclude_sundays` and `exclude_saturdays` decide which weekdays are collected.
  Sunday is always excluded for the current product, and Saturday is collected,
  so the seeded product runs Monday to Saturday.
- `release_date_is_day_one` is off for the seeded product, so the first
  collection is the next collection day after the release date.
- `interest_method` and `interest_rate` belong to the product. Interest is
  withheld at release and is never collected again, so the schedule collects the
  principal only. There is no interest deduction type.
- Only an implemented term unit and payment frequency are accepted, so a product
  can never be configured in a way the engine cannot honour.

The weekend rules are read through `CollectionCalendar`, the single place that
decides whether a date is a collection day. `AmortizationScheduleGenerator`
consumes it at release to freeze the schedule, and the generator never runs
again after that. A non-even division is settled on the final installment, so
the installments always sum to the exact total amount payable.

A deduction type describes how a deduction is calculated, never an amount that
was already charged:

- `percentage_of_loan_amount` needs a `rate`; `fixed_amount` needs a
  `fixed_amount`. The other value is rejected and never stored, so a type cannot
  carry both.
- Rates and amounts are stored as exact decimals and are never passed through a
  float.
- Editing a type or a product never changes a released loan. The amount charged
  to a loan is copied onto the loan as a snapshot when the loan is encoded.

Creating or updating either record writes an audit entry with the previous
values, and an update that changes nothing writes nothing.

The seeded configuration is `LOAN-100D` (100 collection days, Monday to
Saturday, 10% interest withheld at release) with two deduction types,
`DOC_STAMP` at 1% and `PASSBOOK` at ₱50. The seeder is idempotent on the code
column, so re-seeding never overwrites a change made by staff.

## Borrowers

A borrower is the person a loan belongs to and is reused across every loan. The
record is never deleted, because loans and payments must always resolve the
person they belong to; an inactive borrower keeps its history but cannot receive
a new loan.

- The business number, e.g. `BR-MNL1-000001`, is generated on the server from the
  `borrower_number_format` system setting and is unique for the record.
- Search runs on the server and matches the borrower number, any part of the
  name, or the mobile number. Every word of the search term has to match, and
  wildcard characters are treated as literal text.
- The list is sortable by borrower number, name, mobile number and registration
  date, and is paginated.
- Creating and updating a borrower writes an audit entry. An update that changes
  nothing writes nothing.
