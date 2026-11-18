<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Repository\TreasuryAccountRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Which account each payment method lands in, per branch and currency (decision 4). Cash is not mapped. */
final class TreasuryMappingController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly TreasuryAccountRepository $accounts,
        private readonly Connection $connection,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/payment-method-accounts', name: 'treasury_mapping', methods: ['GET'])]
    #[IsGranted('perm:treasury.view')]
    public function mapping(): JsonResponse
    {
        $this->requireCapability();

        return $this->json([
            'branches' => array_map(static fn(array $row): array => [
                'id' => (string) $row['local_id'],
                'name' => (string) $row['descripcion'],
            ], $this->connection->fetchAllAssociative('SELECT local_id, descripcion FROM local WHERE is_active = 1 ORDER BY descripcion')),
            'paymentMethods' => array_map(static fn(array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ], $this->connection->fetchAllAssociative('SELECT id, name FROM payment_method WHERE is_active = 1 ORDER BY id')),
            'mappings' => $this->accounts->mappings(),
        ]);
    }

    #[Route('/api/treasury/payment-method-accounts', name: 'treasury_mapping_set', methods: ['PUT'])]
    #[IsGranted('perm:treasury.manage')]
    public function setMapping(Request $request): JsonResponse
    {
        $this->requireCapability();
        $body = $this->body($request);

        $branchId = (string) ($body['branchId'] ?? '');
        $methodId = (int) ($body['paymentMethodId'] ?? 0);
        $currency = strtoupper((string) ($body['currency'] ?? ''));
        $accountId = isset($body['accountId']) && $body['accountId'] !== '' ? (string) $body['accountId'] : null;

        if ($this->connection->fetchOne('SELECT 1 FROM local WHERE local_id = :id', ['id' => $branchId]) === false) {
            throw new UnprocessableEntityHttpException('La sucursal indicada no existe.');
        }
        if ($this->connection->fetchOne('SELECT 1 FROM payment_method WHERE id = :id', ['id' => $methodId]) === false) {
            throw new UnprocessableEntityHttpException('El método de pago indicado no existe.');
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new UnprocessableEntityHttpException('La moneda debe ser un código de 3 letras.');
        }

        if ($accountId === null) {
            $this->accounts->unmap($branchId, $methodId, $currency);

            return $this->json(['mapped' => false]);
        }

        $account = $this->accounts->find($accountId);
        if (
            $account === null
            || !$account->active
            || !in_array($account->kind, [AccountKind::Bank, AccountKind::Wallet], true)
        ) {
            throw new UnprocessableEntityHttpException('Elige una cuenta bancaria o billetera activa.');
        }
        if ($account->currency !== $currency) {
            throw new UnprocessableEntityHttpException(sprintf(
                'La cuenta es en %s y el mapeo es en %s.',
                $account->currency,
                $currency,
            ));
        }
        $this->accounts->map($branchId, $methodId, $currency, $accountId);

        return $this->json(['mapped' => true]);
    }
}
