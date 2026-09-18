# Justify Billing — Task List

Derived from `BILLING_SYSTEM_PLAN.md` (§13 phased plan), reordered **engine-first** per
2026-09-16 decision: build the pure ARO engine + rule data before the API/tenancy
infrastructure, since the engine carries all the legal risk and has no infra
dependencies.

Markers: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked

**Status 2026-09-18:** Phases A–F complete and green (277 tests, 1 skipped for
I-10; PHPStan level 7 + Pint + `vp check` clean; CI runs the suite on Postgres 16 /
PHP 8.4). Phase F is the advocate test harness — Inertia pages in this repo that
walk firm → client → matter → priced lines → bill → PDF. Run
`php artisan migrate:fresh --seed` then sign in as `test@example.com` / `password`. Deviations from plan while
implementing: `brick/money` 0.15 uses `formatToLocale()` (not `formatTo()`)
and camelCase `RoundingMode::HalfUp`; `users.id` stays bigint so
`reviewed_by`/`decided_by` FKs are bigint, not uuid; Sch 4/6/12 "per 15 min"
heads are `per_unit` items; S6.GETTING_UP / S8.GETTING_UP are `getting_up` heads that
derive one-third of the `instructionFee` passed on the request; VatCalculator/WithholdingTaxCalculator
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
- [x] Pest: `tests/Unit/Aro/` holds the pure-engine invariants (no DB); the
      schedule golden cases live in `tests/Feature/Aro/` and run through the
      seeded catalogue, because the Resolver reads `aro_items` rows and a golden
      case must prove seed data and engine together (plan §11 updated)

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

## Phase E — audit fixes (2026-09-17)

From the review of the Devin build against the Order PDF.

- [x] Sch 10 Part B only in contested matters: `contested_only` cost basis +
      `FeeRequest::$contested` (I-12); uncontested heads marked `non_contentious`
- [x] "Not exceeding" heads are ceilings, not floors: `FeeBound` + `ceiling`
      on `Computed`; `discretionary_cap`, `params.bound`, `params.ceiling`
      (Sch 9 6(2)(b) opposed, Sch 10 7(b) commissions, per-km service, Sch 7 item 2)
- [x] Posture bound to its table (`params.posture_table`; Sch 7 per scale, I-11);
      wrong-table and no-table postures rejected
- [x] Certificates restricted to Sch 6 instruction fees
- [x] `InterestCalculator` rounds once at the end (sub-cent principals no longer throw)
- [x] Basis ≤ 0 rejected; per-unit heads need quantity ≥ 1; flat heads × whole quantity
- [x] Pointer and inactive heads throw instead of returning 0; `S12.CAUTION` inactive
- [x] `S5.HOURLY` = `agreed_rate`; `S6.GETTING_UP` / `S8.GETTING_UP` = `getting_up`
- [x] Unknown modifier codes throw
- [x] Sch 1 rules 29, 34, 35, 36, 41, 46 seeded as modifiers; para 32 cited correctly
- [x] `S6.SERVICE.PER_KM` split from the 3-km flat; `cap: true` param bug removed
- [x] Provenance: replace steps print the resulting figure; one carry step per fixed bracket run
- [x] Seeder prunes rows dropped from YAML; keeps `reviewed` status
- [x] `aro_bands.rate` cast matches column scale; `firm_id` on interpretations documented
- [x] CI: Postgres 16 service, PHP 8.4, `pdo_pgsql`
- [x] Plan §4/§5/§6/§11/§14 corrected (debt collection shape, service split,
      I-9…I-12, test layout, resolved open items)
- [ ] Tests compare money as `float` after `round()` — exact at these magnitudes;
      switch to decimal-string assertions when the I-7 shilling rounding lands

---

## Phase F — advocate test harness (2026-09-18)

Decision: the advocate test happens on Inertia/React pages **in this repo**
(the "client-facing" TanStack app is a separate deliverable; these pages are
for us and pilot advocates to exercise the engine end to end). Every screen
goes through the same services the JSON API will use, so `/api/v1` is a thin
layer to add later — the TanStack client is not blocked on domain logic.

