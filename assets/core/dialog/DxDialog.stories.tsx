import type { Meta, StoryObj } from '@storybook/react';
import { DxDialog } from './DxDialog';
import { createCrudEvents } from '@core/event/createCrudEvents';

// ── Shared sample data ────────────────────────────────────────────────────────

const storyEvents = createCrudEvents('story-dialog');

// ── Meta ──────────────────────────────────────────────────────────────────────

const meta: Meta<typeof DxDialog> = {
  title: 'Core/DxDialog',
  component: DxDialog,
  parameters: {
    layout: 'centered',
  },
  args: {
    title: 'Story Dialog',
    width: 480,
    positiveText: 'Save',
    negativeText: 'Cancel',
    footerVisible: true,
    children: (
      <div style={{ padding: '16px' }}>
        <p>Dialog content goes here.</p>
      </div>
    ),
  },
};

export default meta;
type Story = StoryObj<typeof DxDialog>;

// ── Stories ───────────────────────────────────────────────────────────────────

/** Controlled mode — dialog is open, driven by isOpen prop */
export const Controlled_Open: Story = {
  args: {
    isOpen: true,
    onClose: () => undefined,
  },
};

/** Controlled mode — dialog is closed */
export const Controlled_Closed: Story = {
  args: {
    isOpen: false,
    onClose: () => undefined,
  },
};

/** Event-driven mode — dialog opens/closes via the RxJS event bus */
export const EventDriven: Story = {
  args: {
    events: storyEvents,
  },
};
