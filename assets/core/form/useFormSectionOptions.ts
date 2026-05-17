import { MutableRefObject, useEffect } from 'react';

import type { FormLayout } from '@core/crud/FormLayout';

export interface SectionOptionsFormRef {
  instance: () => {
    itemOption: (itemName: string, optionName: string, value: unknown) => void;
  };
}

export function useFormSectionOptions(
  formLayout: FormLayout | undefined,
  formRef: MutableRefObject<SectionOptionsFormRef | null>,
): void {
  useEffect(() => {
    if (formLayout?.type !== 'sections') return;

    formLayout.sections.forEach((section) => {
      try {
        const instance = formRef.current?.instance();
        instance?.itemOption(section.label, 'collapsible', section.collapsible ?? false);
        if (section.defaultCollapsed) {
          instance?.itemOption(section.label, 'collapsed', true);
        }
      } catch {
        // itemOption may fail if the section name is not registered; ignore silently.
      }
    });
    // Run once after the renderer mounts; layout descriptors are static per form instance.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
}
