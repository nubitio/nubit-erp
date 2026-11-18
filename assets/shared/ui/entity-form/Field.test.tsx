import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { Field, FormSection, type FieldControlProps } from './Field';

const input = (control: FieldControlProps) => <input {...control} />;

describe('Field', () => {
  afterEach(cleanup);

  it('names the control by its label alone, so getByLabel(…, { exact: true }) finds it', () => {
    render(
      <Field
        label="RUC/DNI"
        required
        hint="Ingresa 8 dígitos para DNI u 11 para RUC."
        error="Obligatorio"
      >
        {input}
      </Field>,
    );

    // not "RUC/DNI * Ingresa 8 dígitos… Obligatorio": hint and error are descriptions, not the name
    expect(screen.getByLabelText('RUC/DNI', { exact: true })).toBeTruthy();
  });

  it('marks the control required without putting the marker in the label text', () => {
    render(
      <Field label="Razón social" required>
        {input}
      </Field>,
    );

    const control = screen.getByLabelText('Razón social', { exact: true });
    expect(control.getAttribute('aria-required')).toBe('true');
    expect(screen.getByText('Razón social').getAttribute('data-required')).toBe('true');
    expect(screen.queryByText('*')).toBeNull();
  });

  it('links the hint, then the error, with aria-describedby and marks the control invalid', () => {
    render(
      <Field label="RUC/DNI" hint="Ayuda" error="El RUC no es válido">
        {input}
      </Field>,
    );

    const control = screen.getByLabelText('RUC/DNI');
    expect(control.getAttribute('aria-invalid')).toBe('true');
    expect(control.getAttribute('aria-describedby')).toMatch(/-hint .*-error$/);
    expect(
      document.getElementById(control.getAttribute('aria-describedby')!.split(' ')[0]!)
        ?.textContent,
    ).toBe('Ayuda');
    expect(screen.getByRole('alert').textContent).toBe('El RUC no es válido');
  });

  it('describes the control by the hint only when there is no error, and is not invalid', () => {
    render(
      <Field label="Correo" hint="Correo de contacto.">
        {input}
      </Field>,
    );

    const control = screen.getByLabelText('Correo');
    expect(control.getAttribute('aria-invalid')).toBeNull();
    expect(control.getAttribute('aria-describedby')).toMatch(/-hint$/);
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('describes nothing when there is neither hint nor error', () => {
    render(<Field label="Teléfono">{input}</Field>);

    expect(screen.getByLabelText('Teléfono').getAttribute('aria-describedby')).toBeNull();
  });

  it('gives every field its own ids', () => {
    render(
      <>
        <Field label="A" hint="a">
          {input}
        </Field>
        <Field label="B" hint="b">
          {input}
        </Field>
      </>,
    );

    expect(screen.getByLabelText('A').id).not.toBe(screen.getByLabelText('B').id);
  });
});

describe('FormSection', () => {
  afterEach(cleanup);

  it('groups its fields under a legend', () => {
    render(
      <FormSection title="Contacto">
        <Field label="Correo">{input}</Field>
      </FormSection>,
    );

    expect(screen.getByRole('group', { name: 'Contacto' })).toBeTruthy();
  });
});
