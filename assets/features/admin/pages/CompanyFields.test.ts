import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CompanyFields } from './CompanyFields';

const { consultRuc, toast, validateRUC, imageFormatter } = vi.hoisted(() => ({
  consultRuc: vi.fn(),
  toast: vi.fn(),
  validateRUC: vi.fn(() => true),
  imageFormatter: vi.fn(),
}));

vi.mock('@features/consult', () => ({
  consultService: {
    consultRuc,
  },
}));

vi.mock('@shared/lib/SharedUtils', () => ({
  __esModule: true,
  default: toast,
  SharedUtils: {
    validateRUC,
    imageFormatter,
  },
}));

describe('CompanyFields', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    validateRUC.mockReturnValue(true);
  });

  it('ruc search button reads value from formRef and populates fields', () => {
    const setFieldValue = vi.fn();
    const getFieldValue = vi.fn(() => '20123456789');
    consultRuc.mockReturnValue({
      subscribe: ({ next }: { next: (response: unknown) => void }) =>
        next({
          data: {
            active: true,
            name: 'ACME SAC',
            address: 'Av. Principal 123',
          },
        }),
    });

    const formRef = {
      current: {
        getFieldValue,
        setFieldValue,
      },
    } as any;

    const fields = CompanyFields(formRef);
    const rucField = fields.find((field) => field.name === 'ruc') as
      | ({ buttons?: Array<{ options?: { onClick?: () => void } }> } & (typeof fields)[number])
      | undefined;
    const onClick = rucField?.buttons?.[0]?.options?.onClick;

    expect(onClick).toBeTypeOf('function');

    onClick?.();

    expect(getFieldValue).toHaveBeenCalledWith('ruc');
    expect(validateRUC).toHaveBeenCalledWith('20123456789');
    expect(consultRuc).toHaveBeenCalledWith('20123456789');
    expect(setFieldValue).toHaveBeenCalledWith('businessName', 'ACME SAC');
    expect(setFieldValue).toHaveBeenCalledWith('tradeName', 'ACME SAC');
    expect(setFieldValue).toHaveBeenCalledWith('fiscalAddress', 'Av. Principal 123');
    expect(toast).not.toHaveBeenCalled();
  });

  it('shows warning when ruc is invalid', () => {
    const getFieldValue = vi.fn(() => '123');
    const formRef = {
      current: {
        getFieldValue,
        setFieldValue: vi.fn(),
      },
    } as any;

    const fields = CompanyFields(formRef);
    const rucField = fields.find((field) => field.name === 'ruc') as
      | ({ buttons?: Array<{ options?: { onClick?: () => void } }> } & (typeof fields)[number])
      | undefined;
    const onClick = rucField?.buttons?.[0]?.options?.onClick;

    onClick?.();

    expect(consultRuc).not.toHaveBeenCalled();
    expect(toast).toHaveBeenCalledWith('Ingresa un RUC válido de 11 dígitos', 'warning');
  });
});
