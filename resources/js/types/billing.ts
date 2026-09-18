export type FeeBound = 'prescribed' | 'minimum' | 'maximum';

export type ComputedStep = {
    rule_ref: string;
    description: string;
    amount_cents: number;
    running_total_cents: number;
    replaces: boolean;
};

export type Computed = {
    amount_cents: number;
    bound: FeeBound;
    ceiling_cents: number | null;
    provenance: string;
    steps: ComputedStep[];
};

export type PreviewResponse = Computed & {
    version: { id: string; code: string; status: string };
    item: {
        id: string;
        code: string;
        label: string;
        rule_reference: string;
        schedule: number;
    } | null;
};

export type CatalogueModifier = {
    code: string;
    label: string;
    rule_reference: string;
    op: string;
    value: string;
    condition_key: string | null;
    needs_amount: boolean;
};

export type CatalogueItem = {
    id: string;
    code: string;
    schedule: number;
    label: string;
    rule_reference: string;
    basis_type: string | null;
    computation: string;
    scale_variant: string;
    unit_label: string | null;
    units_included: number | null;
    included_amount_cents: number | null;
    is_instruction_fee: boolean;
    applies_cost_basis: string;
    is_active: boolean;
    bound: FeeBound;
    ceiling_cents: number | null;
    note: string | null;
    needs: {
        basis: boolean;
        quantity: boolean;
        quantity_required: boolean;
        scale: boolean;
        posture: boolean;
        posture_table: string | Record<string, string> | null;
        certificates: boolean;
        agreed_rate: boolean;
        instruction_fee: boolean;
        contested: boolean;
    };
    pointer_target: string | null;
    modifiers: CatalogueModifier[];
};

export type AroVersionSummary = {
    id: string;
    code: string;
    legal_notice: string;
    status: string;
    effective_from?: string | null;
};

export type Posture =
    | 'full_trial'
    | 'undefended'
    | 'no_appearance'
    | 'summary'
    | 'settled_pre_hearing';

export type CostBasis = 'party_party' | 'advocate_client';

export type CourtLevelOption = {
    value: string;
    label: string;
    schedule: number | null;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

export type ClientRow = {
    id: string;
    full_name: string;
    client_type: string;
    kra_pin: string | null;
    email: string | null;
    phone: string | null;
    is_withholding_agent: boolean;
    is_vat_exempt: boolean;
    matters_count?: number;
};

export type ClientForm = {
    id?: string;
    full_name: string;
    client_number: string | null;
    client_type: string;
    kra_pin: string | null;
    id_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    is_withholding_agent: boolean;
    is_vat_exempt: boolean;
    vat_exemption_reference: string | null;
};

export type MatterRow = {
    id: string;
    title: string;
    reference: string | null;
    court_level: string;
    court_level_label: string;
    status: string;
    client: string;
    value_cents: number | null;
    unbilled_count: number;
};

export type Classification = {
    aro_item_id: string | null;
    item: {
        id: string;
        code: string;
        label: string;
        rule_reference: string;
    } | null;
    basis_cents: number | null;
    basis_limb: string | null;
    scale: string | null;
    posture: Posture | null;
    certificates: Record<string, boolean> | null;
    contested: boolean;
    is_exempt: boolean;
    exemption_reason: string | null;
};

export type FeeAgreementRow = {
    id: string;
    type: string;
    type_label: string;
    hourly_rate_cents: number | null;
    fixed_amount_cents: number | null;
    election_communicated_at: string | null;
    signed_at: string | null;
    notes: string | null;
};

export type ChargeableItemRow = {
    id: string;
    kind: 'fee' | 'time' | 'disbursement' | 'recharge';
    description: string;
    occurred_on: string;
    quantity: number | null;
    unit: string | null;
    entered_cents: number;
    computed_minimum_cents: number | null;
    computed_bound: FeeBound | null;
    computed_ceiling_cents: number | null;
    shortfall_cents: number;
    uplift_justification: string | null;
    is_billable: boolean;
    bill_id: string | null;
    aro_item: {
        id: string;
        code: string;
        label: string;
        rule_reference: string;
    } | null;
    aro_item_id: string | null;
    basis_override_cents: number | null;
    scale_override: string | null;
    posture_override: Posture | null;
    modifier_codes: string[];
    modifier_amounts: Record<string, number>;
    snapshot: Computed | null;
};

export type Shortfall = {
    items: {
        id: string;
        description: string;
        entered_cents: number;
        minimum_cents: number;
        shortfall_cents: number;
    }[];
    total_cents: number;
    exempt: boolean;
    election: boolean;
    blocking: boolean;
};

export type BillRow = {
    id: string;
    number: string | null;
    type: string;
    status: string;
    cost_basis: CostBasis;
    total_cents: number;
    paid_cents?: number;
    client?: string;
    matter?: string;
    matter_id?: string;
    issued_at?: string | null;
    created_at: string | null;
};

export type BillLine = {
    id: string;
    seq: number;
    dated_on: string | null;
    particulars: string;
    claimed_cents: number;
    taxed_off_cents: number | null;
    rule_reference: string | null;
    provenance: string | null;
    section: 'fees' | 'disbursements' | 'taxation_attendance';
    vat_rate: number;
    vat_cents: number;
    tax_type_code: string | null;
};
