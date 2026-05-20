import { SmartCrudPage } from '@nubit/core';
import { userResource } from './userResource';

export const UserPage = () => <SmartCrudPage resource={userResource} />;
