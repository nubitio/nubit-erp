import { useReducer, useCallback, useEffect } from 'react';
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

type CrudState = Pick<SmartCrudOperationState, 'activeOperation' | 'formData'>;

type CrudAction =
  | { type: 'sync-routing'; routingState: RoutingSnapshot }
  | { type: 'set-form-data'; data: Record<string, unknown> }
  | { type: 'create' }
  | { type: 'edit' }
  | { type: 'reset' };

function stateFromRouting(routingState: RoutingSnapshot): CrudState {
  if (routingState.initialRecordId != null) {
    return { activeOperation: 'edit', formData: null };
  }

  if (routingState.initialIsNew) {
    return { activeOperation: 'create', formData: {} };
  }

  return { activeOperation: null, formData: null };
}

function crudReducer(state: CrudState, action: CrudAction): CrudState {
  switch (action.type) {
    case 'sync-routing':
      return stateFromRouting(action.routingState);
    case 'set-form-data':
      return { ...state, formData: action.data };
    case 'create':
      return { activeOperation: 'create', formData: {} };
    case 'edit':
      return { activeOperation: 'edit', formData: null };
    case 'reset':
      return { activeOperation: null, formData: null };
    default:
      return state;
  }
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

  const [{ activeOperation, formData }, dispatchState] = useReducer(
    crudReducer,
    routingState,
    stateFromRouting,
  );

  // Stable callback — avoids re-rendering CrudPage on every setState call
  const handleFormDataChange = useCallback((data: Record<string, unknown>) => {
    dispatchState({ type: 'set-form-data', data });
  }, []);

  // Sync from routing state changes
  useEffect(() => {
    dispatchState({
      type: 'sync-routing',
      routingState: {
        initialRecordId: routingState.initialRecordId,
        initialIsNew: routingState.initialIsNew,
      },
    });
  }, [routingState.initialIsNew, routingState.initialRecordId]);

  // Sync from event bus (ADD / EDIT / CANCEL / SUCCESS)
  useEffect(() => {
    const subscriptions: Subscription[] = [];

    if (events.ADD) {
      subscriptions.push(
        on(events.ADD, () => {
          dispatchState({ type: 'create' });
        }),
      );
    }

    if (events.EDIT) {
      subscriptions.push(
        on(events.EDIT, () => {
          dispatchState({ type: 'edit' });
        }),
      );
    }

    if (events.CANCEL) {
      subscriptions.push(
        on(events.CANCEL, () => {
          dispatchState({ type: 'reset' });
        }),
      );
    }

    if (events.SUCCESS) {
      subscriptions.push(
        on(events.SUCCESS, () => {
          dispatchState({ type: 'reset' });
        }),
      );
    }

    return () => {
      subscriptions.forEach((sub) => sub.unsubscribe());
    };
  }, [events.ADD, events.CANCEL, events.EDIT, events.SUCCESS, on]);

  return { activeOperation, formData, handleFormDataChange };
}
