<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Core\Exception\ServiceException;
use App\Dto\Assistant\CashRegisterStatusDto;
use App\Entity\CashRegister;
use App\Entity\CashShift;
use App\Enum\CashShiftStatus;
use App\Repository\CashRegisterRepository;
use App\Repository\CashShiftRepository;
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
        private CashShiftRepository $cashShiftRepository,
    ) {
    }

    public function __invoke(int $cashRegisterId): CashRegisterStatusDto
    {
        /** @var CashRegister|null $register */
        $register = $this->cashRegisterRepository->find($cashRegisterId);
        if (!$register instanceof CashRegister) {
            throw ServiceException::create('Caja no encontrada.');
        }

        /** @var CashShift|null $latestShift */
        $latestShift = $this->cashShiftRepository->createQueryBuilder('cs')
            ->addSelect('cr')
            ->join('cs.cashRegister', 'cr')
            ->where('cr.id = :id')
            ->setParameter('id', $cashRegisterId)
            ->orderBy('cs.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $status = $latestShift?->getStatus()->value ?? CashShiftStatus::CLOSED->value;

        return new CashRegisterStatusDto(
            $register->getId() ?? 0,
            $register->getName(),
            $status,
            $latestShift?->getId(),
            $latestShift?->getOpenedAt()?->format(\DateTimeInterface::ATOM),
            $latestShift?->getOpenedBy()?->getUsername(),
            $latestShift?->getClosedAt()?->format(\DateTimeInterface::ATOM),
            $latestShift?->getOpeningBalance(),
            $latestShift?->getExpectedAmount(),
            $latestShift?->getCountedAmount(),
            $latestShift?->getDifference(),
        );
    }
}
