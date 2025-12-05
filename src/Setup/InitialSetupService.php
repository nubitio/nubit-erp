<?php

namespace App\Setup;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Dto\Setup\InitialSetupInput;
use App\Dto\Setup\SetupStatusDto;
use App\Entity\Branch;
use App\Entity\Category;
use App\Entity\Company;
use App\Entity\Customer;
use App\Entity\LogisticOperation;
use App\Entity\PaymentMethod;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\VoucherType;
use App\Repository\BranchRepository;
use App\Repository\CategoryRepository;
use App\Repository\CompanyRepository;
use App\Repository\CustomerRepository;
use App\Repository\LogisticOperationRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherTypeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InitialSetupService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly CompanyRepository $companyRepository,
        private readonly BranchRepository $branchRepository,
        private readonly RoleRepository $roleRepository,
        private readonly LogisticOperationRepository $logisticOperationRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly VoucherTypeRepository $voucherTypeRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly Connection $connection,
        private readonly SettingsService $settingsService,
    ) {
    }

    public function status(): SetupStatusDto
    {
        $userCount = $this->userRepository->count([]);
        $companyCount = $this->companyRepository->count([]);
        $branchCount = $this->branchRepository->count([]);

        return new SetupStatusDto(
            configured: $userCount > 0 && $companyCount > 0 && $branchCount > 0,
            userCount: $userCount,
            companyCount: $companyCount,
            branchCount: $branchCount,
        );
    }

    /**
     * @throws Exception
     */
    public function configure(InitialSetupInput $input): SetupStatusDto
    {
        $status = $this->status();
        if ($status->configured || $status->userCount > 0) {
            throw new BadRequestHttpException('La configuración inicial ya fue completada.');
        }

        $this->connection->beginTransaction();
        try {
            $superAdminRole = $this->ensureDefaultRoles();

            $company = (new Company())
                ->setRuc($input->companyRuc)
                ->setBusinessName($input->companyBusinessName)
                ->setTradeName($input->companyTradeName)
                ->setFiscalAddress($input->companyFiscalAddress)
                ->setPhone($input->companyPhone);

            $this->entityManager->persist($company);

            $branch = (new Branch())
                ->setName($input->branchName)
                ->setAddress($input->branchAddress)
                ->setPhone($input->branchPhone)
                ->setCompany($company);

            $this->entityManager->persist($branch);

            $user = (new User())
                ->setUsername($input->adminUsername)
                ->setRole($superAdminRole);
            $hashedPassword = $this->passwordHasher->hashPassword($user, $input->adminPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->maybeSeedSettings($company);
            $this->seedReferenceData();
            $this->assignFullMenuToRole(intval($superAdminRole->getId()));

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $this->status();
    }

    private function ensureDefaultRoles(): Role
    {
        $roles = [
            1 => 'Super Administrador',
            2 => 'Administrador',
            3 => 'Cajero',
        ];

        foreach ($roles as $id => $name) {
            $this->connection->executeStatement(
                'INSERT INTO rol (rol_id, descripcion)
                VALUES (?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)',
                [$id, $name]
            );
        }

        $superAdmin = $this->roleRepository->find(1) ?? $this->roleRepository->findOneBy(['name' => $roles[1]]);

        if (null === $superAdmin) {
            throw new \RuntimeException('No se pudo preparar el rol de super administrador.');
        }

        return $superAdmin;
    }

    private function maybeSeedSettings(Company $company): void
    {
        try {
            $this->settingsService->set(Constants::COMPANY_ID, (string)$company->getRuc());
            $this->settingsService->set(Constants::COMPANY_NAME, (string)$company->getBusinessName());
        } catch (\Throwable) {
            // Settings table might not be ready yet; fail silently to avoid blocking setup.
        }
    }

    /**
     * Grants the given role access to all menus.
     *
     * @throws Exception
     */
    private function assignFullMenuToRole(int $roleId): void
    {
        $menuIds = $this->connection->fetchFirstColumn('SELECT id FROM menu');

        foreach ($menuIds as $menuId) {
            $this->connection->executeStatement(
                'INSERT INTO menu_rol (menu_id, role_id)
                VALUES (?, ?) ON DUPLICATE KEY UPDATE menu_id = VALUES(menu_id), role_id = VALUES(role_id)',
                [$menuId, $roleId]
            );
        }
    }

    /**
     * Seed core reference data needed for first use.
     */
    private function seedReferenceData(): void
    {
        // Logistic operations (tipo_id: 1 venta, 2 compra, 3 entrada, 4 salida)
        $operations = [
            ['Venta', 1],
            ['Compra', 2],
            ['Ingreso', 3],
            ['Salida', 4],
            ['Transferencia', 5],
        ];
        foreach ($operations as [$name, $type]) {
            $this->getOrCreateLogisticOperation($name, $type);
        }

        // Payment methods including digital wallets
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

        // Voucher types (SUNAT)
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

        // Default category
        $this->getOrCreateCategory('General');

        // Default customer
        $this->getOrCreateCustomer('00000000', 'Cliente General');
    }

    private function getOrCreateLogisticOperation(string $name, int $type): void
    {
        $existing = $this->logisticOperationRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $operation = (new LogisticOperation())
            ->setName($name)
            ->setType($type);

        $this->entityManager->persist($operation);
        $this->entityManager->flush();
    }

    private function getOrCreatePaymentMethod(string $name): void
    {
        $existing = $this->paymentMethodRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $paymentMethod = (new PaymentMethod())->setName($name);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();
    }

    private function getOrCreateVoucherType(string $code, string $name): void
    {
        $existing = $this->voucherTypeRepository->find($code);
        if ($existing) {
            return;
        }

        $voucherType = (new VoucherType())
            ->setCode($code)
            ->setName($name);
        $this->entityManager->persist($voucherType);
        $this->entityManager->flush();
    }

    private function getOrCreateCategory(string $name): void
    {
        $existing = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($existing) {
            return;
        }

        $category = (new Category())->setName($name);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
    }

    private function getOrCreateCustomer(string $ruc, string $businessName): void
    {
        $existing = $this->customerRepository->find($ruc);
        if ($existing) {
            return;
        }

        $customer = (new Customer())
            ->setRuc($ruc)
            ->setBusinessName($businessName)
            ->setFiscalAddress('N/A');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();
    }
}
