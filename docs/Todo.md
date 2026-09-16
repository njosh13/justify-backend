# Justify Billing — Task List

Derived from `BILLING_SYSTEM_PLAN.md` (§13 phased plan), reordered **engine-first** per
2026-09-16 decision: build the pure ARO engine + rule data before the API/tenancy
infrastructure, since the engine carries all the legal risk and has no infra
dependencies.

Markers: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked

**Status 2026-09-16:** Phases A–D complete and green (162 tests, 122 ARO
golden/engine cases, PHPStan + Pint clean). Deviations from plan while
implementing: `brick/money` 0.15 uses `formatToLocale()` (not `formatTo()`)
and camelCase `RoundingMode::HalfUp`; `users.id` stays bigint so
`reviewed_by`/`decided_by` FKs are bigint, not uuid; Sch 4/6/12 "per 15 min"
heads are `per_unit` items; S6.GETTING_UP is seeded as a derived marker and
computed via `GettingUpFee` at bill time; VatCalculator/WithholdingTaxCalculator
created early under `app/Domain/Tax/Vat/` to cover the §8 golden cases.

## Recorded decisions

- **Order of work:** engine core → ARO data layer + seeds → golden tests → then
  infrastructure (tenancy, API, admin) → bills → tax/eTIMS.
- **Dev database:** local PostgreSQL + Redis (not Sail). Switch `.env` off SQLite.
- **Supabase prototype:** no access to the live project right now — dump and data
  import (plan §12) are deferred; schema is built fresh from the plan.
- **Auth:** Sanctum bearer tokens for `/api/v1` (TanStack client); keep Fortify
  session auth for the Inertia admin console. Both live in this repo.
- **Admin console:** stays Inertia/React in this repo; internal-only.

---

## Phase A — Minimal setup (only what the engine needs)

- [x] Point `.env` at local Postgres (`DB_CONNECTION=pgsql`), create the database,
      run migrations. Redis stays `database` driver until queues are needed.
- [x] `composer require brick/money` (pulls `brick/math`)
- [x] `composer require spatie/laravel-data` — DTOs for engine inputs/outputs
- [x] `composer require symfony/yaml` — seed file format (plan §5)
- [x] Create `app/Domain/Aro/` skeleton per plan §2.4
- [x] Pest: add `tests/Unit/Aro/` — engine tests must run with **no DB**
      (pure PHP, `brick/money` only)

## Phase B — ARO engine core (pure PHP — plan §4.1–4.7, 4.11–4.12)

Engine has zero I/O, zero Eloquent, zero clock reads. Money in, `Computed` out.

- [x] `Step`, `Computed`, `Band`, `Computation` interface (§4.1)
- [x] `TieredComputation` — cumulative bands, fixed brackets, per-band floors (§4.2)
- [x] `BracketRateComputation` — non-cumulative, kept for the rejected Sch 2
      reading so both are expressible (§4.3, I-2)
- [x] `FlatComputation`, `PerUnitComputation` (incl. "or part thereof" ceiling) (§4.4)
- [x] `FolioCounter` — 100 words/folio, figure groups count as one word (§4.5, para 17)
- [x] `ModifierOp` enum + `Modifier` + `ModifierPipeline` — sort_order, reductions
      before floors before caps, `floor_ratio_of_base` (§4.6, I-4)
- [x] `Posture` enum + `PostureMultiplier` (65/75/85%) (§4.7, I-1)
- [x] `Certificates` (two advocates ×2, senior counsel ×1.5) (§4.7)
- [x] `GettingUpFee` (⅓ of instruction fee, discretionary) + adjournment cap (§4.7)
- [x] `CostBasisUplift` — Part B ×1.5 (§4.7, I-6)
- [x] `InterestCalculator` — 14% p.a. simple, actual/365, para 7 claim gate (§4.11, I-5)
- [x] `TaxationRiskScorer` — one-sixth rule (§4.12, para 77)

## Phase C — ARO data layer (plan §3.2)

The resolver + FeeCalculator are the only places the engine touches the DB.

- [x] Migrations: `aro_versions`, `aro_items`, `aro_bands`, `aro_modifiers`,
      `aro_interpretations` — exact columns per §3.2
- [~] Models + factories (`AroVersion`, `AroItem`, `AroBand`, `AroModifier`,
      `AroInterpretation`) — models done; factories deferred until HTTP layer
- [x] `Resolver` — `aro_items` row → `Computation` instance (§4.8). Extra
      computations added: `base_plus_rate` (Sch 5 debt collection — the Order's
      bracket bases are non-continuous) and `pointer` ("charged under Schedule 5")
- [x] `FeeCalculator` service (§4.9)
- [ ] `ShortfallCalculator` roll-up (§4.10)
- [x] `AroSeeder` + `database/seeders/aro/LN221-2023/*.yaml`
- [x] Seed Sch 1 (three scales + all modifiers listed in §5)
- [x] Seed Sch 2 (adopted cumulative reading per I-2/I-3) + Sch 3
- [x] Seed Sch 5 (hourly, per-folio, per-unit, debt collection, chattels)
- [x] Seed Sch 6 (instruction fee tables, posture multipliers, certificates,
      per-folio drawing, attendance, service, execution/garnishee)
