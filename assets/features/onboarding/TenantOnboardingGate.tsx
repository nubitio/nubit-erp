import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Button, FileDropzone, FormField, TextField, Timeline, TimelineItem } from '@nubitio/ui';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import {
  useApplyTenantPreset,
  useCompleteTenantOnboarding,
  useTenantRuntimeConfig,
  useUploadTenantCertificate,
} from '@shared/hooks';
import { consultService } from '@features/consult';
import {
  tenantOnboardingService,
  type SaveBranchInput,
  type SaveCompanyInput,
  type SaveWarehouseInput,
} from './tenantOnboardingService';
import './TenantOnboardingGate.scss';

const STEPS = ['Welcome', 'Company', 'SUNAT', 'Branch', 'Warehouse', 'Done'] as const;

type StepKey = (typeof STEPS)[number];

const STEP_META: Record<StepKey, { title: string; subtitle: string }> = {
  Welcome: {
    title: 'Elige tu tipo de negocio',
    subtitle: 'Ajustaremos inventario, facturación y menús para que eFact arranque simple.',
  },
  Company: {
    title: 'Company information',
    subtitle: 'Enter your RUC to auto-fill from SUNAT records.',
  },
  SUNAT: {
    title: 'SUNAT connection',
    subtitle: 'Enter your SOL credentials to send electronic documents.',
  },
  Branch: {
    title: 'Your first branch',
    subtitle: 'You can add more branches later from Settings.',
  },
  Warehouse: {
    title: 'First warehouse',
    subtitle: 'Linked to your branch. Add more warehouses later.',
  },
  Done: {
    title: "You're all set!",
    subtitle: "Your Efact account is ready. Here's a summary of what was configured:",
  },
};

type OnboardingStep = 0 | 1 | 2 | 3 | 4 | 5;

