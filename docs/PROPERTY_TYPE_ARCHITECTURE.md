# Property type architecture

**Property type is a property of the society, not a question asked on every
screen.** A society contains apartments, villas, or both. That single setting
decides what may be created inside it, what navigation appears, and what the
APIs advertise.

```
SOCIETY  (property_type: apartment | villa | both)
   └── PROPERTY  (flats.property_type: apartment | villa)
         ├── apartment  → building_id + flat_number
         └── villa      → villa_area_id + flat_number
               └── OWNER / RESIDENT / TENANT
                     └── visitors · bills · tickets · facilities · events
```

---

## 1. The types

Two enums, deliberately separate (`app/Enums/`):

| Enum | Values | Answers |
|---|---|---|
| `PropertyType` | `apartment`, `villa` | What *one unit* is |
| `SocietyPropertyType` | `apartment`, `villa`, `both` | What a *society* permits |

Both use the exact strings already stored in `flats.property_type` and
`owners.property_type`, so they were introduced without rewriting any row.

`SocietyPropertyType` is the one to reach for:

```php
$society->propertyType();              // SocietyPropertyType, never null
$society->supportsApartments();        // bool
$society->supportsVillas();            // bool
$society->isMixedProperty();           // bool
$society->allowedPropertyTypeValues(); // ['villa'] — feed straight to Rule::in
$society->propertyConfig();            // the array the API/JS consume
```

`propertyType()` falls back to `Both` for an unsaved model, so it is always
safe to call. `both` is the permissive value — it hides nothing.

## 2. Storage

`societies.property_type` — `string(20)`, NOT NULL, default `both`, indexed.

A plain string, not a DB enum: SQLite implements enums as CHECK constraints
and widening one later needs a full table rebuild (see
`2026_09_07_000003_repair_visitors_table`). The value is constrained by the
PHP enum and validated on every write.

### Backfill policy

`2026_09_09_000001_add_property_type_to_societies` infers from data that
already exists, and **only where the evidence is unambiguous**:

| Evidence | Result | Certain? |
|---|---|---|
| apartments > 0 **and** villas > 0 | `both` | yes |
| villas > 0, no apartments, no buildings | `villa` | yes |
| apartments > 0, no villas, no villa areas | `apartment` | yes |
| anything else | `both` | **no — logged for review** |

`both` is the safe default because narrowing can hide records; widening
cannot. Ambiguous societies are written to the log with their counts.

Result on the development database:

| Society | Apts | Villas | Result | Note |
|---|---|---|---|---|
| Urban Heights Residency | 77 | 15 | `both` | certain |
| Sahgun Villa Estate | 6 | 0 | `apartment` | **its name disagrees with its data** — review |
| Royal Prime Township | 21 | 0 | `both` | has a villa area but no villas — ambiguous |
| Royal suncity | 0 | 0 | `both` | empty — ambiguous |

> Royal Prime was **not** set to villa-only despite being described that way,
> because it holds 21 apartments and narrowing it would have hidden them.
> Confirm the intended type on the society configuration screen.

## 3. Enforcement — never the UI alone

Three layers, each independent:

**Configuration.** `SuperAdmin\SocietyController` create/update validate
`Rule::in(SocietyPropertyType::values())`.

**Creation.** `OwnerController` resolves the society *before* validating, then
applies `Rule::in($society->allowedPropertyTypeValues())`. `society_id` comes
from that resolution, never straight from the request — an Admin cannot post
another society's id, and an edit is pinned to the owner's existing society.

**Presentation.** The owner form renders only the supported options (and
rebuilds them in JS when a Super Admin switches society); the sidebar and
mobile menu hide the group for an absent type.

A forged POST that skips the form entirely is rejected identically — see
`SocietyPropertyTypeTest::test_forged_property_type_is_rejected_without_the_form`.

## 4. Navigation

`layouts/app.blade.php` (desktop) and `partials/mobile-menu.blade.php` both
guard the Apartments and Villas groups:

```blade
$sfSociety = auth()->user()?->society;
$sfShowApartments = $sfSociety === null || $sfSociety->supportsApartments();
$sfShowVillas     = $sfSociety === null || $sfSociety->supportsVillas();
```

Both files matter: the mobile menu is always in the DOM even when visually
hidden, so guarding only the desktop sidebar leaves the links present.

A null society (Super Admin) resolves permissively.

## 5. API contract

Login (`user`), `/auth/me` and `/profile` (`data`) all carry:

```json
{
  "property_type": "villa",
  "society": {
    "id": 1,
    "name": "Royal Prime",
    "property_type": "villa",
    "property_type_label": "Villa Township",
    "supports_apartments": false,
    "supports_villas": true,
    "is_mixed": false
  }
}
```

Every pre-existing key is unchanged, so builds that ignore `society` keep
working — asserted by `ApiSocietyConfigTest::test_existing_keys_are_unchanged`.

The app should branch on `supports_*` rather than inferring from whether
`villa_number` happens to be populated, which shows apartment UI inside a
villa township whenever a unit has not been assigned yet.

## 6. Why the existing tables were kept

`flats` already holds both kinds, discriminated by `property_type`, with
`building_id` for apartments and `villa_area_id` for villas. That *is* the
common Property concept the brief asks for — it did not need a new table, a
polymorphic rewrite, or a data migration, any of which would have risked
production data for no behavioural gain. The work added the missing piece:
the society-level setting that governs it.

## 7. Tests

| File | Covers |
|---|---|
| `SocietyPropertyTypeTest` (18) | Enum behaviour, society helpers, villa-rejects-apartment, apartment-rejects-villa, mixed-accepts-both, forged POST, cross-society write, edit narrowing, rendered options, admin configuration |
| `ApiSocietyConfigTest` (4) | Login / me / profile carry the capability; existing keys unchanged |
| `PropertyTypeNavigationTest` (4) | Sidebar adapts per type; null society is safe |

## 8. Not yet done

Tracked in [TODO.md](TODO.md). The society-level foundation is in place; these
consume it:

- Dashboard cards are not yet property-type aware (§5 of the brief).
- Query scoping for villas/apartments across the remaining modules relies on
  existing per-controller society scoping; it is correct but not yet expressed
  through the new helpers.
- Flutter does not yet read `society.supports_*`; the API now provides it.
- Guard experience (§8) and the resident "My Property" simplification (§7)
  are untouched.
