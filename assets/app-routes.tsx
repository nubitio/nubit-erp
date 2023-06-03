import {SupplierPage} from "./pages/supplier/SupplierPage";
import {withNavigationWatcher} from './contexts/navigation';
import {DashboardPage} from "./pages/dashboard/DashboardPage";
import {ProductPage} from "./pages/product/ProductPage";

const routes = [
    {
        path: '/dashboard',
        element: DashboardPage,
    },
    {
        path: '/supplier',
        element: SupplierPage,
    },
    {
        path: '/product',
        element: ProductPage,
    }
];

export const appRoutes = routes.map((route) => {
    return {
        ...route,
        element: withNavigationWatcher(route.element, route.path),
    };
});
