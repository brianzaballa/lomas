# AGENTS.md

# LoMaS

## Loan Management System

LoMaS is a web-based **Loan Management System for small lending operations**.

The system manages:

* Borrowers
* Loan products
* Loan applications
* Loan approvals
* Loan releases
* Dynamic loan deductions
* Amortization schedules
* Daily collections
* Payments
* Loan balances
* Weekly collection reporting
* Monthly progress reporting
* Users
* Roles and permissions
* System settings
* Audit history

---

# 1. Technology Stack

Use the following stack:

```text
Backend:
Laravel

Frontend:
Svelte 5
TypeScript
Inertia

UI:
Tailwind CSS
shadcn-svelte
Lucide Icons

Database:
MySQL

Build:
Vite
```

Prefer Laravel's official Svelte starter kit where possible.

Do not introduce a separate frontend application unless there is a clear architectural requirement.

The Laravel application should remain the primary source of truth.

---

# 2. Core Philosophy

LoMaS is a financial system.

Financial correctness is more important than implementation speed.

The system must be:

* Accurate
* Auditable
* Traceable
* Fast to encode
* Easy to understand
* Easy to maintain
* Extensible
* Safe against accidental data modification

Avoid overengineering.

Do not build future functionality until it is required.

However, the architecture must not prevent future loan products and lending rules.

---

# 3. Initial Loan Product

The initial product is:

```text
100-Day Loan
```

Rules:

```text
Term:
100 collection days

Payment Frequency:
Daily

Sunday:
Excluded

Saturday:
Configurable
```

The term means:

> 100 valid collection days, not 100 calendar days.

Do not calculate maturity by simply adding 100 calendar days.

---

# 4. Product-Driven Architecture

Do NOT hard-code the 100-day loan into the application.

Create a configurable:

```text
LoanProduct
```

The initial product should be:

```text
100-Day Loan
```

The architecture should later allow:

```text
50-Day Loan
60-Day Loan
90-Day Loan
120-Day Loan
Weekly Loan
Monthly Loan
```

without rewriting the loan engine.

---

# 5. Loan Product Settings

A loan product should support configurable settings.

Suggested fields:

```text
id
name
code
description
term
term_unit
payment_frequency
exclude_sundays
exclude_saturdays
release_date_is_day_one
interest_method
interest_rate
is_active
created_at
updated_at
```

Notes on the fields:

```text
term          counts the unit in term_unit, not calendar days
interest_rate belongs to the product, not to a deduction type
is_active     an inactive product stays available for loans released under
              it and is not offered when a new loan is encoded
```

Initial configuration:

```text
name = 100-Day Loan
code = LOAN-100D
term = 100
term_unit = collection_days
payment_frequency = daily
exclude_sundays = true
exclude_saturdays = false        Monday to Saturday, see section 63.1.12
release_date_is_day_one = false  first collection is the next collection day
interest_method = percentage_of_loan_amount
interest_rate = 10
is_active = true
```

Only a term unit and a payment frequency that the engine actually implements
are accepted. Adding weekly or monthly products means adding the case here and
the behaviour it needs, not bypassing the validation.

---

# 6. Dynamic Loan Deductions

Deductions must be configurable through system settings.

Do NOT hard-code:

```text
Doc Stamp
Passbook
Other
```

inside the loan calculation code.

### Interest

Interest is a deduction, but it is not a deduction type. It is defined by the
loan product:

```text
Calculation:
Percentage of Loan amount, using the rate on the product

Loan Amount = ₱10,000
Product interest rate = 10%

Interest = ₱1,000
```

The interest method and rate live on `loan_products`. See sections 7 and 63.1.2.

Initial deduction types:

---

### Documentary Stamp

Default:

```text
1% of Loan Amount
```

Example:

```text
Loan Amount = ₱10,000

Doc Stamp = ₱100
```

The rate should be configurable through settings.

---

### Passbook

Default:

```text
₱50 fixed amount
```

The amount should be configurable.

---

### Other

The system must allow users with proper permission to create additional deduction types.

Examples:

```text
Processing Fee
Insurance
Membership
Service Charge
Other Charges
```

---

# 7. Deduction Types

Suggested table:

```text
loan_deduction_types

id
name
code
calculation_method
rate
fixed_amount
is_active
sort_order
created_at
updated_at
```

Supported initial methods:

```text
percentage_of_loan_amount
fixed_amount
```

Future methods may be added.

Only the value that belongs to the method is stored, so a type never carries a
rate and a fixed amount at the same time.

Example:

```text
Documentary Stamp
    calculation_method = percentage_of_loan_amount
    rate = 1

Passbook
    calculation_method = fixed_amount
    fixed_amount = 50
```

There is deliberately no Interest row. Its rate is on the loan product, see
section 63.1.2.

---

# 8. Loan Deduction Snapshot

CRITICAL:

When a loan is created/released, preserve the exact deduction configuration used by that loan.

Never recalculate historical loans using current settings.

Example:

At release:

```text
Interest = 10%
Doc Stamp = 1%
Passbook = ₱50
```

Later settings change:

```text
Interest = 12%
Doc Stamp = 1.5%
Passbook = ₱75
```

The original loan must remain:

```text
Interest = 10%
Doc Stamp = 1%
Passbook = ₱50
```

Suggested table:

```text
loan_deductions

id
loan_id
deduction_type_id
name
code
calculation_method
rate
base_amount
amount
created_at
updated_at
```

Store enough information to reproduce the original calculation.

---

# 9. Loan Financial Summary

Always distinguish:

```text
Gross Loan Amount
Total Interest
Total Other Deductions
Total Deductions
Net Proceeds
Total Amount Payable
Amount Paid
Outstanding Balance
```

Do not use ambiguous labels such as:

```text
Amount
Total
Balance
```

without context.

Prefer:

```text
Gross Loan Amount
Outstanding Loan Balance
Total Amount Paid
Net Proceeds
```

---

# 10. Loan Calculation

Create dedicated services.

Recommended:

```text
LoanCalculator
LoanInterestCalculator
LoanDeductionCalculator
LoanReleaseService
LoanBalanceCalculator
```

The frontend may display estimated calculations.

Laravel must recalculate all financial values before persistence.

The server is authoritative.

---

# 11. Interest Calculation

The initial interest method is:

```text
Percentage of Loan Amount
```

Example:

```text
Loan Amount = ₱10,000
Interest Rate = 10%

Interest = ₱1,000
```

Do not assume reducing-balance interest unless explicitly required.

The interest method should belong to the loan product.

---

# 12. Net Proceeds

The system must clearly distinguish:

```text
Gross Loan
Deductions
Net Proceeds
```

