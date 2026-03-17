import './CashMovementCategoryPage.scss';
import { Constants } from '@core/common/Constants';
import { CrudPage, defineResource } from '@core/crud';
import { cashMovementCategoryEvents } from '@features/cashRegister';
import { CashMovementCategoryFields } from './CashMovementCategoryFields';

const resource = defineResource({
  id: 'cash-movement-categories',
  title: 'Categorías de caja',
  apiUrl: Constants.API_BASE_URL + 'cash-movement-categories',
  fields: CashMovementCategoryFields(),
  events: cashMovementCategoryEvents,
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
export const CashMovementCategoryPage = () => <CrudPage resource={resource} />;
