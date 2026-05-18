import { SmartCrudPage, defineResource } from '@core/crud';
import { defineFields } from '@core/crud/fieldContract';
import { customerApiUrl, customerEvents } from './model/constants';
import { consultService } from '@features/consult';
import { SharedUtils } from '@shared/lib/SharedUtils';
import toast from '@shared/lib/SharedUtils';
import type { MutableRefObject } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataRecord } from '@core/data/DataRecord';

type FormEditorRef = {
  instance: () => {
    getEditor: (name: string) => { option: (name: string, value?: unknown) => unknown } | undefined;
  };
};

interface CustomerRecord extends DataRecord {
  id: string;
  ruc: string;
  businessName: string;
  fiscalAddress: string;
  phone: string;
  email: string;
  priceList: unknown;
}

interface ButtonContext {
  formRef: MutableRefObject<FormEditorRef | null>;
}

function setField(formRef: MutableRefObject<FormEditorRef | null>, name: string, value: unknown) {
  formRef.current?.instance().getEditor(name)?.option('value', value);
}

const customerResource = defineResource<CustomerRecord>({
  id: 'customer',
  title: 'Clientes',
  apiUrl: customerApiUrl,
  fields: [],
  events: customerEvents,
  fieldContract: defineFields<CustomerRecord>({
    source: 'hydra',
    strategy: 'augment',
    directives: [
      {
        kind: 'override',
        key: 'ruc',
        patch: {
          maxLength: 11,
          validators: [
            {
              type: 'stringLength',
              options: {
                min: 8,
                max: 11,
                message: 'El RUC/DNI debe tener entre 8 y 11 dígitos',
              },
            },
            {
              type: 'pattern',
              options: {
                pattern: '^[0-9]+$',
                message: 'El RUC/DNI solo debe contener números',
              },
            },
            {
              type: 'custom',
              options: {
                validationCallback: (e: { value: string }) => {
                  const doc = e.value;
                  return doc.length === 8 ? true : SharedUtils.validateRUC(doc);
                },
                message: 'El RUC ingresado no es válido',
              },
            },
          ],
          buttons: [
            {
              name: 'search',
              location: 'after',
              options: {
                icon: 'search',
                stylingMode: 'text',
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                onClick: (_e: unknown, ctx: ButtonContext) => {
                  const formRef = ctx?.formRef;
                  const rawDoc = formRef?.current?.instance().getEditor('ruc')?.option('value');
                  const doc = String(rawDoc ?? '').trim();

                  if (!formRef?.current || doc.length < 8) {
                    return;
                  }

                  if (doc.length === 8) {
                    consultService.consultDni(doc).then((response) => {
                      setField(formRef, 'businessName', response.data.name);
                    });
                  } else if (doc.length === 11) {
                    consultService.consultRuc(doc).then((response) => {
                      if (!response.data.active) {
                        toast('El RUC ingresado no se encuentra activo', 'error');
                        return;
                      }
                      setField(formRef, 'businessName', response.data.name);
                      setField(formRef, 'fiscalAddress', response.data.address);
                    });
                  }
                },
              },
            },
          ],
        },
      },
    ],
  }),
});

export const CustomerPage = () => {
  const { t } = useTranslation();

  return <SmartCrudPage resource={{ ...customerResource, title: t('customer.title') }} />;
};

// Static properties consumed by SalePage — must stay as module-level singletons.
CustomerPage.EVENTS = customerEvents;
CustomerPage.API_URL = customerApiUrl;