Basic formula:

```text
Net Proceeds =
Gross Loan Amount - Applicable Deductions
```

However, clarify whether interest is included in the amount deducted from proceeds.

Do not silently assume this rule.

---

# 13. Total Payable

The system must distinguish:

```text
Gross Loan Amount
Interest
Other Deductions
Net Proceeds
Total Amount Payable
```

For the initial flat-interest model:

```text
Total Amount Payable =
Loan Principal + Interest
```

Other deductions may affect the amount actually released without necessarily changing the amount owed.

This distinction must be explicit in the implementation.

Resolved behaviour for the initial product: interest is withheld at release, so
`Total Amount Payable` is the principal alone and the schedule collects the
principal only. See section 63.1.2. A product that collects interest over the
term keeps the formula above.

---

# 14. Amortization Engine

Create a dedicated:

```text
AmortizationScheduleGenerator
```

Do not generate amortization schedules inside controllers or Svelte components.

Input:

```text
loan
start_date
term
exclude_sundays
exclude_saturdays
```

Output:

```text
100 valid collection dates
```

Each installment should contain:

```text
installment_number
due_date
principal_due
interest_due
total_due
paid_amount
remaining_amount
status
```

---

# 15. Collection Calendar

Create a reusable:

```text
CollectionCalendar
```

Responsibilities:

```text
isCollectionDay(date)
nextCollectionDay(date)
previousCollectionDay(date)
generateCollectionDates(startDate, numberOfDays)
```

Initial rules:

```text
Sunday = excluded
Saturday = configurable
```

Do not duplicate weekend logic across controllers, reports, and frontend code.

---

# 16. Saturday Rule

The product must have:

```text
exclude_saturdays
```

When:

```text
true
```

skip:

```text
Saturday
Sunday
```

When:

```text
false
```

allow:

```text
Saturday
```

while still excluding Sunday.

The amortization generator must continue until exactly the configured number of collection days has been generated.

---

# 17. First Collection Date

The following rule must be explicitly configured before production:

```text
Does the release date count as Day 1?
```

Possible behavior:

```text
Release date is Day 1
```

or:

```text
First collection is the next valid collection day
```

Do not assume this silently.

Keep the rule inside the collection calendar/amortization service.

---

# 18. Amortization Rounding

Financial calculations must not create rounding drift.

Example:

```text
Total Payable = ₱11,000
Term = 100 days

Daily = ₱110
```

If an amount cannot be evenly divided:

```text
Total of all installments must equal
the exact Total Amount Payable.
```

Use an explicit rounding strategy.

A common strategy is:

```text
Calculate normal installments
Calculate rounding difference
Apply difference to final installment
```

Never allow:

```text
SUM(installments) != Total Payable
```

---

# 19. Financial Precision

Never use floating-point numbers for money.

Database:

```text
DECIMAL(15,2)
```

or another appropriate fixed-precision decimal type.

Avoid:

```text
FLOAT
DOUBLE
```

for monetary fields.

Use decimal-safe calculations in Laravel/PHP.

---

# 20. Borrowers

A borrower can have multiple loans.

Suggested fields:

```text
id                 uuid, not an auto-increment counter
node_id            the node that owns the borrower
borrower_number    node qualified, e.g. BR-MNL1-000001
first_name
middle_name
last_name
suffix
birth_date
mobile_number
address
status
created_at
updated_at
```

Borrower information must be reusable across loans.

A borrower belongs to the node that registered it. Central does not own
borrowers and never edits them. Two nodes may register the same person; see
section 69.9.

---

# 21. Borrower Number

Generate a unique business identifier, unique across every node.

Example, issued by node MNL1:

```text
BR-MNL1-000001
BR-MNL1-000002
```

Do not expose raw database IDs as the primary business identifier.

```text
The node code is part of the number, so uniqueness is global without a central
allocator. A branch can therefore issue numbers while completely offline.
See sections 69.2 and 69.3.
```

---

# 22. Loan Number

Generate a unique loan number, unique across every node.

Example, issued by node MNL1:

```text
LN-MNL1-2026-000001
LN-MNL1-2026-000002
```

The format should be configurable, and must contain the node token and a
sequence token:

```text
LN-{node}-{year}-{seq:6}
```

See sections 21, 41 and 69.3.

---

# 23. Loan Lifecycle

Initial statuses:

```text
Draft
Pending Approval
Approved
Released
Active
Past Due
Completed
Cancelled
```

Do not delete released loans.

Use status transitions.

---

# 24. Loan Encoding UX

The loan encoding screen is one of the most important screens in LoMaS.

Optimize for:

> Minimum clicks + minimum typing + maximum visibility of financial calculations.

Preferred workflow:

```text
1. Search Borrower
2. Select Borrower
3. Select Loan Product
4. Enter Loan Amount
5. Calculate Deductions
6. Show Net Proceeds
7. Show Total Payable
8. Show Daily Amortization
9. Review
10. Save
```

Avoid unnecessary multi-page forms.

---

# 25. Loan Calculator UI

As the encoder enters the loan amount, immediately display:

```text
Loan Amount
Interest
Documentary Stamp
Passbook
Other Deductions
-------------------------
Total Deductions
Net Proceeds
Total Amount Payable
Daily Amortization
```

Use live calculations for UX.

However:

> The frontend calculation is only a preview.

Laravel must recalculate before saving.

---

# 26. Fast Data Entry

Optimize for keyboard users.

Support:

```text
Tab
Shift + Tab
Enter
Escape
Ctrl/Cmd + K
```

where appropriate.

Use:

```text
Combobox
Autocomplete
Searchable Select
Numeric Input
Date Picker
```

instead of large dropdowns.

Avoid unnecessary modal dialogs.

---

# 27. Borrower Search

Borrower search should support:

```text
Borrower Number
Name
Mobile Number
Loan Number
```

Search should be fast and server-side.

Use debounce for live search.

Do not load the entire borrower table into the browser.

---

# 28. Collection Encoding

The daily collection screen should be optimized for batch entry.

Preferred workflow:

```text
Select Date
    ↓
Show Scheduled Collections
    ↓
Enter Payment Amounts
    ↓
Calculate Remaining Balances
    ↓
Review
    ↓
Post Collection
```

Possible columns:

```text
Loan #
Borrower
Due
Paid
Remaining
Status
```

---

# 29. Payments

Payments must be separate historical transactions.

Suggested table:

```text
payments

id
payment_number
loan_id
payment_date
amount
principal_amount
interest_amount
other_amount
payment_method
reference_number
received_by
status
created_at
updated_at
```

Do not overwrite payment history.

---

# 30. Payment Allocation