- [x] `firms`, `firm_user` (uuid pivot), `clients`, `matters`,
      `matter_classifications`, `fee_agreements`, `chargeable_items`, `bills`,
      `bill_lines`, `bill_events`, `payments`; `aro_versions.published_by/at`;
      `users.current_firm_id`
- [x] Tenancy: `BelongsToFirm` trait + `FirmScope` + `CurrentFirm` (scoped
      singleton, user's chosen membership) + `EnsureFirmSelected` middleware;
      `firm_id` never fillable; cross-firm requests 404 (tests)
- [x] Roles on `firm_user` (owner/admin/advocate/accounts/readonly) + policies;
      `spatie/laravel-permission` not needed at this size
- [x] Firm onboarding + settings (KRA PIN, VAT registered, rounding policy,
      default cost basis, bill prefix); firm switcher endpoint
- [x] Clients, matters (forum → governing schedule), classification (para 21
      basis, scale, posture, certificates, contested, exemption with audit)
- [x] Fee agreements incl. para 22 election with `election_communicated_at`
- [x] Chargeable items: `ChargeableItemPricer` (matter context + line
      overrides → `FeeRequest`), `ChargeableItemWriter` enforces para 3 floors,
      "not exceeding" ceilings, uplift justification; snapshot stored per line
- [x] `POST /aro/preview` + `GET /aro/items` (JSON, session auth) — the live
      preview and the **Fee calculator** page use the same engine as bills
- [x] `ShortfallCalculator` roll-up; matter page shows blocking / permitted
- [x] `BillAssembler` (lines on the bill's basis, VAT/WHT, para 69 sections and
      taxation line), `BillNumberer` (row-locked per-firm sequence),
      `BillIssuer` (refuses shortfall / unpublished ARO; numbers, locks,
      snapshots, renders PDF), `BillLifecycle` (deliver → deemed-agreed date,
      para 7 interest claim, payments → partially_paid/paid)
- [x] Immutability: model guards on `bills`/`bill_lines` + Postgres triggers;
      only `taxed_off_cents` may change after issue
- [x] PDFs via `barryvdh/laravel-dompdf` (pure PHP; plan's Browsershot needs a
      headless Chrome on every box): fee note / tax invoice with VAT and WHT
      memo, bill of costs in the para 69 five columns; HTML preview route
- [x] Admin: ARO versions list + catalogue browser + interpretations; two-person
      review/publish gate; `php artisan aro:publish` (with `--force` outside
      production)
- [x] `DemoSeeder` (local only): firm, two users (`test@example.com` /
      `partner@example.com`, password `password`), conveyancing, High Court
      and hourly matters with priced lines; ARO version published
- [x] Tests: tenancy isolation, onboarding, chargeable-item enforcement,
      preview, bill lifecycle (§8 worked invoice, numbering, lock, PDF bytes,
      escaping), publish gate, page smoke (277 tests)
- [ ] `/api/v1` with Sanctum bearer + refresh tokens for the TanStack client
      (plan §10) — controllers wrap the same services
- [ ] `time_entries` timer UI (time is captured today as `kind: time` lines)
- [ ] Statement PDF, credit notes / void, taxation tracking (`taxations`)
- [ ] `spatie/laravel-activitylog` on bills/classifications/ARO data
- [ ] Line rounding is HALF_UP to the shilling at bill time (I-7); the item
      minimum keeps cents — decide whether previews should show the rounded
      figure

## Deferred — tax, eTIMS, hardening (plan Phases 5–6)

- [ ] `firm_tax_profiles`, `tax_rates` tables (VAT rate is a constant today;
      `firms.vat_registered` + `clients.is_vat_exempt` drive the invoice)
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
- [!] WHT rate (5%) + monthly threshold (24,000) — confirm with tax advisor (§14.8)
- [!] KRA third-party-integrator listing decision — product call (§14.9)
