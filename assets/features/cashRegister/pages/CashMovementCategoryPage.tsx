import { Constants } from '@core/common/Constants';
import { SmartCrudPage, defineResource } from '@core/crud';
import { cashMovementCategoryEvents } from '@features/cashRegister';

const resource = defineResource({
  id: 'cash-movement-categories',
  title: 'Categorías de caja',
  apiUrl: Constants.API_BASE_URL + 'cash-movement-categories',
  fields: [],
  events: cashMovementCategoryEvents,
});
export const CashMovementCategoryPage = () => <SmartCrudPage resource={resource} />;