Payment allocation must be explicit.

A payment may be allocated against:

```text
Principal
Interest
Penalty
Other
```

The allocation method should be a defined business rule.

Do not silently invent allocation priorities.

---

# 31. Partial Payments

Support partial payments.

Example:

```text
Installment Due = ₱110

Payment = ₱50

Remaining = ₱60
```

Status:

```text
Partially Paid
```

Do not mark the installment fully paid.

---

# 32. Overpayments

Overpayment handling must be explicitly defined.

Possible future behaviors:

```text
Apply to next installment
Store as borrower credit
Refund
```

Do not invent a rule.

---

# 33. Payment Reversal

Never delete a posted financial transaction.

Instead use:

```text
Reversal
Void
Adjustment
```

with:

```text
reason
user
timestamp
original transaction
```

---

# 34. Weekly Notes Collection Report

Create:

```text
Notes Collection Report
```

The normal reporting week is:

```text
Monday
Tuesday
Wednesday
Thursday
Friday
Saturday
```

Sunday is excluded.

The report must support a selected week.

Example:

```text
Week:
September 28 – October 3, 2026
```

The report should show daily collections.

Example:

```text
Monday       ₱25,000
Tuesday      ₱24,500
Wednesday    ₱26,100
Thursday     ₱23,800
Friday       ₱27,400
Saturday     ₱21,900
-----------------------
TOTAL       ₱148,700
```

Do not manually store report totals.

Calculate them from payment transactions.

---

# 35. Notes Collection Report Details

The report should support:

```text
Date
Loan Number
Borrower
Scheduled Amount
Amount Collected
Balance
Status
```

Include daily totals and weekly totals.

Filters may include:

```text
Collection Date
Collector
Loan Product
Borrower
Loan Status
```

---

# 36. Notes Collection Report Output

Support:

```text
Print
PDF
Excel
CSV
```

The printed report should be compact and operational.

Do not simply print the web page.

Create a dedicated print layout.

---

# 37. Monthly Progress Report

Create:

```text
Progress Report
```

The report is generated monthly.

User selects:

```text
Year
Month
```

Example:

```text
October 2026
```

Suggested metrics:

```text
Beginning Portfolio
New Loans
Gross Loan Releases
Total Collections
Principal Collected
Interest Collected
Other Collections
Completed Loans
Active Loans
Past Due Loans
Outstanding Balance
Ending Portfolio
```

The exact definitions of each metric must be documented.

---

# 38. Report Reconciliation

Every report must be traceable to source transactions.

For example:

```text
Monthly Collections
=
SUM(valid posted payments within period)
```

Do not create separate manually maintained monthly totals.

Reports must be reproducible.

---

# 39. Dashboard

The dashboard should prioritize operational information.

Show:

```text
Today's Expected Collection
Today's Actual Collection
Today's Collection Shortfall
Active Loans
Past Due Loans
Loans Released This Month
Collections This Month
Outstanding Portfolio
```

Quick actions:

```text
New Borrower
New Loan
Record Collection
Today's Collection
Weekly Report
Monthly Progress Report
```

Avoid decorative dashboards.

---

# 40. Main Navigation

Recommended:

```text
Dashboard

Borrowers
    All Borrowers
    New Borrower

Loans
    All Loans
    New Loan
    Active Loans
    Past Due
    Completed

Collections
    Daily Collection
    Payments
    Notes Collection Report

Reports
    Progress Report

Settings
    Loan Products
    Deduction Types
    System Settings

Administration
    Users
    Roles
    Audit Logs
```

---

# 41. Settings

Settings must include:

Ownership:

```text
Loan products, deduction types and system settings are owned by central and
distributed to the nodes. A branch reads them and cannot change them. See
section 69.4.
```

## Loan Products

```text
Name
Code
Term
Payment Frequency
Exclude Sundays
Exclude Saturdays
Interest Method
Interest Rate
Release Date Is Day One
Active
Distributed Version
Distributed At
```

## Deduction Types

```text
Name
Code
Calculation Method
Rate
Fixed Amount
Active
Sort Order
Distributed Version
Distributed At
```

## System Settings

```text
Business Name
Business Address
Contact Information
Currency
Loan Number Format
Borrower Number Format
```

Number format placeholders:

```text
{year}    four digit year
{month}   two digit month
{node}    the node code, e.g. MNL1
{seq}     sequence number
{seq:N}   sequence padded to N digits
```

A number format must contain `{seq}`, and must contain `{node}` for every
business number. A sequence that restarts at 1 on every node is not a unique
identifier.

---

# 42. Database Structure

Initial tables should include:

```text
users

nodes

sequences

borrowers

loan_products

loan_deduction_types

system_settings

loans

loan_deductions

loan_amortizations

payments

payment_allocations

audit_logs

sync_events

sync_batches
```

Rules that apply to every domain table:

```text
id        uuid primary key, never an auto-increment counter. A branch and the
          hub both create records while offline, so the key must be assigned by
          the creating node, never by a shared sequence.
node_id   the node that created the record. This is what makes a record
          attributable after consolidation and what every report groups by.
```

The tables that hold configuration rather than transactions are marked per
section 69.4. `nodes`, `sequences`, `sync_events` and `sync_batches` are defined
in section 69.

Tables that are deliberately not domain tables, with the reason:

```text
users            node local identity, never synchronised, integer key. Two
                 nodes may both have a user 1 and never meet in one database.
                 See section 69.10.
sequences        one counter row per node and entity. It is created by exactly
                 one node and holds no business content, so it carries no
                 uuid. Its node_id is the counter owner, not provenance of a
                 financial record. See section 69.3.
system_settings  a single row per install, so the key is the fixed value 1. See
                 section 41.
loan_products    uuid key and no node_id, because central owns the definition
loan_deduction   and distributes it. A branch applies a version; it never
_types           writes one. See section 69.4.
```

Possible future tables:

```text
holidays
collectors
borrower_merges
loan_adjustments
loan_restructures
penalties
```

`branches` is superseded by `nodes`.

Do not implement future tables until needed.

---

# 43. Suggested Relationships

```text
Borrower
    hasMany Loans

LoanProduct
    hasMany Loans

Loan
    belongsTo Borrower
    belongsTo LoanProduct
    hasMany LoanDeductions
    hasMany LoanAmortizations
    hasMany Payments

LoanDeductionType
    hasMany LoanDeductions

Payment
    hasMany PaymentAllocations

LoanAmortization
    hasMany PaymentAllocations
```

---

# 44. Recommended Domain Services

At minimum:

