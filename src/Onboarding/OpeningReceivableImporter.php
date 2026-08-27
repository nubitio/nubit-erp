<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class OpeningReceivableImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CustomerRepository $customerRepository,
        private BranchRepository $branchRepository,
        private WarehouseRepository $warehouseRepository,
        private PaymentMethodRepository $paymentMethodRepository,
        private BranchContextServiceInterface $branchContext,
        private Security $security,
    ) {}

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array{created: int, errors: list<array{row: int, message: string}>}
     */
    public function import(array $rows): array
    {
        $branch = $this->resolveBranch();
        $warehouse = $this->resolveWarehouse($branch);
        $paymentMethod =
            $this->paymentMethodRepository->findOneBy(['name' => 'Credito']) ?? $this->paymentMethodRepository->findBy(
                [],
                ['id' => 'ASC'],
                1,
            )[0] ?? null;
        $user = $this->security->getUser();
        $userId = $user instanceof User ? (int) $user->getId() : 0;
        $now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $raw) {
            $parsed = OpeningBalanceRow::tryFrom(is_array($raw) ? $raw : []);
            if (is_string($parsed)) {
                $errors[] = ['row' => $index + 1, 'message' => $parsed];
                continue;
            }

            try {
                $customer = $this->getOrCreateCustomer($parsed);
                $sale = $this->buildSale($parsed, $customer, $branch, $warehouse, $paymentMethod, $userId, $now);
                $this->entityManager->persist($sale);
                ++$created;
            } catch (RuntimeException $exception) {
                $errors[] = ['row' => $index + 1, 'message' => $exception->getMessage()];
            }
        }

        $this->entityManager->flush();

        return ['created' => $created, 'errors' => $errors];
    }

    private function resolveBranch(): Branch
    {
        $branchId = $this->branchContext->getCurrentBranchId();
        $branch = $branchId !== null
            ? $this->branchRepository->find($branchId)
            : $this->branchRepository->findOneBy([]);
        if (!$branch instanceof Branch) {
            throw new RuntimeException('No hay un local seleccionado para importar saldos.');
        }

        return $branch;
    }

    private function resolveWarehouse(Branch $branch): Warehouse
    {
        $warehouse = $this->warehouseRepository->findOneBy([
            'branch' => $branch,
        ]) ?? $this->warehouseRepository->findOneBy([]);
        if (!$warehouse instanceof Warehouse) {
            throw new RuntimeException('No hay un almacén para importar saldos.');
        }

        return $warehouse;
    }

    private function getOrCreateCustomer(OpeningBalanceRow $row): Customer
    {
        $existing = $this->customerRepository->findOneBy(['ruc' => $row->ruc]);
        if ($existing instanceof Customer) {
            if ($row->phone !== null && $existing->getPhone() === null) {
                $existing->setPhone($row->phone);
            }

            return $existing;
        }

        $customer = new Customer()
            ->setRuc($row->ruc)
            ->setBusinessName($row->name)
            ->setPhone($row->phone)
            ->setIsActive(true);
        $this->entityManager->persist($customer);

        return $customer;
    }

    private function buildSale(
        OpeningBalanceRow $row,
        Customer $customer,
        Branch $branch,
        Warehouse $warehouse,
        ?PaymentMethod $paymentMethod,
        int $userId,
        DateTimeImmutable $now,
    ): SaleRecord {
        $issuedDate = $now->setTime(0, 0);
        $issuedAt = DateTime::createFromImmutable($now);
        $number =
            $row->number !== null && $row->number !== ''
                ? $row->number
                : 'SI-' . substr(bin2hex(random_bytes(4)), 0, 8);

        $sale = new SaleRecord()
            ->setWarehouse($warehouse)
            ->setAlmacen($warehouse)
            ->setBranch($branch)
            ->setCliente($customer)
            ->setFecha($issuedAt)
            ->setIssuedAt($issuedAt)
            ->setIssuedDate($issuedDate)
            ->setDueDate($row->dueDate)
            ->setFormaPago(2)
            ->setCreditSale(true)
            ->setTipoPago($paymentMethod)
            ->setHasInvoice(false)
            ->setEstado(1)
            ->setStatus('COMPLETED')
            ->setCurrency('PEN')
            ->setSubtotal($row->amount)
            ->setTaxTotal('0.00')
            ->setTotal($row->amount)
            ->setGrandTotal($row->amount)
            ->setPaidTotal('0.00')
            ->setBalanceDue($row->amount)
            ->setCreatedBy($userId)
            ->setSellerUserId($userId)
            ->setNote('Saldo inicial importado desde Excel. No es un comprobante SUNAT.')
            ->setInvoice($number)
            ->setSerie('SI')
            ->setNumero($number);

        $item = new SaleItemRecord()
            ->setLineNo(1)
            ->setQty('1.000')
            ->setUnitPrice($row->amount)
            ->setGrossAmount($row->amount)
            ->setTaxAmount('0.00')
            ->setNetAmount($row->amount)
            ->setDiscountAmount('0.00')
            ->setNameSnapshot('Saldo inicial')
            ->setSkuSnapshot($number);
        $sale->addSaleItemRecord($item);

        return $sale;
    }
}
