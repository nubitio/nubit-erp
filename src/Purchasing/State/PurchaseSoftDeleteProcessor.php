<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\Purchase;
use DateTime;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class PurchaseSoftDeleteProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {
    }

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Purchase) {
            return null;
        }

        if ($data->getDeletedAt() === null) {
            $data->setDeletedAt(new DateTime());
        }

        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return null;
    }
}