```text
LoanCalculator
LoanInterestCalculator
LoanDeductionCalculator
LoanReleaseService
AmortizationScheduleGenerator
CollectionCalendar
PaymentAllocator
LoanBalanceCalculator
```

Keep financial rules out of controllers and Svelte components.

---

# 45. Controller Rules

Controllers must remain thin.

Bad:

```text
Controller:
    calculate interest
    calculate deductions
    generate 100 dates
    calculate payments
    calculate balance
    save loan
```

Good:

```text
Controller:
    validate request
    call service
    return response
```

Example:

```text
LoanReleaseService::release($loan)
```

should coordinate release.

---

# 46. Database Transactions

Use database transactions for:

```text
Loan creation
Loan approval
Loan release
Schedule generation
Payment posting
Payment reversal
Loan adjustment
```

A financial operation must either complete fully or roll back.

---

# 47. Audit Trail

Audit:

```text
Loan Created
Loan Updated
Loan Approved
Loan Released
Loan Cancelled
Payment Posted
Payment Reversed
Loan Adjusted
Settings Changed
```

Store:

```text
Node                  the node the entry was written on
User                  the actor label, e.g. "MNL1 - admin@lomas.test"
Action
Record
Old Value
New Value
Reason
Timestamp             the business time the action happened
```

The actor is stored as a label plus the node, never as a bare local user id. Two
nodes both have a user 1, so a local foreign key cannot identify who acted once
the records meet at central. See section 69.10.

---

# 48. Historical Data Protection

After loan release, do not silently change:

```text
Original Loan Amount
Interest Rate
Deduction Rate
Deduction Amount
Amortization Schedule
Release Date
Net Proceeds
```

If correction is necessary, use:

```text
Adjustment
Correction
Restructure
Reversal
```

with an audit trail.

---

# 49. Security

Implement:

```text
Authentication
Authorization
Roles
Permissions
CSRF protection
Server-side validation
Mass assignment protection
Audit logging
Rate limiting where appropriate
```

For multi-branch operation, also implement:

```text
Signed sync bundles    Ed25519 signature per node, verified on import
Bundle integrity       a sha256 of the payload recorded in the manifest
Key custody            each node holds its own private key, never shared
No public sync surface sync moves through files, so no sync endpoint is exposed
Private storage        bundles live outside the web root, never served
```

Initial roles may include:

```text
Administrator
Loan Officer
Collector
Encoder
Viewer
```

---

# 50. Permissions

Suggested permissions:

```text
borrowers.view
borrowers.create
borrowers.update

loans.view
loans.create
loans.update
loans.approve
loans.release
loans.cancel

payments.view
payments.create
payments.reverse

reports.view
reports.export

settings.view
settings.manage

users.manage
roles.manage

audit.view

nodes.view
nodes.manage

sync.export
sync.import
sync.distribute
sync.conflicts.view
```

Use least privilege.

```text
sync.export      create an outbound bundle for central. Held at branches.
sync.import      apply an inbound bundle from a branch. Held at central.
sync.distribute  publish configuration down to the nodes. Central only.
nodes.manage     register a node and manage its keys. Central only.
```

---

# 51. UI Theme

Use a professional financial/admin interface.

Recommended foundation:

```text
Laravel Official Svelte Starter Kit
+
Svelte 5
+
TypeScript
+
Inertia
+
Tailwind CSS
+
shadcn-svelte
+
Lucide
```

Do not use a flashy SaaS/marketing theme.

The interface should prioritize:

```text
Dense information
Clear numbers
Fast encoding
Readable tables
Search
Keyboard navigation
Printability
```

---

# 52. shadcn-svelte

Use shadcn-svelte as the primary component library.

Prefer components such as:

```text
Button
Input
Input Group
Combobox
Command
Select
Dialog
Sheet
Table
Tabs
Badge
Card
Dropdown Menu
Calendar
Date Picker
Toast
Alert
Tooltip
Pagination
Skeleton
```

Do not install multiple UI frameworks.

---

# 53. Icons

Use Lucide icons.

Keep icon usage consistent.

Examples:

```text
Users
Wallet
Banknote
Calendar
Receipt
FileText
Settings
Search
Plus
Printer
Download
```

---

# 54. Tables

Tables are critical to LoMaS.

Support:

```text
Search
Filtering
Sorting
Pagination
Row actions
Column visibility where useful
Responsive behavior
```

Use server-side pagination for large datasets.

Do not load thousands of records into Svelte unnecessarily.

---

# 55. Forms

Forms should be compact and efficient.

Use:

```text
Autocomplete
Combobox
Numeric inputs
Date pickers
Inline calculation
Validation messages
Keyboard navigation
```

Financial actions should have confirmation.

Examples:

```text
Release Loan
Post Payment
Reverse Payment
Cancel Loan
```

---

# 56. Responsive Design

The primary environment is desktop.

However, collection screens should work well on:

```text
Tablet
Mobile
```

Especially:

```text
Daily Collection
Borrower Search
Payment Entry
Loan Lookup
```

Use large enough touch targets for field collection use.

---

# 57. Printing

Provide dedicated print layouts for:

```text
Amortization Schedule
Payment Receipt
Notes Collection Report
Progress Report
Borrower Statement
Loan Statement
```

Use CSS print styles and dedicated report layouts.

Do not rely on browser printing of arbitrary dashboard pages.

---

# 58. Export

Reports should eventually support:

```text
PDF
Excel
CSV
Print
```

Keep exported reports consistent with their on-screen data.

---

# 59. Performance

Optimize for normal small-business hardware and internet connections.

Avoid:

```text
Huge JavaScript bundles
Unnecessary polling
Loading entire datasets
Heavy animations
Unnecessary dependencies
```

Use:

```text
Pagination
Debounced search
Server-side filtering
Database indexes
Lazy loading
Caching where appropriate
```

---

# 60. Database Indexes

Index fields used frequently for:

```text
Borrower Search
Loan Search
Daily Collections
Weekly Reports
Monthly Reports
```

Likely indexes:

```text
borrowers.borrower_number
borrowers.mobile_number
borrowers.last_name
borrowers.node_id

loans.loan_number
loans.borrower_id
loans.status
loans.release_date
loans.node_id

loan_amortizations.loan_id
loan_amortizations.due_date
loan_amortizations.status
loan_amortizations.node_id

payments.loan_id
payments.payment_date
payments.status
payments.node_id

audit_logs.node_id

sync_events.unique(node_id, sequence)
sync_batches.node_id
```

Every report and dashboard metric groups by `node_id`, so it is indexed
throughout. Business numbers are already node qualified, so the unique index on
`borrower_number` and `loan_number` is global and needs no node column.

Only add indexes that match actual query patterns.

---

