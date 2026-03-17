import './CategoryPage.scss';
import { defineResource, SmartCrudPage } from '@core/crud';

export const CategoryPage = () => <SmartCrudPage resource={defineResource('api/categories')} />;
