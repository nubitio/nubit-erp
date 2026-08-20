<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AI\Dto\CashRegisterStatusDto;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Repository\CashRegisterRepository;
use App\CashRegister\Repository\CashSessionRepository;
use DateTimeInterface;
use Nubit\Platform\Exception\ServiceException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cash_register_status',
    description: 'Return the latest status of a cash register (open/closed) and related shift details.',
    method: 'cashRegisterStatus',
)]
final readonly class CashRegisterTools
{
    public function __construct(
        private CashRegisterRepository $cashRegisterRepository,
        private CashSessionRepository $cashSessionRepository,
    ) {}

    public function cashRegisterStatus(int $cashRegisterId): CashRegisterStatusDto
    {
        /** @var CashRegister|null $register */
        $register = $this->cashRegisterRepository->find($cashRegisterId);
        if (!$register instanceof CashRegister) {
            throw ServiceException::create('Caja no encontrada.');
        }

        $latestSession = $this->cashSessionRepository->findLatestByCashRegister($cashRegisterId);

        $status = $latestSession?->getStatus() ?? 'CLOSED';

        $openingBalance = $latestSession?->getOpeningAmount();
        $expectedAmount = $latestSession?->getExpectedAmount();
        $countedAmount = $latestSession?->getCountedAmount();
        $difference = $latestSession?->getDifferenceAmount();

        return new CashRegisterStatusDto(
            $register->getId() ?? 0,
            $register->getName(),
            $status,
            $latestSession?->getId(),
            $latestSession?->getOpenedAt()?->format(DateTimeInterface::ATOM),
            $latestSession?->getOpenedBy()->getUsername(),
            $latestSession?->getClosedAt()?->format(DateTimeInterface::ATOM),
            $openingBalance !== null ? (float) $openingBalance : null,
            $expectedAmount !== null ? (float) $expectedAmount : null,
            $countedAmount !== null ? (float) $countedAmount : null,
            $difference !== null ? (float) $difference : null,
        );
    }
}
