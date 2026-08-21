import React, { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Button, FileDropzone, FormField, TextField, Timeline, TimelineItem } from '@nubitio/ui';
import toast, { SharedUtils } from '@shared/lib/SharedUtils';
import {
  useApplyTenantPreset,
  useCompleteTenantOnboarding,
  useFeature,
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
import { OnboardingHelp } from './OnboardingHelp';
import './TenantOnboardingGate.scss';

const Assistant = lazy(() => import('@features/assistant/Assistant'));

const STEPS = ['Welcome', 'Company', 'SUNAT', 'Branch', 'Warehouse', 'Done'] as const;

type StepKey = (typeof STEPS)[number];

const STEP_LABEL: Record<StepKey, string> = {
  Welcome: 'Giro',
  Company: 'Empresa',
  SUNAT: 'SUNAT',
  Branch: 'Local',
  Warehouse: 'Almacén',
  Done: 'Listo',
};

const STEP_META: Record<StepKey, { title: string; subtitle: string }> = {
  Welcome: {
    title: 'Elige tu tipo de negocio',
    subtitle: 'Ajustaremos inventario, facturación y menús para que eFact arranque simple.',
  },
  Company: {
    title: 'Datos de tu empresa',
    subtitle: 'Ingresa el RUC para completar razón social y domicilio fiscal desde SUNAT.',
  },
  SUNAT: {
    title: 'Conexión con SUNAT',
    subtitle: 'Ingresa tus credenciales SOL y el certificado para emitir comprobantes electrónicos.',
  },
  Branch: {
    title: 'Tu primer local',
    subtitle: 'Puedes agregar más sucursales después desde Configuración.',
  },
  Warehouse: {
    title: 'Tu almacén',
    subtitle: 'Queda vinculado a tu local. Puedes agregar más almacenes después.',
  },
  Done: {
    title: '¡Listo para vender!',
    subtitle: 'Tu cuenta de eFact quedó configurada. Resumen:',
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
  const assistantEnabled = useFeature('ai_assistant');

  useEffect(() => {
    const onFill = (event: Event) => {
      const payload = (event as CustomEvent<Record<string, unknown>>).detail ?? {};
      setCompany((current) => ({
        ...current,
        ruc: typeof payload.ruc === 'string' ? payload.ruc.replace(/\D/g, '').slice(0, 11) : current.ruc,
        businessName: typeof payload.businessName === 'string' ? payload.businessName : current.businessName,
        tradeName: typeof payload.tradeName === 'string' ? payload.tradeName : current.tradeName,
        fiscalAddress: typeof payload.fiscalAddress === 'string' ? payload.fiscalAddress : current.fiscalAddress,
        phone: typeof payload.phone === 'string' ? payload.phone : current.phone,
      }));
      setBranch((current) => ({
        ...current,
        name: typeof payload.branchName === 'string' ? payload.branchName : current.name,
        invoiceSeries: typeof payload.invoiceSeries === 'string' ? payload.invoiceSeries : current.invoiceSeries,
        receiptSeries: typeof payload.boletaSeries === 'string' ? payload.boletaSeries : current.receiptSeries,
      }));
      setWarehouse((current) => ({
        ...current,
        name: typeof payload.warehouseName === 'string' ? payload.warehouseName : current.name,
      }));
    };
    window.addEventListener('efact:onboarding-fill', onFill);
    return () => window.removeEventListener('efact:onboarding-fill', onFill);
  }, []);

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
          <span className="tenant-onboarding__step-count">Paso {step + 1} de 6</span>
        </header>

        <nav className="tenant-onboarding__steps" aria-label="Pasos de onboarding">
          <Timeline variant="stepper" orientation="horizontal" aria-label="Pasos de onboarding">
            {STEPS.map((label, index) => (
              <TimelineItem
                key={label}
                status={index < step ? 'complete' : index === step ? 'current' : 'pending'}
                title={STEP_LABEL[label]}
              />
            ))}
          </Timeline>
        </nav>

        <div className={`tenant-onboarding__body tenant-onboarding__body--${currentStep.toLowerCase()}`}>
          <div className="tenant-onboarding__main">
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
                  <Button type="button" variant="secondary" onClick={verifyRuc}>Consultar SUNAT</Button>
                </div>
              </FormField>
              <FormField label="Razón social" className="tenant-onboarding__field"><TextField value={company.businessName} onChange={(e) => setCompany({ ...company, businessName: e.target.value })} /></FormField>
              <FormField label="Nombre comercial (opcional)" className="tenant-onboarding__field"><TextField value={company.tradeName ?? ''} onChange={(e) => setCompany({ ...company, tradeName: e.target.value })} /></FormField>
              <FormField label="Domicilio fiscal" className="tenant-onboarding__field"><TextField value={company.fiscalAddress} onChange={(e) => setCompany({ ...company, fiscalAddress: e.target.value })} /></FormField>
              <FormField label="Teléfono" className="tenant-onboarding__field"><TextField value={company.phone ?? ''} onChange={(e) => setCompany({ ...company, phone: e.target.value })} /></FormField>
              <FormField label="Moneda" className="tenant-onboarding__field"><TextField value="PEN — Sol" disabled /></FormField>
            </div>
          )}

          {currentStep === 'SUNAT' && (
            <div className="tenant-onboarding__grid">
              <FormField label="Usuario SOL" className="tenant-onboarding__field"><TextField value={sunat.solUser} onChange={(e) => setSunat({ ...sunat, solUser: e.target.value })} placeholder="Ej. MODDATOS" disabled={sunatSkipped} /></FormField>
              <FormField label="Clave SOL" className="tenant-onboarding__field"><TextField value={sunat.solPass} onChange={(e) => setSunat({ ...sunat, solPass: e.target.value })} type="password" disabled={sunatSkipped} /></FormField>
              <FormField label="Certificado digital (.pfx o PEM)" className="tenant-onboarding__field tenant-onboarding__dropzone">
                <FileDropzone
                  accept=".pfx,.pem,.crt,.cer"
                  value={certificateFile ? { fileName: certificateFile.name } : null}
                  disabled={sunatSkipped}
                  labels={{
                    prompt: 'Suelta el archivo .pfx o haz clic para buscarlo',
                    hint: 'Certificado digital SUNAT en formato PFX o PEM.',
                    replace: 'Cambiar certificado',
                    remove: 'Quitar certificado',
                    open: 'Abrir certificado',
                  }}
                  onFileSelect={setCertificateFile}
                  onClear={() => setCertificateFile(null)}
                />
              </FormField>
              <FormField label="Contraseña del certificado" className="tenant-onboarding__field"><TextField value={sunat.certificatePassword} onChange={(e) => setSunat({ ...sunat, certificatePassword: e.target.value })} type="password" placeholder="Clave del PFX" disabled={sunatSkipped} /></FormField>
              <Button className="tenant-onboarding__skip" variant="ghost" type="button" onClick={() => setSunatSkipped((value) => !value)}>
                {sunatSkipped ? 'Configurar SUNAT ahora' : 'Configurar SUNAT más tarde'}
              </Button>
            </div>
          )}

          {currentStep === 'Branch' && (
            <div className="tenant-onboarding__grid">
              <FormField label="Nombre del local" className="tenant-onboarding__field"><TextField value={branch.name} onChange={(e) => setBranch({ ...branch, name: e.target.value })} placeholder="Ej. San Isidro" /></FormField>
              <FormField label="Dirección" className="tenant-onboarding__field"><TextField value={branch.address ?? ''} onChange={(e) => setBranch({ ...branch, address: e.target.value })} placeholder="Calle, distrito, provincia" /></FormField>
              <FormField label="Serie de factura" className="tenant-onboarding__field"><TextField value={branch.invoiceSeries} onChange={(e) => setBranch({ ...branch, invoiceSeries: e.target.value.toUpperCase().slice(0, 6) })} /></FormField>
              <FormField label="Serie de boleta" className="tenant-onboarding__field"><TextField value={branch.receiptSeries} onChange={(e) => setBranch({ ...branch, receiptSeries: e.target.value.toUpperCase().slice(0, 6) })} /></FormField>
            </div>
          )}

          {currentStep === 'Warehouse' && (
            <div className="tenant-onboarding__grid">
              <FormField label="Nombre del almacén" className="tenant-onboarding__field"><TextField value={warehouse.name} onChange={(e) => setWarehouse({ ...warehouse, name: e.target.value })} placeholder="Ej. Almacén principal" /></FormField>
              <FormField label="Método de valuación" className="tenant-onboarding__field"><TextField value="Promedio ponderado (PP) — SUNAT" disabled /></FormField>
            </div>
          )}

          {currentStep === 'Done' && (
            <div className="tenant-onboarding__done">
              <div className="tenant-onboarding__check">✓</div>
              <h1>{meta.title}</h1>
              <p>{meta.subtitle}</p>
              <ul>
                <li>Empresa: {company.businessName || company.ruc}</li>
                <li>Local: {branch.name}</li>
                <li>Almacén: {warehouse.name}</li>
                <li>SUNAT: {sunatSkipped ? 'se configura después en Ajustes' : 'configurada'}</li>
              </ul>
            </div>
          )}
          </div>
          <div className="tenant-onboarding__aside">
            <OnboardingHelp step={currentStep} />
            {assistantEnabled ? (
              <div className="tenant-onboarding__assistant">
                <Suspense fallback={null}>
                  <Assistant showToolbar={false} screen="onboarding" entityType="onboarding" entityId={currentStep} />
                </Suspense>
              </div>
            ) : null}
          </div>
        </div>

        <footer className="tenant-onboarding__footer">
          <Button type="button" variant="secondary" onClick={() => setStep((value) => Math.max(value - 1, 0) as OnboardingStep)} disabled={step === 0 || busy}>Atrás</Button>
          <Button type="button" variant="primary" onClick={goNext} disabled={!canContinue || busy} loading={busy}>
            {step === 5 ? 'Entrar a eFact' : busy ? 'Guardando…' : 'Continuar'}
          </Button>
        </footer>
      </section>
    </main>
  );
}