# 61. Testing

Financial calculations require automated tests.

## Amortization

Test:

```text
100 collection days
Sunday excluded
Saturday included
Saturday excluded
Start on Saturday
Start on Sunday
Month transition
Year transition
Leap year
```

## Deductions

Test:

```text
Interest percentage
Doc Stamp percentage
Fixed Passbook
Multiple deductions
Other deductions
Zero deductions
```

## Payments

Test:

```text
Full payment
Partial payment
Multiple payments
Overpayment
Payment reversal
```

## Rounding

Test:

```text
Even division
Non-even division
Recurring decimal
Very small amounts
Large amounts
```

---

# 62. Example Calculation

Example only:

```text
Loan Amount = ₱10,000

Interest = 10%
Doc Stamp = 1%
Passbook = ₱50
```

Results:

```text
Interest       ₱1,000
Doc Stamp        ₱100
Passbook          ₱50
----------------------
Deductions     ₱1,150
```

For the resolved 100-Day Loan product, see section 63.1.2:

```text
Interest is withheld at release

Total Amount Payable = ₱10,000.00   collected over 100 collection days
Net Proceeds         = ₱8,850.00   cash handed to the borrower
Daily installment    = ₱100.00      principal ÷ collection days
```

These values are examples.

Never hard-code them.

---

# 63. Important Business Rules Requiring Confirmation

Before implementing production financial behavior, explicitly confirm:

```text
1. Does the release date count as Day 1?

2. Is the first collection on the release date or next collection day?

3. Is interest deducted from the released proceeds?

4. Is interest calculated only from the original loan amount?

5. Is Doc Stamp always 1%?

6. Is Passbook always ₱50?

7. Can users add custom deductions to individual loans?

8. Can a deduction be disabled for a particular loan?

9. Are holidays excluded from amortization?

10. How are partial payments allocated?

11. How are overpayments handled?

12. Can payments be backdated?

13. Can posted payments be edited?

14. How are reversed payments reflected in reports?

15. Can released loans be edited?

16. Can amortization schedules be regenerated after release?

17. How are past-due installments handled?

18. Are penalties implemented now or later?
```

If a rule is unknown:

```text
TODO: BUSINESS RULE REQUIRED
```

Do not invent a financial rule.

Status: all 18 questions were answered on 2026-10-03. The binding answers are in
section 63.1. Section 63 is kept for traceability only.

---

# 63.1 Resolved Business Rules

Answers of record, given on 2026-10-03. These decisions override any earlier
assumption. Items marked `TODO: CONFIRM` are still open.

## 63.1.1 Release Date and First Collection (Questions 1 and 2)

```text
Does the release date count as Day 1?
No. By default the first collection is the next day.

Is the first collection on the release date or the next collection day?
The next collection day.

Configurability:
Add an option so that a product can count the release date as Day 1.
```

Implementation intent:

```text
Loan products get a boolean setting, e.g. release_date_is_day_one.
When true, a release date that is itself a valid collection day becomes
installment 1. When false, the first installment is the next valid collection
day.

The option lives on the product only. No snapshot column is needed on the loan
because the generated schedule is frozen at posting and already records the
outcome, including every due date.
```

Resolved: the option lives on the loan product. No system default is needed.

## 63.1.2 Interest (Questions 3 and 4)

Answer of record for the current loan product:

```text
The borrower loans the gross amount.

Net Proceeds = Gross Loan Amount less all deductions.

The amortization collects the Gross Loan Amount, not the Net Proceeds.
```

So for a PHP 10,000 loan:

```text
Gross Loan Amount     10,000.00   the amount borrowed
Deductions            -1,150.00   interest, doc stamp, passbook, custom
Net Proceeds           8,850.00   cash handed to the borrower

Total Amount Payable  10,000.00   collected over 100 collection days
Daily installment        100.00   10,000.00 / 100
```

Implementation intent:

```text
Interest is a deduction. It is computed on the gross loan amount, withheld at
release, and is never collected again.

Every installment of the current product is principal. The schedule amortizes
the Gross Loan Amount over the term, so:

    loans.principal_amount     = Gross Loan Amount
    loans.interest_amount      = the interest deduction, already withheld
    loans.total_amount_payable = Gross Loan Amount
    amortization total due     = Gross Loan Amount / number of collection days

No interest collection mode, no interest component in the schedule, no second
interest charge. Do not add them until a product actually needs them.
```

Interest method and rate stay on the product so another product can calculate
interest differently, but nothing else about the engine changes for now.

Note: this overrides the illustrative formulas in sections 13 and 62, which
assumed interest was repaid through the schedule.

## 63.1.3 Deductions (Questions 5, 6, 7 and 8)

```text
Is Doc Stamp always 1%?
Default 1% and editable.

Is Passbook always PHP 50?
Default PHP 50 and editable.

Can users add custom deductions to individual loans?
Yes.

Can a deduction be disabled for a particular loan?
Yes, dynamically.
```

Implementation intent:

```text
Deduction types stay configurable: Doc Stamp defaults to 1% and Passbook to a
fixed 50, both editable in Settings.

An encoder may add a custom deduction to a single loan, and may switch any
configured deduction off for that loan. Both are expressed by the deduction
snapshot on the loan:

    every configured deduction that applies to the loan gets a snapshot row
    a custom deduction gets a snapshot row with no deduction type
    a deduction switched off for that loan gets no snapshot row
```

Nothing recalculates a released loan. Settings changes only affect loans encoded
afterwards.

## 63.1.4 Collection Days (Question 9)

```text
Are holidays excluded from the amortization?
No. Only Sundays, and Saturdays when the product says so.
```

Implementation intent:

```text
Do not create a holidays table. The collection calendar uses the product rules
only: exclude Sundays always, exclude Saturdays when configured.
```

## 63.1.5 Payment Allocation (Question 10, with a proposed design)

Answer of record:

```text
Record a separate payment, then compute the result in the amortization.
```

Recommended design, proposed by the agent:

```text
Every payment is allocated explicitly. Posting a payment writes one allocation
row per installment it touches, so any balance can be explained from the
transaction history alone.

payments
    payment_number, loan_id, collection_id, payment_date, amount,
    allocated_amount, unapplied_amount, payment_method, reference_number,
    received_by, status, reversal_of_payment_id, reversed_at, reversed_by,
    reversal_reason

payment_allocations
    payment_id, loan_amortization_id, component, amount

component = principal | interest | penalty | other

Default waterfall:
1. Oldest unpaid installment first, ordered by due_date then
   installment_number.
2. Every installment of the current product is principal, so a payment settles
   principal only.
3. Any amount beyond the total amount payable stays unapplied on the payment.
   It is never silently pushed into a future installment.

Why not "compute it in the amortization":
- The schedule stays frozen at posting and stores scheduled amounts only.
- Paid and remaining amounts are sums of allocations, so every peso is
  traceable to a payment.
- A reversal only touches one payment and its allocations.
- Reports reconcile payments to allocations exactly, per section 38.
```

