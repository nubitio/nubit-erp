import {withNavigationWatcher} from './contexts/navigation';
import {DashboardPage} from "./pages/Dashboard/DashboardPage";
import {ProductPage} from "./pages/Product/ProductPage";
import {AuthPage} from "./pages/Auth/AuthPage";
import {BranchPage} from "./pages/Branch/BranchPage";
import {CashboxPage} from "./pages/Cashbox/CashboxPage";
import {CashboxConsultPage} from "./pages/CashboxConsult/CashboxConsultPage";
import {CategoryPage} from "./pages/Category/CategoryPage";
import {ChangePasswordPage} from "./pages/ChangePassword/ChangePasswordPage";
import {ComboOptionPage} from "./pages/ComboOption/ComboOptionPage";
import {CompanyPage} from "./pages/Company/CompanyPage";
import {ConfigPage} from "./pages/Config/ConfigPage";
import {ConsultationPage} from "./features/hms/pages/Consultation/ConsultationPage";
import {CreditNotePage} from "./pages/CreditNote/CreditNotePage";
import {CustomerPage} from "./pages/Customer/CustomerPage";
import {DepartmentPage} from "./pages/Department/DepartmentPage";
import {EmployeePage} from "./pages/Employee/EmployeePage";
import {InboundPage} from "./pages/Inbound/InboundPage";
import {InvoicePage} from "./pages/Invoice/InvoicePage";
import {KardexPage} from "./pages/Kardex/KardexPage";
import MedicalRecord from "./features/hms/pages/MedicalRecord/MedicalRecord";
import {MilitaryPatientPage} from "./features/hms/pages/MilitaryPatient/MilitaryPatientPage";
import {OperationPage} from "./pages/Operation/OperationPage";
import {OutboundPage} from "./pages/Outbound/OutboundPage";
import {PatientPage} from "./features/hms/pages/Patient/PatientPage";
import {PaymentMethodPage} from "./pages/PaymentMethod/PaymentMethodPage";
import {PerformancePage} from "./pages/Performance/PerformancePage";
import {PhysicianPage} from "./features/hms/pages/Physician/PhysicianPage";
import {PositionPage} from "./pages/Position/PositionPage";
import {RecipePage} from "./pages/Recipe/RecipePage";
import {RolePage} from "./pages/Role/RolePage";
import {SaleConsultPage} from "./pages/SaleConsult/SaleConsultPage";
import {SpecialtyPage} from "./features/hms/pages/Specialty/SpecialtyPage";
import {TransferPage} from "./pages/Transfer/TransferPage";
import {UserPage} from "./pages/User/UserPage";
import {VoucherSeriesPage} from "./pages/VoucherSeries/VoucherSeriesPage";
import {VoucherTypePage} from "./pages/VoucherType/VoucherTypePage";
import {WarehousePage} from "./pages/Warehouse/WarehousePage";
import {PriceListPage} from "./pages/PriceList/PriceListPage";
import {SupplierPage} from "./pages/Supplier/SupplierPage";
import {SalePage} from "./pages/Sale/SalePage";
import {SalePage as HmsSalePage} from "./features/hms/pages/Sale/SalePage";
import {UnitOfMeasurePage} from "./pages/UnitOfMeasure/UnitOfMeasurePage";
import {ProductionPage} from "./pages/Production/ProductionPage";
import PatientType from "./pages/PatientType";

const routes = [
    {
        path: '/dashboard',
        element: DashboardPage,
    },
    {
        path: '/product',
        element: ProductPage,
    },
    {
        path: '/auth',
        element: AuthPage,
    },
    {
        path: '/branch',
        element: BranchPage,
    },
    {
        path: '/cashbox',
        element: CashboxPage,
    },
    {
        path: '/cashbox-consult',
        element: CashboxConsultPage,
    },
    {
        path: '/category',
        element: CategoryPage,
    },
    {
        path: '/changePassword',
        element: ChangePasswordPage,
    },
    {
        path: '/comboOption',
        element: ComboOptionPage,
    },
    {
        path: '/company',
        element: CompanyPage,
    },
    {
        path: '/config',
        element: ConfigPage,
    },
    {
        path: '/consultation',
        element: ConsultationPage,
    },
    {
        path: '/credit-note',
        element: CreditNotePage,
    },
    {
        path: '/customer',
        element: CustomerPage,
    },
    {
        path: '/department',
        element: DepartmentPage,
    },
    {
        path: '/employee',
        element: EmployeePage,
    },
    {
        path: '/inbound',
        element: InboundPage,
    },
    {
        path: '/invoice',
        element: InvoicePage,
    },
    {
        path: '/kardex',
        element: KardexPage,
    },
    {
        path: '/medical-record',
        element: MedicalRecord,
    },
    {
        path: '/military-patient',
        element: MilitaryPatientPage,
    },
    {
        path: '/operation',
        element: OperationPage,
    },
    {
        path: '/outbound',
        element: OutboundPage,
    },
    {
        path: '/patient',
        element: PatientPage,
    },
    {
        path: '/payment-method',
        element: PaymentMethodPage,
    },
    {
        path: '/performance',
        element: PerformancePage,
    },
    {
        path: '/physician',
        element: PhysicianPage,
    },
    {
        path: '/position',
        element: PositionPage,
    },
    {
        path: '/recipe',
        element: RecipePage,
    },
    {
        path: '/role',
        element: RolePage,
    },
    {
        path: '/sale',
        element: SalePage,
    },
    {
        path: '/sale-consult',
        element: SaleConsultPage,
    },
    {
        path: '/specialty',
        element: SpecialtyPage,
    },
    {
        path: '/transfer',
        element: TransferPage,
    },
    {
        path: '/user',
        element: UserPage,
    },
    {
        path: '/voucher-series',
        element: VoucherSeriesPage,
    },
    {
        path: '/voucher-type',
        element: VoucherTypePage,
    },
    {
        path: '/warehouse',
        element: WarehousePage,
    },
    {
        path: '/price-list',
        element: PriceListPage,
    },
    {
        path: '/supplier',
        element: SupplierPage,
    },
    {
        path: '/unit-of-measure',
        element: UnitOfMeasurePage,
    },
    {
        path: '/production',
        element: ProductionPage,
    },
    {
        path: '/sale-hms',
        element: HmsSalePage,
    }
];

export const appRoutes = routes.map((route) => {
    return {
        ...route,
        element: withNavigationWatcher(route.element, route.path),
    };
});
