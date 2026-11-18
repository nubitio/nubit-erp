<?php

declare(strict_types=1);

namespace App\Treasury\Controller;

use App\Identity\Entity\User;
use App\Settings\Capability\TenantCapabilityResolver;
use App\Treasury\Domain\TreasuryException;
use App\Treasury\Service\TransferMoney;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TreasuryTransferController extends TreasuryApiController
{
    public function __construct(
        TenantCapabilityResolver $capabilities,
        private readonly TransferMoney $transfers,
    ) {
        parent::__construct($capabilities);
    }

    #[Route('/api/treasury/transfers', name: 'treasury_transfer', methods: ['POST'])]
    #[IsGranted('perm:treasury.transfer')]
    public function transfer(Request $request): JsonResponse
    {
        $this->requireCapability();
        $body = $this->body($request);

        try {
            $date = isset($body['date']) && $body['date'] !== ''
                ? new DateTimeImmutable((string) $body['date'])
                : new DateTimeImmutable();
        } catch (Exception) {
            throw new BadRequestHttpException('La fecha de la transferencia no es válida.');
        }

        $user = $this->getUser();
        try {
            $id = $this->transfers->transfer(
                (string) ($body['fromAccountId'] ?? ''),
                (string) ($body['toAccountId'] ?? ''),
                (string) ($body['amount'] ?? ''),
                $date,
                isset($body['reference']) ? (string) $body['reference'] : null,
                $user instanceof User ? $user->getId() : null,
            );
        } catch (TreasuryException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->json(['transferId' => $id], Response::HTTP_CREATED);
    }
}