`TODO: CONFIRM` the waterfall. Oldest installment first is proposed and not yet
confirmed. It is the only rule the current product needs, because every
installment is principal.

## 63.1.6 Overpayments (Question 11)

```text
How are overpayments handled?
Keep the excess in the payments table.
```

Implementation intent:

```text
An overpayment is stored on the payment, never spread silently:

    payments.amount            the money actually received
    payments.allocated_amount  the part that settled installments
    payments.unapplied_amount  the excess

The excess stays visible as an unapplied credit on the loan and is reported
separately. Applying it to a future installment, refunding it or holding it as
borrower credit requires a separate, explicit, audited action.
```

## 63.1.7 Daily Collection Posting (Question 12)

Answer of record:

```text
Posting of payments is daily. Preparing a collection can happen on the day
after or on later days.
```

Recommended design, proposed by the agent:

```text
A collection day is posted once, as a unit.

collections (posting)
    business_date      the collection day being posted
    status             open | posted
    prepared_by, prepared_at
    posted_by, posted_at
    payment_count, total_amount

payments
    collection_id      the posting the payment belongs to
    payment_date       the collection day the payment settles
```

Rules:

```text
An encoder may prepare and add payments to an open collection day, including a
day that has already passed.

Once a collection day is posted it is closed. No payment is added, edited or
deleted afterwards. Corrections are made by reversing a payment and posting a
replacement, always audited.

payment_date must equal the collection day of its posting, so a payment can
never silently move to another day.
```

## 63.1.8 Editing and Reversing Payments (Questions 13 and 14)

Answer of record:

```text
Can posted payments be edited?
Yes, by an administrator only, and only with an audit trail.

How are reversed payments reflected in reports?
They do not need a separate treatment in reports. The audit trail handles it.
```

Implementation intent:

```text
Editing a posted payment is restricted to users with payments.reverse, or a
dedicated edit permission, and always writes an audit entry with the old value,
the new value and the reason.

A reversed payment keeps its row with status reversed. Its allocations stop
counting, and collection totals, loan balances and reports exclude it. That is
required for section 38 reconciliation.

Reports show no extra reversal column and no reversal subtotal. The audit trail
explains the change.
```

## 63.1.9 Loan Posting, Unposting and Restructuring (Questions 15 and 16)

Answer of record:

```text
Can released loans be edited?
No. A released loan must be unposted. Only a loan without payments may be
unposted. A restructure of the loan is allowed.

Can amortization schedules be regenerated after release?
No. Schedules are generated upon posting.
```

Implementation intent:

```text
Release, or posting, is the only operation that generates the amortization
schedule. The schedule is never regenerated.

Unposting is allowed only when the loan has no payments and no allocations. It
reverses the release, voids the schedule, stores the net proceeds reversal and
returns the loan to the previous status. Every step is audited.

`TODO: CONFIRM` whether a released loan number is ever reused after unposting.
The proposal is that it is never reused, so the audit trail always resolves a
number to exactly one loan.
```

## 63.1.10 Past Due (Question 17)

Answer of record:

```text
How are past-due installments handled?
In payments.
```

Implementation intent:

```text
Past due is derived from the frozen schedule and the payment allocations. No
separate past due ledger is maintained and no penalty is charged yet.
```

## 63.1.11 Penalties (Question 18)

Answer of record:

```text
Are penalties implemented now or later?
Later.
```

Implementation intent:

```text
No penalties table, no penalty rules. The allocation component `penalty`
already exists so that a later phase does not have to change payment history.
```

---

## 63.1.12 Seeded Loan Product and Deduction Defaults

Answer of record, given on 2026-10-03 with the Phase 3 implementation.

```text
How many collection days?
100, counted as valid collection days.

Which days are collected?
Monday to Saturday. Sunday is excluded, Saturday is collected.

Is the release date counted as the first collection day?
No. The first collection is the next collection day.

What is the interest rate?
10% of the original loan amount, withheld at release.

Are Sundays and Saturdays excluded?
Sunday always. Saturday is a product setting, so Monday to Saturday is the
default configuration rather than a system wide constant.

How is interest configured?
On the loan product, through interest_method and interest_rate. There is no
interest deduction type.

Which deduction types are configured by default?
Documentary Stamp at 1% of the loan amount and Passbook at a fixed ₱50. Both are
editable in Settings. No other type is created.
```

Implementation intent:

```text
The seeded product is:

    LOAN-100D, 100 collection days, daily, exclude_sundays = true,
    exclude_saturdays = false, release_date_is_day_one = false,
    interest_method = percentage_of_loan_amount, interest_rate = 10.00

The seeded deduction types are:

    DOC_STAMP  percentage_of_loan_amount  rate = 1.00      sort_order = 1
    PASSBOOK   fixed_amount                fixed_amount = 50.00  sort_order = 2

The seeder is idempotent on the code column, so it never duplicates or
overwrites configuration a staff member has already changed.

Only term_unit = collection_days and payment_frequency = daily are accepted,
because those are the only cases the calendar and generator implement.

The weekend rules are read through CollectionCalendar, which is the only place
that decides whether a date is a collection day.
```

---

## 63.1.13 Multi-Branch and Offline Operation

Answers of record, given on 2026-10-03 with the offline design. The rules are in
section 69. Items marked `TODO: CONFIRM` are still open.

```text
What is a node physically?
A complete Laravel install with its own database and web UI. Central is a node
that also consolidates. Every node works with zero connectivity.

How do records move between nodes?
Signed file bundles, carried by hand. No sync endpoint is exposed.

Who owns configuration?
Central owns loan products, deduction types and system settings, and
distributes them. Branches read them and cannot change them. Staff accounts,
roles and permissions are node local and are never synchronised, so a branch
never waits on central to create an account. See section 69.4.

How are numbers unique when two offline nodes both issue BR-000001?
Every business number is node qualified, e.g. BR-MNL1-000001, and every domain
key is a uuid generated by the creating node. No central allocator exists.

Can a branch collect against a loan encoded at another branch?
No. A loan belongs to the branch that encoded it, and that branch collects it.

When does this land?
Node identity and the sync tables land before loans exist, as Phase 3.5. The
sync engine and the consolidation console land as Phase 10.
```

`TODO: CONFIRM` the following. These are business decisions and must not be
invented:

