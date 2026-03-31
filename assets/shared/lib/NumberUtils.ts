export default class NumberUtils {
  static isNumber(value: unknown): boolean {
    return (
      (typeof value === 'number' || (typeof value === 'string' && value.trim() !== '')) &&
      !isNaN(value as number)
    );
  }

  static formatNumber(value: number, decimal: number = 2): string {
    return value.toFixed(decimal);
  }

  static formatCurrency(value: number, currency: string = 'PEN'): string {
    return Intl.NumberFormat('es-PE', { style: 'currency', currency }).format(value);
  }
}
