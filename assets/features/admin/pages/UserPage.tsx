import { SmartCrudPage } from '@core/crud';
import { userResource } from './userResource';

export const UserPage = () => <SmartCrudPage resource={userResource} />;
