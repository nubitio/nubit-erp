<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Context\BranchContextInterface;
use App\AI\Entity\AssistantProposedAction;
use App\AI\Service\ProposedActionFactory;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Entity\User;
use App\Settings\Runtime\TenantRuntimeConfigProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;
use Throwable;

#[AsTool(
    name: 'propose_sale',
    description: 'Propose registering a POS sale. Does NOT save or emit a CPE. '
    . 'itemsJson is a JSON array of {productId|code|name, qty}. '
    . 'Optional customerRuc (DNI/RUC), paymentMethodId (default cash), emitInvoice (default true = boleta). '
    . 'The UI shows Confirmar / Descartar. Requires an open cash session.',
    method: 'proposeSale',
)]
final readonly class ProposeSaleTool
{
    public function __construct(
        private ProposedActionFactory $factory,
        private ProductRepository $productRepository,
        private CustomerRepository $customerRepository,
        private EntityManagerInterface $entityManager,
        private BranchContextInterface $branchContext,
        private TenantRuntimeConfigProvider $runtimeConfigProvider,
        private Security $security,
    ) {}

    /**
     * @return array{error: string}|array{id: string, kind: string, summary: string, payload: array<string, mixed>}
     */
    public function proposeSale(
        string $itemsJson,
        ?string $customerRuc = null,
        ?int $paymentMethodId = null,
        bool $emitInvoice = true,
    ): array {
        $parsed = json_decode($itemsJson, true);
        if (!is_array($parsed) || $parsed === []) {
            return ['error' => 'itemsJson debe ser un JSON array de líneas, por ejemplo [{"name":"Coca Cola","qty":2}].'];
        }

        $cashError = $this->requireOpenCashSession();
        if ($cashError !== null) {
            return ['error' => $cashError];
        }

        $lines = [];
        $total = 0.0;
        foreach ($parsed as $index => $raw) {
            if (!is_array($raw)) {
                return ['error' => sprintf('La línea %d no es un objeto.', $index + 1)];
            }
            $resolved = $this->resolveLine($raw);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            $lines[] = $resolved;
            $total += (float) $resolved['qty'] * (float) $resolved['unitPrice'];
        }

        $customer = $this->resolveCustomer($customerRuc);
        if (isset($customer['error'])) {
            return $customer;
        }

        $methodId = $paymentMethodId ?? PaymentMethod::CASH;
        $method = $this->entityManager->find(PaymentMethod::class, $methodId);
        if (!$method instanceof PaymentMethod) {
            return ['error' => 'Método de pago no encontrado.'];
        }

        $warehouseId = $this->defaultWarehouseId();
        if ($warehouseId === null) {
            return ['error' => 'No hay almacén por defecto para registrar la venta.'];
        }

        $payload = [
            'warehouseId' => $warehouseId,
            'customerId' => $customer['id'] ?? null,
            'customerName' => $customer['name'] ?? null,
            'paymentMethodId' => $methodId,
            'paymentMethodName' => $method->getName(),
            'emitInvoice' => $emitInvoice,
            'items' => $lines,
            'total' => round($total, 2),
        ];

        $itemSummary = implode(', ', array_map(
            static fn(array $line): string => sprintf('%sx %s', $line['qty'], $line['name']),
            $lines,
        ));
        $doc = $emitInvoice ? 'Boleta' : 'Nota de venta';
        $who = is_string($payload['customerName']) ? ' · ' . $payload['customerName'] : '';
        $summary = sprintf('%s · %s · S/ %s%s', $doc, $itemSummary, number_format($total, 2, '.', ''), $who);

        $action = $this->factory->propose(AssistantProposedAction::KIND_CREATE_SALE, $summary, $payload);

        return $action->toCard();
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{error: string}|array{productId: string, name: string, qty: float, unitPrice: string}
     */
    private function resolveLine(array $raw): array
    {
        $qty = $raw['qty'] ?? $raw['quantity'] ?? 1;
        if (!is_numeric($qty) || (float) $qty <= 0) {
            return ['error' => 'Cada línea necesita una cantidad mayor que cero.'];
        }

        $product = null;
        $productId = isset($raw['productId']) ? trim((string) $raw['productId']) : '';
        if ($productId !== '') {
            $found = $this->productRepository->find($productId);
            $product = $found instanceof Product ? $found : null;
        }

        if (!$product instanceof Product) {
            $term = trim((string) ($raw['code'] ?? $raw['name'] ?? ''));
            if ($term === '') {
                return ['error' => 'Cada línea necesita productId, code o name.'];
            }
            $matches = $this->productRepository->searchByTerm($term, 1);
            if ($matches === []) {
                return ['error' => sprintf('No encontré el producto "%s".', $term)];
            }
            $found = $this->productRepository->find($matches[0]->id);
            $product = $found instanceof Product ? $found : null;
        }

        if (!$product instanceof Product) {
            return ['error' => 'Producto no encontrado.'];
        }

        return [
            'productId' => (string) $product->getId(),
            'name' => $product->getName(),
            'qty' => (float) $qty,
            'unitPrice' => $product->getSalePrice(),
        ];
    }

    /**
     * @return array{error: string}|array{id: int|null, name: string|null}
     */
    private function resolveCustomer(?string $customerRuc): array
    {
        if ($customerRuc === null || trim($customerRuc) === '') {
            return ['id' => null, 'name' => null];
        }

        $ruc = trim($customerRuc);
        $customer = $this->customerRepository->findOneBy(['ruc' => $ruc]);
        if (!$customer instanceof Customer) {
            return ['error' => sprintf('No hay un cliente con documento %s. Créalo primero con propose_customer.', $ruc)];
        }

        return ['id' => $customer->getId(), 'name' => $customer->getBusinessName()];
    }

    private function requireOpenCashSession(): ?string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return 'Se requiere un usuario autenticado.';
        }

        try {
            $open = (int) $this->entityManager->createQuery(<<<'DQL'
                SELECT COUNT(cs.id)
                FROM App\CashRegister\Entity\CashSession cs
                WHERE cs.openedBy = :userId AND cs.status = :status
                DQL)
                ->setParameter('userId', $user->getId())
                ->setParameter('status', 'OPEN')
                ->getSingleScalarResult();
        } catch (Throwable) {
            return 'No pude verificar si la caja está abierta.';
        }

        if ($open < 1) {
            return 'La caja debe estar abierta para registrar una venta.';
        }

        return null;
    }

    private function defaultWarehouseId(): ?string
    {
        try {
            $config = $this->runtimeConfigProvider->getConfig();
            $defaults = is_array($config['defaults'] ?? null) ? $config['defaults'] : [];
            $warehouse = is_array($defaults['warehouse'] ?? null) ? $defaults['warehouse'] : [];
            $id = $warehouse['id'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        } catch (Throwable) {
            return $this->branchContext->getCurrentBranchId();
        }
    }
}
