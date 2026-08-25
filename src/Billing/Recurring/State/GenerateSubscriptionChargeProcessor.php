<?php

declare(strict_types=1);

namespace App\Billing\Recurring\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Billing\Recurring\Dto\GenerateSubscriptionChargeDto;
use App\Billing\Recurring\Entity\CustomerSubscription;
use App\Billing\Recurring\Entity\CustomerSubscriptionRun;
use App\Billing\Recurring\Service\SubscriptionChargeGenerator;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/** @implements ProcessorInterface<GenerateSubscriptionChargeDto, CustomerSubscriptionRun> */
final readonly class GenerateSubscriptionChargeProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SubscriptionChargeGenerator $generator,
    ) {}

    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): CustomerSubscriptionRun {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }
        if (!$this->security->hasPermission('subscriptions.generate')) {
            throw new AccessDeniedHttpException('No tienes permiso para generar cargos de suscripción.');
        }

        $subscription = $this->entityManager->find(CustomerSubscription::class, $data->id);
        if (!$subscription instanceof CustomerSubscription) {
            throw new NotFoundHttpException('Suscripción no encontrada.');
        }
        if ($subscription->getStatus() !== 'active') {
            throw new ConflictHttpException('Solo se pueden generar cargos para suscripciones activas.');
        }
        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
        if ($subscription->getNextBillingDate() > $today) {
            throw new ConflictHttpException(sprintf(
                'El próximo cargo corresponde al %s y todavía no está vencido.',
                $subscription->getNextBillingDate()->format('d/m/Y'),
            ));
        }

        return $this->generator->generate(
            $subscription,
            $today,
            $user->userId,
            'manual',
        );
    }
}