```text
1.  Must a branch hold the current configuration before it may release a loan,
    or may it release against the version it already has?
2.  May central correct or reverse a transaction posted by a branch, and how is
    that correction shown in reports?
3.  How long are bundles and rejected sync events retained?
4.  How are signing keys rotated, and who holds them at a branch?
5.  May a duplicate borrower merge ever rewrite a released loan? Assumption: no,
    section 48 still applies.
6.  Whose clock is authoritative for a business date that is entered days before
    it syncs? Assumption for now: the local business date is preserved and the
    hub receipt time is recorded separately.
```

---

# 64. Development Order

Implement in this order.

## Phase 1 — Foundation

```text
Authentication
Users
Roles
Permissions
System Settings
Application Layout
```

## Phase 2 — Borrowers

```text
Borrower CRUD
Borrower Search
Borrower Profile
Borrower Loan History
```

## Phase 3 — Loan Configuration

```text
Loan Products
Deduction Types
Loan Settings
```

## Phase 3.5 — Node Identity and Offline Foundation

```text
Nodes and Node Codes
UUIDv7 Keys
node_id on Every Domain Table
Node Qualified Number Formats
Per Node Sequence Counters
Node Identity in the Audit Trail
Sync Event and Sync Batch Tables
```

This phase lands before loans exist on purpose. Retrofitting identity onto
populated tables afterwards is far more expensive. See sections 69.2, 69.3,
69.5 and 69.10.

## Phase 4 — Loan Encoding

```text
New Loan
Loan Calculator
Dynamic Deductions
Loan Review
```

## Phase 5 — Loan Processing

```text
Approval
Release
Loan Number
Net Proceeds
```

## Phase 6 — Amortization

```text
Collection Calendar
100-Day Generator
Sunday Exclusion
Saturday Setting
Amortization Display
```

## Phase 7 — Collections

```text
Daily Collection
Payment Posting
Partial Payments
Payment Allocation
Payment Reversal
```

## Phase 8 — Reporting

```text
Notes Collection Report
Weekly Summary
Progress Report
Monthly Summary
```

## Phase 9 — Production Hardening

```text
Audit
Reconciliation
Security Review
Performance Review
Backup Strategy
Error Handling
Financial Integrity Testing
```

## Phase 10 — Branch Sync and Central Consolidation

```text
Outbound Bundle Export
Signed Bundle Verification
Inbound Import and Idempotency
Sequence Continuity Checks
Configuration Distribution
Stale Configuration Warning
Sync Console and Batch History
Consolidated Reports Grouped by Node
Duplicate Borrower Review
```

---

# 65. Git / Code Quality

Keep commits focused.

Prefer:

```text
feat: add loan deduction settings
feat: add 100-day amortization generator
feat: add daily collection encoding
fix: correct amortization rounding
fix: prevent duplicate payment posting
```

Avoid huge unrelated commits.

Run tests before committing.

---

# 66. Agent Behavior

The coding agent must:

1. Inspect the existing project before making changes.
2. Understand existing conventions.
3. Reuse existing components.
4. Avoid unnecessary dependencies.
5. Keep controllers thin.
6. Keep business rules in services/domain classes.
7. Add automated tests for financial logic.
8. Use database transactions for financial operations.
9. Protect historical financial records.
10. Never delete posted financial transactions.
11. Never silently invent financial rules.
12. Never use floating-point calculations for money.
13. Prefer simple maintainable solutions.
14. Explain destructive migrations before executing them.
15. Verify calculations with concrete examples.
16. Give every new domain table a uuid key and a node_id, from the first
    migration.
17. Never invent a conflict resolution rule. If two nodes can disagree, ask.
18. Never let one node depend on another at request time, and never assume a
    connection exists.

---

# 67. Definition of Done

A feature is not complete simply because the UI works.

A production-ready financial feature requires:

```text
Database
Backend Logic
Validation
Authorization
Financial Calculations
Transactions
Automated Tests
Audit Trail where required
Error Handling
Edge Case Handling
Responsive UI
Print/Export where applicable
```

---

# 68. Final Principle

LoMaS must always be able to explain:

```text
Who is the borrower?

What loan did they receive?

When was it released?

How much was the original loan?

What interest was charged?

What deductions were applied?

How much was actually released?

What was the original amortization schedule?

How much was paid?

When was each payment made?

How much remains?

What is past due?

Who recorded the transaction?

What was changed?

Who changed it?

When was it changed?

Why was it changed?
```

After consolidation, LoMaS must also be able to answer:

```text
Which node recorded it?

Which operator, on which node?

Which sync batch carried it?

Has every node's data actually arrived, or is any node behind?

Was anything rejected on import, and why?
```

If the system cannot answer these questions from its data and transaction history, the implementation is incomplete.

---

# 69. Multi-Branch and Offline Operation

LoMaS runs as several independent installations. A branch is not a thin client of
a central server: it is a complete installation that keeps working when nothing
is reachable. Central is the same thing plus one responsibility, consolidating
what the branches produced.

This section is the rule set. The answers of record behind it are in section
63.1.13. Where an open question has no answer, it is marked
`TODO: CONFIRM` and must be asked, not guessed.

## 69.1 Topology

A node is a complete Laravel installation:

```text
its own database
its own web UI
its own users
no dependency on any other node at request time
```

Central is a node with `role = central`. It owns configuration and it applies
inbound bundles. It does not re-post, re-sequence or re-value a branch
transaction.

Rules:

```text
Day to day work must never depend on connectivity. No screen may wait for
another node. Sync is a background, retryable activity, not a precondition for
work. A node that has never synced must still be able to encode a borrower,
release a loan and post a collection.
```

## 69.2 Node identity

```text
nodes
    id            uuid
    code          short uppercase code, unique, e.g. MNL1
    name
    role          central | branch
    public_key    Ed25519 public key used to verify its outbound bundles
    timezone
    is_active
    last_seen_at
    last_applied_serial  the highest sync_events.sequence this node has applied
    created_at
    updated_at
```

```text
The code is permanent. It is written into every business number and every audit
entry, so changing it would orphan records already in other nodes. It is set
once at installation and never edited. The local code is configurable in
config/lomas.php, not in the database, so a fresh install cannot come up with
the wrong identity. TODO: CONFIRM whether a code may ever be reissued after a
node is retired.
```

## 69.3 Keys and numbering

Two offline nodes cannot share a counter, so no domain table uses an
auto-increment key.

```text
Primary key   uuid v7 on every domain table. Assigned by the creating node.
              Time ordered, so index locality and recent-first queries survive.
Business key  node qualified, so it is globally unique without a coordinator:
                  BR-MNL1-000001      borrower
                  LN-MNL1-2026-000001 loan
                  PY-MNL1-2026-000001 payment
Number format must contain {node} and {seq}. See section 41.
```