- [x] Seed Sch 7 (lower/higher scale variant)
- [x] Seed Sch 10 — item 1(a) lower-bracket fees are absent from BOTH the local
      PDF and the official Kenya Law text (§14.1); seed the >1M rule
      (5% of first 1M + 1% over) and leave the four fixed brackets pending
- [x] Transcribe + seed Sch 4 trade-mark table — text extracted, values legible
      (§14.3)
- [x] Seed Sch 8 & 9 — **Part B ×1.5 confirmed present in both schedules**
      (§14.2 resolved 2026-09-16)
- [x] Interpretation item to register: Sch 10 item 1(g) — literal
      "2,103 per 20,000 of net estate × entries" yields 210,300 for the plan's
      worked case (400,000 estate, 5 entries), not the stated 52,575.
      Reconcile formula vs. expected value before trusting golden test.
- [x] Seed `aro_interpretations` register I-1…I-8 + new I-9 (Sch 10 missing
      brackets) and I-10 (Sch 10 1(g) formula inconsistency)
- [x] Seed validation test: tiered/bracket items have contiguous bands from 0,
      exactly one open band, no overlaps (§11.2)

## Phase D — Golden tests (plan §11.1 — every row is an `it()`)

- [x] Sch 1 First Scale table (8 basis cases + vendor-no-agreement)
- [x] Sch 1 Second Scale + all modifier chains incl. combined-reduction floor
- [x] Sch 1 Third Scale negotiation units (half-unit remainder rule)
- [x] Sch 2 — adopted reading; assert bracket_rate figure is NOT produced
- [x] Sch 3 floor-reject/floor-justify; Sch 5 units, debt tiers, chattels
- [x] Sch 6 — postures, getting-up, certificates, Part B, per-folio, per-km,
      possession-suit basis (proviso iv)
- [x] Sch 7 lower/higher; Sch 10 (incl. pending brackets once transcribed);
      Sch 11; Sch 12
- [x] Part I — folio counter, interest dates/gates, deemed agreement + month-end
      clamp, objection deadline, one-sixth boundary, para 22 election, para 58
- [x] VAT/WHT arithmetic cases (§8 worked invoice + threshold + exempt)
- [x] Property tests: monotonic tiered fees, band floors, uplift = exactly 1.5×,
      every `Computed` has steps + provenance (§11.2)

---

## Deferred — infrastructure (plan Phases 0/2 remainder)

Do after Phase D is green:

- [ ] `composer require laravel/sanctum spatie/laravel-permission
      spatie/laravel-activitylog laravel/horizon spatie/laravel-pdf`
- [ ] Core schema §3.1–3.4: `firms`, `firm_user`, `clients`, `matters`,
      `matter_classifications`, `fee_agreements`, `chargeable_items`,
      `time_entries`, `documents`
- [ ] `BelongsToFirm` trait + global scope + policies; tenant isolation test
      suite (404-not-403 on cross-firm access)
- [ ] `routes/api.php` `/api/v1` + Sanctum token auth (access + rotating refresh)
- [ ] `POST /aro/preview`, classification, fee agreements, chargeable-items,
      shortfall endpoints (§10)
- [ ] Inertia admin: ARO versions/items/modifiers with two-person publish gate,
      interpretations register
- [ ] HTTP tests per §11.3 (below-minimum 422, uplift justification, etc.)

## Deferred — bills, tax, eTIMS (plan Phases 4–5)

- [ ] Bills schema §3.5–3.6 + `BillAssembler`, `BillIssuer`, lock trigger,
      numbering, delivery/deemed-agreement clocks, interest accrual job,
      payments, taxations
- [ ] PDFs: fee note, bill of costs (para 69 five-column), statement (§7)
- [ ] VAT/WHT calculators + `firm_tax_profiles`, `tax_rates` (§8)
- [ ] eTIMS: `EtimsClient`, device init, item registration,
      `SubmitInvoiceToEtims` (sequential `invcNo` reservation), credit notes,
      reconciliation job, portal fallback (§9)
- [ ] eTIMS nightly sandbox contract tests (§11.4)
- [ ] Hardening: activity log, rate limits, backups/restore, perf, `cmcKey`
      security review (Phase 6)

## Blocked / needs input

- [!] Supabase schema dump + CSV export — no project access right now (§12)
- [!] KRA OSCU Spec v2.0 PDF + sandbox credentials — validate §9.3 field names
- [!] Sch 10 item 1(a) lower-bracket fees — missing from local PDF AND Kenya Law
      web text; pull the original enactment PDF / Gazette supplement (§14.1)
- [!] Building-society rule paragraph number — cited as "para 34", verify (§14.4)
- [!] WHT rate (5%) + monthly threshold (24,000) — confirm with tax advisor (§14.8)
- [!] KRA third-party-integrator listing decision — product call (§14.9)
