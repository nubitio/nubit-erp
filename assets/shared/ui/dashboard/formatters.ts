const currencyFormatterIntl = new Intl.NumberFormat('es-PE', {
  style: 'currency',
  currency: 'PEN',
  maximumFractionDigits: 0,
});
const decimalFormatterIntl = new Intl.NumberFormat('es-PE', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});
const percentFormatterIntl = new Intl.NumberFormat('es-PE', {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1,
});

export const currencyFormatter = (value: number) => currencyFormatterIntl.format(value);
export const decimalFormatter = (value: number) => decimalFormatterIntl.format(value);
export const percentFormatter = (value: number) => percentFormatterIntl.format(value);
