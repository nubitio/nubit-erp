import { useEffect, useRef, useState } from 'react';
import { Alert, AppDialog, Button, FormField, FormLayout, Grid, Row, TextField } from '@nubitio/ui';
import { mapApiViolations } from '@nubitio/crud';
import { httpClient } from '@shared/api/HttpClient';
import { createEndpoints } from '@shared/api/endpoints';
import { SharedUtils } from '@shared/lib/SharedUtils';
import toast from '@shared/lib/SharedUtils';
import { lookupCustomerDocument } from '@features/customer/model/lookupCustomerDocument';

const FORM_ID = 'pos-quick-customer-form';
const customerEndpoints = createEndpoints('customers');

interface Props {
  onClose: () => void;
  /** Receives the raw API customer so the POS can select it immediately. */
  onCreated: (customer: Record<string, unknown>) => void;
}

type Values = { ruc: string; businessName: string; phone: string; email: string };

export const QuickCustomerDialog = ({ onClose, onCreated }: Props) => {
  const [values, setValues] = useState<Values>({
    ruc: '',
    businessName: '',
    phone: '',
    email: '',
  });
  const [address, setAddress] = useState('');
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<string, string>>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [looking, setLooking] = useState(false);
  const [feedback, setFeedback] = useState<{
    tone: 'danger' | 'warning' | 'info';
    message: string;
  } | null>(null);
  const valuesRef = useRef(values);
  const addressRef = useRef(address);
  useEffect(() => {
    valuesRef.current = values;
    addressRef.current = address;
  }, [values, address]);

  const set = (key: keyof Values, value: string) => {
    setValues((v) => ({ ...v, [key]: value }));
    setFieldErrors((e) => (e[key] ? { ...e, [key]: undefined } : e));
  };

  const lookup = async () => {
    setLooking(true);
    setFeedback(null);
    const result = await lookupCustomerDocument({
      getFieldValue: (name: string) =>
        name === 'fiscalAddress' ? addressRef.current : valuesRef.current[name as keyof Values],
      setFieldValue: (name: string, value: unknown) => {
        if (name === 'fiscalAddress') setAddress(String(value ?? ''));
        else set(name as keyof Values, String(value ?? ''));
      },
    } as Parameters<typeof lookupCustomerDocument>[0]);
    setLooking(false);
    if (result)
      setFeedback({
        tone: result.tone === 'success' ? 'info' : result.tone,
        message: result.message,
      });
  };

  const submit = async () => {
    if (saving) return;
    const ruc = values.ruc.trim();
    if (!/^\d{8}$|^\d{11}$/.test(ruc) || (ruc.length === 11 && !SharedUtils.validateRUC(ruc))) {
      toast('Ingresa un DNI (8 dígitos) o un RUC válido (11 dígitos)', 'error');
      return;
    }
    if (!values.businessName.trim()) {
      toast('Ingresa el nombre o razón social', 'error');
      return;
    }
    setSaving(true);
    setFormError(null);
    setFieldErrors({});
    try {
      const { data } = await httpClient.post<Record<string, unknown>>(customerEndpoints.list(), {
        ruc,
        businessName: values.businessName.trim(),
        fiscalAddress: address.trim() || null,
        phone: values.phone.trim() || null,
        email: values.email.trim() || null,
        isActive: true,
      });
      toast('Cliente creado', 'success');
      onCreated(data);
    } catch (error) {
      // A 422 with violations (e.g. duplicate RUC/DNI) is not toasted by HttpClient: this form
      // owns showing it. Other failures were already reported by HttpClient.
      const violations = (error as { data?: { violations?: unknown } } | null)?.data?.violations;
      if (Array.isArray(violations)) {
        const mapped = mapApiViolations(violations);
        const known = new Set<string>(['ruc', 'businessName', 'fiscalAddress', 'phone', 'email']);
        const placed: Record<string, string> = {};
        const unplaced = [...mapped.unassigned];
        for (const [path, message] of Object.entries(mapped.fieldErrors)) {
          if (known.has(path)) placed[path] = message;
          else unplaced.push(message);
        }
        setFieldErrors(placed);
        setFormError(unplaced.length > 0 ? unplaced.join(' ') : null);
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppDialog
      open
      title="Nuevo cliente"
      onClose={() => {
        if (!saving) onClose();
      }}
      width={480}
      closeOnOutsideClick={false}
      closeOnEscape={!saving}
      showCloseButton={!saving}
      footer={
        <>
          <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            variant="primary"
            loading={saving}
            data-testid="pos-quick-customer-submit"
          >
            Guardar y usar en la venta
          </Button>
        </>
      }
    >
      <FormLayout
        id={FORM_ID}
        data-testid="pos-quick-customer-form"
        noValidate
        onSubmit={(e) => {
          e.preventDefault();
          void submit();
        }}
      >
        <FormField label="RUC/DNI" required htmlFor="pos-qc-ruc" error={fieldErrors.ruc}>
          <Row gap={2}>
            <TextField
              id="pos-qc-ruc"
              invalid={Boolean(fieldErrors.ruc)}
              value={values.ruc}
              maxLength={11}
              inputMode="numeric"
              required
              autoFocus
              onChange={(e) => set('ruc', e.target.value.replace(/\D/g, ''))}
              placeholder="8 dígitos (DNI) u 11 (RUC)"
            />
            <Button
              type="button"
              variant="secondary"
              icon="ph ph-magnifying-glass"
              loading={looking}
              onClick={() => void lookup()}
            >
              Consultar
            </Button>
          </Row>
        </FormField>
        {formError && <Alert tone="danger">{formError}</Alert>}
        {feedback && <Alert tone={feedback.tone}>{feedback.message}</Alert>}
        <FormField
          label="Nombre / Razón social"
          required
          htmlFor="pos-qc-name"
          error={fieldErrors.businessName}
        >
          <TextField
            id="pos-qc-name"
            invalid={Boolean(fieldErrors.businessName)}
            value={values.businessName}
            required
            onChange={(e) => set('businessName', e.target.value)}
          />
        </FormField>
        <FormField
          label="Dirección fiscal"
          htmlFor="pos-qc-address"
          error={fieldErrors.fiscalAddress}
        >
          <TextField
            id="pos-qc-address"
            invalid={Boolean(fieldErrors.fiscalAddress)}
            value={address}
            onChange={(e) => setAddress(e.target.value)}
          />
        </FormField>
        <Grid cols={2} gap={3}>
          <FormField label="Teléfono" htmlFor="pos-qc-phone" error={fieldErrors.phone}>
            <TextField
              id="pos-qc-phone"
              invalid={Boolean(fieldErrors.phone)}
              value={values.phone}
              onChange={(e) => set('phone', e.target.value)}
            />
          </FormField>
          <FormField label="Correo" htmlFor="pos-qc-email" error={fieldErrors.email}>
            <TextField
              id="pos-qc-email"
              invalid={Boolean(fieldErrors.email)}
              type="email"
              value={values.email}
              onChange={(e) => set('email', e.target.value)}
            />
          </FormField>
        </Grid>
      </FormLayout>
    </AppDialog>
  );
};
