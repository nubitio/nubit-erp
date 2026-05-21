import { SmartCrudPage } from '@nubit/crud';
import { userResource } from './userResource';

export const UserPage = () => <SmartCrudPage resource={userResource} />;
