<?php

declare(strict_types=1);

namespace App\Service;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Entity\CashMovementCategory;
use App\Catalog\Entity\Category;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\SunatCatalog;
use App\Catalog\Entity\UnitOfMeasure;
use App\Catalog\Entity\VoucherType;
use App\Enum\CashMovementType;
use App\Repository\CashMovementCategoryRepository;
use App\Catalog\Repository\CategoryRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Catalog\Repository\SunatCatalogRepository;
use App\Catalog\Repository\UnitOfMeasureRepository;
use App\Catalog\Repository\VoucherTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

readonly class InitialDataSeeder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettingsService $settingsService,
        private MenuSeedService $menuSeedService,
        private LogisticOperationRepository $logisticOperationRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private CashMovementCategoryRepository $cashMovementCategoryRepository,
        private VoucherTypeRepository $voucherTypeRepository,
        private UnitOfMeasureRepository $unitOfMeasureRepository,
        private CategoryRepository $categoryRepository,
        private CustomerRepository $customerRepository,
        private SunatCatalogRepository $sunatCatalogRepository,
    ) {
    }

    public function seed(): void
    {
        $this->seedSettings();
        $this->seedReferenceData();
        $this->menuSeedService->seed();
    }

    private function seedSettings(): void
    {
        try {
            $this->settingsService->set(Constants::TAX, 'int', '18');
            $this->settingsService->set(Constants::CONSUMPTION_TAX, 'int', '0');
            $this->settingsService->set(Constants::AUTO_INVOICE, 'bool', 'true');
            $this->settingsService->set(Constants::PRINT_LOGO, 'bool', 'true');
            $this->settingsService->set(Constants::PRINT_METHOD, 'string', 'WEBUSB');
            $this->settingsService->set(Constants::REQUIRE_CASH_REGISTER, 'bool', 'true');
            $this->settingsService->set(Constants::SUNAT_SEND_ENABLED, 'bool', 'true');
            $this->settingsService->set(Constants::SUNAT_REJECTION_STREAK, 'int', '0');
            $this->settingsService->set(Constants::SUNAT_REJECTION_THRESHOLD, 'int', '3');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_MAX_ATTEMPTS, 'int', '8');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_BASE_SECONDS, 'int', '60');
            $this->settingsService->set(Constants::SUNAT_SEND_RETRY_MAX_SECONDS, 'int', '1800');
            // Post-cutover default: new tenants start directly in V2 authoritative mode.
            $this->settingsService->set(Constants::SALES_V2_MODE, 'string', 'v2_authoritative');
            $this->settingsService->set(Constants::PURCHASES_V2_MODE, 'string', 'v2_authoritative');
            $this->settingsService->set(Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'bool', 'true');
            $this->settingsService->set(Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'bool', 'true');
            $this->settingsService->set(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, 'string', 'BLOCK');
            $this->settingsService->set(Constants::INVENTORY_CORE_MODE, 'string', 'v2_authoritative');
            $this->settingsService->set(Constants::INVENTORY_CORE_DUAL_WRITE, 'bool', 'true');
            $this->settingsService->set(Constants::INVENTORY_CORE_READ_FROM_STOCK_ITEMS, 'bool', 'true');
        } catch (Throwable) {
            // Settings table might not be ready yet; fail silently to keep seeding idempotent.
        }
    }

    private function seedReferenceData(): void
    {
        $this->getOrCreateUnitOfMeasure('NIU', 'Unidad', 1, 1.0, true);

        $operations = [
            ['Venta', 2],
            ['Devolución de venta', 1],
            ['Compra', 1],
            ['Devolución de compra', 2],
            ['Ajuste de inventario entrada', 1],
            ['Ajuste de inventario salida', 2],
            ['Transferencia entrada', 1],
            ['Transferencia salida', 2],
        ];
        foreach ($operations as [$name, $type]) {
            $this->getOrCreateLogisticOperation($name, $type);
        }

        $paymentMethods = [
            'Efectivo',
            'Credito',
            'Tarjeta débito',
            'Tarjeta crédito',
            'Transferencia',
            'Yape',
            'Plin',
            'Link de pago',
            'Depósito',
        ];
        foreach ($paymentMethods as $method) {
            $this->getOrCreatePaymentMethod($method);
        }

        $cashMovementCategories = [
            [CashMovementCategory::CASH_SALE, 'Venta en efectivo', CashMovementType::INCOME],
            [CashMovementCategory::CREDIT_PAYMENT, 'Cobro de crédito', CashMovementType::INCOME],
            [CashMovementCategory::CASH_REPLENISHMENT, 'Reposición de caja', CashMovementType::INCOME],
            [CashMovementCategory::OTHER_INCOME, 'Otros ingresos', CashMovementType::INCOME],
            [CashMovementCategory::OVERAGE_ADJUSTMENT, 'Ajuste por sobrante', CashMovementType::INCOME],
            [CashMovementCategory::PETTY_CASH_EXPENSE, 'Gasto de caja chica', CashMovementType::EXPENSE],
            [CashMovementCategory::CUSTOMER_REFUND, 'Devolución al cliente', CashMovementType::EXPENSE],
            [CashMovementCategory::MANAGER_WITHDRAWAL, 'Retiro de efectivo', CashMovementType::EXPENSE],
            [CashMovementCategory::BANK_DEPOSIT, 'Depósito bancario', CashMovementType::EXPENSE],
            [CashMovementCategory::SHORTAGE_ADJUSTMENT, 'Ajuste por faltante', CashMovementType::EXPENSE],
        ];
        foreach ($cashMovementCategories as [$code, $name, $type]) {
            $this->getOrCreateCashMovementCategory($code, $name, $type);
        }

        $voucherTypes = [
            ['00', 'Nota de Venta'],
            ['01', 'Factura'],
            ['03', 'Boleta'],
            ['07', 'Nota de Crédito'],
            ['08', 'Nota de Débito'],
        ];
        foreach ($voucherTypes as [$code, $name]) {
            $this->getOrCreateVoucherType($code, $name);
        }

        $operationTypes = [
            ['0101', 'Venta Interna'],
            ['0200', 'Exportación de bienes'],
            [
                '1001',
                'Operación sujeta al Sistema de Pago de Obligaciones Tributarias con el Gobierno Central',
            ],
            ['1004', 'Operación Sujeta a Detracción- Servicios de Transporte Carga'],
        ];
        foreach ($operationTypes as [$code, $name]) {
            $this->getOrCreateSunatCatalog(51, $code, $name);
        }

        $paymentMethods = [
            ['001', 'Depósito en cuenta'],
            ['002', 'Giro'],
            ['003', 'Transferencia de fondos'],
            ['004', 'Orden de pago'],
            ['005', 'Tarjeta de débito'],
            [
                '006',
                'Tarjeta de crédito emitida en el país por una empresa del sistema financiero',
            ],
            [
                '007',
                'Cheques con la cláusula de "NO NEGOCIABLE", "INTRANSFERIBLES", "NO A LA ORDEN" '
                    . 'u otra equivalente, a que se refiere el inciso g) del artículo 5° de la ley',
            ],
            ['008', 'Efectivo, por operaciones en las que no existe obligación de utilizar medio de pago'],
            ['009', 'Efectivo, en los demás casos'],
            ['010', 'Medios de pago usados en comercio exterior'],
            [
                '011',
                'Documentos emitidos por las EDPYMES y las cooperativas de ahorro y crédito '
                    . 'no autorizadas a captar depósitos del público',
            ],
            [
                '012',
                'Tarjeta de crédito emitida en el país o en el exterior por una empresa no '
                    . 'perteneciente al sistema financiero, cuyo objeto principal sea la emisión '
                    . 'y administración de tarjetas de crédito',
            ],
            ['013', 'Tarjetas de crédito emitidas en el exterior por empresas bancarias o financieras no domiciliadas'],
            ['101', 'Transferencias – Comercio exterior'],
            ['102', 'Cheques bancarios - Comercio exterior'],
            ['103', 'Orden de pago simple - Comercio exterior'],
            ['104', 'Orden de pago documentario - Comercio exterior'],
            ['105', 'Remesa simple - Comercio exterior'],
            ['106', 'Remesa documentaria - Comercio exterior'],
            ['107', 'Carta de crédito simple - Comercio exterior'],
            ['108', 'Carta de crédito documentario - Comercio exterior'],
            ['999', 'Otros medios de pago'],
        ];
        foreach ($paymentMethods as [$code, $name]) {
            $this->getOrCreateSunatCatalog(59, $code, $name);
        }

        $detractionCatalog = [
            ['001', 'Azúcar y melaza de caña', 10.0],
            ['003', 'Alcohol etílico', 10.0],
            ['004', 'Recursos hidrobiológicos', 4.0],
            ['005', 'Maíz amarillo duro', 4.0],
            ['007', 'Caña de azúcar', 10.0],
            ['008', 'Madera', 4.0],
            ['009', 'Arena y piedra.', 10.0],
            ['010', 'Residuos, subproductos, desechos, recortes y desperdicios', 15.0],
            ['012', 'Intermediación laboral y tercerización', 12.0],
            ['014', 'Carnes y despojos comestibles', 4.0],
            ['016', 'Aceite de pescado', 10.0],
            ['017', 'Harina, polvo y “pellets” de pescado, crustáceos, moluscos y demás invertebrados acuáticos', 4.0],
            ['019', 'Arrendamiento de bienes muebles', 10.0],
            ['020', 'Mantenimiento y reparación de bienes muebles', 12.0],
            ['021', 'Movimiento de carga', 10.0],
            ['022', 'Otros servicios empresariales', 12.0],
            ['023', 'Leche', 4.0],
            ['024', 'Comisión mercantil', 10.0],
            ['025', 'Fabricación de bienes por encargo', 10.0],
            ['026', 'Servicio de transporte de personas', 10.0],
            ['027', 'Servicio de transporte de carga', 4.0],
            ['028', 'Transporte de pasajeros', 10.0],
            ['030', 'Contratos de construcción', 4.0],
            ['031', 'Oro gravado con el IGV', 10.0],
            ['032', 'Paprika y otros frutos de los generos capsicum o pimienta', 10.0],
            ['034', 'Minerales metálicos no auríferos', 10.0],
            ['035', 'Bienes exonerados del IGV', 1.5],
            ['036', 'Oro y demás minerales metálicos exonerados del IGV', 1.5],
            ['037', 'Demás servicios gravados con el IGV', 12.0],
            ['039', 'Minerales no metálicos', 10.0],
            ['041', 'Plomo', 15.0],
        ];
        foreach ($detractionCatalog as [$code, $name, $percentage]) {
            $this->getOrCreateSunatCatalog(54, $code, $name, $percentage);
        }

        $this->getOrCreateCategory('General');
        $this->getOrCreateCustomer('00000000', 'Cliente General');
    }

    private function getOrCreateLogisticOperation(string $name, int $type): void
    {
        $existing = $this->logisticOperationRepository->findOneBy(['name' => $name]);
        if ($existing instanceof LogisticOperation) {
            return;
        }

        $operation = new LogisticOperation()
            ->setName($name)
            ->setType($type);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();
    }

    private function getOrCreatePaymentMethod(string $name): void
    {
        $existing = $this->paymentMethodRepository->findOneBy(['name' => $name]);
        if ($existing instanceof PaymentMethod) {
            return;
        }

        $paymentMethod = new PaymentMethod()->setName($name);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();
    }

    private function getOrCreateCashMovementCategory(string $code, string $name, CashMovementType $type): void
    {
        $existing = $this->cashMovementCategoryRepository->findOneBy(['code' => $code]);
        if ($existing instanceof CashMovementCategory) {
            return;
        }

        $category = new CashMovementCategory()
            ->setCode($code)
            ->setName($name)
            ->setType($type)
            ->setIsSystem(true)
            ->setActive(true);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateVoucherType(string $code, string $name): void
    {
        $existing = $this->voucherTypeRepository->find($code);
        if ($existing instanceof VoucherType) {
            return;
        }

        $voucherType = new VoucherType()
            ->setCode($code)
            ->setName($name);
        $this->entityManager->persist($voucherType);
        $this->entityManager->flush();
    }

    private function getOrCreateUnitOfMeasure(
        string $code,
        string $name,
        int $type,
        float $ratio,
        bool $reference
    ): void {
        $existing = $this->unitOfMeasureRepository->findOneBy(['code' => $code]);
        if ($existing instanceof UnitOfMeasure) {
            return;
        }

        $unit = new UnitOfMeasure()
            ->setName($name)
            ->setCode($code)
            ->setType($type)
            ->setRatio((string) $ratio)
            ->setReference($reference);

        $this->entityManager->persist($unit);
        $this->entityManager->flush();
    }

    private function getOrCreateCategory(string $name): void
    {
        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing instanceof Category) {
            return;
        }

        $category = new Category()
            ->setName($name);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateCustomer(string $ruc, string $businessName): void
    {
        $existing = $this->customerRepository->findOneBy(['ruc' => $ruc]);
        if ($existing instanceof Customer) {
            return;
        }

        $customer = new Customer()
            ->setRuc($ruc)
            ->setBusinessName($businessName);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
    }

    private function getOrCreateSunatCatalog(
        int $catalog,
        string $code,
        string $name,
        ?float $percentage = null
    ): void {
        $existing = $this->sunatCatalogRepository->findOneBy([
            'catalog' => $catalog,
            'code' => $code,
        ]);
        if ($existing instanceof SunatCatalog) {
            return;
        }

        $entry = (new SunatCatalog())
            ->setCatalog($catalog)
            ->setCode($code)
            ->setDescription($name)
            ->setPercentage($percentage)
            ->setActive(true);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
