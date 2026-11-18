import { Combobox, TextAreaField, TextField, Toggle, Button } from '@nubitio/ui';
import { useEffect, useRef, useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import toast from '@shared/lib/SharedUtils';
import { applyServerErrors } from '@shared/lib/formErrors';
import { EntityDrawer } from '@shared/ui/entity-form/EntityDrawer';
import { Field, FormSection } from '@shared/ui/entity-form/Field';
import { loadPriceLists, useSaveCustomer } from './api/customerData';
import {
  CUSTOMER_FIELDS,
  emptyCustomerForm,
  priceListOf,
  toFormValues,
  toPayload,
  validateBusinessName,
  validateContact,
  validateDocument,
  validateEmail,
  validatePhone,
  type Customer,
  type CustomerFormValues,
} from './form/customerForm';
import { lookupCustomerDocument } from './model/lookupCustomerDocument';

/**
 * `key` changes on every opening, so each one mounts a fresh form: nothing to reset, and a lookup
 * response that arrives after the drawer was closed lands on a form that no longer exists.
 */
export type CustomerDrawerState =
  | { mode: 'closed' }
  | { mode: 'create'; key: number }
  | { mode: 'edit'; key: number; customer: Customer };

interface Feedback {
  tone: 'success' | 'warning' | 'danger';
  message: string;
  /** The document the message is about; it is hidden as soon as the field holds another one. */
  forDocument: string;
}

const HINTS = {
  ruc: 'Ingresa 8 dígitos para DNI u 11 para RUC. Puedes consultar sus datos.',
  email: 'Correo de contacto del cliente.',
  contact: 'Persona a quien se dirige el recordatorio de pago. Opcional.',
  active: 'Disponible para seleccionarlo en ventas.',
};

export interface CustomerDrawerProps {
  state: CustomerDrawerState;
  onClose: () => void;
}

export function CustomerDrawer({ state, onClose }: CustomerDrawerProps) {
  if (state.mode === 'closed') return null;
  return <OpenCustomerDrawer key={state.key} state={state} onClose={onClose} />;
}

function OpenCustomerDrawer({
  state,
  onClose,
}: {
  state: Exclude<CustomerDrawerState, { mode: 'closed' }>;
  onClose: () => void;
}) {
  const editing = state.mode === 'edit' ? state.customer : null;
  const save = useSaveCustomer();

  const {
    register,
    handleSubmit,
    control,
    setError,
    setValue,
    getValues,
    formState: { errors },
  } = useForm<CustomerFormValues>({
    defaultValues: editing ? toFormValues(editing) : emptyCustomerForm(),
    mode: 'onSubmit',
    reValidateMode: 'onChange',
  });

  const [formError, setFormError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<Feedback | null>(null);
  /** The document a lookup is running for; the button is busy only while the field still holds it. */
  const [lookingUp, setLookingUp] = useState<string | null>(null);

  /** False once this drawer is closed: late lookup responses must not touch it. */
  const alive = useRef(true);
  useEffect(() => {
    alive.current = true;
    return () => {
      alive.current = false;
    };
  }, []);

  // useWatch (not watch): subscribes this component to the field and stays safe for the React Compiler.
  const documentValue = useWatch({ control, name: 'ruc' }).trim();
  const consulting = lookingUp !== null && lookingUp === documentValue;
  // A message is about one document; once the field holds another, it no longer applies.
  const visibleFeedback = feedback && feedback.forDocument === documentValue ? feedback : null;

  const consult = async () => {
    const doc = getValues('ruc').trim();
    setFeedback(null);
    setLookingUp(doc);

    const result = await lookupCustomerDocument({
      getFieldValue: (name) => getValues(name as keyof CustomerFormValues),
      setFieldValue: (name, value) =>
        setValue(name as keyof CustomerFormValues, value as never, {
          shouldDirty: true,
          shouldValidate: true,
        }),
      isCurrent: () => alive.current,
    });

    if (!alive.current) return;
    setLookingUp((current) => (current === doc ? null : current));
    if (result) setFeedback({ ...result, forDocument: doc });
  };

  const submit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      await save.mutateAsync({
        ...(editing ? { id: editing.id } : {}),
        payload: toPayload(values),
      });
      toast('Cliente guardado', 'success');
      onClose();
    } catch (error) {
      setFormError(applyServerErrors(error, setError, CUSTOMER_FIELDS).formError);
    }
  });

  const seededPriceList = editing ? priceListOf(editing) : null;

  return (
    <EntityDrawer
      title={editing ? 'Editar cliente' : 'Nuevo cliente'}
      isOpen
      onClose={onClose}
      onSubmit={submit}
      busy={save.isPending}
      submitLabel="Guardar cliente"
      formError={formError}
    >
      <FormSection title="Identificación">
        <Field label="RUC/DNI" required hint={HINTS.ruc} error={errors.ruc?.message} wide>
          {(control) => (
            <div className="erp-field__row">
              <TextField
                {...control}
                inputMode="numeric"
                maxLength={11}
                autoComplete="off"
                {...register('ruc', { validate: validateDocument })}
              />
              <Button
                type="button"
                variant="secondary"
                icon="ph ph-magnifying-glass"
                aria-label="Consultar RUC o DNI"
                loading={consulting}
                onClick={consult}
              >
                {consulting ? 'Consultando…' : 'Consultar'}
              </Button>
            </div>
          )}
        </Field>
        {visibleFeedback && (
          <p
            className="erp-action-feedback"
            data-tone={visibleFeedback.tone}
            role={visibleFeedback.tone === 'danger' ? 'alert' : 'status'}
          >
            {visibleFeedback.message}
          </p>
        )}
        <Field label="Razón social" required error={errors.businessName?.message} wide>
          {(control) => (
            <TextField
              {...control}
              {...register('businessName', { validate: validateBusinessName })}
            />
          )}
        </Field>
        <Field label="Dirección fiscal" wide>
          {(control) => <TextAreaField {...control} rows={3} {...register('fiscalAddress')} />}
        </Field>
      </FormSection>

      <FormSection title="Contacto">
        <Field label="Correo" hint={HINTS.email} error={errors.email?.message}>
          {(control) => (
            <TextField
              {...control}
              type="email"
              {...register('email', { validate: validateEmail })}
            />
          )}
        </Field>
        <Field label="Teléfono" error={errors.phone?.message}>
          {(control) => (
            <TextField
              {...control}
              type="tel"
              {...register('phone', { validate: validatePhone })}
            />
          )}
        </Field>
        <Field
          label="Contacto de cobranza"
          hint={HINTS.contact}
          error={errors.collectionContactName?.message}
        >
          {(control) => (
            <TextField
              {...control}
              {...register('collectionContactName', { validate: validateContact })}
            />
          )}
        </Field>
      </FormSection>

      <FormSection title="Condiciones">
        <Controller
          control={control}
          name="priceList"
          render={({ field }) => (
            <Combobox
              label="Tarifa"
              options={
                seededPriceList
                  ? [{ value: seededPriceList.iri, label: seededPriceList.label }]
                  : []
              }
              loadOptions={loadPriceLists}
              value={field.value}
              onChange={field.onChange}
              onBlur={field.onBlur}
              clearable
            />
          )}
        />
        <Controller
          control={control}
          name="isActive"
          render={({ field }) => (
            <div className="erp-field">
              <Toggle label="Cliente activo" checked={field.value} onChange={field.onChange} />
              <p className="erp-field__hint">{HINTS.active}</p>
            </div>
          )}
        />
      </FormSection>
    </EntityDrawer>
  );
}
