# Justify Billing System — Build Plan

**Scope:** a statutory-grade billing engine for Kenyan advocates, implementing the Advocates (Remuneration) Order (Legal Notice 64 of 1962, as revised to 31 December 2022 by LN 221/2023 — "the Order" or "ARO"), the VAT Act 2013, withholding-tax rules under the Income Tax Act, and KRA eTIMS electronic invoicing.

**Stack decision:** Laravel (12 or 13) + Inertia v3/React for the backend and admin console; the existing TanStack Start app becomes the client-facing product for solo practitioners through large firms, talking to Laravel over a JSON API.

**Source of law used for this plan:** `docs/The Advocates (Remuneration) Order.pdf` and <https://new.kenyalaw.org/akn/ke/act/ln/1962/64/eng@2022-12-31>. Every figure in the test tables below is traceable to a paragraph or schedule item. Where the published text is ambiguous, the ambiguity is recorded in §6 and the chosen reading is stated explicitly.

---

## Table of contents

1. [Principles and non-negotiables](#1-principles-and-non-negotiables)
2. [Architecture](#2-architecture)
3. [Database schema](#3-database-schema)
4. [The ARO engine — algorithms in PHP](#4-the-aro-engine--algorithms-in-php)
5. [ARO rule catalogue (seed data)](#5-aro-rule-catalogue-seed-data)
6. [Interpretations register](#6-interpretations-register)
7. [Bill lifecycle](#7-bill-lifecycle)
8. [VAT and withholding tax](#8-vat-and-withholding-tax)
9. [eTIMS integration](#9-etims-integration)
10. [API surface for the TanStack client](#10-api-surface-for-the-tanstack-client)
11. [Tests](#11-tests)
12. [Migration from the Supabase prototype](#12-migration-from-the-supabase-prototype)
13. [Phased build plan](#13-phased-build-plan)
14. [Open items to verify](#14-open-items-to-verify)

---

## 1. Principles and non-negotiables

1. **The law is data, not code.** Every figure, band, floor, cap and percentage in the Order lives in versioned database rows seeded from a reviewed file. The engine is a small interpreter over that data. When the Order is amended, we add a new `aro_versions` row and new data; the engine does not change.
2. **The engine is pure.** `App\Domain\Aro\Engine` has no I/O, no Eloquent, no dates from the clock. Inputs in, money and provenance out. This is what makes it testable against the Order line by line.
3. **Every computed figure carries provenance.** A `Computed` value is a money amount plus an ordered list of steps, each citing the rule it came from. The provenance string is printed on bills of costs and is what an advocate reads out at taxation.
4. **Money is exact.** `brick/money` with KES, computed in cents, rounded once at the line level to the whole shilling (HALF_UP) under a per-firm rounding policy. Never PHP floats. Never `DECIMAL` columns read back as floats.
5. **Floors are enforced; discretion is captured, never guessed.** Where the Order says "such sum as may be reasonable but not less than X", the engine computes X and the system requires the advocate to record a justification for anything above it (paras 4, 5, 50A, Sch 6 provisos). The engine never invents a discretionary figure.
6. **Bills are immutable once issued.** An issued bill stores a full computed snapshot (inputs, ARO version, every step). Corrections are credit notes and re-issues, never edits (para 71 spirit, and an eTIMS requirement).
7. **Two cost bases, always.** Every litigation computation can be produced as party-and-party (Schedule A parts) and as advocate-and-client (A plus 50%, Sch 6B, 7B, 10B, 11B). The bill records which basis it was drawn on.
8. **Tenant isolation is tested, not assumed.** Every table with firm data carries `firm_id`; every model uses a global scope; a dedicated test suite proves a user in firm A cannot read or write firm B through any endpoint.

---

## 2. Architecture

### 2.1 Components

```text
┌──────────────────────────┐        JSON API (Sanctum)        ┌──────────────────────────────┐
│  TanStack Start client   │ ───────────────────────────────▶ │  Laravel                     │
│  (solo → enterprise)     │ ◀─────────────────────────────── │  ├─ /api/v1        (client)  │
└──────────────────────────┘                                  │  ├─ /admin  Inertia v3/React │
                                                              │  ├─ Domain\Aro     (engine)  │
┌──────────────────────────┐        Inertia (session)         │  ├─ Domain\Billing           │
│  Admin console (ops,     │ ───────────────────────────────▶ │  ├─ Domain\Tax  (VAT, eTIMS) │
│  ARO data, support)      │                                  │  └─ Horizon queues           │
└──────────────────────────┘                                  └──────────────┬───────────────┘
                                                                             │
                                          ┌──────────────┬───────────────────┼──────────────────┐
                                          ▼              ▼                   ▼                  ▼
                                      PostgreSQL       Redis            KRA eTIMS API      Object storage
                                      (primary)     (queues/cache)    (OSCU, sandbox→prod)   (PDFs, evidence)
```

- **Laravel** owns identity, tenancy, the ARO engine, bills, tax and eTIMS. PHP 8.4, PostgreSQL 16, Redis, Horizon.
- **Inertia v3 + React** for the admin console only: ARO version management, rule data entry with review workflow, interpretations register, eTIMS device onboarding, tenant support tooling. This is internal; it does not need to be pretty, it needs to be auditable.
- **TanStack Start** stays as the product. It stops calling Supabase directly and calls `/api/v1`. Live previews call `POST /api/v1/aro/preview`, which runs the same engine the bill uses — one source of truth.
- **Auth:** Laravel Sanctum with bearer tokens everywhere — a short-lived access token (15 minutes) plus a rotating refresh token via `POST /api/v1/auth/refresh`. Cookies are not used: the client also ships as a Tauri desktop/mobile app whose origin cannot share a same-site cookie with the API host, and one auth path is simpler than two (see `docs/FRONTEND_PLAN.md` §4.2). Roles via `spatie/laravel-permission` scoped by firm.

### 2.2 Tenancy

Single database, row-level scoping by `firm_id`. This matches the prototype's data model and is the simplest thing that is correct for solo practitioners and mid-size firms.

- `BelongsToFirm` trait: adds a global scope on `firm_id` from the authenticated user's current firm, and sets `firm_id` on create.
- Policies on every model; controllers never query without the scope.
- Enterprise tier: a `firms.isolation` flag reserving the option of a dedicated database per firm via `stancl/tenancy` later. Do not build this in phase 1; do keep all firm-scoped queries going through the trait so the switch is mechanical.

### 2.3 Packages

| Package                                | Purpose                                                          |
| -------------------------------------- | ---------------------------------------------------------------- |
| `brick/money`, `brick/math`            | exact KES arithmetic                                             |
| `spatie/laravel-data`                  | typed DTOs for engine inputs/outputs and API resources           |
| `spatie/laravel-permission`            | roles per firm (owner, admin, advocate, accounts, read-only)     |
| `spatie/laravel-activitylog`           | audit trail on bills, classifications, ARO data                  |
| `laravel/sanctum`                      | SPA + token auth                                                 |
| `laravel/horizon`                      | queues for eTIMS submission, PDF rendering, interest accrual     |
| `spatie/laravel-pdf` (Browsershot)     | PDF rendering from Blade — bills of costs need real table layout |
| `pestphp/pest` + `pest-plugin-laravel` | tests                                                            |
| `inertiajs/inertia-laravel` v3         | admin console                                                    |

### 2.4 Domain module layout

```text
app/Domain/
├── Aro/
│   ├── Engine/            # pure: Money in, Computed out
│   │   ├── Computed.php
│   │   ├── Step.php
│   │   ├── Band.php
│   │   ├── Computations/  # Tiered, BracketRate, Flat, PerUnit, PerFolio
│   │   ├── Modifiers/     # Modifier, ModifierOp, ModifierPipeline
│   │   ├── FolioCounter.php
│   │   ├── PostureMultiplier.php
│   │   ├── GettingUpFee.php
│   │   ├── CostBasisUplift.php
│   │   └── Resolver.php   # aro_items row → Computation instance
│   ├── Models/            # AroVersion, AroItem, AroBand, AroModifier, AroInterpretation
│   └── Services/          # FeeCalculator (loads rules, calls engine), ShortfallCalculator
├── Billing/
│   ├── Models/            # Bill, BillLine, BillEvent, ChargeableItem, FeeAgreement, MatterClassification, Payment, Taxation
│   ├── Services/          # BillAssembler, BillIssuer, InterestCalculator, TaxationRiskScorer
│   └── Documents/         # FeeNote, BillOfCosts (para 69), TaxInvoice, CreditNote renderers
└── Tax/
    ├── Vat/               # VatCalculator, WithholdingTaxCalculator
    └── Etims/             # EtimsClient, DeviceInitializer, ItemRegistrar, InvoiceSubmitter, jobs
```

---

## 3. Database schema

Conventions: `id` is `uuid`; timestamps everywhere; soft deletes on user-facing records; money columns are `bigint` minor units (cents) named `*_cents`; percentages are `numeric(9,6)` stored as decimals (0.02 for 2%); every firm-scoped table has `firm_id uuid not null` indexed.

### 3.1 Identity and tenancy

| Table       | Key columns                                                                                                                                                                                                                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `firms`     | `name`, `plan` (`solo`,`firm`,`enterprise`), `isolation` (`shared`,`dedicated`), `kra_pin`, `lsk_firm_number`, `address`, `email`, `phone`, `rounding_policy` (`shilling_half_up` default), `default_cost_basis` (`advocate_client`) |
| `users`     | `email`, `password`, `full_name`, `lsk_number`, `is_active`                                                                                                                                                                          |
| `firm_user` | `firm_id`, `user_id`, `role` (owner/admin/advocate/accounts/readonly), `hourly_rate_cents`, `is_default`                                                                                                                             |
| `clients`   | `firm_id`, `full_name`, `client_number`, `kra_pin`, `id_number`, `email`, `phone`, `address`, `is_withholding_agent` (bool), `client_type` (`individual`,`company`,`government`)                                                     |
| `matters`   | existing prototype columns plus `court_level` (`high_court`,`subordinate`,`tribunal_lt`,`tribunal_rr`,`tribunal_other`,`none`), `value_cents` nullable                                                                               |

### 3.2 ARO rule data

**`aro_versions`** — one row per amendment state of the Order.

| Column                            | Notes                                                                             |
| --------------------------------- | --------------------------------------------------------------------------------- |
| `code`                            | e.g. `LN221-2023`                                                                 |
| `legal_notice`                    | `LN 221 of 2023 (24th Annual Supplement)`                                         |
| `effective_from` / `effective_to` | `2022-12-31` / null                                                               |
| `source_url`, `source_sha256`     | provenance of the seed file                                                       |
| `status`                          | `draft`, `reviewed`, `published` — only `published` versions can be used on bills |
| `reviewed_by`, `reviewed_at`      | second-person sign-off on data entry                                              |

**`aro_items`** — one row per chargeable head in a schedule.

| Column                  | Notes                                                                                                  |
| ----------------------- | ------------------------------------------------------------------------------------------------------ |
| `aro_version_id`        |                                                                                                        |
| `code`                  | stable key, e.g. `S1.SALE`, `S6.INSTR.DEFENDED`, `S7.INSTR`                                            |
| `schedule`              | 1–12                                                                                                   |
| `label`                 | `Sale or purchase of land — vendor's or purchaser's advocate`                                          |
| `rule_reference`        | `Sch 1, First Scale, para 1`                                                                           |
| `basis_type`            | `consideration`, `annual_rent`, `sum_sued`, `gross_estate`, `debt`, `amount_secured`, `none`           |
| `computation`           | `tiered`, `bracket_rate`, `flat`, `per_unit`, `per_folio`, `discretionary_floor`                       |
| `scale_variant`         | `none`, `lower_higher`                                                                                 |
| `applies_cost_basis`    | `non_contentious`, `contentious` (contentious items get the 50% uplift when drawn advocate-and-client) |
| `is_instruction_fee`    | bool — posture multipliers and getting-up attach to these                                              |
| `unit_label`            | `folio`, `15 minutes`, `hour`, `day`, `km`, `entry`, `patent`                                          |
| `units_included`        | e.g. 4 for "four folios or less"                                                                       |
| `included_amount_cents` | fee for the included units                                                                             |
| `params`                | jsonb for anything else (e.g. `{"discretionary": true}`)                                               |
| `is_active`             |                                                                                                        |

**`aro_bands`** — tiers for `tiered` and `bracket_rate` items.

| Column        | Notes                                                                                                   |
| ------------- | ------------------------------------------------------------------------------------------------------- |
| `aro_item_id` |                                                                                                         |
| `scale`       | null, `lower`, `higher`                                                                                 |
| `lower_cents` | exclusive lower bound (0 for first band)                                                                |
| `upper_cents` | inclusive upper bound, null = open                                                                      |
| `fixed_cents` | fixed fee for this bracket (Sch 6/7/11 lower brackets)                                                  |
| `rate`        | decimal rate applied to the slice above `lower_cents` (cumulative) or to the whole basis (bracket_rate) |
| `floor_cents` | minimum for this band (Sch 1 first band 35,000)                                                         |
| `sort`        |                                                                                                         |

**`aro_modifiers`** — reusable adjustments.

| Column                    | Notes                                                                                      |
| ------------------------- | ------------------------------------------------------------------------------------------ |
| `aro_version_id`          |                                                                                            |
| `code`                    | `S1.VENDOR_NO_AGREEMENT`, `S1.GRANTOR`, `S1.DISCHARGE_UNDERTAKING`, `S6.SENIOR_COUNSEL`, … |
| `label`, `rule_reference` |                                                                                            |
| `op`                      | `multiply`, `add`, `floor`, `cap`, `floor_ratio_of_base`                                   |
| `value`                   | decimal string for ratios, cents for amounts                                               |
| `applies_to_codes`        | jsonb array of `aro_items.code` patterns                                                   |
| `condition_key`           | free key the UI maps to a checkbox/question, e.g. `undertaking_required`                   |
| `sort_order`              | the Order says Sch 1 rules apply "in sequence" (para 26(1))                                |

**`aro_interpretations`** — the register in §6, as data so bills can cite it.

| Column                                                                                                                               | Notes |
| ------------------------------------------------------------------------------------------------------------------------------------ | ----- |
| `aro_version_id`, `rule_reference`, `question`, `decision`, `rationale`, `decided_by`, `decided_at`, `status` (`proposed`,`adopted`) |

### 3.3 Matter classification and agreements

**`matter_classifications`** (one active per matter; history kept)

| Column                                                        | Notes                                                                                                                                                                     |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `matter_id`, `firm_id`                                        |                                                                                                                                                                           |
| `aro_version_id`, `aro_item_id`                               | primary head                                                                                                                                                              |
| `basis_cents`                                                 | subject-matter value                                                                                                                                                      |
| `basis_limb`                                                  | `deed_price`, `stamp_duty_value`, `estate_duty_value`, `last_sale_10y`, `market_value_3y`, `sum_sued`, `sum_found_due`, `annual_rent`, `gross_estate` — para 21 hierarchy |
| `basis_evidence_document_id`                                  | valuation, agreement, plaint                                                                                                                                              |
| `scale`                                                       | `lower`, `higher`, null                                                                                                                                                   |
| `posture`                                                     | `undefended`, `no_appearance`, `summary`, `settled_pre_hearing`, `full_trial`, null — Sch 6/7 item 1                                                                      |
| `certificates`                                                | jsonb: `{"two_advocates":true,"senior_counsel":false,"complex":false,"higher_scale_order":false}` (paras 50A, 59, 60; Sch 6 provisos)                                     |
| `is_exempt`, `exemption_reason`, `exempted_by`, `exempted_at` | pro bono, legal aid                                                                                                                                                       |
| `is_active`, `superseded_by`                                  |                                                                                                                                                                           |

**`fee_agreements`**

| Column                                                    | Notes                                                                                                        |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `matter_id`, `firm_id`, `client_id`                       |                                                                                                              |
| `type`                                                    | `hourly` (Sch 5 Part I), `scale`, `fixed`, `schedule5_election` (para 22), `contingency_prohibited` (reject) |
| `hourly_rate_cents`, `fixed_amount_cents`                 |                                                                                                              |
| `election_notice_document_id`, `election_communicated_at` | para 22(1): election must be signified in writing before or with the bill                                    |
| `signed_at`, `document_id`                                |                                                                                                              |
| `is_active`                                               |                                                                                                              |

### 3.4 Chargeable work

**`chargeable_items`** — replaces both `bill_line_items` and the fee side of `time_entries`.

| Column                                | Notes                                                                                               |
| ------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `matter_id`, `firm_id`, `advocate_id` |                                                                                                     |
| `kind`                                | `fee`, `time`, `disbursement`, `recharge` — see §8 for why disbursement vs recharge matters for VAT |
| `aro_item_id`                         | nullable for disbursements                                                                          |
| `description`                         |                                                                                                     |
| `occurred_on`                         | date of the work — column 1 of a para 69 bill                                                       |
| `quantity`, `unit`                    | e.g. 6 folios, 3 × 15 min                                                                           |
| `folio_text_hash`, `folios`           | when counted from a document                                                                        |
| `basis_override_cents`                | per-item subject-matter value where it differs from the matter's                                    |
| `scale_override`                      |                                                                                                     |
| `modifier_codes`                      | jsonb array of `aro_modifiers.code` applied                                                         |
| `entered_cents`                       | what the advocate wants to charge                                                                   |
| `computed_minimum_cents`              | engine output at save time                                                                          |
| `computed_snapshot`                   | jsonb: full `Computed` (steps, version)                                                             |
| `uplift_justification`                | required when `entered_cents > computed_minimum_cents` and the head is discretionary                |
| `is_billable`, `bill_id`              | nullable until billed                                                                               |
| `voucher_document_id`                 | para 74: vouchers for disbursements                                                                 |

**`time_entries`** — keep for time tracking; a time entry generates a `chargeable_items` row of kind `time` with `aro_item_id` = `S5.TIME_ENGAGED` or the agreed hourly head.

### 3.5 Bills

**`bills`**

| Column                                                                                                                   | Notes                                                                                                                    |
| ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| `firm_id`, `matter_id`, `client_id`                                                                                      |                                                                                                                          |
| `number`                                                                                                                 | per-firm sequence, format configurable (`JF/2026/0001`)                                                                  |
| `type`                                                                                                                   | `fee_note`, `bill_of_costs`, `tax_invoice`, `credit_note`, `proforma`                                                    |
| `cost_basis`                                                                                                             | `party_party`, `advocate_client`                                                                                         |
| `aro_version_id`                                                                                                         | frozen at issue                                                                                                          |
| `status`                                                                                                                 | `draft`, `issued`, `delivered`, `disputed`, `taxation_filed`, `taxed`, `partially_paid`, `paid`, `written_off`, `voided` |
| `fees_cents`, `uplift_cents`, `recharges_cents`, `disbursements_cents`, `vat_cents`, `wht_expected_cents`, `total_cents` |                                                                                                                          |
| `issued_at`, `issued_by`                                                                                                 |                                                                                                                          |
| `delivered_at`, `delivery_method`, `delivery_proof_document_id`                                                          | starts the para 6/7 clocks                                                                                               |
| `deemed_agreed_at`                                                                                                       | `delivered_at + 1 month` (para 6 proviso) unless disputed                                                                |
| `disputed_at`                                                                                                            |                                                                                                                          |
| `interest_claimed_at`                                                                                                    | para 7: interest only if claimed before payment in full                                                                  |
| `paid_in_full_at`                                                                                                        |                                                                                                                          |
| `computed_snapshot`                                                                                                      | jsonb: everything needed to re-render and re-justify                                                                     |
| `pdf_document_id`                                                                                                        |                                                                                                                          |
| `original_bill_id`                                                                                                       | for credit notes                                                                                                         |
| `locked_at`                                                                                                              | set on issue; row becomes append-only via DB trigger                                                                     |

**`bill_lines`** — para 69 five-column format is the canonical shape.

| Column                                        | Notes                                                                                                                |
| --------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `bill_id`, `seq`                              | column 2: serial item number                                                                                         |
| `dated_on`                                    | column 1                                                                                                             |
| `particulars`                                 | column 3                                                                                                             |
| `claimed_cents`                               | column 4                                                                                                             |
| `taxed_off_cents`                             | column 5, filled after taxation                                                                                      |
| `aro_item_id`, `rule_reference`, `provenance` | printed under particulars                                                                                            |
| `chargeable_item_id`                          |                                                                                                                      |
| `section`                                     | `fees`, `disbursements` (shown separately at the foot, para 69(2)), `taxation_attendance` (blank amount, para 69(3)) |
| `vat_rate`, `vat_cents`, `tax_type_code`      | eTIMS `taxTyCd` A–E                                                                                                  |

**`bill_events`** — append-only audit: `issued`, `delivered`, `viewed`, `disputed`, `interest_claimed`, `payment_received`, `etims_submitted`, `taxation_filed`, `taxed`, `credit_note_issued`.

**`payments`** — `bill_id`, `amount_cents`, `method` (`mpesa`,`bank`,`cheque`,`cash`), `reference`, `received_at`, `allocated_to` (`fees`,`disbursements`,`interest`).

**`interest_accruals`** — `bill_id`, `period_start`, `period_end`, `principal_cents`, `rate` (0.14), `amount_cents`; computed nightly by a scheduled job, only when `interest_claimed_at` is set.

**`taxations`** — `bill_id`, `court`, `cause_number`, `filed_at`, `notice_issued_at`, `hearing_at`, `certificate_at`, `claimed_cents`, `court_fees_cents`, `allowed_cents`, `taxed_off_cents`, `one_sixth_breached` (para 77), `objection_deadline_at` (14 days, para 11), `reference_document_id`.

### 3.6 Tax and eTIMS

**`firm_tax_profiles`** — `firm_id`, `kra_pin`, `vat_registered`, `vat_registration_date`, `etims_mode` (`none`,`portal`,`oscu`,`vscu`), `etims_environment` (`sandbox`,`production`), `etims_branch_id` (`00` default), `etims_device_serial`, `etims_cmc_key` (encrypted), `etims_sdc_id`, `etims_last_invoice_no` (strictly sequential per branch — see §9), `etims_certified_at`.

**`tax_rates`** — `code` (`B`), `name` (`Standard VAT`), `rate` (0.16), `effective_from`, `effective_to`. Also `A` exempt, `C` zero-rated, `D` non-VAT, `E` 8%.

**`etims_items`** — `firm_id`, `item_code` (our code, e.g. `LEGAL-FEES`), `item_cls_code` (KRA classification, e.g. `80121600`), `item_name`, `item_type_code` (`3` service), `tax_type_code` (`B`), `registered_at`, `last_response`.

**`etims_submissions`** — `bill_id`, `firm_id`, `kind` (`sale`,`credit_note`), `request_payload` (jsonb), `response_payload` (jsonb), `status` (`queued`,`sent`,`accepted`,`rejected`,`retry`), `attempts`, `last_error`, `invoice_no` (our `invcNo`), `receipt_no`, `internal_data`, `receipt_signature`, `sdc_datetime`, `sdc_id`, `mrc_no`, `qr_code_url`, `cuin` (derived control-unit invoice number for print), `submitted_at`, `accepted_at`.

**`etims_code_lists`** — cache of `selectCodeList` / `selectItemClsList` responses with `fetched_at`.

### 3.7 Documents

**`documents`** — `firm_id`, `kind` (`bill_pdf`, `valuation`, `agreement`, `voucher`, `election_notice`, `delivery_proof`, `taxation_certificate`), `storage_path`, `sha256`, `uploaded_by`.

---

## 4. The ARO engine — algorithms in PHP

All code below is `App\Domain\Aro\Engine`. It depends only on `brick/money`.

### 4.1 Value objects

```php
<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine;

use Brick\Money\Money;

final readonly class Step
{
    public function __construct(
        public string $ruleRef,     // "Sch 1, First Scale, para 1(ii)"
        public string $description, // "1.5% × KES 5,000,000"
        public Money $amount,       // contribution of this step (may be negative)
        public Money $runningTotal,
    ) {}
}

final class Computed
{
    /** @param Step[] $steps */
    private function __construct(
        public readonly Money $amount,
        public readonly array $steps,
        public readonly bool $discretionary = false, // "not less than" heads
    ) {}

    public static function zero(): self
    {
        return new self(Money::zero('KES'), []);
    }

    public function add(string $ruleRef, string $description, Money $delta): self
    {
        $total = $this->amount->plus($delta);
        return new self($total, [...$this->steps, new Step($ruleRef, $description, $delta, $total)], $this->discretionary);
    }

    public function replace(string $ruleRef, string $description, Money $new): self
    {
        return $this->add($ruleRef, $description, $new->minus($this->amount));
    }

    public function markDiscretionary(): self
    {
        return new self($this->amount, $this->steps, true);
    }

    public function provenance(): string
    {
        return implode('; ', array_map(
            fn (Step $s) => sprintf('%s: %s = %s', $s->ruleRef, $s->description, $s->amount->formatTo('en_KE')),
            $this->steps,
        ));
    }
}

final readonly class Band
{
    public function __construct(
        public Money $lower,        // exclusive
        public ?Money $upper,       // inclusive; null = open
        public ?Money $fixed,       // fixed fee for the bracket (Sch 6/7/11)
        public ?string $rate,       // decimal string, e.g. '0.015'
        public ?Money $floor,       // minimum within this band (Sch 1 band 1)
    ) {}

    public function contains(Money $basis): bool
    {
        return $basis->isGreaterThan($this->lower)
            && ($this->upper === null || $basis->isLessThanOrEqualTo($this->upper));
    }
}

interface Computation
{
    public function compute(?Money $basis, int|float|null $quantity = null): Computed;
}
```

### 4.2 Tiered (cumulative) computation — Schedules 1, 5 (debt), 6, 7, 10, 11

Fee for a basis inside band _n_ is the fee at the top of band _n−1_ plus `rate × (basis − lower_n)`, or the band's `fixed` amount where the bracket is a fixed fee. Band 1 may carry a floor ("or KES 35,000 whichever is higher").

```php
<?php

declare(strict_types=1);

namespace App\Domain\Aro\Engine\Computations;

use App\Domain\Aro\Engine\{Band, Computation, Computed};
use Brick\Math\RoundingMode;
use Brick\Money\Money;

final class TieredComputation implements Computation
{
    /** @param Band[] $bands ascending, contiguous */
    public function __construct(
        private readonly string $ruleRef,
        private readonly array $bands,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        if ($basis === null) {
            throw new \InvalidArgumentException("{$this->ruleRef} requires a subject-matter value");
        }

        $result = Computed::zero();

        foreach ($this->bands as $i => $band) {
            if ($basis->isLessThanOrEqualTo($band->lower)) {
                break; // basis does not reach this band
            }

            $bandRef = sprintf('%s band %d', $this->ruleRef, $i + 1);

            if ($band->fixed !== null && $band->contains($basis)) {
                // Fixed-fee bracket (Sch 6/7/11 lower brackets): replaces, does not accumulate.
                return $result->replace($bandRef, sprintf('fixed fee for %s', self::range($band)), $band->fixed);
            }

            if ($band->fixed !== null) {
                // Fixed bracket below the basis: its value is the running total for the next band.
                $result = $result->replace($bandRef, sprintf('fee as for %s', $band->upper->formatTo('en_KE')), $band->fixed);
                continue;
            }

            $top   = $band->upper === null ? $basis : Money::min($basis, $band->upper);
            $slice = $top->minus($band->lower);
            $fee   = $slice->multipliedBy($band->rate, RoundingMode::HALF_UP);

            $result = $result->add(
                $bandRef,
                sprintf('%s%% × %s', rtrim(rtrim(bcmul($band->rate, '100', 4), '0'), '.'), $slice->formatTo('en_KE')),
                $fee,
            );

            if ($band->floor !== null && $result->amount->isLessThan($band->floor)) {
                $result = $result->replace($bandRef.' floor', sprintf('or %s whichever is higher', $band->floor->formatTo('en_KE')), $band->floor);
            }
        }

        return $result;
    }

    private static function range(Band $b): string
    {
        return $b->upper === null
            ? 'over '.$b->lower->formatTo('en_KE')
            : $b->lower->formatTo('en_KE').' – '.$b->upper->formatTo('en_KE');
    }
}
```

Worked example, Sch 1 First Scale, basis 10,000,000:

```text
band 1: 2% × 5,000,000 = 100,000   (floor 35,000 not reached)
band 2: 1.5% × 5,000,000 = 75,000
total 175,000
```

### 4.3 Bracket-rate (non-cumulative) computation — Schedule 2 Note 4 reading

The rate of the band that contains the basis is applied to the whole basis. Kept as a separate computation so both readings of Schedule 2 can be expressed and the adopted one selected in data (§6, item I-2).

```php
final class BracketRateComputation implements Computation
{
    public function __construct(private readonly string $ruleRef, private readonly array $bands) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        foreach ($this->bands as $band) {
            if ($band->contains($basis)) {
                $fee = $basis->multipliedBy($band->rate, RoundingMode::HALF_UP);
                $c = Computed::zero()->add($this->ruleRef, sprintf('%s × whole basis', $band->rate), $fee);
                if ($band->floor !== null && $fee->isLessThan($band->floor)) {
                    $c = $c->replace($this->ruleRef.' floor', 'minimum', $band->floor);
                }
                return $c;
            }
        }
        throw new \LogicException('No band contains basis');
    }
}
```

### 4.4 Flat, per-unit, per-folio, discretionary floor

```php
final class FlatComputation implements Computation
{
    public function __construct(private string $ruleRef, private Money $amount, private bool $discretionary = false) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $c = Computed::zero()->add($this->ruleRef, $this->discretionary ? 'not less than' : 'prescribed fee', $this->amount);
        return $this->discretionary ? $c->markDiscretionary() : $c;
    }
}

/**
 * "four folios or less 1,100; in excess of four folios, per folio 150" (Sch 6 item 4(a))
 * "per 15 minutes or part thereof 1,000" (Sch 5 Part II item 3) → unitsIncluded 0, includedAmount 0
 */
final class PerUnitComputation implements Computation
{
    public function __construct(
        private string $ruleRef,
        private int $unitsIncluded,
        private Money $includedAmount,
        private Money $perAdditionalUnit,
    ) {}

    public function compute(?Money $basis, int|float|null $quantity = null): Computed
    {
        $units = (int) ceil((float) ($quantity ?? 0)); // "or part thereof"
        $c = Computed::zero();
        if ($this->unitsIncluded > 0) {
            $c = $c->add($this->ruleRef, sprintf('first %d units', $this->unitsIncluded), $this->includedAmount);
        }
        $extra = max(0, $units - $this->unitsIncluded);
        if ($extra > 0) {
            $c = $c->add($this->ruleRef, sprintf('%d additional units × %s', $extra, $this->perAdditionalUnit->formatTo('en_KE')), $this->perAdditionalUnit->multipliedBy($extra));
        }
        return $c;
    }
}
```

### 4.5 Folio counter — para 17

A folio is 100 words; any part of a folio is one folio; a sum or quantity of one denomination stated in figures is one word.

```php
final class FolioCounter
{
    public static function words(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        // Collapse "Kshs. 25,564" / "£25,564 16s 8d" style figure groups: a currency marker
        // followed by a number is one word; each figure+unit pair is one word (para 17 example).
        $text = preg_replace('/\b(K?[Ss]hs?\.?|KES|£|\$)\s*(?=\d)/u', '', $text);
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($tokens);
    }

    public static function folios(string $text): int
    {
        return (int) ceil(self::words($text) / 100);
    }
}
```

### 4.6 Modifier pipeline

Modifiers run in `sort_order` after the base computation. `floor_ratio_of_base` exists for Sch 1 rules that cap total reductions ("not subject … to a reduction in excess of one-half of the scale fee").

```php
enum ModifierOp: string
{
    case Multiply = 'multiply';
    case Add = 'add';
    case Floor = 'floor';
    case Cap = 'cap';
    case FloorRatioOfBase = 'floor_ratio_of_base';
}

final readonly class Modifier
{
    public function __construct(
        public string $code,
        public string $ruleRef,
        public ModifierOp $op,
        public string|Money $value, // decimal string for ratios, Money for amounts
        public int $sortOrder,
    ) {}
}

final class ModifierPipeline
{
    /** @param Modifier[] $modifiers */
    public function apply(Computed $base, array $modifiers): Computed
    {
        usort($modifiers, fn ($a, $b) => $a->sortOrder <=> $b->sortOrder);
        $original = $base->amount;
        $c = $base;

        foreach ($modifiers as $m) {
            $c = match ($m->op) {
                ModifierOp::Multiply => $c->replace($m->ruleRef, "× {$m->value}", $c->amount->multipliedBy($m->value, RoundingMode::HALF_UP)),
                ModifierOp::Add      => $c->add($m->ruleRef, 'add', $m->value),
                ModifierOp::Floor    => $c->amount->isLessThan($m->value)
                                            ? $c->replace($m->ruleRef, 'subject to minimum', $m->value) : $c,
                ModifierOp::Cap      => $c->amount->isGreaterThan($m->value)
                                            ? $c->replace($m->ruleRef, 'subject to maximum', $m->value) : $c,
                ModifierOp::FloorRatioOfBase => (function () use ($c, $m, $original) {
                    $floor = $original->multipliedBy($m->value, RoundingMode::HALF_UP);
                    return $c->amount->isLessThan($floor)
                        ? $c->replace($m->ruleRef, "reduction capped at {$m->value} of scale", $floor) : $c;
                })(),
            };
        }

        return $c;
    }
}
```

Schedule 1 Second Scale examples as modifier chains (all relative to the grantee creation fee):

| Situation                                                    | Chain                                                                   |
| ------------------------------------------------------------ | ----------------------------------------------------------------------- |
| Grantor's advocate, creation (d)                             | `multiply 0.5`                                                          |
| Grantee's advocate, discharge with undertaking (c)(i)        | `multiply 0.25`, `floor 15,000`                                         |
| Grantee's advocate, discharge without undertaking (c)(ii)    | `multiply 0.15`, `floor 10,000`                                         |
| Grantor's advocate, discharge (e)                            | `multiply 0.25`, `floor 15,000`                                         |
| Equitable mortgage by deposit, creation (Note 1(a))          | `multiply 0.5`, `floor 12,500`                                          |
| Discharge of equitable mortgage (Note 1(b))                  | `multiply 0.15`, `floor 10,000`, `cap 42,000`                           |
| Building society printed form (para 34)                      | `multiply 0.6667`, `floor_ratio_of_base 0.5`                            |
| Vendor's advocate prepares no agreement (para 18(a) proviso) | `multiply 0.6667` on First Scale                                        |
| Second security, same grantee (Note 5)                       | separate line: `multiply 0.25`; third and later: `multiply 0.10`        |
| Second property in one charge (Note 6)                       | separate line: `multiply 0.10`; third and later: `multiply 0.05`        |
| Each additional grantor (Note 7)                             | separate line: `multiply 0.05`; total divided equally among grantors    |
| One advocate for both grantee and grantor (Note 3)           | grantee fee + `multiply 0.5` of grantor fee (i.e. + 25% of grantee fee) |

### 4.7 Litigation instruction fee: posture, scale, certificates, getting-up, uplift

```php
enum Posture: string
{
    case FullTrial = 'full_trial';
    case Undefended = 'undefended';           // uses the (a) table
    case NoAppearance = 'no_appearance';      // 65% of (a)
    case Summary = 'summary';                 // 75% of (b)
    case SettledPreHearing = 'settled_pre_hearing'; // 85% of (b)
}

final class PostureMultiplier
{
    public static function apply(Computed $instruction, Posture $posture): Computed
    {
        return match ($posture) {
            Posture::NoAppearance      => $instruction->replace('Sch 6/7 item 1(a)', '65% — no appearance entered', $instruction->amount->multipliedBy('0.65', RoundingMode::HALF_UP)),
            Posture::Summary           => $instruction->replace('Sch 6/7 item 1(b)', '75% — determined summarily', $instruction->amount->multipliedBy('0.75', RoundingMode::HALF_UP)),
            Posture::SettledPreHearing => $instruction->replace('Sch 6/7 item 1(c)', '85% — settled before first hearing confirmed', $instruction->amount->multipliedBy('0.85', RoundingMode::HALF_UP)),
            default                    => $instruction,
        };
    }
}

final class Certificates
{
    public function __construct(
        public bool $twoAdvocates = false,   // Sch 6 proviso (ii): instruction fee doubled
        public bool $seniorCounsel = false,  // Sch 6 proviso (iii): instruction fee +50%, other charges doubled
        public bool $higherScaleOrder = false, // para 50A
    ) {}

    public function applyToInstruction(Computed $c): Computed
    {
        if ($this->twoAdvocates) {
            $c = $c->replace('Sch 6 proviso (ii)', 'certificate for two advocates — doubled', $c->amount->multipliedBy(2));
        }
        if ($this->seniorCounsel) {
            $c = $c->replace('Sch 6 proviso (iii)', 'certificate for senior counsel — increased by one-half', $c->amount->multipliedBy('1.5', RoundingMode::HALF_UP));
        }
        return $c;
    }
}

/** Sch 6 para 2: not less than one-third of the instruction fee, only once hearing confirmed. */
final class GettingUpFee
{
    public static function minimum(Computed $instruction): Computed
    {
        $third = $instruction->amount->dividedBy(3, RoundingMode::HALF_UP);
        return Computed::zero()
            ->add('Sch 6 para 2', 'getting up — not less than one-third of instruction fee', $third)
            ->markDiscretionary();
    }

    /** proviso (ii): up to 15% of instruction fee per adjournment of a confirmed hearing, if the judge so directs */
    public static function adjournmentCap(Computed $instruction): Money
    {
        return $instruction->amount->multipliedBy('0.15', RoundingMode::HALF_UP);
    }
}

/** Sch 6B, 7B, 10B, 11B: advocate-and-client minimum = party-and-party fees increased by 50%. */
final class CostBasisUplift
{
    public static function advocateClient(Computed $partyAndParty, string $scheduleRef): Computed
    {
        return $partyAndParty->replace(
            "{$scheduleRef} Part B",
            'advocate and client — increased by 50%',
            $partyAndParty->amount->multipliedBy('1.5', RoundingMode::HALF_UP),
        );
    }
}
```

### 4.8 Resolver: `aro_items` row → Computation

```php
final class Resolver
{
    public function computationFor(AroItem $item, ?string $scale): Computation
    {
        $bands = $item->bands
            ->when($item->scale_variant === 'lower_higher', fn ($q) => $q->where('scale', $scale ?? 'lower'))
            ->sortBy('sort')
            ->map(fn (AroBand $b) => new Band(
                Money::ofMinor($b->lower_cents, 'KES'),
                $b->upper_cents === null ? null : Money::ofMinor($b->upper_cents, 'KES'),
                $b->fixed_cents === null ? null : Money::ofMinor($b->fixed_cents, 'KES'),
                $b->rate,
                $b->floor_cents === null ? null : Money::ofMinor($b->floor_cents, 'KES'),
            ))->values()->all();

        return match ($item->computation) {
            'tiered'              => new TieredComputation($item->rule_reference, $bands),
            'bracket_rate'        => new BracketRateComputation($item->rule_reference, $bands),
            'flat'                => new FlatComputation($item->rule_reference, Money::ofMinor($item->included_amount_cents, 'KES')),
            'discretionary_floor' => new FlatComputation($item->rule_reference, Money::ofMinor($item->included_amount_cents, 'KES'), discretionary: true),
            'per_unit', 'per_folio' => new PerUnitComputation(
                $item->rule_reference,
                $item->units_included ?? 0,
                Money::ofMinor($item->included_amount_cents ?? 0, 'KES'),
                Money::ofMinor($item->params['per_unit_cents'], 'KES'),
            ),
        };
    }
}
```

### 4.9 FeeCalculator service (the only place the engine meets the database)

```php
final class FeeCalculator
{
    public function __construct(private Resolver $resolver, private ModifierPipeline $pipeline) {}

    public function minimum(FeeRequest $r): Computed
    {
        $item = AroItem::query()
            ->whereBelongsTo($r->version)
            ->where('code', $r->itemCode)
            ->firstOrFail();

        $base = $this->resolver->computationFor($item, $r->scale)->compute($r->basis, $r->quantity);

        $modifiers = AroModifier::query()
            ->whereBelongsTo($r->version)
            ->whereIn('code', $r->modifierCodes)
            ->get()
            ->map(fn ($m) => $m->toEngine())
            ->all();

        $c = $this->pipeline->apply($base, $modifiers);

        if ($item->is_instruction_fee) {
            $c = PostureMultiplier::apply($c, $r->posture ?? Posture::FullTrial);
            $c = $r->certificates->applyToInstruction($c);
        }

        if ($item->applies_cost_basis === 'contentious' && $r->costBasis === CostBasis::AdvocateClient) {
            $c = CostBasisUplift::advocateClient($c, "Sch {$item->schedule}");
        }

        return $c;
    }
}
```

### 4.10 Shortfall (para 3) at matter level

The prototype's single scalar is replaced by a per-item check plus a matter roll-up:

```text
for each chargeable_item of kind fee|time with an aro_item:
    minimum_i = FeeCalculator.minimum(...)
    shortfall_i = max(0, minimum_i − entered_i)
matter_shortfall = Σ shortfall_i
required_heads_missing = instruction fee present but getting-up absent when posture = full_trial and hearing confirmed, etc.
```

A bill cannot be issued while `matter_shortfall > 0` unless the classification is exempt or a `fee_agreements.type = schedule5_election` exists with `election_communicated_at` set (para 22).

### 4.11 Interest — para 7

Simple interest at 14% per annum on costs and disbursements, from the expiry of one month after delivery of the bill, only if the claim for interest is raised before the bill is paid or tendered in full. Actual/365 day count.

```php
final class InterestCalculator
{
    public const RATE = '0.14';

    public function accrued(
        Money $principal,
        CarbonImmutable $deliveredAt,
        ?CarbonImmutable $interestClaimedAt,
        ?CarbonImmutable $paidInFullAt,
        CarbonImmutable $asOf,
    ): Money {
        $start = $deliveredAt->addMonthNoOverflow();

        if ($interestClaimedAt === null) {
            return Money::zero('KES');
        }
        if ($paidInFullAt !== null && $interestClaimedAt->greaterThan($paidInFullAt)) {
            return Money::zero('KES'); // claim raised after full payment: not chargeable
        }

        $end = $paidInFullAt === null ? $asOf : min($paidInFullAt, $asOf);
        if ($end->lessThanOrEqualTo($start)) {
            return Money::zero('KES');
        }

        $days = $start->diffInDays($end);

        return $principal
            ->multipliedBy(self::RATE, RoundingMode::UNNECESSARY)
            ->multipliedBy($days)
            ->dividedBy(365, RoundingMode::HALF_UP);
    }
}
```

### 4.12 Taxation risk — para 77

```php
final class TaxationRiskScorer
{
    /** true when more than one-sixth of the bill (excluding court fees) is taxed off. */
    public static function oneSixthBreached(Money $claimedExCourtFees, Money $taxedOff): bool
    {
        return $taxedOff->multipliedBy(6)->isGreaterThan($claimedExCourtFees);
    }
}
```

Pre-filing, the same class scores a draft bill: proportion of lines that are discretionary uplifts without justification, lines above scale with no para 4/5 note, disbursements without vouchers (para 74).

### 4.13 Bill assembler

```text
input: matter, classification, cost basis, chargeable items (unbilled, billable)
1. group: fees (kind fee|time), recharges, disbursements
2. for fees: recompute minimum with the ARO version being billed; line.claimed = max(entered, minimum) unless exempt/elected; store provenance
3. if contentious and cost basis = advocate_client: uplift is applied per line via FeeCalculator (already), and the bill shows a subtotal line "Increase of 50% as between advocate and client (Sch 6B)"
4. VAT: taxable = fees + recharges; disbursements out of scope (§8)
5. WHT memo: if client.is_withholding_agent, wht_expected = 5% × fees (§8)
6. sequence lines by occurred_on then seq; disbursements in their own section at the foot (para 69(2))
7. append "Attending taxation" line with blank amount when type = bill_of_costs (para 69(3))
8. snapshot everything; number the bill; lock
```

---

## 5. ARO rule catalogue (seed data)

Seed lives in `database/seeders/aro/LN221-2023/*.yaml` and is loaded by `AroSeeder`. The full catalogue is roughly 200 items; the table below fixes the codes and shapes for the heads that carry most of the money. All amounts KES.

### Schedule 1 — sales, purchases, securities

| Code                       | Rule                     | Basis          | Computation                                              | Bands                                                                                                 |
| -------------------------- | ------------------------ | -------------- | -------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `S1.SALE`                  | Sch 1 First Scale para 1 | consideration  | tiered                                                   | (0–5M] 2% floor 35,000; (5M–100M] 1.5%; (100M–250M] 1.25%; (250M–1B] 1%; >1B 0.1%                     |
| `S1.SECURITY.GRANTEE`      | Sch 1 Second Scale 2(b)  | amount secured | tiered                                                   | (0–2.5M] 2% floor 28,000; (2.5M–5M] 1.75%; (5M–100M] 1%; (100M–250M] 0.75%; (250M–1B] 0.15%; >1B 0.1% |
| `S1.NEGOTIATION`           | Sch 1 Third Scale 3      | consideration  | per_unit (KES 2,000 units; ≤1,000 remainder = half unit) | first 100 units @112; next 200 @52; thereafter @30                                                    |
| `S1.DEBENTURE.NO_SECURITY` | Sch 1 Note 2             | —              | → Schedule 5                                             |                                                                                                       |

Modifiers: `S1.VENDOR_NO_AGREEMENT` ×⅔; `S1.GRANTOR` ×0.5; `S1.DISCHARGE.UNDERTAKING` ×0.25 floor 15,000; `S1.DISCHARGE.NO_UNDERTAKING` ×0.15 floor 10,000; `S1.GRANTOR.DISCHARGE` ×0.25 floor 15,000; `S1.EQUITABLE` ×0.5 floor 12,500; `S1.EQUITABLE.DISCHARGE` ×0.15 floor 10,000 cap 42,000; `S1.BOTH_SIDES` +25%; `S1.ADDITIONAL_SECURITY.FIRST` ×0.25; `S1.ADDITIONAL_SECURITY.SUBSEQUENT` ×0.10; `S1.ADDITIONAL_PROPERTY.SECOND` ×0.10; `S1.ADDITIONAL_PROPERTY.SUBSEQUENT` ×0.05; `S1.ADDITIONAL_GRANTOR` ×0.05; `S1.PRINTED_FORM` ×⅔ then floor_ratio_of_base 0.5; `S1.TRANSFER_OF_MORTGAGE.TITLE_PREVIOUSLY_INVESTIGATED` → Schedule 5 (para 41).

### Schedule 2 — leases at a rack rent

| Code               | Rule         | Basis                                         | Computation             | Bands                                           |
| ------------------ | ------------ | --------------------------------------------- | ----------------------- | ----------------------------------------------- |
| `S2.LEASE.PREPARE` | Sch 2 para 1 | annual rent (highest rent if varying, Note 1) | tiered (see I-2)        | (0–500k] 15% floor 20,000; (500k–3M] 3%; >3M 1% |
| `S2.LEASE.PERUSE`  | Sch 2 para 2 | annual rent                                   | `S2.LEASE.PREPARE` ×0.5 |                                                 |

Modifiers: `S2.PRINTED_FORM` ×⅔ (two or more leases in common form); `S2.PREMIUM` adds `S1.SALE` on the premium (para 48); `S2.EXTENSION_BY_ENDORSEMENT` → Schedule 5 (Note 2).

### Schedule 3 — companies

| Code                      | Rule         | Computation                |
| ------------------------- | ------------ | -------------------------- |
| `S3.INCORPORATION`        | Sch 3 para 1 | discretionary_floor 60,000 |
| `S3.FOREIGN_REGISTRATION` | Sch 3 para 2 | discretionary_floor 60,000 |

### Schedule 4 — trade marks

Flat fees per particular (registration, opposition, renewal, assignment, search). Seed from the table in the Order; taxing officer is the Registrar of Trade Marks (para 10).

### Schedule 5 — general business and elected matters

| Code                 | Rule                | Computation                                                                                                             |
| -------------------- | ------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `S5.HOURLY`          | Sch 5 Part I para 2 | agreed hourly rate (from `fee_agreements`), no scale floor of its own; para 3 still applies via the applicable schedule |
| `S5.INSTRUCTIONS`    | Part II item 1      | discretionary (no floor stated)                                                                                         |
| `S5.DRAWING`         | Part II item 2      | per_folio 250                                                                                                           |
| `S5.ENGROSSING`      | Part II item 2      | per_folio 50                                                                                                            |
| `S5.FAIR_COPY`       | Part II item 2      | per_folio 30                                                                                                            |
| `S5.PERUSING`        | Part II item 2      | per_folio 70                                                                                                            |
| `S5.ATTENDANCE`      | Part II item 3      | per_unit 15 min @1,000                                                                                                  |
| `S5.PHONE`           | Part II item 3      | per_unit 15 min @150                                                                                                    |
| `S5.TIME_ENGAGED`    | Part II item 4      | per_unit 15 min @7,000 (in lieu of per-item charges)                                                                    |
| `S5.LETTER`          | Part II item 5      | flat 300 or per_folio 200                                                                                               |
| `S5.LETTER_RECEIVED` | Part II item 5      | flat 150 or per_folio 70                                                                                                |
| `S5.OPINION`         | Part II item 6      | discretionary_floor 35,000                                                                                              |
| `S5.JOURNEY.DAY`     | Part II item 6      | flat 15,000 per day of ≥7 hours                                                                                         |
| `S5.JOURNEY.HOUR`    | Part II item 6      | per_unit hour @2,500                                                                                                    |
| `S5.DEBT_COLLECTION` | Part II item 7      | tiered: (0–100k] 10%; (100k–500k] 5%; (500k–2M] 3%; >2M 1.5%                                                            |
| `S5.CHATTELS.SMALL`  | Chattels transfer   | flat 6,000 (≤50,000 secured)                                                                                            |
| `S5.CHATTELS.LARGE`  | Chattels transfer   | `S1.SECURITY.GRANTEE` ×0.5 (>50,000 secured)                                                                            |

Modifiers: `S5.DEBT.ONE_LETTER` ×0.5 floor 1,000.

### Schedule 6 — High Court (Part A party-and-party; Part B = A × 1.5)

| Code                              | Rule               | Basis    | Computation                            | Bands                                                                                              |
| --------------------------------- | ------------------ | -------- | -------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `S6.INSTR.UNDEFENDED`             | item 1(a)          | value    | tiered                                 | (0–500k] fixed 45,000; (500k–750k] fixed 65,000; (750k–1M] fixed 75,000; (1M–20M] 1.75%; >20M 1.5% |
| `S6.INSTR.DEFENDED`               | item 1(b)          | value    | tiered                                 | (0–500k] fixed 75,000; (500k–750k] fixed 90,000; (750k–1M] fixed 120,000; (1M–20M] 2%; >20M 1.5%   |
| `S6.INSTR.OTHER.UNDEFENDED`       | Other matters (i)  | —        | discretionary_floor 45,000             |
| `S6.INSTR.OTHER.DEFENDED`         | Other matters (ii) | —        | discretionary_floor 75,000             |
| `S6.INSTR.APPEAL`                 | Appeals (a)        | —        | discretionary_floor 25,200             |
| `S6.INSTR.ELECTION_PETITION`      | (i)                | —        | discretionary_floor 500,000            |
| `S6.INSTR.CONSTITUTIONAL.OPPOSED` | (j)(ii)            | —        | discretionary_floor 100,000            |
| `S6.INSTR.ARBITRAL_SET_ASIDE`     | (j)(iii)           | —        | flat 50,000                            |
| `S6.INSTR.DIVORCE.UNDEFENDED`     | (g)(i)             | —        | flat 20,000                            |
| `S6.INSTR.DIVORCE.DEFENDED`       | (g)(i)             | —        | discretionary_floor 35,300             |
| `S6.INSTR.WINDING_UP.SUPPORT`     | (f)(ii)            | —        | flat 10,000                            |
| `S6.INSTR.COMPLEX_CERTIFIED`      | (c)(vii)           | —        | discretionary_floor 100,000            |
| `S6.INSTR.APPLICATION.UNOPPOSED`  | (c)(viii)          | —        | flat 3,000                             |
| `S6.INSTR.APPLICATION.OPPOSED`    | (c)(viii)          | —        | discretionary_floor 5,000              |
| `S6.GETTING_UP`                   | para 2             | —        | derived: ≥ ⅓ instruction fee           |
| `S6.DRAWING.PLEADING`             | item 4(a)          | folios   | per_folio: ≤4 folios 1,100; +150/folio |
| `S6.DRAWING.OTHER`                | item 4(d)          | folios   | per_folio 180                          |
| `S6.DRAWING.BILL_OF_COSTS`        | item 4(e)          | folios   | per_folio 180                          |
| `S6.DRAWING.AFFIDAVIT_OF_SERVICE` | item 4(f)          | —        | flat 240                               |
| `S6.COPIES`                       | item 5(a),(e)      | folios   | per_folio 25                           |
| `S6.LETTER`                       | item 6             | —        | flat 1,000 or per_folio 118            |
| `S6.ATTEND.REGISTRAR`             | item 7(a)          | —        | flat 1,000                             |
| `S6.ATTEND.ROUTINE`               | item 7(b)          | —        | flat 500                               |
| `S6.ATTEND.COURT.HALF_HOUR`       | item 7(d)          | —        | lower 1,100 / higher 1,900             |
| `S6.ATTEND.COURT.HOUR`            | item 7(d)          | —        | lower 2,300 / higher 3,000             |
| `S6.ATTEND.COURT.DAY`             | item 7(d)          | —        | lower 10,000 / higher 15,000           |
| `S6.PERUSAL.ROUTINE`              | item 8(b)          | —        | flat 50                                |
| `S6.SERVICE.LOCAL`                | item 9(a)          | —        | flat 1,400 (within 3 km)               |
| `S6.SERVICE.PER_KM`               | item 9(b)          | km       | per_unit km @35 (beyond 3 km)          |
| `S6.EXECUTION.INSTRUCTIONS`       | item 12(a)         | —        | flat 1,000                             |
| `S6.OBJECTION.INSTRUCTIONS`       | item 13(a)         | —        | flat 10,000                            |
| `S6.GARNISHEE.UNOPPOSED`          | item 14(a)         | —        | flat 4,200                             |
| `S6.GARNISHEE.OPPOSED`            | item 14(b)         | —        | discretionary_floor 14,000             |
| `S6.CERT_COSTS.NO_APPEARANCE`     | item 15(a)(i)      | —        | flat 1,200                             |
| `S6.CERT_COSTS.EXTRA_SERVICE`     | item 15(a)(ii)     | attempts | per_unit @250                          |

Modifiers on instruction fees: posture (65/75/85%), `S6.TWO_ADVOCATES` ×2, `S6.SENIOR_COUNSEL` ×1.5, `S6.ADJOURNMENT` add ≤15% per occasion (judge's direction required), `S6.PAYMENT_IN.LATE_ACCEPTANCE` ×0.75, `S6.PAYMENT_IN.NOT_ACCEPTED` ×0.5 (proviso (v)). Rent/possession suits: value = arrears + max(annual rental value, 1/10 capital value) (proviso (iv)).

### Schedule 7 — subordinate courts (Part B = A × 1.5)

| Code                               | Rule          | Basis                | Computation                 | Bands (lower / higher)                                                                                                                                                        |
| ---------------------------------- | ------------- | -------------------- | --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `S7.INSTR`                         | item 1        | sum sued / found due | tiered, lower_higher        | (0–50k] 10,000 / 15,000; (50k–100k] 15,000 / 30,000; (100k–200k] 30,000 / 40,000; (200k–500k] 45,000 / 65,000; (500k–1M] 65,000 / 90,000; (1M–2M] 90,000 / 120,000; >2M +2.5% |
| `S7.INSTR.NO_SUM.UNDEFENDED`       | item 2        | —                    | discretionary_floor 20,000  |
| `S7.INSTR.ELECTION_PETITION`       | item 3        | —                    | discretionary_floor 100,000 |
| `S7.INSTR.DIVORCE.UNDEFENDED`      | item 4(a)(i)  | —                    | flat 10,000                 |
| `S7.INSTR.DIVORCE.DEFENDED`        | item 4(a)(ii) | —                    | flat 18,000                 |
| `S7.APPLICATION`                   | item 5        | —                    | flat 3,000                  |
| `S7.ATTEND.MAGISTRATE`             | item 6        | —                    | flat 1,400                  |
| `S7.HEARING.FIRST_DAY`             | item 7(i)     | —                    | flat 5,000                  |
| `S7.HEARING.SUBSEQUENT_PART`       | item 7(ii)    | parts                | per_unit @2,100             |
| `S7.ADJOURNMENT`                   | item 8        | —                    | flat 2,100                  |
| `S7.ADR.HALF_HOUR` / `S7.ADR.HOUR` | item 9        | —                    | flat 1,000 / 1,400          |
| `S7.SERVICE.LOCAL`                 | item 10(i)    | —                    | flat 1,400                  |
| `S7.AFFIDAVIT`                     | item 11       | —                    | flat 1,000                  |

Lower scale where no defence or other denial of liability filed; higher scale otherwise (Note to item 1). Posture multipliers apply as in Schedule 6.

### Schedules 8 and 9 — Landlord & Tenant and Rent Restriction tribunals

Seed from the Order's tables (fixed fees keyed to relief sought; Sch 8 item (b): non-pecuniary relief ≥2,940 unopposed / ≥23,520 opposed). Confirm whether Part B uplift applies (§14).

### Schedule 10 — probate and administration (Part B = A × 1.5 in contested matters)

| Code                            | Rule           | Basis                     | Computation                                                                                                                          |
| ------------------------------- | -------------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `S10.GRANT.UNCONTESTED`         | item 1(a)      | gross capital value       | tiered: fixed brackets ≤10k, 10k–50k, 50k–200k, 200k–1M (figures to be transcribed from PDF — §14), then 5% of first 1M + 1% over 1M |
| `S10.GRANT.CONTESTED`           | item 1(d)      | —                         | `S10.GRANT.UNCONTESTED` ×2 (floor)                                                                                                   |
| `S10.RESEAL.CONTESTED`          | item 1(b)      | —                         | `S10.GRANT.UNCONTESTED` ×0.8                                                                                                         |
| `S10.CONFIRMATION.UNCONTESTED`  | item 1(c)(i)   | —                         | flat 15,000                                                                                                                          |
| `S10.CONFIRMATION.CONTESTED`    | item 1(c)(ii)  | —                         | discretionary_floor 30,000                                                                                                           |
| `S10.CAVEAT`                    | item 1(e)      | —                         | flat 10,000                                                                                                                          |
| `S10.OBJECTION`                 | item 1(f)      | —                         | discretionary_floor 10,000                                                                                                           |
| `S10.INVENTORY`                 | item 1(g)      | net estate, entries       | 2,103 per 20,000 × entries, floor 3,000                                                                                              |
| `S10.WILL`                      | item 2(c)      | —                         | discretionary_floor 30,000                                                                                                           |
| `S10.ADMIN.COMMISSION.CAPITAL`  | item 7(b)(i)   | net capital p.a.          | cap 2.5% p.a.                                                                                                                        |
| `S10.ADMIN.COMMISSION.INCOME`   | item 7(b)(ii)  | income                    | cap 3%                                                                                                                               |
| `S10.ADMIN.COMMISSION.REALISED` | item 7(b)(iii) | realised/invested capital | cap 1.5%                                                                                                                             |

### Schedule 11 — other tribunals (Part B = A × 1.5)

| Code                          | Rule           | Basis | Computation                                             | Bands                                                                                                                                  |
| ----------------------------- | -------------- | ----- | ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `S11.INSTR`                   | item 8(a)      | value | tiered, lower_higher (lower = 50% of higher, item 8(b)) | (0–50k] 17,640; (50k–100k] 23,520; (100k–200k] 35,280; (200k–500k] 58,800; (500k–1M] 100,000; (1M–20M] 1%; (20M–250M] 0.5%; >250M 0.1% |
| `S11.INSTR.NO_VALUE`          | item 9         | —     | discretionary_floor 35,280                              |
| `S11.ATTEND.TRIBUNAL`         | item 10(a),(b) | —     | flat 500                                                |
| `S11.HEARING.SUBSEQUENT_DAY`  | item 10(c)(i)  | days  | per_unit @4,000                                         |
| `S11.HEARING.SUBSEQUENT_PART` | item 10(c)(ii) | parts | per_unit @2,100                                         |
| `S11.ADJOURNMENT`             | item 10(d)     | —     | flat 1,200                                              |

Lower scale where disposed of ex parte, by consent, or on a preliminary point of law (item 4).

### Schedule 12 — patents, utility models, industrial designs

`S12.PATENT`, `S12.DESIGN`, `S12.UTILITY_MODEL`, `S12.NATIONAL_PHASE`: flat 42,000 each (item 1). Remaining items (amendments, oppositions, renewals) seeded from the table.

### Part I general paragraphs as modifiers

| Code                          | Rule    | Shape                                                        |
| ----------------------------- | ------- | ------------------------------------------------------------ |
| `P4.EXCEPTIONAL_DISPATCH`     | para 4  | add (justified amount, advocate-and-client only)             |
| `P5.SPECIAL_FEE`              | para 5  | add (justified amount; factors (a)–(d) captured)             |
| `P7.INTEREST`                 | para 7  | 14% p.a., §4.11                                              |
| `P22.ELECTION`                | para 22 | switches item to Schedule 5; floor = original schedule's fee |
| `P58.RESTRICT_TO_SUBORDINATE` | para 58 | replaces `S6.*` instruction with `S7.*`                      |
| `P77.ONE_SIXTH`               | para 77 | risk flag, §4.12                                             |

---

## 6. Interpretations register

These are places where the published text is ambiguous or self-contradictory. Each is stored in `aro_interpretations` and cited on any bill that depends on it. Decisions below are the recommended defaults; the firm's admin can adopt a different reading per firm, and the bill will cite that instead.

| ID  | Rule                          | Problem                                                                                                                                                               | Adopted reading                                                                                                                                                                          | Rationale                                                                                                                                                                    |
| --- | ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| I-1 | Sch 6 & 7 item 1(a)–(c)       | "(a) … no appearance entered … 65% of the fees chargeable under item 1(a)" is self-referential as published.                                                          | (a)/(b)/(c) are percentage multipliers on the tables that follow: 65% applies to the undefended table; 75% and 85% apply to the defended table.                                          | The tables themselves are labelled (a) undefended and (b) defended; the multipliers only make sense as reductions of those. Matches prevailing practice.                     |
| I-2 | Sch 2 para 1(b),(c) vs Note 4 | (b) and (c) say "the fee prescribed in (a) **plus**"; Note 4 says "It shall not be cumulative" and "percentage rate of the band within which the consideration lies". | Cumulative (tiered).                                                                                                                                                                     | The non-cumulative reading gives a rent of 1,000,000 a fee of 30,000 (3%) — lower than a rent of 500,000 (75,000). A scale that falls as the basis rises cannot be intended. |
| I-3 | Sch 2 para 1(c)               | "(c) … the fee prescribed in **(a)** plus 1% on the excess" skips (b).                                                                                                | Fee at 3,000,000 under (b) plus 1% of the excess over 3,000,000.                                                                                                                         | Literal reading produces a cliff (4,000,000 → 85,000 vs 3,000,000 → 150,000).                                                                                                |
| I-4 | Sch 1 rule 26(1)              | Rules 27–41 "applied in sequence" — order of stacked reductions.                                                                                                      | Modifiers execute in the paragraph order of the Order (`sort_order`), reductions before floors, floors before caps.                                                                      | Follows the text; produces the smaller of two reductions only where the Order says so (printed-form cap).                                                                    |
| I-5 | Para 7 interest               | Simple or compound; day count.                                                                                                                                        | Simple, actual/365.                                                                                                                                                                      | Order silent; Kenyan courts apply simple interest absent agreement.                                                                                                          |
| I-6 | Sch 6 para 2 getting-up       | "not less than one-third of the instruction fee allowed on taxation" — party-and-party or advocate-and-client base.                                                   | One-third of the party-and-party instruction fee, then the Part B 50% uplift applies to the total.                                                                                       | Part B says "the fees prescribed in A above, increased by 50%" — the uplift is applied once, to the whole.                                                                   |
| I-7 | Rounding                      | Order figures are whole shillings; percentages produce cents.                                                                                                         | Compute in cents; round each bill line HALF_UP to the shilling; totals are sums of rounded lines.                                                                                        | Avoids penny drift between preview and bill. Firm-configurable.                                                                                                              |
| I-8 | Sch 5 Part I hourly vs para 3 | Does an agreed hourly rate escape the schedule minimum?                                                                                                               | No. Hourly billing is permitted, but the total charged for a matter to which another schedule applies may not fall below that schedule's fee unless para 22 election is made in writing. | Para 3 is absolute; para 22(2) confirms election cannot reduce below scale.                                                                                                  |

---

## 7. Bill lifecycle

```text
draft ──issue──▶ issued ──deliver──▶ delivered ──(1 month, no dispute)──▶ deemed_agreed
                                        │                                      │
                                        ├──dispute──▶ disputed ──file──▶ taxation_filed ──▶ taxed
                                        │
                                        └──payment(s)──▶ partially_paid ──▶ paid
issued/delivered ──credit note──▶ voided (original) + new bill
```

**Issue** (`BillIssuer`): recompute every fee line against the published ARO version; refuse if shortfall > 0 and no exemption/election; assign number; write snapshot; lock; render PDF; if `type = tax_invoice` and firm is eTIMS-integrated, enqueue `SubmitInvoiceToEtims` — the PDF is re-rendered with CUIN and QR once accepted (§9).

**Deliver**: record `delivered_at`, method, proof. Sets `deemed_agreed_at = delivered_at + 1 month` (para 6). Starts the para 7 interest eligibility clock at the same date.

**Interest**: nightly job accrues for bills with `interest_claimed_at` set and no `paid_in_full_at` (§4.11). Interest lines print on statements, not on the original bill.

**Dispute / taxation**: `taxations` row; `objection_deadline_at = certificate_at + 14 days` (para 11(1)); after certificate, fill `bill_lines.taxed_off_cents`, compute para 77 flag.

**Documents rendered from one snapshot:**

| Type          | Audience                                    | Notes                                                                                                                                     |
| ------------- | ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Fee note      | client, non-contentious or agreed           | what the prototype produces today, plus VAT block                                                                                         |
| Bill of costs | court / opposing party / client at taxation | para 69 five columns; disbursements at foot; taxation attendance blank; provenance under each item                                        |
| Tax invoice   | client, VAT-registered firm                 | KRA-required fields: firm PIN, client PIN, serial number, date, description, taxable value, VAT, total, **CUIN + QR + SCU ID from eTIMS** |
| Credit note   | client                                      | references original; submitted to eTIMS as refund type                                                                                    |
| Statement     | client                                      | bills, payments, interest to date                                                                                                         |

---

## 8. VAT and withholding tax

**VAT (VAT Act 2013).** Legal services are standard-rated at 16% (tax type `B`). Rules the assembler applies:

| Line kind                                                                                                                                                                              | VAT treatment                    | eTIMS `taxTyCd`                                                                            |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- | ------------------------------------------------------------------------------------------ |
| `fee`, `time` (professional fees, including the Part B uplift)                                                                                                                         | 16%                              | `B`                                                                                        |
| `recharge` — costs the firm incurs as principal and passes on (photocopying, courier, printing, travel)                                                                                | 16% (part of the taxable supply) | `B`                                                                                        |
| `disbursement` — paid as agent for the client, on the client's behalf, receipted to the client (stamp duty, court fees, registration fees, search fees, valuer's/auctioneer's charges) | outside the scope                | `D` (non-VAT) — or exclude from the eTIMS invoice and show on a separate disbursement note |
| Exempt clients (e.g. certain diplomatic/exempt bodies with a KRA exemption)                                                                                                            | 0% with exemption reference      | `A`                                                                                        |

Para 19 of the Order defines what is a disbursement for remuneration purposes; that list maps onto the "agent" column above. The prototype's `is_disbursement` boolean collapses recharges into disbursements — the `kind` enum fixes this.

**Withholding tax (Income Tax Act s.35).** Resident clients that are withholding agents (companies, government, NGOs) deduct 5% WHT on professional fees paid to a resident advocate (threshold: aggregate payments above KES 24,000 per month). The bill shows an informational block: "Where you are a withholding agent, withhold KES X (5% of professional fees) and remit to KRA; pay the balance." `payments` records the withheld amount against a WHT certificate reference so the receivable reconciles.

**Invoice arithmetic (worked):** fees 100,000; recharges 5,000; agent disbursements 20,000 → taxable 105,000 → VAT 16,800 → total 141,800; WHT expected (if agent) 5,250 on 105,000; net cash expected 136,550.

---

## 9. eTIMS integration

### 9.1 What eTIMS requires of a law firm

Since 1 January 2024, expenses are only tax-deductible for the payer if supported by an eTIMS-generated invoice, so corporate clients will demand eTIMS invoices from their advocates. VAT-registered firms must transmit every tax invoice to KRA in real time and print the control-unit invoice number (CUIN), the SCU/device ID and a QR code on the invoice.

Integration routes:

| Route                                                           | Who it suits                      | Our support                                                                                                       |
| --------------------------------------------------------------- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| eTIMS Online Portal / eTIMS Lite                                | firms not integrated yet          | bill status `awaiting_cuin`; user keys the invoice into the portal and pastes the CUIN back; we re-render the PDF |
| **OSCU** (Online Sales Control Unit, KRA-hosted, always online) | a SaaS that is always online — us | primary route                                                                                                     |
| VSCU (Virtual SCU, taxpayer-hosted, offline buffering, bulk)    | large ERPs with offline needs     | not needed                                                                                                        |

Onboarding: KRA runs a development → testing → vetting → certification process. Sandbox at `https://etims-api-sbx.kra.go.ke/etims-api/` (portal `etims-sbx.kra.go.ke`); production `https://etims-api.kra.go.ke/etims-api/`. Justify should apply to be listed as a KRA-verified third-party integrator so firms onboard by registering a device serial against Justify rather than each firm self-certifying. Until then, each firm self-integrates using our software (the taxpayer applies; we supply the system). Specification: KRA OSCU Specification v2.0 (`kra.go.ke/images/publications/OSCU_Specification_Document_v2.0.pdf`). Validate every field name below against that PDF before coding; KRA revises it.

### 9.2 Per-firm device lifecycle

1. Firm registers on the eTIMS portal, chooses OSCU, gets a **device serial number** for a branch (`bhfId`, `00` for head office).
2. Admin enters PIN, branch, serial in `firm_tax_profiles`.
3. `DeviceInitializer` calls `selectInitOsdcInfo` with `{tin, bhfId, dvcSrlNo}` → response carries `cmcKey` (communication key) and `sdcId`. Store `cmcKey` encrypted. It is the credential for every later call (headers `tin`, `bhfId`, `cmcKey`).
4. Pull code lists (`selectCodeList`, `selectItemClsList`) into `etims_code_lists`.
5. Register our service items with `saveItem`: at minimum `LEGAL-FEES` (taxTyCd `B`, itemTyCd `3` service, itemClsCd from the UNSPSC-derived class list under 8012xxxx "legal services" — pick from the pulled list) and `DISBURSEMENT` (taxTyCd `D`).
6. Firm runs a sandbox invoice end-to-end; admin flips `etims_environment = production` and records `etims_certified_at`.

### 9.3 Invoice submission

`SubmitInvoiceToEtims` job (Horizon, queue `etims`, unique per bill, exponential backoff, max 10 attempts, then `rejected` with alert):

1. **Reserve `invcNo`** in a DB transaction: `UPDATE firm_tax_profiles SET etims_last_invoice_no = etims_last_invoice_no + 1 WHERE firm_id = ? RETURNING etims_last_invoice_no`. KRA requires strictly sequential invoice numbers per branch with no gaps; a rejected submission must be retried with the **same** `invcNo`, never a new one. Persist the number on `etims_submissions` before calling out.
2. Build payload (`saveTrnsSalesOsdc`):

```json
{
    "tin": "P051234567X",
    "bhfId": "00",
    "invcNo": 1042,
    "orgInvcNo": 0,
    "custTin": "P000111222Y",
    "custNm": "Acme Holdings Ltd",
    "salesTyCd": "N",
    "rcptTyCd": "S",
    "pmtTyCd": "02",
    "salesSttsCd": "02",
    "cfmDt": "20260915103000",
    "salesDt": "20260915",
    "stockRlsDt": "20260915103000",
    "totItemCnt": 2,
    "taxblAmtA": 0,
    "taxblAmtB": 105000,
    "taxblAmtC": 0,
    "taxblAmtD": 20000,
    "taxblAmtE": 0,
    "taxRtA": 0,
    "taxRtB": 16,
    "taxRtC": 0,
    "taxRtD": 0,
    "taxRtE": 0,
    "taxAmtA": 0,
    "taxAmtB": 16800,
    "taxAmtC": 0,
    "taxAmtD": 0,
    "taxAmtE": 0,
    "totTaxblAmt": 125000,
    "totTaxAmt": 16800,
    "totAmt": 141800,
    "prchrAcptcYn": "N",
    "remark": "Fee note JF/2026/0042 — Matter HCCC 123/2026",
    "regrId": "justify",
    "regrNm": "Justify",
    "modrId": "justify",
    "modrNm": "Justify",
    "receipt": {
        "custTin": "P000111222Y",
        "rcptPbctDt": "20260915103000",
        "trdeNm": "Kimondo & Co. Advocates",
        "adrs": "Nairobi",
        "topMsg": "Advocates (Remuneration) Order, Sch 6",
        "btmMsg": "Thank you",
        "prchrAcptcYn": "N"
    },
    "itemList": [
        {
            "itemSeq": 1,
            "itemCd": "LEGAL-FEES",
            "itemClsCd": "80121600",
            "itemNm": "Professional fees — instructions to defend HCCC 123/2026 (Sch 6 item 1(b), Part B)",
            "pkgUnitCd": "NT",
            "pkg": 1,
            "qtyUnitCd": "U",
            "qty": 1,
            "prc": 105000,
            "splyAmt": 105000,
            "dcRt": 0,
            "dcAmt": 0,
            "taxTyCd": "B",
            "taxblAmt": 105000,
            "taxAmt": 16800,
            "totAmt": 121800
        },
        {
            "itemSeq": 2,
            "itemCd": "DISBURSEMENT",
            "itemClsCd": "80121600",
            "itemNm": "Disbursements paid as agent — court filing fees (receipts attached)",
            "pkgUnitCd": "NT",
            "pkg": 1,
            "qtyUnitCd": "U",
            "qty": 1,
            "prc": 20000,
            "splyAmt": 20000,
            "dcRt": 0,
            "dcAmt": 0,
            "taxTyCd": "D",
            "taxblAmt": 20000,
            "taxAmt": 0,
            "totAmt": 20000
        }
    ]
}
```

`pmtTyCd` `02` = credit (invoice issued, payment later), which is the normal case for a fee note. `custTin` is optional for individuals without a PIN but required for corporate clients that will claim the expense.

3. POST with headers `tin`, `bhfId`, `cmcKey`. Success is `resultCd == "000"`. Store the `data` block: `curRcptNo`, `totRcptNo`, `intrlData`, `rcptSign`, `sdcDateTime`/`vsdcRcptPbctDate`, `sdcId`, `mrcNo`, `qrCodeUrl`.
4. Build the CUIN as `{sdcId}/{curRcptNo}` (KRA's presentation), generate the QR from `qrCodeUrl`, re-render the PDF, mark `etims_submissions.status = accepted`, emit `bill_events.etims_submitted`.
5. On `resultCd != "000"`: store `resultMsg`, decide retryable (network/5xx/`resultCd` in the transient list) vs. terminal (validation). Terminal errors notify the firm's accounts role with the message and keep the bill `issued` with `etims_status = rejected` — the bill is still a valid fee note under the Order; it is not yet a valid tax invoice.

### 9.4 Credit notes

A credit note is a sale with `rcptTyCd = "R"`, `orgInvcNo = <original invcNo>`, negative-free amounts equal to the credited portion, and a new sequential `invcNo`. The original bill is `voided` or reduced; a replacement bill is issued if needed. Never edit an accepted invoice.

### 9.5 PHP shape

```php
final class EtimsClient
{
    public function __construct(private FirmTaxProfile $profile, private HttpFactory $http) {}

    public function post(string $endpoint, array $payload): EtimsResponse
    {
        $res = $this->http
            ->baseUrl($this->profile->baseUrl())
            ->withHeaders([
                'tin'    => $this->profile->kra_pin,
                'bhfId'  => $this->profile->etims_branch_id,
                'cmcKey' => $this->profile->etims_cmc_key, // decrypted cast
            ])
            ->timeout(20)
            ->retry(0)
            ->post($endpoint, $payload);

        return EtimsResponse::fromHttp($res); // resultCd, resultMsg, data
    }
}

final class SubmitInvoiceToEtims implements ShouldQueue, ShouldBeUnique
{
    public int $tries = 10;
    public function backoff(): array { return [30, 60, 120, 300, 900, 1800, 3600, 3600, 3600, 3600]; }
    public function uniqueId(): string { return $this->billId; }

    public function handle(EtimsClient $client, InvoicePayloadBuilder $builder): void
    {
        $bill = Bill::lockForUpdate()->findOrFail($this->billId);
        $submission = EtimsSubmission::firstOrCreate(['bill_id' => $bill->id, 'kind' => 'sale']);

        if ($submission->invoice_no === null) {
            $submission->invoice_no = $bill->firm->taxProfile->reserveInvoiceNumber(); // atomic increment
            $submission->save();
        }

        $payload = $builder->build($bill, $submission->invoice_no);
        $submission->update(['request_payload' => $payload, 'attempts' => $submission->attempts + 1, 'status' => 'sent']);

        $res = $client->post('saveTrnsSalesOsdc', $payload);
        $submission->update(['response_payload' => $res->raw]);

        if (! $res->ok()) {
            $submission->update(['status' => $res->transient() ? 'retry' : 'rejected', 'last_error' => $res->message]);
            if ($res->transient()) { $this->release($this->backoff()[$this->attempts() - 1] ?? 3600); }
            return;
        }

        $submission->update([
            'status'            => 'accepted',
            'receipt_no'        => $res->data['curRcptNo'],
            'internal_data'     => $res->data['intrlData'],
            'receipt_signature' => $res->data['rcptSign'],
            'sdc_datetime'      => $res->data['sdcDateTime'] ?? $res->data['vsdcRcptPbctDate'],
            'sdc_id'            => $res->data['sdcId'],
            'mrc_no'            => $res->data['mrcNo'] ?? null,
            'qr_code_url'       => $res->data['qrCodeUrl'] ?? null,
            'accepted_at'       => now(),
        ]);

        RenderBillPdf::dispatch($bill->id); // re-render with CUIN + QR
    }
}
```

### 9.6 Operational rules

- One eTIMS branch per firm office; multi-office firms get multiple `firm_tax_profiles` rows keyed by branch.
- Never submit drafts. Never submit twice: `ShouldBeUnique` + the `invcNo` reservation make the job idempotent.
- Keep `etims_submissions.request_payload`/`response_payload` forever — KRA audits reference them.
- Daily reconciliation job: `selectTrnsPurchaseSalesList` (or the equivalent sales-list query in the v2.0 spec) for the last 24 hours vs. our accepted submissions; alert on mismatch.
- Sandbox tests run in CI nightly against `etims-api-sbx` with a dedicated test PIN so a KRA field change breaks a test before it breaks a customer.

---

## 10. API surface for the TanStack client

All routes under `/api/v1`, Sanctum-authenticated, firm-scoped. Responses are `spatie/laravel-data` resources.

| Method                  | Route                                                               | Purpose                                                                                                                                                                 |
| ----------------------- | ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `POST`                  | `/auth/login` `/auth/refresh` `/auth/logout`                        | bearer access token (15 min) + rotating refresh token; `logout` revokes both                                                                                            |
| `GET`                   | `/auth/me`                                                          | user, current firm, role, tax-profile summary — loaded once by the client's route guard                                                                                 |
| `GET`                   | `/dashboard/summary`                                                | unbilled WIP, shortfall count, bills by status, receivables ageing, interest accruing, eTIMS rejected                                                                   |
| `GET`                   | `/aro/versions`                                                     | published versions                                                                                                                                                      |
| `GET`                   | `/aro/items?schedule=&q=`                                           | searchable catalogue (replaces `fee_schedule_items` select)                                                                                                             |
| `POST`                  | `/aro/preview`                                                      | `{item_code, basis_cents?, quantity?, scale?, posture?, modifier_codes[], certificates{}, cost_basis}` → `Computed` (amount, steps, provenance). Powers live previews.  |
| `GET/PUT`               | `/matters/{id}/classification`                                      |                                                                                                                                                                         |
| `GET/POST`              | `/matters/{id}/fee-agreements`                                      | including para 22 election with document upload                                                                                                                         |
| `GET/POST/PATCH/DELETE` | `/matters/{id}/chargeable-items`                                    | server recomputes `computed_minimum` on every write; rejects `entered < minimum` for non-exempt heads with `422 {code: "below_statutory_minimum", minimum, provenance}` |
| `GET`                   | `/matters/{id}/shortfall`                                           | per-item and total (§4.10)                                                                                                                                              |
| `POST`                  | `/matters/{id}/bills`                                               | assemble a draft `{type, cost_basis, item_ids[]}`                                                                                                                       |
| `POST`                  | `/bills/{id}/issue` `/deliver` `/dispute` `/claim-interest` `/void` | lifecycle                                                                                                                                                               |
| `GET`                   | `/bills/{id}/pdf`                                                   | signed URL                                                                                                                                                              |
| `POST`                  | `/bills/{id}/payments`                                              |                                                                                                                                                                         |
| `POST`                  | `/bills/{id}/credit-notes`                                          |                                                                                                                                                                         |
| `GET`                   | `/bills/{id}/etims`                                                 | submission status, CUIN                                                                                                                                                 |
| `POST`                  | `/bills/{id}/etims/retry`                                           | accounts role only                                                                                                                                                      |
| `GET/POST`              | `/taxations`                                                        |                                                                                                                                                                         |
| `GET`                   | `/reports/wip`, `/reports/receivables`, `/reports/interest`         |                                                                                                                                                                         |

Admin (Inertia, `/admin`): ARO versions and items with a two-person review before `published`; interpretations register; eTIMS device onboarding; firm impersonation for support (logged).

---

## 11. Tests

Pest. Three layers: engine golden tests (pure PHP, no DB, run in milliseconds), service/HTTP tests (Postgres, seeded ARO), and external contract tests (eTIMS sandbox, nightly).

### 11.1 Golden cases from the Order

Each row becomes one `it(...)` in `tests/Unit/Aro/<Schedule>Test.php`. Amounts in KES; expected values are exact before line rounding unless marked "→ rounded".

**Schedule 1, First Scale (`S1.SALE`)**

| Basis                                 | Expected             | Rule                              |
| ------------------------------------- | -------------------- | --------------------------------- |
| 1,000,000                             | 35,000               | 2% = 20,000 < floor 35,000        |
| 1,750,000                             | 35,000               | 2% exactly equals floor           |
| 5,000,000                             | 100,000              | top of band 1                     |
| 10,000,000                            | 175,000              | 100,000 + 1.5% × 5,000,000        |
| 100,000,000                           | 1,525,000            | 100,000 + 1.5% × 95,000,000       |
| 250,000,000                           | 3,400,000            | 1,525,000 + 1.25% × 150,000,000   |
| 1,000,000,000                         | 10,900,000           | 3,400,000 + 1% × 750,000,000      |
| 2,000,000,000                         | 11,900,000           | 10,900,000 + 0.1% × 1,000,000,000 |
| 10,000,000 + `S1.VENDOR_NO_AGREEMENT` | 116,666.67 → 116,667 | para 18(a) proviso, −⅓            |

**Schedule 1, Second Scale (`S1.SECURITY.GRANTEE` and modifiers)**

| Basis / chain                                  | Expected                      | Rule                                                                                                                                                                     |
| ---------------------------------------------- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1,000,000                                      | 28,000                        | 2% = 20,000 < floor 28,000                                                                                                                                               |
| 2,500,000                                      | 50,000                        |                                                                                                                                                                          |
| 5,000,000                                      | 93,750                        | 50,000 + 1.75% × 2,500,000                                                                                                                                               |
| 10,000,000                                     | 143,750                       | 93,750 + 1% × 5,000,000                                                                                                                                                  |
| 100,000,000                                    | 1,043,750                     |                                                                                                                                                                          |
| 250,000,000                                    | 2,168,750                     | 1,043,750 + 0.75% × 150,000,000                                                                                                                                          |
| 10,000,000, `S1.GRANTOR`                       | 71,875                        | (d) 50%                                                                                                                                                                  |
| 10,000,000, `S1.DISCHARGE.UNDERTAKING`         | 35,937.50 → 35,938            | (c)(i) 25%, floor 15,000 not reached                                                                                                                                     |
| 1,000,000, `S1.DISCHARGE.NO_UNDERTAKING`       | 10,000                        | (c)(ii) 15% × 28,000 = 4,200 → floor                                                                                                                                     |
| 1,000,000, `S1.GRANTOR.DISCHARGE`              | 15,000                        | (e) 25% × 28,000 = 7,000 → floor                                                                                                                                         |
| 1,000,000, `S1.EQUITABLE`                      | 14,000                        | Note 1(a) 50% × 28,000                                                                                                                                                   |
| 100,000,000, `S1.EQUITABLE.DISCHARGE`          | 42,000                        | Note 1(b) 15% × 1,043,750 = 156,562.50 → cap                                                                                                                             |
| 1,000,000, `S1.EQUITABLE.DISCHARGE`            | 10,000                        | 15% × 28,000 = 4,200 → floor                                                                                                                                             |
| 10,000,000, `S1.BOTH_SIDES`                    | 179,687.50 → 179,688          | Note 3: 143,750 + ½ × 71,875                                                                                                                                             |
| 10,000,000, three securities same grantee      | 194,062.50 → 194,063          | Note 5: 143,750 + 25% + 10%                                                                                                                                              |
| 10,000,000, three properties                   | 165,312.50 → 165,313          | Note 6: + 10% + 5%                                                                                                                                                       |
| 10,000,000, three grantors                     | 158,125 total; 52,708.33 each | Note 7: + 5% + 5%, divided equally                                                                                                                                       |
| 10,000,000, `S1.PRINTED_FORM` alone            | 95,833.33 → 95,833            | −⅓; above the 50% floor of 71,875                                                                                                                                        |
| 10,000,000, `S1.EQUITABLE` + `S1.PRINTED_FORM` | 71,875                        | 143,750 × 0.5 × ⅔ = 47,916.67, but combined reduction may not exceed one-half of the scale fee → floored at 71,875 (building-society rule; verify paragraph number, §14) |

**Schedule 1, Third Scale (`S1.NEGOTIATION`)**

| Basis     | Expected | Rule                                              |
| --------- | -------- | ------------------------------------------------- |
| 200,000   | 11,200   | 100 units × 112                                   |
| 201,000   | 11,226   | + half unit × 52 (remainder 1,000 ≤ 1,000 = half) |
| 202,000   | 11,252   | + 1 unit × 52                                     |
| 1,000,000 | 27,600   | 11,200 + 200 × 52 + 200 × 30                      |

**Schedule 2 (`S2.LEASE.PREPARE`, adopted reading I-2/I-3)**

| Annual rent                                  | Expected                   | Rule                                                                             |
| -------------------------------------------- | -------------------------- | -------------------------------------------------------------------------------- |
| 100,000                                      | 20,000                     | 15% = 15,000 → floor                                                             |
| 300,000                                      | 45,000                     |                                                                                  |
| 500,000                                      | 75,000                     |                                                                                  |
| 1,000,000                                    | 90,000                     | 75,000 + 3% × 500,000                                                            |
| 3,000,000                                    | 150,000                    | 75,000 + 3% × 2,500,000                                                          |
| 4,000,000                                    | 160,000                    | 150,000 + 1% × 1,000,000 (I-3)                                                   |
| 1,000,000, `S2.LEASE.PERUSE`                 | 45,000                     | para 2, 50%                                                                      |
| 1,000,000, `S2.PRINTED_FORM`                 | 60,000                     | −⅓                                                                               |
| rent 1,000,000 + premium 10,000,000          | 90,000 + 175,000 = 265,000 | para 48                                                                          |
| _Rejected reading (bracket_rate):_ 1,000,000 | 30,000                     | asserted **not** produced by `S2.LEASE.PREPARE` under the adopted interpretation |

**Schedule 3** — `S3.INCORPORATION`: minimum 60,000; entering 50,000 is rejected; entering 80,000 with justification is accepted and marked discretionary.

**Schedule 5 (`S5.DEBT_COLLECTION` and units)**

| Input                                   | Expected                 | Rule                               |
| --------------------------------------- | ------------------------ | ---------------------------------- |
| debt 80,000                             | 8,000                    | 10%                                |
| debt 300,000                            | 20,000                   | 10,000 + 5% × 200,000              |
| debt 1,000,000                          | 65,000                   | 50,000 + 3% × 500,000              |
| debt 5,000,000                          | 145,000                  | 100,000 + 1.5% × 3,000,000         |
| debt 80,000, `S5.DEBT.ONE_LETTER`       | 4,000                    | half                               |
| debt 5,000, `S5.DEBT.ONE_LETTER`        | 1,000                    | 250 → floor 1,000                  |
| `S5.ATTENDANCE`, 40 minutes             | 3,000                    | 3 × 15-minute units (part thereof) |
| `S5.TIME_ENGAGED`, 2 hours              | 56,000                   | 8 × 7,000                          |
| `S5.JOURNEY.DAY`, 7 hours               | 15,000                   |                                    |
| `S5.JOURNEY.HOUR`, 3 hours              | 7,500                    |                                    |
| `S5.OPINION` entered 20,000             | rejected; minimum 35,000 |                                    |
| `S5.CHATTELS.LARGE`, secured 10,000,000 | 71,875                   | ½ × 143,750                        |

**Schedule 6 (`S6.INSTR.*`, getting-up, uplift, certificates)**

| Input                                                                          | Expected                  | Rule                                          |
| ------------------------------------------------------------------------------ | ------------------------- | --------------------------------------------- |
| undefended, 400,000                                                            | 45,000                    | 1(a) bracket                                  |
| undefended, 600,000                                                            | 65,000                    |                                               |
| undefended, 900,000                                                            | 75,000                    |                                               |
| undefended, 5,000,000                                                          | 145,000                   | 75,000 + 1.75% × 4,000,000                    |
| undefended, 20,000,000                                                         | 407,500                   |                                               |
| undefended, 50,000,000                                                         | 857,500                   | 407,500 + 1.5% × 30,000,000                   |
| defended, 400,000                                                              | 75,000                    | 1(b)                                          |
| defended, 5,000,000                                                            | 200,000                   | 120,000 + 2% × 4,000,000                      |
| defended, 20,000,000                                                           | 500,000                   |                                               |
| defended, 50,000,000                                                           | 950,000                   |                                               |
| defended 5,000,000, posture settled_pre_hearing                                | 170,000                   | 85% (I-1)                                     |
| defended 5,000,000, posture summary                                            | 150,000                   | 75%                                           |
| undefended 5,000,000, posture no_appearance                                    | 94,250                    | 65% of 145,000                                |
| defended 5,000,000, getting-up minimum                                         | 66,666.67 → 66,667        | para 2, ⅓                                     |
| defended 5,000,000, advocate-and-client                                        | 300,000                   | Part B ×1.5                                   |
| defended 5,000,000 + getting-up, advocate-and-client                           | 400,000                   | (200,000 + 66,666.67) × 1.5 (I-6)             |
| defended 5,000,000, senior counsel certificate                                 | 300,000 (party-and-party) | proviso (iii) +50%                            |
| defended 5,000,000, two advocates certificate                                  | 400,000                   | proviso (ii) ×2                               |
| adjournment cap on 200,000                                                     | 30,000                    | proviso (ii) 15%                              |
| `S6.DRAWING.PLEADING`, 3 folios                                                | 1,100                     | ≤4 folios                                     |
| `S6.DRAWING.PLEADING`, 6 folios                                                | 1,400                     | 1,100 + 2 × 150                               |
| `S6.ATTEND.COURT.DAY`, lower / higher                                          | 10,000 / 15,000           | item 7(d)                                     |
| `S6.SERVICE.PER_KM`, 10 km                                                     | 1,400 + 7 × 35 = 1,645    | item 9                                        |
| possession suit: arrears 200,000, annual rent 600,000, capital value 5,000,000 | basis 800,000             | proviso (iv): arrears + max(600,000; 500,000) |

**Schedule 7 (`S7.INSTR`)**

| Input                                       | Expected              | Rule                       |
| ------------------------------------------- | --------------------- | -------------------------- |
| 40,000, lower / higher                      | 10,000 / 15,000       |                            |
| 150,000, lower / higher                     | 30,000 / 40,000       |                            |
| 1,500,000, lower / higher                   | 90,000 / 120,000      |                            |
| 3,000,000, lower                            | 115,000               | 90,000 + 2.5% × 1,000,000  |
| 3,000,000, higher                           | 145,000               | 120,000 + 2.5% × 1,000,000 |
| 3,000,000, higher, advocate-and-client      | 217,500               | Part B                     |
| `S7.HEARING.FIRST_DAY` + 2 subsequent parts | 5,000 + 4,200 = 9,200 | item 7                     |

**Schedule 10 (`S10.*`)**

| Input                                     | Expected                        | Rule                            |
| ----------------------------------------- | ------------------------------- | ------------------------------- |
| uncontested grant, gross estate 3,000,000 | 70,000                          | 5% × 1,000,000 + 1% × 2,000,000 |
| uncontested grant, 10,000,000             | 140,000                         |                                 |
| contested grant, 3,000,000                | ≥140,000                        | item 1(d) twice                 |
| contested re-sealing, 3,000,000           | 56,000                          | item 1(b) four-fifths           |
| inventory: net estate 400,000, 5 entries  | 52,575                          | 2,103 × 20 × 5                  |
| inventory: net estate 20,000, 1 entry     | 3,000                           | 2,103 → floor 3,000             |
| brackets ≤1,000,000                       | **pending transcription** (§14) |                                 |

**Schedule 11 (`S11.INSTR`)**

| Input                            | Expected       | Rule                          |
| -------------------------------- | -------------- | ----------------------------- |
| 30,000, higher / lower           | 17,640 / 8,820 | item 8(a),(b)                 |
| 5,000,000                        | 140,000        | 100,000 + 1% × 4,000,000      |
| 100,000,000                      | 690,000        | 290,000 + 0.5% × 80,000,000   |
| 300,000,000                      | 1,490,000      | 1,440,000 + 0.1% × 50,000,000 |
| 100,000,000, advocate-and-client | 1,035,000      | Part B                        |

**Schedule 12** — `S12.PATENT` ×3 patents: 126,000.

**Part I paragraphs**

| Input                                                                          | Expected                                                                                               | Rule                         |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ | ---------------------------- |
| `FolioCounter::folios('')`                                                     | 0                                                                                                      | para 17                      |
| 100 words                                                                      | 1                                                                                                      |                              |
| 101 words                                                                      | 2                                                                                                      | any part = one folio         |
| 250 words                                                                      | 3                                                                                                      |                              |
| `"Kshs. 25,564"`                                                               | 1 word                                                                                                 | para 17 example              |
| interest: 1,000,000 delivered 2026-01-01, claimed 2026-02-15, as-of 2026-03-03 | 11,506.85                                                                                              | 14% × 30/365 from 2026-02-01 |
| interest: same, claimed 2026-02-15, paid in full 2026-02-10                    | 0                                                                                                      | claim after full payment     |
| interest: same, never claimed                                                  | 0                                                                                                      |                              |
| interest: as-of 2026-01-20                                                     | 0                                                                                                      | before one month             |
| deemed agreement: delivered 2026-03-15                                         | 2026-04-15                                                                                             | para 6                       |
| deemed agreement: delivered 2026-01-31                                         | 2026-02-28                                                                                             | month-end clamp              |
| objection deadline: certificate 2026-05-01                                     | 2026-05-15                                                                                             | para 11(1) 14 days           |
| one-sixth: claimed 600,000 ex court fees, taxed off 120,000                    | breached (20%)                                                                                         | para 77                      |
| one-sixth: taxed off 100,000                                                   | not breached (16.67%, not "more than")                                                                 |                              |
| para 22 election without written notice                                        | bill issue rejected `422 election_not_communicated`                                                    |                              |
| para 22 election, elected Schedule 5 total 150,000 vs Sch 1 scale 175,000      | shortfall 25,000                                                                                       | para 22(2)                   |
| para 58: High Court matter, value 800,000                                      | instruction fee computed under `S7.INSTR` higher = 90,000, not `S6` 120,000, when restriction flag set |                              |

**VAT / WHT**

| Input                                               | Expected                                   |
| --------------------------------------------------- | ------------------------------------------ |
| fees 100,000, recharges 5,000, disbursements 20,000 | taxable 105,000; VAT 16,800; total 141,800 |
| client is withholding agent                         | wht_expected 5,250                         |
| fees 20,000 (below 24,000 monthly threshold), agent | wht_expected 0                             |
| exempt client with reference                        | VAT 0, tax type `A`, reference printed     |

### 11.2 Invariants (property tests, `pest-plugin-faker`)

- `TieredComputation` is monotonic non-decreasing in basis for every seeded item.
- `TieredComputation(basis) ≥ floor` of band 1 for every basis > 0.
- `CostBasisUplift` always equals exactly 1.5 × party-and-party before rounding.
- Every `Computed` from `FeeCalculator` has ≥1 step and a non-empty `provenance()`.
- Sum of rounded lines == bill `total_cents` (no hidden rounding).
- Re-running `BillAssembler` on an issued bill's snapshot reproduces `total_cents` byte-for-byte under the same `aro_version_id`, and still does after a new `aro_versions` row is published.
- Every `aro_items` row of computation `tiered`/`bracket_rate` has contiguous, non-overlapping bands starting at 0 with exactly one open band (seed validation test).

### 11.3 Service and HTTP tests

- `POST /chargeable-items` below minimum → 422 with `minimum` and `provenance`; at minimum → 201; above minimum on a discretionary head without `uplift_justification` → 422.
- `POST /bills/{id}/issue` with shortfall → 422; with exemption → 201; with valid election → 201 and bill cites para 22.
- Issued bill row is immutable: `UPDATE` on `bills`/`bill_lines` after `locked_at` raises (DB trigger test).
- Tenant isolation: for every route in §10, user of firm A requesting firm B's resource ids gets 404, never 403 (no existence leak), and mass-assignment of `firm_id` is ignored.
- Interest accrual job is idempotent across reruns for the same day.
- PDF render of a bill of costs contains the five para 69 column headers, disbursements after fees, and a blank "Attending taxation" line.

### 11.4 eTIMS contract tests (nightly, sandbox PIN)

- `selectInitOsdcInfo` returns `cmcKey`; stored encrypted; decrypt round-trips.
- `saveTrnsSalesOsdc` for the §8 worked invoice returns `resultCd 000` and the six receipt fields; PDF re-render contains CUIN and a scannable QR (decode it in the test).
- Retry with the same `invcNo` after a simulated timeout does not create a duplicate on the sales list.
- Credit note with `rcptTyCd R` and `orgInvcNo` is accepted.
- Payload builder: `totTaxblAmt == Σ taxblAmt{A..E}`, `totAmt == totTaxblAmt + totTaxAmt`, `totItemCnt == count(itemList)`; rejected payloads never leave the builder.

---

## 12. Migration from the Supabase prototype

1. `supabase db dump --schema public > docs/prototype/supabase-schema.sql` and dump the bodies of `compute_fee_schedule_minimum` and `get_matter_billing_shortfall`. Keep them in `docs/prototype/` as the record of what the prototype did; they are not ported.
2. Export `firms`, `profiles`, `clients`, `matters`, `time_entries`, `bill_line_items`, `billing_rates`, `fee_schedule_items` to CSV. Write a one-off `php artisan justify:import-prototype` that maps: `profiles` → `users` + `firm_user`; `bill_line_items` → `chargeable_items` (`is_disbursement` → `kind = disbursement`; everything else `fee`); `fee_schedule_items.id` → `aro_items.code` via a hand-maintained map; `matters.primary_fee_schedule_item_id`/`fee_basis_value`/`is_fee_schedule_exempt` → `matter_classifications`.
3. TanStack: replace `supabase-js` calls with `backendFetch<T>` and hand-written per-feature wire types (`docs/FRONTEND_PLAN.md` §4.1) — no OpenAPI tooling; Laravel API Resources are the contract, so keep response shapes stable and note every field change in the PR for the frontend's `*-wire.ts`. Delete `types/supabase.ts`. Fix the two broken `invalidateQueries` keys (`LineItems.tsx:118`, `Tabs.tsx:363/385`) as part of the switch — or rather, they disappear with the rewrite of those components against the new API.
4. Auth cut-over: import users with forced password reset; Sanctum SPA cookies on `app.justify.<tld>` with API on `api.justify.<tld>`.

---

## 13. Phased build plan

Each phase ends with its acceptance test green in CI. Estimates assume one backend engineer full-time plus one frontend engineer from phase 3.

| Phase                             | Weeks | Deliverables                                                                                                                                                                             | Acceptance                                                                                          |
| --------------------------------- | ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| **0 — Foundation**                | 1     | Laravel repo, PHP 8.4, Postgres, Redis, Horizon, Pest, CI, `brick/money`, Sanctum, tenancy trait + policies, prototype dump in `docs/prototype/`                                         | tenant isolation test suite green on a stub resource                                                |
| **1 — Engine**                    | 2     | `Domain\Aro\Engine` complete (§4.1–4.7, 4.11, 4.12); YAML seed for all 12 schedules; `AroSeeder`; seed validation test; every golden case in §11.1                                       | 100% of §11.1 green; invariants green                                                               |
| **2 — Rule data & admin**         | 1.5   | `aro_*` tables and models; Resolver; FeeCalculator; interpretations register; Inertia admin for versions/items/modifiers with two-person publish; `POST /aro/preview`                    | preview endpoint returns identical figures to engine tests; admin cannot publish unreviewed version |
| **3 — Matters & chargeable work** | 2     | classification, fee agreements incl. para 22 election, chargeable items with server-side minimum, shortfall, time entries → chargeable items; TanStack switched to API for these screens | §11.3 chargeable-item and shortfall tests green; prototype data imported                            |
| **4 — Bills**                     | 2     | BillAssembler, issue/lock, numbering, delivery, deemed agreement, interest job, payments, taxation tracking, one-sixth flag; PDFs for fee note, bill of costs (para 69), statement       | §11.3 bill tests green; a real Schedule 6 matter produces a bill of costs a taxing officer can read |
| **5 — Tax & eTIMS**               | 2     | VAT/WHT, tax invoice PDF, `firm_tax_profiles`, device init, item registration, `SubmitInvoiceToEtims`, credit notes, reconciliation job, portal fallback                                 | §11.4 sandbox contract tests green nightly; first firm certified                                    |
| **6 — Hardening**                 | 1     | activity log everywhere, rate limits, backups/restore drill, load test on preview endpoint, security review of `cmcKey` handling, runbooks                                               | restore drill passes; p95 preview < 100 ms                                                          |

Total: ~11.5 weeks to a certified, billing-grade system. Phases 1 and 2 are the ones that must not be rushed — everything else is conventional Laravel.

---

## 14. Open items to verify

1. **Schedule 10 item 1(a) lower brackets** — the fee column for gross estate ≤1,000,000 did not survive the web transcription. Transcribe from `docs/The Advocates (Remuneration) Order.pdf` (Schedule 10, near page 46–48) before seeding; add golden tests once known.
2. **Schedules 8 and 9 Part B** — confirm whether the 50% advocate-and-client uplift applies to the two rent tribunals' schedules as it does to 6, 7, 10 and 11.
3. **Schedule 4 table** — transcribe all trade-mark particulars from the PDF.
4. **Sch 1 building-society rule number** — cited here as "para 34"; confirm the paragraph number in the current text (rules 27–41 govern Schedule 1).
5. **ARO amendments after 31 Dec 2022** — none found at the time of writing; the LSK has circulated draft revised scales in the past. Watch the Kenya Gazette; the `aro_versions` design absorbs a new order without engine changes.
6. **eTIMS field names** — validate every field in §9.3 against the KRA OSCU Specification v2.0 PDF and the sandbox before implementation; KRA revises the spec and endpoint names (`saveTrnsSalesOsdc` vs newer variants).
7. **Legal-services item classification code** — pick the exact `itemClsCd` from the firm's pulled `selectItemClsList`; `80121600` is a placeholder from the UNSPSC family.
8. **WHT threshold and rate** — confirm the current s.35 rate (5% resident professional fees) and threshold with the firm's tax advisor before release; both have been adjusted by Finance Acts before.
9. **Third-party integrator listing** — decide whether Justify applies for KRA integrator status (lets firms onboard by device serial only) or each firm self-certifies with our software. The former is the right product answer; it has a vetting lead time.
