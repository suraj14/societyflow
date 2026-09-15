# SQLite vs MariaDB — schema parity audit

**Date:** 2026-09-15 · **Engines:** SQLite 3 (development) vs **MariaDB 10.4.27** (production-like)
**Method:** full programmatic dump of both schemas (`PRAGMA table_info` / `foreign_key_list` /
`index_list` against `information_schema.COLUMNS` / `KEY_COLUMN_USAGE` /
`REFERENTIAL_CONSTRAINTS` / `STATISTICS`), then diffed field by field.
64 tables on SQLite, 63 on MariaDB.

---

## Why the two engines drifted at all

Laravel 10 compiles `->foreign()` on an **existing** table to nothing on SQLite —
`SQLiteGrammar::compileForeign()` is an empty method — because SQLite has no
`ALTER TABLE ADD CONSTRAINT`. Migrations therefore express constraints in one of
two ways:

- inside `if (DB::getDriverName() === 'sqlite')` **table rebuilds**, which MariaDB never runs;
- as plain `ALTER`s, which SQLite silently ignores.

Either way one engine gets the constraint and the other does not, with nothing
logged. Every finding below traces to that single cause.

---

## 1. Confirmed production-risk findings

### ⚠️ P0 — deleting a user destroys gate and residency history on MariaDB

| | SQLite | MariaDB |
|---|---|---|
| `residents.user_id` ON DELETE | `SET NULL` | **`CASCADE`** |
| `visitors.host_user_id` ON DELETE | `SET NULL` | **`CASCADE`** |

**Demonstrated, not theorised.** On MariaDB, deleting one user removed both its
visitor record and its resident record:

```
BEFORE             visitors=1  residents=1
AFTER_USER_DELETE  visitors=0  residents=0
```

`OwnerController::destroy` deletes user accounts in normal operation, so this is
reachable. A visitor log is a security record; losing it because a resident's
account was removed is silent, irreversible data loss that development can never
reveal, since SQLite merely nulls the column.

**Intent is documented in the migrations**: both columns were declared `CASCADE`
in 2024 and deliberately changed to `SET NULL` afterwards — `residents.user_id`
in `2026_01_16`, `visitors.host_user_id` in `2026_01_10` (twice) and again in
`2026_09_07`. MariaDB never received any of those changes.

**Recommended canonical behaviour:** `SET NULL` — preserve the record, drop the link.
**Migration required:** yes, and it is blocked by the next finding.

### ⚠️ P1-15 — `visitors.host_user_id` nullability (the blocker above)

| | SQLite | MariaDB |
|---|---|---|
| Nullable | `YES` | **`NO`** |

These two findings are one problem: **MySQL cannot apply `ON DELETE SET NULL` to a
`NOT NULL` column**, which is precisely why MariaDB is still on `CASCADE`. Fixing
the cascade requires making the column nullable there first.

**Business rule, established from the code rather than assumed.** All four creation
paths set a host:

| Flow | Source of `host_user_id` |
|---|---|
| `Api/VisitorApiController` (resident, and guard-registered) | `$user->id`, falling back to the flat's active resident |
| `GuardController` (walk-in, delivery, service, emergency) | `auth()->id()` |
| `VillaOwner/VisitorController` | `$user->id` |
| `VisitorController` (web) | `auth()->id()` |

So **every visitor has a host at creation** — including guard-created walk-ins,
where the guard is the host. Development data agrees: 18 visitors, **0 null**.

That makes `NOT NULL` correct *at insert* but wrong *at delete*: the column must
be able to become null later, when the host's account is removed, or the visitor
record dies with it. The two requirements are compatible — application-enforced at
creation, nullable at rest.

**Recommended canonical behaviour:** nullable column + `SET NULL`, matching SQLite.
**Migration required:** yes — make MariaDB nullable, then re-point the constraint.
**Decision needed from you:** confirm that visitor history should outlive the host's
account. I have not changed it; the brief said to prove the rule first.

### ⚠️ P1 — `tenants` cascade rules disagree, in both directions

| | SQLite | MariaDB |
|---|---|---|
| `tenants.user_id` ON DELETE | **`CASCADE`** | `SET NULL` |
| `tenants.flat_id` ON DELETE | `SET NULL` | **`CASCADE`** |

Each engine is wrong about a different column. Deleting a **user** destroys the
tenancy on SQLite; deleting a **flat** destroys it on MariaDB. Latest declared
intent is `SET NULL` for both (`2026_01_22` for `user_id`, `2026_01_28` line 59
for `flat_id`).

**Recommended:** `SET NULL` on both. **Migration required:** yes, both engines.

