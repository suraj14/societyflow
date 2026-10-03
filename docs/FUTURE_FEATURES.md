# SocietyFlow — Future Features (documented, not implemented)

Per this session's explicit instruction: document ideas surfaced during
audits; do not build them now. Nothing here is scoped, estimated, or
committed to — it's a parking lot, not a roadmap.

## From this session's audit

- **API route reachability monitoring** — the production `/api/v1/*`
  outage found this session (see `FINAL_PRODUCTION_READINESS.md`) would
  have been caught immediately by a scheduled external uptime check hitting
  `/api/v1/health` after every deploy. Worth a lightweight cron + alert once
  someone owns production deploys.
- **Automated `route:cache` verification in the deploy pipeline** — if the
  root cause turns out to be a stale route cache (the leading theory, not
  confirmed - see the readiness doc), a deploy step that runs
  `php artisan route:list --json | grep api/v1/health` and fails the deploy
  if absent would prevent a repeat.

## Carried forward from prior sessions (still just ideas)

- AI-assisted complaint categorization / priority suggestion — explicitly
  out of scope per every prior brief's "no AI" instruction; noted only
  because a future session may be asked for it specifically.
- WhatsApp/SMS notification channel beyond the existing email/push (SMS/OTP
  infrastructure exists for auth; a general notification channel does not).
- Per-society push notification template overrides (`P2-11` — currently
  blocked on a UNIQUE constraint that makes an override impossible, not a
  feature gap so much as a schema fix that would unlock the feature).
- Recurring/scheduled visitor passes (e.g. a maid who visits every weekday)
  — today each visit needs its own pass or invite; `access_passes` already
  supports a *revocable, standing* pass, but not a *recurring schedule*
  layered on top of it.
- Facility booking payment/refund lifecycle (§8, "payment if applicable /
  refund or credit if applicable" in the latest brief) — bookings exist and
  are protected against double-booking, but no facility currently charges a
  fee, so there is no payment or refund path to build yet. Flag for whoever
  decides paid facilities are in scope.
- A dedicated Staff mobile home screen (Flutter currently routes Staff to
  the generic resident-style home, deliberately, per session 9's own
  decision not to invent an unrelated Staff product without a spec).
