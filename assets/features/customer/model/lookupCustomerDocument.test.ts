import { afterEach, describe, expect, it, vi } from 'vitest';
import { consultService } from '@features/consult';
import { lookupCustomerDocument } from './lookupCustomerDocument';

afterEach(() => vi.restoreAllMocks());
function form() {
  const values: Record<string, unknown> = {
    ruc: '20100000009',
    businessName: '',
    fiscalAddress: '',
  };
  return {
    values,
    getFieldValue: (name: string) => values[name],
    setFieldValue: (name: string, value: unknown) => {
      values[name] = value;
    },
    isCurrent: () => true,
  };
}
const data = {
  id: '20100000009',
  name: 'Empresa de prueba',
  address: 'Dirección de prueba',
  active: true,
};
const response = { data } as never;

describe('customer document lookup', () => {
  it('validates before calling the provider and completes an active RUC', async () => {
    const lookup = vi.spyOn(consultService, 'consultRuc').mockResolvedValue(response);
    const current = form();
    current.values.ruc = '123';
    expect((await lookupCustomerDocument(current))?.tone).toBe('warning');
    current.values.ruc = '20100000001';
    expect((await lookupCustomerDocument(current))?.tone).toBe('warning');
    expect(lookup).not.toHaveBeenCalled();
    current.values.ruc = data.id;
    expect((await lookupCustomerDocument(current))?.message).toContain('RUC activo');
    expect(current.values).toMatchObject({ businessName: data.name, fiscalAddress: data.address });
  });
  it('does not complete an inactive RUC or claim a status absent from the API', async () => {
    vi.spyOn(consultService, 'consultRuc').mockResolvedValue({
      data: { ...data, active: false },
    } as never);
    const current = form();
    const result = await lookupCustomerDocument(current);
    expect(result?.tone).toBe('danger');
    expect(result?.message).not.toContain('Habido');
    expect(current.values.businessName).toBe('');
  });
  it('preserves manual changes and ignores a response for a different form session', async () => {
    let finish!: (value: never) => void;
    vi.spyOn(consultService, 'consultRuc').mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = resolve;
        }),
    );
    const current = form();
    const pending = lookupCustomerDocument(current);
    current.values.businessName = 'Nombre editado';
    finish(response);
    expect((await pending)?.tone).toBe('warning');
    expect(current.values.businessName).toBe('Nombre editado');
    expect(current.values.fiscalAddress).toBe(data.address);
    const obsolete = lookupCustomerDocument(current);
    current.isCurrent = () => false;
    finish(response);
    expect(await obsolete).toBeUndefined();
  });
  it('keeps the fiscal address when consulting a DNI and surfaces failure for retry', async () => {
    const current = form();
    current.values.ruc = '12345678';
    current.values.fiscalAddress = 'Dirección manual';
    vi.spyOn(consultService, 'consultDni')
      .mockRejectedValueOnce(new Error())
      .mockResolvedValueOnce({ data: { ...data, id: '12345678' } } as never);
    expect((await lookupCustomerDocument(current))?.tone).toBe('danger');
    expect((await lookupCustomerDocument(current))?.message).toContain('DNI encontrado');
    expect(current.values.fiscalAddress).toBe('Dirección manual');
  });
});
