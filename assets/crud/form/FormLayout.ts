export interface FormTab {
  label: string;
  fields: string[];
  icon?: string;
}

export interface FormSection {
  label: string;
  fields: string[];
  collapsible?: boolean;
  defaultCollapsed?: boolean;
}

export type FormLayout =
  | { type: 'tabs'; tabs: FormTab[] }
  | { type: 'sections'; sections: FormSection[] };