```text
sequences
    id               auto-increment. It is not a domain table and carries no
                     business content, so it is deliberately the one operational
                     table without a uuid key. See section 42.
    node_id          the node that owns this counter, not provenance of a
                     financial record
    entity           borrower | loan | payment
    next_value
    updated_at
```

A node draws its next sequence from its own row while offline. Central never
allocates on a branch's behalf, and a branch never asks for a range. The unique
index on the business number remains the final guard.

Local uniqueness is not global uniqueness, and the system must never assume
otherwise. `BR-MNL1-000001` and `BR-CAV1-000001` are two different borrowers,
which is correct.

## 69.4 Who writes what

One record, one writer. This is the primary conflict avoidance rule, and it is
why the design needs almost no merge logic.

```text
Central owns and distributes, branches read only:
    loan_products
    loan_deduction_types
    system_settings

The owning branch is the only writer of its own records:
    borrowers
    loans
    loan_deductions
    loan_amortizations
    payments
    payment_allocations
    audit_logs

Node local, never synchronised, each node manages its own staff:
    users
    roles
    permissions

Central never edits a branch record. If central data is wrong, the owning
branch corrects it and the correction syncs back.
```

Staff accounts, roles and permissions are node local, not distributed
configuration. Each node manages its own staff, so a branch never waits for
central to create an account. See section 69.10.

A loan belongs to the branch that encoded it, and only that branch collects it,
approves it and cancels it. See section 63.1.13.

Distribution, not synchronisation, is the mechanism for configuration: central
publishes a version, branches apply it, and a branch that has not applied the
latest version says so in the UI rather than silently working with a stale
product.

## 69.5 Change log

Every business write also appends one immutable event, in the same database
transaction as the record itself.

```text
sync_events
    id           uuid
    node_id      the node that wrote it
    sequence     monotonic per node, starting at 1, never reused
    entity_type
    entity_id    the uuid of the affected record
    operation    created | updated | reversed
    payload      the row as written, money as exact decimal strings
    occurred_at  business time on the writing node
    recorded_at  when this node received it, for inbound events
    payload_hash sha256 of the canonical payload
    batch_id     the outbound batch that carried it, nullable while pending
    created_at
```

Rules:

```text
Append only. Never updated, never deleted, never reordered. A correction is a
new event, so the history of what central received is reconstructable. Sequence
gaps are impossible because the sequence is drawn inside the same transaction as
the write.
```

## 69.6 Bundles

```text
A bundle is the only thing that crosses a node boundary.

Manifest  node code, node id, batch uuid, first sequence, last sequence, event
          count, payload sha256, signature, created_at
Payload   gzipped events, ordered by sequence
File      lomas-sync-{node_code}-{serial}-{created_at}.json.gz
Signature Ed25519 over the manifest with the sending node's private key
```

```text
sync_batches
    id           uuid
    direction    inbound | outbound
    peer_node_id the node the data came from or went to
    manifest_hash
    signature_valid
    event_count
    first_sequence
    last_sequence
    status       verified | rejected | partial
    reject_reason
    received_at
    applied_at
    applied_by
```

Rules:

```text
Transfer is a file. A memory card, a shared folder, an offline laptop, a
printed manifest. No endpoint, no webhook, no polling. Bundles live outside the
web root and are never served over HTTP.

An applied batch is retained, not deleted. It is the evidence of what central
received and when.
```

## 69.7 Import rules

```text
1. Verify the signature against the sending node's registered public key.
2. Verify the payload sha256 against the manifest.
3. Verify sequence continuity: the bundle must continue from the highest
   sequence this node has already applied. A gap rejects the whole bundle.
4. Verify the peer is registered and not retired.
5. Apply events in sequence order, each in its own transaction, recording the
   batch.
6. Never apply a partially valid bundle.
```

```text
Idempotency: sync_events is unique on (node_id, sequence). Re-importing the same
bundle, or importing it twice through different routes, changes nothing. There is
no merge rule to get wrong, because an event that already exists is a no-op.
```

```text
A rejected bundle is a visible, retryable state with a reason. It must never be
silently dropped, and it must never block the node from working.
```

## 69.8 Configuration distribution

```text
loan_products and loan_deduction_types carry
    version           increments on every central change
    distributed_at    when central last published it

system_settings carries
    version
    distributed_at

A branch shows the version it holds and how far behind it is.
```

```text
A loan records the product version it was encoded under, and the deduction
snapshot records the deduction versions used. Consolidated reporting therefore
never has to guess which configuration applied to which loan. TODO: CONFIRM
question 1 of section 63.1.13: whether a branch must be current before it may
release a loan, or may release against the version it holds.
```

## 69.9 Duplicate borrowers

```text
Two nodes may register the same person on the same day, offline. That is
expected, not an error. Central does not merge automatically.

Central flags candidates for review: same mobile number, or same name plus
birth date. A merge is a deliberate, audited action that keeps both original
records and links them, so the history of each loan stays intact. A merge never
rewrites a released loan. See section 48.
```

## 69.10 Users and audit

```text
Users, roles and permissions are node local and are never synchronised. Each
node manages its own staff, so a branch never waits for central to create an
account, and two nodes may both have an "admin@lomas.test" without colliding.
```

Therefore audit entries never depend on a local user id:

```text
node_id       the node the entry was written on
user_label    "MNL1 - admin@lomas.test", resolved at write time
user_id       nullable, for convenience only, never the identity
occurred_at   business time on the writing node
```

Every audit entry also produces a `sync_events` event, so consolidation can
answer who did what, where, and in which batch.

## 69.11 Consolidated reporting

Every metric is computable two ways, and the two must agree:

```text
Per node     grouped by node_id
Hub wide     the sum of the node figures, over accepted batches only
```

```text
Rule: a hub-wide figure is never stored. It is always recalculated from the
transactions, so it stays reconcilable under section 38. Reports that span
nodes must show the node column. Rejected or pending events are excluded from
totals and shown separately, never folded in silently.
```

## 69.12 Rules of engagement

```text
Never invent a conflict rule. If two nodes can disagree about a record, ask
first. The design keeps that set small by giving every record exactly one
writer; anything else is a question, not an implementation detail.

Never silently renumber a record at consolidation. If a number is wrong, the
owning branch issues the correction, and both events are visible.

Never block a node on central. A node that has been offline for a month must
behave exactly like a node that has been online for a month.

Never delete a bundle, an event, or a transaction to resolve a merge problem.
```
