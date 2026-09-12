# SocietyFlow — final system status

**Date:** 2026-09-12 · **Branch:** `production-readiness` · **Head:** `6aa8512`
`main` is untouched at `2f65516`.

Companion documents:
[PROPERTY_TYPE_ARCHITECTURE.md](PROPERTY_TYPE_ARCHITECTURE.md) (design),
[PRODUCTION_QA_REPORT.md](PRODUCTION_QA_REPORT.md) (evidence),
[CURRENT_STATUS.md](CURRENT_STATUS.md) (living state),
[TODO.md](TODO.md) (backlog).

---

## The shape of the system now

```
SOCIETY  ── property_type: villa | apartment | both   ← single source of truth
   │
   ├── decides which property types may be created      (validation)
   ├── decides which menus exist                        (navigation)
   ├── decides which dashboard figures mean anything    (reporting)
   ├── decides the wording residents see                (web + mobile)
   └── is published to the mobile app                   (API contract)
         │
      PROPERTY  ── flats, discriminated by property_type
         │          building_id for apartments · villa_area_id for villas
         │
      OWNER / RESIDENT / TENANT
         │
      VISITORS · GUARDS · BILLS · TICKETS · FACILITIES · EVENTS
```

The central decision: **`flats` was already the unified Property table** the
brief asked for. It holds both kinds, discriminated by `property_type`, with
`building_id` for apartments and `villa_area_id` for villas. No new table, no
polymorphic rewrite, no data migration — the missing piece was the
society-level setting governing it, not a new schema.

## Layer status

| Layer | State | Evidence |
|---|---|---|
| Database | 70 migrations applied, 0 pending | `migrate:status` |
| Backend | 260 tests / 674 assertions pass | `phpunit` |
| Web | 6 roles browser-verified, console clean | [QA §3](PRODUCTION_QA_REPORT.md) |
| Mobile API | society config on login / me / profile | `ApiSocietyConfigTest` |
| Flutter source | 21 tests pass, analyze at baseline | `flutter test`, `flutter analyze` |
| Flutter release build | **NOT VERIFIED** — JDK broken (P0-9) | — |
| MySQL | **NOT VERIFIED** — no server | — |

## What the property type controls

| Surface | Behaviour |
|---|---|
| Society create / edit | 3-way choice with a live capability preview |
| Owner, tenant, rent, resident, visitor, payment, facility, utility forms | Offer only supported types |
| Owner / tenant / rent creation endpoints | **Reject** unsupported types server-side |
| Sidebar and mobile menu | Apartment and villa groups appear only when real |
| Admin dashboard | Mixed splits the counts; single-type gets one labelled card and no Buildings card for villas |
| Owner dashboard | "My Villas" / "My Apartments" / split totals |
| Guard gate | One search covers both kinds; labels follow the record |
| Mobile API | `society.property_type`, `supports_apartments`, `supports_villas`, `is_mixed` |
| Flutter | `SocietyConfig.propertyNoun` drives labels — "Villa" / "Apartment" / "Property" |

Enforcement is layered: the form filters, the validator rebuilds the rule
from `$society->allowedPropertyTypeValues()`, and `society_id` is resolved
from the authenticated user rather than the request. A client that skips the
form is rejected identically — there are tests for exactly that.

## Guard experience

`Guard` was a role with nothing behind it: no controller, no route, no view.
It matched no branch in `DashboardController` and fell through to
`"Default: Admin dashboard"`, so gate staff saw monthly revenue, pending
dues, occupancy and resident counts.

Guards now land on a mobile-first gate with its own minimal layout — no
sidebar, no administration:

- four counters (waiting / expected / inside / departed) from one grouped query
- three queues: awaiting approval, expected today, currently inside
- one-tap check-in and check-out
- one search box that finds a flat, a villa, a block or a resident
- thumb-reachable bottom navigation, 48px minimum tap targets

`GuardController` owns no new visitor semantics — approval, entry and exit
stay the visitor module's. Every query is pinned to the guard's own society
with no Super Admin widening.

**Delivered:** brief §8 capabilities 1, 2, 3, 7, 8, 9, 11, 12, 13, 14, 15.
**Not delivered:** 4, 5, 6 (notify / request approval / decide at the gate),
10 (photo capture — needs a device camera), 16 (blacklist — needs a table).

## Known limitations

Pre-existing, documented in [CLAUDE.md](../CLAUDE.md) §11, unchanged:

- No payment gateway; no subscription enforcement
- No mobile push client (`firebase_messaging` absent)
- `flats.owner_id` unset for most rows — ownership falls back to address matching
- Document numbers can be reissued after deleting the newest record
- 31 dev accounts still use `password` / `password123`
- 4 intentional Flutter `DropdownButtonFormField value:` deprecations
- 106 `console.log` calls across views

Added by this work: none.

## Open items

| Priority | Item |
|---|---|
| P0-3 | Run the suite against MySQL |
| P0-7 | Real `applicationId` (currently `com.example.*`) |
| P0-8 | Release keystore — builds are debug-signed |
| P0-9 | Repair the Android JDK — blocks all release builds |
| P1-12 | Remaining brief sections — §8 capabilities 4–6/10/16, §12 Flutter guard screens |
| — | Review the two ambiguous society backfills (below) |

## ⚠️ Requires an administrator's decision

The migration refused to guess where the data was ambiguous:

- **Royal Prime Township** — the brief calls it a villa society; its data
  holds **21 apartments and zero villas**. Set to `both`, because `villa`
  would have hidden all 21 units.
- **Sahgun Villa Estate** — named for villas, contains only apartments.
  Set to `apartment` on the evidence.

Both are one click to correct at `/super-admin/societies/{id}/edit`.
Narrowing a society hides non-matching records; it never deletes them, and
the owner edit form keeps an unsupported stored value visible and flagged.

## Honest summary

The property type is now genuinely a property of the society, enforced in
the database, the validators, the navigation, the dashboards, the API and
the mobile app, with 23 new tests covering the paths that matter and a real
browser driven across six roles.

It is **not** proven production-ready. Three things stand between this and
release, none of them code: the suite has never run against MySQL, the
Android toolchain cannot produce a release build, and the mobile app has no
signing identity. Until those are resolved, treat the web application as
verified on SQLite and the mobile application as unbuilt.
