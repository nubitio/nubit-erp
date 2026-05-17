import { useEffect } from 'react';

import type { FormLayout } from '@core/crud/FormLayout';

export interface UseFormSectionOptionsAccessors {
  setItemOption: (itemName: string, optionName: string, value: unknown) => void;
}

export function useFormSectionOptions(
  formLayout: FormLayout | undefined,
  accessors: UseFormSectionOptionsAccessors,
): void {
  useEffect(() => {
    if (formLayout?.type !== 'sections') return;

    formLayout.sections.forEach((section) => {
      try {
        accessors.setItemOption(section.label, 'collapsible', section.collapsible ?? false);
        if (section.defaultCollapsed) {
          accessors.setItemOption(section.label, 'collapsed', true);
        }
      } catch {
        // itemOption may fail if the section name is not registered; ignore silently.
      }
    });
    // Run once after the renderer mounts; layout descriptors are static per form instance.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
}
