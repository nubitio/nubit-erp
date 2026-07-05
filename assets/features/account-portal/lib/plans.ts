export type BillingCycle = 'monthly' | 'annual';
export type PlanKey = 'free' | 'basic' | 'pro';
export type PaymentGateway = 'paypal' | 'mercadopago';

export interface PlanOption {
  key: PlanKey;
  name: string;
  monthly: string;
  annual: string;
  hint: string;
  features: string[];
}

export const PLANS: PlanOption[] = [
  {
    key: 'free',
    name: 'Free',
    monthly: 'Gratis',
    annual: 'Gratis',
    hint: 'Para empezar',
    features: ['3 usuarios', '500 facturas/mes', 'POS básico'],
  },
  {
    key: 'basic',
    name: 'Basic',
    monthly: 'S/ 29.90',
    annual: 'S/ 299',
    hint: 'Negocios en crecimiento',
    features: ['10 usuarios', '5 000 facturas/mes', 'Inventario completo'],
  },
  {
    key: 'pro',
    name: 'Pro',
    monthly: 'S/ 79.90',
    annual: 'S/ 799',
    hint: 'Operación avanzada',
    features: ['Usuarios ilimitados', 'Facturas ilimitadas', 'Soporte prioritario'],
  },
];

export function planPrice(option: PlanOption, cycle: BillingCycle): string {
  const amount = cycle === 'monthly' ? option.monthly : option.annual;
  if (amount === 'Gratis') return amount;
  return cycle === 'monthly' ? `${amount}/mes` : `${amount}/año`;
}