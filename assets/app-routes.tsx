import {SupplierPage} from "./pages/supplier/SupplierPage";
import {withNavigationWatcher} from './contexts/navigation';
import {DashboardPage} from "./pages/dashboard/DashboardPage";
import {ProductPage} from "./pages/product/ProductPage";
import {AuthPage} from "./pages/Auth/AuthPage";
import {BranchPage} from "./pages/Branch/BranchPage";
import {CashboxPage} from "./pages/Cashbox/CashboxPage";
import {CashboxConsultPage} from "./pages/CashboxConsult/CashboxConsultPage";
import {CategoryPage} from "./pages/Category/CategoryPage";
import {ChangePasswordPage} from "./pages/ChangePassword/ChangePasswordPage";
import {ComboOptionPage} from "./pages/ComboOption/ComboOptionPage";
import {CompanyPage} from "./pages/Company/CompanyPage";
import {ConfigPage} from "./pages/Config/ConfigPage";
import {ConsultationPage} from "./pages/Consultation/ConsultationPage";
import {ConsultationSalePage} from "./pages/ConsultationSale/ConsultationSalePage";
import {CreditNotePage} from "./pages/CreditNote/CreditNotePage";
import {CustomerPage} from "./pages/Customer/CustomerPage";
import {DepartmentPage} from "./pages/Department/DepartmentPage";
import {EmployeePage} from "./pages/Employee/EmployeePage";
import {InboundPage} from "./pages/Inbound/InboundPage";
import {InvoicePage} from "./pages/Invoice/InvoicePage";
import {KardexPage} from "./pages/Kardex/KardexPage";
import MedicalRecord from "./pages/MedicalRecord/MedicalRecord";
import {MilitaryPatientPage} from "./pages/MilitaryPatient/MilitaryPatientPage";
import {OperationPage} from "./pages/Operation/OperationPage";
import {OutboundPage} from "./pages/Outbound/OutboundPage";
import {PatientPage} from "./pages/Patient/PatientPage";
import {PatientTypePage} from "./pages/PatientType/PatientTypePage";
import {PaymentMethodPage} from "./pages/PaymentMethod/PaymentMethodPage";
import {PerformancePage} from "./pages/Performance/PerformancePage";
import {PhysicianPage} from "./pages/Physician/PhysicianPage";
import {PositionPage} from "./pages/Position/PositionPage";
import {PriceListPage} from "./pages/PriceList/PriceListPage";
import {RecipePage} from "./pages/Recipe/RecipePage";
import {RolePage} from "./pages/Role/RolePage";
import {SalePage} from "./pages/Sale/SalePage";
import {SaleConsultPage} from "./pages/SaleConsult/SaleConsultPage";
import {SpecialtyPage} from "./pages/Specialty/SpecialtyPage";
import {TransferPage} from "./pages/Transfer/TransferPage";
import {UserPage} from "./pages/User/UserPage";
import {VoucherSeriesPage} from "./pages/VoucherSeries/VoucherSeriesPage";
import {VoucherTypePage} from "./pages/VoucherType/VoucherTypePage";
import {WarehousePage} from "./pages/Warehouse/WarehousePage";

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
    },
    {
        path: '/Auth',
        element: AuthPage,
    },
    {
        path: '/Branch',
        element: BranchPage,
    },
    {
        path: '/Cashbox',
        element: CashboxPage,
    },
    {
        path: '/CashboxConsult',
        element: CashboxConsultPage,
    },
    {
        path: '/Category',
        element: CategoryPage,
    },
    {
        path: '/ChangePassword',
        element: ChangePasswordPage,
    },
    {
        path: '/ComboOption',
        element: ComboOptionPage,
    },
    {
        path: '/Company',
        element: CompanyPage,
    },
    {
        path: '/Config',
        element: ConfigPage,
    },
    {
        path: '/Consultation',
        element: ConsultationPage,
    },
    {
        path: '/ConsultationSale',
        element: ConsultationSalePage,
    },
    {
        path: '/CreditNote',
        element: CreditNotePage,
    },
    {
        path: '/Customer',
        element: CustomerPage,
    },
    {
        path: '/Department',
        element: DepartmentPage,
    },
    {
        path: '/Employee',
        element: EmployeePage,
    },
    {
        path: '/Inbound',
        element: InboundPage,
    },
    {
        path: '/Invoice',
        element: InvoicePage,
    },
    {
        path: '/Kardex',
        element: KardexPage,
    },
    {
        path: '/MedicalRecord',
        element: MedicalRecord,
    },
    {
        path: '/MilitaryPatient',
        element: MilitaryPatientPage,
    },
    {
        path: '/Operation',
        element: OperationPage,
    },
    {
        path: '/Outbound',
        element: OutboundPage,
    },
    {
        path: '/Patient',
        element: PatientPage,
    },{
        path: '/PatientType',
        element: PatientTypePage,
    },
    {
        path: '/PaymentMethod',
        element: PaymentMethodPage,
    },
    {
        path: '/Performance',
        element: PerformancePage,
    },
    {
        path: '/Physician',
        element: PhysicianPage,
    },
    {
        path: '/Position',
        element: PositionPage,
    },
    {
        path: '/PriceList',
        element: PriceListPage,
    },
    {
        path: '/Recipe',
        element: RecipePage,
    },
    {
        path: '/Role',
        element: RolePage,
    },
    {
        path: '/Sale',
        element: SalePage,
    },
    {
        path: '/SaleConsult',
        element: SaleConsultPage,
    },
    {
        path: '/Specialty',
        element: SpecialtyPage,
    },
    {
        path: '/Transfer',
        element: TransferPage,
    },
    {
        path: '/User',
        element: UserPage,
    },
    {
        path: '/VoucherSeries',
        element: VoucherSeriesPage,
    },
    {
        path: '/VoucherType',
        element: VoucherTypePage,
    },
    {
        path: '/Warehouse',
        element: WarehousePage,
    },
];

export const appRoutes = routes.map((route) => {
    return {
        ...route,
        element: withNavigationWatcher(route.element, route.path),
    };
});
