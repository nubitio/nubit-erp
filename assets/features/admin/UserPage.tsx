import { SmartCrudPage } from '@nubitio/crud';
import { userResource } from './userResource';

export const UserPage = () => <SmartCrudPage resource={userResource} />;
