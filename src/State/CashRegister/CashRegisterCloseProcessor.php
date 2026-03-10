<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Application\Session\Security;
use App\Core\Http\ApiResponse;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Entity\CashSession;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProcessorInterface<CashRegisterCloseDto, ApiResponse>
 */
readonly class CashRegisterCloseProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private CashSessionRepository $cashSessionRepository,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param CashRegisterCloseDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $closureItems = $data->closureItems ?? [];
        $notes = [];

        if (!empty($closureItems)) {
            $expectedTotal = BigDecimal::zero();
            $countedTotal = BigDecimal::zero();
            foreach ($closureItems as $item) {
                $expectedTotal = $expectedTotal->plus(BigDecimal::of($item['expectedAmount'] ?? '0'));
                $countedTotal = $countedTotal->plus(BigDecimal::of($item['countedAmount'] ?? '0'));
                if (!empty($item['note'])) {
                    $notes[] = trim((string) $item['note']);
                }
            }

            $differenceTotal = $countedTotal->minus($expectedTotal);
        } else {
            $expectedTotal = BigDecimal::of($data->expectedAmount ?? '0');
            $countedTotal = BigDecimal::of($data->countedAmount ?? '0');
            $differenceTotal = isset($data->difference)
                ? BigDecimal::of($data->difference)
                : $countedTotal->minus($expectedTotal);
            if (!empty($data->note)) {
                $notes[] = trim((string) $data->note);
            }
        }

        $expectedStr = $expectedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
        $countedStr = $countedTotal->toScale(2, RoundingMode::HALF_UP)->__toString();
        $differenceStr = $differenceTotal->toScale(2, RoundingMode::HALF_UP)->__toString();

        try {
            // Attempt 1: direct lookup by cash_sessions.id
            $cashSession = $this->entityManager->find(CashSession::class, $uriVariables['id']);

            // Attempt 2: backward compat — session was opened by legacy code, look up via legacy_cash_shift_id
            if (!$cashSession instanceof CashSession) {
                $cashSession = $this->cashSessionRepository->findByLegacyCashShiftId((int) $uriVariables['id']);
            }

            if (!$cashSession instanceof CashSession) {
                throw new NotFoundHttpException('Sesión de caja no encontrada.');
            }

            $authUser = $this->security->connectedUser();
            if ($authUser === null) {
                throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
            }

            $userRef = $this->entityManager->getReference(User::class, $authUser->userId);

            $cashSession->setCountedAmount($countedStr);
            $cashSession->setExpectedAmount($expectedStr);
            $cashSession->setDifferenceAmount($differenceStr);
            $cashSession->setCloseNote($notes !== [] ? implode('; ', $notes) : null);
            $cashSession->setStatus('CLOSED');
            $cashSession->setClosedBy($userRef instanceof User ? $userRef : null);
            $cashSession->setClosedAt(new DateTimeImmutable());

            $this->entityManager->persist($cashSession);
            $this->entityManager->flush();
        } catch (ORMException $ormException) {
            return new ApiResponse(
                success: false,
                message: 'Error al cerrar la caja',
                data: $ormException->getMessage(),
            );
        }

        return new ApiResponse(
            success: true,
            message: 'Caja cerrada correctamente',
        );
    }
}
