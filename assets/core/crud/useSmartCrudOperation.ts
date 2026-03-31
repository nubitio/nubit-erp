import { useState, useCallback, useEffect } from 'react';
import type { Subscription } from 'rxjs';
import type { FormEventNames } from '../event/EventTypes';
import { useEvents } from '../event/EventHook';
import type { SmartCrudOperation } from './fieldOperationSemantics';

interface RoutingSnapshot {
  initialRecordId: string | null;
  initialIsNew: boolean;
}

export interface SmartCrudOperationState {
  activeOperation: SmartCrudOperation | null;
  formData: Record<string, unknown> | null;
  handleFormDataChange: (data: Record<string, unknown>) => void;
}

/**
 * Owns the `activeOperation` + `formData` state pair and keeps them in sync
 * with routing changes and the resource event bus (ADD / EDIT / CANCEL / SUCCESS).
 *
 * Extracted from SmartCrudPage to consolidate three separate useEffects that
 * previously managed these coupled states independently.
 */
export function useSmartCrudOperation(
  events: FormEventNames,
  routingState: RoutingSnapshot,
): SmartCrudOperationState {
  const [on] = useEvents();

  const [formData, setFormData] = useState<Record<string, unknown> | null>(null);
  const [activeOperation, setActiveOperation] = useState<SmartCrudOperation | null>(() => {
    if (routingState.initialRecordId != null) return 'edit';
    if (routingState.initialIsNew) return 'create';
    return null;
  });

  // Stable callback — avoids re-rendering CrudPage on every setState call
  const handleFormDataChange = useCallback((data: Record<string, unknown>) => {
    setFormData(data);
  }, []);

  // Sync from routing state changes
  useEffect(() => {
    if (routingState.initialRecordId != null) {
      setFormData(null);
      setActiveOperation('edit');
      return;
    }

    if (routingState.initialIsNew) {
      setFormData({});
      setActiveOperation('create');
      return;
    }

    setFormData(null);
    setActiveOperation(null);
  }, [routingState.initialIsNew, routingState.initialRecordId]);

  // Sync from event bus (ADD / EDIT / CANCEL / SUCCESS)
  useEffect(() => {
    const subscriptions: Subscription[] = [];

    if (events.ADD) {
      subscriptions.push(
        on(events.ADD, () => {
          setFormData({});
          setActiveOperation('create');
        }),
      );
    }

    if (events.EDIT) {
      subscriptions.push(
        on(events.EDIT, () => {
          setFormData(null);
          setActiveOperation('edit');
        }),
      );
    }

    if (events.CANCEL) {
      subscriptions.push(
        on(events.CANCEL, () => {
          setFormData(null);
          setActiveOperation(null);
        }),
      );
    }

    if (events.SUCCESS) {
      subscriptions.push(
        on(events.SUCCESS, () => {
          setFormData(null);
          setActiveOperation(null);
        }),
      );
    }

    return () => {
      subscriptions.forEach((sub) => sub.unsubscribe());
    };
  }, [events.ADD, events.CANCEL, events.EDIT, events.SUCCESS, on]);

  return { activeOperation, formData, handleFormDataChange };
}
