import type { Meta, StoryObj } from '@storybook/react';
import { MemoryRouter } from 'react-router-dom';
import { DxDataGrid } from './DxDataGrid';
import { createCrudEvents } from '@core/event/createCrudEvents';
import { identityField, textField, numberField } from '../field/FieldBuilders';
import type { Field } from '../field/Field';

// ── Shared sample data ────────────────────────────────────────────────────────

const storyEvents = createCrudEvents('story-grid');

const sampleFields: Field[] = [
  identityField().build(),
  textField().name('name').label('Name').required(true).build(),
  textField().name('code').label('Code').build(),
  numberField().name('stock').label('Stock').align('right').build(),
];

// ── Meta ──────────────────────────────────────────────────────────────────────

const meta: Meta<typeof DxDataGrid> = {
  title: 'Core/DxDataGrid',
  component: DxDataGrid,
  decorators: [
    (Story) => (
      <MemoryRouter>
        <div style={{ height: '500px', display: 'flex', flexDirection: 'column' }}>
          <Story />
        </div>
      </MemoryRouter>
    ),
  ],
  parameters: {
    layout: 'fullscreen',
  },
  args: {
    id: 'story-grid',
    title: 'Story Products',
    url: '/api/story-products',
    fields: sampleFields,
    events: storyEvents,
    allowAdd: true,
    allowEdit: true,
    allowDelete: true,
  },
};

export default meta;
type Story = StoryObj<typeof DxDataGrid>;

// ── Stories ───────────────────────────────────────────────────────────────────

export const Default: Story = {};

export const WithSorting: Story = {
  args: {
    sort: [{ selector: 'name', desc: false }],
  },
};

export const WithMultipleSelection: Story = {
  args: {
    selectionMode: 'multiple',
  },
};

export const WithColumnPresets: Story = {
  args: {
    visibleColumns: ['name', 'code'],
    stateStoringEnabled: false,
  },
};
