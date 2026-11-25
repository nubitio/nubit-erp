<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Repository\TreasuryAccountRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

final class TreasuryAccountCreationController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly TreasuryAccountRepository $accounts,
        private readonly Connection $connection,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/accounts', name: 'treasury_account_create', methods: ['POST'])]
    #[IsGranted('perm:treasury.manage')]
    public function createAccount(Request $request): JsonResponse
    {
        $this->requireCapability();
        $body = $this->body($request);

        $kind = AccountKind::configurable($body['kind'] ?? null) ?? throw new UnprocessableEntityHttpException(
            'Selecciona billetera o fondos por liquidar.',
        );
        $name = trim((string) ($body['name'] ?? ''));
        $currency = strtoupper(trim((string) ($body['currency'] ?? 'PEN')));
        $branchId = isset($body['branchId']) && $body['branchId'] !== '' ? (string) $body['branchId'] : null;
        if ($name === '' || mb_strlen($name) > 120) {
            throw new UnprocessableEntityHttpException(
                'El nombre de la cuenta es obligatorio (máximo 120 caracteres).',
            );
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new UnprocessableEntityHttpException(
                'La moneda debe ser un código de 3 letras, por ejemplo PEN o USD.',
            );
        }
        if (
            $branchId !== null
            && $this->connection->fetchOne('SELECT 1 FROM local WHERE local_id = :id', ['id' => $branchId]) === false
        ) {
            throw new UnprocessableEntityHttpException('La sucursal indicada no existe.');
        }

        $account = new TreasuryAccount(Uuid::v7()->toRfc4122(), $kind, $name, $currency, $branchId, null, null, true);
        $this->accounts->add($account);

        return $this->json(self::account($account) + ['balance' => '0.00'], Response::HTTP_CREATED);
    }
}
