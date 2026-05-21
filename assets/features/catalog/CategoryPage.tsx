import { defineResource, SmartCrudPage } from '@nubit/crud';

export const CategoryPage = () => <SmartCrudPage resource={defineResource('api/categories')} />;
