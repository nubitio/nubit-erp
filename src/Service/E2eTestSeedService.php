<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Setup\InitialSetupInput;
use App\Entity\Branch;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\PaymentMethod;
use App\Entity\Product;
use App\Entity\Role;
use App\Entity\CashRegister;
use App\Entity\SaleRecord;
use App\Entity\SaleItemRecord;
use App\Entity\UnitOfMeasure;
use App\Entity\User;
use App\Entity\VoucherSeries;
use App\Entity\VoucherType;
use App\Entity\Warehouse;
use App\Repository\BranchRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\CustomerRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\ProductRepository;
use App\Repository\RoleRepository;
use App\Repository\UnitOfMeasureRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherSeriesRepository;
use App\Repository\VoucherTypeRepository;
use App\Repository\WarehouseRepository;
use App\Setup\InitialSetupService;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class E2eTestSeedService implements E2eTestSeedServiceInterface
{
    private const string E2E_USERNAME = 'ci_e2e_user';
    private const string E2E_BRANCH_NAME = 'CI E2E Branch';
    private const string E2E_CUSTOMER_RUC = '20100000001';
    private const string E2E_CUSTOMER_NAME = 'Cliente CI E2E';
    private const string E2E_PRODUCT_CODE = 'E2E-CI-PRODUCT';
    private const string E2E_PRODUCT_NAME = 'Producto CI E2E';
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly InitialSetupService $initialSetupService,
        private readonly CompanyRepository $companyRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        private readonly BranchRepository $branchRepository,
        private readonly WarehouseRepository $warehouseRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UnitOfMeasureRepository $unitOfMeasureRepository,
        private readonly VoucherTypeRepository $voucherTypeRepository,
        private readonly VoucherSeriesRepository $voucherSeriesRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly CashRegisterRepository $cashRegisterRepository,
    ) {
    }

    /**
     * @return array{username: string, password: string, saleId: string}
     */
    public function seed(): array
    {
        $this->ensureInitialSetup();

        $password = $this->generatePassword();
        $user = $this->upsertE2eUser($password);

        $branch = $this->upsertBranch();
        $warehouse = $this->upsertWarehouse($branch);
        $this->upsertCashRegister($branch);
        $this->ensureVoucherSeries($branch);

        $customer = $this->upsertCustomer();
        $product = $this->upsertProduct();

        $sale = $this->createFreshSale($user, $branch, $warehouse, $customer, $product);

        return [
            'username' => $user->getUsername(),
            'password' => $password,
            'saleId' => (string) $sale->getId(),
        ];
    }

    public function reset(): void
    {
        $this->purgeE2eSales();

        $user = $this->userRepository->findOneBy(['username' => self::E2E_USERNAME]);
        if ($user instanceof User && $user->getId() !== null) {
            $this->connection->executeStatement('DELETE FROM refresh_token WHERE user_id = :user_id', [
                'user_id' => $user->getId(),
            ]);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }

    private function ensureInitialSetup(): void
    {
        if ($this->initialSetupService->status()->configured) {
            return;
        }

        $input = new InitialSetupInput();
        $input->adminUsername = 'admin';
        $input->adminPassword = 'Admin.3000';
        $input->companyRuc = '20123456789';
        $input->companyBusinessName = 'Empresa Demo CI';
        $input->companyTradeName = 'Demo';
        $input->companyFiscalAddress = 'Av. Principal 123';
        $input->companyPhone = '999999999';
        $input->branchName = 'Principal';
        $input->branchAddress = 'Av. Principal 123';
        $input->branchPhone = '999999999';

        $this->initialSetupService->configure($input);
    }

    private function upsertE2eUser(string $password): User
    {
        $role = $this->resolveUserRole();

        $user = $this->userRepository->findOneBy(['username' => self::E2E_USERNAME]);
        if (!$user instanceof User) {
            $user = new User();
            $user->setUsername(self::E2E_USERNAME);
            $this->entityManager->persist($user);
        }

        $user->setRole($role);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->flush();

        return $user;
    }

    private function upsertBranch(): Branch
    {
        $branch = $this->branchRepository->findOneBy(['name' => self::E2E_BRANCH_NAME]);
        if ($branch instanceof Branch) {
            return $branch;
        }

        $company = $this->companyRepository->findOneBy([]);
        if ($company === null) {
            throw new \RuntimeException('No company found for E2E seed.');
        }

        $branch = new Branch();
        $branch
            ->setName(self::E2E_BRANCH_NAME)
            ->setAddress('CI E2E Address')
            ->setPhone('900000001')
            ->setCompany($company);

        $this->entityManager->persist($branch);
        $this->entityManager->flush();

        return $branch;
    }

    private function upsertWarehouse(Branch $branch): Warehouse
    {
        $warehouse = $this->warehouseRepository->findOneBy(['branch' => $branch]);
        if ($warehouse instanceof Warehouse) {
            return $warehouse;
        }

        $warehouse = new Warehouse();
        $warehouse
            ->setName('Almacen CI E2E')
            ->setBranch($branch);

        $this->entityManager->persist($warehouse);
        $this->entityManager->flush();

        return $warehouse;
    }

    private function ensureVoucherSeries(Branch $branch): void
    {
        $facturaType = $this->voucherTypeRepository->find('01');
        $boletaType = $this->voucherTypeRepository->find('03');

        if ($facturaType instanceof VoucherType) {
            $this->upsertVoucherSeries($branch, $facturaType, 'F001');
        }

        if ($boletaType instanceof VoucherType) {
            $this->upsertVoucherSeries($branch, $boletaType, 'B001');
        }

        $this->entityManager->flush();
    }

    private function upsertVoucherSeries(Branch $branch, VoucherType $voucherType, string $series): void
    {
        $existing = $this->voucherSeriesRepository->findOneBy([
            'branch' => $branch,
            'voucherType' => $voucherType,
        ]);

        if ($existing instanceof VoucherSeries) {
            return;
        }

        $voucherSeries = new VoucherSeries();
        $voucherSeries
            ->setBranch($branch)
            ->setVoucherType($voucherType)
            ->setSeries($series);

        $this->entityManager->persist($voucherSeries);
    }

    private function upsertCustomer(): Customer
    {
        $customer = $this->customerRepository->findOneBy(['ruc' => self::E2E_CUSTOMER_RUC]);
        if ($customer instanceof Customer) {
            return $customer;
        }

        $customer = new Customer();
        $customer
            ->setRuc(self::E2E_CUSTOMER_RUC)
            ->setBusinessName(self::E2E_CUSTOMER_NAME)
            ->setFiscalAddress('CI Address')
            ->setPhone('+51900000001')
            ->setEmail('e2e-ci@example.test');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    private function upsertProduct(): Product
    {
        $product = $this->productRepository->findOneBy(['code' => self::E2E_PRODUCT_CODE]);
        if ($product instanceof Product) {
            return $product;
        }

        $category = $this->categoryRepository->findOneBy([]);
        $unit = $this->unitOfMeasureRepository->findOneBy([]);

        if (!$category instanceof Category || !$unit instanceof UnitOfMeasure) {
            throw new \RuntimeException('No category/unit found for E2E seed.');
        }

        $product = new Product();
        $product
            ->setCode(self::E2E_PRODUCT_CODE)
            ->setType(1)
            ->setName(self::E2E_PRODUCT_NAME)
            ->setCategory($category)
            ->setUnitOfMeasure($unit)
            ->setPurchasePrice('8.00')
            ->setSalePrice('10.00')
            ->setStock('100.000')
            ->setMinStock('0.000');

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function createFreshSale(
        User $user,
        Branch $branch,
        Warehouse $warehouse,
        Customer $customer,
        Product $product,
    ): SaleRecord {
        $this->purgeE2eSales();

        $paymentMethod = $this->resolvePaymentMethod();

        $sale = new SaleRecord();
        $sale
            ->setAlmacen($warehouse)
            ->setBranch($branch)
            ->setCliente($customer)
            ->setTipoId(2)
            ->setFecha(new \DateTime('-1 hour'))
            ->setFormaPago(PaymentMethod::CASH)
            ->setTipoPago($paymentMethod)
            ->setSerie('NV01')
            ->setNumero(sprintf('%08d', random_int(1000, 9999)))
            ->setDescuento('0.00')
            ->setHasInvoice(false)
            ->setCajaId(1)
            ->setEstado(1)
            ->setDateCreated(new DateTimeImmutable())
            ->setSellerUserId((int) $user->getId())
            ->setCreatedBy((int) $user->getId())
            ->setCreatedAt(new \DateTime())
            ->setIgv('18')
            ->setRecargoConsumo(0)
            ->setTipoOperacion('0101')
            ->setSubtotal('10.00')
            ->setTotal('10.00')
            ->setCurrency('PEN')
            ->setExchangeRate('1.000000');

        $item = new SaleItemRecord();
        $item
            ->setSaleRecord($sale)
            ->setProduct($product)
            ->setLineNo(1)
            ->setNameSnapshot($product->getName())
            ->setQty('1.000')
            ->setUnitPrice('10.0000')
            ->setGrossAmount('10.00')
            ->setNetAmount('10.00');

        $this->entityManager->persist($sale);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $sale;
    }

    private function purgeE2eSales(): void
    {
        // Identify E2E sales by the seller (ci_e2e_user). The old `glosa` column
        // was on the legacy `movimiento` table and no longer exists in `sales`.
        $saleIds = $this->connection->fetchFirstColumn(
            'SELECT s.id FROM sales s
             INNER JOIN usuario u ON u.usuario_id = s.seller_user_id
             WHERE u.username = :username',
            ['username' => self::E2E_USERNAME]
        );

        if ($saleIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM tax_document_event WHERE tax_document_id IN '
            . '(SELECT tax_document_id FROM tax_document WHERE sale_id IN (?))',
            [$saleIds],
            [ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            'DELETE FROM tax_document_line WHERE tax_document_id IN '
            . '(SELECT tax_document_id FROM tax_document WHERE sale_id IN (?))',
            [$saleIds],
            [ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            'DELETE FROM tax_document WHERE sale_id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            'DELETE FROM sales WHERE id IN (?)',
            [$saleIds],
            [ArrayParameterType::STRING]
        );
    }

    private function resolveUserRole(): Role
    {
        $role = $this->roleRepository->find(3)
            ?? $this->roleRepository->find(2)
            ?? $this->roleRepository->find(1);

        if (!$role instanceof Role) {
            throw new \RuntimeException('No role found for E2E user.');
        }

        return $role;
    }

    private function resolvePaymentMethod(): PaymentMethod
    {
        $paymentMethod = $this->paymentMethodRepository->find(PaymentMethod::CASH)
            ?? $this->paymentMethodRepository->findOneBy([]);

        if (!$paymentMethod instanceof PaymentMethod) {
            throw new \RuntimeException('No payment method found for sales.');
        }

        return $paymentMethod;
    }

    private function upsertCashRegister(Branch $branch): CashRegister
    {
        $cashRegister = $this->cashRegisterRepository->findOneBy(['branch' => $branch]);
        if ($cashRegister instanceof CashRegister) {
            return $cashRegister;
        }

        $cashRegister = new CashRegister();
        $cashRegister
            ->setName('Caja CI E2E')
            ->setBranch($branch);

        $this->entityManager->persist($cashRegister);
        $this->entityManager->flush();

        return $cashRegister;
    }

    private function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    }
}
