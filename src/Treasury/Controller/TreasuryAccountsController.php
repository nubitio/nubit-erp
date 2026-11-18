<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Repository\TreasuryAccountRepository;
use App\Treasury\Repository\TreasuryMovementRepository;
use App\Treasury\Service\PositionQuery;
use App\Treasury\Service\TreasuryAccountSeeder;
use App\Treasury\Service\TreasuryMovementRecorder;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** Money accounts: the position, wallets, the sync of accounts a tenant already has, and an account's movements. */
final class TreasuryAccountsController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly TreasuryAccountRepository $accounts,
        private readonly TreasuryMovementRepository $movements,
        private readonly PositionQuery $position,
        private readonly TreasuryAccountSeeder $seeder,
        private readonly TreasuryMovementRecorder $recorder,
        private readonly Connection $connection,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/accounts', name: 'treasury_accounts', methods: ['GET'])]
    #[IsGranted('perm:treasury.view')]
    public function accounts(): JsonResponse
    {
        $this->requireCapability();

        return $this->json([
            'accounts' => array_map(
                fn(array $row): array => self::account($row['account']) + ['balance' => $row['balance']],
                $this->position->accounts(),
            ),
            'totalsByCurrency' => $this->position->totalsByCurrency(),
        ]);
    }

    #[Route('/api/treasury/accounts', name: 'treasury_account_create', methods: ['POST'])]
    #[IsGranted('perm:treasury.manage')]
    public function createWallet(Request $request): JsonResponse
    {
        $this->requireCapability();
        $body = $this->body($request);

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

        $account = new TreasuryAccount(
            Uuid::v7()->toRfc4122(),
            AccountKind::Wallet,
            $name,
            $currency,
            $branchId,
            null,
            null,
            true,
        );
        $this->accounts->add($account);

        return $this->json(self::account($account) + ['balance' => '0.00'], Response::HTTP_CREATED);
    }

    /** Creates the accounts the tenant is missing (registers, banks, suspense) and the unambiguous default mappings. */
    #[Route('/api/treasury/accounts/sync', name: 'treasury_accounts_sync', methods: ['POST'])]
    #[IsGranted('perm:treasury.manage')]
    public function sync(): JsonResponse
    {
        $this->requireCapability();

        return $this->json($this->seeder->sync());
    }

    /** The balance an account starts with when the tenant begins using Treasury. It can be set once. */
    #[Route('/api/treasury/accounts/{id}/opening-balance', name: 'treasury_opening_balance', methods: ['POST'])]
    #[IsGranted('perm:treasury.manage')]
    public function openingBalance(string $id, Request $request): JsonResponse
    {
        $this->requireCapability();
        $body = $this->body($request);
        $account = $this->accounts->find($id) ?? throw new NotFoundHttpException('La cuenta no existe.');
        if (!in_array($account->kind, [AccountKind::Bank, AccountKind::Wallet], true)) {
            throw new UnprocessableEntityHttpException(
                'Solo las cuentas bancarias y billeteras tienen saldo inicial; las cajas usan su sesión.',
            );
        }

        try {
            $date = isset($body['date']) && $body['date'] !== ''
                ? new DateTimeImmutable((string) $body['date'])
                : new DateTimeImmutable();
        } catch (Exception) {
            throw new UnprocessableEntityHttpException('La fecha del saldo inicial no es válida.');
        }

        try {
            $result = $this->recorder->record(new MoneyMovementRequest(
                key: new MovementKey(MovementSource::OpeningBalance, $account->id),
                direction: Direction::In,
                amount: (string) ($body['amount'] ?? ''),
                currency: $account->currency,
                branchId: $account->branchId ?? '',
                occurredAt: $date,
                description: 'Saldo inicial',
                accountId: $account->id,
            ));
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
        if ($result->duplicate) {
            throw new UnprocessableEntityHttpException(
                'Esta cuenta ya tiene un saldo inicial; para corregirlo registra un ajuste.',
            );
        }

        return $this->json(['movementId' => $result->movementId], Response::HTTP_CREATED);
    }

    #[Route('/api/treasury/accounts/{id}/movements', name: 'treasury_account_movements', methods: ['GET'])]
    #[IsGranted('perm:treasury.view')]
    public function movementsOf(string $id, Request $request): JsonResponse
    {
        $this->requireCapability();
        $account = $this->accounts->find($id) ?? throw new NotFoundHttpException('La cuenta no existe.');
        if ($account->kind === AccountKind::CashRegister) {
            // Drawer movements live in the cash register ledger (D-A); the cash screens show them.
            return $this->json([
                'account' => self::account($account),
                'movements' => [],
                'note' => 'Los movimientos de caja se ven en la sesión de caja.',
            ]);
        }

        $limit = min(200, max(1, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));

        return $this->json([
            'account' => self::account($account),
            'movements' => array_map(self::movement(...), $this->movements->recent($id, $limit, $offset)),
        ]);
    }
}
