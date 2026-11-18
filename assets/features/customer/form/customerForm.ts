import { SharedUtils } from '@shared/lib/SharedUtils';

/** What the API returns for a customer (the list and the record share this shape). */
export interface PriceListRef {
  '@id'?: string;
  id?: number;
  name?: string;
}

export interface Customer {
  id: number;
  ruc: string;
  businessName: string;
  fiscalAddress: string | null;
  phone: string | null;
  email: string | null;
  collectionContactName: string | null;
  priceList: PriceListRef | string | null;
  isActive: boolean;
}

/** The form edits plain strings; the price list is its IRI (`/api/price-lists/3`), or null. */
export interface CustomerFormValues {
  ruc: string;
  businessName: string;
  fiscalAddress: string;
  email: string;
  phone: string;
  collectionContactName: string;
  priceList: string | null;
  isActive: boolean;
}

/** Field names, in the order the form renders them: the first invalid one gets the focus. */
export const CUSTOMER_FIELDS = [
  'ruc',
  'businessName',
  'fiscalAddress',
  'email',
  'phone',
  'collectionContactName',
  'priceList',
  'isActive',
] as const satisfies ReadonlyArray<keyof CustomerFormValues>;

export const emptyCustomerForm = (): CustomerFormValues => ({
  ruc: '',
  businessName: '',
  fiscalAddress: '',
  email: '',
  phone: '',
  collectionContactName: '',
  priceList: null,
  isActive: true,
});

/** IRI + label of the customer's price list, whichever way the API embedded it. */
export function priceListOf(
  customer: Pick<Customer, 'priceList'>,
): { iri: string; label: string } | null {
  const ref = customer.priceList;
  if (ref === null || ref === undefined) return null;
  if (typeof ref === 'string') return { iri: ref, label: ref.split('/').pop() ?? ref };

  const iri = ref['@id'] ?? (ref.id !== undefined ? `/api/price-lists/${ref.id}` : undefined);
  return iri ? { iri, label: ref.name ?? iri.split('/').pop() ?? iri } : null;
}

export function toFormValues(customer: Customer): CustomerFormValues {
  return {
    ruc: customer.ruc ?? '',
    businessName: customer.businessName ?? '',
    fiscalAddress: customer.fiscalAddress ?? '',
    email: customer.email ?? '',
    phone: customer.phone ?? '',
    collectionContactName: customer.collectionContactName ?? '',
    priceList: priceListOf(customer)?.iri ?? null,
    isActive: customer.isActive ?? true,
  };
}

const blankToNull = (value: string): string | null => (value.trim() === '' ? null : value.trim());

/**
 * The body of a save. Optional text that was left blank is sent as `null`, not `''`: the server
 * validates a non-empty value (an empty phone would fail its pattern) but accepts the absence.
 */
export function toPayload(values: CustomerFormValues) {
  return {
    ruc: values.ruc.trim(),
    businessName: values.businessName.trim(),
    fiscalAddress: blankToNull(values.fiscalAddress),
    email: blankToNull(values.email),
    phone: blankToNull(values.phone),
    collectionContactName: blankToNull(values.collectionContactName),
    priceList: values.priceList,
    isActive: values.isActive,
  };
}

/* ── validation ───────────────────────────────────────────────────────────────
 * Each rule returns an error message, or `true`. Messages are the ones the screen has always shown. */

export const MESSAGES = {
  documentRequired: 'El RUC/DNI es obligatorio',
  documentDigits: 'El RUC/DNI solo debe contener números',
  documentLength: 'El RUC/DNI debe tener entre 8 y 11 dígitos',
  documentInvalid: 'El RUC ingresado no es válido',
  nameRequired: 'La razón social es obligatoria',
  phoneInvalid: 'El teléfono ingresado no es válido',
  emailInvalid: 'El email ingresado no es válido',
  contactTooLong: 'El contacto de cobranza admite hasta 120 caracteres',
} as const;

export function validateDocument(value: string): string | true {
  const doc = value.trim();
  if (doc === '') return MESSAGES.documentRequired;
  if (!/^[0-9]+$/.test(doc)) return MESSAGES.documentDigits;
  if (doc.length < 8 || doc.length > 11) return MESSAGES.documentLength;
  // 8 digits is a DNI (no checksum); anything else must be a valid RUC.
  if (doc.length === 8) return true;
  return SharedUtils.validateRUC(doc) ? true : MESSAGES.documentInvalid;
}

export function validateBusinessName(value: string): string | true {
  return value.trim() === '' ? MESSAGES.nameRequired : true;
}

const PHONE = /^\+?[1-9]\d{1,2}[-.\s]?\d{6,14}$/;
export function validatePhone(value: string): string | true {
  return value.trim() === '' || PHONE.test(value.trim()) ? true : MESSAGES.phoneInvalid;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
export function validateEmail(value: string): string | true {
  return value.trim() === '' || EMAIL.test(value.trim()) ? true : MESSAGES.emailInvalid;
}

export function validateContact(value: string): string | true {
  return value.length <= 120 ? true : MESSAGES.contactTooLong;
}
