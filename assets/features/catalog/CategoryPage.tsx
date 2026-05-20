import { defineResource, SmartCrudPage } from '@nubit/core';

export const CategoryPage = () => <SmartCrudPage resource={defineResource('api/categories')} />;
