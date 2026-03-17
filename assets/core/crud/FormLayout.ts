export interface FormTab {
  label: string;
  fields: string[]; // field.name values in this tab
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
