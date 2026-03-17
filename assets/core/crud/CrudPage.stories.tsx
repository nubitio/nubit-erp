import type { Meta, StoryObj } from '@storybook/react';
import { MemoryRouter } from 'react-router-dom';
import { CrudPage } from './CrudPage';
import { DialogStoreProvider } from './DialogStoreProvider';
import { defineResource } from './defineResource';
import { identityField, textField, numberField } from '../field/FieldBuilders';
import type { Field } from '../field/Field';

// ── Shared sample data ────────────────────────────────────────────────────────

const sampleFields: Field[] = [
  identityField().build(),
  textField().name('name').label('Name').required(true).build(),
  textField().name('code').label('Code').build(),
  numberField().name('price').label('Price').align('right').build(),
];

const baseResource = defineResource({
  id: 'story-products',
  title: 'Story Products',
  apiUrl: '/api/story-products',
  fields: sampleFields,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});

const readOnlyResource = defineResource({
  id: 'story-products-ro',
  title: 'Story Products (Read Only)',
  apiUrl: '/api/story-products',
  fields: sampleFields,
  allowAdd: false,
  allowEdit: false,
  allowDelete: false,
});

const inlineEditResource = defineResource({
  id: 'story-products-inline',
  title: 'Story Products (Inline Edit)',
  apiUrl: '/api/story-products',
  fields: sampleFields,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
  editMode: 'row',
});

// ── Meta ──────────────────────────────────────────────────────────────────────

const meta: Meta<typeof CrudPage> = {
  title: 'Core/CrudPage',
  component: CrudPage,
  decorators: [
    (Story) => (
      <MemoryRouter>
        <DialogStoreProvider>
          <div style={{ height: '600px', display: 'flex', flexDirection: 'column' }}>
            <Story />
          </div>
        </DialogStoreProvider>
      </MemoryRouter>
    ),
  ],
  parameters: {
    layout: 'fullscreen',
  },
};

export default meta;
type Story = StoryObj<typeof CrudPage>;

// ── Stories ───────────────────────────────────────────────────────────────────

export const FullFeatured: Story = {
  args: {
    resource: baseResource,
  },
};

export const ReadOnly: Story = {
  args: {
    resource: readOnlyResource,
  },
};

export const WithInlineEdit: Story = {
  args: {
    resource: inlineEditResource,
  },
};
