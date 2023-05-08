import {SupplierPage} from "./pages/supplier/SupplierPage";
import {withNavigationWatcher} from './contexts/navigation';

const routes = [
    {
        path: '/crm-contact-details',
        element: SupplierPage,
    }
];

export const appRoutes = routes.map((route) => {
    return {
        ...route,
        element: withNavigationWatcher(route.element, route.path),
    };
});