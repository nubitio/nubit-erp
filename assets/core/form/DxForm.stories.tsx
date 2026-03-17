import type { Meta, StoryObj } from '@storybook/react';
import { DxForm } from './DxForm';
import { createCrudEvents } from '../../features/movement/model/createCrudEvents';
import { identityField, textField, numberField, textareaField } from '../field/FieldBuilders';
import type { Field } from '../field/Field';
import type { FormLayout } from '../crud/FormLayout';

// ── Shared sample data ────────────────────────────────────────────────────────

const storyEvents = createCrudEvents('story-form');

const sampleFields: Field[] = [
  identityField().build(),
  textField().name('name').label('Name').required(true).col(6).build(),
  textField().name('code').label('Code').col(6).build(),
  numberField().name('price').label('Price').align('right').col(6).build(),
  textareaField().name('description').label('Description').col(12).build(),
];

const tabsLayout: FormLayout = {
  type: 'tabs',
  tabs: [
    { label: 'General', fields: ['name', 'code'] },
    { label: 'Details', fields: ['price', 'description'] },
  ],
};

const sectionsLayout: FormLayout = {
  type: 'sections',
  sections: [
    { label: 'Basic Info', fields: ['name', 'code'], collapsible: false },
    {
      label: 'Pricing & Notes',
      fields: ['price', 'description'],
      collapsible: true,
      defaultCollapsed: false,
    },
  ],
};

// ── Meta ──────────────────────────────────────────────────────────────────────

const meta: Meta<typeof DxForm> = {
  title: 'Core/DxForm',
  component: DxForm,
  parameters: {
    layout: 'padded',
  },
  args: {
    url: '/api/story-products',
    fields: sampleFields,
    events: storyEvents,
    editable: true,
  },
};

export default meta;
type Story = StoryObj<typeof DxForm>;

// ── Stories ───────────────────────────────────────────────────────────────────

export const Default: Story = {};

export const WithTabs: Story = {
  args: {
    formLayout: tabsLayout,
  },
};

export const WithSections: Story = {
  args: {
    formLayout: sectionsLayout,
  },
};
