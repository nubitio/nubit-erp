<?php

declare(strict_types=1);

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Activity\ActivityRecorderInterface;
use App\Identity\Activity\ActivityType;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<User, null>
 */
final readonly class UserSoftDeleteProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ActivityRecorderInterface $activity,
    ) {}

    /**
     * @param User $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $firstDeletion = $data->getDeletedAt() === null;
        if ($firstDeletion) {
            $data->setDeletedAt(new DateTimeImmutable());
            // Release the e-mail: the unique index also covers soft-deleted rows.
            $data->setEmail(null);
        }

        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($firstDeletion) {
            $this->activity->record(
                ActivityType::USER_DELETED,
                sprintf('Se eliminó al usuario %s', $data->getUsername()),
                subject: $data->getUsername(),
            );
        }

        return null;
    }
}
