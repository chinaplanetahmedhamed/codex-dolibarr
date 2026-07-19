export type Language = 'ar' | 'en';
export type OpaqueId = string;
export type Decimal = string;
export type Currency = string;

export type BilingualLabel = { ar: string; en: string };
export type Status = { code: string; label: BilingualLabel };
export type Money = { amount: Decimal; currency: Currency };
export type PageMeta = { limit: number; has_more: boolean; next_cursor?: string | null };

export type ApiEnvelope<T> = { data: T; request_id: string };
export type ListEnvelope<T> = ApiEnvelope<T[]> & { meta: PageMeta };

export type ApiErrorCode =
  | 'bad_request'
  | 'invalid_credentials'
  | 'session_expired'
  | 'forbidden'
  | 'not_found'
  | 'conflict'
  | 'validation'
  | 'rate_limited'
  | 'server_error';

export type ErrorEnvelope = {
  error: {
    code: ApiErrorCode;
    message: BilingualLabel;
    field_codes?: string[];
    retry_after?: number;
  };
  request_id: string;
};

export type LoginRequest = {
  login: string;
  password: string;
  device_id: string;
  device_name?: string;
};

export type Session = {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  access_expires_in: 900;
  refresh_expires_at: string;
};

export type Me = {
  id: OpaqueId;
  display_name: string;
  company_name: string;
  preferred_language: Language;
};

export type Balance = {
  currency: Currency;
  debit: Decimal;
  credit: Decimal;
  balance: Decimal;
  updated_at: string;
};

export type TimelineStep = {
  code: string;
  label: BilingualLabel;
  state: 'completed' | 'current' | 'next' | 'planned' | 'unconfirmed';
  state_label: BilingualLabel;
  occurred_at?: string | null;
  source?: BilingualLabel | null;
  location?: string | null;
  public_note?: string | null;
  public_reason?: string | null;
};

export type ProjectSummary = {
  id: OpaqueId;
  ref: string;
  title: string;
  status: Status;
  progress_percent: number;
  current_step?: BilingualLabel;
  next_step?: BilingualLabel;
  expected_next_at?: string | null;
  latest_update_at?: string | null;
  latest_update_by?: BilingualLabel;
  updated_at: string;
};

export type ProjectDetail = ProjectSummary & {
  description: string;
  start_at?: string | null;
  end_at?: string | null;
  timeline: TimelineStep[];
  shipment_ids: OpaqueId[];
  document_ids: OpaqueId[];
  report_ids: OpaqueId[];
  balances: Balance[];
};

export type ShipmentSummary = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  ref: string;
  status: Status;
  container_number?: string | null;
  container_type?: string | null;
  container_count: number;
  bl_number?: string | null;
  eta?: string | null;
  ata?: string | null;
  free_time_end?: string | null;
  updated_at: string;
};

export type CustomerAction = {
  label: BilingualLabel;
  responsibility: 'customer' | 'china_planet' | 'external_party' | 'none';
  required_from_customer: boolean;
  due_at?: string | null;
  public_note?: string | null;
} | null;

export type DocumentRequirement = {
  code: string;
  label: BilingualLabel;
  status: 'required' | 'requested' | 'received' | 'under_review' | 'correction_required' | 'approved' | 'published';
  status_label: BilingualLabel;
  requested_from: 'customer' | 'china_planet' | 'carrier' | 'customs' | 'other';
  action_required: boolean;
  due_at?: string | null;
  public_note?: string | null;
  document_id?: OpaqueId | null;
};

export type ShipmentDetail = ShipmentSummary & {
  booking_number?: string | null;
  shipping_line?: string | null;
  vessel_name?: string | null;
  voyage_number?: string | null;
  port_loading?: string | null;
  port_destination?: string | null;
  etd?: string | null;
  atd?: string | null;
  free_time_days?: number | null;
  free_time_start?: string | null;
  eta_changed: boolean;
  previous_eta?: string | null;
  eta_change_days?: number | null;
  latest_update_at?: string | null;
  latest_update_source?: BilingualLabel;
  latest_update: {
    title: BilingualLabel;
    public_note?: string | null;
    occurred_at: string;
    source: BilingualLabel;
  } | null;
  eta_change: {
    changed_at: string;
    previous_eta: string;
    current_eta: string;
    change_days: number;
    source: BilingualLabel;
    public_reason?: string | null;
  } | null;
  customer_notes?: string | null;
  customer_action: CustomerAction;
  timeline: TimelineStep[];
  document_requirements: DocumentRequirement[];
};

export type OrderSummary = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  ref: string;
  ordered_at: string;
  status: Status;
  total: Money;
  paid: Money;
  remaining: Money;
  updated_at: string;
};

export type Transaction = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  occurred_at: string;
  description: string;
  direction: 'debit' | 'credit';
  amount: Money;
  reference?: string | null;
  updated_at: string;
};

export type ServiceInvoice = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  ref: string;
  issued_at: string;
  due_at?: string | null;
  status: Status;
  total: Money;
  paid: Money;
  remaining: Money;
  updated_at: string;
};

export type Document = {
  id: OpaqueId;
  source_type: 'project_asset' | 'operation_report_attachment';
  project_id?: OpaqueId | null;
  shipment_id?: OpaqueId | null;
  report_id?: OpaqueId | null;
  name: string;
  mime_type: string;
  size_bytes: number;
  version: number;
  approved_at: string;
  updated_at: string;
};

export type CommercialDocumentSummary = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  shipment_id?: OpaqueId | null;
  ref: string;
  document_type: 'commercial_invoice' | 'packing_list' | 'bill_of_lading' | 'cargox' | 'certificate_origin' | 'inspection' | 'other';
  document_date: string;
  currency?: Currency | null;
  updated_at: string;
};

export type CommercialDocumentDetail = CommercialDocumentSummary & {
  consignee?: string | null;
  port_loading?: string | null;
  port_destination?: string | null;
  incoterm?: string | null;
  public_notes?: string | null;
  lines: {
    position: number;
    description: string;
    hs_code?: string | null;
    quantity: Decimal;
    unit?: string | null;
    unit_price: Money;
    total: Money;
    cartons?: Decimal | null;
    net_weight?: Decimal | null;
    gross_weight?: Decimal | null;
    country_of_origin?: string | null;
  }[];
};

export type ReportSummary = {
  id: OpaqueId;
  project_id?: OpaqueId | null;
  ref: string;
  report_type: 'order' | 'factory_audit' | 'quality_control' | 'shipping_inquiry' | 'shipping_booking' | 'loading' | 'customs' | 'claim' | 'other';
  title: string;
  stage: Status;
  progress_percent: number;
  next_action?: string | null;
  due_at?: string | null;
  updated_at: string;
};

export type ReportDetail = ReportSummary & {
  public_note: string | null;
  fields: { code: string; label: BilingualLabel; value: string }[];
  documents: Document[];
};

export type Dashboard = {
  active_projects: number;
  active_shipments: number;
  open_orders: number;
  current_project?: ProjectSummary | null;
  current_shipment?: ShipmentSummary | null;
  balances: Balance[];
  updated_at: string;
};

export type ListQuery = { limit?: number; cursor?: string; updated_since?: string };
