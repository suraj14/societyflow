# Testing SocietyFlow

## Running the suite

```bash
./vendor/bin/phpunit                      # everything
./vendor/bin/phpunit --testsuite Feature  # feature tests only
./vendor/bin/phpunit --filter WebSmokeTest --testdox
```

No setup is required. `phpunit.xml` runs against an in-memory SQLite
database, so the suite is self-contained.

It previously pointed at a MySQL database (`societyflow_test`) with
hardcoded credentials that had to exist before a single test could run.
Nothing in the repository created it, so the suite errored at
`RefreshDatabase` on any machine that had not set it up by hand.

## Also run it against MySQL

Production targets MySQL and the two engines disagree in ways that hide
real bugs:

- SQLite treats an unresolvable double-quoted identifier as a string
  literal. A query against a column that does not exist silently returns
  nothing instead of erroring. Several dashboard statistics read
  `visitors.visit_date` and `payments.paid_at` — neither column exists —
  and quietly reported zero for months. On MySQL the same queries are a
  hard failure.
- `YEAR()`, `MONTH()` and similar are MySQL-only.
- Double quotes delimit identifiers in MySQL but can fall back to string
  literals in SQLite, so `status = "sent"` behaves differently on each.

Run the suite both ways before a release:

```bash
DB_CONNECTION=mysql DB_DATABASE=societyflow_test \
DB_USERNAME=... DB_PASSWORD=... ./vendor/bin/phpunit
```

## What the suites cover

| File | Guards against |
|---|---|
| `AuthSecurityTest` | Account takeover via register, OTP flows, enumeration, token revocation on reset |
| `AuthorizationIsolationTest` | Cross-society access through the mobile API |
| `WebTenantIsolationTest` | IDOR on the Blade side — payments, flats, owners, facilities, complaints |
| `AdminCreatedCredentialsTest` | Admin-created accounts never get a well-known password; owner creation is transactional |
| `SchemaContractTest` | What controllers accept is what the database can store |
| `SearchScopeLeakTest` | Search filters cannot widen what a user can see |
| `UtilityBillAccessTest` | Utility bill policy, and `UserFlatResolver`'s three attachment paths |
| `VillaOwnerVisitorTest` | The villa-owner visitor form persists correctly |
| `DashboardStatsTest` | Dashboard statistics query real columns |
| `ApiContentVisibilityTest` | Event/facility payloads are populated; role-restricted events stay hidden |
| `DocumentNumberTest` | Document numbers survive deletions |
| `WebSmokeTest` | 40 screens render across three roles, with data present |
| `QueryPerformanceTest` | List endpoints do not N+1 |
| `HealthEndpointTest` | The server-identity contract the mobile app depends on |

### Two habits worth keeping

**Seed data before asserting a page renders.** The utility-bills listing
called `simplePaginate()` on an already-paginated result and fataled — but
only once a single bill existed. An empty-database check passed happily.

**Assert query counts, not just correctness.** `QueryPerformanceTest`
compares a small batch against a larger one. That is how the
`MaintenanceBill::getFillable()` introspection was found: 94 queries for a
12-row response, growing linearly.

## Flutter

```bash
cd societyflowmob
flutter test
flutter analyze
```

`ServerDiscovery` performs real socket and HTTP I/O against the local
subnet, which cannot run under `flutter test`. Set
`ServerDiscovery.overrideBaseUrl` in `setUp` to skip the scan — see
`test/widget_test.dart`.

`test/models_test.dart` covers JSON parsing against the shapes the API
actually returns. This is the layer where the backend/mobile contract
broke: the events and facilities endpoints serialised nulls for title,
date, venue and charges, and the app rendered empty rows without
complaining, so nothing surfaced the problem on either side. If a payload
key changes on the server, these tests fail instead.
