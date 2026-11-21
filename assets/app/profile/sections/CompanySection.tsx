import { useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { EntityDropdown } from '@nubitio/crud';
import { Alert, Button, Combobox, Skeleton, TextField } from '@nubitio/ui';
import { consultService } from '@features/consult';
import { tenantOnboardingService } from '@features/onboarding/tenantOnboardingService';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { violationsOf } from '../formErrors';
import {
  BANKS,
  LOGO_MAX_BYTES,
  LOGO_TYPES,
  companyService,
  logoIri,
  type Company,
  type CompanyUpdate,
} from '../companyService';
import { MAX_DIGITS, onlyDigits, validateCompany, type CompanyDraft } from '../companyValidation';
import { SettingsGroup, SettingsRow } from '../settings/SettingsRow';

const API = getCoreApiBaseUrl();
const COMPANY_KEY = ['settings', 'company-record'] as const;
const FIELDS = [
  'ruc',
  'businessName',
  'tradeName',
  'fiscalAddress',
  'phone',
  'logo',
  'bankName',
  'bankAccount',
  'bankCci',
  'detraccionAccount',
] as const;
interface UbigeoItem {
  code?: string;
  department?: string | null;
  province?: string | null;
  district?: string | null;
}

/** A list row: the district first, then where it is and its INEI code. */
const UbigeoOption = ({ item }: { item: UbigeoItem }) => (
  <span className="ubigeo-option">
    <strong>{item.district || item.province || item.department}</strong>
    <small>
      {[item.province, item.department].filter(Boolean).join(', ')}
      {item.code ? ` · ${item.code}` : ''}
    </small>
  </span>
);

const BANK_OPTIONS = BANKS.map((bank) => ({ value: bank, label: bank }));

const draftOf = (company: Company): CompanyDraft => ({
  ruc: company.ruc,
  businessName: company.businessName,
  tradeName: company.tradeName ?? '',
  fiscalAddress: company.fiscalAddress,
  phone: company.phone ?? '',
  ubigeoCode: company.ubigeoCode ?? '',
  department: company.department ?? '',
  province: company.province ?? '',
  district: company.district ?? '',
  bankName: company.bankName,
  bankAccount: company.bankAccount ?? '',
  bankCci: company.bankCci ?? '',
  detraccionAccount: company.detraccionAccount ?? '',
});

/**
 * "Empresa": the tenant's fiscal identity, location, bank accounts and logo, edited in place.
 * The company is one record per tenant, so there is no list to pick from.
 */
export const CompanySection = () => {
  const snapshot = useQuery({
    queryKey: ['settings', 'company'],
    queryFn: () => tenantOnboardingService.snapshot(),
  });
  const id = snapshot.data?.company?.id ?? null;

  const record = useQuery({
    queryKey: [...COMPANY_KEY, id],
    queryFn: async () => (await companyService.get(id as string)).data,
    enabled: id !== null,
  });

  if (snapshot.isError || record.isError) {
    return <Alert tone="danger">No se pudieron cargar los datos de la empresa.</Alert>;
  }
  if (snapshot.isPending || (id !== null && record.isPending)) {
    return <Skeleton variant="rect" height={320} />;
  }
  if (id === null || !record.data) {
    return (
      <Alert tone="info">Aún no registraste los datos de tu empresa. Complétalos al empezar.</Alert>
    );
  }
  // Keyed by the saved record so the form restarts from what the server now holds.
  return <CompanyForm key={JSON.stringify(record.data)} company={record.data} />;
};

const CompanyForm = ({ company }: { company: Company }) => {
  const queryClient = useQueryClient();
  const canEdit = hasPermission('settings.edit');
  const input = useRef<HTMLInputElement>(null);
  const [draft, setDraft] = useState<CompanyDraft>(() => draftOf(company));
  const [logo, setLogo] = useState<{ iri: string | null; url: string | null } | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [touched, setTouched] = useState<Partial<Record<keyof CompanyDraft, boolean>>>({});
  const [submitted, setSubmitted] = useState(false);
  const [logoBroken, setLogoBroken] = useState(false);
  const [general, setGeneral] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [uploading, setUploading] = useState(false);

  const set = (patch: Partial<CompanyDraft>) => {
    setDraft((previous) => ({ ...previous, ...patch }));
    // What the server or the upload objected to is stale once the field changes.
    setErrors((previous) => {
      const next = { ...previous };
      for (const key of Object.keys(patch)) delete next[key];
      return next;
    });
  };
  const touch = (name: keyof CompanyDraft) =>
    setTouched((previous) => ({ ...previous, [name]: true }));
  const issues = validateCompany(draft);
  // A rule is shown once the field was left, or on every field after the first save attempt.
  const errorOf = (name: string): string | undefined =>
    errors[name] ||
    (submitted || touched[name as keyof CompanyDraft]
      ? issues[name as keyof CompanyDraft]
      : undefined);
  const logoUrl = logo ? logo.url : (company.logo?.path ?? null);
  const dirty = logo !== null || JSON.stringify(draft) !== JSON.stringify(draftOf(company));

  const verifyRuc = async () => {
    if (issues.ruc) {
      touch('ruc');
      toast('Ingresa un RUC válido de 11 dígitos', 'warning');
      return;
    }
    setVerifying(true);
    try {
      const { data } = await consultService.consultRuc(draft.ruc);
      if (!data.active) {
        toast('El RUC ingresado no se encuentra activo', 'error');
        return;
      }
      set({
        businessName: data.name.slice(0, 255),
        tradeName: data.name.slice(0, 255),
        fiscalAddress: data.address.slice(0, 500),
      });
      toast('Datos cargados desde SUNAT', 'success');
    } catch {
      toast('No se pudo consultar el RUC', 'error');
    } finally {
      setVerifying(false);
    }
  };

  const uploadLogo = async (file: File) => {
    setErrors((previous) => ({ ...previous, logo: '' }));
    if (!LOGO_TYPES.includes(file.type)) {
      setErrors((previous) => ({ ...previous, logo: 'Sube una imagen PNG, JPG o WebP.' }));
      return;
    }
    if (file.size > LOGO_MAX_BYTES) {
      setErrors((previous) => ({ ...previous, logo: 'La imagen supera los 2 MB.' }));
      return;
    }
    setUploading(true);
    try {
      const media = (await companyService.uploadLogo(file)).data;
      const iri = logoIri(media);
      if (!iri) throw new Error('invalid media');
      setLogoBroken(false);
      setLogo({ iri, url: media.path ?? URL.createObjectURL(file) });
    } catch {
      setErrors((previous) => ({ ...previous, logo: 'No se pudo subir el logo.' }));
    } finally {
      setUploading(false);
    }
  };

  const save = async () => {
    setSubmitted(true);
    if (Object.keys(issues).length > 0) return;

    const blank = (value: string) => value.trim() || null;
    const patch: CompanyUpdate = {
      ruc: draft.ruc,
      businessName: draft.businessName.trim(),
      tradeName: blank(draft.tradeName),
      fiscalAddress: draft.fiscalAddress.trim(),
      phone: blank(draft.phone),
      ubigeoCode: blank(draft.ubigeoCode),
      department: blank(draft.department),
      province: blank(draft.province),
      district: blank(draft.district),
      bankName: draft.bankName,
      bankAccount: blank(draft.bankAccount),
      bankCci: blank(draft.bankCci),
      detraccionAccount: blank(draft.detraccionAccount),
      ...(logo ? { logo: logo.iri } : {}),
    };

    setSaving(true);
    setErrors({});
    setGeneral(null);
    try {
      const saved = (await companyService.update(company.id, patch)).data;
      queryClient.setQueryData([...COMPANY_KEY, company.id], saved);
      void queryClient.invalidateQueries({ queryKey: ['settings', 'company'] });
      setLogo(null);
      toast('Datos de empresa guardados', 'success');
    } catch (failure) {
      const violations = violationsOf(failure, FIELDS);
      setErrors(violations.fields);
      setGeneral(violations.general);
    } finally {
      setSaving(false);
    }
  };

  const fieldError = (name: string) => {
    const message = errorOf(name);
    return message ? (
      <p className="settings-error" role="alert">
        {message}
      </p>
    ) : null;
  };

  return (
    <div data-testid="settings-company-section">
      <SettingsGroup title="Identidad fiscal">
        <SettingsRow
          label="Logo"
          description="PNG, JPG o WebP, hasta 2 MB. Sale en tus comprobantes."
        >
          <div className="company-logo">
            {logoUrl && !logoBroken ? (
              <div className="company-logo__frame">
                <img
                  className="company-logo__image"
                  src={logoUrl}
                  alt="Logo de la empresa"
                  onError={() => setLogoBroken(true)}
                />
              </div>
            ) : (
              <div className="company-logo__frame company-logo__frame--empty">
                <i className="ph ph-image" aria-hidden="true" />
                <span>{logoUrl ? 'No se pudo mostrar' : 'Sin logo'}</span>
              </div>
            )}
            {canEdit && (
              <>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  loading={uploading}
                  onClick={() => input.current?.click()}
                >
                  {logoUrl ? 'Cambiar' : 'Subir logo'}
                </Button>
                {logoUrl && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={uploading}
                    onClick={() => setLogo({ iri: null, url: null })}
                  >
                    Quitar
                  </Button>
                )}
                <input
                  ref={input}
                  type="file"
                  accept={LOGO_TYPES.join(',')}
                  hidden
                  data-testid="company-logo-input"
                  onChange={(event) => {
                    const target = event.target;
                    const file = target.files?.[0];
                    target.value = '';
                    if (file) void uploadLogo(file);
                  }}
                />
              </>
            )}
          </div>
        </SettingsRow>
        {fieldError('logo')}
        <SettingsRow
          label="RUC"
          description="11 dígitos, sin espacios ni guiones."
          htmlFor="company-ruc"
        >
          <TextField
            id="company-ruc"
            value={draft.ruc}
            inputMode="numeric"
            maxLength={MAX_DIGITS.ruc}
            disabled={!canEdit}
            invalid={Boolean(errorOf('ruc'))}
            onBlur={() => touch('ruc')}
            onChange={(event) => set({ ruc: onlyDigits(event.target.value, MAX_DIGITS.ruc) })}
          />
          {canEdit && (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              loading={verifying}
              onClick={() => void verifyRuc()}
            >
              Consultar SUNAT
            </Button>
          )}
        </SettingsRow>
        {fieldError('ruc')}
        <SettingsRow label="Razón social" htmlFor="company-business-name">
          <TextField
            id="company-business-name"
            value={draft.businessName}
            maxLength={255}
            disabled={!canEdit}
            invalid={Boolean(errorOf('businessName'))}
            onBlur={() => touch('businessName')}
            onChange={(event) => set({ businessName: event.target.value })}
          />
        </SettingsRow>
        {fieldError('businessName')}
        <SettingsRow label="Nombre comercial" htmlFor="company-trade-name">
          <TextField
            id="company-trade-name"
            value={draft.tradeName}
            maxLength={255}
            disabled={!canEdit}
            invalid={Boolean(errorOf('tradeName'))}
            onBlur={() => touch('tradeName')}
            onChange={(event) => set({ tradeName: event.target.value })}
          />
        </SettingsRow>
        {fieldError('tradeName')}
        <SettingsRow label="Domicilio fiscal" htmlFor="company-fiscal-address">
          <TextField
            id="company-fiscal-address"
            value={draft.fiscalAddress}
            maxLength={500}
            disabled={!canEdit}
            invalid={Boolean(errorOf('fiscalAddress'))}
            onBlur={() => touch('fiscalAddress')}
            onChange={(event) => set({ fiscalAddress: event.target.value })}
          />
        </SettingsRow>
        {fieldError('fiscalAddress')}
        <SettingsRow
          label="Teléfono"
          description="Solo números, de 6 a 10 dígitos."
          htmlFor="company-phone"
        >
          <TextField
            id="company-phone"
            type="tel"
            inputMode="numeric"
            maxLength={MAX_DIGITS.phone}
            placeholder="999999999"
            value={draft.phone}
            disabled={!canEdit}
            invalid={Boolean(errorOf('phone'))}
            onBlur={() => touch('phone')}
            onChange={(event) => set({ phone: onlyDigits(event.target.value, MAX_DIGITS.phone) })}
          />
        </SettingsRow>
        {fieldError('phone')}
      </SettingsGroup>

      <SettingsGroup title="Ubicación">
        <SettingsRow
          label="Ubigeo (INEI)"
          description="Busca por distrito, provincia, departamento o código."
          htmlFor="company-ubigeo"
        >
          <div className="settings-ubigeo">
            <EntityDropdown
              id="company-ubigeo"
              url={`${API}ubigeos`}
              valueField="code"
              textField="name"
              searchExpr={['district', 'province', 'department', 'code']}
              placeholder="Ej. Miraflores o 150122"
              value={draft.ubigeoCode || null}
              disabled={!canEdit}
              itemFormatter={(item) => <UbigeoOption item={item as UbigeoItem} />}
              onChange={(value, item) =>
                set({
                  ubigeoCode: value ?? '',
                  department: String(item?.['department'] ?? ''),
                  province: String(item?.['province'] ?? ''),
                  district: String(item?.['district'] ?? ''),
                })
              }
            />
          </div>
        </SettingsRow>
        <SettingsRow
          label="Distrito, provincia y departamento"
          description="Se completan al elegir el ubigeo."
        >
          <span className="settings-muted">
            {[draft.district, draft.province, draft.department].filter(Boolean).join(' · ') || '—'}
          </span>
        </SettingsRow>
      </SettingsGroup>

      <SettingsGroup title="Datos bancarios">
        <SettingsRow label="Banco" htmlFor="company-bank">
          <Combobox
            id="company-bank"
            options={BANK_OPTIONS}
            value={draft.bankName}
            disabled={!canEdit}
            clearable
            invalid={Boolean(errorOf('bankName'))}
            onBlur={() => touch('bankName')}
            onChange={(value) => set({ bankName: value })}
          />
        </SettingsRow>
        {fieldError('bankName')}
        <SettingsRow
          label="Cuenta bancaria"
          description="Solo números, de 10 a 20 dígitos."
          htmlFor="company-bank-account"
        >
          <TextField
            id="company-bank-account"
            inputMode="numeric"
            maxLength={MAX_DIGITS.bankAccount}
            value={draft.bankAccount}
            disabled={!canEdit}
            invalid={Boolean(errorOf('bankAccount'))}
            onBlur={() => touch('bankAccount')}
            onChange={(event) =>
              set({ bankAccount: onlyDigits(event.target.value, MAX_DIGITS.bankAccount) })
            }
          />
        </SettingsRow>
        {fieldError('bankAccount')}
        <SettingsRow
          label="CCI"
          description="Código de cuenta interbancario: 20 dígitos."
          htmlFor="company-bank-cci"
        >
          <TextField
            id="company-bank-cci"
            inputMode="numeric"
            maxLength={MAX_DIGITS.bankCci}
            value={draft.bankCci}
            disabled={!canEdit}
            invalid={Boolean(errorOf('bankCci'))}
            onBlur={() => touch('bankCci')}
            onChange={(event) =>
              set({ bankCci: onlyDigits(event.target.value, MAX_DIGITS.bankCci) })
            }
          />
        </SettingsRow>
        {fieldError('bankCci')}
        <SettingsRow
          label="Cuenta de detracciones"
          description="Cuenta del Banco de la Nación: 11 dígitos."
          htmlFor="company-detraccion"
        >
          <TextField
            id="company-detraccion"
            inputMode="numeric"
            maxLength={MAX_DIGITS.detraccion}
            value={draft.detraccionAccount}
            disabled={!canEdit}
            invalid={Boolean(errorOf('detraccionAccount'))}
            onBlur={() => touch('detraccionAccount')}
            onChange={(event) =>
              set({ detraccionAccount: onlyDigits(event.target.value, MAX_DIGITS.detraccion) })
            }
          />
        </SettingsRow>
        {fieldError('detraccionAccount')}
      </SettingsGroup>

      {general && <Alert tone="danger">{general}</Alert>}
      {canEdit && (
        <div className="settings-actions">
          <Button
            type="button"
            variant="primary"
            loading={saving}
            disabled={!dirty || uploading}
            data-testid="company-save"
            onClick={() => void save()}
          >
            Guardar cambios
          </Button>
        </div>
      )}
    </div>
  );
};
