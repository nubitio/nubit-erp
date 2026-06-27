import { SchemaCrudPage } from '@nubitio/crud';
import { userResource } from './userResource';

export const UserPage = () => <SchemaCrudPage resource={userResource} />;
