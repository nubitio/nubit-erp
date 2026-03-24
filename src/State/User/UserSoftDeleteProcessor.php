<?php

declare(strict_types=1);

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
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
    ) {
    }

    /**
     * @param User $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data->getDeletedAt() === null) {
            $data->setDeletedAt(new DateTimeImmutable());
        }

        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return null;
    }
}
