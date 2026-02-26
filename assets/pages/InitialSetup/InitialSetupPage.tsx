import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Stepper } from 'devextreme-react/stepper';
import Button from 'devextreme-react/button';
import LoadIndicator from 'devextreme-react/load-indicator';
import Form, {
  FormRef,
  GroupItem,
  Item,
  PatternRule,
  RequiredRule,
  StringLengthRule,
} from 'devextreme-react/form';

import './InitialSetupPage.scss';
import { SetupPayload, setupService } from '../../features/setup';
import { useScreenSizeClass } from '../../utils/media-query';

const steps = [
  {
    id: 'admin',
    title: 'Cuenta administrador',
    description: 'Define las credenciales del usuario raíz.',
  },
  { id: 'company', title: 'Empresa', description: 'Datos fiscales y de contacto de la compañía.' },
  { id: 'branch', title: 'Sucursal principal', description: 'Primer local desde el que operarás.' },
] as const;

const defaultFormData: SetupPayload = {
  adminUsername: '',
  adminPassword: '',
  companyRuc: '',
  companyBusinessName: '',
  companyTradeName: '',
  companyFiscalAddress: '',
  companyPhone: '',
  branchName: '',
  branchAddress: '',
  branchPhone: '',
};

export const InitialSetupPage = () => {
  const navigate = useNavigate();
  const screenSizeClass = useScreenSizeClass();
  const formRef = useRef<FormRef | null>(null);

  const [formData, setFormData] = useState<SetupPayload>(defaultFormData);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [stepIndex, setStepIndex] = useState(0);

  useEffect(() => {
    const subscription = setupService.getStatus().subscribe({
      next: (response) => {
        const data = response.data;
        if (data.configured || data.userCount > 0) {
          navigate('/');
          return;
        }
        setLoading(false);
      },
      error: () => setLoading(false),
    });

    return () => subscription.unsubscribe();
  }, [navigate]);

  const handleFieldChanged = (e: any) => {
    setFormData((prev) => ({ ...prev, [e.dataField]: e.value ?? '' }));
  };

  const focusFirstField = (index: number) => {
    const fieldByStep = ['adminUsername', 'companyRuc', 'branchName'];
    const field = fieldByStep[index] ?? fieldByStep[0];
    const editor = formRef.current?.instance().getEditor(field);
    editor?.focus();
  };

  const validateStep = (): boolean => {
    const result = formRef.current?.instance().validate();
    return result?.isValid ?? true;
  };

  const changeStep = (index: number) => {
    if (index === stepIndex) return;
    if (index > stepIndex && !validateStep()) {
      return;
    }
    setStepIndex(index);
    requestAnimationFrame(() => focusFirstField(index));
  };

  const goNext = () => {
    if (!validateStep()) {
      return;
    }
    if (stepIndex < steps.length - 1) {
      setStepIndex((prev) => prev + 1);
      requestAnimationFrame(() => focusFirstField(stepIndex + 1));
    } else {
      submit();
    }
  };

  const goBack = () => {
    if (stepIndex > 0) {
      setStepIndex((prev) => prev - 1);
      requestAnimationFrame(() => focusFirstField(stepIndex - 1));
    }
  };

  const submit = () => {
    setSubmitting(true);
    setupService.complete(formData).subscribe({
      next: (response) => {
        setStatus(response.data);
        setSubmitting(false);
        navigate('/login');
      },
      error: (err) => {
        const message = err?.response?.data?.detail || 'No se pudo completar la configuración.';
        console.error(message);
        setSubmitting(false);
      },
    });
  };

  if (loading) {
    return (
      <div className={`initial-setup-page ${screenSizeClass}`}>
        <div className="loading">
          <LoadIndicator visible width={48} height={48} />
          <p>Preparando asistente...</p>
        </div>
      </div>
    );
  }

  return (
    <div className={`initial-setup-page ${screenSizeClass}`}>
      <div className="setup-shell">
        <header className="setup-header">
          <div>
            <p className="eyebrow">Asistente de primer uso</p>
          </div>
        </header>

        <Stepper
          className="setup-stepper"
          items={steps.map((step, index) => ({
            id: step.id,
            text: index + 1,
            label: step.title,
            icon: index < stepIndex ? 'check' : undefined,
          }))}
          selectedIndex={stepIndex}
          disabled={submitting}
          onSelectedIndexChange={changeStep}
        />

        <div className="setup-card">
          <div className="step-caption">
            <div className="step-kicker">
              Paso {stepIndex + 1} de {steps.length}
            </div>
            <div className="step-title">{steps[stepIndex].title}</div>
            <div className="step-desc">{steps[stepIndex].description}</div>
          </div>

          <Form
            ref={formRef}
            formData={formData}
            onFieldDataChanged={handleFieldChanged}
            labelMode="floating"
            colCountByScreen={{ xs: 1, sm: 2, md: 2 }}
          >
            {stepIndex === 0 && (
              <GroupItem colCountByScreen={{ xs: 1, sm: 2, md: 2 }} caption="Credenciales">
                <Item dataField="adminUsername" label={{ text: 'Usuario administrador' }}>
                  <RequiredRule message="Usuario administrador es obligatorio" />
                </Item>
                <Item
                  dataField="adminPassword"
                  label={{ text: 'Contraseña' }}
                  editorOptions={{ mode: 'password' }}
                >
                  <RequiredRule message="La contraseña es obligatoria" />
                  <StringLengthRule
                    min={6}
                    message="La contraseña debe tener al menos 6 caracteres"
                  />
                </Item>
              </GroupItem>
            )}

            {stepIndex === 1 && (
              <GroupItem colCountByScreen={{ xs: 1, sm: 2, md: 2 }} caption="Datos de la empresa">
                <Item
                  dataField="companyRuc"
                  label={{ text: 'RUC' }}
                  editorOptions={{ maxLength: 11 }}
                >
                  <RequiredRule message="El RUC es obligatorio" />
                  <StringLengthRule min={11} max={11} message="El RUC debe tener 11 dígitos" />
                  <PatternRule pattern={'^[0-9]+$'} message="Solo números" />
                </Item>
                <Item dataField="companyBusinessName" label={{ text: 'Razón social' }}>
                  <RequiredRule message="Razón social es obligatoria" />
                </Item>
                <Item dataField="companyTradeName" label={{ text: 'Nombre comercial' }} />
                <Item dataField="companyFiscalAddress" label={{ text: 'Domicilio fiscal' }}>
                  <RequiredRule message="El domicilio fiscal es obligatorio" />
                </Item>
                <Item dataField="companyPhone" label={{ text: 'Teléfono' }} />
              </GroupItem>
            )}

            {stepIndex === 2 && (
              <GroupItem colCountByScreen={{ xs: 1, sm: 2, md: 2 }} caption="Sucursal principal">
                <Item dataField="branchName" label={{ text: 'Nombre de la sucursal' }}>
                  <RequiredRule message="El nombre de la sucursal es obligatorio" />
                </Item>
                <Item dataField="branchAddress" label={{ text: 'Dirección' }} />
                <Item dataField="branchPhone" label={{ text: 'Teléfono' }} />
              </GroupItem>
            )}
          </Form>

          <div className="actions">
            <Button
              type="normal"
              stylingMode="outlined"
              onClick={goBack}
              disabled={stepIndex === 0 || submitting}
              text="Atrás"
            />
            <Button
              type="default"
              stylingMode="contained"
              onClick={goNext}
              disabled={submitting}
              icon={
                submitting ? undefined : stepIndex === steps.length - 1 ? 'check' : 'chevronnext'
              }
              text={
                submitting
                  ? 'Guardando...'
                  : stepIndex === steps.length - 1
                    ? 'Finalizar configuración'
                    : 'Siguiente'
              }
            />
          </div>
          {submitting && (
            <div className="setup-overlay">
              <div className="overlay-content">
                <LoadIndicator visible width={32} height={32} />
                <span>Guardando configuración...</span>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
