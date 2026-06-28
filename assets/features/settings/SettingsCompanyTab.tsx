import { useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Button, TextField } from '@nubitio/ui';
import { consultService } from '@features/consult';
import {
  tenantOnboardingService,
  type SaveCompanyInput,
} from '@features/onboarding/tenantOnboardingService';
import { tenantSettingsService } from '@features/config';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import './settingsHub.scss';

const CURRENCY_LABELS: Record<string, string> = {
  PEN: 'PEN — Sol',
  USD: 'USD — Dólar estadounidense',
};

interface CompanyFormState {
  ruc: string;
  businessName: string;
  tradeName: string;
  fiscalAddress: string;
  phone: string;
}

const EMPTY_FORM: CompanyFormState = {
  ruc: '',
  businessName: '',
  tradeName: '',
  fiscalAddress: '',
  phone: '',
};

function SettingsFieldRow({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <div className="settings-field-row">
      <span className="settings-field-row__label">{label}</span>
      <div className="settings-field-row__control">{children}</div>
    </div>
  );
}

export const SettingsCompanyTab = () => {
  const [form, setForm] = useState<CompanyFormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [verifyingRuc, setVerifyingRuc] = useState(false);

  const companyQuery = useQuery({
    queryKey: ['settings', 'company'],
    queryFn: () => tenantOnboardingService.snapshot(),
  });

  const settingsQuery = useQuery({
    queryKey: ['tenant-settings'],
    queryFn: () => tenantSettingsService.getSettings(),
  });

  useEffect(() => {
    const company = companyQuery.data?.company;
    if (!company) return;

    setForm({
      ruc: company.ruc ?? '',
      businessName: company.businessName ?? '',
      tradeName: company.tradeName ?? '',
      fiscalAddress: company.fiscalAddress ?? '',
      phone: company.phone ?? '',
    });
  }, [companyQuery.data]);

  const update = (patch: Partial<CompanyFormState>) => {
    setForm((prev) => ({ ...prev, ...patch }));
  };

  const verifyRuc = async () => {
    const doc = form.ruc.trim();
    if (doc.length !== 11 || !SharedUtils.validateRUC(doc)) {
      toast('Ingresa un RUC válido de 11 dígitos', 'warning');
      return;
    }

    setVerifyingRuc(true);
    try {
      const response = await consultService.consultRuc(doc);
      if (!response.data.active) {
        toast('El RUC ingresado no se encuentra activo', 'error');
        return;
      }
      update({
        businessName: response.data.name,
        tradeName: response.data.name,
        fiscalAddress: response.data.address,
      });
      toast('Datos cargados desde SUNAT', 'success');
    } catch {
      toast('No se pudo consultar el RUC', 'error');
    } finally {
      setVerifyingRuc(false);
    }
  };

  const handleSave = async () => {
    const ruc = form.ruc.trim();
    if (ruc.length !== 11 || !SharedUtils.validateRUC(ruc)) {
      toast('El RUC debe tener 11 dígitos válidos', 'error');
      return;
    }
    if (!form.businessName.trim() || !form.fiscalAddress.trim()) {
      toast('Completa razón social y domicilio fiscal', 'error');
      return;
    }

    const payload: SaveCompanyInput = {
      ruc,
      businessName: form.businessName.trim(),
      tradeName: form.tradeName.trim() || undefined,
      fiscalAddress: form.fiscalAddress.trim(),
      phone: form.phone.trim() || undefined,
    };

    setSaving(true);
    try {
      await tenantOnboardingService.saveCompany(payload);
      await companyQuery.refetch();
      toast('Datos de empresa guardados', 'success');
    } catch {
      toast('Error al guardar los datos de empresa', 'error');
    } finally {
      setSaving(false);
    }
  };

  if (companyQuery.isLoading) {
    return (
      <div className="settings-panel">
        <div className="settings-loading">
          <i className="ph ph-circle-notch settings-loading__icon" aria-hidden="true" />
          Cargando datos de empresa…
        </div>
      </div>
    );
  }

  const currencyCode = settingsQuery.data?.defaultCurrency ?? 'PEN';
  const currencyLabel = CURRENCY_LABELS[currencyCode] ?? currencyCode;

  return (
    <div className="settings-panel" data-testid="settings-company-tab">
      <div className="settings-card">
        <section className="settings-section" aria-labelledby="settings-identity-heading">
          <h3 id="settings-identity-heading" className="settings-section__heading">
            Identidad fiscal
          </h3>
          <div className="settings-fields">
            <SettingsFieldRow label="Razón social">
              <TextField
                value={form.businessName}
                onChange={(e) => update({ businessName: e.target.value })}
              />
            </SettingsFieldRow>
            <SettingsFieldRow label="RUC">
              <div className="settings-field-row__control settings-field-row__control--with-action">
                <TextField
                  value={form.ruc}
                  onChange={(e) =>
                    update({ ruc: e.target.value.replace(/\D/g, '').slice(0, 11) })
                  }
                  inputMode="numeric"
                />
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => void verifyRuc()}
                  loading={verifyingRuc}
                >
                  Consultar SUNAT
                </Button>
              </div>
            </SettingsFieldRow>
            <SettingsFieldRow label="Nombre comercial">
              <TextField
                value={form.tradeName}
                onChange={(e) => update({ tradeName: e.target.value })}
              />
            </SettingsFieldRow>
            <SettingsFieldRow label="Domicilio fiscal">
              <TextField
                value={form.fiscalAddress}
                onChange={(e) => update({ fiscalAddress: e.target.value })}
              />
            </SettingsFieldRow>
          </div>
        </section>

        <section className="settings-section" aria-labelledby="settings-contact-heading">
          <h3 id="settings-contact-heading" className="settings-section__heading">
            Contacto
          </h3>
          <div className="settings-fields">
            <SettingsFieldRow label="Teléfono">
              <TextField
                value={form.phone}
                onChange={(e) => update({ phone: e.target.value })}
              />
            </SettingsFieldRow>
          </div>
        </section>

        <section className="settings-section" aria-labelledby="settings-locale-heading">
          <h3 id="settings-locale-heading" className="settings-section__heading">
            Localización
          </h3>
          <div className="settings-fields">
            <SettingsFieldRow label="País">
              <TextField value="Perú" disabled />
            </SettingsFieldRow>
            <SettingsFieldRow label="Moneda">
              <TextField value={currencyLabel} disabled />
            </SettingsFieldRow>
            <SettingsFieldRow label="Zona horaria">
              <TextField value="America/Lima (UTC-5)" disabled />
            </SettingsFieldRow>
          </div>
        </section>

        <div className="settings-card__footer">
          <Link to="/company" className="settings-advanced-link">
            Logo, ubigeo y datos bancarios
          </Link>
          <Button variant="primary" onClick={() => void handleSave()} loading={saving}>
            Guardar cambios
          </Button>
        </div>
      </div>
    </div>
  );
};