export function TenantOnboardingGate() {
  const navigate = useNavigate();
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const applyPreset = useApplyTenantPreset();
  const completeOnboarding = useCompleteTenantOnboarding();
  const uploadCertificate = useUploadTenantCertificate();
  const snapshotQuery = useQuery({
    queryKey: ['tenant-onboarding-snapshot'],
    queryFn: tenantOnboardingService.snapshot,
  });

  const [step, setStep] = useState<OnboardingStep>(0);
  const [selectedPreset, setSelectedPreset] = useState(runtimeConfig?.profile.vertical ?? 'retail');
  const [company, setCompany] = useState<SaveCompanyInput>({
    ruc: '',
    businessName: '',
    tradeName: '',
    fiscalAddress: '',
    phone: '',
  });
  const [branch, setBranch] = useState<SaveBranchInput>({
    name: '',
    address: '',
    invoiceSeries: 'F001',
    receiptSeries: 'B001',
  });
  const [warehouse, setWarehouse] = useState<SaveWarehouseInput>({ name: '' });
  const [certificateFile, setCertificateFile] = useState<File | null>(null);
  const [sunat, setSunat] = useState({ solUser: '', solPass: '', certificatePassword: '' });
  const [sunatSkipped, setSunatSkipped] = useState(false);

  useEffect(() => {
    const snapshot = snapshotQuery.data;
    if (!snapshot) return;

    queueMicrotask(() => {
      if (snapshot.company) {
        setCompany({
          ruc: snapshot.company.ruc ?? '',
          businessName: snapshot.company.businessName ?? '',
          tradeName: snapshot.company.tradeName ?? '',
          fiscalAddress: snapshot.company.fiscalAddress ?? '',
          phone: snapshot.company.phone ?? '',
        });
      }
      if (snapshot.branch) {
        setBranch((current) => ({
          ...current,
          name: snapshot.branch?.name ?? current.name,
          address: snapshot.branch?.address ?? current.address,
        }));
      }
      if (snapshot.warehouse) {
        setWarehouse({ name: snapshot.warehouse.name ?? '' });
      }
    });
  }, [snapshotQuery.data]);

  const currentStep = STEPS[step];
  const meta = STEP_META[currentStep];
  const canContinue = useMemo(() => {
    if (step === 0) return selectedPreset !== '';
    if (step === 1) return company.ruc.length === 11 && company.businessName.trim() !== '' && company.fiscalAddress.trim() !== '';
    if (step === 2) return sunatSkipped || (sunat.solUser.trim() !== '' && sunat.solPass.trim() !== '' && certificateFile !== null);
    if (step === 3) return branch.name.trim() !== '' && branch.invoiceSeries.trim() !== '' && branch.receiptSeries.trim() !== '';
    if (step === 4) return warehouse.name.trim() !== '';
    return true;
  }, [branch, certificateFile, company, selectedPreset, step, sunat, sunatSkipped, warehouse]);

  const saveCompany = useMutation({ mutationFn: tenantOnboardingService.saveCompany });
  const saveBranch = useMutation({ mutationFn: tenantOnboardingService.saveBranch });
  const saveWarehouse = useMutation({ mutationFn: tenantOnboardingService.saveWarehouse });

  const busy = applyPreset.isPending || saveCompany.isPending || uploadCertificate.isPending || saveBranch.isPending || saveWarehouse.isPending || completeOnboarding.isPending;

  const goNext = async () => {
    try {
      if (step === 0) {
        await applyPreset.mutateAsync(selectedPreset);
      }
      if (step === 1) {
        await saveCompany.mutateAsync(company);
      }
      if (step === 2 && !sunatSkipped && certificateFile) {
        await uploadCertificate.mutateAsync({
          ruc: company.ruc,
          solUser: sunat.solUser,
          solPass: sunat.solPass,
          certificate: certificateFile,
          certificatePassword: sunat.certificatePassword,
        });
      }
      if (step === 3) {
        await saveBranch.mutateAsync(branch);
      }
      if (step === 4) {
        await saveWarehouse.mutateAsync(warehouse);
      }
      if (step === 5) {
        await completeOnboarding.mutateAsync();
        navigate('/dashboard', { replace: true });
        return;
      }
      setStep((value) => Math.min(value + 1, 5) as OnboardingStep);
    } catch (error) {
      toast(error instanceof Error ? error.message : 'No se pudo guardar el paso.', 'error');
    }
  };

  const verifyRuc = async () => {
    const ruc = company.ruc.trim();
    if (ruc.length !== 11 || !SharedUtils.validateRUC(ruc)) {
      toast('Ingresa un RUC válido de 11 dígitos', 'warning');
      return;
    }

    try {
      const response = await consultService.consultRuc(ruc);
      if (!response.data.active) {
        toast('El RUC ingresado no se encuentra activo', 'error');
        return;
      }
      setCompany((current) => ({
        ...current,
        businessName: response.data.name,
        fiscalAddress: response.data.address,
      }));
      toast('Datos SUNAT cargados', 'success');
    } catch {
      toast('No se pudo consultar el RUC', 'error');
    }
  };

  return (
    <main className="tenant-onboarding">
      <section className="tenant-onboarding__card" aria-label="Configuración inicial de eFact">
        <header className="tenant-onboarding__header">
          <div className="tenant-onboarding__brand">
            <span className="tenant-onboarding__logo">e</span>
            <strong>Efact</strong>
          </div>
          <span className="tenant-onboarding__step-count">Step {step + 1} of 6</span>
        </header>

        <nav className="tenant-onboarding__steps" aria-label="Pasos de onboarding">
          <Timeline variant="stepper" orientation="horizontal" aria-label="Pasos de onboarding">
            {STEPS.map((label, index) => (
              <TimelineItem
                key={label}
                status={index < step ? 'complete' : index === step ? 'current' : 'pending'}
                title={label}
              />
            ))}
          </Timeline>
        </nav>

        <div className={`tenant-onboarding__body tenant-onboarding__body--${currentStep.toLowerCase()}`}>
          {currentStep !== 'Done' ? (
            <>
              <h1>{meta.title}</h1>
              <p>{meta.subtitle}</p>
            </>
          ) : null}

          {currentStep === 'Welcome' && (
            <div className="tenant-onboarding__preset-grid">
              {[
                { key: 'retail', title: 'Retail / Tienda', description: 'Ventas rápidas, caja e inventario simple.' },
                { key: 'pharmacy', title: 'Botica / Farmacia', description: 'Inventario estricto y lotes/vencimientos activables.' },
              ].map((option) => (
                <button
                  key={option.key}
                  className={`tenant-onboarding__preset ${selectedPreset === option.key ? 'is-selected' : ''}`}
                  type="button"
                  onClick={() => setSelectedPreset(option.key)}
                >
                  <i className={option.key === 'pharmacy' ? 'ph ph-pill' : 'ph ph-storefront'} aria-hidden="true" />
                  <span className="tenant-onboarding__preset-copy">
                  <strong>{option.title}</strong>
                  <span>{option.description}</span>
                  </span>
                  <span className="tenant-onboarding__preset-check" aria-hidden="true">{selectedPreset === option.key ? '✓' : ''}</span>
                </button>
              ))}
            </div>
          )}

          {currentStep === 'Company' && (
            <div className="tenant-onboarding__grid">
              <FormField label="RUC" className="tenant-onboarding__field tenant-onboarding__field--with-button">
                <div>
                  <TextField value={company.ruc} onChange={(e) => setCompany({ ...company, ruc: e.target.value.replace(/\D/g, '').slice(0, 11) })} />
                  <Button type="button" variant="secondary" onClick={verifyRuc}>Verify with SUNAT</Button>
                </div>
              </FormField>
              <FormField label="Legal name" className="tenant-onboarding__field"><TextField value={company.businessName} onChange={(e) => setCompany({ ...company, businessName: e.target.value })} /></FormField>
              <FormField label="Trade name (optional)" className="tenant-onboarding__field"><TextField value={company.tradeName ?? ''} onChange={(e) => setCompany({ ...company, tradeName: e.target.value })} /></FormField>
              <FormField label="Fiscal address" className="tenant-onboarding__field"><TextField value={company.fiscalAddress} onChange={(e) => setCompany({ ...company, fiscalAddress: e.target.value })} /></FormField>
              <FormField label="Contact phone" className="tenant-onboarding__field"><TextField value={company.phone ?? ''} onChange={(e) => setCompany({ ...company, phone: e.target.value })} /></FormField>
              <FormField label="Currency" className="tenant-onboarding__field"><TextField value="PEN — Sol" disabled /></FormField>
            </div>
          )}

          {currentStep === 'SUNAT' && (
            <div className="tenant-onboarding__grid">
              <FormField label="SOL username" className="tenant-onboarding__field"><TextField value={sunat.solUser} onChange={(e) => setSunat({ ...sunat, solUser: e.target.value })} placeholder="e.g. EFACT01" disabled={sunatSkipped} /></FormField>
              <FormField label="SOL password" className="tenant-onboarding__field"><TextField value={sunat.solPass} onChange={(e) => setSunat({ ...sunat, solPass: e.target.value })} type="password" disabled={sunatSkipped} /></FormField>
              <FormField label="Digital certificate (.pfx or PEM)" className="tenant-onboarding__field tenant-onboarding__dropzone">
                <FileDropzone
                  accept=".pfx,.pem,.crt,.cer"
                  value={certificateFile ? { fileName: certificateFile.name } : null}
                  disabled={sunatSkipped}
                  labels={{
                    prompt: 'Drop .pfx file here or click to browse',
                    hint: 'SUNAT digital certificate in PFX or PEM format.',
                    replace: 'Replace certificate',
                    remove: 'Remove certificate',
                    open: 'Open certificate',
                  }}
                  onFileSelect={setCertificateFile}
                  onClear={() => setCertificateFile(null)}
                />
              </FormField>
              <FormField label="Certificate password" className="tenant-onboarding__field"><TextField value={sunat.certificatePassword} onChange={(e) => setSunat({ ...sunat, certificatePassword: e.target.value })} type="password" placeholder="PFX passphrase" disabled={sunatSkipped} /></FormField>
              <Button className="tenant-onboarding__skip" variant="ghost" type="button" onClick={() => setSunatSkipped((value) => !value)}>
                {sunatSkipped ? 'Configure SUNAT now' : 'Skip SUNAT for now'}
              </Button>
            </div>
          )}

          {currentStep === 'Branch' && (
            <div className="tenant-onboarding__grid">
              <FormField label="Branch name" className="tenant-onboarding__field"><TextField value={branch.name} onChange={(e) => setBranch({ ...branch, name: e.target.value })} placeholder="e.g. San Isidro Main" /></FormField>
              <FormField label="Address" className="tenant-onboarding__field"><TextField value={branch.address ?? ''} onChange={(e) => setBranch({ ...branch, address: e.target.value })} placeholder="Street, district, province" /></FormField>
              <FormField label="Invoice series — Factura" className="tenant-onboarding__field"><TextField value={branch.invoiceSeries} onChange={(e) => setBranch({ ...branch, invoiceSeries: e.target.value.toUpperCase().slice(0, 6) })} /></FormField>
              <FormField label="Invoice series — Boleta" className="tenant-onboarding__field"><TextField value={branch.receiptSeries} onChange={(e) => setBranch({ ...branch, receiptSeries: e.target.value.toUpperCase().slice(0, 6) })} /></FormField>
            </div>
          )}

          {currentStep === 'Warehouse' && (
            <div className="tenant-onboarding__grid">
              <FormField label="Warehouse name" className="tenant-onboarding__field"><TextField value={warehouse.name} onChange={(e) => setWarehouse({ ...warehouse, name: e.target.value })} placeholder="e.g. Stock Room 1" /></FormField>
              <FormField label="Default valuation method" className="tenant-onboarding__field"><TextField value="Promedio Ponderado (PP) — SUNAT" disabled /></FormField>
            </div>
          )}

          {currentStep === 'Done' && (
            <div className="tenant-onboarding__done">
              <div className="tenant-onboarding__check">✓</div>
              <h1>{meta.title}</h1>
              <p>{meta.subtitle}</p>
              <ul>
                <li>Company: {company.businessName || company.ruc}</li>
                <li>Branch: {branch.name}</li>
                <li>Warehouse: {warehouse.name}</li>
                <li>SUNAT: {sunatSkipped ? 'set up later in Settings' : 'configured'}</li>
              </ul>
            </div>
          )}
        </div>

        <footer className="tenant-onboarding__footer">
          <Button type="button" variant="secondary" onClick={() => setStep((value) => Math.max(value - 1, 0) as OnboardingStep)} disabled={step === 0 || busy}>Back</Button>
          <Button type="button" variant="primary" onClick={goNext} disabled={!canContinue || busy} loading={busy}>
            {step === 5 ? 'Enter Efact' : busy ? 'Saving...' : 'Continue'}
          </Button>
        </footer>
      </section>
    </main>
  );
}
