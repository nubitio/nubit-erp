export interface TenantRecord {
  id: number;
  name: string;
  connection: string;
  status?: string;
  plan?: string | null;
  backup_frequency?: number;
  backup_retention_days?: number;
  primary_domain?: string | null;
}

export interface TenantDomain {
  id: number;
  domain: string;
  is_primary: number | boolean;
}

export interface PlanOption {
  id: number;
  name: string;
  is_default: boolean;
}

export interface QuotaUsage {
  limit: number;
  current: number;
}

export interface TenantDetailPayload {
  tenant: TenantRecord;
  domains: TenantDomain[];
  primaryDomain: string | null;
  plans: PlanOption[];
  usage: {
    quotaSummary: Record<string, QuotaUsage>;
    usageLatest: Record<string, number>;
    usageDeltas: Record<string, { previous: number; current: number; delta: number }>;
    usageHistory: unknown[];
    quotaAlerts: Array<{ resource: string; percent: number }>;
    error?: string;
  };
  backups: BackupRecord[];
  binlogSummary: {
    last_backup_at?: string | null;
    total_count?: number;
    total_size_bytes?: number;
  };
  provisions: ProvisionRecord[];
  subscription: SubscriptionRecord | null;
  paymentEvents: PaymentEventRecord[];
  certificates: CertificateRecord[];
}

export interface BackupRecord {
  id: number;
  filename: string;
  backup_type?: string;
  size_bytes: number;
  storage_type?: string;
  status: string;
  error_message?: string | null;
  created_at: string;
}

export interface ProvisionRecord {
  id: number;
  status: string;
  domain: string;
  target_container?: string;
  created_at: string;
  started_at?: string | null;
  completed_at?: string | null;
  error_message?: string | null;
}

export interface SubscriptionRecord {
  id: number;
  plan: string;
  status: string;
  billing_cycle: string;
  gateway: string;
  gateway_subscription_id?: string | null;
  gateway_customer_id?: string | null;
  account_id?: number;
  current_period_start?: string | null;
  current_period_end?: string | null;
  grace_period_days?: number;
  created_at: string;
  cancelled_at?: string | null;
}

export interface PaymentEventRecord {
  id: number;
  gateway: string;
  event_type: string;
  gateway_event_id: string;
  processed_at?: string | null;
  created_at: string;
}

export interface CertificateRecord {
  id: number;
  ruc: string;
  status: string | { value: string };
  expiresAt?: string | null;
  expires_at?: string | null;
  subjectCn?: string | null;
  subject_cn?: string | null;
  issuerCn?: string | null;
  issuer_cn?: string | null;
  serialNumber?: string | null;
  serial_number?: string | null;
  fingerprintSha256?: string;
  fingerprint_sha256?: string;
  validFrom?: string | null;
  valid_from?: string | null;
  uploadedAt?: string | null;
  uploaded_at?: string | null;
  uploadedBy?: string | null;
  uploaded_by?: string | null;
  revokedAt?: string | null;
  revoked_at?: string | null;
  revokedBy?: string | null;
  revoked_by?: string | null;
  revocationReason?: string | null;
  revocation_reason?: string | null;
  supersededById?: number | null;
  superseded_by_id?: number | null;
  lastAlertedAt?: string | null;
  last_alerted_at?: string | null;
  lycetSyncStatus?: string | null;
  sync_status?: string | null;
  lycetSyncError?: string | null;
  lycet_sync_error?: string | null;
  lycetSyncUpdatedAt?: string | null;
  lycet_sync_updated_at?: string | null;
}

export interface NotificationChannelRow {
  enabled: boolean;
  severity: string;
  recipients: string[];
  config: Record<string, unknown>;
}

export interface TenantNotificationsPayload {
  eventDefinitions: Record<string, { label: string; severity: string }>;
  channels: string[];
  matrix: Record<string, Record<string, NotificationChannelRow>>;
}

export interface FeatureOverrideRecord {
  id: number;
  featureKey: string;
  isEnabled: boolean;
  configJson: Record<string, unknown> | null;
  expiresAt: string | null;
  reason: string | null;
  isActive: boolean;
  isExpired: boolean;
  cleanedAt: string | null;
  createdAt: string;
}

export interface FeatureCatalogEntry {
  key: string;
  label: string;
  category: string;
  hasConfig: boolean;
}

export interface TenantFeaturesPayload {
  overrides: FeatureOverrideRecord[];
  features: FeatureCatalogEntry[];
  categories: Record<string, string>;
}

export type TenantTab =
  | 'general'
  | 'domains'
  | 'provisioning'
  | 'usage'
  | 'subscription'
  | 'backups'
  | 'certificates'
  | 'notifications'
  | 'features';
