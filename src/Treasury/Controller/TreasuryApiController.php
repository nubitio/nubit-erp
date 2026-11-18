<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Settings\Capability\CapabilityKey;
use App\Settings\Capability\CapabilityNotAvailableException;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Domain\TreasuryMovement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Shared plumbing of the internal Treasury API: the capability gate, the JSON body and the response shapes. */
abstract class TreasuryApiController extends AbstractController
{
    public function __construct(
        private readonly TenantCapabilityResolver $capabilities,
    ) {}

    protected function requireCapability(): void
    {
        try {
            $this->capabilities->require(CapabilityKey::TreasuryAccounts);
        } catch (CapabilityNotAvailableException $e) {
            throw new AccessDeniedHttpException('Las cuentas de dinero no están habilitadas para este negocio.', $e);
        }
    }

    /** @return array<string, mixed> */
    protected function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('El cuerpo de la solicitud debe ser JSON.');
        }

        $body = [];
        foreach ($data as $key => $value) {
            $body[(string) $key] = $value;
        }

        return $body;
    }

    /** @return array<string, mixed> */
    protected static function account(TreasuryAccount $account): array
    {
        return [
            'id' => $account->id,
            'kind' => $account->kind->value,
            'name' => $account->name,
            'currency' => $account->currency,
            'branchId' => $account->branchId,
            'cashRegisterId' => $account->cashRegisterId,
            'bankAccountId' => $account->bankAccountId,
            'active' => $account->active,
        ];
    }

    /** @return array<string, mixed> */
    protected static function movement(TreasuryMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'signedAmount' => $movement->signedAmount,
            'currency' => $movement->currency,
            'occurredAt' => $movement->occurredAt->format('c'),
            'source' => $movement->key->source->value,
            'sourceId' => $movement->key->id,
            'leg' => $movement->key->leg->value,
            'party' => $movement->party === null
                ? null
                : ['type' => $movement->party->type, 'id' => $movement->party->id],
            'reference' => $movement->reference,
            'description' => $movement->description,
            'transferId' => $movement->transferId,
            'correctsMovementId' => $movement->correctsMovementId,
            'origin' => $movement->origin,
        ];
    }
}
