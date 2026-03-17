/**
 * Re-export from `@core/event/createCrudEvents`.
 *
 * The implementation was moved to `core/` to eliminate the circular dependency
 * where `core/event/createScopedEventBus` imported from `@features/movement`.
 * All existing importers of `@features/movement` or this file continue to work
 * without modification.
 */
export { createCrudEvents } from '@core/event/createCrudEvents';