### P1 — `flats(building_id, flat_number)` UNIQUE exists only on SQLite

Duplicate flat numbers within one building are **possible on production and
impossible in development**. Development data has 0 duplicates, so the constraint
can still be added.

**Recommended:** add the unique index on MariaDB. **Migration required:** yes.

### P1 — two columns are `NOT NULL` without a default on MariaDB only

| Column | SQLite | MariaDB | Effect |
|---|---|---|---|
| `notices.publish_date` | nullable | `NOT NULL`, no default | insert fails on production if omitted |
| `tenants.move_in_date` | nullable | `NOT NULL`, no default | same |

Development data supplies both in every row (15 notices, 6 tenants, 0 nulls), so
no live path is currently broken — but any code path that omits them works in
development and throws in production.

**Recommended:** decide per column, then align. **Migration required:** yes, once decided.

---

## 2. Fixed in this pass

| Finding | Fix |
|---|---|
| `flats.villa_area_id` FK missing on MariaDB | `2026_09_15_000004_add_villa_area_foreign_key_to_flats` |

`ON DELETE CASCADE` was chosen on evidence: the live SQLite schema uses it, the
three most recent declarations all say `CASCADE` (the 2024 original said
`SET NULL` but was superseded), and `flats.building_id → buildings` is already
`CASCADE` on **both** engines — a villa area is to villas what a building is to
apartments. `VillaAreaController::destroy` refuses to delete an area that still
has villas, so the cascade is a backstop against direct database deletion rather
than a routine path.

Data checked first: 17 flats carry a villa area, **0 dangling**. Nothing was
repaired or inferred.

Previously fixed in this series: `users.owner_id` (`2026_09_15_000001`),
`flats.owner_id` (`2026_09_15_000003`), `societies.public_id` (`2026_09_15_000002`).

---

## 3. Differences that are NOT problems

Recorded so nobody re-investigates them.

| Difference | Count | Why it is fine |
|---|---|---|
| `INDEX(id) ... only MARIADB UNIQUE` | ~60 | The primary key. SQLite reports it differently. |
| Single-column MariaDB-only indexes | 37 | MySQL creates an index for every foreign key automatically; SQLite does not. Not a real difference. |
| `varchar` vs `enum` on 6 status columns | 6 | SQLite lost the `CHECK` during table rebuilds. **Testing** risk only — SQLite accepts values MariaDB rejects, so a bad value passes tests and fails in production. `SchemaContractTest` already guards the important ones. P2. |
| 11 nullability differences **with defaults** | 11 | e.g. `flats.status` is `NOT NULL DEFAULT 'vacant'` on SQLite and nullable with the same default on MariaDB. Inserts always supply or default. Harmless. |
| FKs present on MariaDB, absent on SQLite | 4 | `payments.flat_id`, `societies.admin_id`, `societies.subscription_plan_id`, `system_settings.society_id`. MariaDB is **stricter** here, so production is the safe side. Worth aligning eventually (P2) — adding them to SQLite needs a table rebuild. |
| `users.tenant_id` FK missing on SQLite (P2-12) | 1 | MariaDB has it, `SET NULL`, referencing `tenants.id`; 4 users linked, **0 dangling**. Production is already correct. SQLite-only gap → **P2**, and fixing it means rebuilding `users` again. |

### Stale table

`flats_old` exists in the development SQLite database only — leftover staging
from the `2026_01_23_120000` rebuild, which creates `flats_old` and never drops
it. Harmless but confusing; it is not created by a fresh install of either engine.
**P2, no migration required** — a one-line cleanup when convenient.

---

## 4. Tests

`tests/Feature/SchemaParityTest.php` — 17 tests, written to run on **either**
engine so the same file proves parity when executed twice. It asserts eight
required foreign keys with their exact `ON DELETE` rules, six columns that must
stay nullable because the empty state is legitimate, that an invalid villa area
is rejected, that a flat with no villa area is accepted, and that deleting a
villa area cascades to its villas but not to unrelated flats.

```
./vendor/bin/phpunit --filter SchemaParityTest                       → 17 passed
DB_CONNECTION=mysql ./vendor/bin/phpunit --filter "SchemaParity|FlatOwner|OwnerId|UserOwner"  → 59 passed
DB_CONNECTION=mysql ./vendor/bin/phpunit                             → 347 passed / 927
./vendor/bin/phpunit                                                 → 347 passed / 931
```

## 5. Flutter / API

**No changes required and none made.** None of the columns in this audit appears
in an API payload, and none is referenced anywhere in `societyflowmob/lib`.
