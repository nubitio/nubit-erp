/**
 * Identifies which screen the user is currently viewing and what entity is active.
 * Sent to the backend so the AI system prompt includes live screen context.
 *
 * Maps to App\AI\Dto\ScreenContextInput on the PHP side.
 */
export type ScreenContext = {
  /** The current screen / route segment. */
  screen:
    | 'dashboard'
    | 'pos'
    | 'sale_form'
    | 'inventory'
    | 'cash_register'
    | 'customers'
    | 'products'
    | 'purchases'
    | 'reports'
    | string;
  /** Entity type being viewed (e.g. "sale", "product", "customer"). */
  entityType?: string;
  /** UUID or numeric ID of the active entity. */
  entityId?: string;
};
